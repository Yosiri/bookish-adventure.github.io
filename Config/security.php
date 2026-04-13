<?php
// =============================================================
// UMDC – Security & Helper Functions
// =============================================================
defined('UMDC_APP') or define('UMDC_APP', true);

// ── Session ───────────────────────────────────────────────────
// Each role gets its OWN session name so multiple accounts can be
// open simultaneously in different browser tabs without overwriting
// each other. The session to load is chosen via ?_sess= URL param
// OR via a per-role cookie set at login time.
//
// Session name mapping:
//   UMDC_ADMIN  → admin accounts
//   UMDC_ORG    → organization accounts
//   UMDC_USER   → donor accounts
//   UMDC_SES    → default / unknown (login page, public pages)

function _umdc_session_name_for_role(string $role): string {
    $map = [
        'admin'        => 'UMDC_ADMIN',
        'organization' => 'UMDC_ORG',
        'user'         => 'UMDC_USER',
    ];
    return $map[$role] ?? 'UMDC_SES';
}

function umdc_session_start(?string $forceRole = null): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Determine which session to open.
    // Priority: 1) explicit role arg  2) _sess URL param  3) detect from existing cookies
    if ($forceRole) {
        $name = _umdc_session_name_for_role($forceRole);
    } elseif (!empty($_GET['_sess']) && in_array($_GET['_sess'], ['UMDC_ADMIN','UMDC_ORG','UMDC_USER'], true)) {
        $name = $_GET['_sess'];
    } else {
        // Auto-detect: try each role cookie and pick the one that exists
        $detected = 'UMDC_SES';
        foreach (['UMDC_ADMIN','UMDC_ORG','UMDC_USER'] as $n) {
            if (!empty($_COOKIE[$n])) { $detected = $n; break; }
        }
        $name = $detected;
    }

    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',   // Lax (not Strict) so redirects carry the cookie
    ]);
    session_start();
}

// Called right after a successful login so we can switch to the
// role-specific session before writing any session data.
function umdc_start_role_session(string $role): void {
    // Destroy whatever generic session was open (login page session)
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $name = _umdc_session_name_for_role($role);
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    session_regenerate_id(true);
}

// Returns the _sess param to append to dashboard URLs so the browser
// tab always loads the correct session for that role.
function umdc_sess_param(string $role): string {
    return '?_sess=' . _umdc_session_name_for_role($role);
}

// ── CSRF ──────────────────────────────────────────────────────
function csrf_token(): string {
    umdc_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), $token);
}

function csrf_check(): void {
    if (!csrf_verify()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']));
    }
}

// ── Auth Middleware ────────────────────────────────────────────
function requireRole(string $role): void {
    umdc_session_start();
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: /public/login.html');
        exit;
    }
    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        header('Location: /public/unauthorized.html');
        exit;
    }
}

function requireAnyRole(array $roles): void {
    umdc_session_start();
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: /public/login.html');
        exit;
    }
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        header('Location: /public/unauthorized.html');
        exit;
    }
}

function isLoggedIn(): bool {
    umdc_session_start();
    return isset($_SESSION['user_id']);
}

function currentUserId(): ?int {
    umdc_session_start();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function currentRole(): ?string {
    umdc_session_start();
    return $_SESSION['role'] ?? null;
}

// ── Output Sanitization ───────────────────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitizeInput(string $str): string {
    return trim(strip_tags($str));
}

// ── Rate Limiting (simple file-based) ────────────────────────
function rateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $dir  = sys_get_temp_dir() . '/umdc_rate/';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $file = $dir . md5($key) . '.txt';

    $now     = time();
    $entries = [];
    if (file_exists($file)) {
        $entries = array_filter(explode("\n", file_get_contents($file)));
        $entries = array_filter($entries, fn($t) => ($now - (int)$t) < $windowSeconds);
    }
    if (count($entries) >= $maxAttempts) return false;
    $entries[] = $now;
    file_put_contents($file, implode("\n", $entries));
    return true;
}

// ── Audit Log ─────────────────────────────────────────────────
function auditLog(PDO $pdo, ?int $userId, string $action, string $entityType = '', ?int $entityId = null): void {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $pdo->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address) VALUES (?,?,?,?,?)")
            ->execute([$userId, $action, $entityType, $entityId, $ip]);
    } catch (PDOException $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

// ── JSON Response Helper ──────────────────────────────────────
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── Secure Headers ────────────────────────────────────────────
function setSecureHeaders(): void {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:;");
}

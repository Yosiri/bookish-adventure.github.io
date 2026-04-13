<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';

// Start a generic session just for the login page itself.
// If already logged-in under a role session, redirect away.
umdc_session_start();
setSecureHeaders();

// Already logged in — redirect to correct dashboard
if (isLoggedIn()) {
    $role = currentRole();
    $sess = umdc_sess_param($role);
    header("Location: /dashboard/{$role}.php{$sess}");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/login.html');
    exit;
}

// Rate limit by IP
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rateLimit("login:{$ip}", 10, 300)) {
    header('Location: /public/login.html?error=rate_limit');
    exit;
}

$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    header('Location: /public/login.html?error=invalid');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] === 'banned' || $user['status'] === 'suspended') {
            header('Location: /public/login.html?error=suspended');
            exit;
        }

        $role = $user['role_name'];

        // ── KEY FIX ─────────────────────────────────────────────
        // Switch to the role-specific session BEFORE writing data.
        // This gives admin, org, and user each their own cookie
        // (UMDC_ADMIN, UMDC_ORG, UMDC_USER) so multiple tabs can
        // be logged in as different roles simultaneously.
        // ─────────────────────────────────────────────────────────
        umdc_start_role_session($role);

        $_SESSION['user_id']    = (int)$user['user_id'];
        $_SESSION['role']       = $role;
        $_SESSION['user_name']  = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        auditLog($pdo, (int)$user['user_id'], 'login');

        // Append _sess so the dashboard page opens the right session
        $sess = umdc_sess_param($role);
        header("Location: /dashboard/{$role}.php{$sess}");
        exit;

    } else {
        auditLog($pdo, null, 'login_failed', 'email', null);
        header('Location: /public/login.html?error=invalid');
        exit;
    }
} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    header('Location: /public/login.html?error=server');
    exit;
}

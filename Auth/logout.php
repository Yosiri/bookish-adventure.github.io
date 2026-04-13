<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';

// Open whichever role session is active for this tab
umdc_session_start();

if (isset($_SESSION['user_id'])) {
    auditLog($pdo, $_SESSION['user_id'], 'logout');
}

// Clear session data
$_SESSION = [];

// Expire only the role-specific cookie for this tab
$name = session_name();   // e.g. UMDC_ADMIN, UMDC_USER, UMDC_ORG
$p    = session_get_cookie_params();
setcookie($name, '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();

header('Location: /public/login.html?logged_out=1');
exit;

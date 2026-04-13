<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
setSecureHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/register.html');
    exit;
}

// Rate limit registration
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rateLimit("register:{$ip}", 5, 3600)) {
    header('Location: /public/register.html?error=rate_limit');
    exit;
}

$role     = in_array($_POST['role'] ?? '', ['user', 'organization']) ? $_POST['role'] : 'user';
$fname    = sanitizeInput($_POST['first_name'] ?? '');
$lname    = sanitizeInput($_POST['last_name'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

// Validation
$errors = [];
if (!$fname || strlen($fname) > 100) $errors[] = 'invalid_name';
if (!$lname || strlen($lname) > 100) $errors[] = 'invalid_name';
if (!$email)                          $errors[] = 'invalid_email';
if (strlen($password) < 8)            $errors[] = 'weak_password';
if ($password !== $confirm)           $errors[] = 'password_mismatch';

if ($errors) {
    header('Location: /public/register.html?error=' . $errors[0]);
    exit;
}

try {
    // Check duplicate email
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        header('Location: /public/register.html?error=email_exists');
        exit;
    }

    // Get role_id
    $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $roleStmt->execute([$role]);
    $role_id = $roleStmt->fetchColumn();
    if (!$role_id) { $role_id = 3; } // default to 'user'

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare("
        INSERT INTO users (role_id, first_name, last_name, email, password_hash)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$role_id, $fname, $lname, $email, $hash]);
    $newUserId = (int)$pdo->lastInsertId();

    auditLog($pdo, $newUserId, 'register');

    header('Location: /public/login.html?registered=success');
    exit;

} catch (PDOException $e) {
    error_log('Register error: ' . $e->getMessage());
    header('Location: /public/register.html?error=server');
    exit;
}

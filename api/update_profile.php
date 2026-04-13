<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireAnyRole(['user','organization','admin']);
setSecureHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
csrf_check();

$user_id  = currentUserId();
$fname    = sanitizeInput($_POST['first_name'] ?? '');
$lname    = sanitizeInput($_POST['last_name'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone    = sanitizeInput($_POST['phone'] ?? '');
$address  = sanitizeInput($_POST['address'] ?? '');

if (!$fname || !$lname) jsonResponse(['success' => false, 'message' => 'Name is required'], 422);
if (!$email)             jsonResponse(['success' => false, 'message' => 'Valid email required'], 422);

try {
    // Check email uniqueness (excluding self)
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $check->execute([$email, $user_id]);
    if ($check->fetch()) jsonResponse(['success' => false, 'message' => 'Email already in use'], 409);

    $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address=? WHERE user_id=?")
        ->execute([$fname, $lname, $email, $phone ?: null, $address ?: null, $user_id]);

    // Update session name
    $_SESSION['user_name']  = trim("$fname $lname");
    $_SESSION['user_email'] = $email;

    auditLog($pdo, $user_id, 'profile_updated');
    jsonResponse(['success' => true, 'message' => 'Profile updated successfully']);

} catch (PDOException $e) {
    error_log('Update profile: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Update failed'], 500);
}

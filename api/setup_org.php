<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('organization');
setSecureHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG');
    exit;
}

csrf_check();

$user_id     = currentUserId();
$org_name    = sanitizeInput($_POST['org_name'] ?? '');
$org_email   = filter_var(trim($_POST['org_email'] ?? ''), FILTER_VALIDATE_EMAIL);
$org_contact = sanitizeInput($_POST['org_contact'] ?? '');
$description = sanitizeInput($_POST['description'] ?? '');

if (!$org_name || strlen($org_name) > 255) {
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=invalid_name');
    exit;
}

try {
    // Check if org already exists for this user
    $check = $pdo->prepare("SELECT org_id FROM organizations WHERE user_id = ?");
    $check->execute([$user_id]);
    if ($check->fetch()) {
        header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=already_exists');
        exit;
    }

    $pdo->prepare("
        INSERT INTO organizations (user_id, org_name, org_email, org_contact, description, verified)
        VALUES (?, ?, ?, ?, ?, 0)
    ")->execute([$user_id, $org_name, $org_email ?: null, $org_contact ?: null, $description ?: null]);

    auditLog($pdo, $user_id, 'org_created');
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&success=org_created');
    exit;

} catch (PDOException $e) {
    error_log('Setup org: ' . $e->getMessage());
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=server');
    exit;
}

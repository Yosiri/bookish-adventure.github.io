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
$title       = sanitizeInput($_POST['title'] ?? '');
$description = sanitizeInput($_POST['description'] ?? '');
$category    = sanitizeInput($_POST['category'] ?? '');
$target      = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
$end_date    = $_POST['end_date'] ?? '';

// Validate
if (!$title || !$description || !$category || !$target || !$end_date) {
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=missing_fields');
    exit;
}
if ($target < 1000) {
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=low_target');
    exit;
}
if (strtotime($end_date) <= time()) {
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=invalid_date');
    exit;
}

try {
    // Get org_id
    $orgStmt = $pdo->prepare("SELECT org_id FROM organizations WHERE user_id = ?");
    $orgStmt->execute([$user_id]);
    $org = $orgStmt->fetch();
    if (!$org) {
        header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=no_org');
        exit;
    }

    $pdo->beginTransaction();

    // Insert campaign
    $stmt = $pdo->prepare("
        INSERT INTO campaigns (org_id, title, description, category, target_amount, start_date, end_date, status)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'pending')
    ");
    $stmt->execute([$org['org_id'], $title, $description, $category, $target, $end_date]);
    $campaign_id = (int)$pdo->lastInsertId();

    // Create approval record
    $pdo->prepare("INSERT INTO approvals (entity_type, entity_id, status) VALUES ('campaign', ?, 'pending')")
        ->execute([$campaign_id]);

    auditLog($pdo, $user_id, 'campaign_created', 'campaign', $campaign_id);
    $pdo->commit();

    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&success=campaign_submitted');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Campaign submit: ' . $e->getMessage());
    header('Location: /dashboard/organization.php?_sess=UMDC_ORG&error=server');
    exit;
}

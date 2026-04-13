<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireAnyRole(['user', 'organization', 'admin']);
setSecureHeaders();
header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT c.campaign_id, c.title, c.description, c.category,
               c.target_amount, COALESCE(c.current_amount, 0) AS current_amount,
               c.start_date, c.end_date, c.status, o.org_name,
               (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.campaign_id AND d.status = 'completed') AS donor_count
        FROM campaigns c
        JOIN organizations o ON c.org_id = o.org_id
        WHERE c.status IN ('approved','active')
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $campaigns = $stmt->fetchAll();

    jsonResponse(['success' => true, 'campaigns' => $campaigns]);
} catch (PDOException $e) {
    error_log('Campaigns API: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to fetch campaigns'], 500);
}

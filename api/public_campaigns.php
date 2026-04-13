<?php
// Public campaigns endpoint — no auth required
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
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
        ORDER BY c.current_amount DESC
        LIMIT 12
    ");
    $stmt->execute();
    $campaigns = $stmt->fetchAll();

    jsonResponse(['success' => true, 'campaigns' => $campaigns]);
} catch (PDOException $e) {
    error_log('Public campaigns API: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load campaigns'], 500);
}

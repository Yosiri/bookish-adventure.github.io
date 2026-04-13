<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireAnyRole(['user','organization','admin']);
setSecureHeaders();
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT l.public_hash, c.title AS campaign_title, d.amount, l.created_at
        FROM ledgers l
        JOIN donations d ON l.donation_id = d.donation_id
        JOIN campaigns c ON d.campaign_id = c.campaign_id
        WHERE d.status = 'completed'
        ORDER BY l.created_at DESC
        LIMIT 100
    ");
    $records = $stmt->fetchAll();
    jsonResponse(['success' => true, 'records' => $records]);
} catch (PDOException $e) {
    error_log('Transparency API: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load records'], 500);
}

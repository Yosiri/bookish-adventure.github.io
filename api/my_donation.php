<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('user');
setSecureHeaders();
header('Content-Type: application/json');

$user_id = currentUserId();
try {
    $stmt = $pdo->prepare("
        SELECT d.donation_id, d.donation_type, d.amount, d.status, d.created_at,
               c.title AS campaign_title, c.category,
               r.receipt_number,
               l.public_hash
        FROM donations d
        JOIN campaigns c ON d.campaign_id = c.campaign_id
        LEFT JOIN receipts r ON r.donation_id = d.donation_id
        LEFT JOIN ledgers l ON l.donation_id = d.donation_id
        WHERE d.user_id = ?
        ORDER BY d.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $donations = $stmt->fetchAll();

    jsonResponse(['success' => true, 'donations' => $donations]);
} catch (PDOException $e) {
    error_log('My donations: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load donations'], 500);
}

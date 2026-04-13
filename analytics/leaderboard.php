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
        SELECT CONCAT(u.first_name, ' ', LEFT(u.last_name, 1), '.') AS donor_name,
               COALESCE(SUM(d.amount), 0) AS total_donated,
               COUNT(d.donation_id) AS donation_count
        FROM users u
        JOIN donations d ON d.user_id = u.user_id
        WHERE d.status = 'completed' AND d.donation_type = 'cash'
        GROUP BY u.user_id
        ORDER BY total_donated DESC
        LIMIT 20
    ");
    $leaderboard = $stmt->fetchAll();
    jsonResponse(['success' => true, 'leaderboard' => $leaderboard]);
} catch (PDOException $e) {
    error_log('Leaderboard: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load'], 500);
}

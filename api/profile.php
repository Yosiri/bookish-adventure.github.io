<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireAnyRole(['user','organization','admin']);
setSecureHeaders();
header('Content-Type: application/json');

$user_id = currentUserId();

try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.address, u.created_at, r.role_name,
               COALESCE(SUM(CASE WHEN d.status='completed' THEN d.amount ELSE 0 END), 0) AS total_donated,
               COUNT(DISTINCT d.donation_id) AS donation_count,
               COUNT(DISTINCT d.campaign_id) AS campaigns_supported,
               MAX(d.created_at) AS last_donation
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        LEFT JOIN donations d ON d.user_id = u.user_id
        WHERE u.user_id = ?
        GROUP BY u.user_id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) jsonResponse(['success' => false, 'message' => 'User not found'], 404);

    jsonResponse([
        'success' => true,
        'user'    => [
            'user_id'    => $user['user_id'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'email'      => $user['email'],
            'phone'      => $user['phone'],
            'address'    => $user['address'],
            'created_at' => $user['created_at'],
            'role'       => $user['role_name'],
        ],
        'stats' => [
            'total_donated'      => (float)$user['total_donated'],
            'donation_count'     => (int)$user['donation_count'],
            'campaigns_supported'=> (int)$user['campaigns_supported'],
            'last_donation'      => $user['last_donation'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('Profile API: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load profile'], 500);
}

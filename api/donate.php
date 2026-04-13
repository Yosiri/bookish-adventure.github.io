<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('user');
setSecureHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

csrf_check();

$campaign_id    = filter_var($_POST['campaign_id'] ?? 0, FILTER_VALIDATE_INT);
$donation_type  = $_POST['donation_type'] ?? '';
$user_id        = currentUserId();

if (!$campaign_id || !in_array($donation_type, ['cash','item','service'], true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid request data'], 400);
}

// Verify campaign is active
$stmt = $pdo->prepare("SELECT campaign_id, title FROM campaigns WHERE campaign_id = ? AND status IN ('approved','active')");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch();
if (!$campaign) {
    jsonResponse(['success' => false, 'message' => 'Campaign not found or not active'], 404);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO donations (user_id, campaign_id, donation_type, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $campaign_id, $donation_type]);
    $donation_id = (int)$pdo->lastInsertId();

    $response = ['success' => true, 'donation_id' => $donation_id];

    if ($donation_type === 'cash') {
        $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        if (!$amount || $amount < 10) {
            throw new Exception('Minimum donation amount is ₱10.00');
        }
        if ($amount > 1000000) {
            throw new Exception('Amount exceeds maximum limit');
        }
        $pdo->prepare("UPDATE donations SET amount = ? WHERE donation_id = ?")->execute([$amount, $donation_id]);
        $response['message']  = 'Cash donation recorded. Please complete payment.';
        $response['redirect'] = "../donations/cash.php?id={$donation_id}";

    } elseif ($donation_type === 'item') {
        $item_name   = sanitizeInput($_POST['item_name'] ?? '');
        $quantity    = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
        $description = sanitizeInput($_POST['item_description'] ?? '');

        if (!$item_name || strlen($item_name) > 255) {
            throw new Exception('Please enter a valid item name');
        }
        $quantity = max(1, min(9999, $quantity ?: 1));

        $pdo->prepare("INSERT INTO item_donations (donation_id, item_name, quantity, description) VALUES (?,?,?,?)")
            ->execute([$donation_id, $item_name, $quantity, $description]);
        $response['message'] = 'Item donation recorded. Our team will arrange courier pickup.';

    } elseif ($donation_type === 'service') {
        $description = sanitizeInput($_POST['service_description'] ?? '');
        if (!$description || strlen($description) < 10) {
            throw new Exception('Please provide a service description (min 10 characters)');
        }
        $pdo->prepare("UPDATE donations SET amount = 0 WHERE donation_id = ?")->execute([$donation_id]);
        // Store service note in item_donations table reuse
        $pdo->prepare("INSERT INTO item_donations (donation_id, item_name, quantity, description) VALUES (?,'[SERVICE]',1,?)")
            ->execute([$donation_id, $description]);
        $response['message'] = 'Service offer recorded. The organization will contact you.';
    }

    auditLog($pdo, $user_id, 'donation_created', 'donation', $donation_id);
    $pdo->commit();

    jsonResponse($response);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Donate API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}

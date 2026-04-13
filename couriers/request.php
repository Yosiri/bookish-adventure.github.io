<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('user');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_USER';

$donation_id = filter_var($_GET['donation_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$donation_id) { header("Location: /dashboard/user.php{$_SESS_PARAM}"); exit; }

// Verify item donation belongs to this user
$stmt = $pdo->prepare("
    SELECT i.item_id, i.item_name, i.quantity, c.title AS campaign_title
    FROM item_donations i
    JOIN donations d ON i.donation_id = d.donation_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    WHERE d.donation_id = ? AND d.user_id = ? AND d.donation_type = 'item'
");
$stmt->execute([$donation_id, currentUserId()]);
$item = $stmt->fetch();
if (!$item) { header("Location: /dashboard/user.php{$_SESS_PARAM}&error=not_found"); exit; }

// Check if delivery already requested
$existing = $pdo->prepare("SELECT delivery_id, status FROM deliveries WHERE item_id = ? ORDER BY created_at DESC LIMIT 1");
$existing->execute([$item['item_id']]);
$existing = $existing->fetch();

$couriers = $pdo->query("SELECT * FROM couriers WHERE active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $courier_id = filter_var($_POST['courier_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$courier_id) { header("Location: /couriers/request.php{$_SESS_PARAM}&donation_id={$donation_id}&error=no_courier"); exit; }

    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO deliveries (item_id, courier_id, status) VALUES (?, ?, 'requested')")
        ->execute([$item['item_id'], $courier_id]);
    $delivery_id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO approvals (entity_type, entity_id, status) VALUES ('delivery', ?, 'pending')")
        ->execute([$delivery_id]);
    auditLog($pdo, currentUserId(), 'courier_requested', 'delivery', $delivery_id);
    $pdo->commit();

    header("Location: /dashboard/user.php{$_SESS_PARAM}&success=courier_requested");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request Courier – UMDC</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/global.css">
<style>body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}</style>
</head>
<body>
<div class="card" style="max-width:440px;width:100%;padding:36px;">
    <a href="/dashboard/user.php<?= $_SESS_PARAM ?>" style="font-size:.82rem;color:var(--color-muted);text-decoration:none;display:flex;align-items:center;gap:6px;margin-bottom:20px;">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <h3 style="font-weight:800;margin-bottom:4px;"><i class="fas fa-truck" style="color:var(--brand-primary);margin-right:8px;"></i>Request Courier Pickup</h3>
    <p style="color:var(--color-muted);font-size:.875rem;margin-bottom:24px;">Choose a courier to collect your item donation. An admin will approve the request.</p>

    <?php if ($existing && $existing['status'] !== 'cancelled'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <span>A courier pickup for this item is already <strong><?= ucfirst($existing['status']) ?></strong>. You cannot request another.</span>
    </div>
    <a href="/dashboard/user.php<?= $_SESS_PARAM ?>" class="btn-secondary" style="margin-top:12px;">Back to Dashboard</a>

    <?php elseif (empty($couriers)): ?>
    <div class="empty-state">
        <i class="fas fa-truck"></i>
        <h6>No Couriers Available</h6>
        <p>Please contact an admin to arrange pickup.</p>
    </div>

    <?php else: ?>
    <!-- Item summary -->
    <div style="background:var(--color-surface-2);border-radius:10px;padding:14px 16px;margin-bottom:20px;">
        <div style="font-size:.75rem;color:var(--color-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Item Details</div>
        <div style="font-weight:700;"><?= e($item['item_name']) ?></div>
        <div style="font-size:.82rem;color:var(--color-muted);">Qty: <?= (int)$item['quantity'] ?> · Campaign: <?= e($item['campaign_title']) ?></div>
    </div>

    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span>Please select a courier.</span></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label">Select Courier</label>
            <select class="form-input" name="courier_id" required>
                <option value="">Choose a courier…</option>
                <?php foreach ($couriers as $c): ?>
                <option value="<?= $c['courier_id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:12px;">
            <i class="fas fa-truck"></i> Submit Pickup Request
        </button>
    </form>
    <?php endif; ?>
</div>
<script src="/assets/js/global.js"></script>
</body></html>

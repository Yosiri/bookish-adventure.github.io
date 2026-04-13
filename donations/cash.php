<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
require_once __DIR__ . '/../receipts/generate.php';
umdc_session_start();
requireRole('user');
setSecureHeaders();

$donation_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$donation_id) {
    header('Location: /dashboard/user.php?_sess=UMDC_USER');
    exit;
}

$user_id = currentUserId();

$stmt = $pdo->prepare("
    SELECT d.*, c.title AS campaign_title, c.campaign_id,
           CONCAT(u.first_name,' ',u.last_name) AS donor_name
    FROM donations d
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    JOIN users u ON d.user_id = u.user_id
    WHERE d.donation_id = ? AND d.user_id = ? AND d.donation_type = 'cash'
");
$stmt->execute([$donation_id, $user_id]);
$donation = $stmt->fetch();

if (!$donation) {
    header('Location: /dashboard/user.php?_sess=UMDC_USER');
    exit;
}

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    csrf_check();

    if ($donation['status'] !== 'pending') {
        header('Location: /dashboard/user.php?_sess=UMDC_USER&error=already_processed');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update donation status
        $pdo->prepare("UPDATE donations SET status='completed' WHERE donation_id=?")
            ->execute([$donation_id]);

        // Update campaign current amount
        $pdo->prepare("UPDATE campaigns SET current_amount = current_amount + ? WHERE campaign_id = ?")
            ->execute([$donation['amount'], $donation['campaign_id']]);

        // Insert payment record
        $ref = 'UMDC-' . strtoupper(bin2hex(random_bytes(6)));
        $pdo->prepare("INSERT INTO payments (donation_id, gateway, transaction_ref, payment_status, paid_at) VALUES (?, 'manual', ?, 'success', NOW())")
            ->execute([$donation_id, $ref]);

        // Generate receipt and ledger hash
        generateReceipt($pdo, $donation_id);

        auditLog($pdo, $user_id, 'payment_completed', 'donation', $donation_id);
        $pdo->commit();

        header('Location: /dashboard/user.php?_sess=UMDC_USER&success=payment_complete');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Payment confirm: ' . $e->getMessage());
        $error = 'Payment processing failed. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Complete Donation – UMDC</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/global.css">
<style>
  body { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .payment-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:20px; padding:40px; max-width:500px; width:100%; box-shadow:var(--shadow-lg); }
  .amount-display { font-size:3rem; font-weight:800; letter-spacing:-2px; text-align:center; color:var(--brand-primary); font-family:var(--font-mono); margin:20px 0; }
  .detail-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--color-border); font-size:.875rem; }
  .detail-row:last-child { border-bottom:none; }
  .detail-label { color:var(--color-muted); }
  .detail-val { font-weight:600; text-align:right; max-width:260px; }
</style>
</head>
<body>
<div class="payment-card">
    <a href="/dashboard/user.php?_sess=UMDC_USER" style="color:var(--color-muted);font-size:.82rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <h2 style="font-weight:800;margin-bottom:4px;">Complete Your Donation</h2>
    <p style="color:var(--color-muted);font-size:.875rem;margin-bottom:24px;">Review the details before confirming.</p>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <?php if ($donation['status'] === 'completed'): ?>
    <div style="text-align:center;padding:30px;">
        <i class="fas fa-check-circle" style="font-size:3rem;color:var(--color-success);margin-bottom:12px;display:block;"></i>
        <h4 style="font-weight:700;">Already Completed</h4>
        <p style="color:var(--color-muted);">This donation has already been processed.</p>
        <a href="/receipts/view.php?id=<?= $donation_id ?>" class="btn-primary" style="margin-top:16px;">View Receipt</a>
    </div>
    <?php else: ?>

    <div class="amount-display">₱<?= number_format((float)$donation['amount'], 2) ?></div>

    <div style="background:var(--color-surface-2);border-radius:12px;padding:16px 20px;margin-bottom:24px;">
        <div class="detail-row">
            <span class="detail-label">Campaign</span>
            <span class="detail-val"><?= e($donation['campaign_title']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Donor</span>
            <span class="detail-val"><?= e($donation['donor_name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Donation ID</span>
            <span class="detail-val" style="font-family:var(--font-mono);">#<?= $donation_id ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="badge-pill badge-pending">Pending Payment</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date</span>
            <span class="detail-val"><?= date('F d, Y', strtotime($donation['created_at'])) ?></span>
        </div>
    </div>

    <div class="alert alert-warning" style="margin-bottom:20px;">
        <i class="fas fa-info-circle"></i>
        <span>In a production environment, you would be redirected to a payment gateway (GCash, PayMaya, etc.) to complete your payment securely.</span>
    </div>

    <form method="POST">
        <?= csrf_field() ?>
        <button type="submit" name="confirm_payment" class="btn-primary" style="width:100%;padding:14px;font-size:1rem;justify-content:center;">
            <i class="fas fa-check"></i> Confirm Donation — ₱<?= number_format((float)$donation['amount'], 2) ?>
        </button>
    </form>
    <a href="/dashboard/user.php?_sess=UMDC_USER" style="display:block;text-align:center;margin-top:14px;font-size:.82rem;color:var(--color-muted);">Cancel donation</a>

    <?php endif; ?>
</div>
<script src="/assets/js/global.js"></script>
</body>
</html>

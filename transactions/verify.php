<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
// This page is public — no auth required for hash verification
setSecureHeaders();

$hash = trim($_GET['hash'] ?? '');
$data = null;

if ($hash && strlen($hash) <= 128) {
    $stmt = $pdo->prepare("
        SELECT l.public_hash, d.amount, d.created_at, d.donation_type,
               c.title AS campaign_title, o.org_name,
               r.receipt_number,
               CONCAT(u.first_name,' ',LEFT(u.last_name,1),'.') AS donor
        FROM ledgers l
        JOIN donations d ON l.donation_id = d.donation_id
        JOIN campaigns c ON d.campaign_id = c.campaign_id
        JOIN organizations o ON c.org_id = o.org_id
        LEFT JOIN receipts r ON r.donation_id = d.donation_id
        JOIN users u ON d.user_id = u.user_id
        WHERE l.public_hash = ?
    ");
    $stmt->execute([$hash]);
    $data = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Transaction – UMDC</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/global.css">
<style>
  body { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .verify-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:20px; padding:40px; max-width:500px; width:100%; box-shadow:var(--shadow-lg); }
  .detail-row { display:flex; justify-content:space-between; padding:11px 0; border-bottom:1px solid var(--color-border); font-size:.875rem; }
  .detail-row:last-child { border-bottom:none; }
  .hash-box { background:var(--color-surface-2); border-radius:8px; padding:12px; font-family:var(--font-mono); font-size:.72rem; word-break:break-all; color:var(--brand-primary); margin:16px 0; }
</style>
</head>
<body>
<div class="verify-card">
    <div style="text-align:center;margin-bottom:28px;">
        <?php if ($data): ?>
        <div style="font-size:3.5rem;margin-bottom:10px;">✅</div>
        <h2 style="font-weight:800;color:var(--color-success);">Transaction Verified</h2>
        <p style="color:var(--color-muted);font-size:.875rem;margin-top:6px;">This donation record is authentic and exists in the ledger.</p>
        <?php elseif ($hash): ?>
        <div style="font-size:3.5rem;margin-bottom:10px;">❌</div>
        <h2 style="font-weight:800;color:var(--color-danger);">Not Found</h2>
        <p style="color:var(--color-muted);font-size:.875rem;margin-top:6px;">No transaction found matching that hash.</p>
        <?php else: ?>
        <div style="font-size:3.5rem;margin-bottom:10px;">🔍</div>
        <h2 style="font-weight:800;">Verify a Transaction</h2>
        <p style="color:var(--color-muted);font-size:.875rem;margin-top:6px;">Paste a transaction hash below to verify a donation record.</p>
        <?php endif; ?>
    </div>

    <?php if ($data): ?>
    <div class="hash-box"><?= e($data['public_hash']) ?></div>
    <div>
        <div class="detail-row"><span style="color:var(--color-muted);">Campaign</span><span style="font-weight:600;text-align:right;max-width:260px;"><?= e($data['campaign_title']) ?></span></div>
        <div class="detail-row"><span style="color:var(--color-muted);">Organization</span><span><?= e($data['org_name']) ?></span></div>
        <div class="detail-row"><span style="color:var(--color-muted);">Donor</span><span><?= e($data['donor']) ?></span></div>
        <div class="detail-row"><span style="color:var(--color-muted);">Amount</span><span style="font-weight:800;color:var(--color-success);font-family:var(--font-mono);">₱<?= number_format((float)$data['amount'], 2) ?></span></div>
        <?php if ($data['receipt_number']): ?>
        <div class="detail-row"><span style="color:var(--color-muted);">Receipt #</span><span style="font-family:var(--font-mono);font-size:.82rem;"><?= e($data['receipt_number']) ?></span></div>
        <?php endif; ?>
        <div class="detail-row"><span style="color:var(--color-muted);">Date</span><span><?= date('F d, Y', strtotime($data['created_at'])) ?></span></div>
        <div class="detail-row"><span style="color:var(--color-muted);">Type</span><span style="text-transform:capitalize;"><?= e($data['donation_type']) ?></span></div>
    </div>
    <?php endif; ?>

    <form method="GET" style="display:flex;gap:10px;margin-top:24px;">
        <input class="form-input" type="text" name="hash" placeholder="Paste transaction hash…"
               value="<?= e($hash) ?>" style="flex:1;">
        <button type="submit" class="btn-primary" style="white-space:nowrap;padding:10px 18px;">Verify</button>
    </form>
    <div style="text-align:center;margin-top:16px;">
        <a href="/public/index.php" style="font-size:.82rem;color:var(--color-muted);">← Back to UMDC</a>
    </div>
</div>
<script src="/assets/js/global.js"></script>
</body></html>

<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireAnyRole(['user','organization','admin']);
setSecureHeaders();

$donation_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$donation_id) {
    http_response_code(400);
    exit('Invalid request');
}

// Security: users can only view their own receipts, admin/org can view all
$whereExtra = '';
$params     = [$donation_id];
if (currentRole() === 'user') {
    $whereExtra = ' AND d.user_id = ?';
    $params[]   = currentUserId();
}

$stmt = $pdo->prepare("
    SELECT d.donation_id, d.donation_type, d.amount, d.status, d.created_at,
           CONCAT(u.first_name,' ',u.last_name) AS donor_name, u.email AS donor_email,
           c.title AS campaign_title, o.org_name,
           r.receipt_number, r.generated_at,
           l.public_hash,
           p.transaction_ref, p.gateway, p.paid_at
    FROM donations d
    JOIN users u ON d.user_id = u.user_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    JOIN organizations o ON c.org_id = o.org_id
    LEFT JOIN receipts r ON r.donation_id = d.donation_id
    LEFT JOIN ledgers l ON l.donation_id = d.donation_id
    LEFT JOIN payments p ON p.donation_id = d.donation_id
    WHERE d.donation_id = ?{$whereExtra} AND d.status = 'completed'
");
$stmt->execute($params);
$d = $stmt->fetch();

if (!$d) {
    http_response_code(404);
    exit('Receipt not found or not yet available.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt – <?= e($d['receipt_number'] ?? 'UMDC') ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/global.css">
<style>
  body { background:#f8f9fc; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
  .receipt-card { background:#fff; border-radius:16px; padding:40px; max-width:480px; width:100%; box-shadow:0 4px 30px rgba(0,0,0,.1); }
  .receipt-header { text-align:center; border-bottom:2px dashed #e8ecf3; padding-bottom:24px; margin-bottom:24px; }
  .receipt-logo { font-size:1.5rem; font-weight:800; background:linear-gradient(135deg,#3b6ff0,#7c3aed); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:4px; }
  .receipt-no { font-family:var(--font-mono); font-size:.8rem; color:var(--color-muted); }
  .amount-big { font-size:3rem; font-weight:800; letter-spacing:-2px; text-align:center; color:#3b6ff0; margin:20px 0; font-family:var(--font-mono); }
  .row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f0f2f8; font-size:.875rem; }
  .row:last-child { border-bottom:none; }
  .row .label { color:var(--color-muted); }
  .row .val { font-weight:600; text-align:right; max-width:240px; }
  .hash-box { background:#f8f9fc; border-radius:8px; padding:12px; font-family:var(--font-mono); font-size:.7rem; color:var(--color-muted); word-break:break-all; margin-top:20px; }
  .print-btn { display:block; width:100%; background:linear-gradient(135deg,#3b6ff0,#7c3aed); color:#fff; border:none; border-radius:10px; padding:13px; font-size:1rem; font-weight:700; cursor:pointer; margin-top:24px; font-family:var(--font-sans); }
  @media print { .print-btn { display:none; } body { background:white; } .receipt-card { box-shadow:none; } }
</style>
</head>
<body>
<div class="receipt-card">
    <div class="receipt-header">
        <div class="receipt-logo">UMDC Platform</div>
        <div class="receipt-no"><?= e($d['receipt_number'] ?? 'N/A') ?></div>
        <div style="margin-top:8px;font-size:.78rem;color:var(--color-success);font-weight:600;">✅ DONATION CONFIRMED</div>
    </div>

    <div class="amount-big">₱<?= number_format((float)$d['amount'], 2) ?></div>

    <div>
        <div class="row">
            <span class="label">Campaign</span>
            <span class="val"><?= e($d['campaign_title']) ?></span>
        </div>
        <div class="row">
            <span class="label">Organization</span>
            <span class="val"><?= e($d['org_name']) ?></span>
        </div>
        <div class="row">
            <span class="label">Donor</span>
            <span class="val"><?= e($d['donor_name']) ?></span>
        </div>
        <div class="row">
            <span class="label">Email</span>
            <span class="val" style="font-size:.82rem;"><?= e($d['donor_email']) ?></span>
        </div>
        <div class="row">
            <span class="label">Donation Type</span>
            <span class="val" style="text-transform:capitalize;"><?= e($d['donation_type']) ?></span>
        </div>
        <?php if ($d['transaction_ref']): ?>
        <div class="row">
            <span class="label">Transaction Ref</span>
            <span class="val" style="font-family:var(--font-mono);font-size:.82rem;"><?= e($d['transaction_ref']) ?></span>
        </div>
        <?php endif; ?>
        <div class="row">
            <span class="label">Date</span>
            <span class="val"><?= date('F d, Y g:i A', strtotime($d['paid_at'] ?? $d['created_at'])) ?></span>
        </div>
        <div class="row">
            <span class="label">Status</span>
            <span class="val" style="color:var(--color-success);">Completed ✓</span>
        </div>
    </div>

    <?php if ($d['public_hash']): ?>
    <div class="hash-box">
        <div style="font-weight:600;color:var(--color-text);margin-bottom:4px;font-size:.75rem;">🔗 Blockchain Hash</div>
        <?= e($d['public_hash']) ?>
    </div>
    <?php endif; ?>

    <button class="print-btn" onclick="window.print()">
        🖨️ Print / Save PDF
    </button>
</div>
</body>
</html>

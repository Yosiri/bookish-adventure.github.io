<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireAnyRole(['user','organization','admin']);
setSecureHeaders();

$_SESS_PARAM = '?_sess=UMDC_' . strtoupper(currentRole() === 'organization' ? 'ORG' : currentRole());

$records = $pdo->query("
    SELECT l.public_hash, d.amount, d.created_at, d.donation_type,
           c.title AS campaign_title, o.org_name
    FROM ledgers l
    JOIN donations d ON l.donation_id = d.donation_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    JOIN organizations o ON c.org_id = o.org_id
    WHERE d.status = 'completed'
    ORDER BY d.created_at DESC
    LIMIT 100
")->fetchAll();

$user_name = e($_SESSION['user_name'] ?? 'User');
$role      = currentRole();
$dash_url  = "/dashboard/{$role}.php{$_SESS_PARAM}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Public Ledger – UMDC</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/global.css">
<link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
<div style="max-width:960px;margin:0 auto;padding:32px 20px;">
    <div style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <a href="<?= $dash_url ?>" style="font-size:.82rem;color:var(--color-muted);text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:10px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h2 style="font-weight:800;letter-spacing:-.5px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-link" style="color:var(--brand-primary);font-size:1.2rem;"></i>
                Public Ledger
            </h2>
            <p style="color:var(--color-muted);font-size:.875rem;margin-top:4px;">
                All verified transactions — tamper-proof and publicly auditable.
            </p>
        </div>
        <a href="/transactions/verify.php<?= $_SESS_PARAM ?>" class="btn-secondary" style="font-size:.85rem;padding:9px 18px;">
            <i class="fas fa-search"></i> Verify a Hash
        </a>
    </div>

    <div class="card">
        <div style="overflow-x:auto;">
            <table class="table-custom">
                <thead>
                    <tr><th>Hash</th><th>Campaign</th><th>Organization</th><th>Amount</th><th>Type</th><th>Date</th><th>Verify</th></tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:.72rem;color:var(--brand-primary);">
                        <?= e(substr($r['public_hash'], 0, 16)) ?>…
                    </td>
                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($r['campaign_title']) ?></td>
                    <td style="font-size:.82rem;color:var(--color-muted);"><?= e($r['org_name']) ?></td>
                    <td class="amount-mono" style="color:var(--color-success);">₱<?= number_format((float)$r['amount'], 2) ?></td>
                    <td><span class="badge-pill badge-info" style="text-transform:capitalize;"><?= e($r['donation_type']) ?></span></td>
                    <td style="font-size:.78rem;color:var(--color-muted);"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                    <td>
                        <a href="/transactions/verify.php<?= $_SESS_PARAM ?>&hash=<?= urlencode($r['public_hash']) ?>"
                           style="font-size:.8rem;color:var(--brand-primary);">Verify →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--color-muted);">No verified transactions yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="/assets/js/global.js"></script>
</body></html>

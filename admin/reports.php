<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

$s = [
    'total_users'       => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_orgs'        => $pdo->query("SELECT COUNT(*) FROM organizations WHERE verified=1")->fetchColumn(),
    'total_campaigns'   => $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status IN('active','completed')")->fetchColumn(),
    'total_raised'      => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn(),
    'total_donors'      => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM donations WHERE status='completed'")->fetchColumn(),
    'cash_donations'    => $pdo->query("SELECT COUNT(*) FROM donations WHERE donation_type='cash' AND status='completed'")->fetchColumn(),
    'item_donations'    => $pdo->query("SELECT COUNT(*) FROM donations WHERE donation_type='item'")->fetchColumn(),
    'service_donations' => $pdo->query("SELECT COUNT(*) FROM donations WHERE donation_type='service'")->fetchColumn(),
    'pending_approvals' => $pdo->query("SELECT COUNT(*) FROM approvals WHERE status='pending'")->fetchColumn(),
    'open_flags'        => $pdo->query("SELECT COUNT(*) FROM fraud_flags WHERE status='open'")->fetchColumn(),
    'pending_verif'     => $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='pending'")->fetchColumn(),
    'total_receipts'    => $pdo->query("SELECT COUNT(*) FROM receipts")->fetchColumn(),
];
$generated = date('F d, Y \a\t h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>
<style>
  .report-section { margin-bottom: 28px; }
  .report-section h6 { font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--color-muted);margin-bottom:12px; }
  .report-row { display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--color-border);font-size:.875rem; }
  .report-row:last-child { border-bottom:none; }
  .report-val { font-family:var(--font-mono);font-weight:700;color:var(--brand-primary); }
  @media print { .sidebar,.mobile-toggle,.print-btn { display:none!important; } .main-content { margin-left:0!important; } }
</style>

<main class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
        <div>
            <h1>Governance Report</h1>
            <p>Platform summary for administrative review. Generated: <?= $generated ?></p>
        </div>
        <button class="btn-primary print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Export PDF
        </button>
    </div>

    <div class="two-col">
        <div class="card">
            <div class="card-header"><h5>User Activity</h5></div>
            <div class="card-body">
                <div class="report-section">
                    <div class="report-row"><span>Total Registered Users</span><span class="report-val"><?= number_format($s['total_users']) ?></span></div>
                    <div class="report-row"><span>Unique Donors</span><span class="report-val"><?= number_format($s['total_donors']) ?></span></div>
                    <div class="report-row"><span>Verified Organizations</span><span class="report-val"><?= number_format($s['total_orgs']) ?></span></div>
                    <div class="report-row"><span>Pending Verifications</span><span class="report-val"><?= number_format($s['pending_verif']) ?></span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5>Campaign Performance</h5></div>
            <div class="card-body">
                <div class="report-section">
                    <div class="report-row"><span>Active / Completed Campaigns</span><span class="report-val"><?= number_format($s['total_campaigns']) ?></span></div>
                    <div class="report-row"><span>Total Amount Raised</span><span class="report-val">₱<?= number_format((float)$s['total_raised'], 2) ?></span></div>
                    <div class="report-row"><span>Pending Approvals</span><span class="report-val"><?= number_format($s['pending_approvals']) ?></span></div>
                    <div class="report-row"><span>Receipts Generated</span><span class="report-val"><?= number_format($s['total_receipts']) ?></span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5>Donation Breakdown</h5></div>
            <div class="card-body">
                <div class="report-section">
                    <div class="report-row"><span>Cash Donations (Completed)</span><span class="report-val"><?= number_format($s['cash_donations']) ?></span></div>
                    <div class="report-row"><span>Item Donations</span><span class="report-val"><?= number_format($s['item_donations']) ?></span></div>
                    <div class="report-row"><span>Service Donations</span><span class="report-val"><?= number_format($s['service_donations']) ?></span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5>Platform Health</h5></div>
            <div class="card-body">
                <div class="report-section">
                    <div class="report-row"><span>Open Fraud Flags</span>
                        <span class="report-val" style="color:<?= $s['open_flags']>0?'var(--color-danger)':'var(--color-success)' ?>">
                            <?= number_format($s['open_flags']) ?>
                        </span>
                    </div>
                    <div class="report-row"><span>Pending Approvals</span>
                        <span class="report-val" style="color:<?= $s['pending_approvals']>0?'var(--color-warning)':'var(--color-success)' ?>">
                            <?= number_format($s['pending_approvals']) ?>
                        </span>
                    </div>
                    <div class="report-row"><span>Report Generated</span><span style="font-size:.8rem;color:var(--color-muted);"><?= $generated ?></span></div>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body></html>

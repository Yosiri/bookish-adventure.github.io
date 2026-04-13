<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

// Monthly donations (last 6 months)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           COUNT(*) AS count,
           COALESCE(SUM(amount), 0) AS total
    FROM donations
    WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll();

// Campaign performance
$campaigns = $pdo->query("
    SELECT c.title, c.target_amount, COALESCE(c.current_amount,0) AS current_amount,
           COUNT(d.donation_id) AS donor_count, c.status
    FROM campaigns c
    LEFT JOIN donations d ON d.campaign_id=c.campaign_id AND d.status='completed'
    GROUP BY c.campaign_id
    ORDER BY current_amount DESC LIMIT 10
")->fetchAll();

// Top orgs
$orgs = $pdo->query("
    SELECT o.org_name,
           COALESCE(SUM(d.amount),0) AS total_raised,
           COUNT(DISTINCT c.campaign_id) AS campaign_count
    FROM organizations o
    LEFT JOIN campaigns c ON c.org_id=o.org_id
    LEFT JOIN donations d ON d.campaign_id=c.campaign_id AND d.status='completed'
    GROUP BY o.org_id ORDER BY total_raised DESC LIMIT 8
")->fetchAll();

$user_name = e($_SESSION['user_name'] ?? 'Admin');
$initials  = strtoupper(substr($user_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>



<main class="main-content">
    <div class="page-header">
        <h1>Analytics</h1>
        <p>Platform performance overview.</p>
    </div>

    <!-- Monthly chart -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h5>Monthly Donations (Last 6 Months)</h5></div>
        <div class="card-body">
            <div id="monthlyChart" style="display:flex;align-items:flex-end;gap:12px;height:180px;padding:0 10px;">
                <?php
                $maxTotal = max(array_column($monthly, 'total') ?: [1]);
                foreach ($monthly as $m):
                    $pct = $maxTotal > 0 ? ($m['total'] / $maxTotal) * 100 : 0;
                    $label = date('M', strtotime($m['month'] . '-01'));
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                    <div style="font-size:.7rem;color:var(--color-muted);font-family:var(--font-mono);">₱<?= number_format((float)$m['total'], 0) ?></div>
                    <div style="width:100%;background:var(--brand-gradient);border-radius:4px 4px 0 0;height:<?= max(4, $pct) ?>%;min-height:4px;transition:height .4s;"></div>
                    <div style="font-size:.72rem;color:var(--color-muted);"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($monthly)): ?>
                <p style="color:var(--color-muted);text-align:center;width:100%;">No data yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="two-col">
        <!-- Top Campaigns -->
        <div class="card">
            <div class="card-header"><h5>Top Campaigns by Amount Raised</h5></div>
            <table class="table-custom">
                <thead><tr><th>Campaign</th><th>Raised</th><th>Target</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($campaigns as $c):
                    $pct = $c['target_amount'] > 0 ? min(100, ($c['current_amount']/$c['target_amount'])*100) : 0;
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.875rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($c['title']) ?></div>
                        <div style="margin-top:4px;"><?= progressBar($c['current_amount'], $c['target_amount']) ?></div>
                    </td>
                    <td class="amount-mono" style="color:var(--color-success);">₱<?= number_format((float)$c['current_amount'],0) ?></td>
                    <td class="amount-mono" style="font-size:.8rem;color:var(--color-muted);">₱<?= number_format((float)$c['target_amount'],0) ?></td>
                    <td><span class="badge-pill badge-<?= e($c['status']) ?>"><?= ucfirst($c['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($campaigns)): ?>
                <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--color-muted);">No data yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Organizations -->
        <div class="card">
            <div class="card-header"><h5>Top Organizations by Fundraising</h5></div>
            <table class="table-custom">
                <thead><tr><th>Organization</th><th>Total Raised</th><th>Campaigns</th></tr></thead>
                <tbody>
                <?php foreach ($orgs as $o): ?>
                <tr>
                    <td style="font-weight:600;"><?= e($o['org_name']) ?></td>
                    <td class="amount-mono" style="color:var(--color-success);">₱<?= number_format((float)$o['total_raised'],2) ?></td>
                    <td><?= $o['campaign_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orgs)): ?>
                <tr><td colspan="3" style="text-align:center;padding:30px;color:var(--color-muted);">No data yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<?php
function progressBar($current, $target) {
    $pct = $target > 0 ? min(100, ($current / $target) * 100) : 0;
    return '<div style="height:4px;background:#e8ecf3;border-radius:2px;overflow:hidden;"><div style="height:100%;background:linear-gradient(90deg,#3b6ff0,#7c3aed);width:' . $pct . '%;"></div></div>';
}
?>
</body>
</html>

<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();   // reads _sess=UMDC_ADMIN
requireRole('admin');
setSecureHeaders();

$_SESS_PARAM = '?_sess=UMDC_ADMIN';

$user_name = e($_SESSION['user_name'] ?? 'Admin');
$initials  = strtoupper(substr($user_name, 0, 2));

$stats = [
    'users'             => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'orgs'              => (int)$pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn(),
    'donations'         => (int)$pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn(),
    'campaigns'         => (int)$pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn(),
    'pending_approvals' => (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE status='pending'")->fetchColumn(),
    'open_flags'        => (int)$pdo->query("SELECT COUNT(*) FROM fraud_flags WHERE status='open'")->fetchColumn(),
    'total_raised'      => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn(),
];

$recent_donations = $pdo->query("
    SELECT d.donation_id, d.donation_type, d.amount, d.status, d.created_at,
           CONCAT(u.first_name,' ',u.last_name) AS donor_name, c.title AS campaign_title
    FROM donations d
    JOIN users u ON d.user_id = u.user_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    ORDER BY d.created_at DESC LIMIT 8
")->fetchAll();

$recent_users = $pdo->query("
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.status, u.created_at, r.role_name
    FROM users u JOIN roles r ON u.role_id = r.role_id
    ORDER BY u.created_at DESC LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title>Admin Dashboard – UMDC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>



<!-- ── Main ────────────────────────────────────────────────── -->
<main class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= strtok($user_name, ' ') ?>. Here's what's happening on UMDC.</p>
    </div>

    <?php if ($stats['pending_approvals'] > 0 || $stats['open_flags'] > 0): ?>
    <div class="alert-banner" style="margin-bottom:24px;">
        <i class="fas fa-exclamation-circle"></i>
        <span>
            <?php if ($stats['pending_approvals'] > 0): ?>
            <strong><?= $stats['pending_approvals'] ?> pending approval<?= $stats['pending_approvals'] > 1 ? 's' : '' ?></strong>
            <?php endif; ?>
            <?php if ($stats['pending_approvals'] > 0 && $stats['open_flags'] > 0): ?> and <?php endif; ?>
            <?php if ($stats['open_flags'] > 0): ?>
            <strong><?= $stats['open_flags'] ?> open fraud flag<?= $stats['open_flags'] > 1 ? 's' : '' ?></strong>
            <?php endif; ?>
            require your attention. <a href="../admin/approvals.php<?= $_SESS_PARAM ?>">Review now →</a>
        </span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= number_format($stats['users']) ?></div>
            <div class="stat-sub">Registered accounts</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Organizations</div>
            <div class="stat-value"><?= number_format($stats['orgs']) ?></div>
            <div class="stat-sub">Registered NGOs</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Raised</div>
            <div class="stat-value" style="font-family:var(--font-mono);font-size:1.5rem;">₱<?= number_format($stats['total_raised'], 0) ?></div>
            <div class="stat-sub">Completed donations</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Campaigns</div>
            <div class="stat-value"><?= number_format($stats['campaigns']) ?></div>
            <div class="stat-sub"><?= $stats['pending_approvals'] ?> pending review</div>
        </div>
    </div>

    <!-- Tables -->
    <div class="two-col">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-donate" style="color:var(--brand-primary);margin-right:8px"></i>Recent Donations</h5>
                <a href="../admin/donations.php<?= $_SESS_PARAM ?>" style="font-size:.8rem;color:var(--brand-primary);">View all →</a>
            </div>
            <table class="table-custom">
                <thead><tr><th>Donor</th><th>Campaign</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recent_donations as $d): ?>
                <tr>
                    <td><?= e($d['donor_name']) ?></td>
                    <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($d['campaign_title']) ?></td>
                    <td class="amount-mono"><?= $d['donation_type'] === 'cash' ? '₱' . number_format((float)$d['amount'], 2) : ucfirst($d['donation_type']) ?></td>
                    <td><span class="badge-pill badge-<?= e($d['status']) ?>"><?= ucfirst($d['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_donations)): ?>
                <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--color-muted);">No donations yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-plus" style="color:var(--brand-secondary);margin-right:8px"></i>Recent Registrations</h5>
                <a href="../admin/users.php<?= $_SESS_PARAM ?>" style="font-size:.8rem;color:var(--brand-primary);">View all →</a>
            </div>
            <table class="table-custom">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recent_users as $u): ?>
                <tr>
                    <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td style="color:var(--color-muted);font-size:.8rem;"><?= e($u['email']) ?></td>
                    <td><span class="badge-pill badge-role-<?= e($u['role_name']) ?>"><?= ucfirst($u['role_name']) ?></span></td>
                    <td><span class="badge-pill badge-<?= $u['status'] === 'active' ? 'active' : 'banned' ?>"><?= ucfirst($u['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_users)): ?>
                <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--color-muted);">No users yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body>
</html>

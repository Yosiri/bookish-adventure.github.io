<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('organization');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ORG';

$user_id   = currentUserId();
$user_name = e($_SESSION['user_name'] ?? 'Organization');
$initials  = strtoupper(substr($user_name, 0, 1) . (strpos($user_name, ' ') !== false ? substr(strrchr($user_name, ' '), 1, 1) : ''));

// Fetch org record
$orgStmt = $pdo->prepare("SELECT * FROM organizations WHERE user_id = ?");
$orgStmt->execute([$user_id]);
$org = $orgStmt->fetch();
$needs_setup = !$org;

$campaigns = [];
$stats = ['total_raised' => 0, 'total_campaigns' => 0, 'active_campaigns' => 0, 'total_donors' => 0];

if (!$needs_setup) {
    $cStmt = $pdo->prepare("
        SELECT c.*, (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.campaign_id AND d.status='completed') AS donor_count,
               (SELECT COALESCE(SUM(amount),0) FROM donations d WHERE d.campaign_id = c.campaign_id AND d.status='completed') AS raised
        FROM campaigns c WHERE c.org_id = ? ORDER BY c.created_at DESC
    ");
    $cStmt->execute([$org['org_id']]);
    $campaigns = $cStmt->fetchAll();

    $qs = [
        'total_raised'    => "SELECT COALESCE(SUM(d.amount),0) FROM donations d JOIN campaigns c ON d.campaign_id=c.campaign_id WHERE c.org_id=? AND d.status='completed'",
        'total_campaigns' => "SELECT COUNT(*) FROM campaigns WHERE org_id=?",
        'active_campaigns'=> "SELECT COUNT(*) FROM campaigns WHERE org_id=? AND status IN ('approved','active')",
        'total_donors'    => "SELECT COUNT(DISTINCT d.user_id) FROM donations d JOIN campaigns c ON d.campaign_id=c.campaign_id WHERE c.org_id=?",
    ];
    foreach ($qs as $k => $q) {
        $s = $pdo->prepare($q);
        $s->execute([$org['org_id']]);
        $stats[$k] = $s->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<meta name="sess-name" content="UMDC_ORG">
<title>Organization Dashboard – UMDC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/global.css">
<link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>

<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand-name">UMDC</div>
        <div class="brand-sub">Organization Portal</div>
    </div>
    <nav class="sidebar-nav">
        <a href="organization.php<?= $_SESS_PARAM ?>" class="sidebar-link active"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <?php if (!$needs_setup): ?>
        <button class="sidebar-link" onclick="openModal('campaignModal')"><i class="fas fa-plus-circle"></i> New Campaign</button>
        <a href="../verification/submit.php<?= $_SESS_PARAM ?>" class="sidebar-link"><i class="fas fa-id-badge"></i> Verification</a>
        <a href="../transactions/ledger.php<?= $_SESS_PARAM ?>" class="sidebar-link"><i class="fas fa-list-alt"></i> Transactions</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= $initials ?></div>
            <div>
                <div class="sidebar-user-name"><?= $user_name ?></div>
                <div class="sidebar-user-role"><?= $needs_setup ? 'Organization' : e($org['org_name']) ?></div>
            </div>
            <button class="logout-btn" data-logout title="Sign out"><i class="fas fa-sign-out-alt"></i></button>
        </div>
    </div>
</aside>

<!-- ── Main ────────────────────────────────────────────────── -->
<main class="main-content">

<?php if ($needs_setup): ?>
<!-- Setup prompt -->
<div class="page-header">
    <h1>Set Up Your Organization</h1>
    <p>Complete your organization profile to start creating campaigns.</p>
</div>
<div class="card" style="max-width:520px;">
    <div class="card-header"><h5>Organization Details</h5></div>
    <div class="card-body">
        <form method="POST" action="../api/setup_org.php" id="setupForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Organization Name</label>
                <input class="form-input" type="text" name="org_name" placeholder="e.g., Helping Hands Foundation" required>
            </div>
            <div class="form-group">
                <label class="form-label">Organization Email</label>
                <input class="form-input" type="email" name="org_email" placeholder="org@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input class="form-input" type="tel" name="org_contact" placeholder="+63 9XX XXX XXXX">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" placeholder="Tell donors about your organization..." rows="3"></textarea>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Organization</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Dashboard content -->
<div class="page-header">
    <h1><?= e($org['org_name']) ?></h1>
    <p>Manage your campaigns and track donations.</p>
</div>

<?php if (!$org['verified']): ?>
<div class="verification-card">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <h5>Organization Not Verified</h5>
        <p>Submit your verification documents to unlock full features. <a href="../verification/submit.php<?= $_SESS_PARAM ?>" style="color:var(--brand-primary);font-weight:600;">Submit Documents →</a></p>
    </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Raised</div>
        <div class="stat-value" style="font-family:var(--font-mono);font-size:1.5rem;color:var(--color-success);">₱<?= number_format((float)$stats['total_raised'], 2) ?></div>
        <div class="stat-sub">Completed donations</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Campaigns</div>
        <div class="stat-value"><?= (int)$stats['total_campaigns'] ?></div>
        <div class="stat-sub"><?= (int)$stats['active_campaigns'] ?> active</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Campaigns</div>
        <div class="stat-value"><?= (int)$stats['active_campaigns'] ?></div>
        <div class="stat-sub">Currently running</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Donors</div>
        <div class="stat-value"><?= number_format((int)$stats['total_donors']) ?></div>
        <div class="stat-sub">Unique supporters</div>
    </div>
</div>

<!-- Campaigns table -->
<div class="card">
    <div class="card-header">
        <h5>Your Campaigns</h5>
        <button class="btn-primary" style="padding:8px 16px;font-size:.82rem;" onclick="openModal('campaignModal')">
            <i class="fas fa-plus"></i> New Campaign
        </button>
    </div>
    <?php if (empty($campaigns)): ?>
    <div class="empty-state">
        <i class="fas fa-bullhorn"></i>
        <h6>No campaigns yet</h6>
        <p>Create your first campaign to start receiving donations.</p>
        <button class="btn-primary" style="margin-top:12px;" onclick="openModal('campaignModal')">Create Campaign</button>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table-custom">
            <thead><tr><th>Title</th><th>Category</th><th>Target</th><th>Raised</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $c): 
                $pct = $c['target_amount'] > 0 ? min(100, ($c['current_amount'] / $c['target_amount']) * 100) : 0;
            ?>
            <tr>
                <td><strong><?= e($c['title']) ?></strong></td>
                <td><?= e($c['category'] ?? '—') ?></td>
                <td class="amount-mono">₱<?= number_format((float)$c['target_amount'], 2) ?></td>
                <td class="amount-mono" style="color:var(--color-success)">₱<?= number_format((float)($c['raised'] ?? $c['current_amount']), 2) ?></td>
                <td style="min-width:120px;">
                    <div class="progress-wrap"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
                    <div style="font-size:.7rem;color:var(--color-muted);margin-top:4px;"><?= number_format($pct, 0) ?>%</div>
                </td>
                <td><span class="badge-pill badge-<?= e($c['status']) ?>"><?= ucfirst($c['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</main>

<!-- ── New Campaign Modal ──────────────────────────────────── -->
<div class="modal-overlay" id="campaignModal">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-bullhorn" style="color:var(--brand-primary)"></i> Create New Campaign</h4>
            <button class="modal-close" onclick="closeModal('campaignModal')">&times;</button>
        </div>
        <form method="POST" action="../campaigns/submit.php" id="campaignForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Campaign Title</label>
                <input class="form-input" type="text" name="title" placeholder="e.g., Typhoon Relief for Mindanao" required maxlength="255">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-input" name="category" required>
                    <option value="">Select category…</option>
                    <option value="Disaster Relief">Disaster Relief</option>
                    <option value="Education">Education</option>
                    <option value="Health">Health</option>
                    <option value="Environment">Environment</option>
                    <option value="Community">Community</option>
                    <option value="Food">Food &amp; Nutrition</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" rows="3" placeholder="Describe the campaign and how funds will be used..." required></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Target Amount (₱)</label>
                    <input class="form-input" type="number" name="target_amount" placeholder="50000" min="1000" required>
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input class="form-input" type="date" name="end_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn-secondary" onclick="closeModal('campaignModal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Submit for Review</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body>
</html>

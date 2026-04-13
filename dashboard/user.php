<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();   // reads _sess=UMDC_USER from URL automatically
requireRole('user');
setSecureHeaders();

// Pass _sess through all internal links so this tab stays on the right session
$_SESS_PARAM = '?_sess=UMDC_USER';

$user_id = currentUserId();
$user_name = e($_SESSION['user_name'] ?? 'User');
$initials  = strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1) . (strpos($_SESSION['user_name'] ?? '', ' ') !== false ? substr(strrchr($_SESSION['user_name'], ' '), 1, 1) : ''));

// Quick stats
$stats = [
    'total_donated'      => $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM donations WHERE user_id=? AND status='completed'"),
    'donation_count'     => $pdo->prepare("SELECT COUNT(*) FROM donations WHERE user_id=?"),
    'campaigns_supported'=> $pdo->prepare("SELECT COUNT(DISTINCT campaign_id) FROM donations WHERE user_id=?"),
    'pending_count'      => $pdo->prepare("SELECT COUNT(*) FROM donations WHERE user_id=? AND status='pending'"),
];
foreach ($stats as $k => $s) { $s->execute([$user_id]); $stats[$k] = $s->fetchColumn(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<meta name="sess-name" content="UMDC_USER">
<title>Dashboard – UMDC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/global.css">
<link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>

<!-- ── Mobile Toggle ──────────────────────────────────────── -->
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

<!-- ── Sidebar ────────────────────────────────────────────── -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand-name">UMDC</div>
        <div class="brand-sub">Donor Portal</div>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section-label">Main</span>
        <button class="sidebar-link active" onclick="loadSection('campaigns')">
            <i class="fas fa-hand-holding-heart"></i> Browse Campaigns
        </button>
        <button class="sidebar-link" onclick="loadSection('my-donations')">
            <i class="fas fa-receipt"></i> My Donations
        </button>
        <button class="sidebar-link" onclick="loadSection('leaderboard')">
            <i class="fas fa-trophy"></i> Leaderboard
        </button>
        <button class="sidebar-link" onclick="loadSection('transparency')">
            <i class="fas fa-shield-alt"></i> Transparency
        </button>
        <span class="nav-section-label">Account</span>
        <button class="sidebar-link" onclick="loadSection('profile')">
            <i class="fas fa-user"></i> My Profile
        </button>
        <a href="/verification/submit.php<?= $_SESS_PARAM ?>" class="sidebar-link">
            <i class="fas fa-id-card"></i> Verification
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= $initials ?></div>
            <div>
                <div class="sidebar-user-name"><?= $user_name ?></div>
                <div class="sidebar-user-role">Donor</div>
            </div>
            <button class="logout-btn" data-logout title="Sign out"><i class="fas fa-sign-out-alt"></i></button>
        </div>
    </div>
</aside>

<!-- ── Main Content ────────────────────────────────────────── -->
<main class="main-content">
    <!-- Page header -->
    <div class="page-header">
        <h1>Welcome back, <?= strtok($user_name, ' ') ?> 👋</h1>
        <p>Make a difference today — browse campaigns and donate.</p>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'payment_complete'): ?>
    <div class="alert alert-success" data-auto-dismiss>
        <i class="fas fa-check-circle"></i>
        <span>Your donation was completed successfully! Thank you for making a difference.</span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Donated</div>
            <div class="stat-value" style="font-family:var(--font-mono)">₱<?= number_format((float)$stats['total_donated'], 2) ?></div>
            <div class="stat-sub">Lifetime contributions</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Donations Made</div>
            <div class="stat-value"><?= number_format((int)$stats['donation_count']) ?></div>
            <div class="stat-sub">All types combined</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Campaigns Supported</div>
            <div class="stat-value"><?= number_format((int)$stats['campaigns_supported']) ?></div>
            <div class="stat-sub">Unique campaigns</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= (int)$stats['pending_count'] ?></div>
            <div class="stat-sub">Awaiting payment</div>
        </div>
    </div>

    <!-- Dynamic section -->
    <div id="dynamic-content">
        <!-- Loaded by JS -->
    </div>
</main>

<!-- ── Donation Modal ──────────────────────────────────────── -->
<div class="modal-overlay" id="donationModal">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-heart" style="color:var(--brand-primary)"></i> Make a Donation</h4>
            <button class="modal-close" onclick="closeModal('donationModal')">&times;</button>
        </div>

        <div id="donation-campaign-info" style="margin-bottom:20px;"></div>

        <form id="donationForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="campaign_id" id="campaign_id">

            <div class="form-group">
                <label class="form-label">Donation Type</label>
                <select class="form-input" name="donation_type" id="donation_type" onchange="DonationForm.toggleTypeFields(this.value)">
                    <option value="cash">💰 Cash Donation</option>
                    <option value="item">📦 Item Donation</option>
                    <option value="service">🔧 Service Offer</option>
                </select>
            </div>

            <!-- Cash fields -->
            <div id="cash-fields">
                <div class="form-group">
                    <label class="form-label">Amount (₱)</label>
                    <input class="form-input" type="number" name="amount" id="amount"
                           placeholder="Enter amount (min ₱10)" min="10" step="0.01">
                    <div class="form-error" id="amount-error"></div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                    <?php foreach ([50,100,250,500,1000] as $amt): ?>
                    <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:.8rem;"
                            onclick="document.getElementById('amount').value='<?= $amt ?>'"><?= '₱' . number_format($amt) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Item fields -->
            <div id="item-fields" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Item Name</label>
                    <input class="form-input" type="text" name="item_name" id="item_name"
                           placeholder="e.g., Blankets, Canned Goods">
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input class="form-input" type="number" name="quantity" placeholder="1" min="1" max="9999">
                </div>
                <div class="form-group">
                    <label class="form-label">Description (optional)</label>
                    <textarea class="form-textarea" name="item_description" placeholder="Condition, size, brand..." rows="2"></textarea>
                </div>
            </div>

            <!-- Service fields -->
            <div id="service-fields" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Describe Your Service</label>
                    <textarea class="form-textarea" name="service_description" id="service_description"
                              placeholder="What service can you offer? (e.g., Medical consultation, Carpentry, Legal advice...)" rows="3"></textarea>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn-secondary" onclick="closeModal('donationModal')">Cancel</button>
                <button type="button" class="btn-primary" onclick="DonationForm.submit(this)">
                    <i class="fas fa-check"></i> Confirm Donation
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script src="/assets/js/user.js"></script>
<script>
// Make all API calls from this tab carry _sess so PHP opens the right session
window._SESS = '<?= $_SESS_PARAM ?>';
// Auto-load campaigns section
loadSection('campaigns');
</script>
</body>
</html>

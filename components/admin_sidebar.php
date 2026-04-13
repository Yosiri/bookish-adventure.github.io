<?php
// components/admin_sidebar.php
// Included by every admin page. Requires $pdo and session to be active.
defined('UMDC_APP') or define('UMDC_APP', true);

$_admin_name     = e($_SESSION['user_name'] ?? 'Admin');
$_admin_initials = strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 2));
$_SESS           = isset($_SESS_PARAM) ? $_SESS_PARAM : '?_sess=UMDC_ADMIN';

// Badge counts for sidebar
$_pending_approvals = (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE status='pending'")->fetchColumn();
$_open_flags        = (int)$pdo->query("SELECT COUNT(*) FROM fraud_flags WHERE status='open'")->fetchColumn();
$_pending_verif     = (int)$pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='pending'")->fetchColumn();

$_current = basename($_SERVER['PHP_SELF']);
function _admin_active(string $file): string {
    global $_current;
    return ($file === $_current) ? ' active' : '';
}
?>
<link rel="stylesheet" href="/assets/css/global.css">
<link rel="stylesheet" href="/assets/css/dashboard.css">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<meta name="sess-name" content="UMDC_ADMIN">
</head><body class="theme-dark">

<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

<aside class="sidebar sidebar-dark">
    <div class="sidebar-logo">
        <div class="brand-name">UMDC</div>
        <div class="brand-sub">Admin Panel</div>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section-label">Overview</span>
        <a href="/dashboard/admin.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('admin.php') ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>

        <span class="nav-section-label">Management</span>
        <a href="/admin/approvals.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('approvals.php') ?>">
            <i class="fas fa-check-double"></i> Approvals
            <?php if ($_pending_approvals > 0): ?>
            <span class="badge-count"><?= $_pending_approvals ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/review_campaigns.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('review_campaigns.php') ?>">
            <i class="fas fa-bullhorn"></i> Campaigns
        </a>
        <a href="/admin/organizations.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('organizations.php') ?>">
            <i class="fas fa-building"></i> Organizations
        </a>
        <a href="/admin/users.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('users.php') ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <a href="/admin/donations.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('donations.php') ?>">
            <i class="fas fa-donate"></i> Donations
        </a>
        <a href="/admin/fraud.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('fraud.php') ?>">
            <i class="fas fa-flag"></i> Fraud Flags
            <?php if ($_open_flags > 0): ?>
            <span class="badge-count"><?= $_open_flags ?></span>
            <?php endif; ?>
        </a>
        <a href="/verification/verify_user.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('verify_user.php') ?>">
            <i class="fas fa-id-card"></i> Verifications
            <?php if ($_pending_verif > 0): ?>
            <span class="badge-count"><?= $_pending_verif ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/couriers.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('couriers.php') ?>">
            <i class="fas fa-truck"></i> Couriers
        </a>

        <span class="nav-section-label">Reports</span>
        <a href="/admin/analytics.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('analytics.php') ?>">
            <i class="fas fa-chart-bar"></i> Analytics
        </a>
        <a href="/admin/reports.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('reports.php') ?>">
            <i class="fas fa-file-alt"></i> Reports
        </a>
        <a href="/admin/system.php<?= $_SESS ?>" class="sidebar-link<?= _admin_active('system.php') ?>">
            <i class="fas fa-cog"></i> System
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= $_admin_initials ?></div>
            <div>
                <div class="sidebar-user-name"><?= $_admin_name ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
            <button class="logout-btn" data-logout title="Sign out">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    </div>
</aside>

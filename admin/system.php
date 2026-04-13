<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

$php_v        = PHP_VERSION;
$server       = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$db_v         = $pdo->query("SELECT VERSION()")->fetchColumn();
$tables       = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$audit_count  = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$notif_unread = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
$sess_count   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>
<style>
  .info-row { display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--color-border);font-size:.875rem; }
  .info-row:last-child { border-bottom:none; }
  .info-val { font-family:var(--font-mono);font-size:.82rem;color:var(--color-success); }
  .table-tag { background:rgba(59,111,240,.1);color:var(--brand-primary);font-size:.72rem;padding:3px 8px;border-radius:4px;font-family:var(--font-mono);margin:3px;display:inline-block; }
</style>

<main class="main-content">
    <div class="page-header">
        <h1>System Control Panel</h1>
        <p>Server info, database status, and platform diagnostics.</p>
    </div>

    <div class="two-col" style="margin-bottom:20px;">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-server" style="color:var(--brand-primary);margin-right:8px;"></i>Environment</h5></div>
            <div class="card-body">
                <div class="info-row"><span style="color:var(--color-muted);">PHP Version</span><span class="info-val"><?= e($php_v) ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Server</span><span class="info-val" style="font-size:.75rem;"><?= e($server) ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">MySQL Version</span><span class="info-val"><?= e($db_v) ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Database</span><span class="info-val">umdc</span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Timezone</span><span class="info-val"><?= date_default_timezone_get() ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Server Time</span><span class="info-val"><?= date('Y-m-d H:i:s') ?></span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5><i class="fas fa-chart-line" style="color:var(--brand-secondary);margin-right:8px;"></i>Platform Stats</h5></div>
            <div class="card-body">
                <div class="info-row"><span style="color:var(--color-muted);">Audit Log Entries</span><span class="info-val"><?= number_format($audit_count) ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Unread Notifications</span><span class="info-val"><?= number_format($notif_unread) ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Active Users</span><span class="info-val"><?= number_format($sess_count) ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Database Tables</span><span class="info-val"><?= count($tables) ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Max Upload Size</span><span class="info-val"><?= ini_get('upload_max_filesize') ?></span></div>
                <div class="info-row"><span style="color:var(--color-muted);">Memory Limit</span><span class="info-val"><?= ini_get('memory_limit') ?></span></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h5><i class="fas fa-database" style="color:var(--brand-primary);margin-right:8px;"></i>Database Tables</h5></div>
        <div class="card-body">
            <?php foreach ($tables as $t): ?>
            <span class="table-tag"><?= e($t) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-shield-alt" style="color:var(--color-success);margin-right:8px;"></i>Security Status</h5></div>
        <div class="card-body">
            <div class="info-row"><span style="color:var(--color-muted);">CSRF Protection</span><span style="color:var(--color-success);font-weight:600;">✓ Active</span></div>
            <div class="info-row"><span style="color:var(--color-muted);">Session Separation</span><span style="color:var(--color-success);font-weight:600;">✓ Role-based cookies</span></div>
            <div class="info-row"><span style="color:var(--color-muted);">Password Hashing</span><span style="color:var(--color-success);font-weight:600;">✓ bcrypt cost 12</span></div>
            <div class="info-row"><span style="color:var(--color-muted);">SQL Injection</span><span style="color:var(--color-success);font-weight:600;">✓ PDO prepared statements</span></div>
            <div class="info-row"><span style="color:var(--color-muted);">Rate Limiting</span><span style="color:var(--color-success);font-weight:600;">✓ Login & Register</span></div>
            <div class="info-row"><span style="color:var(--color-muted);">Secure Headers</span><span style="color:var(--color-success);font-weight:600;">✓ CSP, X-Frame, XSS</span></div>
            <div class="info-row"><span style="color:var(--color-muted);">Audit Logging</span><span style="color:var(--color-success);font-weight:600;">✓ <?= number_format($audit_count) ?> entries</span></div>
        </div>
    </div>
</main>
<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body></html>

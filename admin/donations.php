<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$type    = sanitizeInput($_GET['type'] ?? '');
$status  = sanitizeInput($_GET['status'] ?? '');
$search  = sanitizeInput($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];

if ($type && in_array($type, ['cash','item','service'])) {
    $where[] = "d.donation_type = ?"; $params[] = $type;
}
if ($status && in_array($status, ['pending','completed','failed','refunded'])) {
    $where[] = "d.status = ?"; $params[] = $status;
}
if ($search) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
    $s = "%$search%"; $params = array_merge($params, [$s, $s, $s, $s]);
}

$whereSQL = implode(' AND ', $where);
$total = $pdo->prepare("SELECT COUNT(*) FROM donations d JOIN users u ON d.user_id=u.user_id JOIN campaigns c ON d.campaign_id=c.campaign_id WHERE $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT d.donation_id, d.donation_type, d.amount, d.status, d.created_at,
           CONCAT(u.first_name,' ',u.last_name) AS donor_name, u.email AS donor_email,
           c.title AS campaign_title, o.org_name,
           r.receipt_number
    FROM donations d
    JOIN users u ON d.user_id = u.user_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    JOIN organizations o ON c.org_id = o.org_id
    LEFT JOIN receipts r ON r.donation_id = d.donation_id
    WHERE $whereSQL
    ORDER BY d.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$donations = $stmt->fetchAll();

$summary = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) AS total_raised,
        COUNT(CASE WHEN status='completed' THEN 1 END) AS completed,
        COUNT(CASE WHEN status='pending' THEN 1 END) AS pending
    FROM donations
")->fetch();

$user_name = e($_SESSION['user_name'] ?? 'Admin');
$initials  = strtoupper(substr($user_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Donations – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>



<main class="main-content">
    <div class="page-header">
        <h1>Donations</h1>
        <p>View and manage all donation transactions.</p>
    </div>

    <!-- Summary stats -->
    <div class="stats-grid" style="margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-label">Total Donations</div>
            <div class="stat-value"><?= number_format((int)$summary['total']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Raised</div>
            <div class="stat-value" style="font-family:var(--font-mono);font-size:1.4rem;">₱<?= number_format((float)$summary['total_raised'], 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed</div>
            <div class="stat-value" style="color:var(--color-success);"><?= number_format((int)$summary['completed']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value" style="color:var(--color-warning);"><?= number_format((int)$summary['pending']) ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:16px 20px;">
            <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <input class="form-input" type="text" name="q" placeholder="Search donor or campaign…" value="<?= e($search) ?>" style="max-width:240px;">
                <select class="form-input" name="type" style="max-width:140px;">
                    <option value="">All Types</option>
                    <option value="cash" <?= $type==='cash'?'selected':'' ?>>Cash</option>
                    <option value="item" <?= $type==='item'?'selected':'' ?>>Item</option>
                    <option value="service" <?= $type==='service'?'selected':'' ?>>Service</option>
                </select>
                <select class="form-input" name="status" style="max-width:140px;">
                    <option value="">All Status</option>
                    <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
                    <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
                    <option value="failed" <?= $status==='failed'?'selected':'' ?>>Failed</option>
                </select>
                <button type="submit" class="btn-primary" style="padding:9px 20px;">Filter</button>
                <?php if ($search||$type||$status): ?>
                <a href="/admin/donations.php<?= $_SESS_PARAM ?>" class="btn-secondary" style="padding:9px 20px;">Clear</a>
                <?php endif; ?>
                <span style="margin-left:auto;font-size:.82rem;color:var(--color-muted);"><?= number_format($total) ?> records</span>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div style="overflow-x:auto;">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>#</th><th>Donor</th><th>Campaign</th><th>Organization</th>
                        <th>Type</th><th>Amount</th><th>Status</th><th>Date</th><th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($donations as $d): ?>
                <tr>
                    <td class="amount-mono" style="color:var(--color-muted);font-size:.75rem;"><?= $d['donation_id'] ?></td>
                    <td>
                        <div><?= e($d['donor_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--color-muted);"><?= e($d['donor_email']) ?></div>
                    </td>
                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['campaign_title']) ?></td>
                    <td style="font-size:.8rem;color:var(--color-muted);"><?= e($d['org_name']) ?></td>
                    <td><span class="badge-pill badge-info" style="text-transform:capitalize;"><?= e($d['donation_type']) ?></span></td>
                    <td class="amount-mono"><?= $d['donation_type'] === 'cash' ? '₱' . number_format((float)$d['amount'], 2) : '—' ?></td>
                    <td><span class="badge-pill badge-<?= e($d['status']) ?>"><?= ucfirst($d['status']) ?></span></td>
                    <td style="font-size:.78rem;color:var(--color-muted);"><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                    <td>
                        <?php if ($d['receipt_number']): ?>
                        <a href="../receipts/view.php<?= $_SESS_PARAM ?>&id=<?= $d['donation_id'] ?>" target="_blank" class="btn-secondary" style="font-size:.72rem;padding:4px 10px;">View</a>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--color-muted);">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($donations)): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--color-muted);">No donations found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div style="padding:16px 20px;display:flex;gap:8px;justify-content:center;border-top:1px solid var(--color-border);">
            <?php for ($p = 1; $p <= min($pages, 10); $p++): ?>
            <a href="?q=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>&_sess=UMDC_ADMIN"
               class="<?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"
               style="padding:6px 14px;font-size:.82rem;"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</main>
<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body>
</html>

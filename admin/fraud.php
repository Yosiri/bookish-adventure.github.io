<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $flag_id = filter_var($_POST['flag_id'] ?? 0, FILTER_VALIDATE_INT);
    $action  = $_POST['action'] ?? '';
    if ($flag_id && in_array($action, ['reviewed','resolved'], true)) {
        $pdo->prepare("UPDATE fraud_flags SET status=? WHERE flag_id=?")->execute([$action, $flag_id]);
        auditLog($pdo, currentUserId(), "fraud_flag_{$action}", 'flag', $flag_id);
    }
    header('Location: /admin/fraud.php?updated=1');
    exit;
}

$flags = $pdo->query("
    SELECT f.*, CONCAT(u.first_name,' ',u.last_name) AS flagged_by_name
    FROM fraud_flags f
    LEFT JOIN users u ON f.flagged_by = u.user_id
    ORDER BY FIELD(f.status,'open','reviewed','resolved'), f.created_at DESC
")->fetchAll();

$user_name = e($_SESSION['user_name'] ?? 'Admin');
$initials  = strtoupper(substr($user_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fraud Flags – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>



<main class="main-content">
    <div class="page-header">
        <h1>Fraud Flags</h1>
        <p>Review and resolve flagged activity reports.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i><span>Flag status updated.</span></div>
    <?php endif; ?>

    <div class="card">
        <div style="overflow-x:auto;">
            <table class="table-custom">
                <thead>
                    <tr><th>Entity Type</th><th>Entity ID</th><th>Reason</th><th>Flagged By</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($flags as $f): ?>
                <tr>
                    <td><span class="badge-pill badge-info" style="text-transform:capitalize;"><?= e($f['entity_type']) ?></span></td>
                    <td class="amount-mono">#<?= $f['entity_id'] ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($f['reason']) ?>"><?= e($f['reason'] ?? '—') ?></td>
                    <td style="font-size:.82rem;"><?= e($f['flagged_by_name'] ?? 'System') ?></td>
                    <td>
                        <span class="badge-pill <?= $f['status']==='open' ? 'badge-failed' : ($f['status']==='reviewed' ? 'badge-pending' : 'badge-active') ?>">
                            <?= ucfirst($f['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:.78rem;color:var(--color-muted);"><?= date('M d, Y', strtotime($f['created_at'])) ?></td>
                    <td>
                        <?php if ($f['status'] === 'open'): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="flag_id" value="<?= $f['flag_id'] ?>">
                            <button name="action" value="reviewed" class="btn-secondary" style="font-size:.75rem;padding:5px 10px;">Mark Reviewed</button>
                            <button name="action" value="resolved" class="btn-primary" style="font-size:.75rem;padding:5px 10px;margin-left:4px;">Resolve</button>
                        </form>
                        <?php elseif ($f['status'] === 'reviewed'): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="flag_id" value="<?= $f['flag_id'] ?>">
                            <button name="action" value="resolved" class="btn-primary" style="font-size:.75rem;padding:5px 10px;">Resolve</button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--color-muted);">Resolved</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($flags)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--color-muted);">No fraud flags found</td></tr>
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

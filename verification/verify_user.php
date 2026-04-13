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
    $id     = filter_var($_POST['req_id'] ?? 0, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $notes  = sanitizeInput($_POST['review_notes'] ?? '');

    if ($id && in_array($action, ['approve','reject'], true)) {
        $pdo->beginTransaction();
        $req = $pdo->prepare("SELECT * FROM verification_requests WHERE id=?");
        $req->execute([$id]);
        $req = $req->fetch();
        if ($req) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare("UPDATE verification_requests SET status=?, reviewed_by=?, review_notes=? WHERE id=?")
                ->execute([$status, currentUserId(), $notes, $id]);
            if ($action === 'approve') {
                $pdo->prepare("UPDATE users SET is_verified=1 WHERE user_id=?")->execute([$req['user_id']]);
                if ($req['type'] === 'organization') {
                    $pdo->prepare("UPDATE organizations SET verified=1 WHERE user_id=?")->execute([$req['user_id']]);
                }
            }
            auditLog($pdo, currentUserId(), "verification_{$action}", 'verification_request', $id);
        }
        $pdo->commit();
    }
    header("Location: /verification/verify_user.php{$_SESS_PARAM}&updated=1");
    exit;
}

$filter   = in_array($_GET['filter'] ?? '', ['pending','approved','rejected']) ? $_GET['filter'] : '';
$where    = $filter ? "WHERE v.status = '$filter'" : '';
$requests = $pdo->query("
    SELECT v.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email, r.role_name
    FROM verification_requests v
    JOIN users u ON v.user_id = u.user_id
    JOIN roles r ON u.role_id = r.role_id
    $where
    ORDER BY FIELD(v.status,'pending','approved','rejected'), v.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifications – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1>Verification Requests</h1>
        <p>Review identity and organization verification document submissions.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i><span>Verification status updated.</span></div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
        <?php foreach ([''=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v): ?>
        <a href="?_sess=UMDC_ADMIN<?= $k ? '&filter='.$k : '' ?>"
           class="<?= $filter===$k ? 'btn-primary' : 'btn-secondary' ?>"
           style="padding:7px 16px;font-size:.82rem;text-decoration:none;"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div style="overflow-x:auto;">
            <table class="table-custom">
                <thead>
                    <tr><th>User</th><th>Role</th><th>Document Type</th><th>Submitted</th><th>Status</th><th>Notes</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $v): ?>
                <tr>
                    <td>
                        <strong><?= e($v['user_name']) ?></strong>
                        <div style="font-size:.75rem;color:var(--color-muted);"><?= e($v['email']) ?></div>
                    </td>
                    <td><span class="badge-pill badge-role-<?= e($v['role_name']) ?>"><?= ucfirst($v['role_name']) ?></span></td>
                    <td style="font-size:.85rem;"><?= e($v['document_type'] ?? '—') ?></td>
                    <td style="font-size:.78rem;color:var(--color-muted);"><?= date('M d, Y', strtotime($v['created_at'])) ?></td>
                    <td><span class="badge-pill badge-<?= $v['status']==='approved'?'active':($v['status']==='rejected'?'failed':'pending') ?>"><?= ucfirst($v['status']) ?></span></td>
                    <td style="font-size:.8rem;color:var(--color-muted);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= e($v['review_notes'] ?: '—') ?>
                    </td>
                    <td>
                        <?php if ($v['status'] === 'pending'): ?>
                        <button class="btn-primary" style="font-size:.75rem;padding:5px 10px;margin-bottom:4px;"
                                onclick="approveVerif(<?= $v['id'] ?>)">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn-danger" style="font-size:.75rem;padding:5px 10px;"
                                onclick="rejectVerif(<?= $v['id'] ?>, '<?= e($v['user_name']) ?>')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--color-muted);">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($requests)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--color-muted);">No verification requests found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Hidden form for actions -->
<form method="POST" id="verifActionForm" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="req_id" id="verifReqId">
    <input type="hidden" name="action" id="verifAction">
    <input type="hidden" name="review_notes" id="verifNotes">
</form>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>
window._SESS = '<?= $_SESS_PARAM ?>';

function approveVerif(id) {
    if (!confirm('Approve this verification request?')) return;
    document.getElementById('verifReqId').value   = id;
    document.getElementById('verifAction').value  = 'approve';
    document.getElementById('verifNotes').value   = '';
    document.getElementById('verifActionForm').submit();
}
function rejectVerif(id, name) {
    const reason = prompt('Rejection reason for ' + name + ' (required):');
    if (!reason) return;
    document.getElementById('verifReqId').value   = id;
    document.getElementById('verifAction').value  = 'reject';
    document.getElementById('verifNotes').value   = reason;
    document.getElementById('verifActionForm').submit();
}
</script>
</body></html>

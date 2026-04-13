<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $approval_id  = filter_var($_POST['approval_id'] ?? 0, FILTER_VALIDATE_INT);
    $action       = $_POST['action'] ?? '';
    $reason       = sanitizeInput($_POST['reason'] ?? '');
    $admin_id     = currentUserId();

    if ($approval_id && in_array($action, ['approve','reject'], true)) {
        $pdo->beginTransaction();
        try {
            // Fetch approval record
            $appr = $pdo->prepare("SELECT * FROM approvals WHERE approval_id=?")->execute([$approval_id]);
            $appr = $pdo->prepare("SELECT * FROM approvals WHERE approval_id=?");
            $appr->execute([$approval_id]);
            $appr = $appr->fetch();

            if ($appr) {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                $pdo->prepare("UPDATE approvals SET status=?, admin_id=?, reason=? WHERE approval_id=?")
                    ->execute([$newStatus, $admin_id, $reason, $approval_id]);

                // Update the underlying entity
                if ($appr['entity_type'] === 'campaign') {
                    $campStatus = $action === 'approve' ? 'approved' : 'rejected';
                    $pdo->prepare("UPDATE campaigns SET status=? WHERE campaign_id=?")
                        ->execute([$campStatus, $appr['entity_id']]);
                } elseif ($appr['entity_type'] === 'organization') {
                    if ($action === 'approve') {
                        $pdo->prepare("UPDATE organizations SET verified=1 WHERE org_id=?")
                            ->execute([$appr['entity_id']]);
                    }
                }

                auditLog($pdo, $admin_id, "approval_{$action}", $appr['entity_type'], $appr['entity_id']);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Approval action: ' . $e->getMessage());
        }
    }
    header('Location: /admin/approvals.php?updated=1');
    exit;
}

// Load pending approvals
$approvals = $pdo->query("
    SELECT a.*,
           CASE
               WHEN a.entity_type='campaign' THEN c.title
               WHEN a.entity_type='organization' THEN o.org_name
               ELSE 'Unknown'
           END AS entity_name,
           CASE
               WHEN a.entity_type='campaign' THEN (SELECT CONCAT(u2.first_name,' ',u2.last_name) FROM organizations org2 JOIN users u2 ON org2.user_id=u2.user_id WHERE org2.org_id=c.org_id)
               WHEN a.entity_type='organization' THEN (SELECT CONCAT(u3.first_name,' ',u3.last_name) FROM users u3 WHERE u3.user_id=o.user_id)
               ELSE '—'
           END AS submitted_by,
           CASE WHEN a.entity_type='campaign' THEN c.description
                WHEN a.entity_type='organization' THEN o.description
                ELSE '' END AS entity_desc,
           CASE WHEN a.entity_type='campaign' THEN c.target_amount ELSE NULL END AS target_amount
    FROM approvals a
    LEFT JOIN campaigns c ON a.entity_type='campaign' AND a.entity_id=c.campaign_id
    LEFT JOIN organizations o ON a.entity_type='organization' AND a.entity_id=o.org_id
    WHERE a.status='pending'
    ORDER BY a.created_at ASC
")->fetchAll();

$user_name = e($_SESSION['user_name'] ?? 'Admin');
$initials  = strtoupper(substr($user_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approvals – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>




<main class="main-content">
    <div class="page-header">
        <h1>Pending Approvals</h1>
        <p><?= count($approvals) ?> item<?= count($approvals) !== 1 ? 's' : '' ?> awaiting your review.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i><span>Approval action completed.</span></div>
    <?php endif; ?>

    <?php if (empty($approvals)): ?>
    <div class="card">
        <div class="empty-state" style="padding:60px 20px;">
            <i class="fas fa-check-circle" style="color:var(--color-success);opacity:1;"></i>
            <h6>All Caught Up!</h6>
            <p>No pending approvals at this time.</p>
        </div>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($approvals as $a): ?>
        <div class="card">
            <div class="card-body" style="padding:24px;">
                <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:260px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <span class="badge-pill badge-pending" style="text-transform:capitalize;"><?= e($a['entity_type']) ?></span>
                            <span style="font-size:.75rem;color:var(--color-muted);">Submitted <?= date('M d, Y', strtotime($a['created_at'])) ?></span>
                        </div>
                        <h4 style="font-weight:700;margin-bottom:6px;"><?= e($a['entity_name'] ?? 'Unknown') ?></h4>
                        <div style="font-size:.82rem;color:var(--color-muted);margin-bottom:10px;">
                            Submitted by: <strong><?= e($a['submitted_by'] ?? '—') ?></strong>
                        </div>
                        <?php if ($a['entity_desc']): ?>
                        <p style="font-size:.875rem;color:var(--color-muted);line-height:1.6;margin-bottom:10px;"><?= e($a['entity_desc']) ?></p>
                        <?php endif; ?>
                        <?php if ($a['target_amount']): ?>
                        <div style="font-size:.82rem;">Target: <strong class="amount-mono">₱<?= number_format((float)$a['target_amount'], 2) ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;min-width:220px;">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="approval_id" value="<?= $a['approval_id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:10px;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <button class="btn-danger" style="width:100%;justify-content:center;padding:10px;"
                                onclick="document.getElementById('reject-form-<?= $a['approval_id'] ?>').classList.toggle('active')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <div id="reject-form-<?= $a['approval_id'] ?>" style="display:none;" class="">
                        </div>
                        <form method="POST" id="reject-form-<?= $a['approval_id'] ?>" style="display:none;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="approval_id" value="<?= $a['approval_id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <textarea class="form-textarea" name="reason" placeholder="Reason for rejection (optional)..." rows="2" style="margin-bottom:8px;"></textarea>
                            <button type="submit" class="btn-danger" style="width:100%;justify-content:center;">Confirm Rejection</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>
// Toggle reject form
document.querySelectorAll('[onclick*="reject-form"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('onclick').match(/reject-form-(\d+)/)?.[1];
        if (id) {
            const form = document.getElementById('reject-form-' + id);
            if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    });
});
</script>
</body>
</html>

<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

// Handle approve / reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cid    = filter_var($_POST['campaign_id'] ?? 0, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $reason = sanitizeInput($_POST['reason'] ?? '');

    if ($cid && in_array($action, ['approve','reject'], true)) {
        $pdo->beginTransaction();
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE campaigns SET status=? WHERE campaign_id=?")->execute([$newStatus, $cid]);
        $pdo->prepare("UPDATE approvals SET status=?, admin_id=?, reason=? WHERE entity_id=? AND entity_type='campaign'")
            ->execute([$newStatus, currentUserId(), $reason, $cid]);
        auditLog($pdo, currentUserId(), 'campaign_' . $action, 'campaign', $cid);
        $pdo->commit();
        header("Location: /admin/review_campaigns.php{$_SESS_PARAM}&success={$action}");
        exit;
    }
}

// Filters
$filter = in_array($_GET['filter'] ?? '', ['pending','approved','rejected','active','completed']) ? $_GET['filter'] : '';
$search = sanitizeInput($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
if ($filter) { $where[] = "c.status = ?"; $params[] = $filter; }
if ($search) { $where[] = "(c.title LIKE ? OR o.org_name LIKE ?)"; $s = "%$search%"; $params = array_merge($params, [$s, $s]); }
$whereSQL = implode(' AND ', $where);

$campaigns = $pdo->prepare("
    SELECT c.*, o.org_name, CONCAT(u.first_name,' ',u.last_name) AS owner,
           COALESCE(c.current_amount, 0) AS raised,
           (SELECT COUNT(*) FROM donations d WHERE d.campaign_id=c.campaign_id AND d.status='completed') AS donor_count
    FROM campaigns c
    JOIN organizations o ON c.org_id = o.org_id
    JOIN users u ON o.user_id = u.user_id
    WHERE $whereSQL
    ORDER BY FIELD(c.status,'pending','approved','active','rejected','completed'), c.created_at DESC
");
$campaigns->execute($params);
$campaigns = $campaigns->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campaigns – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1>Campaign Management</h1>
        <p>Review, approve, and manage all platform campaigns.</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-<?= $_GET['success']==='approve' ? 'success' : 'danger' ?>" data-auto-dismiss>
        <i class="fas fa-<?= $_GET['success']==='approve' ? 'check-circle' : 'times-circle' ?>"></i>
        <span>Campaign <?= $_GET['success']==='approve' ? 'approved' : 'rejected' ?> successfully.</span>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="_sess" value="UMDC_ADMIN">
                <input class="form-input" type="text" name="q" placeholder="Search campaign or org…" value="<?= e($search) ?>" style="max-width:240px;">
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php foreach (['','pending','approved','active','rejected','completed'] as $f): ?>
                    <a href="?_sess=UMDC_ADMIN<?= $f ? '&filter='.$f : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                       class="<?= $filter===$f ? 'btn-primary' : 'btn-secondary' ?>"
                       style="padding:6px 14px;font-size:.8rem;text-decoration:none;">
                        <?= $f ? ucfirst($f) : 'All' ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <span style="margin-left:auto;font-size:.82rem;color:var(--color-muted);"><?= count($campaigns) ?> campaign<?= count($campaigns)!==1?'s':'' ?></span>
            </form>
        </div>
    </div>

    <!-- Campaign list -->
    <?php if (empty($campaigns)): ?>
    <div class="card">
        <div class="empty-state"><i class="fas fa-bullhorn"></i><h6>No campaigns found</h6><p>Try adjusting your filters.</p></div>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($campaigns as $c):
        $pct = $c['target_amount'] > 0 ? min(100, ($c['raised'] / $c['target_amount']) * 100) : 0;
    ?>
    <div class="card">
        <div class="card-body" style="padding:22px 24px;">
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <!-- Left: info -->
                <div style="flex:1;min-width:260px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                        <span class="badge-pill badge-<?= e($c['status']) ?>"><?= ucfirst($c['status']) ?></span>
                        <span style="font-size:.75rem;color:var(--color-muted);"><?= e($c['category'] ?? '—') ?></span>
                        <span style="font-size:.75rem;color:var(--color-muted);">Submitted <?= date('M d, Y', strtotime($c['created_at'])) ?></span>
                    </div>
                    <h4 style="font-weight:700;font-size:1rem;margin-bottom:4px;"><?= e($c['title']) ?></h4>
                    <div style="font-size:.82rem;color:var(--color-muted);margin-bottom:10px;">
                        <i class="fas fa-building" style="margin-right:4px;"></i><?= e($c['org_name']) ?> &nbsp;·&nbsp;
                        <i class="fas fa-user" style="margin-right:4px;"></i><?= e($c['owner']) ?>
                    </div>
                    <p style="font-size:.855rem;line-height:1.6;color:var(--color-muted);margin-bottom:12px;
                               display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                        <?= e($c['description'] ?? '') ?>
                    </p>
                    <div style="display:flex;gap:24px;font-size:.82rem;flex-wrap:wrap;">
                        <div>
                            <span style="color:var(--color-muted);">Target</span>
                            <strong style="display:block;font-family:var(--font-mono);">₱<?= number_format((float)$c['target_amount'], 2) ?></strong>
                        </div>
                        <div>
                            <span style="color:var(--color-muted);">Raised</span>
                            <strong style="display:block;font-family:var(--font-mono);color:var(--color-success);">₱<?= number_format((float)$c['raised'], 2) ?></strong>
                        </div>
                        <div>
                            <span style="color:var(--color-muted);">Donors</span>
                            <strong style="display:block;"><?= (int)$c['donor_count'] ?></strong>
                        </div>
                        <div>
                            <span style="color:var(--color-muted);">End Date</span>
                            <strong style="display:block;"><?= $c['end_date'] ? date('M d, Y', strtotime($c['end_date'])) : '—' ?></strong>
                        </div>
                    </div>
                    <?php if ($c['raised'] > 0): ?>
                    <div style="margin-top:10px;">
                        <div class="progress-wrap"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
                        <div style="font-size:.7rem;color:var(--color-muted);margin-top:3px;"><?= number_format($pct, 1) ?>% funded</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right: actions -->
                <?php if ($c['status'] === 'pending'): ?>
                <div style="display:flex;flex-direction:column;gap:8px;min-width:200px;">
                    <form method="POST" style="display:contents;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
                        <button name="action" value="approve" class="btn-primary" style="justify-content:center;"
                                onclick="return confirm('Approve this campaign?')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    </form>
                    <form method="POST" id="reject-<?= $c['campaign_id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="button" class="btn-danger" style="width:100%;justify-content:center;"
                                onclick="document.getElementById('rr-<?= $c['campaign_id'] ?>').style.display=document.getElementById('rr-<?= $c['campaign_id'] ?>').style.display==='none'?'block':'none'">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <div id="rr-<?= $c['campaign_id'] ?>" style="display:none;margin-top:8px;">
                            <textarea class="form-textarea" name="reason" placeholder="Reason for rejection…" rows="2" style="margin-bottom:8px;"></textarea>
                            <button type="submit" class="btn-danger" style="width:100%;justify-content:center;"
                                    onclick="return confirm('Confirm rejection?')">Confirm Rejection</button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;min-width:120px;">
                    <span style="font-size:.8rem;color:var(--color-muted);font-style:italic;">Already <?= $c['status'] ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body></html>

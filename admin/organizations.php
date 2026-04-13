<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

// Handle verify / unverify
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $org_id = filter_var($_POST['org_id'] ?? 0, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if ($org_id && in_array($action, ['verify','unverify'], true)) {
        $pdo->prepare("UPDATE organizations SET verified=? WHERE org_id=?")
            ->execute([$action === 'verify' ? 1 : 0, $org_id]);
        auditLog($pdo, currentUserId(), "org_{$action}", 'organization', $org_id);
    }
    header("Location: /admin/organizations.php{$_SESS_PARAM}&updated=1");
    exit;
}

$search = sanitizeInput($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(o.org_name LIKE ? OR u.email LIKE ?)";
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s]);
}
if ($filter === 'verified')   { $where[] = "o.verified = 1"; }
if ($filter === 'unverified') { $where[] = "o.verified = 0"; }
$whereSQL = implode(' AND ', $where);

$orgs = $pdo->prepare("
    SELECT o.*, CONCAT(u.first_name,' ',u.last_name) AS owner_name, u.email,
           COUNT(c.campaign_id) AS campaign_count,
           COALESCE(SUM(CASE WHEN d.status='completed' THEN d.amount ELSE 0 END), 0) AS total_raised
    FROM organizations o
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN campaigns c ON c.org_id = o.org_id
    LEFT JOIN donations d ON d.campaign_id = c.campaign_id
    WHERE $whereSQL
    GROUP BY o.org_id
    ORDER BY o.created_at DESC
");
$orgs->execute($params);
$orgs = $orgs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organizations – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1>Organizations</h1>
        <p>Manage registered organizations and their verification status.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i><span>Organization updated successfully.</span></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="_sess" value="UMDC_ADMIN">
                <input class="form-input" type="text" name="q" placeholder="Search org or owner…" value="<?= e($search) ?>" style="max-width:240px;">
                <?php foreach ([''=>'All','verified'=>'Verified','unverified'=>'Unverified'] as $k=>$v): ?>
                <a href="?_sess=UMDC_ADMIN<?= $k ? '&filter='.$k : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                   class="<?= $filter===$k ? 'btn-primary' : 'btn-secondary' ?>"
                   style="padding:6px 14px;font-size:.8rem;text-decoration:none;"><?= $v ?></a>
                <?php endforeach; ?>
                <button type="submit" class="btn-primary" style="padding:8px 18px;">Search</button>
                <span style="margin-left:auto;font-size:.82rem;color:var(--color-muted);"><?= count($orgs) ?> org<?= count($orgs)!==1?'s':'' ?></span>
            </form>
        </div>
    </div>

    <div class="card">
        <div style="overflow-x:auto;">
            <table class="table-custom">
                <thead>
                    <tr><th>Organization</th><th>Owner</th><th>Contact</th><th>Campaigns</th><th>Total Raised</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($orgs as $o): ?>
                <tr>
                    <td>
                        <strong><?= e($o['org_name']) ?></strong>
                        <?php if ($o['description']): ?>
                        <div style="font-size:.75rem;color:var(--color-muted);margin-top:2px;"><?= e(substr($o['description'], 0, 55)) ?>…</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><?= e($o['owner_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--color-muted);"><?= e($o['email']) ?></div>
                    </td>
                    <td style="font-size:.82rem;color:var(--color-muted);"><?= e($o['org_contact'] ?: '—') ?></td>
                    <td><?= (int)$o['campaign_count'] ?></td>
                    <td class="amount-mono" style="color:var(--color-success);">₱<?= number_format((float)$o['total_raised'], 2) ?></td>
                    <td>
                        <span class="badge-pill <?= $o['verified'] ? 'badge-active' : 'badge-pending' ?>">
                            <?= $o['verified'] ? 'Verified' : 'Unverified' ?>
                        </span>
                    </td>
                    <td style="font-size:.78rem;color:var(--color-muted);"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="org_id" value="<?= $o['org_id'] ?>">
                            <?php if (!$o['verified']): ?>
                            <button name="action" value="verify" class="btn-primary" style="font-size:.75rem;padding:5px 12px;"
                                    onclick="return confirm('Verify this organization?')">
                                <i class="fas fa-check"></i> Verify
                            </button>
                            <?php else: ?>
                            <button name="action" value="unverify" class="btn-secondary" style="font-size:.75rem;padding:5px 12px;"
                                    onclick="return confirm('Revoke verification?')">
                                <i class="fas fa-times"></i> Revoke
                            </button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orgs)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--color-muted);">No organizations found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body></html>

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
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = sanitizeInput($_POST['name'] ?? '');
        $endpoint = sanitizeInput($_POST['api_endpoint'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO couriers (name, api_endpoint, active) VALUES (?, ?, 1)")
                ->execute([$name, $endpoint ?: null]);
        }
    } elseif ($action === 'toggle') {
        $id = filter_var($_POST['courier_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id) $pdo->prepare("UPDATE couriers SET active = NOT active WHERE courier_id=?")->execute([$id]);
    } elseif ($action === 'approve_delivery') {
        $did = filter_var($_POST['delivery_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($did) {
            $pdo->prepare("UPDATE deliveries SET status='approved', approved_by=? WHERE delivery_id=?")->execute([currentUserId(), $did]);
            $pdo->prepare("UPDATE approvals SET status='approved', admin_id=? WHERE entity_id=? AND entity_type='delivery'")->execute([currentUserId(), $did]);
        }
    }
    header("Location: /admin/couriers.php{$_SESS_PARAM}");
    exit;
}

$couriers  = $pdo->query("SELECT * FROM couriers ORDER BY name")->fetchAll();
$deliveries = $pdo->query("
    SELECT dv.*, c.name AS courier_name, i.item_name, i.quantity,
           CONCAT(u.first_name,' ',u.last_name) AS donor_name,
           cam.title AS campaign_title
    FROM deliveries dv
    JOIN couriers c ON dv.courier_id = c.courier_id
    JOIN item_donations i ON dv.item_id = i.item_id
    JOIN donations d ON i.donation_id = d.donation_id
    JOIN users u ON d.user_id = u.user_id
    JOIN campaigns cam ON d.campaign_id = cam.campaign_id
    ORDER BY dv.created_at DESC LIMIT 50
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Couriers – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div><h1>Courier Management</h1><p>Manage delivery couriers and item donation pickup requests.</p></div>
        <button class="btn-primary" onclick="openModal('addCourierModal')"><i class="fas fa-plus"></i> Add Courier</button>
    </div>

    <!-- Couriers list -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h5><i class="fas fa-truck" style="color:var(--brand-primary);margin-right:8px;"></i>Registered Couriers</h5></div>
        <table class="table-custom">
            <thead><tr><th>Name</th><th>API Endpoint</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($couriers as $c): ?>
            <tr>
                <td><strong><?= e($c['name']) ?></strong></td>
                <td style="font-size:.8rem;color:var(--color-muted);font-family:var(--font-mono);"><?= e($c['api_endpoint'] ?: '—') ?></td>
                <td><span class="badge-pill <?= $c['active'] ? 'badge-active' : 'badge-failed' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="courier_id" value="<?= $c['courier_id'] ?>">
                        <button type="submit" class="btn-secondary" style="font-size:.75rem;padding:5px 12px;">
                            <?= $c['active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($couriers)): ?>
            <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--color-muted);">No couriers added yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Delivery requests -->
    <div class="card">
        <div class="card-header"><h5><i class="fas fa-box" style="color:var(--brand-secondary);margin-right:8px;"></i>Delivery Requests</h5></div>
        <div style="overflow-x:auto;">
            <table class="table-custom">
                <thead><tr><th>Donor</th><th>Item</th><th>Campaign</th><th>Courier</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($deliveries as $d): ?>
                <tr>
                    <td><?= e($d['donor_name']) ?></td>
                    <td><strong><?= e($d['item_name']) ?></strong> ×<?= $d['quantity'] ?></td>
                    <td style="font-size:.8rem;color:var(--color-muted);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['campaign_title']) ?></td>
                    <td><?= e($d['courier_name']) ?></td>
                    <td><span class="badge-pill badge-<?= in_array($d['status'],['approved','delivered'])?'active':($d['status']==='cancelled'?'failed':'pending') ?>"><?= ucfirst($d['status']) ?></span></td>
                    <td style="font-size:.78rem;color:var(--color-muted);"><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                    <td>
                        <?php if ($d['status'] === 'requested'): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve_delivery">
                            <input type="hidden" name="delivery_id" value="<?= $d['delivery_id'] ?>">
                            <button type="submit" class="btn-primary" style="font-size:.75rem;padding:5px 12px;"
                                    onclick="return confirm('Approve this delivery request?')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--color-muted);">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($deliveries)): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--color-muted);">No delivery requests</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add Courier Modal -->
<div class="modal-overlay" id="addCourierModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <h4><i class="fas fa-truck" style="color:var(--brand-primary);"></i> Add Courier</h4>
            <button class="modal-close" onclick="closeModal('addCourierModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Courier Name</label>
                <input class="form-input" type="text" name="name" placeholder="e.g., LBC Express" required>
            </div>
            <div class="form-group">
                <label class="form-label">API Endpoint (optional)</label>
                <input class="form-input" type="url" name="api_endpoint" placeholder="https://api.courier.com/track">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn-secondary" onclick="closeModal('addCourierModal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Add Courier</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script>window._SESS = '<?= $_SESS_PARAM ?>';</script>
</body></html>

<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireRole('admin');
setSecureHeaders();
$_SESS_PARAM = '?_sess=UMDC_ADMIN';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? '';
    $user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);

    if ($user_id && $user_id !== currentUserId()) {
        if ($action === 'suspend') {
            $pdo->prepare("UPDATE users SET status='suspended' WHERE user_id=?")->execute([$user_id]);
            auditLog($pdo, currentUserId(), 'user_suspended', 'user', $user_id);
        } elseif ($action === 'activate') {
            $pdo->prepare("UPDATE users SET status='active' WHERE user_id=?")->execute([$user_id]);
            auditLog($pdo, currentUserId(), 'user_activated', 'user', $user_id);
        } elseif ($action === 'ban') {
            $pdo->prepare("UPDATE users SET status='banned' WHERE user_id=?")->execute([$user_id]);
            auditLog($pdo, currentUserId(), 'user_banned', 'user', $user_id);
        }
    }
    header('Location: /admin/users.php?updated=1');
    exit;
}

// Filters
$search    = sanitizeInput($_GET['q'] ?? '');
$roleFilter= sanitizeInput($_GET['role'] ?? '');
$status    = sanitizeInput($_GET['status'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $s = "%$search%";
    $params   = array_merge($params, [$s, $s, $s]);
}
if ($roleFilter) {
    $where[]  = "r.role_name = ?";
    $params[] = $roleFilter;
}
if ($status) {
    $where[]  = "u.status = ?";
    $params[] = $status;
}

$whereSQL = implode(' AND ', $where);
$total    = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.role_id WHERE $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at,
           r.role_name,
           (SELECT COUNT(*) FROM donations WHERE user_id=u.user_id AND status='completed') AS donation_count,
           (SELECT COALESCE(SUM(amount),0) FROM donations WHERE user_id=u.user_id AND status='completed') AS total_donated
    FROM users u JOIN roles r ON u.role_id=r.role_id
    WHERE $whereSQL
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$user_name = e($_SESSION['user_name'] ?? 'Admin');
$initials  = strtoupper(substr($user_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title>Users – UMDC Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>




<main class="main-content">
    <div class="page-header">
        <h1>User Management</h1>
        <p>View, search, and manage all registered users.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i><span>User status updated successfully.</span></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:16px 20px;">
            <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <input class="form-input" type="text" name="q" placeholder="Search name or email…" value="<?= e($search) ?>" style="max-width:260px;">
                <select class="form-input" name="role" style="max-width:160px;">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>Admin</option>
                    <option value="organization" <?= $roleFilter==='organization'?'selected':'' ?>>Organization</option>
                    <option value="user" <?= $roleFilter==='user'?'selected':'' ?>>User</option>
                </select>
                <select class="form-input" name="status" style="max-width:160px;">
                    <option value="">All Status</option>
                    <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
                    <option value="suspended" <?= $status==='suspended'?'selected':'' ?>>Suspended</option>
                    <option value="banned" <?= $status==='banned'?'selected':'' ?>>Banned</option>
                </select>
                <button type="submit" class="btn-primary" style="padding:9px 20px;">Search</button>
                <?php if ($search || $roleFilter || $status): ?>
                <a href="/admin/users.php<?= $_SESS_PARAM ?>" class="btn-secondary" style="padding:9px 20px;">Clear</a>
                <?php endif; ?>
                <span style="color:var(--color-muted);font-size:.82rem;margin-left:auto;"><?= number_format($total) ?> user<?= $total !== 1 ? 's' : '' ?></span>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div style="overflow-x:auto;">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Donations</th>
                        <th>Total Given</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
                    <td style="font-size:.82rem;color:var(--color-muted);"><?= e($u['email']) ?></td>
                    <td><span class="badge-pill badge-role-<?= e($u['role_name']) ?>"><?= ucfirst($u['role_name']) ?></span></td>
                    <td><?= (int)$u['donation_count'] ?></td>
                    <td class="amount-mono">₱<?= number_format((float)$u['total_donated'], 2) ?></td>
                    <td><span class="badge-pill badge-<?= $u['status'] === 'active' ? 'active' : ($u['status'] === 'suspended' ? 'pending' : 'banned') ?>"><?= ucfirst($u['status']) ?></span></td>
                    <td style="font-size:.8rem;color:var(--color-muted);"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['user_id'] !== currentUserId()): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <?php if ($u['status'] !== 'active'): ?>
                            <button type="submit" name="action" value="activate" class="btn-primary" style="font-size:.75rem;padding:5px 12px;">Activate</button>
                            <?php else: ?>
                            <button type="submit" name="action" value="suspend" class="btn-secondary" style="font-size:.75rem;padding:5px 12px;"
                                    onclick="return confirm('Suspend this user?')">Suspend</button>
                            <button type="submit" name="action" value="ban" class="btn-danger" style="font-size:.75rem;padding:5px 12px;"
                                    onclick="return confirm('Ban this user? This is serious.')">Ban</button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--color-muted);">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--color-muted);">No users found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div style="padding:16px 20px;display:flex;gap:8px;justify-content:center;border-top:1px solid var(--color-border);">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="?q=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>&_sess=UMDC_ADMIN"
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

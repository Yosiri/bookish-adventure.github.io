<?php
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';
umdc_session_start();
requireAnyRole(['user','organization']);
setSecureHeaders();

$user_id = currentUserId();
$role    = currentRole();

// Check existing request
$existing = $pdo->prepare("SELECT * FROM verification_requests WHERE user_id=? AND type=? ORDER BY created_at DESC LIMIT 1");
$existing->execute([$user_id, $role]);
$existing = $existing->fetch();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($existing && $existing['status'] === 'pending') {
        $error = 'You already have a pending verification request.';
    } else {
        $docType  = sanitizeInput($_POST['document_type'] ?? '');
        $notes    = sanitizeInput($_POST['notes'] ?? '');
        $file     = $_FILES['document'] ?? null;

        if (!$docType) {
            $error = 'Please select a document type.';
        } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please upload a valid document.';
        } else {
            // Validate file type
            $allowedTypes = ['image/jpeg','image/png','application/pdf'];
            $finfo        = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType     = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes, true)) {
                $error = 'Only JPEG, PNG, and PDF files are accepted.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'File must be under 5MB.';
            } else {
                $uploadDir = __DIR__ . '/../../uploads/verification/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);

                $safeName  = bin2hex(random_bytes(16)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                $destPath  = $uploadDir . $safeName;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $pdo->prepare("
                        INSERT INTO verification_requests (user_id, type, document_type, document_path, status)
                        VALUES (?, ?, ?, ?, 'pending')
                    ")->execute([$user_id, $role, $docType, 'verification/' . $safeName]);

                    auditLog($pdo, $user_id, 'verification_submitted');
                    $success = 'Verification request submitted! Our team will review within 2–3 business days.';
                    // Reload existing
                    $existing = $pdo->prepare("SELECT * FROM verification_requests WHERE user_id=? AND type=? ORDER BY created_at DESC LIMIT 1");
                    $existing->execute([$user_id, $role]);
                    $existing = $existing->fetch();
                } else {
                    $error = 'File upload failed. Please try again.';
                }
            }
        }
    }
}

$user_name = e($_SESSION['user_name'] ?? 'User');
$initials  = strtoupper(substr($user_name, 0, 2));
$dashLink  = $role === 'organization' ? '/dashboard/organization.php?_sess=UMDC_ORG' : '/dashboard/user.php?_sess=UMDC_USER';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verification – UMDC</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/global.css">
<link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<aside class="sidebar">
    <div class="sidebar-logo"><div class="brand-name">UMDC</div><div class="brand-sub"><?= ucfirst($role) ?> Portal</div></div>
    <nav class="sidebar-nav">
        <a href="<?= $dashLink ?>" class="sidebar-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= $initials ?></div>
            <div><div class="sidebar-user-name"><?= $user_name ?></div><div class="sidebar-user-role"><?= ucfirst($role) ?></div></div>
            <button class="logout-btn" data-logout><i class="fas fa-sign-out-alt"></i></button>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="page-header">
        <h1>Identity Verification</h1>
        <p>Submit your documents to get verified and unlock all platform features.</p>
    </div>

    <!-- Status Card -->
    <?php if ($existing): ?>
    <div class="card" style="margin-bottom:24px;">
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;">
                <?php if ($existing['status'] === 'approved'): ?>
                <i class="fas fa-check-circle" style="font-size:2rem;color:var(--color-success);"></i>
                <div>
                    <h5 style="font-weight:700;color:var(--color-success);">Verified ✓</h5>
                    <p style="color:var(--color-muted);font-size:.875rem;">Your account is verified.</p>
                </div>
                <?php elseif ($existing['status'] === 'pending'): ?>
                <i class="fas fa-clock" style="font-size:2rem;color:var(--color-warning);"></i>
                <div>
                    <h5 style="font-weight:700;color:var(--color-warning);">Under Review</h5>
                    <p style="color:var(--color-muted);font-size:.875rem;">Submitted <?= date('M d, Y', strtotime($existing['created_at'])) ?>. We'll notify you when complete.</p>
                </div>
                <?php elseif ($existing['status'] === 'rejected'): ?>
                <i class="fas fa-times-circle" style="font-size:2rem;color:var(--color-danger);"></i>
                <div>
                    <h5 style="font-weight:700;color:var(--color-danger);">Rejected</h5>
                    <p style="color:var(--color-muted);font-size:.875rem;"><?= e($existing['review_notes'] ?? 'Please resubmit with valid documents.') ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i><span><?= e($success) ?></span></div>
    <?php endif; ?>

    <?php if (!$existing || $existing['status'] === 'rejected'): ?>
    <div class="card" style="max-width:560px;">
        <div class="card-header"><h5>Submit Verification Documents</h5></div>
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label">Document Type</label>
                    <select class="form-input" name="document_type" required>
                        <option value="">Select a document type…</option>
                        <?php if ($role === 'user'): ?>
                        <option value="National ID">National ID (PhilSys)</option>
                        <option value="Passport">Passport</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Voter's ID">Voter's ID</option>
                        <option value="SSS ID">SSS / GSIS ID</option>
                        <?php else: ?>
                        <option value="SEC Registration">SEC Registration</option>
                        <option value="BIR Certificate">BIR Certificate of Registration</option>
                        <option value="DSWD Accreditation">DSWD Accreditation</option>
                        <option value="Mayor's Permit">Mayor's Permit</option>
                        <option value="Audited Financial Statement">Audited Financial Statement</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Upload Document <span style="color:var(--color-muted);font-weight:400;">(JPG, PNG, or PDF — max 5MB)</span></label>
                    <input class="form-input" type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required style="padding:8px;">
                </div>

                <div class="form-group">
                    <label class="form-label">Additional Notes (optional)</label>
                    <textarea class="form-textarea" name="notes" rows="2" placeholder="Any additional information for our review team…"></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit for Review
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/dashboard.js"></script>
</body>
</html>

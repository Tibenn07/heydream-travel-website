<?php
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_partner = isset($_SESSION['partner_id']) && !empty($_SESSION['partner_id']);

if (!$is_admin && !$is_partner) {
    header('Location: partner-login.php');
    exit;
}

$partnerId = intval($_GET['id'] ?? 0);
if ($partnerId <= 0) {
    header('Location: partner-dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, company_name, contact_person, email, phone, website, business_type, status, approved_at, created_at FROM partner_applications WHERE id = ? LIMIT 1");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    header('Location: partner-dashboard.php');
    exit;
}

$heading = htmlspecialchars($partner['company_name'] ?: 'Partner Profile');
$contactPerson = htmlspecialchars($partner['contact_person'] ?: 'N/A');
$email = htmlspecialchars($partner['email'] ?: 'N/A');
$phone = htmlspecialchars($partner['phone'] ?: 'N/A');
$website = htmlspecialchars($partner['website'] ?: 'N/A');
$businessType = htmlspecialchars($partner['business_type'] ?: 'N/A');
$status = htmlspecialchars(ucfirst($partner['status'] ?: 'pending'));
$approvedAt = $partner['approved_at'] ? date('M d, Y', strtotime($partner['approved_at'])) : 'N/A';
$joinedAt = $partner['created_at'] ? date('M d, Y', strtotime($partner['created_at'])) : 'N/A';
$mailtoLink = 'mailto:' . rawurlencode($partner['email']) . '?subject=' . rawurlencode('HeyDream Partnership Inquiry');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Profile - <?= $heading ?></title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #0f172a; font-family: 'Poppins', sans-serif; margin: 0; padding: 0; }
        .page-shell { max-width: 980px; margin: 0 auto; padding: 32px 20px; }
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
        .page-header h1 { margin: 0; font-size: 2rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06); padding: 28px; }
        .profile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .profile-item { background: #f8fafc; border-radius: 16px; padding: 18px; }
        .profile-item h3 { margin: 0 0 10px; font-size: 0.95rem; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; }
        .profile-item p { margin: 0; font-size: 1rem; color: #0f172a; }
        .action-row { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 12px; }
        .action-row a, .action-row button { display: inline-flex; align-items: center; gap: 10px; padding: 14px 20px; border-radius: 999px; text-decoration: none; font-weight: 700; transition: all 0.2s ease; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; border: 1px solid transparent; }
        .btn-primary { background: #0f172a; color: white; border: 1px solid transparent; }
        .btn-primary:hover, .btn-secondary:hover { transform: translateY(-1px); }
        .status-chip { display: inline-flex; align-items: center; gap: 0.5rem; padding: 10px 14px; border-radius: 999px; background: #f1f5f9; color: #334155; font-weight: 700; }
        .status-chip.approved { background: #d1fae5; color: #065f46; }
        .status-chip.pending { background: #fef3c7; color: #92400e; }
        .status-chip.rejected { background: #fee2e2; color: #991b1b; }
        @media (max-width: 720px) { .profile-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="page-header">
            <div>
                <p style="margin:0;color:#64748b;font-size:0.95rem;">Partner Uploader Profile</p>
                <h1><?= $heading ?></h1>
            </div>
            <div class="status-chip <?= strtolower($partner['status'] ?? 'pending') ?>">
                <i class="fas fa-user-check"></i> <?= $status ?> Partner
            </div>
        </div>

        <div class="card">
            <div class="profile-grid">
                <div class="profile-item">
                    <h3>Contact Person</h3>
                    <p><?= $contactPerson ?></p>
                </div>
                <div class="profile-item">
                    <h3>Email Address</h3>
                    <p><?= $email ?></p>
                </div>
                <div class="profile-item">
                    <h3>Phone Number</h3>
                    <p><?= $phone ?></p>
                </div>
                <div class="profile-item">
                    <h3>Business Type</h3>
                    <p><?= $businessType ?></p>
                </div>
                <div class="profile-item" style="grid-column: span 2;">
                    <h3>Website</h3>
                    <p><?= $website !== 'N/A' ? '<a href="' . $website . '" target="_blank" rel="noopener noreferrer">' . $website . '</a>' : 'N/A' ?></p>
                </div>
                <div class="profile-item">
                    <h3>Joined On</h3>
                    <p><?= $joinedAt ?></p>
                </div>
                <div class="profile-item">
                    <h3>Approved On</h3>
                    <p><?= $approvedAt ?></p>
                </div>
            </div>

            <div class="action-row">
                <a href="<?= $mailtoLink ?>" class="btn-primary"><i class="fas fa-envelope"></i> Send Email</a>
                <a href="<?= $is_partner ? 'partner-dashboard.php' : 'dashboard.php' ?>" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>

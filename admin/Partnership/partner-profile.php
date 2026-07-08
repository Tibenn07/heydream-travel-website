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

$profileStmt = $pdo->prepare("SELECT * FROM partner_profiles WHERE partner_id = ? LIMIT 1");
$profileStmt->execute([$partnerId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    header('Location: partner-dashboard.php');
    exit;
}

$heading = htmlspecialchars($profile['business_display_name'] ?? $partner['company_name'] ?? 'Partner Profile');
$contactPerson = htmlspecialchars($partner['contact_person'] ?: 'N/A');
$email = htmlspecialchars($partner['email'] ?: 'N/A');
$phone = htmlspecialchars($profile['phone'] ?? $partner['phone'] ?? 'N/A');
$website = htmlspecialchars($profile['website'] ?? $partner['website'] ?? 'N/A');
$businessType = htmlspecialchars($partner['business_type'] ?: 'N/A');
$status = htmlspecialchars(ucfirst($partner['status'] ?: 'pending'));
$approvedAt = $partner['approved_at'] ? date('M d, Y', strtotime($partner['approved_at'])) : 'N/A';
$joinedAt = $partner['created_at'] ? date('M d, Y', strtotime($partner['created_at'])) : 'N/A';
$bio = htmlspecialchars($profile['bio'] ?? '');
$description = htmlspecialchars($profile['description'] ?? '');
$address = htmlspecialchars($profile['address'] ?? '');
$city = htmlspecialchars($profile['city'] ?? '');
$country = htmlspecialchars($profile['country'] ?? '');
$yearsInBusiness = htmlspecialchars($profile['years_in_business'] ?? '');
$teamSize = htmlspecialchars($profile['team_size'] ?? '');
$specialties = htmlspecialchars($profile['specialties'] ?? '');
$certifications = htmlspecialchars($profile['certifications'] ?? '');
$socialLinks = $profile['social_media_links'] ?? '';
$logoPath = $profile['logo_path'] ?? '';
$bannerPath = $profile['banner_image_path'] ?? '';
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
        body { background: linear-gradient(135deg, #eef6ff 0%, #f8fafc 100%); color: #0f172a; font-family: 'Poppins', sans-serif; margin: 0; padding: 0; }
        .page-shell { max-width: 1100px; margin: 0 auto; padding: 32px 20px 48px; }
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
        .page-header h1 { margin: 0; font-size: 2rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06); overflow: hidden; }
        .cover { min-height: 220px; background: linear-gradient(135deg, #0f4c81 0%, #5ca6ff 100%); position: relative; }
        .cover img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; }
        .hero { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 18px; padding: 18px 24px 0; margin-top: -40px; position: relative; z-index: 1; }
        .avatar { width: 96px; height: 96px; border-radius: 50%; border: 4px solid white; overflow: hidden; background: linear-gradient(135deg, #0f4c81, #f59e0b); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.1rem; font-weight: 800; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .hero-copy { background: white; border-radius: 18px; padding: 16px 18px; box-shadow: 0 12px 30px rgba(15,23,42,0.08); flex: 1; }
        .hero-copy h2 { margin: 0 0 6px; font-size: 1.35rem; }
        .hero-copy p { margin: 0; color: #64748b; line-height: 1.6; }
        .content-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; padding: 24px; }
        .panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; }
        .panel h3 { margin: 0 0 12px; font-size: 1rem; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; }
        .panel p, .panel li { color: #0f172a; line-height: 1.7; }
        .stats-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 999px; background: #e8f2ff; color: #0f4c81; font-weight: 600; font-size: 0.9rem; }
        .social-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .social-link { display: inline-flex; align-items: center; gap: 7px; padding: 8px 10px; border-radius: 999px; background: #fff; color: #0f4c81; text-decoration: none; border: 1px solid #dbeafe; font-size: 0.9rem; font-weight: 600; }
        .action-row { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 12px; }
        .action-row a, .action-row button { display: inline-flex; align-items: center; gap: 10px; padding: 14px 20px; border-radius: 999px; text-decoration: none; font-weight: 700; transition: all 0.2s ease; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; border: 1px solid transparent; }
        .btn-primary { background: #0f172a; color: white; border: 1px solid transparent; }
        .btn-primary:hover, .btn-secondary:hover { transform: translateY(-1px); }
        .status-chip { display: inline-flex; align-items: center; gap: 0.5rem; padding: 10px 14px; border-radius: 999px; background: #f1f5f9; color: #334155; font-weight: 700; }
        .status-chip.approved { background: #d1fae5; color: #065f46; }
        .status-chip.pending { background: #fef3c7; color: #92400e; }
        .status-chip.rejected { background: #fee2e2; color: #991b1b; }
        @media (max-width: 820px) { .content-grid { grid-template-columns: 1fr; } }
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
            <div class="cover">
                <?php if (!empty($bannerPath)): ?><img src="../<?= htmlspecialchars($bannerPath) ?>" alt="Cover photo"><?php endif; ?>
            </div>
            <div class="hero">
                <div class="avatar">
                    <?php if (!empty($logoPath)): ?>
                        <img src="../<?= htmlspecialchars($logoPath) ?>" alt="Partner logo">
                    <?php else: ?>
                        <span><?= htmlspecialchars(strtoupper(substr($heading, 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="hero-copy">
                    <h2><?= $heading ?></h2>
                    <p><?= !empty($bio) ? $bio : 'This partner is committed to delivering memorable travel experiences for every customer.' ?></p>
                </div>
            </div>
            <div class="content-grid">
                <div>
                    <div class="panel">
                        <h3>About the Partner</h3>
                        <p><?= !empty($description) ? $description : 'A trusted travel partner helping clients discover the right packages, experiences, and services for their next journey.' ?></p>
                        <div class="stats-row">
                            <?php if (!empty($yearsInBusiness)): ?><span class="chip"><i class="fas fa-calendar"></i> <?= htmlspecialchars($yearsInBusiness) ?> years</span><?php endif; ?>
                            <?php if (!empty($teamSize)): ?><span class="chip"><i class="fas fa-users"></i> <?= htmlspecialchars($teamSize) ?> team</span><?php endif; ?>
                            <?php if (!empty($specialties)): ?><span class="chip"><i class="fas fa-star"></i> <?= htmlspecialchars($specialties) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="panel" style="margin-top: 16px;">
                        <h3>Highlights</h3>
                        <ul>
                            <?php if (!empty($certifications)): ?><li><strong>Certifications:</strong> <?= htmlspecialchars($certifications) ?></li><?php endif; ?>
                            <li><strong>Business Type:</strong> <?= htmlspecialchars($businessType) ?></li>
                            <li><strong>Joined:</strong> <?= htmlspecialchars($joinedAt) ?></li>
                            <li><strong>Approved:</strong> <?= htmlspecialchars($approvedAt) ?></li>
                        </ul>
                    </div>
                </div>
                <div>
                    <div class="panel">
                        <h3>Contact Info</h3>
                        <p><strong>Contact Person:</strong> <?= htmlspecialchars($contactPerson) ?></p>
                        <p><strong>Email:</strong> <a href="<?= $mailtoLink ?>"><?= htmlspecialchars($email) ?></a></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($phone) ?></p>
                        <p><strong>Location:</strong> <?= htmlspecialchars(trim($address . ' ' . $city . ' ' . $country)) ?: 'Not provided' ?></p>
                        <?php if (!empty($website) && $website !== 'N/A'): ?>
                            <div class="social-links">
                                <a class="social-link" href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener"><i class="fas fa-globe"></i> Website</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="panel" style="margin-top: 16px;">
                        <h3>Social / Links</h3>
                        <?php if (!empty($socialLinks)): ?>
                            <div class="social-links">
                                <?php foreach (preg_split('/\r\n|\n|\r/', trim($socialLinks)) as $link): ?>
                                    <?php if (trim($link) !== ''): ?>
                                        <a class="social-link" href="<?= htmlspecialchars(trim($link)) ?>" target="_blank" rel="noopener"><i class="fas fa-link"></i> Visit</a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No extra social links were added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="action-row" style="padding: 0 24px 24px;">
                <a href="<?= $mailtoLink ?>" class="btn-primary"><i class="fas fa-envelope"></i> Send Email</a>
                <a href="<?= $is_partner ? 'partner-dashboard.php' : 'dashboard.php' ?>" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>

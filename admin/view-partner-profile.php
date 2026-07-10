<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/maps_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$partnerId = (int) ($_GET['id'] ?? 0);
if ($partnerId <= 0) {
    die('<h1>Invalid Partner</h1><p>No partner ID provided.</p>');
}

// Unlike the public view-partner-profile.php, admins can look up a partner
// regardless of approval status (a booking can reference a partner who is
// still pending or was later rejected).
$stmt = $pdo->prepare("SELECT * FROM partner_applications WHERE id = ? LIMIT 1");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    die('<h1>Partner Not Found</h1><p>The partner you are looking for does not exist.</p>');
}

$profileStmt = $pdo->prepare("SELECT * FROM partner_profiles WHERE partner_id = ? LIMIT 1");
$profileStmt->execute([$partnerId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$packagesStmt = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM flash_deals WHERE partner_id = ?) +
        (SELECT COUNT(*) FROM foreign_destinations WHERE partner_id = ?) +
        (SELECT COUNT(*) FROM destinations WHERE partner_id = ?) +
        (SELECT COUNT(*) FROM site_services WHERE partner_id = ?) +
        (SELECT COUNT(*) FROM cruises WHERE partner_id = ?) AS total
");
$packagesStmt->execute([$partnerId, $partnerId, $partnerId, $partnerId, $partnerId]);
$packagesListed = (int) ($packagesStmt->fetchColumn() ?: 0);

$heading = htmlspecialchars($profile['business_display_name'] ?? $partner['company_name'] ?? 'Partner Profile');
$contactPerson = htmlspecialchars($partner['contact_person'] ?: 'N/A');
$email = htmlspecialchars($partner['email'] ?: 'N/A');
$phone = htmlspecialchars($profile['phone'] ?? $partner['phone'] ?? 'N/A');
$website = htmlspecialchars($profile['website'] ?? $partner['website'] ?? 'N/A');
$businessType = htmlspecialchars($partner['business_type'] ?: 'N/A');
$businessSince = $partner['created_at'] ? date('Y', strtotime($partner['created_at'])) : date('Y');
$bio = htmlspecialchars($profile['bio'] ?? '');
$description = htmlspecialchars($profile['description'] ?? '');
$address = htmlspecialchars($profile['address'] ?? '');
$city = htmlspecialchars($profile['city'] ?? '');
$country = htmlspecialchars($profile['country'] ?? '');
$yearsInBusiness = htmlspecialchars($profile['years_in_business'] ?? '');
$teamSize = htmlspecialchars($profile['team_size'] ?? '');
$specialties = array_filter(array_map('trim', explode(',', $profile['specialties'] ?? '')));
$certifications = htmlspecialchars($profile['certifications'] ?? '');
$socialLinks = parseSocialLinks($profile['social_media_links'] ?? '');
$logoPath = $profile['logo_path'] ?? '';
$bannerPath = $profile['banner_image_path'] ?? '';
$locationParts = array_filter([$address, $city, $country]);
$locationText = implode(', ', $locationParts);
$mailtoLink = 'mailto:' . rawurlencode($partner['email']) . '?subject=' . rawurlencode('HeyDream Partnership Inquiry');

$statusKey = strtolower($partner['status'] ?? 'pending');
$statusLabel = ucfirst($statusKey);
$statusIcon = $statusKey === 'approved' ? 'fa-circle-check' : ($statusKey === 'rejected' ? 'fa-circle-xmark' : 'fa-clock');

$fromBookings = ($_GET['from'] ?? '') === 'bookings';
$backHref = $fromBookings ? 'dashboard.php#bookingsTableScroll' : 'dashboard.php';
$backLabel = $fromBookings ? 'Back to Bookings' : 'Back to Dashboard';

$hasMapQuery = $locationText !== '';
if ($hasMapQuery && GOOGLE_MAPS_API_KEY !== '') {
    $mapSrc = 'https://www.google.com/maps/embed/v1/place?key=' . urlencode(GOOGLE_MAPS_API_KEY) . '&q=' . urlencode($locationText);
} elseif ($hasMapQuery) {
    // No API key configured yet — fall back to the free, keyless embed so the map still works.
    $mapSrc = 'https://www.google.com/maps?q=' . urlencode($locationText) . '&output=embed';
} else {
    $mapSrc = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $heading ?> - HeyDream Partners</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7fb;
            --panel: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #0f4c81;
            --primary-soft: #e8f2ff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #eef6ff 0%, #f8fafc 100%);
            color: var(--text);
            min-height: 100vh;
        }
        .page-shell { max-width: 1080px; margin: 0 auto; padding: 28px 20px 56px; }

        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .top-brand { display: flex; align-items: center; gap: 10px; color: var(--primary); font-weight: 700; font-size: 1.05rem; }
        .top-brand img { height: 34px; width: auto; border-radius: 50%; }
        .top-back {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: 999px;
            background: #fff; border: 1px solid var(--border); color: var(--text);
            text-decoration: none; font-weight: 600; font-size: 0.85rem;
            box-shadow: 0 4px 12px rgba(15,23,42,0.05);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .top-back:hover { box-shadow: 0 8px 20px rgba(15,23,42,0.09); transform: translateY(-1px); }

        .profile-hero {
            position: relative;
            border-radius: 24px;
            padding: 36px;
            background: linear-gradient(135deg, #0f4c81 0%, #1d6fc7 55%, #4c9ce8 100%);
            color: #fff;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(15, 76, 129, 0.28);
            margin-bottom: 22px;
        }
        .profile-hero-banner { position: absolute; inset: 0; opacity: 0.35; }
        .profile-hero-banner img { width: 100%; height: 100%; object-fit: cover; }
        .profile-hero-inner { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 32px; align-items: center; justify-content: space-between; }
        .profile-hero-left { display: flex; align-items: center; gap: 20px; min-width: 260px; }
        .profile-avatar {
            width: 88px; height: 88px; border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.85); background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.1rem; font-weight: 800; overflow: hidden; flex-shrink: 0;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-hero-name { font-size: 1.55rem; font-weight: 700; margin: 0 0 6px; }
        .profile-hero-bio { margin: 0 0 10px; color: rgba(255,255,255,0.88); font-size: 0.92rem; line-height: 1.6; max-width: 480px; }
        .status-chip {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 14px; border-radius: 999px; font-size: 0.78rem; font-weight: 700;
            background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.55); color: #d1fae5;
        }
        .status-chip.pending { background: rgba(245, 158, 11, 0.18); border-color: rgba(245, 158, 11, 0.55); color: #fef3c7; }
        .status-chip.rejected { background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.55); color: #fee2e2; }

        .profile-hero-stats { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 14px; flex: 1; min-width: 0; max-width: 460px; }
        .profile-stat { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.22); border-radius: 18px; padding: 14px 12px; backdrop-filter: blur(6px); }
        .profile-stat i { font-size: 1.05rem; color: #cfe6ff; margin-bottom: 6px; display: block; }
        .profile-stat .stat-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.8); margin-bottom: 3px; }
        .profile-stat .stat-value { font-size: 1.15rem; font-weight: 700; line-height: 1.2; }

        .content-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; }
        .info-card {
            background: var(--panel); border-radius: 22px; border: 1px solid var(--border);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05); padding: 24px 26px; margin-bottom: 20px;
        }
        .info-card h3 {
            margin: 0 0 16px; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--muted); display: flex; align-items: center; gap: 8px;
        }
        .info-card h3 i { color: var(--primary); }
        .info-card p { margin: 0 0 8px; color: var(--text); line-height: 1.7; }

        .chip-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 999px; background: var(--primary-soft); color: var(--primary); font-weight: 600; font-size: 0.85rem; }

        .info-row { display: flex; align-items: flex-start; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-row:first-child { padding-top: 0; }
        .info-icon { width: 36px; height: 36px; border-radius: 11px; background: var(--primary-soft); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem; }
        .info-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 2px; }
        .info-value { font-size: 0.94rem; color: var(--text); line-height: 1.5; word-break: break-word; }
        .info-value a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .info-value a:hover { text-decoration: underline; }
        .info-value.empty { color: #94a3b8; font-style: italic; }

        .social-links { display: flex; flex-wrap: wrap; gap: 8px; }
        .social-link { display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border-radius: 999px; background: var(--primary-soft); color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 600; border: 1px solid #dbeafe; }
        .social-link:hover { background: #dbeafe; }
        .social-link.brand-fb { background: #e7f0fe; color: #1877f2; border-color: #cfe2fd; }
        .social-link.brand-fb:hover { background: #d7e6fd; }
        .social-link.brand-tiktok { background: #f1f1f1; color: #111; border-color: #e2e2e2; }
        .social-link.brand-tiktok:hover { background: #e6e6e6; }
        .social-link.brand-x { background: #eceef0; color: #0f1419; border-color: #dfe2e4; }
        .social-link.brand-x:hover { background: #e2e4e6; }
        .social-link.brand-yt { background: #fde8e8; color: #ff0000; border-color: #fbd3d3; }
        .social-link.brand-yt:hover { background: #fcd6d6; }
        .social-link.brand-ig { background: #fdeef7; color: #d6249f; border-color: #fbd9ee; }
        .social-link.brand-ig:hover { background: #fbdff1; }

        .map-embed { width: 100%; height: 220px; border-radius: 18px; overflow: hidden; border: 1px solid var(--border); background: #eef2f7; }
        .map-embed iframe { width: 100%; height: 100%; border: 0; display: block; }
        .map-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; height: 100%; color: var(--muted); font-size: 0.85rem; }
        .map-empty i { font-size: 1.5rem; color: #cbd5e1; }

        .action-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
        .pill-btn { display: inline-flex; align-items: center; gap: 9px; padding: 13px 22px; border-radius: 999px; text-decoration: none; font-weight: 700; font-size: 0.92rem; border: none; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .pill-btn.primary { background: linear-gradient(135deg, #0f4c81, #1d6fc7); color: #fff; box-shadow: 0 10px 24px rgba(15,76,129,0.28); }
        .pill-btn.secondary { background: #fff; color: var(--text); border: 1px solid var(--border); }
        .pill-btn:hover { transform: translateY(-2px); }

        @media (max-width: 820px) { .content-grid { grid-template-columns: 1fr; } }
        @media (max-width: 560px) {
            .profile-hero { padding: 24px; }
            .profile-hero-inner { flex-direction: column; align-items: flex-start; }
            .top-brand span { display: none; }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="top-bar">
            <div class="top-brand">
                <img src="../images/Heydream Logo.png" alt="HeyDream">
                <span>Partner Profile</span>
            </div>
            <a href="<?= htmlspecialchars($backHref) ?>" class="top-back"><i class="fas fa-arrow-left"></i> <?= htmlspecialchars($backLabel) ?></a>
        </div>

        <div class="profile-hero">
            <?php if (!empty($bannerPath)): ?>
                <div class="profile-hero-banner"><img src="../<?= htmlspecialchars($bannerPath) ?>" alt="Cover photo"></div>
            <?php endif; ?>
            <div class="profile-hero-inner">
                <div class="profile-hero-left">
                    <div class="profile-avatar">
                        <?php if (!empty($logoPath)): ?>
                            <img src="../<?= htmlspecialchars($logoPath) ?>" alt="<?= $heading ?> logo">
                        <?php else: ?>
                            <span><?= htmlspecialchars(strtoupper(substr($heading, 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="profile-hero-name"><?= $heading ?></p>
                        <p class="profile-hero-bio"><?= !empty($bio) ? $bio : (!empty($description) ? $description : 'A trusted HeyDream travel partner ready to help plan your next journey.') ?></p>
                        <span class="status-chip <?= htmlspecialchars($statusKey) ?>"><i class="fas <?= htmlspecialchars($statusIcon) ?>"></i> <?= htmlspecialchars($statusLabel) ?> Partner</span>
                    </div>
                </div>
                <div class="profile-hero-stats">
                    <div class="profile-stat">
                        <i class="fas fa-calendar-check"></i>
                        <div class="stat-title">Partner Since</div>
                        <div class="stat-value"><?= htmlspecialchars($businessSince) ?></div>
                    </div>
                    <div class="profile-stat">
                        <i class="fas fa-suitcase-rolling"></i>
                        <div class="stat-title">Packages</div>
                        <div class="stat-value"><?= $packagesListed ?></div>
                    </div>
                    <div class="profile-stat">
                        <i class="fas fa-award"></i>
                        <div class="stat-title">Years Active</div>
                        <div class="stat-value"><?= !empty($yearsInBusiness) ? $yearsInBusiness : '-' ?></div>
                    </div>
                    <div class="profile-stat">
                        <i class="fas fa-users"></i>
                        <div class="stat-title">Team Size</div>
                        <div class="stat-value"><?= !empty($teamSize) ? $teamSize : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <div class="info-card">
                    <h3><i class="fas fa-building"></i> About the Partner</h3>
                    <p><?= !empty($description) ? $description : 'A trusted travel partner helping clients discover the right packages, experiences, and services for their next journey.' ?></p>
                    <?php if (!empty($specialties)): ?>
                        <div class="chip-row">
                            <?php foreach ($specialties as $tag): ?>
                                <span class="chip"><i class="fas fa-star"></i> <?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-clipboard-list"></i> Highlights</h3>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-tag"></i></div>
                        <div><div class="info-label">Business Type</div><div class="info-value"><?= $businessType ?></div></div>
                    </div>
                    <?php if (!empty($certifications)): ?>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-certificate"></i></div>
                        <div><div class="info-label">Certifications</div><div class="info-value"><?= $certifications ?></div></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-clock"></i></div>
                        <div><div class="info-label">Operating Hours</div><div class="info-value <?= empty($profile['operating_hours']) ? 'empty' : '' ?>"><?= !empty($profile['operating_hours']) ? htmlspecialchars($profile['operating_hours']) : 'Not provided' ?></div></div>
                    </div>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-link"></i> Social / Links</h3>
                    <?php
                        $hasAnySocial = array_filter([$socialLinks['facebook'], $socialLinks['tiktok'], $socialLinks['x'], $socialLinks['youtube'], $socialLinks['instagram']]) || !empty($socialLinks['other']);
                    ?>
                    <?php if ($hasAnySocial): ?>
                        <div class="social-links">
                            <?php if (!empty($socialLinks['facebook'])): ?>
                                <a class="social-link brand-fb" href="<?= htmlspecialchars($socialLinks['facebook']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i> Facebook</a>
                            <?php endif; ?>
                            <?php if (!empty($socialLinks['tiktok'])): ?>
                                <a class="social-link brand-tiktok" href="<?= htmlspecialchars($socialLinks['tiktok']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-tiktok"></i> TikTok</a>
                            <?php endif; ?>
                            <?php if (!empty($socialLinks['x'])): ?>
                                <a class="social-link brand-x" href="<?= htmlspecialchars($socialLinks['x']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i> X</a>
                            <?php endif; ?>
                            <?php if (!empty($socialLinks['youtube'])): ?>
                                <a class="social-link brand-yt" href="<?= htmlspecialchars($socialLinks['youtube']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-youtube"></i> YouTube</a>
                            <?php endif; ?>
                            <?php if (!empty($socialLinks['instagram'])): ?>
                                <a class="social-link brand-ig" href="<?= htmlspecialchars($socialLinks['instagram']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> Instagram</a>
                            <?php endif; ?>
                            <?php foreach ($socialLinks['other'] as $link): ?>
                                <?php [$platformName, $platformIcon] = detectSocialPlatform($link); ?>
                                <a class="social-link" href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener"><i class="<?= htmlspecialchars($platformIcon) ?>"></i> Visit <?= htmlspecialchars($platformName) ?> Link</a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="margin:0; color: var(--muted); font-size: 0.9rem;">No social links were added yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="info-card">
                    <h3><i class="fas fa-address-card"></i> Contact Info</h3>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-user"></i></div>
                        <div><div class="info-label">Contact Person</div><div class="info-value"><?= $contactPerson ?></div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div><div class="info-label">Email</div><div class="info-value"><a href="<?= $mailtoLink ?>"><?= $email ?></a></div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-phone"></i></div>
                        <div><div class="info-label">Phone</div><div class="info-value <?= ($phone === 'N/A') ? 'empty' : '' ?>"><?= $phone ?></div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-location-dot"></i></div>
                        <div><div class="info-label">Location</div><div class="info-value <?= empty($locationParts) ? 'empty' : '' ?>"><?= !empty($locationParts) ? htmlspecialchars($locationText) : 'Not provided' ?></div></div>
                    </div>
                    <?php if (!empty($website) && $website !== 'N/A'): ?>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-globe"></i></div>
                        <div><div class="info-label">Website</div><div class="info-value"><a href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($website) ?></a></div></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-map-location-dot"></i> Find Us</h3>
                    <div class="map-embed">
                        <?php if ($mapSrc): ?>
                            <iframe src="<?= htmlspecialchars($mapSrc) ?>" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <?php else: ?>
                            <div class="map-empty"><i class="fas fa-map-location-dot"></i> No address on file yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-row">
            <a href="<?= $mailtoLink ?>" class="pill-btn primary"><i class="fas fa-envelope"></i> Send Email</a>
            <a href="<?= htmlspecialchars($backHref) ?>" class="pill-btn secondary"><i class="fas fa-arrow-left"></i> <?= htmlspecialchars($backLabel) ?></a>
        </div>
    </div>
</body>
</html>

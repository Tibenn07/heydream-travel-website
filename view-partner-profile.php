<?php
require_once __DIR__ . '/config/database.php';

$partnerId = (int)($_GET['id'] ?? 0);

if ($partnerId <= 0) {
    die('<h1>Invalid Partner</h1><p>No partner ID provided.</p>');
}

$stmt = $pdo->prepare("SELECT * FROM partner_applications WHERE id = ? AND status = 'approved' LIMIT 1");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();

if (!$partner) {
    die('<h1>Partner Not Found</h1><p>The partner you are looking for does not exist or is not yet approved.</p>');
}

$profileStmt = $pdo->prepare("SELECT * FROM partner_profiles WHERE partner_id = ? LIMIT 1");
$profileStmt->execute([$partnerId]);
$profile = $profileStmt->fetch();

$displayName = htmlspecialchars($profile['business_display_name'] ?? $partner['company_name'] ?? 'Nevets Neotech');
$coverImageSrc = !empty($profile['banner_image_path']) ? $profile['banner_image_path'] : 'https://images.unsplash.com/photo-1518684079-98673c6fbb1e?auto=format&fit=crop&w=1400&q=80';
$avatarImageSrc = !empty($profile['logo_path']) ? $profile['logo_path'] : 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=400&q=80';
$locationText = trim(implode(', ', array_filter([$profile['address'] ?? '', $profile['city'] ?? '', $profile['country'] ?? ''])));
$locationText = $locationText ?: 'Bohol, Philippines';
$mapQuery = rawurlencode($locationText);
$mapSrc = "https://www.google.com/maps?q={$mapQuery}&output=embed";
$websiteUrl = trim($profile['website'] ?? '');
$websiteLabel = $websiteUrl ? preg_replace('#^https?://#', '', $websiteUrl) : 'nevetstravel.com';
$specialties = array_filter(array_map('trim', explode(',', $profile['specialties'] ?? '')));
if (empty($specialties)) {
    $specialties = ['Travel Tours', 'Adventure Tours', 'Cultural Immersive', 'Luxury Retreats'];
}
$tagline = !empty($profile['bio']) ? htmlspecialchars($profile['bio']) : 'A boutique travel agency crafting unforgettable Philippine journeys with bespoke care and local expertise.';
$joinedDate = !empty($partner['created_at']) ? date('F Y', strtotime($partner['created_at'])) : 'March 2024';
$serviceItems = [
    ['icon' => 'fa-calendar-day', 'label' => 'Curated itinerary planning'],
    ['icon' => 'fa-plane-departure', 'label' => 'Luxury transport & flights'],
    ['icon' => 'fa-hiking', 'label' => 'Adventure & guided experiences'],
];
$badgeValues = [
    'Verified Partner',
    !empty($profile['years_in_business']) ? $profile['years_in_business'] . ' Years Experience' : '2 Years Experience',
    !empty($profile['team_size']) ? 'Team Size ' . $profile['team_size'] : 'Team Size 1',
];
$websiteMode = !empty($websiteUrl) ? 'website' : 'web';
$hasSocialLinks = !empty(trim($profile['social_media_links'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['business_display_name'] ?? $partner['company_name']) ?> - HeyDream Partners</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8efe6;
            --card-bg: rgba(255, 255, 255, 0.85);
            --panel-bg: #fff8f1;
            --text: #1c1b20;
            --muted: #6d6a73;
            --dusty: #8b5d57;
            --accent: #1f5f61;
            --accent-soft: #dbeceb;
            --gold: #b78b5a;
            --shadow: 0 24px 70px rgba(17, 14, 18, 0.12);
            --border: rgba(143, 111, 96, 0.16);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, #f9efe0 0%, #fff7ee 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .page-wrap {
            max-width: 1240px;
            margin: 0 auto;
            padding: 28px 26px 40px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            color: var(--dusty);
            text-decoration: none;
            font-weight: 600;
            border-radius: 999px;
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(31, 95, 97, 0.08);
            margin-bottom: 24px;
        }

        .profile-card {
            border-radius: 30px;
            overflow: hidden;
            box-shadow: var(--shadow);
            background: rgba(255,255,255,0.88);
            border: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .cover-photo {
            position: relative;
            width: 100%;
            min-height: 300px;
            background: center/cover no-repeat url('<?= htmlspecialchars($coverImageSrc) ?>');
        }

        .cover-photo::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(40, 26, 27, 0.08), rgba(40, 26, 27, 0.48));
        }

        .cover-badge {
            position: absolute;
            left: 32px;
            top: 24px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.85);
            color: #6a3b48;
            font-size: 0.95rem;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.72);
            box-shadow: 0 16px 30px rgba(31, 95, 97, 0.12);
        }

        .cover-badge i {
            color: var(--gold);
        }

        .profile-top {
            position: relative;
            padding: 0 32px 32px;
            display: grid;
            grid-template-columns: 220px 1fr;
            align-items: end;
            gap: 24px;
            margin-top: -90px;
        }

        .avatar-wrap {
            width: 190px;
            height: 190px;
            border-radius: 50%;
            background: linear-gradient(145deg, rgba(255,255,255,0.95), rgba(255,255,255,0.8));
            padding: 8px;
            box-shadow: 0 24px 50px rgba(31, 95, 97, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.8);
            overflow: hidden;
        }

        .avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .profile-details {
            padding: 28px 32px 32px;
            border-radius: 30px;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 18px 60px rgba(31, 95, 97, 0.08);
            border: 1px solid rgba(143, 111, 96, 0.12);
            backdrop-filter: blur(8px);
        }

        .profile-details h1 {
            margin: 0 0 10px;
            font-size: clamp(2.4rem, 3vw, 3.4rem);
            letter-spacing: -0.04em;
            font-family: 'Cormorant Garamond', serif;
            color: #4d1f31;
        }

        .profile-details .subtitle {
            margin: 0 0 20px;
            color: var(--dusty);
            font-size: 1rem;
            line-height: 1.8;
            max-width: 720px;
        }

        .profile-details .tagline {
            margin-top: 8px;
            color: #5a3d45;
            font-size: 0.96rem;
            max-width: 760px;
            line-height: 1.8;
        }

        .pill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-radius: 999px;
            padding: 12px 18px;
            font-size: 0.95rem;
            background: rgba(190, 136, 110, 0.12);
            color: #5a3d45;
            border: 1px solid rgba(190, 136, 110, 0.25);
            font-weight: 600;
        }

        .pill i {
            color: var(--gold);
        }

        .social-links {
            display: flex;
            gap: 14px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .social-links a {
            width: 52px;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.74);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.85), 0 16px 30px rgba(31, 95, 97, 0.08);
            color: var(--dusty);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .social-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(31, 95, 97, 0.15);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            padding: 34px 32px 42px;
            background: var(--panel-bg);
        }

        .left-panel,
        .right-panel {
            display: grid;
            gap: 24px;
        }

        .panel {
            border-radius: 28px;
            padding: 26px 28px;
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(143, 111, 96, 0.12);
            box-shadow: 0 18px 54px rgba(31, 95, 97, 0.08);
        }

        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 22px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.72);
            background: rgba(255,255,255,0.82);
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 16px 40px rgba(31, 95, 97, 0.08);
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            background: rgba(255,255,255,0.95);
        }

        .panel h2 {
            margin: 0 0 18px;
            font-size: 1.14rem;
            color: #3f1c29;
            letter-spacing: -0.01em;
        }

        .panel p,
        .panel li {
            margin: 0;
            color: var(--dusty);
            line-height: 1.9;
            font-size: 0.98rem;
        }

        .panel ul {
            padding-left: 18px;
            margin: 0;
            list-style: none;
        }

        .panel ul li {
            position: relative;
            padding-left: 28px;
            margin-bottom: 16px;
        }

        .panel ul li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent);
        }

        .service-item {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .service-item i {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(31, 95, 97, 0.12);
            color: var(--accent);
            font-size: 1rem;
        }

        .skills-shelf {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .skill-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px;
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(29, 73, 76, 0.08), rgba(30, 89, 92, 0.02));
            border: 1px solid rgba(31, 95, 97, 0.08);
        }

        .skill-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #0f4446;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            box-shadow: 0 12px 24px rgba(15, 68, 70, 0.14);
        }

        .skill-title {
            font-weight: 700;
            color: #213a3a;
            font-size: 0.96rem;
        }

        .profile-meta {
            display: grid;
            gap: 18px;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(143,111,96,0.12);
        }

        .meta-label {
            color: var(--dusty);
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .meta-value {
            color: var(--text);
            font-weight: 700;
            text-align: right;
        }

        .map-embed {
            width: 100%;
            height: 220px;
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid rgba(143,111,96,0.14);
            background: #f7f3ef;
            margin-top: 18px;
        }

        .map-embed iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        .compact-stats {
            display: grid;
            gap: 16px;
        }

        .compact-card {
            display: grid;
            gap: 10px;
            padding: 22px 20px;
            border-radius: 22px;
            background: #ffffff;
            border: 1px solid rgba(143,111,96,0.1);
            box-shadow: 0 14px 40px rgba(31, 95, 97, 0.08);
        }

        .compact-card .value {
            font-size: 1.65rem;
            font-weight: 800;
            color: #3a1f26;
        }

        .compact-card .label {
            color: var(--dusty);
            font-size: 0.9rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        @media (max-width: 1024px) {
            .profile-top {
                grid-template-columns: 1fr;
                margin-top: -70px;
            }

            .profile-details {
                margin-top: 0;
            }
        }

        @media (max-width: 720px) {
            .page-wrap {
                padding: 20px 16px 32px;
            }

            .profile-top {
                padding: 0 20px 20px;
            }

            .content-grid {
                padding: 28px 20px 32px;
            }

            .content-grid,
            .profile-top,
            .panel {
                border-radius: 24px;
            }

            .avatar-wrap {
                width: 150px;
                height: 150px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>

        <div class="profile-card">
            <div class="cover-photo">
                <div class="cover-badge"><i class="fas fa-mountain"></i> Chocolate Hills Collection</div>
            </div>

            <div class="profile-top">
                <div class="avatar-wrap">
                    <img src="<?= htmlspecialchars($avatarImageSrc) ?>" alt="<?= $displayName ?> logo">
                </div>

                <div class="profile-details">
                    <h1><?= $displayName ?></h1>
                    <div class="subtitle">A boutique travel agency specializing in luxury Philippine experiences and curated guest journeys.</div>
                    <p class="tagline"><?= $tagline ?></p>

                    <div class="pill-list">
                        <span class="pill"><i class="fas fa-award"></i> Verified Partner</span>
                        <?php if (!empty($profile['years_in_business'])): ?>
                            <span class="pill"><i class="fas fa-briefcase"></i> <?= htmlspecialchars($profile['years_in_business']) ?> years experience</span>
                        <?php endif; ?>
                        <?php if (!empty($profile['team_size'])): ?>
                            <span class="pill"><i class="fas fa-users"></i> <?= htmlspecialchars($profile['team_size']) ?> members</span>
                        <?php endif; ?>
                    </div>

                    <div class="social-links">
                        <?php if (!empty($profile['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($profile['phone']) ?>" title="Call partner"><i class="fas fa-phone"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($partner['email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($partner['email']) ?>" title="Email partner"><i class="fas fa-envelope"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($websiteUrl)): ?>
                            <a href="<?= htmlspecialchars($websiteUrl) ?>" target="_blank" title="Visit website"><i class="fas fa-globe"></i></a>
                        <?php endif; ?>
                        <?php if ($hasSocialLinks): ?>
                            <a href="#" title="Social profile"><i class="fas fa-user-friends"></i></a>
                        <?php endif; ?>
                    </div>

                    <div class="action-bar">
                        <a href="mailto:<?= htmlspecialchars($partner['email'] ?? 'info@nevetstravel.com') ?>" class="action-btn"><i class="fas fa-envelope"></i> Contact Partner</a>
                        <?php if (!empty($websiteUrl)): ?>
                            <a href="<?= htmlspecialchars($websiteUrl) ?>" target="_blank" class="action-btn"><i class="fas fa-arrow-right"></i> Explore Website</a>
                        <?php else: ?>
                            <a href="index.php" class="action-btn"><i class="fas fa-plane-departure"></i> Browse Trips</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="left-panel">
                    <div class="panel description-block">
                        <div class="section-title"><i class="fas fa-user-circle"></i> Profile Summary</div>
                        <p><?= !empty($profile['bio']) ? htmlspecialchars($profile['bio']) : 'No bio available yet. This partner can add a short profile summary to highlight their travel services and specialties.' ?></p>
                    </div>

                    <div class="panel description-block">
                        <div class="section-title"><i class="fas fa-info-circle"></i> Services & Offerings</div>
                        <p><?= !empty($profile['description']) ? htmlspecialchars($profile['description']) : 'Service details are not provided. This section is reserved for the partner to share the packages, experiences, or travel services they offer through HeyDream.' ?></p>
                    </div>

                    <?php if (!empty($specialties)): ?>
                        <div class="panel skills-block">
                            <div class="section-title"><i class="fas fa-lightbulb"></i> Signature Specialties</div>
                            <div class="skills-shelf">
                                <?php foreach ($specialties as $specialty): ?>
                                    <div class="skill-card">
                                        <span class="skill-icon"><i class="fas fa-compass"></i></span>
                                        <span class="skill-title"><?= htmlspecialchars($specialty) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="right-panel">
                    <div class="panel profile-meta">
                        <div class="section-title"><i class="fas fa-building"></i> Partner Details</div>
                        <div class="meta-row">
                            <div class="meta-label">Contact Person</div>
                            <div class="meta-value"><?= htmlspecialchars($partner['contact_person'] ?? 'Not set') ?></div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Phone</div>
                            <div class="meta-value"><?= htmlspecialchars($profile['phone'] ?? 'Not set') ?></div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Email</div>
                            <div class="meta-value"><?= htmlspecialchars($partner['email'] ?? 'Not set') ?></div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Website</div>
                            <div class="meta-value"><?= $websiteLabel ? htmlspecialchars($websiteLabel) : 'Not set' ?></div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Location</div>
                            <div class="meta-value"><?= htmlspecialchars($locationText) ?></div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Joined</div>
                            <div class="meta-value"><?= htmlspecialchars($joinedDate) ?></div>
                        </div>
                    </div>
                    <div class="map-embed">
                        <iframe src="<?= htmlspecialchars($mapSrc) ?>" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>

                    <div class="panel stats-grid">
                        <div class="stat-card">
                            <div class="value"><?= htmlspecialchars($profile['years_in_business'] ?: '—') ?></div>
                            <div class="label">Years in business</div>
                        </div>
                        <div class="stat-card">
                            <div class="value"><?= htmlspecialchars($profile['team_size'] ?: '—') ?></div>
                            <div class="label">Team size</div>
                        </div>
                        <div class="stat-card">
                            <div class="value"><?= count($specialties) ?: '—' ?></div>
                            <div class="label">Specialties</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

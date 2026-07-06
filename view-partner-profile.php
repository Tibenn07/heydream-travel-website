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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['business_display_name'] ?? $partner['company_name']) ?> - HeyDream Partners</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f4c81;
            --primary-soft: #e8f2ff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --success: #10b981;
            --bg: #f4f7fb;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #eef6ff 0%, #f8fafc 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 24px;
            transition: 0.2s;
        }

        .back-btn:hover {
            background: #0d3a60;
        }

        .profile-header {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            margin-bottom: 24px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .company-info h1 {
            margin: 0 0 8px 0;
            font-size: 2rem;
            color: var(--primary);
        }

        .company-info .location {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: var(--success);
            color: white;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .contact-info {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .contact-item i {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .contact-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .contact-item a:hover {
            text-decoration: underline;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .profile-panel {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        .profile-panel h2 {
            margin: 0 0 16px 0;
            font-size: 1.2rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-panel h2 i {
            font-size: 1.3rem;
        }

        .bio-text {
            line-height: 1.8;
            color: var(--text);
            margin-bottom: 16px;
        }

        .specialties {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }

        .specialty-tag {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
        }

        .stat-card {
            background: var(--primary-soft);
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .stat-card .value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-card .label {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .description-section {
            background: var(--primary-soft);
            padding: 16px;
            border-radius: 12px;
            margin-top: 16px;
            border-left: 4px solid var(--primary);
        }

        .description-section h3 {
            margin: 0 0 12px 0;
            color: var(--primary);
        }

        .description-section p {
            margin: 0;
            line-height: 1.7;
            color: var(--text);
        }

        .certifications {
            background: var(--bg);
            padding: 16px;
            border-radius: 12px;
            margin-top: 12px;
            border: 1px solid var(--border);
        }

        .certifications h3 {
            margin: 0 0 12px 0;
            color: var(--primary);
            font-size: 0.95rem;
        }

        .certifications p {
            margin: 0;
            line-height: 1.6;
            color: var(--text);
            font-size: 0.9rem;
            white-space: pre-wrap;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .header-top {
                flex-direction: column;
            }

            .company-info h1 {
                font-size: 1.5rem;
            }

            .contact-info {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="partners.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Partners</a>

        <div class="profile-header">
            <div class="header-top">
                <div class="company-info">
                    <h1><?= htmlspecialchars($profile['business_display_name'] ?? $partner['company_name']) ?></h1>
                    <div class="location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($profile['address'] ?? 'Address not provided') ?>, 
                        <?= htmlspecialchars($profile['city'] ?? 'City') ?>, 
                        <?= htmlspecialchars($profile['country'] ?? 'Country') ?>
                    </div>
                    <span class="status-badge"><i class="fas fa-check-circle"></i> Verified Partner</span>
                </div>
                <div style="text-align: right;">
                    <?php if (!empty($profile['years_in_business']) || !empty($profile['team_size'])): ?>
                        <div class="stats-grid" style="max-width: 200px;">
                            <?php if (!empty($profile['years_in_business'])): ?>
                                <div class="stat-card">
                                    <div class="value"><?= htmlspecialchars($profile['years_in_business']) ?></div>
                                    <div class="label">Years</div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($profile['team_size'])): ?>
                                <div class="stat-card">
                                    <div class="value"><?= htmlspecialchars($profile['team_size']) ?></div>
                                    <div class="label">Team</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="contact-info">
                <?php if (!empty($profile['phone'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($profile['phone']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($partner['email'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:<?= htmlspecialchars($partner['email']) ?>"><?= htmlspecialchars($partner['email']) ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($profile['website'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-globe"></i>
                        <a href="<?= htmlspecialchars($profile['website']) ?>" target="_blank"><?= htmlspecialchars($profile['website']) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-grid">
            <div>
                <?php if (!empty($profile['bio'])): ?>
                    <div class="profile-panel">
                        <h2><i class="fas fa-quote-left"></i> About</h2>
                        <p class="bio-text"><?= htmlspecialchars($profile['bio']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profile['specialties'])): ?>
                    <div class="profile-panel">
                        <h2><i class="fas fa-star"></i> Specialties</h2>
                        <div class="specialties">
                            <?php foreach (array_map('trim', explode(',', $profile['specialties'])) as $specialty): ?>
                                <span class="specialty-tag"><?= htmlspecialchars($specialty) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profile['description'])): ?>
                    <div class="profile-panel">
                        <h2><i class="fas fa-info-circle"></i> Services</h2>
                        <div class="description-section">
                            <p><?= nl2br(htmlspecialchars($profile['description'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profile['certifications'])): ?>
                    <div class="profile-panel">
                        <h2><i class="fas fa-certificate"></i> Certifications</h2>
                        <div class="certifications">
                            <p><?= nl2br(htmlspecialchars($profile['certifications'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <div class="profile-panel">
                    <h2><i class="fas fa-building"></i> Company Details</h2>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div>
                            <strong style="font-size: 0.85rem; color: var(--muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Contact Person</strong>
                            <span><?= htmlspecialchars($partner['contact_person'] ?? 'N/A') ?></span>
                        </div>
                        <div>
                            <strong style="font-size: 0.85rem; color: var(--muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Business Type</strong>
                            <span><?= htmlspecialchars($partner['business_type'] ?? 'N/A') ?></span>
                        </div>
                        <?php if (!empty($profile['years_in_business'])): ?>
                            <div>
                                <strong style="font-size: 0.85rem; color: var(--muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Experience</strong>
                                <span><?= htmlspecialchars($profile['years_in_business']) ?> years in business</span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($profile['team_size'])): ?>
                            <div>
                                <strong style="font-size: 0.85rem; color: var(--muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Team Size</strong>
                                <span><?= htmlspecialchars($profile['team_size']) ?> team members</span>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                            <strong style="font-size: 0.85rem; color: var(--muted); text-transform: uppercase; display: block; margin-bottom: 8px;">Member Since</strong>
                            <span><?= date('M d, Y', strtotime($partner['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

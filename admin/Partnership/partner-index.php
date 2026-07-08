<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['partner_id']) || empty($_SESSION['partner_id'])) {
    header('Location: partner-login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

function normalizePartnerPackageReferenceValue($value)
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
}

$partnerId = (int)($_SESSION['partner_id'] ?? 0);
$partnerUploadsStmt = $pdo->prepare("SELECT * FROM partner_package_uploads WHERE partner_id = ? AND upload_status = 'approved' ORDER BY created_at DESC");
$partnerUploadsStmt->execute([$partnerId]);
$partnerUploads = $partnerUploadsStmt->fetchAll(PDO::FETCH_ASSOC);

$existingPartnerPackageNames = [];
$existingPartnerPackageQueries = [
    "SELECT name FROM foreign_destinations WHERE is_active = 1 AND name IS NOT NULL AND name <> ''",
    "SELECT name FROM destinations WHERE type = 'local' AND is_active = 1 AND name IS NOT NULL AND name <> ''",
    "SELECT destination_name FROM hotel_booking_settings WHERE is_active = 1 AND destination_name IS NOT NULL AND destination_name <> ''",
    "SELECT title FROM site_services WHERE is_active = 1 AND title IS NOT NULL AND title <> '' AND service_type IN ('premium', 'flight', 'experience')",
    "SELECT title FROM visas WHERE is_active = 1 AND title IS NOT NULL AND title <> ''"
];

foreach ($existingPartnerPackageQueries as $query) {
    $queryStmt = $pdo->query($query);
    while ($row = $queryStmt->fetchColumn()) {
        $normalized = normalizePartnerPackageReferenceValue($row);
        if ($normalized !== '') {
            $existingPartnerPackageNames[$normalized] = true;
        }
    }
}

$partnerUploads = array_values(array_filter($partnerUploads, function ($upload) use ($existingPartnerPackageNames) {
    $packageName = normalizePartnerPackageReferenceValue($upload['package_name'] ?? '');
    $destinationName = normalizePartnerPackageReferenceValue($upload['destination_name'] ?? '');
    return !isset($existingPartnerPackageNames[$packageName]) && !isset($existingPartnerPackageNames[$destinationName]);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partners Packages and Services | HeyDream</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #f7f9fc;
            color: #0f172a;
            margin: 0;
        }

        .page-header {
            background: linear-gradient(135deg, #0f4c81 0%, #67b0f1 100%);
            color: white;
            padding: 32px 24px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2rem;
            margin: 0 0 12px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            max-width: 760px;
            margin-left: auto;
            margin-right: auto;
            font-size: 1rem;
        }

        .page-nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .back-link {
            color: #0f4c81;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .page-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px 48px;
        }

        .section-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin-bottom: 24px;
        }

        .section-title-popular {
            margin: 0;
            font-size: 1.8rem;
            letter-spacing: -0.02em;
        }

        .view-all-link {
            color: #0f4c81;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .popular-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            align-items: center;
            justify-items: center;
        }

        .foreign-card {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 320px;
        }

        .foreign-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 24px 52px rgba(15, 23, 42, 0.12);
        }

        .foreign-card-image {
            background: linear-gradient(135deg, #0f4c81, #4f8ee9);
            color: white;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            font-size: 2rem;
        }

        .foreign-card-content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .foreign-card-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            line-height: 1.3;
        }

        .foreign-card-location,
        .foreign-card-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            color: #475569;
        }

        .foreign-card-desc {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.6;
            min-height: 68px;
        }

        .foreign-card-footer {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            font-weight: 700;
            color: #0f4c81;
        }

        .partner-profile-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
            font-weight: 700;
            color: #0f4c81;
            text-decoration: none;
        }

        .partner-profile-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            padding: 0;
            text-align: center;
            background: transparent;
            border: none;
            color: #0f4c81;
            box-shadow: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: 260px;
        }

        .empty-state p {
            margin: 0;
            font-size: 1rem;
            color: #334155;
            line-height: 1.8;
        }

        .empty-state-icon {
            font-size: 2rem;
            color: #0f4c81;
            margin-bottom: 14px;
        }

        @media (max-width: 640px) {
            .page-header {
                padding: 28px 18px;
            }

            .page-section {
                padding: 0 18px 36px;
            }

            .section-title-popular {
                font-size: 1.5rem;
            }

            .foreign-card {
                min-height: 320px;
            }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="page-nav">
            <a href="../../index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
            <span>Partners Packages and Services</span>
        </div>
        <h1>Explore Partner Packages</h1>
        <p>Browse all approved partner packages from different providers. Each package below is ready for booking and curated by our partner network.</p>
    </header>

    <main class="page-section">
        <div class="section-header-wrapper">
            <h2 class="section-title-popular">All Partner Packages</h2>
            <a href="../../index.php#partners-packages" class="view-all-link">Return to homepage section <i class="fas fa-arrow-right"></i></a>
        </div>

        <div class="popular-grid">
            <?php if (empty($partnerUploads)): ?>
                <div class="empty-state">
                    <i class="fas fa-info-circle empty-state-icon"></i>
                    <p>No partner packages are available right now. Please check back later.</p>
                </div>
            <?php else: ?>
                <?php foreach ($partnerUploads as $upload): ?>
                    <div class="foreign-card">
                        <div class="foreign-card-image" style="<?= !empty($upload['image_path']) ? 'padding: 0; background: #fff;' : '' ?>">
                            <?php if (!empty($upload['image_path'])): ?>
                                <img src="../../<?= htmlspecialchars($upload['image_path']) ?>" alt="<?= htmlspecialchars($upload['package_name'] ?: 'Partner package image') ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                            <?php else: ?>
                                <div>
                                    <i class="fas fa-handshake"></i>
                                    <div style="font-size: 0.95rem; margin-top: 8px;"><?= htmlspecialchars($upload['partner_company'] ?: 'Partner Service') ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="foreign-card-content">
                            <h3 class="foreign-card-name"><?= htmlspecialchars($upload['package_name'] ?: 'Untitled Package') ?></h3>
                            <div class="foreign-card-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($upload['destination_name'] ?: 'Destination not specified') ?></div>
                            <p class="foreign-card-desc"><?= htmlspecialchars($upload['description'] ?: 'Partner package available for booking.') ?></p>
                            <div class="foreign-card-meta"><i class="fas fa-user"></i> Uploaded by <?= htmlspecialchars($upload['uploaded_by_name'] ?: $upload['partner_company'] ?: 'Partner') ?></div>
                            <a class="partner-profile-link" href="partner-profile.php?id=<?= (int)$upload['partner_id'] ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fas fa-arrow-up-right-from-square"></i> View partner profile
                            </a>
                            <div class="foreign-card-footer">
                                <span><?= !empty($upload['duration']) ? htmlspecialchars($upload['duration']) : 'Flexible duration' ?></span>
                                <span>?<?= number_format((float)$upload['price'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

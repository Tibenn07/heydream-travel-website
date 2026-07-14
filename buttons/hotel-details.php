<?php
// ========================================
// FILE: buttons/hotel-details.php
// DESCRIPTION: Full-page detail view for a hotel/premium service (mirrors package-details.php)
// ========================================
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$identifier = isset($_GET['id']) ? $_GET['id'] : '';
$isNumeric = is_numeric($identifier);

$svc = null;

try {
    $sql = "SELECT ss.*, COALESCE(pr.business_display_name, p.company_name, ss.partner_company) AS partner_company
            FROM site_services ss
            LEFT JOIN partner_applications p ON ss.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = ss.partner_id
            WHERE ss.service_type = 'premium' AND ";
    if ($isNumeric) {
        $stmt = $pdo->prepare($sql . "ss.id = :id");
        $stmt->execute(['id' => intval($identifier)]);
    } else {
        $stmt = $pdo->prepare($sql . "(ss.title = :name OR REPLACE(LOWER(ss.title), ' ', '_') = :name OR ss.title LIKE :name_like)");
        $stmt->execute(['name' => $identifier, 'name_like' => '%' . $identifier . '%']);
    }
    $svc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($svc) {
        $itStmt = $pdo->prepare("SELECT * FROM service_itinerary WHERE service_id = ? ORDER BY day_number");
        $itStmt->execute([$svc['id']]);
        $svc['itinerary_rows'] = $itStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $svc = null;
}

// Splits a free-text block (one item per line) into a clean array, since
// site_services stores highlights/inclusions/exclusions as newline-separated
// text rather than JSON (unlike the destinations/packages tables).
function svcLines($text)
{
    if (!$text) return [];
    return array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== ''));
}

function svcImg($path)
{
    if (!$path) return '';
    if (preg_match('#^(https?:)?//#', $path) || strpos($path, 'data:') === 0) return $path;
    return '../' . $path;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $svc ? htmlspecialchars($svc['title']) . ' - HeyDream Travel' : 'Hotel Not Found - HeyDream Travel' ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { background: #f4f6f9; font-family: 'Poppins', sans-serif; }

        .pkgdet-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }

        .pkgdet-top-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }

        .pkgdet-breadcrumb { font-size: 0.85rem; color: #64748b; }
        .pkgdet-breadcrumb a { color: #003580; text-decoration: none; font-weight: 600; }
        .pkgdet-breadcrumb a:hover { text-decoration: underline; }

        .pkgdet-back-btn { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: #003580; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; text-decoration: none; box-shadow: 0 1px 3px rgba(15,23,42,0.06); cursor: pointer; transition: background 0.2s, box-shadow 0.2s; flex-shrink: 0; }
        .pkgdet-back-btn:hover { background: #f1f5f9; box-shadow: 0 2px 6px rgba(15,23,42,0.1); }

        .pkgdet-title-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
        .pkgdet-title-row h1 { font-size: 1.9rem; font-weight: 800; color: #0f172a; margin: 0 0 6px; }
        .pkgdet-location { color: #475569; font-size: 0.95rem; }
        .pkgdet-location i { color: #ff9800; margin-right: 4px; }

        .pkgdet-gallery { display: grid; gap: 8px; border-radius: 16px; overflow: hidden; margin-bottom: 28px; height: 440px; grid-template-columns: 1fr; }
        .pkgdet-gtile { position: relative; cursor: pointer; overflow: hidden; background: #e2e8f0; }
        .pkgdet-gtile img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.35s ease; }
        .pkgdet-gtile:hover img { transform: scale(1.05); }

        @media (max-width: 768px) { .pkgdet-gallery { height: 280px; } }

        .pkgdet-body { display: grid; grid-template-columns: 1fr 340px; gap: 28px; align-items: start; }
        @media (max-width: 900px) {
            .pkgdet-body { grid-template-columns: 1fr; }
            .pkgdet-sticky { order: -1; margin-bottom: 20px; position: static !important; }
        }

        .pkgdet-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .pkgdet-card h2 { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }
        .pkgdet-card h2 i { color: #ff9800; }
        .pkgdet-card h4 { font-size: 0.92rem; color: #0f172a; margin: 18px 0 8px; }
        .pkgdet-card h4:first-child { margin-top: 0; }

        .pkgdet-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .pkgdet-chip { background: #f0f7ff; border: 1px solid #cce5ff; color: #003580; padding: 8px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .pkgdet-chip i { color: #ff9800; }

        .pkgdet-desc { color: #334155; line-height: 1.7; font-size: 0.95rem; white-space: pre-line; }

        .pkgdet-day { border-left: 3px solid #ff9800; padding: 4px 0 16px 16px; margin-bottom: 8px; }
        .pkgdet-day:last-child { margin-bottom: 0; }
        .pkgdet-day h4 { margin: 0 0 6px; color: #003580; font-weight: 700; font-size: 0.95rem; }

        .pkgdet-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 600px) { .pkgdet-two-col { grid-template-columns: 1fr; } }
        .pkgdet-line-row { display: flex; align-items: flex-start; gap: 10px; font-size: 0.88rem; line-height: 1.5; margin-bottom: 10px; color: #334155; }
        .pkgdet-line-row:last-child { margin-bottom: 0; }
        .pkgdet-line-row .dot { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.68rem; flex-shrink: 0; margin-top: 1px; }
        .pkgdet-line-row.inc .dot { background: #dcfce7; color: #15803d; }
        .pkgdet-line-row.exc .dot { background: #fee2e2; color: #b91c1c; }

        .pkgdet-social-badge { display: inline-flex; align-items: center; gap: 6px; background: #fff7ed; color: #c2410c; padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; }

        .pkgdet-partner-box { display: flex; align-items: center; gap: 14px; }
        .pkgdet-partner-box .icon { width: 48px; height: 48px; border-radius: 50%; background: #fff7ed; color: #ff9800; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .pkgdet-partner-box a { color: #003580; font-weight: 700; text-decoration: none; }
        .pkgdet-partner-box a:hover { text-decoration: underline; }

        .pkgdet-sticky { position: sticky; top: 20px; }
        .pkgdet-price-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; }
        .pkgdet-price-now { font-size: 1.9rem; font-weight: 900; color: #ff9800; }
        .pkgdet-price-per { color: #64748b; font-size: 0.85rem; }
        .pkgdet-book-btn { width: 100%; margin-top: 16px; background: linear-gradient(135deg, #ff9800, #f57c00); color: #fff; border: none; padding: 14px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; box-shadow: 0 6px 14px rgba(255,152,0,0.3); }
        .pkgdet-book-btn:hover { filter: brightness(1.05); }
        .pkgdet-price-meta { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; font-size: 0.85rem; color: #475569; }
        .pkgdet-price-meta div { display: flex; justify-content: space-between; }

        .pkgdet-notfound { text-align: center; min-height: 56vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; }
        .pkgdet-notfound .pkgdet-notfound-icon { width: 88px; height: 88px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .pkgdet-notfound .pkgdet-notfound-icon i { font-size: 2.2rem; color: #003580; }
        .pkgdet-notfound h2 { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .pkgdet-notfound p { color: #64748b; margin-bottom: 28px; }
        .pkgdet-notfound-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
        .pkgdet-notfound-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border-radius: 24px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: transform 0.15s, box-shadow 0.15s; }
        .pkgdet-notfound-btn.primary { background: #003580; color: #fff; box-shadow: 0 4px 12px rgba(0,53,128,0.25); }
        .pkgdet-notfound-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,53,128,0.32); }

        /* ---- Booking modal (ported from hotel.php so "Book Now" opens in place) ---- */
        .booking-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 5000; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
        .booking-modal.active { display: flex; }
        .booking-modal-content { background: #f8fafc; border-radius: 30px; max-width: 650px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3); animation: modalPopUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes modalPopUp { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .booking-modal-header { background: linear-gradient(135deg, #FFC107 0%, #FF9800 100%); color: white; padding: 30px; border-radius: 30px 30px 0 0; position: relative; }
        .booking-modal-header h2 { margin: 0; font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .booking-modal-header p { margin: 5px 0 0; font-size: 0.9rem; opacity: 0.9; }
        .close-booking { position: absolute; top: 25px; right: 25px; font-size: 24px; cursor: pointer; width: 35px; height: 35px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .close-booking:hover { background: rgba(255, 255, 255, 0.2); }
        .booking-steps-nav { display: flex; justify-content: center; padding: 30px 20px; background: white; border-bottom: 1px solid #e2e8f0; }
        .step-item { flex: 1; text-align: center; position: relative; }
        .step-circle { width: 35px; height: 35px; background: #e2e8f0; color: #64748b; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: 800; font-size: 0.9rem; z-index: 2; position: relative; transition: 0.3s; }
        .step-item.active .step-circle { background: #ff9800; color: white; box-shadow: 0 0 0 5px rgba(255, 152, 0, 0.2); }
        .step-item.completed .step-circle { background: #22c55e; color: white; }
        .step-label { font-size: 0.75rem; font-weight: 700; color: #64748b; }
        .step-item.active .step-label { color: #ff9800; }
        .step-connector { position: absolute; top: 17px; left: 50%; width: 100%; height: 2px; background: #e2e8f0; z-index: 1; }
        .step-item.completed .step-connector { background: #22c55e; }
        .booking-body { padding: 30px; }
        .service-mini-card { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .mini-card-icon { width: 60px; height: 60px; background: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ff5252; font-size: 1.5rem; }
        .mini-card-info h4 { margin: 0; color: #ff9800; font-size: 1.1rem; }
        .mini-card-info .mini-price { font-size: 1.3rem; font-weight: 900; color: #ff9800; }
        .section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-left: 10px; border-left: 4px solid #ff9800; color: #ff9800; font-weight: 800; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .input-group label .required { color: #ff5252; }
        .input-group input, .input-group select { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 1rem; transition: 0.3s; }
        .input-group input:focus, .input-group select:focus { border-color: #ff9800; outline: none; box-shadow: 0 0 0 4px rgba(255, 152, 0, 0.15); }
        .summary-table { background: white; border-radius: 15px; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .summary-row { display: flex; padding: 12px 20px; border-bottom: 1px solid #f1f5f9; }
        .summary-row:last-child { border-bottom: none; }
        .summary-label { width: 140px; color: #64748b; font-size: 0.9rem; }
        .summary-value { flex: 1; font-weight: 600; color: #1e293b; }
        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .pay-option { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; cursor: pointer; display: flex; align-items: center; gap: 15px; transition: 0.3s; position: relative; }
        .pay-option:hover { border-color: #ff9800; background: #fffcf0; }
        .pay-option.selected { border-color: #ff9800; background: #fffcf0; box-shadow: 0 8px 20px rgba(255, 152, 0, 0.1); }
        .pay-radio { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .pay-option.selected .pay-radio { border-color: #ff9800; }
        .pay-option.selected .pay-radio::after { content: ''; width: 10px; height: 10px; background: #ff9800; border-radius: 50%; }
        .pay-icon { width: 45px; height: 45px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #1e293b; border: 1px solid #e2e8f0; }
        .pay-info .pay-name { display: block; font-weight: 700; color: #ff9800; font-size: 0.95rem; }
        .pay-info .pay-desc { font-size: 0.75rem; color: #64748b; }
        .modal-footer { display: flex; gap: 15px; padding: 25px 30px; background: white; border-top: 1px solid #e2e8f0; }
        .btn-back { flex: 1; padding: 15px; border: none; background: #94a3b8; color: white; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-proceed { flex: 2; padding: 15px; border: none; background: linear-gradient(135deg, #FFC107 0%, #FF9800 100%); color: white; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 10px 20px rgba(255, 152, 0, 0.25); }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo" onclick="window.location.href='../index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
    </header>

    <section class="main-page-section">
        <div class="pkgdet-wrap">
            <?php if (!$svc): ?>
                <div class="pkgdet-notfound">
                    <div class="pkgdet-notfound-icon"><i class="fas fa-hotel"></i></div>
                    <h2>Hotel Not Found</h2>
                    <p>This hotel listing may have been removed or the link is incorrect.</p>
                    <div class="pkgdet-notfound-actions">
                        <a href="hotel.php" class="pkgdet-notfound-btn primary"><i class="fas fa-arrow-left"></i> Back to Hotels</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pkgdet-top-row">
                    <a href="../index.php" class="pkgdet-back-btn"><i class="fas fa-home"></i> Back to Home</a>
                    <div class="pkgdet-breadcrumb">
                        <a href="../index.php">Home</a> /
                        <a href="hotel.php">Hotel</a> /
                        <?= htmlspecialchars($svc['title']) ?>
                    </div>
                </div>

                <div class="pkgdet-title-row">
                    <div>
                        <h1><?= htmlspecialchars($svc['title']) ?></h1>
                        <div class="pkgdet-location"><i class="fas fa-clock"></i> <?= htmlspecialchars($svc['duration'] ?: 'Flexible stay') ?></div>
                    </div>
                    <div class="pkgdet-social-badge"><i class="fas fa-check-circle"></i> Available</div>
                </div>

                <?php
                $galleryImgs = [];
                $decodedGallery = json_decode($svc['image_gallery'] ?? '', true);
                if (is_array($decodedGallery)) { foreach ($decodedGallery as $g) if ($g) $galleryImgs[] = $g; }
                if ($svc['featured_image'] && !in_array($svc['featured_image'], $galleryImgs, true)) {
                    array_unshift($galleryImgs, $svc['featured_image']);
                }
                $heroImg = $galleryImgs[0] ?? '';
                ?>
                <div class="pkgdet-gallery">
                    <div class="pkgdet-gtile">
                        <img src="<?= $heroImg ? htmlspecialchars(svcImg($heroImg)) : '../images/hotel-hero.png' ?>" alt="<?= htmlspecialchars($svc['title']) ?>" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27800%27 height=%27440%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23e2e8f0%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 font-family=%27sans-serif%27 font-size=%2724%27 fill=%27%2394a3b8%27 text-anchor=%27middle%27%3ENo Photo Available%3C/text%3E%3C/svg%3E'">
                    </div>
                </div>

                <div class="pkgdet-body">
                    <div class="pkgdet-main">
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-info-circle"></i> Overview</h2>
                            <div class="pkgdet-chips">
                                <div class="pkgdet-chip"><i class="fas fa-clock"></i> <?= htmlspecialchars($svc['duration'] ?: 'Flexible') ?></div>
                                <?php if ($svc['category']): ?><div class="pkgdet-chip"><i class="fas fa-tag"></i> <?= htmlspecialchars($svc['category']) ?></div><?php endif; ?>
                                <?php if ($svc['badge_text']): ?><div class="pkgdet-chip"><i class="fas fa-award"></i> <?= htmlspecialchars($svc['badge_text']) ?></div><?php endif; ?>
                                <?php if ($svc['available_slots'] > 0): ?><div class="pkgdet-chip"><i class="fas fa-bed"></i> <?= intval($svc['available_slots']) ?> rooms left</div><?php endif; ?>
                            </div>
                            <p class="pkgdet-desc"><?= nl2br(htmlspecialchars($svc['full_description'] ?: $svc['description'] ?: $svc['short_description'] ?: 'Details about this hotel will be provided upon booking.')) ?></p>
                        </div>

                        <?php $highlights = svcLines($svc['highlights']); if ($highlights): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-star"></i> Highlights &amp; Amenities</h2>
                            <?php foreach ($highlights as $h): ?><div class="pkgdet-line-row inc"><span class="dot"><i class="fas fa-check"></i></span><span><?= htmlspecialchars($h) ?></span></div><?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php $amenities = svcLines($svc['amenities']); if ($amenities): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-concierge-bell"></i> Amenities</h2>
                            <div class="pkgdet-chips">
                                <?php foreach ($amenities as $a): ?><div class="pkgdet-chip"><i class="fas fa-check"></i> <?= htmlspecialchars($a) ?></div><?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php $itinerary = $svc['itinerary_rows']; if ($itinerary): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-route"></i> Stay Itinerary</h2>
                            <?php foreach ($itinerary as $day): ?>
                                <div class="pkgdet-day">
                                    <h4><?= htmlspecialchars($day['title'] ?: ('Day ' . $day['day_number'])) ?></h4>
                                    <?php if ($day['description']): ?><p style="margin:0; color:#475569; font-size:0.88rem;"><?= nl2br(htmlspecialchars($day['description'])) ?></p><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php $inclusions = svcLines($svc['inclusions']); $exclusions = svcLines($svc['exclusions']); if ($inclusions || $exclusions): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-clipboard-check"></i> Inclusions &amp; Exclusions</h2>
                            <div class="pkgdet-two-col">
                                <div>
                                    <h4 style="color:#15803d;">Included</h4>
                                    <?php if (!$inclusions): ?><p style="color:#94a3b8; font-size:0.85rem;">Nothing listed.</p><?php endif; ?>
                                    <?php foreach ($inclusions as $i): ?><div class="pkgdet-line-row inc"><span class="dot"><i class="fas fa-check"></i></span><span><?= htmlspecialchars($i) ?></span></div><?php endforeach; ?>
                                </div>
                                <div>
                                    <h4 style="color:#b91c1c;">Not Included</h4>
                                    <?php if (!$exclusions): ?><p style="color:#94a3b8; font-size:0.85rem;">Nothing listed.</p><?php endif; ?>
                                    <?php foreach ($exclusions as $e): ?><div class="pkgdet-line-row exc"><span class="dot"><i class="fas fa-times"></i></span><span><?= htmlspecialchars($e) ?></span></div><?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($svc['required_documents'] || $svc['travel_requirements'] || $svc['cancellation_policy'] || $svc['terms_conditions']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-exclamation-circle"></i> Requirements &amp; Policies</h2>
                            <?php if ($svc['required_documents']): ?><h4>Required Documents</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($svc['required_documents'])) ?></p><?php endif; ?>
                            <?php if ($svc['travel_requirements']): ?><h4>Travel Requirements</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($svc['travel_requirements'])) ?></p><?php endif; ?>
                            <?php if ($svc['cancellation_policy']): ?><h4>Cancellation Policy</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($svc['cancellation_policy'])) ?></p><?php endif; ?>
                            <?php if ($svc['terms_conditions']): ?><h4>Terms &amp; Conditions</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($svc['terms_conditions'])) ?></p><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($svc['partner_id']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-handshake"></i> Provided By</h2>
                            <div class="pkgdet-partner-box">
                                <div class="icon"><i class="fas fa-store"></i></div>
                                <div>
                                    <a href="../view-partner-profile.php?id=<?= intval($svc['partner_id']) ?>&from_type=hotel&from_id=<?= urlencode($identifier) ?>"><?= htmlspecialchars($svc['partner_company'] ?: 'Partner Provider') ?></a>
                                    <p style="margin:2px 0 0; color:#64748b; font-size:0.82rem;">This hotel is offered by one of our trusted partners.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkgdet-sticky">
                        <div class="pkgdet-price-card">
                            <span class="pkgdet-price-now"><?= htmlspecialchars($svc['currency'] ?: '₱') ?><?= number_format($svc['price']) ?></span>
                            <div class="pkgdet-price-per">per night</div>
                            <button class="pkgdet-book-btn" onclick="bookThisHotel()"><i class="fas fa-bolt"></i> Book Now</button>
                            <div class="pkgdet-price-meta">
                                <div><span>Duration</span><strong><?= htmlspecialchars($svc['duration'] ?: 'Flexible') ?></strong></div>
                                <div><span>Status</span><strong><?= htmlspecialchars($svc['status_text'] ?: 'Available') ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-logo-section">
                <div class="footer-logo">
                    <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="footer-logo-img">
                    <span class="footer-brand">HeyDream</span>
                </div>
                <div class="footer-country"><i class="fas fa-globe"></i> Philippines (Pilipinas)</div>
            </div>
            <div class="footer-links-grid">
                <div class="footer-column">
                    <h4>Contact Us</h4>
                    <ul class="contact-list">
                        <li><i class="fas fa-map-marker-alt"></i> 3104 Tektite East Tower, Philippine Stock Exchange, Ortigas</li>
                        <li><i class="fas fa-phone-alt"></i> 0945 776 4140</li>
                        <li><i class="fas fa-envelope"></i> heydreamtravelandtours@gmail.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9AM-6PM<br>Sat: 9AM-1PM</li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="../career.php">Career</a></li>
                        <li><a href="../privacy.php">Data Privacy Policy</a></li>
                        <li><a href="../terms.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-social">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="https://x.com/HeyDreamTravel?s=20" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <!-- Multi-Step Booking Modal (ported from hotel.php) -->
    <div id="bookingModal" class="booking-modal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <span class="close-booking" onclick="closeModal()">&times;</span>
                <h2><i class="fas fa-hotel"></i> Book Hotel</h2>
                <p>Complete your booking</p>
            </div>

            <div class="booking-steps-nav">
                <div class="step-item" id="step1-indicator">
                    <div class="step-circle">1</div>
                    <div class="step-label">Details</div>
                    <div class="step-connector"></div>
                </div>
                <div class="step-item" id="step2-indicator">
                    <div class="step-circle">2</div>
                    <div class="step-label">Review</div>
                    <div class="step-connector"></div>
                </div>
                <div class="step-item" id="step3-indicator">
                    <div class="step-circle">3</div>
                    <div class="step-label">Payment</div>
                    <div class="step-connector"></div>
                </div>
                <div class="step-item" id="step4-indicator">
                    <div class="step-circle">4</div>
                    <div class="step-label">Confirm</div>
                </div>
            </div>

            <div class="booking-body" id="step-contents-container"></div>
            <div class="modal-footer" id="modal-footer-container"></div>
        </div>
    </div>

    <script>
        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser(); echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';

        // "Book Now" opens the exact same 4-step booking wizard used on
        // hotel.php, right here on the details page, instead of navigating away.
        function bookThisHotel() {
            requireLogin('openBookingModal', <?= json_encode($svc['id'] ?? 0) ?>, <?= json_encode($svc['title'] ?? '') ?>, <?= json_encode(floatval($svc['price'] ?? 0)) ?>);
        }
    </script>
    <script>
        let currentHotel = null;
        let bookingData = {
            step: 1,
            hotelId: null,
            hotelName: '',
            price: 0,
            travelers: 1,
            checkIn: '',
            checkOut: '',
            fullName: window.currentFullName || '',
            email: window.currentUserEmail || '',
            phone: '',
            paymentMethod: ''
        };

        function openBookingModal(id, name, price) {
            let cleanPrice = 0;
            if (typeof price === 'string') {
                cleanPrice = parseFloat(price.replace(/[^\d.]/g, ''));
            } else {
                cleanPrice = price;
            }

            bookingData.hotelId = id;
            bookingData.hotelName = name;
            bookingData.price = cleanPrice || 0;
            bookingData.step = 1;

            document.getElementById('bookingModal').classList.add('active');
            renderStep1();
        }

        function closeModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }

        function updateStepIndicators(step) {
            for (let i = 1; i <= 4; i++) {
                const el = document.getElementById(`step${i}-indicator`);
                if (el) {
                    el.classList.remove('active', 'completed');
                    if (i < step) el.classList.add('completed');
                    if (i === step) el.classList.add('active');
                }
            }
        }

        function updateLiveTotal(val) {
            const num = parseInt(val) || 0;
            const total = bookingData.price * num;
            const display = document.getElementById('live-total-val');
            if (display) {
                display.innerText = '₱' + total.toLocaleString();
            }
        }

        function renderStep1() {
            updateStepIndicators(1);
            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-hotel"></i></div>
                    <div class="mini-card-info">
                        <h4>${bookingData.hotelName}</h4>
                        <span class="mini-price">₱${bookingData.price.toLocaleString()}</span>
                        <p style="margin:0; font-size:0.75rem; color:#64748b;">Per Person</p>
                    </div>
                </div>

                <div class="section-header"><i class="fas fa-user"></i> Guest Information</div>
                <div class="input-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" id="applicationEmail" value="${bookingData.email || ''}" placeholder="Your email address">
                </div>
                <div class="input-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="fullName" value="${bookingData.fullName}" placeholder="Steven Rebancos">
                </div>
                <div class="input-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="text" id="phone" value="${bookingData.phone}" placeholder="+63 912 345 6789">
                </div>

                <div class="section-header" style="margin-top:25px;"><i class="fas fa-calendar-alt"></i> Stay Details</div>
                <div class="form-row">
                    <div class="input-group">
                        <label>Check-in Date <span class="required">*</span></label>
                        <input type="date" id="checkIn" value="${bookingData.checkIn}">
                    </div>
                    <div class="input-group">
                        <label>Check-out Date <span class="required">*</span></label>
                        <input type="date" id="checkOut" value="${bookingData.checkOut}">
                    </div>
                </div>
                <div class="input-group">
                    <label>Number of Guests <span class="required">*</span></label>
                    <input type="number" id="travelers" value="${bookingData.travelers}" min="1" max="50" oninput="updateLiveTotal(this.value)">
                </div>

                <div id="live-total-display" style="margin-top:20px; padding:15px; background:#fffcf0; border-radius:12px; border:1px solid #fff3c4; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; color:#b45309;">Estimated Total:</span>
                    <span id="live-total-val" style="font-size:1.2rem; font-weight:900; color:#ff9800;">₱${(bookingData.price * bookingData.travelers).toLocaleString()}</span>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn-proceed" style="flex:1; margin: 0 30px;" onclick="validateStep1()">Proceed to Review <i class="fas fa-arrow-right"></i></button>
            `;
        }

        function validateStep1() {
            const email = document.getElementById('applicationEmail').value.trim();
            const name = document.getElementById('fullName').value;
            const phone = document.getElementById('phone').value;
            const checkIn = document.getElementById('checkIn').value;
            const checkOut = document.getElementById('checkOut').value;
            const travelers = document.getElementById('travelers').value;

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            if (!name || !phone || !checkIn || !checkOut) {
                alert('Please fill in all required fields.');
                return;
            }

            bookingData.email = email;
            bookingData.fullName = name;
            bookingData.phone = phone;
            bookingData.checkIn = checkIn;
            bookingData.checkOut = checkOut;
            bookingData.travelers = parseInt(travelers);

            renderStep2();
        }

        function renderStep2() {
            updateStepIndicators(2);
            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');
            const total = bookingData.price * bookingData.travelers;

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-hotel"></i></div>
                    <div class="mini-card-info">
                        <h4>${bookingData.hotelName}</h4>
                        <span class="mini-price">₱${bookingData.price.toLocaleString()}</span>
                    </div>
                </div>

                <div class="section-header">Guest Info</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Name:</div><div class="summary-value">${bookingData.fullName}</div></div>
                    <div class="summary-row"><div class="summary-label">Phone:</div><div class="summary-value">${bookingData.phone}</div></div>
                </div>

                <div class="section-header">Stay Details</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Check-in:</div><div class="summary-value">${bookingData.checkIn}</div></div>
                    <div class="summary-row"><div class="summary-label">Check-out:</div><div class="summary-value">${bookingData.checkOut}</div></div>
                    <div class="summary-row"><div class="summary-label">Guests:</div><div class="summary-value">${bookingData.travelers} Guest${bookingData.travelers > 1 ? 's' : ''}</div></div>
                </div>

                <div class="section-header">Price Summary</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Base Price:</div><div class="summary-value">₱${bookingData.price.toLocaleString()}</div></div>
                    <div class="summary-row" style="background:#fffcf0;"><div class="summary-label" style="font-weight:800; color:#1e293b;">Total:</div><div class="summary-value" style="color:#ff9800; font-size:1.2rem; font-weight:900;">₱${total.toLocaleString()}</div></div>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn-back" onclick="renderStep1()"><i class="fas fa-arrow-left"></i> Back</button>
                <button class="btn-proceed" onclick="renderStep3()">Proceed to Payment <i class="fas fa-credit-card"></i></button>
            `;
        }

        function renderStep3() {
            updateStepIndicators(3);
            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-hotel"></i></div>
                    <div class="mini-card-info">
                        <h4>${bookingData.hotelName}</h4>
                        <span class="mini-price">₱${(bookingData.price * bookingData.travelers).toLocaleString()}</span>
                    </div>
                </div>

                <div class="section-header"><i class="fas fa-wallet"></i> Select Payment Method</div>
                <div class="payment-grid">
                    <div class="pay-option" onclick="selectPayment('GCash', this)">
                        <div class="pay-radio"></div>
                        <div class="pay-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">GCash</span>
                            <span class="pay-desc">Scan QR to pay</span>
                        </div>
                    </div>
                    <div class="pay-option" onclick="selectPayment('PayMaya', this)">
                        <div class="pay-radio"></div>
                        <div class="pay-icon"><i class="fas fa-wallet"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">PayMaya</span>
                            <span class="pay-desc">Scan QR to pay</span>
                        </div>
                    </div>
                    <div class="pay-option disabled" onclick="alert('Credit/Debit Card payment is coming soon! Please use other payment methods for now.')" style="opacity: 0.6; cursor: not-allowed; filter: grayscale(0.5); position: relative;">
                        <div class="pay-radio" style="background: #e2e8f0;"></div>
                        <div class="pay-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">Card <span style="color: #ef4444; font-size: 0.6rem; font-weight: 800; margin-left: 4px;">NOT AVAILABLE</span></span>
                            <span class="pay-desc">Coming Soon</span>
                        </div>
                    </div>
                    <div class="pay-option" onclick="selectPayment('Bank', this)">
                        <div class="pay-radio"></div>
                        <div class="pay-icon"><i class="fas fa-university"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">Bank</span>
                            <span class="pay-desc">BDO / BPI</span>
                        </div>
                    </div>
                </div>

                <div id="payment-details-panel" style="display:none; background:white; border:1px solid #ff9800; border-radius:20px; padding:25px; text-align:center; animation: fadeIn 0.3s ease; box-shadow:0 10px 30px rgba(255,152,0,0.1);">
                    <p style="font-weight:800; color:#ff9800; margin-bottom:15px; font-size:1.1rem;">Payment Instructions: <span id="selected-method-name">GCash</span></p>

                    <div style="background:#f8fafc; border-radius:15px; padding:15px; margin-bottom:20px; border:1px solid #e2e8f0;">
                        <div style="width:120px; height:120px; background:white; border:1px solid #e2e8f0; margin:0 auto 15px; display:flex; align-items:center; justify-content:center; border-radius:12px;">
                            <i class="fas fa-qrcode" style="font-size:5rem; color:#1e293b;"></i>
                        </div>
                        <p style="font-size:0.85rem; color:#64748b; margin:0;">Account Name: <b>HeyDream Travel</b><br>Account #: <b>0945-XXX-XXXX</b></p>
                    </div>

                    <div style="text-align:left; margin-bottom:20px;">
                        <div class="input-group">
                            <label>Transaction Reference Number <span class="required">*</span></label>
                            <input type="text" id="refNumber" placeholder="Enter Reference ID" oninput="checkPaymentFields()">
                        </div>
                        <div class="input-group">
                            <label>Proof of Payment (Screenshot/Photo) <span class="required">*</span></label>
                            <div style="position:relative;">
                                <input type="file" id="proofFile" style="display:none;" onchange="handleFileSelect(this)">
                                <button type="button" onclick="document.getElementById('proofFile').click()" style="width:100%; padding:15px; background:#f1f5f9; border:2px dashed #cbd5e1; border-radius:12px; color:#64748b; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px;">
                                    <i class="fas fa-cloud-upload-alt"></i> <span id="fileNameDisplay">Upload Receipt</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn-back" onclick="renderStep2()"><i class="fas fa-arrow-left"></i> Back</button>
                <button class="btn-proceed" id="finalPaymentBtn" style="opacity:0.5; pointer-events:none;" onclick="renderStep4()">Complete Payment <i class="fas fa-check-circle"></i></button>
            `;
        }

        function selectPayment(method, el) {
            bookingData.paymentMethod = method;
            document.querySelectorAll('.pay-option').forEach(opt => opt.classList.remove('selected'));
            el.classList.add('selected');

            document.getElementById('payment-details-panel').style.display = 'block';
            document.getElementById('selected-method-name').textContent = method;

            checkPaymentFields();
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                document.getElementById('fileNameDisplay').textContent = input.files[0].name;
                document.getElementById('fileNameDisplay').parentElement.style.borderColor = '#22c55e';
                document.getElementById('fileNameDisplay').parentElement.style.background = '#f0fdf4';
                document.getElementById('fileNameDisplay').parentElement.style.color = '#22c55e';
            }
            checkPaymentFields();
        }

        function checkPaymentFields() {
            const refNo = document.getElementById('refNumber')?.value;
            const file = document.getElementById('proofFile')?.files[0];
            const btn = document.getElementById('finalPaymentBtn');

            if (refNo && file && bookingData.paymentMethod) {
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            } else {
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
            }
        }

        async function renderStep4() {
            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');

            if (!bookingData || !bookingData.hotelName) {
                container.innerHTML = `
                    <div style="text-align:center; padding:40px 20px;">
                        <div style="width:80px; height:80px; background:#fef2f2; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#ef4444; font-size:3rem; margin:0 auto 25px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 style="color:#1e293b;">Booking Session Expired</h3>
                        <p style="color:#64748b; margin-bottom:20px;">Please close this window and start the booking again.</p>
                        <button class="btn-proceed" onclick="closeModal()">Close</button>
                    </div>
                `;
                footer.innerHTML = '';
                return;
            }

            updateStepIndicators(4);

            container.innerHTML = `
                <div style="text-align:center; padding:60px 20px;">
                    <div class="loading-spinner" style="width:60px; height:60px; border:5px solid #f3f3f3; border-top:5px solid #ff9800; border-radius:50%; margin:0 auto 20px; animation: spin 1s linear infinite;"></div>
                    <h3 style="color:#1e293b;">Processing Your Booking...</h3>
                    <p style="color:#64748b;">Please don't close this window.</p>
                </div>
            `;
            footer.innerHTML = '';

            const formData = new FormData();
            formData.append('service_type', 'Hotel');
            formData.append('package_name', bookingData.hotelName);
            formData.append('full_name', bookingData.fullName);
            formData.append('phone', bookingData.phone);
            formData.append('email', bookingData.email);
            formData.append('travel_date', bookingData.checkIn);
            formData.append('check_out', bookingData.checkOut);
            formData.append('number_of_travelers', bookingData.travelers);
            formData.append('total_amount', bookingData.price * bookingData.travelers);
            formData.append('payment_method', bookingData.paymentMethod);
            formData.append('payment_reference', document.getElementById('refNumber')?.value || '');

            const proofFile = document.getElementById('proofFile')?.files[0];
            if (proofFile) {
                formData.append('payment_proof', proofFile);
            }

            try {
                const response = await fetch('../api/save-service-booking.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    container.innerHTML = `
                        <div style="text-align:center; padding:40px 20px;">
                            <div style="width:100px; height:100px; background:#f0fdf4; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#22c55e; font-size:3.5rem; margin:0 auto 25px; box-shadow: 0 15px 30px rgba(34, 197, 94, 0.2);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h2 style="color:#1e293b; margin-bottom:10px; font-weight:800;">Booking Successful!</h2>
                            <p style="color:#64748b; margin-bottom:30px;">Thank you for booking with HeyDream Travel. Your reservation is being processed.</p>

                            <div style="background:white; border:1px solid #e2e8f0; border-radius:20px; padding:20px; margin-bottom:20px;">
                                <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">Booking Number</span>
                                <span style="font-size:1.4rem; font-weight:900; color:#ff9800; letter-spacing:2px;">${result.booking_number}</span>
                            </div>
                            <p style="font-size:0.85rem; color:#64748b;">We've sent the confirmation details to <b>${bookingData.email}</b></p>
                        </div>
                    `;
                    footer.innerHTML = `
                        <button class="btn-proceed" style="flex:1; background:#ff9800;" onclick="window.location.href='../User Account/profile.php?track=' + encodeURIComponent('${result.booking_number}')"><i class="fas fa-file-upload"></i> View My Booking</button>
                        <button class="btn-proceed" style="flex:1; background:#1e293b;" onclick="closeModal()">Close & Return to Hotels</button>
                    `;
                    return;
                } else {
                    throw new Error(result.message || 'Failed to save booking');
                }
            } catch (error) {
                container.innerHTML = `
                    <div style="text-align:center; padding:40px 20px;">
                        <div style="width:80px; height:80px; background:#fef2f2; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#ef4444; font-size:3rem; margin:0 auto 25px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 style="color:#1e293b;">Booking Error</h3>
                        <p style="color:#64748b; margin-bottom:20px;">${error.message}</p>
                        <button class="btn-proceed" onclick="renderStep3()">Try Again</button>
                    </div>
                `;
            }

            footer.innerHTML = `
                <button class="btn-proceed" style="flex:1; background:#1e293b;" onclick="closeModal()">Close & Return to Hotels</button>
            `;
        }
    </script>
    <script src="../js/main.js?v=2"></script>
    <script src="../js/auth-menu.js?v=2"></script>
</body>

</html>

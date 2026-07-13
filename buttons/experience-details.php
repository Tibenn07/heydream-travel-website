<?php
// ========================================
// FILE: buttons/experience-details.php
// DESCRIPTION: Full-page detail view for an experience service (mirrors package-details.php)
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
            WHERE ss.service_type = 'experience' AND ";
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
    <title><?= $svc ? htmlspecialchars($svc['title']) . ' - HeyDream Travel' : 'Experience Not Found - HeyDream Travel' ?></title>
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
        @media (max-width: 900px) { .pkgdet-body { grid-template-columns: 1fr; } }

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

        /* ---- Booking modal (ported from experiences.php so "Book Now" opens in place) ---- */
        .premium-booking-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 5000; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
        .premium-booking-modal.active { display: flex; }
        .booking-modal-content { background: #f8fafc; border-radius: 30px; max-width: 650px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3); animation: modalPopUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes modalPopUp { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .booking-modal-header { background: linear-gradient(135deg, #addb4c, #14c492); color: white; padding: 30px; border-radius: 30px 30px 0 0; position: relative; }
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
        .mini-card-icon { width: 60px; height: 60px; background: #e6fcf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #14c492; font-size: 1.5rem; }
        .mini-card-info h4 { margin: 0; color: #14c492; font-size: 1.1rem; }
        .mini-card-info .mini-price { font-size: 1.3rem; font-weight: 900; color: #ff9800; }
        .section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-left: 10px; border-left: 4px solid #ff9800; color: #14c492; font-weight: 800; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .input-group label .required { color: #ff5252; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 1rem; transition: 0.3s; }
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus { border-color: #14c492; outline: none; box-shadow: 0 0 0 4px rgba(20, 196, 146, 0.1); }
        .summary-table { background: white; border-radius: 15px; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .summary-row { display: flex; padding: 12px 20px; border-bottom: 1px solid #f1f5f9; }
        .summary-row:last-child { border-bottom: none; }
        .summary-label { width: 140px; color: #64748b; font-size: 0.9rem; }
        .summary-value { flex: 1; font-weight: 600; color: #1e293b; }
        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .pay-option { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; cursor: pointer; display: flex; align-items: center; gap: 15px; transition: 0.3s; position: relative; }
        .pay-option:hover { border-color: #14c492; background: #f0fdf4; }
        .pay-option.selected { border-color: #ff9800; background: #fffcf0; box-shadow: 0 8px 20px rgba(255, 152, 0, 0.1); }
        .pay-radio { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .pay-option.selected .pay-radio { border-color: #ff9800; }
        .pay-option.selected .pay-radio::after { content: ''; width: 10px; height: 10px; background: #ff9800; border-radius: 50%; }
        .pay-icon { width: 45px; height: 45px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #1e293b; border: 1px solid #e2e8f0; }
        .pay-info .pay-name { display: block; font-weight: 700; color: #14c492; font-size: 0.95rem; }
        .pay-info .pay-desc { font-size: 0.75rem; color: #64748b; }
        .modal-footer { display: flex; gap: 15px; padding: 25px 30px; background: white; border-top: 1px solid #e2e8f0; }
        .btn-back { flex: 1; padding: 15px; border: none; background: #94a3b8; color: white; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-proceed { flex: 2 !important; padding: 15px !important; border: none !important; background: linear-gradient(135deg, #addb4c, #14c492) !important; color: white !important; border-radius: 12px !important; font-weight: 700 !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 10px !important; box-shadow: 0 10px 20px rgba(20, 196, 146, 0.2) !important; transition: all 0.3s ease !important; }
        .btn-proceed:hover { background: linear-gradient(135deg, #b2e650, #16d9a2) !important; transform: translateY(-2px) !important; box-shadow: 0 12px 24px rgba(20, 196, 146, 0.35) !important; color: white !important; }
        .btn-proceed:disabled { background: #cbd5e1 !important; box-shadow: none !important; cursor: not-allowed !important; transform: none !important; }
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
                    <div class="pkgdet-notfound-icon"><i class="fas fa-star"></i></div>
                    <h2>Experience Not Found</h2>
                    <p>This experience listing may have been removed or the link is incorrect.</p>
                    <div class="pkgdet-notfound-actions">
                        <a href="experiences.php" class="pkgdet-notfound-btn primary"><i class="fas fa-arrow-left"></i> Back to Experiences</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pkgdet-top-row">
                    <a href="../index.php" class="pkgdet-back-btn"><i class="fas fa-home"></i> Back to Home</a>
                    <div class="pkgdet-breadcrumb">
                        <a href="../index.php">Home</a> /
                        <a href="experiences.php">Experiences</a> /
                        <?= htmlspecialchars($svc['title']) ?>
                    </div>
                </div>

                <div class="pkgdet-title-row">
                    <div>
                        <h1><?= htmlspecialchars($svc['title']) ?></h1>
                        <div class="pkgdet-location"><i class="fas fa-clock"></i> <?= htmlspecialchars($svc['duration'] ?: 'Day Tour') ?></div>
                    </div>
                    <div class="pkgdet-social-badge"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($svc['status_text'] ?: 'Available') ?></div>
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
                        <img src="<?= $heroImg ? htmlspecialchars(svcImg($heroImg)) : '../images/experience-hero.png' ?>" alt="<?= htmlspecialchars($svc['title']) ?>" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27800%27 height=%27440%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23e2e8f0%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 font-family=%27sans-serif%27 font-size=%2724%27 fill=%27%2394a3b8%27 text-anchor=%27middle%27%3ENo Photo Available%3C/text%3E%3C/svg%3E'">
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
                                <?php if ($svc['available_slots'] > 0): ?><div class="pkgdet-chip"><i class="fas fa-user-friends"></i> <?= intval($svc['available_slots']) ?> slots left</div><?php endif; ?>
                            </div>
                            <p class="pkgdet-desc"><?= nl2br(htmlspecialchars($svc['full_description'] ?: $svc['description'] ?: $svc['short_description'] ?: 'Details about this experience will be provided upon booking.')) ?></p>
                        </div>

                        <?php $highlights = svcLines($svc['highlights']); if ($highlights): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-star"></i> Highlights</h2>
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
                            <h2><i class="fas fa-route"></i> Itinerary</h2>
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
                                    <a href="../view-partner-profile.php?id=<?= intval($svc['partner_id']) ?>&from_type=experience&from_id=<?= urlencode($identifier) ?>"><?= htmlspecialchars($svc['partner_company'] ?: 'Partner Provider') ?></a>
                                    <p style="margin:2px 0 0; color:#64748b; font-size:0.82rem;">This experience is offered by one of our trusted partners.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkgdet-sticky">
                        <div class="pkgdet-price-card">
                            <span class="pkgdet-price-now"><?= htmlspecialchars($svc['currency'] ?: '₱') ?><?= number_format($svc['price']) ?></span>
                            <div class="pkgdet-price-per">per person</div>
                            <button class="pkgdet-book-btn" onclick="bookThisExperience()"><i class="fas fa-bolt"></i> Book Now</button>
                            <div class="pkgdet-price-meta">
                                <div><span>Duration</span><strong><?= htmlspecialchars($svc['duration'] ?: 'Day Tour') ?></strong></div>
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

    <script>
        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser(); echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';

        // "Book Now" opens the exact same booking wizard used on
        // experiences.php, right here on the details page, instead of
        // navigating away.
        function bookThisExperience() {
            requireLogin('showExperienceBooking', <?= json_encode($svc['title'] ?? '') ?>, <?= json_encode(floatval($svc['price'] ?? 0)) ?>, <?= json_encode($svc['duration'] ?? '') ?>);
        }
    </script>
    <script>
        let currentExp = null, expBookingData = null, selectedPayment = null;

        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
        function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { const btn = event.target; const originalText = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#28a745'; btn.style.color = 'white'; setTimeout(() => { btn.textContent = originalText; btn.style.background = '#e0e0e0'; btn.style.color = ''; }, 1500); }); }
        function handleFileUpload(event, paymentMethod) { const file = event.target.files[0]; if (file) { if (!file.type.match('image.*')) { alert('Please upload an image file (PNG, JPG, JPEG)'); event.target.value = ''; return; } if (file.size > 5 * 1024 * 1024) { alert('File is too large. Maximum size is 5MB.'); event.target.value = ''; return; } const reader = new FileReader(); reader.onload = function (e) { const previewDiv = document.getElementById(`preview-${paymentMethod}`); if (previewDiv) { previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof">`; } }; reader.readAsDataURL(file); const fileNameSpan = document.getElementById(`file-name-${paymentMethod}`); if (fileNameSpan) { fileNameSpan.textContent = file.name; } } }

        function updateExpLiveTotal(val) {
            const num = parseInt(val) || 0;
            const price = currentExp.price || 0;
            const display = document.getElementById('exp-live-total-val');
            if (display) {
                display.innerText = '₱' + (price * num).toLocaleString();
            }
        }

        function updateExpSteps(step) {
            for (let i = 1; i <= 4; i++) {
                const el = document.getElementById(`step${i}-indicator`);
                if (el) {
                    el.classList.remove('active', 'completed');
                    if (i < step) el.classList.add('completed');
                    if (i === step) el.classList.add('active');
                }
            }
        }

        function closeExpBookingModal() {
            document.getElementById('expBookingModal').classList.remove('active');
        }

        function showExperienceBooking(title, price, duration) {
            currentExp = { title, price, duration };
            let modal = document.getElementById('expBookingModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'expBookingModal';
                modal.className = 'premium-booking-modal';
                modal.innerHTML = `
                    <div class="booking-modal-content">
                        <div class="booking-modal-header">
                            <span class="close-booking" onclick="closeExpBookingModal()">&times;</span>
                            <h2><i class="fas fa-star"></i> Book Experience</h2>
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
                        <div class="booking-body" id="exp-step-contents-container">
                        </div>
                        <div class="modal-footer" id="exp-modal-footer-container">
                        </div>
                    </div>`;
                document.body.appendChild(modal);
            }
            renderExpStep1();
            modal.classList.add('active');
        }

        function renderExpStep1() {
            updateExpSteps(1);
            const container = document.getElementById('exp-step-contents-container');
            const footer = document.getElementById('exp-modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-star"></i></div>
                    <div class="mini-card-info">
                        <h4>${currentExp.title}</h4>
                        <span class="mini-price">₱${formatNumber(currentExp.price)}</span>
                        <p style="margin:0; font-size:0.75rem; color:#64748b;">${currentExp.duration}</p>
                    </div>
                </div>

                <div class="section-header"><i class="fas fa-user"></i> Guest Information</div>
                <div class="input-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" id="applicationEmail" value="${(expBookingData && expBookingData.email) || window.currentUserEmail || ''}" placeholder="Your email address">
                </div>
                <div class="input-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="fullName" placeholder="Your full name" value="${(expBookingData && expBookingData.fullName) || window.currentFullName || ''}">
                </div>
                <div class="input-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" id="phone" placeholder="+63 912 345 6789">
                </div>

                <div class="section-header" style="margin-top:25px;"><i class="fas fa-calendar-alt"></i> Experience Details</div>
                <div class="form-row">
                    <div class="input-group">
                        <label>Date <span class="required">*</span></label>
                        <input type="date" id="date" min="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="input-group">
                        <label>Time</label>
                        <select id="time">
                            <option value="morning">Morning (9AM-12PM)</option>
                            <option value="afternoon">Afternoon (1PM-5PM)</option>
                            <option value="evening">Evening (6PM-9PM)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-group">
                        <label>Number of Travelers <span class="required">*</span></label>
                        <input type="number" id="participants" min="1" value="1" oninput="updateExpLiveTotal(this.value)">
                    </div>
                    <div class="input-group">
                        <label>Location</label>
                        <select id="location">
                            <option value="manila">Manila</option>
                            <option value="cebu">Cebu</option>
                            <option value="palawan">Palawan</option>
                            <option value="boracay">Boracay</option>
                        </select>
                    </div>
                </div>

                <div class="section-header" style="margin-top:25px;"><i class="fas fa-info-circle"></i> Special Requirements</div>
                <div class="input-group">
                    <label>Dietary Restrictions</label>
                    <textarea id="dietary" rows="2" placeholder="Any dietary restrictions?"></textarea>
                </div>
                <div class="input-group">
                    <label>Additional Requests</label>
                    <textarea id="requests" rows="2" placeholder="Equipment needs, accessibility, etc."></textarea>
                </div>

                <div id="exp-live-total-display" style="margin-top:20px; padding:15px; background:#f0fbfb; border-radius:12px; border:1px solid #b2e0e0; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; color:#004d4d;">Estimated Total:</span>
                    <span id="exp-live-total-val" style="font-size:1.2rem; font-weight:900; color:#14c492;">₱${formatNumber(currentExp.price)}</span>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn-proceed" style="flex:1;" onclick="validateAndGoToStep2()">Proceed to Review <i class="fas fa-arrow-right"></i></button>
            `;
        }

        function validateAndGoToStep2() {
            const email = document.getElementById('applicationEmail')?.value.trim();
            const fullName = document.getElementById('fullName')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const date = document.getElementById('date')?.value;
            const participants = document.getElementById('participants')?.value;

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            if (!fullName || !phone || !date || !participants || participants < 1) {
                alert('Please fill in all required fields.');
                return;
            }

            goToExpStep2();
        }

        function goToExpStep2() {
            updateExpSteps(2);
            const fullName = document.getElementById('fullName')?.value;
            const email = document.getElementById('applicationEmail')?.value.trim() || window.currentUserEmail || '';
            const phone = document.getElementById('phone')?.value;
            const date = document.getElementById('date')?.value;
            const time = document.getElementById('time')?.value;
            const participants = parseInt(document.getElementById('participants')?.value) || 1;
            const location = document.getElementById('location')?.value;
            const dietary = document.getElementById('dietary')?.value;
            const requests = document.getElementById('requests')?.value;
            const total = currentExp.price * participants;
            expBookingData = { fullName, email, phone, date, time, participants, location, dietary, requests, total };

            const container = document.getElementById('exp-step-contents-container');
            const footer = document.getElementById('exp-modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-star"></i></div>
                    <div class="mini-card-info">
                        <h4>${currentExp.title}</h4>
                        <span class="mini-price">₱${formatNumber(currentExp.price)}</span>
                    </div>
                </div>

                <div class="section-header">Participant Info</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Name:</div><div class="summary-value">${escapeHtml(fullName)}</div></div>
                    <div class="summary-row"><div class="summary-label">Phone:</div><div class="summary-value">${escapeHtml(phone)}</div></div>
                </div>

                <div class="section-header">Experience Details</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Date:</div><div class="summary-value">${new Date(date).toLocaleDateString()}</div></div>
                    <div class="summary-row"><div class="summary-label">Time:</div><div class="summary-value">${time === 'morning' ? 'Morning (9AM-12PM)' : time === 'afternoon' ? 'Afternoon (1PM-5PM)' : 'Evening (6PM-9PM)'}</div></div>
                    <div class="summary-row"><div class="summary-label">Participants:</div><div class="summary-value">${participants}</div></div>
                    <div class="summary-row"><div class="summary-label">Location:</div><div class="summary-value">${location.charAt(0).toUpperCase() + location.slice(1)}</div></div>
                </div>

                <div class="section-header">Price Summary</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Price per Person:</div><div class="summary-value">₱${formatNumber(currentExp.price)}</div></div>
                    <div class="summary-row" style="background:#f0fbfb;"><div class="summary-label" style="font-weight:800; color:#004d4d;">Total:</div><div class="summary-value" style="color:#14c492; font-size:1.2rem; font-weight:900;">₱${formatNumber(total)}</div></div>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn-back" onclick="renderExpStep1()"><i class="fas fa-arrow-left"></i> Back</button>
                <button class="btn-proceed" onclick="goToExpStep3()">Proceed to Payment <i class="fas fa-credit-card"></i></button>
            `;
        }

        function goToExpStep3() {
            updateExpSteps(3);
            const container = document.getElementById('exp-step-contents-container');
            const footer = document.getElementById('exp-modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-star"></i></div>
                    <div class="mini-card-info">
                        <h4>${currentExp.title}</h4>
                        <span class="mini-price">₱${formatNumber(expBookingData.total)}</span>
                    </div>
                </div>

                <div class="section-header"><i class="fas fa-wallet"></i> Select Payment Method</div>
                <div class="payment-grid">
                    <div class="pay-option" onclick="selectPaymentMethod('GCash', this)">
                        <div class="pay-radio"></div>
                        <div class="pay-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">GCash</span>
                            <span class="pay-desc">Scan QR to pay</span>
                        </div>
                    </div>
                    <div class="pay-option" onclick="selectPaymentMethod('PayMaya', this)">
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
                    <div class="pay-option" onclick="selectPaymentMethod('Bank', this)">
                        <div class="pay-radio"></div>
                        <div class="pay-icon"><i class="fas fa-university"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">Bank</span>
                            <span class="pay-desc">BDO / BPI</span>
                        </div>
                    </div>
                </div>

                <div id="payment-details-panel" style="display:none; background:white; border:1px solid #ff9800; border-radius:20px; padding:25px; text-align:center; animation: fadeIn 0.3s ease; box-shadow:0 10px 30px rgba(255,152,0,0.1);">
                    <p style="font-weight:800; color:#14c492; margin-bottom:15px; font-size:1.1rem;">Payment Instructions: <span id="selected-method-name">GCash</span></p>

                    <div style="background:#f8fafc; border-radius:15px; padding:15px; margin-bottom:20px; border:1px solid #e2e8f0;">
                        <div style="width:120px; height:120px; background:white; border:1px solid #e2e8f0; margin:0 auto 15px; display:flex; align-items:center; justify-content:center; border-radius:12px;">
                            <i class="fas fa-qrcode" style="font-size:5rem; color:#1e293b;"></i>
                        </div>
                        <p style="font-size:0.85rem; color:#64748b; margin:0;">Account Name: <b>HeyDream Travel</b><br>Account #: <b>0945-XXX-XXXX</b></p>
                    </div>

                    <div style="text-align:left; margin-bottom:20px;">
                        <div class="input-group">
                            <label>Transaction Reference Number <span class="required">*</span></label>
                            <input type="text" id="refNumber" placeholder="Enter Reference ID" oninput="checkExpPaymentFields()">
                        </div>
                        <div class="input-group">
                            <label>Proof of Payment (Screenshot/Photo) <span class="required">*</span></label>
                            <div style="position:relative;">
                                <input type="file" id="proofFile" style="display:none;" onchange="handleExpFileSelect(this)">
                                <button type="button" onclick="document.getElementById('proofFile').click()" style="width:100%; padding:15px; background:#f1f5f9; border:2px dashed #cbd5e1; border-radius:12px; color:#64748b; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px;">
                                    <i class="fas fa-cloud-upload-alt"></i> <span id="fileNameDisplay">Upload Receipt</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn-back" onclick="goToExpStep2()"><i class="fas fa-arrow-left"></i> Back</button>
                <button class="btn-proceed" id="finalPaymentBtn" style="opacity:0.5; pointer-events:none;" onclick="validateAndGoToStep4()">Complete Payment <i class="fas fa-check-circle"></i></button>
            `;
        }

        function selectPaymentMethod(method, el) {
            selectedPayment = method;
            document.querySelectorAll('.pay-option').forEach(opt => opt.classList.remove('selected'));
            el.classList.add('selected');

            document.getElementById('payment-details-panel').style.display = 'block';
            document.getElementById('selected-method-name').textContent = method;

            checkExpPaymentFields();
        }

        function checkExpPaymentFields() {
            const ref = document.getElementById('refNumber')?.value.trim();
            const file = document.getElementById('proofFile')?.files.length > 0;
            const btn = document.getElementById('finalPaymentBtn');
            if (btn) {
                if (ref && file) {
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                } else {
                    btn.style.opacity = '0.5';
                    btn.style.pointerEvents = 'none';
                }
            }
        }

        function handleExpFileSelect(input) {
            const display = document.getElementById('fileNameDisplay');
            if (input.files && input.files[0]) {
                display.textContent = input.files[0].name;
                display.style.color = '#14c492';
                checkExpPaymentFields();
            } else {
                display.textContent = 'Upload Receipt';
                display.style.color = '#64748b';
                checkExpPaymentFields();
            }
        }

        function renderExpStep4(bookingNumber) {
            updateExpSteps(4);
            const container = document.getElementById('exp-step-contents-container');
            const footer = document.getElementById('exp-modal-footer-container');

            container.innerHTML = `
                <div style="text-align:center; padding:40px 20px;">
                    <div style="width:80px; height:80px; background:#e8f5e9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                        <i class="fas fa-check" style="font-size:2.5rem; color:#22c55e;"></i>
                    </div>
                    <h3 style="color:#1e293b; font-weight:800; font-size:1.5rem; margin-bottom:10px;">Booking Confirmed!</h3>
                    <p style="color:#64748b; font-size:0.95rem; margin-bottom:20px;">Your experience has been successfully booked.</p>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:15px; padding:20px; display:inline-block; text-align:left;">
                        <p style="margin:0 0 8px; font-size:0.85rem; color:#64748b;">Booking Reference</p>
                        <p style="margin:0; font-size:1.3rem; font-weight:900; color:#14c492;">${bookingNumber}</p>
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn-proceed" style="flex:1;" onclick="window.location.href='../User Account/profile.php?track=' + encodeURIComponent('${bookingNumber}')"><i class="fas fa-file-upload"></i> View My Booking</button>
                <button class="btn-proceed" style="flex:1;" onclick="closeExpBookingModal(); location.reload();"><i class="fas fa-plus"></i> Book Another Experience</button>
            `;
        }

        function validateAndGoToStep4() {
            if (!selectedPayment) return;

            if (!currentExp || !expBookingData) {
                alert('Your booking session has expired or was reset. Please close this window and start the booking again.');
                return;
            }

            const btn = document.getElementById('finalPaymentBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.style.pointerEvents = 'none';

            try {
                let paymentMethodName = selectedPayment;
                expBookingData.paymentMethod = paymentMethodName;

                fetch('../api/save-service-booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_type: 'Travel Experience',
                        package_name: currentExp.title,
                        package_duration: currentExp.duration,
                        price_per_person: currentExp.price,
                        full_name: expBookingData.fullName,
                        email: expBookingData.email,
                        phone: expBookingData.phone,
                        travel_date: expBookingData.date,
                        number_of_travelers: expBookingData.participants,
                        special_requests: `Time: ${expBookingData.time}, Location: ${expBookingData.location}, Dietary: ${expBookingData.dietary}, Requests: ${expBookingData.requests}`,
                        total_amount: expBookingData.total,
                        payment_method: expBookingData.paymentMethod,
                        payment_reference: document.getElementById('refNumber')?.value || ''
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderExpStep4(data.booking_number);
                        } else {
                            alert('Error saving booking: ' + data.message);
                            btn.innerHTML = 'Complete Payment <i class="fas fa-check-circle"></i>';
                            btn.style.pointerEvents = 'auto';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Connection error.');
                        btn.innerHTML = 'Complete Payment <i class="fas fa-check-circle"></i>';
                        btn.style.pointerEvents = 'auto';
                    });
            } catch (err) {
                console.error('Booking submission error:', err);
                alert('Something went wrong while submitting your booking: ' + err.message + '. Please try again.');
                btn.innerHTML = 'Complete Payment <i class="fas fa-check-circle"></i>';
                btn.style.pointerEvents = 'auto';
            }
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('expBookingModal')) closeExpBookingModal();
        }
    </script>
    <script src="../js/main.js?v=2"></script>
    <script src="../js/auth-menu.js?v=2"></script>
</body>

</html>

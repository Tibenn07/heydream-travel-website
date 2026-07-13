<?php
// ========================================
// FILE: buttons/flight-details.php
// DESCRIPTION: Full-page detail view for a flight service (mirrors package-details.php)
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
            WHERE ss.service_type = 'flight' AND ";
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
    <title><?= $svc ? htmlspecialchars($svc['title']) . ' - HeyDream Travel' : 'Flight Not Found - HeyDream Travel' ?></title>
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

        /* ---- Booking modal (ported from flights.php so "Book Now" opens in place) ---- */
        .booking-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .booking-modal.active { display: flex; }
        .booking-modal-content { background: white; border-radius: 20px; max-width: 600px; width: 90%; max-height: 85vh; overflow-y: auto; animation: modalSlideIn 0.3s ease; }
        .booking-modal-content::-webkit-scrollbar { width: 8px; }
        .booking-modal-content::-webkit-scrollbar-track { background: transparent; }
        .booking-modal-content::-webkit-scrollbar-thumb { background: rgba(0, 53, 128, 0.2); border-radius: 10px; }
        .booking-modal-content::-webkit-scrollbar-thumb:hover { background: rgba(0, 53, 128, 0.4); }
        .booking-modal-header { background: linear-gradient(135deg, #0091ff, #1a4b8c); color: white; padding: 20px 25px; border-radius: 20px 20px 0 0; position: relative; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 1.8rem; cursor: pointer; color: white; }
        .close-modal:hover { transform: rotate(90deg); color: #ff9800; }
        .booking-modal-header h2 { font-size: 1.3rem; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .booking-modal-header p { font-size: 0.75rem; opacity: 0.8; }
        .booking-steps-nav { display: flex; justify-content: center; padding: 30px 20px; background: white; border-bottom: 1px solid #e2e8f0; }
        .step-item { flex: 1; text-align: center; position: relative; }
        .step-circle { width: 35px; height: 35px; background: #e2e8f0; color: #64748b; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: 800; font-size: 0.9rem; position: relative; z-index: 2; transition: 0.3s; }
        .step-item.active .step-circle { background: #ff9800; color: white; box-shadow: 0 0 0 5px rgba(255, 152, 0, 0.2); }
        .step-item.completed .step-circle { background: #22c55e; color: white; }
        .step-label { font-size: 0.75rem; font-weight: 700; color: #64748b; }
        .step-item.active .step-label { color: #ff9800; }
        .step-connector { position: absolute; top: 17px; left: 50%; width: 100%; height: 2px; background: #e2e8f0; z-index: 1; }
        .step-item.completed .step-connector { background: #22c55e; }
        .booking-body { background: white; padding: 20px; }
        .modal-footer { display: flex; gap: 12px; padding: 25px 30px; background: white; border-top: 1px solid #e2e8f0; justify-content: flex-end; }
        .btn-back { flex: 1; padding: 15px; border: none; background: #94a3b8; color: white; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-proceed { flex: 2; padding: 15px; border: none; background: linear-gradient(135deg, #FFC107 0%, #FF9800 100%); color: white; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 10px 20px rgba(255, 152, 0, 0.25); }
        .step-content { display: none; animation: fadeIn 0.3s ease; padding: 0 5px; }
        .step-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .booking-service-summary { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; border: 1px solid #e0e0e0; }
        .service-icon-large { width: 50px; height: 50px; background: linear-gradient(135deg, #FF6B6B, #FF8E8E); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .service-icon-large i { font-size: 1.4rem; color: white; }
        .service-info h3 { color: #0091ff; margin-bottom: 3px; font-size: 1rem; }
        .service-price { font-size: 1.2rem; font-weight: 700; color: #ff9800; }
        .service-duration { color: #666; font-size: 0.7rem; }
        .form-section { margin-bottom: 20px; }
        .form-section h4 { color: #0091ff; margin-bottom: 12px; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; border-left: 3px solid #ff9800; padding-left: 10px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.75rem; color: #333; }
        .form-group label .required { color: #ff4444; margin-left: 2px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.8rem; background: #fff; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #ff9800; box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.1); }
        .form-group input.error, .form-group select.error { border-color: #ff4444; background: #fff5f5; }
        .error-message { background: #fff5f5; border-left: 3px solid #ff4444; padding: 10px 12px; margin-bottom: 15px; border-radius: 8px; font-size: 0.75rem; color: #ff4444; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .error-list { list-style: none; margin: 0; padding-left: 20px; }
        .review-details { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .review-section { margin-bottom: 15px; }
        .review-section h4 { color: #0091ff; margin-bottom: 10px; font-size: 0.85rem; border-left: 3px solid #ff9800; padding-left: 10px; }
        .review-row { display: flex; padding: 6px 0; border-bottom: 1px solid #e9ecef; font-size: 0.8rem; }
        .review-label { width: 120px; font-weight: 600; color: #666; }
        .review-value { flex: 1; color: #333; }
        .action-buttons { display: flex; gap: 12px; justify-content: center; margin-top: 20px; }
        .submit-booking-btn { background: linear-gradient(135deg, #ff9800, #f57c00); color: white; border: none; padding: 12px 20px; border-radius: 40px; font-size: 0.9rem; font-weight: 600; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .payment-methods { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 10px; }
        .payment-method { display: flex; align-items: center; gap: 12px; padding: 12px; border: 2px solid #e0e0e0; border-radius: 12px; cursor: pointer; transition: all 0.3s ease; background: white; }
        .payment-method:hover { border-color: #ff9800; background: #fff9e6; }
        .payment-method.selected { border-color: #ff9800; background: #fff9e6; }
        .payment-method input { width: 18px; height: 18px; cursor: pointer; accent-color: #ff9800; margin: 0; flex-shrink: 0; }
        .payment-icon { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 10px; flex-shrink: 0; }
        .payment-icon i { font-size: 1.5rem; }
        .payment-details { flex: 1; }
        .payment-name { font-weight: 700; color: #0091ff; margin-bottom: 2px; font-size: 0.85rem; }
        .payment-desc { font-size: 0.65rem; color: #666; }
        .payment-details-box { display: none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 12px; }
        .payment-details-box.show { display: block; animation: fadeIn 0.3s ease; }
        .payment-instructions { background: white; border-radius: 12px; padding: 20px; }
        .qr-code { text-align: center; margin: 15px 0; padding: 15px; background: white; border-radius: 12px; border: 1px solid #e0e0e0; }
        .qr-code img { width: 180px; height: 180px; object-fit: contain; margin: 0 auto; display: block; }
        .qr-placeholder { width: 180px; height: 180px; background: linear-gradient(135deg, #f5f7fa, #e9eef5); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto; gap: 10px; }
        .qr-placeholder i { font-size: 3rem; color: #ff9800; }
        .qr-placeholder p { font-size: 0.7rem; color: #666; margin: 0; }
        .qr-code-note { font-size: 0.7rem; color: #888; margin-top: 8px; text-align: center; }
        .account-details { background: #fff9e6; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .account-details p { margin: 8px 0; font-size: 0.85rem; }
        .account-number { font-weight: bold; color: #0091ff; background: #e8f0fe; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 1rem; }
        .copy-btn { background: #e0e0e0; border: none; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; cursor: pointer; margin-left: 8px; transition: all 0.2s ease; }
        .copy-btn:hover { background: #ff9800; color: white; }
        .file-upload { border: 2px dashed #e0e0e0; border-radius: 12px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #f8f9fa; margin-top: 15px; }
        .file-upload:hover { border-color: #ff9800; background: #fff9e6; }
        .file-upload i { font-size: 1.5rem; color: #ff9800; margin-bottom: 5px; }
        .file-upload p { font-size: 0.7rem; color: #666; margin: 0; }
        .file-upload .file-name { font-size: 0.65rem; color: #0091ff; margin-top: 5px; font-weight: 500; }
        .upload-preview { margin-top: 10px; text-align: center; }
        .upload-preview img { max-width: 100%; max-height: 100px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .instruction-note { background: #e8f0fe; padding: 10px; border-radius: 8px; font-size: 0.7rem; color: #0091ff; margin-top: 10px; text-align: center; }
        .card-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .success-message { text-align: center; padding: 25px; }
        .success-message i { font-size: 2.5rem; color: #28a745; margin-bottom: 12px; }
        .booking-number { background: #e8f0fe; padding: 8px 16px; border-radius: 8px; font-size: 0.8rem; margin: 12px 0; display: inline-block; }
        .details-card { background: #f8f9fa; padding: 15px; border-radius: 12px; margin: 15px 0; text-align: left; font-size: 0.8rem; }
        .details-card p { margin-bottom: 6px; display: flex; justify-content: space-between; flex-wrap: wrap; }
        .btn-primary { background: linear-gradient(135deg, #28a745, #218838); width: auto; padding: 8px 20px; }
        .btn-secondary { background: #6c757d; width: auto; padding: 8px 20px; }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .review-row { flex-direction: column; }
            .review-label { width: 100%; margin-bottom: 3px; }
            .card-row { grid-template-columns: 1fr; }
        }
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
                    <div class="pkgdet-notfound-icon"><i class="fas fa-plane-slash"></i></div>
                    <h2>Flight Not Found</h2>
                    <p>This flight listing may have been removed or the link is incorrect.</p>
                    <div class="pkgdet-notfound-actions">
                        <a href="flights.php" class="pkgdet-notfound-btn primary"><i class="fas fa-arrow-left"></i> Back to Flights</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pkgdet-top-row">
                    <a href="../index.php" class="pkgdet-back-btn"><i class="fas fa-home"></i> Back to Home</a>
                    <div class="pkgdet-breadcrumb">
                        <a href="../index.php">Home</a> /
                        <a href="flights.php">Flights</a> /
                        <?= htmlspecialchars($svc['title']) ?>
                    </div>
                </div>

                <div class="pkgdet-title-row">
                    <div>
                        <h1><?= htmlspecialchars($svc['title']) ?></h1>
                        <div class="pkgdet-location"><i class="fas fa-clock"></i> <?= htmlspecialchars($svc['duration'] ?: 'Flexible duration') ?></div>
                    </div>
                    <?php if ($svc['status_text']): ?>
                        <div class="pkgdet-social-badge"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($svc['status_text']) ?></div>
                    <?php endif; ?>
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
                        <img src="<?= $heroImg ? htmlspecialchars(svcImg($heroImg)) : '../images/flights-hero.jpg' ?>" alt="<?= htmlspecialchars($svc['title']) ?>" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27800%27 height=%27440%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23e2e8f0%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 font-family=%27sans-serif%27 font-size=%2724%27 fill=%27%2394a3b8%27 text-anchor=%27middle%27%3ENo Photo Available%3C/text%3E%3C/svg%3E'">
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
                            <p class="pkgdet-desc"><?= nl2br(htmlspecialchars($svc['full_description'] ?: $svc['description'] ?: $svc['short_description'] ?: 'Details about this flight will be provided upon booking.')) ?></p>
                        </div>

                        <?php $highlights = svcLines($svc['highlights']); if ($highlights): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-star"></i> Highlights</h2>
                            <?php foreach ($highlights as $h): ?><div class="pkgdet-line-row inc"><span class="dot"><i class="fas fa-check"></i></span><span><?= htmlspecialchars($h) ?></span></div><?php endforeach; ?>
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
                                    <a href="../view-partner-profile.php?id=<?= intval($svc['partner_id']) ?>&from_type=flight&from_id=<?= urlencode($identifier) ?>"><?= htmlspecialchars($svc['partner_company'] ?: 'Partner Provider') ?></a>
                                    <p style="margin:2px 0 0; color:#64748b; font-size:0.82rem;">This flight is offered by one of our trusted partners.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkgdet-sticky">
                        <div class="pkgdet-price-card">
                            <span class="pkgdet-price-now"><?= htmlspecialchars($svc['currency'] ?: '₱') ?><?= number_format($svc['price']) ?></span>
                            <div class="pkgdet-price-per">per person</div>
                            <button class="pkgdet-book-btn" onclick="bookThisFlight()"><i class="fas fa-bolt"></i> Book Now</button>
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

    <script>
        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser(); echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';

        // "Book Now" opens the exact same booking wizard used on flights.php,
        // right here on the details page, instead of navigating away.
        function bookThisFlight() {
            requireLogin('showFlightBooking', <?= json_encode($svc['title'] ?? '') ?>, <?= json_encode(floatval($svc['price'] ?? 0)) ?>, <?= json_encode($svc['duration'] ?? '') ?>);
        }
    </script>
    <script>
        let currentFlight = null, flightBookingData = null, selectedPayment = null;

        function formatNumber(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function escapeHtml(t) {
            if (!t) return '';
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.style.background = '#28a745';
                btn.style.color = 'white';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '#e0e0e0';
                    btn.style.color = '';
                }, 1500);
            });
        }

        function handleFileUpload(event, paymentMethod) {
            const file = event.target.files[0];
            if (file) {
                if (!file.type.match('image.*')) {
                    alert('Please upload an image file (PNG, JPG, JPEG)');
                    event.target.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    event.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    const previewDiv = document.getElementById(`preview-${paymentMethod}`);
                    if (previewDiv) {
                        previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof">`;
                    }
                };
                reader.readAsDataURL(file);

                const fileNameSpan = document.getElementById(`file-name-${paymentMethod}`);
                if (fileNameSpan) {
                    fileNameSpan.textContent = file.name;
                }
            }
        }

        function showFlightBooking(title, price, duration) {
            currentFlight = { title, price, duration };
            let modal = document.getElementById('flightBookingModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'flightBookingModal';
                modal.className = 'booking-modal';
                modal.innerHTML = `<div class="booking-modal-content"><div class="booking-modal-header"><span class="close-modal" onclick="closeFlightBookingModal()">&times;</span><h2><i class="fas fa-plane"></i> Book Flight</h2><p>Complete your booking</p></div><div class="booking-steps-nav"><div class="step-item active" id="step1Indicator"><div class="step-circle">1</div><div class="step-label">Details</div><div class="step-connector"></div></div><div class="step-item" id="step2Indicator"><div class="step-circle">2</div><div class="step-label">Review</div><div class="step-connector"></div></div><div class="step-item" id="step3Indicator"><div class="step-circle">3</div><div class="step-label">Payment</div><div class="step-connector"></div></div><div class="step-item" id="step4Indicator"><div class="step-circle">4</div><div class="step-label">Confirm</div></div></div><div class="booking-body"><div id="step1Content" class="step-content active"></div><div id="step2Content" class="step-content"></div><div id="step3Content" class="step-content"></div><div id="step4Content" class="step-content"></div></div><div class="modal-footer" id="booking-footer"></div></div>`;
                document.body.appendChild(modal);
                modal.addEventListener('click', function (e) { if (e.target === modal) closeFlightBookingModal(); });
            }
            renderFlightStep1();
            modal.classList.add('active');
        }

        function renderFlightStep1() {
            document.getElementById('step1Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-plane"></i></div><div class="service-info"><h3>${currentFlight.title}</h3><p class="service-price">₱${formatNumber(currentFlight.price)}</p><p class="service-duration">${currentFlight.duration}</p></div></div>
                <form id="flightForm" onsubmit="return false;">
                    <div class="form-section"><h4><i class="fas fa-user"></i> Traveler Information</h4>
                        <div class="form-group"><label>Email Address <span class="required">*</span></label><input type="email" id="applicationEmail" value="${window.currentUserEmail || ''}" placeholder="Your email address"></div>
                        <div class="form-group"><label>Full Name <span class="required">*</span></label><input type="text" id="fullName" placeholder="As per passport/ID" value="${window.currentFullName || ''}"></div>
                        <div class="form-group"><label>Phone <span class="required">*</span></label><input type="tel" id="phone" placeholder="+63 912 345 6789"></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-calendar-alt"></i> Flight Details</h4>
                        <div class="form-row"><div class="form-group"><label>Departure Date <span class="required">*</span></label><input type="date" id="departureDate" min="${new Date().toISOString().split('T')[0]}"></div>
                        <div class="form-group"><label>Return Date</label><input type="date" id="returnDate"></div></div>
                        <div class="form-row"><div class="form-group"><label>From <span class="required">*</span></label><input type="text" id="fromCity" placeholder="Manila, Cebu"></div>
                        <div class="form-group"><label>To <span class="required">*</span></label><input type="text" id="toCity" placeholder="Destination"></div></div>
                        <div class="form-row"><div class="form-group"><label>Number of Travelers <span class="required">*</span></label><input type="number" id="passengers" min="1" value="1" oninput="updateFlightLiveTotal()"></div>
                        <div class="form-group"><label>Class</label><select id="flightClass" onchange="updateFlightLiveTotal()"><option value="economy">Economy</option><option value="business">Business (+₱5,000)</option><option value="first">First (+₱12,000)</option></select></div></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-info-circle"></i> Special Requests</h4>
                        <div class="form-group"><label>Meal Preference</label><select id="mealPref"><option value="regular">Regular</option><option value="vegetarian">Vegetarian</option><option value="halal">Halal</option></select></div>
                        <div class="form-group"><label>Additional Requests</label><textarea id="requests" rows="2" placeholder="Seat preference, etc."></textarea></div>
                    </div>

                    <div style="background: #f0f7ff; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #0091ff; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700; color: #1e293b;">Estimated Total:</span>
                        <span id="flightLiveTotal" style="font-size: 1.3rem; font-weight: 800; color: #ff9800;">₱${formatNumber(currentFlight.price)}</span>
                    </div>

                    <div id="step1Errors" class="error-message" style="display: none;"></div>
                    <div class="action-buttons"><button type="button" class="btn-proceed" onclick="validateAndGoToStep2()">Review Booking <i class="fas fa-arrow-right"></i></button></div>
                </form>`;
        }

        function updateFlightLiveTotal() {
            const passengers = parseInt(document.getElementById('passengers')?.value || 1);
            const flightClass = document.getElementById('flightClass')?.value;
            let upgrade = 0;
            if (flightClass === 'business') upgrade = 5000;
            else if (flightClass === 'first') upgrade = 12000;

            const total = (currentFlight.price + upgrade) * passengers;
            const liveTotalEl = document.getElementById('flightLiveTotal');
            if (liveTotalEl) {
                liveTotalEl.innerText = '₱' + formatNumber(total);
            }
        }

        function validateAndGoToStep2() {
            const errors = [];
            const email = document.getElementById('applicationEmail')?.value.trim();
            const fullName = document.getElementById('fullName')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const departureDate = document.getElementById('departureDate')?.value;
            const fromCity = document.getElementById('fromCity')?.value.trim();
            const toCity = document.getElementById('toCity')?.value.trim();
            const passengers = document.getElementById('passengers')?.value;

            if (!email) errors.push('Email address is required');
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Please enter a valid email address');
            if (!fullName) errors.push('Full Name is required');
            if (!phone) errors.push('Phone number is required');
            if (!departureDate) errors.push('Departure Date is required');
            if (!fromCity) errors.push('Departure City is required');
            if (!toCity) errors.push('Destination is required');
            if (!passengers || passengers < 1) errors.push('At least 1 traveler is required');

            document.querySelectorAll('.form-group input, .form-group select').forEach(f => f.classList.remove('error'));
            if (!email) document.getElementById('applicationEmail')?.classList.add('error');
            if (!fullName) document.getElementById('fullName')?.classList.add('error');
            if (!phone) document.getElementById('phone')?.classList.add('error');
            if (!departureDate) document.getElementById('departureDate')?.classList.add('error');
            if (!fromCity) document.getElementById('fromCity')?.classList.add('error');
            if (!toCity) document.getElementById('toCity')?.classList.add('error');
            if (!passengers || passengers < 1) document.getElementById('passengers')?.classList.add('error');

            if (errors.length > 0) {
                const errorDiv = document.getElementById('step1Errors');
                errorDiv.style.display = 'flex';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            goToFlightStep2();
        }

        function goToFlightStep2() {
            const fullName = document.getElementById('fullName')?.value;
            const email = document.getElementById('applicationEmail')?.value.trim() || window.currentUserEmail || '';
            const phone = document.getElementById('phone')?.value;
            const departureDate = document.getElementById('departureDate')?.value;
            const returnDate = document.getElementById('returnDate')?.value;
            const fromCity = document.getElementById('fromCity')?.value;
            const toCity = document.getElementById('toCity')?.value;
            const passengers = parseInt(document.getElementById('passengers')?.value) || 1;
            const flightClass = document.getElementById('flightClass')?.value;
            const mealPref = document.getElementById('mealPref')?.value;
            const requests = document.getElementById('requests')?.value;

            let upgrade = 0, classLabel = 'Economy';
            if (flightClass === 'business') { upgrade = 5000; classLabel = 'Business'; }
            else if (flightClass === 'first') { upgrade = 12000; classLabel = 'First'; }
            const pricePerPerson = currentFlight.price + upgrade;
            const total = pricePerPerson * passengers;

            flightBookingData = { fullName, email, phone, departureDate, returnDate, fromCity, toCity, passengers, classLabel, mealPref, requests, total };

            document.getElementById('step2Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-plane"></i></div><div class="service-info"><h3>${currentFlight.title}</h3><p class="service-price">₱${formatNumber(currentFlight.price)}</p></div></div>
                <div class="review-details">
                    <div class="review-section"><h4>Traveler Info</h4>
                        <div class="review-row"><div class="review-label">Name:</div><div class="review-value">${escapeHtml(fullName)}</div></div>
                        <div class="review-row"><div class="review-label">Email:</div><div class="review-value">${escapeHtml(email)}</div></div>
                        <div class="review-row"><div class="review-label">Phone:</div><div class="review-value">${escapeHtml(phone)}</div></div>
                    </div>
                    <div class="review-section"><h4>Flight Details</h4>
                        <div class="review-row"><div class="review-label">Route:</div><div class="review-value">${escapeHtml(fromCity)} → ${escapeHtml(toCity)}</div></div>
                        <div class="review-row"><div class="review-label">Departure:</div><div class="review-value">${new Date(departureDate).toLocaleDateString()}</div></div>
                        ${returnDate ? `<div class="review-row"><div class="review-label">Return:</div><div class="review-value">${new Date(returnDate).toLocaleDateString()}</div></div>` : ''}
                        <div class="review-row"><div class="review-label">Travelers:</div><div class="review-value">${passengers}</div></div>
                        <div class="review-row"><div class="review-label">Class:</div><div class="review-value">${classLabel}</div></div>
                    </div>
                    <div class="review-section"><h4>Price Summary</h4>
                        <div class="review-row"><div class="review-label">Base Price:</div><div class="review-value">₱${formatNumber(currentFlight.price)}</div></div>
                        ${upgrade > 0 ? `<div class="review-row"><div class="review-label">Upgrade:</div><div class="review-value">+₱${formatNumber(upgrade)}</div></div>` : ''}
                        <div class="review-row total"><div class="review-label">Total:</div><div class="review-value" style="color:#ff9800;">₱${formatNumber(total)}</div></div>
                    </div>
                </div>
                <div class="action-buttons"><button type="button" class="btn-back" onclick="goToFlightStep1()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-proceed" onclick="goToFlightStep3()">Proceed to Payment <i class="fas fa-credit-card"></i></button></div>`;
            updateFlightSteps(2);
        }

        function goToFlightStep1() {
            updateFlightSteps(1);
            setTimeout(() => {
                if (flightBookingData) {
                    if (document.getElementById('fullName')) document.getElementById('fullName').value = flightBookingData.fullName || '';
                    if (document.getElementById('applicationEmail')) document.getElementById('applicationEmail').value = flightBookingData.email || '';
                    if (document.getElementById('phone')) document.getElementById('phone').value = flightBookingData.phone || '';
                    if (document.getElementById('departureDate')) document.getElementById('departureDate').value = flightBookingData.departureDate || '';
                    if (document.getElementById('fromCity')) document.getElementById('fromCity').value = flightBookingData.fromCity || '';
                    if (document.getElementById('toCity')) document.getElementById('toCity').value = flightBookingData.toCity || '';
                }
            }, 50);
        }

        function goToFlightStep3() {
            document.getElementById('step3Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-plane"></i></div><div class="service-info"><h3>${currentFlight.title}</h3><p class="service-price">₱${formatNumber(currentFlight.price)}</p></div></div>
                <div class="form-section"><h4><i class="fas fa-credit-card"></i> Select Payment Method</h4>
                    <div class="payment-methods">
                        <div class="payment-method" onclick="selectPaymentMethod('gcash')">
                            <input type="radio" name="payment" value="gcash" id="gcashRadio">
                            <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                            <div class="payment-details">
                                <div class="payment-name">GCash</div>
                                <div class="payment-desc">Scan QR code to pay</div>
                            </div>
                        </div>
                        <div class="payment-method" onclick="selectPaymentMethod('paymaya')">
                            <input type="radio" name="payment" value="paymaya" id="paymayaRadio">
                            <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                            <div class="payment-details">
                                <div class="payment-name">PayMaya</div>
                                <div class="payment-desc">Scan QR code to pay</div>
                            </div>
                        </div>
                        <div class="payment-method disabled" onclick="alert('Credit/Debit Card payment is coming soon! Please use other payment methods for now.')" style="opacity: 0.6; cursor: not-allowed; filter: grayscale(0.5);">
                            <input type="radio" name="payment" value="card" id="cardRadio" disabled>
                            <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                            <div class="payment-details">
                                <div class="payment-name">Credit / Debit Card <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div>
                                <div class="payment-desc">Coming Soon</div>
                            </div>
                        </div>
                        <div class="payment-method" onclick="selectPaymentMethod('bank')">
                            <input type="radio" name="payment" value="bank" id="bankRadio">
                            <div class="payment-icon"><i class="fas fa-university"></i></div>
                            <div class="payment-details">
                                <div class="payment-name">Bank Transfer</div>
                                <div class="payment-desc">BPI, BDO, Metrobank</div>
                            </div>
                        </div>
                    </div>

                    <div id="gcashDetails" class="payment-details-box">
                        <div class="payment-instructions">
                            <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>GCash Payment</h4></div>
                            <div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>GCash QR Code</p><p>0945 776 4140</p></div><div class="qr-code-note">Scan QR code with GCash app</div></div>
                            <div class="account-details"><p><strong>GCash Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboard('0945 776 4140')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(flightBookingData.total)}</span></p></div>
                            <div class="form-group"><label>Reference Number *</label><input type="text" id="paymentRefGcash" placeholder="Enter GCash reference number"></div>
                            <div class="file-upload" onclick="document.getElementById('proofGcash').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-gcash">No file selected</p><div id="preview-gcash" class="upload-preview"></div><input type="file" id="proofGcash" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'gcash')"></div>
                            <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div>
                        </div>
                    </div>

                    <div id="paymayaDetails" class="payment-details-box">
                        <div class="payment-instructions">
                            <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>PayMaya Payment</h4></div>
                            <div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>PayMaya QR Code</p><p>0945 776 4140</p></div><div class="qr-code-note">Scan QR code with PayMaya app</div></div>
                            <div class="account-details"><p><strong>PayMaya Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboard('0945 776 4140')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(flightBookingData.total)}</span></p></div>
                            <div class="form-group"><label>Reference Number *</label><input type="text" id="paymentRefPaymaya" placeholder="Enter PayMaya reference number"></div>
                            <div class="file-upload" onclick="document.getElementById('proofPaymaya').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-paymaya">No file selected</p><div id="preview-paymaya" class="upload-preview"></div><input type="file" id="proofPaymaya" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'paymaya')"></div>
                            <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div>
                        </div>
                    </div>

                    <div id="cardDetails" class="payment-details-box">
                        <div class="payment-instructions">
                            <div class="instruction-header"><i class="fas fa-credit-card"></i><h4>Card Payment</h4></div>
                            <div class="form-group"><label>Card Number *</label><input type="text" id="cardNumber" placeholder="1234 5678 9012 3456"></div>
                            <div class="card-row"><div class="form-group"><label>Expiry *</label><input type="text" id="expiryDate" placeholder="MM/YY"></div><div class="form-group"><label>CVV *</label><input type="text" id="cvv" placeholder="123"></div></div>
                            <div class="form-group"><label>Cardholder Name *</label><input type="text" id="cardName" placeholder="Name on card"></div>
                        </div>
                    </div>

                    <div id="bankDetails" class="payment-details-box">
                        <div class="payment-instructions">
                            <div class="instruction-header"><i class="fas fa-university"></i><h4>Bank Transfer</h4></div>
                            <div class="account-details"><p><strong>BPI:</strong> 1234 5678 90 <button class="copy-btn" onclick="copyToClipboard('1234 5678 90')">Copy</button></p><p><strong>BDO:</strong> 5678 1234 56 <button class="copy-btn" onclick="copyToClipboard('5678 1234 56')">Copy</button></p><p><strong>Metrobank:</strong> 9012 3456 78 <button class="copy-btn" onclick="copyToClipboard('9012 3456 78')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(flightBookingData.total)}</span></p></div>
                            <div class="form-group"><label>Reference Number *</label><input type="text" id="bankRef" placeholder="Enter bank reference number"></div>
                            <div class="file-upload" onclick="document.getElementById('proofBank').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-bank">No file selected</p><div id="preview-bank" class="upload-preview"></div><input type="file" id="proofBank" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'bank')"></div>
                            <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of bank transfer confirmation</div>
                        </div>
                    </div>
                </div>
                <div id="step3Errors" class="error-message" style="display: none;"></div>
                <div class="action-buttons"><button type="button" class="btn-back" onclick="goToFlightStep2()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-proceed" onclick="validateAndGoToStep4()">Complete Payment <i class="fas fa-check-circle"></i></button></div>`;
            updateFlightSteps(3);
        }

        function selectPaymentMethod(method) {
            selectedPayment = method;
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            document.querySelectorAll('input[name="payment"]').forEach(radio => radio.checked = false);
            document.getElementById(`${method}Radio`).checked = true;

            document.getElementById('gcashDetails').classList.remove('show');
            document.getElementById('paymayaDetails').classList.remove('show');
            document.getElementById('cardDetails').classList.remove('show');
            document.getElementById('bankDetails').classList.remove('show');

            if (method === 'gcash') document.getElementById('gcashDetails').classList.add('show');
            else if (method === 'paymaya') document.getElementById('paymayaDetails').classList.add('show');
            else if (method === 'card') document.getElementById('cardDetails').classList.add('show');
            else if (method === 'bank') document.getElementById('bankDetails').classList.add('show');
        }

        function validateAndGoToStep4() {
            const errors = [];
            if (!selectedPayment) errors.push('Please select a payment method');

            if (selectedPayment === 'gcash') {
                const ref = document.getElementById('paymentRefGcash')?.value.trim();
                const file = document.getElementById('proofGcash')?.files[0];
                if (!ref) errors.push('Please enter the GCash reference number');
                if (!file) errors.push('Please upload proof of payment');
            }
            if (selectedPayment === 'paymaya') {
                const ref = document.getElementById('paymentRefPaymaya')?.value.trim();
                const file = document.getElementById('proofPaymaya')?.files[0];
                if (!ref) errors.push('Please enter the PayMaya reference number');
                if (!file) errors.push('Please upload proof of payment');
            }
            if (selectedPayment === 'card') {
                if (!document.getElementById('cardNumber')?.value.trim()) errors.push('Card Number is required');
                if (!document.getElementById('expiryDate')?.value.trim()) errors.push('Expiry Date is required');
                if (!document.getElementById('cvv')?.value.trim()) errors.push('CVV is required');
                if (!document.getElementById('cardName')?.value.trim()) errors.push('Cardholder Name is required');
            }
            if (selectedPayment === 'bank') {
                const ref = document.getElementById('bankRef')?.value.trim();
                const file = document.getElementById('proofBank')?.files[0];
                if (!ref) errors.push('Reference Number is required');
                if (!file) errors.push('Please upload proof of payment');
            }

            if (errors.length > 0) {
                const errorDiv = document.getElementById('step3Errors');
                errorDiv.style.display = 'flex';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            let paymentMethodName = '';
            if (selectedPayment === 'gcash') paymentMethodName = 'GCash';
            else if (selectedPayment === 'paymaya') paymentMethodName = 'PayMaya';
            else if (selectedPayment === 'card') paymentMethodName = 'Credit/Debit Card';
            else if (selectedPayment === 'bank') paymentMethodName = 'Bank Transfer';

            flightBookingData.paymentMethod = paymentMethodName;
            goToFlightStep4();
        }

        function goToFlightStep4() {
            if (!currentFlight || !flightBookingData) {
                alert('Your booking session has expired or was reset. Please close this window and start the booking again.');
                return;
            }

            try {
                // Save to server
                fetch('../api/save-service-booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_type: 'Flight Booking',
                        package_name: currentFlight.title,
                        package_duration: currentFlight.duration,
                        price_per_person: currentFlight.price,
                        full_name: flightBookingData.fullName,
                        email: flightBookingData.email,
                        phone: flightBookingData.phone,
                        travel_date: flightBookingData.departureDate,
                        number_of_travelers: flightBookingData.passengers,
                        special_requests: `Route: ${flightBookingData.fromCity} -> ${flightBookingData.toCity}, Return: ${flightBookingData.returnDate}, Class: ${flightBookingData.classLabel}, Meal: ${flightBookingData.mealPref}, Requests: ${flightBookingData.requests}`,
                        total_amount: flightBookingData.total,
                        payment_method: selectedPayment,
                        payment_reference: document.getElementById(`paymentRef${selectedPayment.charAt(0).toUpperCase() + selectedPayment.slice(1)}`)?.value || ''
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const bookingNumber = data.booking_number;
                            document.getElementById('step4Content').innerHTML = `<div class="success-message"><i class="fas fa-check-circle"></i><h2>✈️ Booking Confirmed!</h2><p>Your flight booking has been confirmed and saved.</p><div class="booking-number">Ticket Reference: ${bookingNumber}</div><div class="details-card"><h4>📋 Ticket Details:</h4><p><strong>Route:</strong> ${currentFlight.title}</p><p><strong>Departure:</strong> ${new Date(flightBookingData.departureDate).toLocaleDateString()}</p><p><strong>Travelers:</strong> ${flightBookingData.passengers}</p><p><strong>Class:</strong> ${flightBookingData.classLabel}</p><p><strong>Total Fare:</strong> <span style="color:#ff9800;">₱${formatNumber(flightBookingData.total)}</span></p><p><strong>Payment Status:</strong> <span style="color:#28a745;">Confirmed</span></p><p><strong>Booked By:</strong> ${escapeHtml(flightBookingData.fullName)}</p></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Your e-ticket will be sent to your email (${flightBookingData.email}) within 2 hours.</div><div class="action-buttons"><button class="submit-booking-btn btn-primary" onclick="window.location.href='../User Account/profile.php?track=' + encodeURIComponent('${bookingNumber}')"><i class="fas fa-file-upload"></i> View My Booking</button><button class="submit-booking-btn btn-secondary" onclick="closeFlightBookingModal(); location.reload();"><i class="fas fa-plus"></i> Book Another Flight</button><button class="submit-booking-btn btn-secondary" onclick="closeFlightBookingModal()"><i class="fas fa-times"></i> Close</button></div></div>`;
                            updateFlightSteps(4);
                        } else {
                            alert('Error saving booking: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Connection error. Please try again.');
                    });
            } catch (err) {
                console.error('Booking submission error:', err);
                alert('Something went wrong while submitting your booking: ' + err.message + '. Please try again.');
            }
        }

        function updateFlightSteps(step) {
            for (let i = 1; i <= 4; i++) {
                const ind = document.getElementById(`step${i}Indicator`), cont = document.getElementById(`step${i}Content`);
                if (i < step) { ind.classList.add('completed'); ind.classList.remove('active'); }
                else if (i === step) { ind.classList.add('active'); ind.classList.remove('completed'); }
                else { ind.classList.remove('active', 'completed'); }
                if (i === step) cont.classList.add('active'); else cont.classList.remove('active');
            }
        }

        function closeFlightBookingModal() {
            const modal = document.getElementById('flightBookingModal');
            if (modal) modal.classList.remove('active');
            flightBookingData = null;
            selectedPayment = null;
        }
    </script>
    <script src="../js/main.js?v=2"></script>
    <script src="../js/auth-menu.js?v=2"></script>
</body>

</html>

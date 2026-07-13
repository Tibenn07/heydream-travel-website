<?php
// ========================================
// FILE: buttons/cruise-details.php
// DESCRIPTION: Full-page detail view for a cruise (mirrors package-details.php)
// ========================================
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$identifier = isset($_GET['id']) ? $_GET['id'] : '';
$isNumeric = is_numeric($identifier);

$cruise = null;

try {
    $sql = "SELECT c.*, COALESCE(pr.business_display_name, p.company_name, c.partner_company) AS partner_company
            FROM cruises c
            LEFT JOIN partner_applications p ON c.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = c.partner_id
            WHERE ";
    if ($isNumeric) {
        $stmt = $pdo->prepare($sql . "c.id = :id");
        $stmt->execute(['id' => intval($identifier)]);
    } else {
        $stmt = $pdo->prepare($sql . "(c.title = :name OR REPLACE(LOWER(c.title), ' ', '_') = :name OR c.title LIKE :name_like)");
        $stmt->execute(['name' => $identifier, 'name_like' => '%' . $identifier . '%']);
    }
    $cruise = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cruise) {
        $itStmt = $pdo->prepare("SELECT * FROM cruise_itinerary WHERE cruise_id = ? ORDER BY day_number");
        $itStmt->execute([$cruise['id']]);
        $cruise['itinerary_rows'] = $itStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $cruise = null;
}

// Splits a free-text block (one item per line) into a clean array.
function crLines($text)
{
    if (!$text) return [];
    return array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== ''));
}

// Some cruise fields (amenities, room_types) are comma-separated instead of newline-separated.
function crCsv($text)
{
    if (!$text) return [];
    return array_values(array_filter(array_map('trim', explode(',', $text)), fn($l) => $l !== ''));
}

function crImg($path)
{
    if (!$path) return '';
    if (preg_match('#^(https?:)?//#', $path) || strpos($path, 'data:') === 0) return $path;
    return '../' . $path;
}

$price = $cruise ? (floatval($cruise['promo_price']) > 0 ? floatval($cruise['promo_price']) : floatval($cruise['base_price'])) : 0;
$hasPromo = $cruise && floatval($cruise['promo_price']) > 0 && floatval($cruise['promo_price']) < floatval($cruise['base_price']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $cruise ? htmlspecialchars($cruise['title']) . ' - HeyDream Travel' : 'Cruise Not Found - HeyDream Travel' ?></title>
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

        .pkgdet-gallery { display: grid; gap: 8px; border-radius: 16px; overflow: hidden; margin-bottom: 28px; height: 440px; }
        .pkgdet-gallery.count-1 { grid-template-columns: 1fr; }
        .pkgdet-gallery.count-2 { grid-template-columns: 1fr 1fr; }
        .pkgdet-gallery.count-multi { grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; }
        .pkgdet-gallery.count-multi .pkgdet-gtile:first-child { grid-row: 1 / 3; }
        .pkgdet-gtile { position: relative; cursor: pointer; overflow: hidden; background: #e2e8f0; }
        .pkgdet-gtile img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.35s ease; }
        .pkgdet-gtile:hover img { transform: scale(1.05); }
        .pkgdet-gtile .pkgdet-more-overlay { position: absolute; inset: 0; background: rgba(15,23,42,0.6); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 800; }

        @media (max-width: 768px) {
            .pkgdet-gallery { height: 280px; }
            .pkgdet-gallery.count-multi { grid-template-columns: 1fr; grid-template-rows: none; height: auto; }
            .pkgdet-gallery.count-multi .pkgdet-gtile { height: 220px; }
            .pkgdet-gallery.count-multi .pkgdet-gtile:first-child { grid-row: auto; height: 260px; }
            .pkgdet-gallery.count-multi .pkgdet-gtile:not(:first-child):nth-child(n+4) { display: none; }
        }

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

        .pkgdet-ship-stat { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; }
        .pkgdet-ship-stat:last-child { border-bottom: none; }
        .pkgdet-ship-stat span:first-child { color: #64748b; }
        .pkgdet-ship-stat span:last-child { font-weight: 700; color: #0f172a; }

        .pkgdet-sticky { position: sticky; top: 20px; }
        .pkgdet-price-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; }
        .pkgdet-price-orig { color: #94a3b8; text-decoration: line-through; font-size: 0.95rem; margin-right: 8px; }
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

        /* ---- Lightbox ---- */
        .pkgdet-lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 5000; align-items: center; justify-content: center; }
        .pkgdet-lightbox.active { display: flex; }
        .pkgdet-lightbox img { max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: 8px; }
        .pkgdet-lightbox-close, .pkgdet-lightbox-prev, .pkgdet-lightbox-next { position: absolute; background: rgba(255,255,255,0.15); color: #fff; border: none; width: 46px; height: 46px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .pkgdet-lightbox-close { top: 20px; right: 20px; }
        .pkgdet-lightbox-prev { left: 20px; top: 50%; transform: translateY(-50%); }
        .pkgdet-lightbox-next { right: 20px; top: 50%; transform: translateY(-50%); }
        .pkgdet-lightbox-counter { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); color: #fff; font-size: 0.85rem; background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 20px; }

        /* ---- Booking modal (ported from cruises.php so "Book This Cruise" opens in place) ---- */
        .booking-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .booking-modal.active { display: flex; }
        .booking-modal-content { background: #f8fafc; border-radius: 30px; max-width: 650px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3); animation: modalPopUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes modalPopUp { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .booking-modal-header { background: #008080; color: white; padding: 30px; border-radius: 30px 30px 0 0; position: relative; }
        .booking-modal-header h2 { margin: 0; font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; gap: 12px; color: white; }
        .booking-modal-header p { margin: 5px 0 0; font-size: 0.9rem; opacity: 0.9; color: white; }
        .close-booking { position: absolute; top: 25px; right: 25px; font-size: 24px; cursor: pointer; width: 35px; height: 35px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.3s; color: white; }
        .close-booking:hover { background: rgba(255, 255, 255, 0.2); transform: rotate(90deg); }
        .booking-steps-nav { display: flex; justify-content: center; padding: 30px 20px; background: white; border-bottom: 1px solid #e2e8f0; }
        .step-item { flex: 1; text-align: center; position: relative; }
        .step-circle { width: 35px; height: 35px; background: #e2e8f0; color: #64748b; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: 800; font-size: 0.9rem; z-index: 2; position: relative; transition: 0.3s; }
        .step-item.active .step-circle { background: #008080; color: white; box-shadow: 0 0 0 5px rgba(0, 128, 128, 0.2); }
        .step-item.completed .step-circle { background: #22c55e; color: white; }
        .step-label { font-size: 0.75rem; font-weight: 700; color: #64748b; }
        .step-item.active .step-label { color: #008080; }
        .step-connector { position: absolute; top: 17px; left: 50%; width: 100%; height: 2px; background: #e2e8f0; z-index: 1; }
        .step-item.completed .step-connector { background: #22c55e; }
        .booking-body { padding: 30px; }
        .step-content { display: none; animation: fadeIn 0.3s ease; }
        .step-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .service-mini-card { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .mini-card-icon { width: 60px; height: 60px; background: #f0fbfb; color: #008080; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .mini-card-info h4 { margin: 0 0 5px; color: #008080; font-size: 1.1rem; font-weight: 800; }
        .mini-card-info .mini-price { font-size: 1.3rem; font-weight: 900; color: #008080; }
        .section-header { display: flex; align-items: center; gap: 10px; margin: 25px 0 20px; padding-left: 10px; border-left: 4px solid #008080; color: #008080; font-weight: 800; font-size: 1rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #1e293b; }
        .input-group label .required { color: #ff4444; margin-left: 2px; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 0.95rem; transition: 0.3s; background: white; color: #1e293b; }
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus { border-color: #008080; outline: none; box-shadow: 0 0 0 4px rgba(0, 128, 128, 0.1); }
        .input-group input.error, .input-group select.error { border-color: #ff4444; background: #fff5f5; }
        .error-message { background: #fff5f5; border-left: 3px solid #ff4444; padding: 10px 12px; margin-bottom: 15px; border-radius: 8px; font-size: 0.75rem; color: #ff4444; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .error-list { list-style: none; margin: 0; padding-left: 20px; }
        .summary-table { background: white; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; margin-bottom: 25px; }
        .summary-row { display: flex; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        .summary-row:last-child { border-bottom: none; }
        .summary-label { font-weight: 600; color: #64748b; }
        .summary-value { font-weight: 800; color: #1e293b; text-align: right; }
        .modal-footer { display: flex; gap: 15px; padding: 25px 30px; background: white; border-top: 1px solid #e2e8f0; border-radius: 0 0 30px 30px; margin: 20px -30px -30px; }
        .btn-back { flex: 1; padding: 15px; border: none; background: #94a3b8; color: white; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 1rem; transition: 0.3s; }
        .btn-back:hover { background: #64748b; transform: translateY(-2px); }
        .btn-proceed { flex: 2; padding: 15px; border: none; background: linear-gradient(135deg, #00B894, #008080); color: white; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 10px 20px rgba(0, 128, 128, 0.2); font-size: 1rem; transition: 0.3s; }
        .btn-proceed:hover { background: #008080; transform: translateY(-2px); box-shadow: 0 12px 24px rgba(0, 128, 128, 0.3); }
        .payment-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 25px; }
        .pay-option { background: white; border: 2px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; align-items: center; gap: 15px; cursor: pointer; transition: all 0.3s ease; }
        .pay-option:hover { border-color: #008080; background: #f0fbfb; }
        .pay-option.selected { border-color: #008080; background: #f0fbfb; box-shadow: 0 10px 20px rgba(0, 128, 128, 0.1); }
        .pay-radio { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 50%; position: relative; flex-shrink: 0; transition: 0.3s; }
        .pay-option.selected .pay-radio { border-color: #008080; background: #008080; }
        .pay-option.selected .pay-radio::after { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 8px; height: 8px; background: white; border-radius: 50%; }
        .pay-icon { font-size: 1.5rem; color: #008080; }
        .pay-info { display: flex; flex-direction: column; text-align: left; }
        .pay-name { font-weight: 800; color: #1e293b; font-size: 0.95rem; }
        .pay-desc { font-size: 0.75rem; color: #64748b; }
        .qr-code { text-align: center; margin: 15px 0; padding: 15px; background: white; border-radius: 12px; border: 1px solid #e0e0e0; }
        .qr-placeholder { width: 180px; height: 180px; background: linear-gradient(135deg, #f5f7fa, #e9eef5); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto; gap: 10px; }
        .qr-placeholder i { font-size: 3rem; color: #008080; }
        .account-details { background: #f0fbfb; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .account-details p { margin: 8px 0; font-size: 0.85rem; }
        .account-number { font-weight: bold; color: #008080; background: #e6f7f7; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 1rem; }
        .copy-btn { background: #e0e0e0; border: none; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; cursor: pointer; margin-left: 8px; transition: all 0.2s ease; }
        .copy-btn:hover { background: #008080; color: white; }
        .success-message { text-align: center; padding: 25px; }
        .success-message i { font-size: 2.5rem; color: #28a745; margin-bottom: 12px; }
        .booking-number { background: #e8f0fe; padding: 8px 16px; border-radius: 8px; font-size: 0.8rem; margin: 12px 0; display: inline-block; }
        .details-card { background: #f8f9fa; padding: 15px; border-radius: 12px; margin: 15px 0; text-align: left; font-size: 0.8rem; }
        .details-card p { margin-bottom: 6px; display: flex; justify-content: space-between; flex-wrap: wrap; }
        .payment-status-pending { background: #fff3cd; color: #856404; padding: 10px; border-radius: 8px; text-align: center; font-size: 0.8rem; margin-top: 10px; }
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
            <?php if (!$cruise): ?>
                <div class="pkgdet-notfound">
                    <div class="pkgdet-notfound-icon"><i class="fas fa-ship"></i></div>
                    <h2>Cruise Not Found</h2>
                    <p>This cruise listing may have been removed or the link is incorrect.</p>
                    <div class="pkgdet-notfound-actions">
                        <a href="cruises.php" class="pkgdet-notfound-btn primary"><i class="fas fa-arrow-left"></i> Back to Cruises</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pkgdet-top-row">
                    <a href="../index.php" class="pkgdet-back-btn"><i class="fas fa-home"></i> Back to Home</a>
                    <div class="pkgdet-breadcrumb">
                        <a href="../index.php">Home</a> /
                        <a href="cruises.php">Cruises</a> /
                        <?= htmlspecialchars($cruise['title']) ?>
                    </div>
                </div>

                <div class="pkgdet-title-row">
                    <div>
                        <h1><?= htmlspecialchars($cruise['title']) ?></h1>
                        <div class="pkgdet-location"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($cruise['departure_port'] ?: 'Port TBA') ?> &nbsp;·&nbsp; <i class="fas fa-clock"></i> <?= htmlspecialchars($cruise['duration'] ?: 'Flexible') ?></div>
                    </div>
                    <div class="pkgdet-social-badge"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($cruise['status'] ?: 'Available') ?></div>
                </div>

                <?php
                $galleryImgs = [];
                $decodedGallery = json_decode($cruise['gallery'] ?? '', true);
                if (is_array($decodedGallery)) { foreach ($decodedGallery as $g) if ($g) $galleryImgs[] = $g; }
                if ($cruise['featured_image'] && !in_array($cruise['featured_image'], $galleryImgs, true)) {
                    array_unshift($galleryImgs, $cruise['featured_image']);
                }
                $imgCount = count($galleryImgs);
                $galleryClass = $imgCount <= 1 ? 'count-1' : ($imgCount === 2 ? 'count-2' : 'count-multi');
                $visibleImages = $imgCount > 4 ? array_slice($galleryImgs, 0, 4) : $galleryImgs;
                ?>
                <div class="pkgdet-gallery <?= $galleryClass ?>" id="crGallery">
                    <?php if ($imgCount === 0): ?>
                        <div class="pkgdet-gtile">
                            <img src="../images/placeholder-cruise.jpg" alt="<?= htmlspecialchars($cruise['title']) ?>" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27800%27 height=%27440%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23e2e8f0%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 font-family=%27sans-serif%27 font-size=%2724%27 fill=%27%2394a3b8%27 text-anchor=%27middle%27%3ENo Photo Available%3C/text%3E%3C/svg%3E'">
                        </div>
                    <?php else: foreach ($visibleImages as $i => $img): ?>
                        <div class="pkgdet-gtile" onclick="openLightbox(<?= $i ?>)">
                            <img src="<?= htmlspecialchars(crImg($img)) ?>" alt="<?= htmlspecialchars($cruise['title']) ?> photo <?= $i + 1 ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                            <?php if ($i === 3 && $imgCount > 4): ?><div class="pkgdet-more-overlay">+<?= $imgCount - 4 ?> Photos</div><?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="pkgdet-body">
                    <div class="pkgdet-main">
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-info-circle"></i> Overview</h2>
                            <div class="pkgdet-chips">
                                <div class="pkgdet-chip"><i class="fas fa-clock"></i> <?= htmlspecialchars($cruise['duration'] ?: 'Flexible') ?></div>
                                <?php if ($cruise['category']): ?><div class="pkgdet-chip"><i class="fas fa-tag"></i> <?= htmlspecialchars($cruise['category']) ?></div><?php endif; ?>
                                <?php if ($cruise['destination_type']): ?><div class="pkgdet-chip"><i class="fas fa-globe-asia"></i> <?= htmlspecialchars($cruise['destination_type']) ?></div><?php endif; ?>
                                <?php if ($cruise['available_slots'] > 0): ?><div class="pkgdet-chip"><i class="fas fa-user-friends"></i> <?= intval($cruise['available_slots']) ?> slots left</div><?php endif; ?>
                                <?php if ($cruise['rating'] > 0): ?><div class="pkgdet-chip"><i class="fas fa-star"></i> <?= number_format($cruise['rating'], 1) ?> (<?= intval($cruise['feedback_count']) ?> reviews)</div><?php endif; ?>
                            </div>
                            <p class="pkgdet-desc"><?= nl2br(htmlspecialchars($cruise['full_description'] ?: $cruise['short_description'] ?: 'Details about this cruise will be provided upon booking.')) ?></p>
                        </div>

                        <?php $highlights = crLines($cruise['highlights']); if ($highlights): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-star"></i> Highlights</h2>
                            <?php foreach ($highlights as $h): ?><div class="pkgdet-line-row inc"><span class="dot"><i class="fas fa-check"></i></span><span><?= htmlspecialchars($h) ?></span></div><?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php $itinerary = $cruise['itinerary_rows']; if ($itinerary): ?>
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

                        <?php if ($cruise['ship_name'] || $cruise['cruise_line'] || $cruise['ship_description']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-ship"></i> The Vessel</h2>
                            <?php if ($cruise['ship_description']): ?><p class="pkgdet-desc" style="margin-bottom:14px;"><?= nl2br(htmlspecialchars($cruise['ship_description'])) ?></p><?php endif; ?>
                            <?php if ($cruise['ship_name']): ?><div class="pkgdet-ship-stat"><span>Ship Name</span><span><?= htmlspecialchars($cruise['ship_name']) ?></span></div><?php endif; ?>
                            <?php if ($cruise['cruise_line']): ?><div class="pkgdet-ship-stat"><span>Cruise Line</span><span><?= htmlspecialchars($cruise['cruise_line']) ?></span></div><?php endif; ?>
                            <?php if ($cruise['destinations']): ?><div class="pkgdet-ship-stat"><span>Destinations</span><span><?= htmlspecialchars($cruise['destinations']) ?></span></div><?php endif; ?>
                            <?php if ($cruise['route']): ?><div class="pkgdet-ship-stat"><span>Route</span><span><?= htmlspecialchars($cruise['route']) ?></span></div><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php $roomTypes = crCsv($cruise['room_types']); $amenities = crCsv($cruise['amenities']); if ($roomTypes || $amenities): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-bed"></i> Rooms &amp; Amenities</h2>
                            <?php if ($roomTypes): ?>
                                <h4>Room Types</h4>
                                <div class="pkgdet-chips">
                                    <?php foreach ($roomTypes as $r): ?><div class="pkgdet-chip"><i class="fas fa-door-open"></i> <?= htmlspecialchars($r) ?></div><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($amenities): ?>
                                <h4>Amenities</h4>
                                <div class="pkgdet-chips">
                                    <?php foreach ($amenities as $a): ?><div class="pkgdet-chip"><i class="fas fa-concierge-bell"></i> <?= htmlspecialchars($a) ?></div><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php $inclusions = crLines($cruise['inclusions']); $exclusions = crLines($cruise['exclusions']); if ($inclusions || $exclusions): ?>
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

                        <?php if ($cruise['required_documents'] || $cruise['travel_requirements'] || $cruise['health_requirements'] || $cruise['cancellation_policy'] || $cruise['refund_policy'] || $cruise['terms_conditions']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-exclamation-circle"></i> Requirements &amp; Policies</h2>
                            <?php if ($cruise['required_documents']): ?><h4>Required Documents</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($cruise['required_documents'])) ?></p><?php endif; ?>
                            <?php if ($cruise['travel_requirements']): ?><h4>Travel Requirements</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($cruise['travel_requirements'])) ?></p><?php endif; ?>
                            <?php if ($cruise['health_requirements']): ?><h4>Health Requirements</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($cruise['health_requirements'])) ?></p><?php endif; ?>
                            <?php if ($cruise['cancellation_policy']): ?><h4>Cancellation Policy</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($cruise['cancellation_policy'])) ?></p><?php endif; ?>
                            <?php if ($cruise['refund_policy']): ?><h4>Refund Policy</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($cruise['refund_policy'])) ?></p><?php endif; ?>
                            <?php if ($cruise['terms_conditions']): ?><h4>Terms &amp; Conditions</h4><p class="pkgdet-desc"><?= nl2br(htmlspecialchars($cruise['terms_conditions'])) ?></p><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($cruise['partner_id']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-handshake"></i> Provided By</h2>
                            <div class="pkgdet-partner-box">
                                <div class="icon"><i class="fas fa-store"></i></div>
                                <div>
                                    <a href="../view-partner-profile.php?id=<?= intval($cruise['partner_id']) ?>&from_type=cruise&from_id=<?= urlencode($identifier) ?>"><?= htmlspecialchars($cruise['partner_company'] ?: 'Partner Provider') ?></a>
                                    <p style="margin:2px 0 0; color:#64748b; font-size:0.82rem;">This cruise is offered by one of our trusted partners.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkgdet-sticky">
                        <div class="pkgdet-price-card">
                            <?php if ($hasPromo): ?><span class="pkgdet-price-orig">₱<?= number_format($cruise['base_price']) ?></span><?php endif; ?>
                            <span class="pkgdet-price-now">₱<?= number_format($price) ?></span>
                            <div class="pkgdet-price-per">per person</div>
                            <button class="pkgdet-book-btn" onclick="bookThisCruise()"><i class="fas fa-bolt"></i> Book This Cruise</button>
                            <div class="pkgdet-price-meta">
                                <div><span>Duration</span><strong><?= htmlspecialchars($cruise['duration'] ?: 'Flexible') ?></strong></div>
                                <div><span>Status</span><strong><?= htmlspecialchars($cruise['status'] ?: 'Available') ?></strong></div>
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

    <!-- Lightbox -->
    <div class="pkgdet-lightbox" id="pkgdetLightbox">
        <button class="pkgdet-lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
        <button class="pkgdet-lightbox-prev" onclick="shiftLightbox(-1)"><i class="fas fa-chevron-left"></i></button>
        <img id="pkgdetLightboxImg" src="" alt="Photo">
        <button class="pkgdet-lightbox-next" onclick="shiftLightbox(1)"><i class="fas fa-chevron-right"></i></button>
        <div class="pkgdet-lightbox-counter" id="pkgdetLightboxCounter"></div>
    </div>

    <script>
        const CR_IMAGES = <?= json_encode(array_map('crImg', $galleryImgs ?? [])) ?>;
        let crLightboxIndex = 0;
        function openLightbox(index) {
            if (!CR_IMAGES.length) return;
            crLightboxIndex = index;
            renderLightbox();
            document.getElementById('pkgdetLightbox').classList.add('active');
        }
        function closeLightbox() { document.getElementById('pkgdetLightbox').classList.remove('active'); }
        function shiftLightbox(dir) {
            crLightboxIndex = (crLightboxIndex + dir + CR_IMAGES.length) % CR_IMAGES.length;
            renderLightbox();
        }
        function renderLightbox() {
            document.getElementById('pkgdetLightboxImg').src = CR_IMAGES[crLightboxIndex];
            document.getElementById('pkgdetLightboxCounter').innerText = (crLightboxIndex + 1) + ' / ' + CR_IMAGES.length;
        }
        document.getElementById('pkgdetLightbox').addEventListener('click', (e) => {
            if (e.target.id === 'pkgdetLightbox') closeLightbox();
        });

        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser(); echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';

        // "Book This Cruise" opens the exact same booking wizard used on
        // cruises.php, right here on the details page, instead of navigating away.
        function bookThisCruise() {
            requireLogin('showCruiseBooking', <?= json_encode($cruise['title'] ?? '') ?>, <?= json_encode($price) ?>, <?= json_encode($cruise['duration'] ?? '') ?>);
        }
    </script>
    <script>
        let currentCruise = null, cruiseBookingData = null, selectedPayment = null;

        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
        function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { const btn = event.target; const originalText = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#28a745'; btn.style.color = 'white'; setTimeout(() => { btn.textContent = originalText; btn.style.background = '#e0e0e0'; btn.style.color = ''; }, 1500); }); }

        function updateCruiseLiveTotal() {
            const pass = parseInt(document.getElementById('passengers').value) || 0;
            const cabin = document.getElementById('cabinType').value;
            let upgrade = 0;
            if (cabin === 'oceanview') upgrade = 8000;
            else if (cabin === 'balcony') upgrade = 15000;
            else if (cabin === 'suite') upgrade = 25000;

            const total = (currentCruise.price + upgrade) * pass;
            const display = document.getElementById('cruise-live-total-val');
            if (display) {
                display.innerText = '₱' + total.toLocaleString();
            }
        }

        function showCruiseBooking(title, price, duration) {
            currentCruise = { title, price, duration };
            let modal = document.getElementById('cruiseBookingModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'cruiseBookingModal';
                modal.className = 'booking-modal';
                modal.innerHTML = `
                    <div class="booking-modal-content">
                        <div class="booking-modal-header">
                            <span class="close-booking" onclick="closeCruiseBookingModal()">&times;</span>
                            <h2><i class="fas fa-ship"></i> Book Cruise</h2>
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
                    </div>`;
                document.body.appendChild(modal);
                modal.addEventListener('click', function (e) { if (e.target === modal) closeCruiseBookingModal(); });
            }
            renderCruiseStep1();
            modal.classList.add('active');
        }

        function renderCruiseStep1() {
            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-ship"></i></div>
                    <div class="mini-card-info">
                        <h4>${currentCruise.title}</h4>
                        <span class="mini-price">₱${formatNumber(currentCruise.price)}</span>
                        <p style="margin:0; font-size:0.75rem; color:#64748b;">Per Person</p>
                    </div>
                </div>
                <form id="cruiseForm" onsubmit="return false;">
                    <div class="section-header"><i class="fas fa-user"></i> Passenger Information</div>
                    <div class="input-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" id="applicationEmail" value="${window.currentUserEmail || ''}" placeholder="Your email address">
                    </div>
                    <div class="input-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" id="fullName" placeholder="As per passport" value="${window.currentFullName || ''}">
                    </div>
                    <div class="input-group">
                        <label>Phone <span class="required">*</span></label>
                        <input type="tel" id="phone" placeholder="+63 912 345 6789">
                    </div>

                    <div class="section-header" style="margin-top:25px;"><i class="fas fa-calendar-alt"></i> Cruise Details</div>
                    <div class="input-group">
                        <label>Departure Date <span class="required">*</span></label>
                        <input type="date" id="departureDate" min="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="form-row">
                        <div class="input-group">
                            <label>Cabin Type</label>
                            <select id="cabinType" onchange="updateCruiseLiveTotal()">
                                <option value="interior">Interior</option>
                                <option value="oceanview">Ocean View (+₱8,000)</option>
                                <option value="balcony">Balcony (+₱15,000)</option>
                                <option value="suite">Suite (+₱25,000)</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Dining</label>
                            <select id="dining">
                                <option value="main">Main Dining</option>
                                <option value="anytime">Anytime Dining</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-header" style="margin-top:25px;"><i class="fas fa-info-circle"></i> Special Requests</div>
                    <div class="input-group">
                        <label>Dietary Restrictions</label>
                        <textarea id="dietary" rows="2" placeholder="Any dietary restrictions?"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Additional Requests</label>
                        <textarea id="requests" rows="2" placeholder="Accessibility needs, celebrations, etc."></textarea>
                    </div>

                    <div class="input-group">
                        <label>Number of Passengers <span class="required">*</span></label>
                        <input type="number" id="passengers" min="1" value="1" oninput="updateCruiseLiveTotal()">
                    </div>

                    <div id="cruise-live-total-display" style="margin-top:20px; padding:15px; background:#f0fbfb; border-radius:12px; border:1px solid #b2e0e0; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; color:#008080; font-size:0.85rem;">Estimated Total:</span>
                        <span id="cruise-live-total-val" style="font-size:1.2rem; font-weight:900; color:#008080;">₱${formatNumber(currentCruise.price)}</span>
                    </div>
                    <div id="step1Errors" class="error-message" style="display: none;"></div>
                </form>`;

            footer.innerHTML = `
                <button type="button" class="btn-proceed" style="flex:1; margin: 0 30px;" onclick="validateAndGoToStep2()">Proceed to Review <i class="fas fa-arrow-right"></i></button>
            `;

            updateCruiseSteps(1);
        }

        function validateAndGoToStep2() {
            const errors = [];
            const email = document.getElementById('applicationEmail')?.value.trim();
            const fullName = document.getElementById('fullName')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const departureDate = document.getElementById('departureDate')?.value;
            const passengers = document.getElementById('passengers')?.value;
            if (!email) errors.push('Email address is required');
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Please enter a valid email address');
            if (!fullName) errors.push('Full Name is required');
            if (!phone) errors.push('Phone number is required');
            if (!departureDate) errors.push('Departure Date is required');
            if (!passengers || passengers < 1) errors.push('At least 1 passenger is required');

            document.querySelectorAll('.input-group input, .input-group select').forEach(f => f.classList.remove('error'));
            if (!email) document.getElementById('applicationEmail')?.classList.add('error');
            if (!fullName) document.getElementById('fullName')?.classList.add('error');
            if (!phone) document.getElementById('phone')?.classList.add('error');
            if (!departureDate) document.getElementById('departureDate')?.classList.add('error');
            if (!passengers || passengers < 1) document.getElementById('passengers')?.classList.add('error');

            if (errors.length > 0) {
                const errorDiv = document.getElementById('step1Errors');
                errorDiv.style.display = 'flex';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            goToCruiseStep2();
        }

        function goToCruiseStep2() {
            const fullName = document.getElementById('fullName')?.value;
            const email = document.getElementById('applicationEmail')?.value.trim() || window.currentUserEmail || '';
            const phone = document.getElementById('phone')?.value;
            const departureDate = document.getElementById('departureDate')?.value;
            const passengers = parseInt(document.getElementById('passengers')?.value) || 1;
            const cabinType = document.getElementById('cabinType')?.value;
            const dining = document.getElementById('dining')?.value;
            const dietary = document.getElementById('dietary')?.value;
            const requests = document.getElementById('requests')?.value;
            let upgrade = 0, cabinLabel = 'Interior';
            if (cabinType === 'oceanview') { upgrade = 8000; cabinLabel = 'Ocean View'; }
            else if (cabinType === 'balcony') { upgrade = 15000; cabinLabel = 'Balcony'; }
            else if (cabinType === 'suite') { upgrade = 25000; cabinLabel = 'Suite'; }
            const total = (currentCruise.price + upgrade) * passengers;
            cruiseBookingData = { fullName, email, phone, departureDate, passengers, cabinLabel, dining, dietary, requests, total };

            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-ship"></i></div>
                    <div class="mini-card-info">
                        <h4>${currentCruise.title}</h4>
                        <span class="mini-price">₱${formatNumber(currentCruise.price)}</span>
                        <p style="margin:0; font-size:0.75rem; color:#64748b;">Per Person</p>
                    </div>
                </div>

                <div class="section-header">Passenger Info</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Name:</div><div class="summary-value">${escapeHtml(fullName)}</div></div>
                    <div class="summary-row"><div class="summary-label">Email:</div><div class="summary-value">${escapeHtml(email)}</div></div>
                    <div class="summary-row"><div class="summary-label">Phone:</div><div class="summary-value">${escapeHtml(phone)}</div></div>
                </div>

                <div class="section-header">Cruise Details</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Departure:</div><div class="summary-value">${new Date(departureDate).toLocaleDateString()}</div></div>
                    <div class="summary-row"><div class="summary-label">Passengers:</div><div class="summary-value">${passengers} Guest${passengers > 1 ? 's' : ''}</div></div>
                    <div class="summary-row"><div class="summary-label">Cabin:</div><div class="summary-value">${cabinLabel}</div></div>
                    <div class="summary-row"><div class="summary-label">Dining:</div><div class="summary-value">${dining === 'main' ? 'Main Dining' : 'Anytime Dining'}</div></div>
                </div>

                <div class="section-header">Price Summary</div>
                <div class="summary-table">
                    <div class="summary-row"><div class="summary-label">Base Price:</div><div class="summary-value">₱${formatNumber(currentCruise.price)}</div></div>
                    ${upgrade > 0 ? `<div class="summary-row"><div class="summary-label">Upgrade:</div><div class="summary-value">+₱${formatNumber(upgrade)}</div></div>` : ''}
                    <div class="summary-row" style="background:#f0fbfb;"><div class="summary-label" style="font-weight:800; color:#1e293b;">Total:</div><div class="summary-value" style="color:#008080; font-size:1.2rem; font-weight:900;">₱${formatNumber(total)}</div></div>
                </div>
            `;

            footer.innerHTML = `
                <button type="button" class="btn-back" onclick="goToCruiseStep1()"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-proceed" onclick="goToCruiseStep3()">Proceed to Payment <i class="fas fa-credit-card"></i></button>
            `;

            updateCruiseSteps(2);
        }

        function goToCruiseStep1() {
            updateCruiseSteps(1);
            renderCruiseStep1();
            setTimeout(() => {
                if (cruiseBookingData) {
                    if (document.getElementById('applicationEmail')) document.getElementById('applicationEmail').value = cruiseBookingData.email || '';
                    if (document.getElementById('fullName')) document.getElementById('fullName').value = cruiseBookingData.fullName || '';
                    if (document.getElementById('phone')) document.getElementById('phone').value = cruiseBookingData.phone || '';
                    if (document.getElementById('departureDate')) document.getElementById('departureDate').value = cruiseBookingData.departureDate || '';
                    if (document.getElementById('passengers')) document.getElementById('passengers').value = cruiseBookingData.passengers || '1';
                    updateCruiseLiveTotal();
                }
            }, 50);
        }

        function goToCruiseStep3() {
            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');

            container.innerHTML = `
                <div class="service-mini-card">
                    <div class="mini-card-icon"><i class="fas fa-ship"></i></div>
                    <div class="mini-card-info">
                        <h4>${currentCruise.title}</h4>
                        <span class="mini-price">₱${formatNumber(cruiseBookingData.total)}</span>
                    </div>
                </div>

                <div class="section-header"><i class="fas fa-wallet"></i> Select Payment Method</div>
                <div class="payment-grid">
                    <div class="pay-option" onclick="selectPaymentMethod('gcash', this)">
                        <div class="pay-radio"></div>
                        <div class="pay-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">GCash</span>
                            <span class="pay-desc">Scan QR to pay</span>
                        </div>
                    </div>
                    <div class="pay-option" onclick="selectPaymentMethod('paymaya', this)">
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
                    <div class="pay-option" onclick="selectPaymentMethod('bank', this)">
                        <div class="pay-radio"></div>
                        <div class="pay-icon"><i class="fas fa-university"></i></div>
                        <div class="pay-info">
                            <span class="pay-name">Bank</span>
                            <span class="pay-desc">BDO / BPI</span>
                        </div>
                    </div>
                </div>

                <div id="payment-details-panel" style="display:none; background:white; border:1px solid #008080; border-radius:20px; padding:25px; text-align:center; animation: fadeIn 0.3s ease; box-shadow:0 10px 30px rgba(0,128,128,0.1);">
                    <p style="font-weight:800; color:#008080; margin-bottom:15px; font-size:1.1rem;">Payment Instructions: <span id="selected-method-name">GCash</span></p>

                    <div style="background:#f8fafc; border-radius:15px; padding:15px; margin-bottom:20px; border:1px solid #e2e8f0;">
                        <div style="width:120px; height:120px; background:white; border:1px solid #e2e8f0; margin:0 auto 15px; display:flex; align-items:center; justify-content:center; border-radius:12px;">
                            <i class="fas fa-qrcode" style="font-size:5rem; color:#1e293b;"></i>
                        </div>
                        <div id="payment-accounts-info" style="font-size:0.85rem; color:#64748b; margin:0; line-height:1.6;">
                        </div>
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
                                <div id="preview-payment-proof" style="margin-top: 15px; max-width: 100%; max-height: 200px; overflow: hidden; border-radius: 12px; display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="step3Errors" class="error-message" style="display: none;"></div>
            `;

            footer.innerHTML = `
                <button type="button" class="btn-back" onclick="goToCruiseStep2()"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-proceed" id="finalPaymentBtn" style="opacity:0.5; pointer-events:none;" onclick="validateAndGoToStep4()">Complete Payment <i class="fas fa-check-circle"></i></button>
            `;

            updateCruiseSteps(3);
        }

        function selectPaymentMethod(method, el) {
            selectedPayment = method;
            document.querySelectorAll('.pay-option').forEach(opt => opt.classList.remove('selected'));
            el.classList.add('selected');

            document.getElementById('payment-details-panel').style.display = 'block';

            let methodName = '';
            let accountsHtml = '';

            if (method === 'gcash') {
                methodName = 'GCash';
                accountsHtml = `<p><strong>GCash Number:</strong> <b>0945 776 4140</b> <button class="copy-btn" style="margin-left: 10px; padding: 2px 8px; font-size: 0.75rem; border: none; background: #cbd5e1; border-radius: 4px; cursor: pointer;" onclick="copyToClipboard('0945 776 4140')">Copy</button></p>
                                <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                                <p><strong>Amount:</strong> <span style="color:#008080; font-weight:800;">₱${formatNumber(cruiseBookingData.total)}</span></p>`;
            } else if (method === 'paymaya') {
                methodName = 'PayMaya';
                accountsHtml = `<p><strong>PayMaya Number:</strong> <b>0945 776 4140</b> <button class="copy-btn" style="margin-left: 10px; padding: 2px 8px; font-size: 0.75rem; border: none; background: #cbd5e1; border-radius: 4px; cursor: pointer;" onclick="copyToClipboard('0945 776 4140')">Copy</button></p>
                                <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                                <p><strong>Amount:</strong> <span style="color:#008080; font-weight:800;">₱${formatNumber(cruiseBookingData.total)}</span></p>`;
            } else if (method === 'bank') {
                methodName = 'Bank Transfer';
                accountsHtml = `<p><strong>BPI Account:</strong> <b>1234 5678 90</b> <button class="copy-btn" style="margin-left: 10px; padding: 2px 8px; font-size: 0.75rem; border: none; background: #cbd5e1; border-radius: 4px; cursor: pointer;" onclick="copyToClipboard('1234 5678 90')">Copy</button></p>
                                <p><strong>BDO Account:</strong> <b>5678 1234 56</b> <button class="copy-btn" style="margin-left: 10px; padding: 2px 8px; font-size: 0.75rem; border: none; background: #cbd5e1; border-radius: 4px; cursor: pointer;" onclick="copyToClipboard('5678 1234 56')">Copy</button></p>
                                <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                                <p><strong>Amount:</strong> <span style="color:#008080; font-weight:800;">₱${formatNumber(cruiseBookingData.total)}</span></p>`;
            }

            document.getElementById('selected-method-name').textContent = methodName;
            document.getElementById('payment-accounts-info').innerHTML = accountsHtml;

            document.getElementById('refNumber').value = '';
            document.getElementById('proofFile').value = '';
            document.getElementById('fileNameDisplay').textContent = 'Upload Receipt';
            document.getElementById('preview-payment-proof').style.display = 'none';
            document.getElementById('preview-payment-proof').innerHTML = '';

            checkPaymentFields();
        }

        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                if (!file.type.match('image.*')) {
                    alert('Please upload an image file (PNG, JPG, JPEG)');
                    input.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    input.value = '';
                    return;
                }

                document.getElementById('fileNameDisplay').textContent = file.name;

                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('preview-payment-proof');
                    preview.innerHTML = `<img src="${e.target.result}" style="width:100%; height:auto; object-fit:contain; border-radius:12px; margin-top:10px;">`;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);

                setTimeout(checkPaymentFields, 100);
            }
        }

        function checkPaymentFields() {
            const ref = document.getElementById('refNumber')?.value.trim();
            const file = document.getElementById('proofFile')?.files[0];
            const btn = document.getElementById('finalPaymentBtn');
            if (ref && file) {
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            } else {
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
            }
        }

        function validateAndGoToStep4() {
            const errors = [];
            const ref = document.getElementById('refNumber')?.value.trim();
            const file = document.getElementById('proofFile')?.files[0];

            if (!selectedPayment) errors.push('Please select a payment method');
            if (!ref) errors.push('Please enter the reference number');
            if (!file) errors.push('Please upload proof of payment');

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
            else if (selectedPayment === 'bank') paymentMethodName = 'Bank Transfer';

            cruiseBookingData.paymentMethod = paymentMethodName;
            goToCruiseStep4();
        }

        function goToCruiseStep4() {
            if (!currentCruise || !cruiseBookingData) {
                alert('Your booking session has expired or was reset. Please close this window and start the booking again.');
                return;
            }

            let refVal;
            try {
                refVal = document.getElementById('refNumber')?.value || '';
                fetch('../api/save-service-booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_type: 'Cruise Vacation',
                        package_name: currentCruise.title,
                        package_duration: currentCruise.duration,
                        price_per_person: currentCruise.price,
                        full_name: cruiseBookingData.fullName,
                        email: cruiseBookingData.email,
                        phone: cruiseBookingData.phone,
                        travel_date: cruiseBookingData.departureDate,
                        number_of_travelers: cruiseBookingData.passengers,
                        special_requests: `Cabin: ${cruiseBookingData.cabinLabel}, Dining: ${cruiseBookingData.dining}, Dietary: ${cruiseBookingData.dietary}, Requests: ${cruiseBookingData.requests}`,
                        total_amount: cruiseBookingData.total,
                        payment_method: selectedPayment,
                        payment_reference: refVal
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                    if (data.success) {
                        const bookingNumber = data.booking_number;
                        const container = document.getElementById('step-contents-container');
                        const footer = document.getElementById('modal-footer-container');

                        container.innerHTML = `
                        <div class="success-message" style="text-align:center; padding:20px;">
                            <i class="fas fa-check-circle" style="font-size:4rem; color:#22c55e; margin-bottom:15px; display:block;"></i>
                            <h2 style="color:#1e293b; font-weight:800; margin-bottom:10px;">⏳ Booking Received!</h2>
                            <p style="color:#64748b; font-size:0.95rem; margin-bottom:20px;">Your cruise booking has been received and saved.</p>

                            <div class="booking-number" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:12px; padding:12px; font-weight:800; font-size:1.1rem; color:#0f172a; margin-bottom:20px;">
                                Booking: ${bookingNumber}
                            </div>

                            <div class="details-card" style="background:white; border:1px solid #e2e8f0; border-radius:16px; padding:20px; text-align:left; margin-bottom:20px;">
                                <h4 style="margin:0 0 12px; color:#008080; font-weight:800;">📋 Booking Details:</h4>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Cruise:</strong> ${currentCruise.title}</p>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Departure:</strong> ${new Date(cruiseBookingData.departureDate).toLocaleDateString()}</p>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Passengers:</strong> ${cruiseBookingData.passengers}</p>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Cabin:</strong> ${cruiseBookingData.cabinLabel}</p>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Total Amount:</strong> <span style="color:#008080; font-weight:800;">₱${formatNumber(cruiseBookingData.total)}</span></p>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Payment Method:</strong> ${cruiseBookingData.paymentMethod}</p>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Payment Status:</strong> <span style="color:#008080; font-weight:800;">Pending Verification</span></p>
                                <p style="margin:6px 0; font-size:0.9rem; color:#334155;"><strong>Booked By:</strong> ${escapeHtml(cruiseBookingData.fullName)}</p>
                            </div>

                            <div class="payment-status-pending" style="background:#fff7ed; border:1px solid #ffedd5; border-radius:12px; padding:15px; font-size:0.85rem; color:#c2410c; display:flex; align-items:flex-start; gap:10px; text-align:left; line-height:1.5;">
                                <i class="fas fa-info-circle" style="margin-top: 3px;"></i>
                                <div>Your payment is pending verification. Our team will review your payment proof and send confirmation within 24 hours. A confirmation email has been sent to ${cruiseBookingData.email}.</div>
                            </div>
                        </div>`;

                        footer.innerHTML = `
                        <button class="btn-proceed" style="flex:1;" onclick="window.location.href='../User Account/profile.php?track=' + encodeURIComponent('${bookingNumber}')"><i class="fas fa-file-upload"></i> View My Booking</button>
                        <button class="btn-back" style="flex:1;" onclick="closeCruiseBookingModal(); location.reload();"><i class="fas fa-plus"></i> Book Another Cruise</button>
                        <button class="btn-back" style="flex:1;" onclick="closeCruiseBookingModal()"><i class="fas fa-times"></i> Close</button>
                    `;
                        updateCruiseSteps(4);
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

        function updateCruiseSteps(step) {
            for (let i = 1; i <= 4; i++) {
                const el = document.getElementById(`step${i}-indicator`);
                if (el) {
                    el.classList.remove('active', 'completed');
                    if (i < step) el.classList.add('completed');
                    if (i === step) el.classList.add('active');
                }
            }
        }

        function closeCruiseBookingModal() {
            const modal = document.getElementById('cruiseBookingModal');
            if (modal) modal.classList.remove('active');
            cruiseBookingData = null;
            selectedPayment = null;
        }
    </script>
    <script src="../js/main.js?v=2"></script>
    <script src="../js/auth-menu.js?v=2"></script>
</body>

</html>

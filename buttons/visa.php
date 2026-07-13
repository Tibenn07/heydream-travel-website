<?php
// ========================================
// FILE: buttons/visa.php
// DESCRIPTION: Visa Assistance with Payment System
// ========================================
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = new Auth($pdo);

// ─── Load global visa settings (disclaimer & checklist) ──────────────────────
$global_visa_disclaimer = 'Completing the application does not provide a 100% guarantee of approval.';
$global_visa_checklist = [];
try {
    $gs = $pdo->query("SELECT setting_key, setting_value FROM global_settings WHERE setting_key IN ('visa_disclaimer','visa_checklist')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($gs['visa_disclaimer']))
        $global_visa_disclaimer = $gs['visa_disclaimer'];
    if (!empty($gs['visa_checklist']))
        $global_visa_checklist = json_decode($gs['visa_checklist'], true) ?: [];
} catch (Exception $e) { /* table may not exist yet – use defaults */
}

// ─── Fetch all active categories dynamically ─────────────────────────────────
$active_categories = [];
try {
    $active_categories = $pdo->query("SELECT DISTINCT category FROM visas WHERE is_active = 1 ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Visa Assistance</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef5 100%);
            font-family: 'Poppins', sans-serif;
        }

        .service-hero-container {
            padding: 100px 1% 20px;
            width: 100%;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease 0.1s, transform 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
        }

        .service-hero-container.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        .service-hero {
            background: linear-gradient(180deg, #8A2BE2 0%, #6C5CE7 40%, #ffffff 100%);
            margin: 0 auto;
            padding: 30px 5%;
            text-align: left;
            color: white;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            border-radius: 20px;
            width: 98%;
            max-width: 1900px;
            box-shadow: 0 15px 35px rgba(106, 130, 251, 0.2);
        }

        .hero-globe-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
            border-radius: inherit;
            -webkit-mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
            mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
        }

        .hero-globe-bg {
            position: absolute;
            right: -5%;
            top: 60%;
            transform: translateY(-50%);
            height: 180%;
            min-height: 500px;
            width: auto;
            opacity: 0.95;
        }

        .hero-plane-trail {
            position: absolute;
            left: 5%;
            top: 50%;
            transform: translateY(-50%);
            width: 200px;
            height: auto;
            opacity: 0.6;
            pointer-events: none;
            z-index: 1;
        }

        .hero-content {
            width: 50%;
            min-width: 500px;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            z-index: 10;
        }

        .hero-header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .hero-icon-large {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .hero-icon-large i {
            font-size: 1.8rem;
            color: #8A2BE2;
        }

        .service-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -1px;
            color: white !important;
            text-shadow: none;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            line-height: 1.5;
            color: white;
            max-width: 600px;
            margin: 10px 0 30px 0;
            font-weight: 400;
            opacity: 0.9;
            text-align: left;
        }

        .hero-search {
            width: 100%;
            max-width: 650px;
            margin: 0;
        }

        .hero-logo-box {
            display: none;
        }

        .service-content {
            padding: 40px 1%;
            width: 100%;
            max-width: 1900px;
            margin: 0 auto;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease;
            text-align: left;
            display: flex;
            flex-direction: row;
            gap: 18px;
            height: 100%;
            min-height: 180px;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(108, 92, 231, 0.15);
        }

        .service-card.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        .service-card:nth-child(1).animate-in { transition-delay: 0.55s; }
        .service-card:nth-child(2).animate-in { transition-delay: 0.70s; }
        .service-card:nth-child(3).animate-in { transition-delay: 0.85s; }
        .service-card:nth-child(4).animate-in { transition-delay: 1.00s; }
        .service-card:nth-child(5).animate-in { transition-delay: 1.15s; }
        .service-card:nth-child(6).animate-in { transition-delay: 1.30s; }

        .service-icon {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #6c5ce7, #8a7cff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.2);
        }

        .service-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .service-card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .service-card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
            flex-wrap: wrap;
        }

        .service-card h3 {
            color: #003580;
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .service-card p {
            color: #666;
            line-height: 1.4;
            margin-bottom: 12px;
            font-size: 0.8rem;
        }

        .price-section {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .price-tag {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ff3366;
            margin: 0;
        }

        .book-btn {
            background: #6c5ce7;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .book-btn:hover {
            background: #5a4bcf;
            transform: translateY(-2px);
        }

        .visa-status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin: 0;
            white-space: nowrap;
        }

        .visa-badge-required {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .visa-badge-free {
            background: #eafaf1;
            color: #1e8449;
        }

        .info-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
        }

        .info-section h3 {
            color: #003580;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .requirements {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .req-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .req-item i {
            font-size: 1.3rem;
            color: #6c5ce7;
            width: 35px;
        }

        .req-item h4 {
            color: #003580;
            font-size: 0.8rem;
            margin-bottom: 2px;
        }

        .req-item p {
            color: #666;
            font-size: 0.7rem;
        }

        .back-button-container {
            text-align: center;
            padding: 40px 15px;
            margin-top: 20px;
            background: #f8f9fa;
        }

        .back-button {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
        }

        .back-button:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            color: white;
        }

        .back-button i {
            font-size: 1rem;
        }

        /* Hero Search Bar */
        .hero-search {
            width: 100%;
            max-width: 580px;
            margin: 0;
        }

        .hero-search-wrapper {
            background: white;
            border-radius: 100px;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .hero-search-wrapper:focus-within {
            transform: scale(1.01);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .hero-search-wrapper i {
            color: #7c68ee;
            margin-right: 15px;
            font-size: 1.2rem;
        }

        .hero-search-wrapper input {
            flex: 1;
            min-width: 0;
            border: none;
            outline: none;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            color: #333;
            background: transparent;
        }

        .hero-search-wrapper input::placeholder {
            color: #aaa;
            font-weight: 400;
        }

        .search-wrapper {
            background: white;
            border-radius: 50px;
            padding: 8px 15px 8px 25px;
            display: flex;
            align-items: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(108, 92, 231, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-wrapper:focus-within {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 45px rgba(108, 92, 231, 0.15);
            border-color: #6c5ce7;
        }

        .search-wrapper i {
            color: #6c5ce7;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        /* Continent Cards */
        .continents-section {
            padding: 30px 1% 0;
            width: 100%;
            max-width: 1900px;
            margin: 0 auto;
            overflow: hidden;
        }

        .continents-grid {
            display: flex;
            justify-content: center;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 5px 15px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }

        .continents-grid::-webkit-scrollbar {
            height: 4px;
            display: block;
        }

        .continents-grid::-webkit-scrollbar-track {
            background: rgba(124, 104, 238, 0.05);
            border-radius: 10px;
        }

        .continents-grid::-webkit-scrollbar-thumb {
            background: rgba(124, 104, 238, 0.3);
            border-radius: 10px;
        }

        .continent-card {
            min-width: 190px;
            flex: 1 1 220px;
            max-width: 320px;
            background: white;
            border-radius: 20px;
            padding: 16px 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            min-height: 120px;
            scroll-snap-align: start;
        }

        .continent-card-content {
            flex: 1;
            position: relative;
            z-index: 2;
            padding-right: 20px;
        }

        .continent-card-image {
            position: absolute;
            right: 0;
            top: 0;
            width: 50%;
            height: 100%;
            background-size: cover;
            background-position: center;
            z-index: 1;
        }

        .continent-card-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, white 0%, rgba(255, 255, 255, 0.8) 20%, transparent 100%);
        }

        .continent-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .continent-card.active {
            border-color: #6c5ce7;
            background: #f8f7ff;
        }

        .continent-card.active .continent-card-image::before {
            background: linear-gradient(90deg, #f8f7ff 0%, rgba(248, 247, 255, 0.8) 20%, transparent 100%);
        }

        .continent-icon {
            width: 38px;
            height: 38px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c5ce7;
            font-size: 1rem;
            margin-bottom: 10px;
            box-shadow: 0 4px 10px rgba(108, 92, 231, 0.12);
        }

        .continent-card h4 {
            font-size: 1.1rem;
            font-weight: 800;
            color: #003580;
            margin-bottom: 3px;
        }

        .continent-card p {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .explore-link {
            font-size: 0.85rem;
            font-weight: 700;
            color: #6c5ce7;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .search-wrapper input {
            flex: 1;
            min-width: 0;
            border: none;
            outline: none;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            color: #333;
            background: transparent;
        }

        .search-wrapper input::placeholder {
            color: #999;
            font-weight: 400;
        }

        .search-count {
            background: #f0f2f5;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #6c5ce7;
            font-weight: 600;
            margin-left: 10px;
            transition: all 0.3s ease;
            white-space: nowrap;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            color: #666;
            animation: fadeIn 0.4s ease;
        }

        .no-results i {
            font-size: 3.5rem;
            color: #ddd;
        }

        .no-results p {
            font-size: 1.1rem;
            font-weight: 500;
        }

        .category-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            color: #003580;
            font-size: 1.4rem;
            font-weight: 800;
            margin: 5px 0 15px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .category-title::before,
        .category-title::after {
            content: "";
            height: 1px;
            width: 80px;
            background: rgba(108, 92, 231, 0.4);
            border-radius: 2px;
        }

        .category-title.hidden {
            display: none;
        }

        .service-card.hidden {
            display: none;
        }

        /* Autocomplete Dropdown */
        .search-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 20px;
            right: 20px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(108, 92, 231, 0.18);
            border: 1px solid rgba(108, 92, 231, 0.15);
            z-index: 200;
            overflow: hidden;
            animation: dropdownFadeIn 0.2s ease;
            display: none;
        }

        .search-dropdown.open {
            display: block;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-header {
            padding: 10px 18px 6px;
            font-size: 0.7rem;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid #f0f2f5;
        }

        .dropdown-item-visa {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 18px;
            cursor: pointer;
            transition: background 0.15s ease;
            border-bottom: 1px solid #f8f9fa;
        }

        .dropdown-item-visa:last-child {
            border-bottom: none;
        }

        .dropdown-item-visa:hover,
        .dropdown-item-visa.highlighted {
            background: linear-gradient(90deg, rgba(108, 92, 231, 0.06), rgba(138, 124, 255, 0.04));
        }

        .dropdown-item-visa .di-flag {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .dropdown-item-visa .di-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #003580;
        }

        .dropdown-item-visa .di-label em {
            color: #6c5ce7;
            font-style: normal;
        }

        .dropdown-item-visa .di-cat {
            font-size: 0.7rem;
            color: #999;
            margin-top: 1px;
        }

        .dropdown-empty {
            padding: 20px 18px;
            text-align: center;
            color: #bbb;
            font-size: 0.85rem;
        }

        .dropdown-empty i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 6px;
            color: #ddd;
        }

        /* Booking Modal */
        .booking-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .booking-modal.active {
            display: flex;
        }

        .booking-modal-content {
            background: white;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        .booking-modal-header {
            background: linear-gradient(135deg, #6c5ce7, #8a7cff);
            color: white;
            padding: 20px 25px;
            border-radius: 20px 20px 0 0;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.8rem;
            cursor: pointer;
            color: white;
        }

        .close-modal:hover {
            transform: rotate(90deg);
            color: #ff9800;
        }

        .booking-modal-header h2 {
            font-size: 1.3rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-modal-header p {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .booking-steps {
            display: flex;
            margin: 20px 0 25px;
            position: relative;
            padding: 0 10px;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 32px;
            height: 32px;
            background: #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 6px;
            font-weight: bold;
            font-size: 0.8rem;
            color: #666;
        }

        .step.active .step-number {
            background: #ff9800;
            color: white;
        }

        .step.completed .step-number {
            background: #28a745;
            color: white;
        }

        .step-label {
            font-size: 0.65rem;
            color: #666;
        }

        .step.active .step-label {
            color: #ff9800;
            font-weight: 600;
        }

        .step-line {
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }

        .step:last-child .step-line {
            display: none;
        }

        .step-content {
            display: none;
            animation: fadeIn 0.3s ease;
            padding: 0 5px;
        }

        .step-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .booking-service-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            border: 1px solid #e0e0e0;
        }

        .service-icon-large {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #6c5ce7, #8a7cff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .service-icon-large i {
            font-size: 1.4rem;
            color: white;
        }

        .service-info h3 {
            color: #003580;
            margin-bottom: 3px;
            font-size: 1rem;
        }

        .service-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ff9800;
        }

        .service-duration {
            color: #666;
            font-size: 0.7rem;
        }

        .form-section {
            margin-bottom: 20px;
        }

        .form-section h4 {
            color: #003580;
            margin-bottom: 12px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border-left: 3px solid #ff9800;
            padding-left: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.75rem;
            color: #333;
        }

        .form-group label .required {
            color: #ff4444;
            margin-left: 2px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.8rem;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff9800;
            box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.1);
        }

        .form-group input.error,
        .form-group select.error {
            border-color: #ff4444;
            background: #fff5f5;
        }

        .error-message {
            background: #fff5f5;
            border-left: 3px solid #ff4444;
            padding: 10px 12px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #ff4444;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .error-list {
            list-style: none;
            margin: 0;
            padding-left: 20px;
        }

        .review-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .review-section {
            margin-bottom: 15px;
        }

        .review-section h4 {
            color: #003580;
            margin-bottom: 10px;
            font-size: 0.85rem;
            border-left: 3px solid #ff9800;
            padding-left: 10px;
        }

        .review-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.8rem;
        }

        .review-label {
            width: 120px;
            font-weight: 600;
            color: #666;
        }

        .review-value {
            flex: 1;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-prev {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-next {
            background: linear-gradient(135deg, #6c5ce7, #8a7cff);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-next:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }

        .submit-booking-btn {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 10px;
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .payment-method:hover {
            border-color: #ff9800;
            background: #fff9e6;
        }

        .payment-method.selected {
            border-color: #ff9800;
            background: #fff9e6;
        }

        .payment-method input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #ff9800;
            margin: 0;
            flex-shrink: 0;
        }

        .payment-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .payment-icon i {
            font-size: 1.5rem;
        }

        .payment-details {
            flex: 1;
        }

        .payment-name {
            font-weight: 700;
            color: #003580;
            margin-bottom: 2px;
            font-size: 0.85rem;
        }

        .payment-desc {
            font-size: 0.65rem;
            color: #666;
        }

        .payment-details-box {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .payment-details-box.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .payment-instructions {
            background: white;
            border-radius: 12px;
            padding: 20px;
        }

        .instruction-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ff9800;
        }

        .instruction-header i {
            font-size: 1.5rem;
            color: #ff9800;
        }

        .instruction-header h4 {
            color: #003580;
            margin: 0;
            font-size: 1rem;
        }

        .qr-placeholder {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, #f5f7fa, #e9eef5);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            gap: 10px;
        }

        .qr-placeholder i {
            font-size: 3rem;
            color: #ff9800;
        }

        .account-details {
            background: #fff9e6;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .account-details p {
            margin: 8px 0;
            font-size: 0.85rem;
        }

        .account-number {
            font-weight: bold;
            color: #003580;
            background: #e8f0fe;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 1rem;
        }

        .copy-btn {
            background: #e0e0e0;
            border: none;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            cursor: pointer;
            margin-left: 8px;
            transition: all 0.2s ease;
        }

        .copy-btn:hover {
            background: #ff9800;
            color: white;
        }

        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
            margin-top: 15px;
        }

        .file-upload:hover {
            border-color: #ff9800;
            background: #fff9e6;
        }

        .file-upload i {
            font-size: 1.5rem;
            color: #ff9800;
            margin-bottom: 5px;
        }

        .file-upload p {
            font-size: 0.7rem;
            color: #666;
            margin: 0;
        }

        .continent-card.start-card {
            background: white;
            border: 2px solid #7c68ee;
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: auto;
            height: 100%;
        }

        .start-card .continent-icon {
            width: 45px;
            height: 45px;
            background: #f5f4ff;
            color: #7c68ee;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .start-card h4 {
            font-size: 1.4rem;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .start-card p {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 20px;
        }

        .start-card .explore-link {
            color: #7c68ee;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .file-upload .file-name {
            font-size: 0.65rem;
            color: #003580;
            margin-top: 5px;
            font-weight: 500;
        }

        .upload-preview {
            margin-top: 10px;
            text-align: center;
        }

        .upload-preview img {
            max-width: 100%;
            max-height: 100px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .instruction-note {
            background: #e8f0fe;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.7rem;
            color: #003580;
            margin-top: 10px;
            text-align: center;
        }

        .payment-status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.8rem;
            margin-top: 10px;
        }

        .card-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .success-message {
            text-align: center;
            padding: 25px;
        }

        .success-message i {
            font-size: 2.5rem;
            color: #28a745;
            margin-bottom: 12px;
        }

        .booking-number {
            background: #e8f0fe;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin: 12px 0;
            display: inline-block;
        }

        .details-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin: 15px 0;
            text-align: left;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #28a745, #218838);
            width: auto;
            padding: 8px 20px;
        }

        .btn-secondary {
            background: #6c757d;
            width: auto;
            padding: 8px 20px;
        }

        /* Visa Gate Modal */
        .gate-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 2500;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .gate-modal.active {
            display: flex;
        }

        .gate-content {
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            animation: modalSlideIn 0.3s ease;
            overflow: hidden;
        }

        .gate-header {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            padding: 20px;
            position: relative;
            text-align: center;
            flex-shrink: 0;
        }

        .gate-header h2 {
            font-size: 1.3rem;
            margin: 0;
        }

        .gate-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            transition: 0.3s;
        }

        .gate-close:hover {
            color: #ff9800;
            transform: rotate(90deg);
        }

        .gate-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }

        .gate-disclaimer {
            background: #fff5f5;
            border-left: 4px solid #ff4444;
            padding: 12px;
            font-size: 0.8rem;
            color: #333;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }

        .gate-footer {
            padding: 0 25px 25px;
            display: flex;
            justify-content: center;
            flex-shrink: 0;
        }

        .gate-proceed-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
            max-width: 250px;
        }

        .gate-proceed-btn:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ============================================================
           ENTRANCE ANIMATIONS
        ============================================================ */

        /* Keyframes */
        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeSlideLeft {
            from { opacity: 0; transform: translateX(-40px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeSlideRight {
            from { opacity: 0; transform: translateX(40px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes popIn {
            0%   { opacity: 0; transform: scale(0.8); }
            70%  { opacity: 1; transform: scale(1.04); }
            100% { transform: scale(1); }
        }
        @keyframes globeSpin {
            from { opacity: 0; transform: translateY(-50%) rotate(-15deg) scale(0.85); }
            to   { opacity: 0.95; transform: translateY(-50%) rotate(0deg) scale(1); }
        }

        /* Hero section */
        .service-hero {
            animation: fadeSlideDown 0.7s cubic-bezier(0.22,1,0.36,1) both;
        }
        .hero-icon-large {
            animation: popIn 0.6s cubic-bezier(0.22,1,0.36,1) 0.35s both;
        }
        .service-hero h1 {
            animation: fadeSlideLeft 0.65s cubic-bezier(0.22,1,0.36,1) 0.45s both;
        }
        .hero-subtitle {
            animation: fadeSlideLeft 0.65s cubic-bezier(0.22,1,0.36,1) 0.58s both;
        }
        .hero-search {
            animation: fadeSlideUp 0.65s cubic-bezier(0.22,1,0.36,1) 0.70s both;
        }
        .hero-globe-bg {
            animation: globeSpin 1.1s cubic-bezier(0.22,1,0.36,1) 0.2s both;
        }

        /* Continent cards – staggered via JS class */
        .continent-card {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.55s cubic-bezier(0.22,1,0.36,1),
                        transform 0.55s cubic-bezier(0.22,1,0.36,1);
        }
        .continent-card.anim-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Service cards – staggered via JS class */
        .service-card {
            opacity: 0;
            transform: translateY(35px);
            transition: opacity 0.5s cubic-bezier(0.22,1,0.36,1),
                        transform 0.5s cubic-bezier(0.22,1,0.36,1),
                        box-shadow 0.3s ease;
        }
        .service-card.anim-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Category titles */
        .category-title {
            animation: fadeSlideUp 0.55s cubic-bezier(0.22,1,0.36,1) 0.3s both;
        }

        /* Info section */
        .info-section {
            animation: fadeSlideUp 0.65s cubic-bezier(0.22,1,0.36,1) 0.2s both;
        }

        /* Req items stagger */
        .req-item {
            opacity: 0;
            transform: translateX(-20px);
            transition: opacity 0.45s cubic-bezier(0.22,1,0.36,1),
                        transform 0.45s cubic-bezier(0.22,1,0.36,1);
        }
        .req-item.anim-in {
            opacity: 1;
            transform: translateX(0);
        }

        /* Back button */
        .back-button-container {
            animation: fadeSlideUp 0.6s cubic-bezier(0.22,1,0.36,1) 0.3s both;
        }

        /* Navbar */
        .navbar {
            animation: fadeSlideDown 0.55s cubic-bezier(0.22,1,0.36,1) 0s both;
        }

        @media (max-width: 768px) {
            html {
                background-color: #1e3c72 !important;
                overflow-x: hidden !important;
                overflow-y: auto !important;
                width: 100% !important;
            }

            body {
                overflow-x: hidden !important;
                overflow-y: auto !important;
                width: 100% !important;
                position: relative !important;
            }

            .service-hero-container {
                padding: 0 !important;
            }

            .service-hero {
                padding: 65px 6% 30px !important;
                min-height: auto;
                border-radius: 0;
                width: 100%;
                margin: 0;
            }

            .hero-content {
                width: 100%;
                min-width: 0;
                z-index: 2;
            }

            .hero-globe-wrapper {
                opacity: 0.25;
            }

            .hero-globe-bg {
                right: -20%;
                height: 120%;
                min-height: auto;
            }

            .service-hero h1 {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 0.95rem;
            }

            .hero-search-wrapper {
                padding: 12px 20px;
            }

            .hero-search-wrapper input {
                font-size: 0.85rem;
            }

            .service-content {
                padding-left: 0 !important;
                padding-right: 0 !important;
                overflow-x: hidden;
            }

            .service-grid {
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 20px;
                gap: 15px;
                padding-left: 15px;
                padding-right: 15px;
                margin: 0;
            }

            .service-card {
                flex: 0 0 250px;
                scroll-snap-align: start;
                padding: 20px 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .requirements {
                grid-template-columns: 1fr;
            }

            .review-row {
                flex-direction: column;
            }

            .review-label {
                width: 100%;
                margin-bottom: 3px;
            }

            .card-row {
                grid-template-columns: 1fr;
            }

            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .continents-grid {
                justify-content: flex-start;
                padding-right: 25px !important;
            }

            .continent-card {
                min-width: 140px;
                padding: 12px;
                min-height: auto;
            }

            .continent-icon {
                width: 35px;
                height: 35px;
                margin-bottom: 8px;
            }

            .continent-icon i {
                font-size: 0.9rem;
            }

            .continent-card h4 {
                font-size: 0.9rem;
                margin-bottom: 3px;
            }

            .continent-card p {
                display: none;
            }

            .explore-link {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 550px) {
            .service-grid {
                gap: 12px;
            }

            .service-card {
                padding: 15px 12px;
            }

            .service-card h3 {
                font-size: 0.95rem;
            }

            .service-card p {
                display: block;
                font-size: 0.75rem;
                margin-bottom: 10px;
            }

            .service-icon {
                width: 48px;
                height: 48px;
            }

            .service-icon i {
                font-size: 1.3rem;
            }

            .price-tag {
                font-size: 1.1rem;
            }

            .book-btn {
                padding: 8px 12px;
                font-size: 0.75rem;
            }

            .payment-methods {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
</head>

<body>

    <!-- ══════════════════════════════════════
         PREMIUM VISA ENTRANCE LOADER
    ══════════════════════════════════════ -->
    <div id="pagePreloader">
        <div class="loader-bg-element bg-visa"></div>
        <div class="loader-particle lp-v1"></div>
        <div class="loader-particle lp-v2"></div>
        <div class="loader-particle lp-v3"></div>
        <div class="loader-particle lp-v4"></div>

        <div class="loader-logo-area">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo">
            <style>
                .loader-dynamic-logo { height: 100px !important; width: auto !important; margin-left: 10px; }
                @media (max-width: 768px) { .loader-dynamic-logo { height: 60px !important; } }
                @media (max-width: 480px) { .loader-dynamic-logo { height: 45px !important; margin-left: 5px; } }
            </style>
            <img src="../images/Localista (1).png" alt="Localista" class="loader-dynamic-logo">
        </div>

        <div class="visa-animation-container">
            <div class="visa-icon-3d"><i class="fas fa-passport"></i></div>
        </div>

        <div class="loader-text-area">
            <h1 id="loaderTitle">Global Access Made Easy...</h1>
            <p id="loaderSubtext">We're finding the best visa options for you</p>
        </div>

        <div class="loader-progress-container">
            <div class="loader-progress-fill" id="loaderBarFill"></div>
            <div class="loader-percent" id="loaderPercent">0%</div>
        </div>

        <div class="search-mockup-pill">
            <div class="mockup-route">
                <div style="background: rgba(255,255,255,0.2); width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; margin-right:5px;"><i class="fas fa-globe-americas"></i></div>
                <span id="dynamicRouteText">Checking Policies</span>
            </div>
            <div class="mockup-status">
                <span id="mockupStatusText">Scanning borders...</span>
                <i class="fas fa-passport" id="mockupStatusIcon"></i>
            </div>
        </div>

        <div class="loader-steps">
            <div class="loader-step active stage-v1" id="lStep1">
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <span>Initial Review</span>
            </div>
            <div class="loader-step stage-v2" id="lStep2">
                <div class="step-icon"><i class="fas fa-list-check"></i></div>
                <span>Scanning Rules</span>
            </div>
            <div class="loader-step stage-v3" id="lStep3">
                <div class="step-icon"><i class="fas fa-shield-alt"></i></div>
                <span>Verifying Access</span>
            </div>
            <div class="loader-step stage-v4" id="lStep4">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <span>Finalizing Path</span>
            </div>
        </div>

        <div class="loader-footer">
            <i class="fas fa-hands-helping"></i> <span id="loaderFooterText">Sit back and relax, your global access is just a step away.</span>
        </div>
    </div>

    <style>
        /* ============================================
           PREMIUM VISA ENTRANCE LOADER CSS
        ============================================ */
        #pagePreloader {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #8A2BE2, #6C5CE7, #003580);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease, visibility 0.8s ease;
            overflow: hidden;
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        #pagePreloader.hide {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        /* Subtle Background Elements */
        .loader-bg-element {
            position: absolute;
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            z-index: -1;
        }
        .bg-visa { top: -10%; left: -10%; width: 120%; height: 120%; border-radius: 50%; opacity: 0.08; filter: blur(60px); background: radial-gradient(white, transparent); }

        /* Floating particles */
        .loader-particle {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.3;
            animation: floatVisaParticle 8s linear infinite;
        }
        .lp-v1 { top: 20%; left: 15%; width: 6px; height: 6px; animation-duration: 9s; }
        .lp-v2 { top: 60%; left: 5%; width: 12px; height: 12px; animation-duration: 11s; animation-delay: 2s; }
        .lp-v3 { top: 30%; right: 15%; width: 8px; height: 8px; animation-duration: 10s; animation-delay: 1s; }
        .lp-v4 { top: 75%; right: 25%; width: 14px; height: 14px; animation-duration: 14s; }

        @keyframes floatVisaParticle {
            0% { transform: translateY(0) scale(1); opacity: 0; }
            40% { opacity: 0.8; }
            100% { transform: translateY(-100px) scale(0.2); opacity: 0; }
        }

        /* Top Logo */
        .loader-logo-area {
            position: absolute;
            top: 40px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDownVisa 1s ease-out forwards;
        }
        
        .loader-logo-area img {
            width: 45px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .loader-logo-area h2 {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            line-height: 1.1;
            font-family: 'Poppins', sans-serif;
        }
        
        .loader-logo-area h2 span {
            display: block;
            font-size: 0.65rem;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.9;
        }

        /* Animated Visa Icon */
        .visa-animation-container {
            position: relative;
            margin-bottom: 25px;
            animation: bobVisa 4s ease-in-out infinite;
        }
        
        .visa-icon-3d {
            font-size: 2.5rem;
            color: white;
            text-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1));
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 15px 35px rgba(108, 92, 231, 0.4);
        }
        
        @keyframes bobVisa {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); box-shadow: 0 25px 45px rgba(108, 92, 231, 0.5); }
        }

        /* Text Area */
        .loader-text-area {
            text-align: center;
            margin-bottom: 30px;
            animation: slideUpVisa 1s ease-out forwards;
        }

        .loader-text-area h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 0 4px 10px rgba(0,0,0,0.2);
            font-family: 'Poppins', sans-serif;
        }

        .loader-text-area p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Glassmorphism Progress Bar */
        .loader-progress-container {
            width: 100%;
            max-width: 600px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 40px;
            height: 65px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: visible;
            display: flex;
            align-items: center;
            padding: 6px;
            margin-bottom: 25px;
            animation: slideUpVisa 1.2s ease-out forwards;
        }

        .loader-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #d8b4fe 0%, #f3e8ff 100%);
            border-radius: 35px;
            width: 0%;
            position: relative;
            transition: width 0.1s linear;
            box-shadow: 0 0 20px rgba(216, 180, 254, 0.6);
            overflow: hidden;
        }
        
        .loader-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8), transparent);
            animation: shimmerVisa 1.5s infinite;
        }

        @keyframes shimmerVisa {
            100% { left: 200%; }
        }

        .loader-percent {
            position: absolute;
            right: 30px;
            font-weight: 800;
            font-size: 1.3rem;
            color: #6c5ce7;
            text-shadow: 0 1px 2px rgba(255,255,255,0.9);
            z-index: 10;
        }

        /* Search Mockup Pill */
        /* Search Mockup Pill - Hidden */
        .search-mockup-pill {
            display: none;
        }

        .mockup-route {
            display: none;
        }
        
        .mockup-route i { 
            display: none;
        }
        
        .mockup-divider {
            display: none;
        }
        
        .mockup-divider i {
            display: none;
        }

        .mockup-status {
            display: none;
        }

        /* Loading Steps - Modern Single Step Display */
        .loader-steps {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
            justify-content: center;
            align-items: center;
            animation: slideUpVisa 1.2s ease-out forwards;
            height: 80px;
            min-height: 80px;
        }

        .loader-step {
            display: none;
        }

        .loader-step.active {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 1.1rem;
            opacity: 0;
            transform: scale(0.8);
            animation: stepFadeIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            font-weight: 700;
            color: white;
        }

        @keyframes stepFadeIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .loader-step.done {
            display: none;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.4);
            transition: all 0.4s ease;
        }
        
        .loader-step.active .step-icon {
            animation: iconPulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 8px 32px rgba(102, 126, 234, 0.4); }
            50% { transform: scale(1.1); box-shadow: 0 12px 48px rgba(102, 126, 234, 0.8); }
        }

        /* Footer */
        .loader-footer {
            position: absolute;
            bottom: 35px;
            font-size: 0.95rem;
            opacity: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
            animation: fadeInVisa 2s ease-out forwards;
            color: white;
            transition: opacity 0.4s ease, transform 0.4s ease;
            text-align: center;
            max-width: 280px;
            left: 50%;
            transform: translateX(-50%);
        }
        .loader-footer::before,
        .loader-footer::after {
            display: none;
        }

        @keyframes slideDownVisa {
            from { transform: translateY(-40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideUpVisa {
            from { transform: translateY(40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes fadeInVisa {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 650px) {
            .loader-steps { flex-direction: column; gap: 15px; text-align: center; align-items: center; height: auto; }
            .loader-text-area h1 { font-size: 1.7rem; }
            .loader-progress-container { max-width: 90%; }
            .step-icon { width: 45px; height: 45px; font-size: 1.2rem; }
            .loader-step.active { font-size: 1rem; }
        }
    </style>

    <script>
        // --- Premium Visa Loader Sequence ---
        (function () {
            window.addEventListener('load', function () {
                const loader = document.getElementById('pagePreloader');
                if (!loader) return;
                
                const percentEl = document.getElementById('loaderPercent');
                const barFill = document.getElementById('loaderBarFill');
                const mockupStatusText = document.getElementById('mockupStatusText');
                const mockupStatusIcon = document.getElementById('mockupStatusIcon');
                const titleEl = document.getElementById('loaderTitle');
                
                let progress = 0;
                // Total duration = 2000ms for fast loader
                const duration = 2000; 
                const intervalTime = 20;
                const increment = (100 / (duration / intervalTime));
                
                const urlParams = new URLSearchParams(window.location.search);
                const query = urlParams.get('search') || urlParams.get('destination') || urlParams.get('q');
                const hasRoute = query && query.trim().length > 0;
                
                const dynamicRouteEl = document.getElementById('dynamicRouteText');
                const footerTextEl = document.getElementById('loaderFooterText');
                
                const loadingMessages = [
                    { p: 0, text: 'Scanning requirements...', icon: 'fa-globe', title: 'Global Access Made Easy...', destText: (hasRoute ? `Visa for ${query}` : 'Checking Policies'), footer: "Sit back and relax, your global access is just a step away." },
                    { p: 25, text: 'Matching profiles...', icon: 'fa-file-signature', title: 'Curating Application Paths...', destText: (hasRoute ? `Checklist for ${query}` : 'Global Visas'), footer: "Streamlining immigration rules for you." },
                    { p: 55, text: 'Finalizing docs...', icon: 'fa-shield-alt', title: 'Verifying Security Policies...', destText: (hasRoute ? `Embassy bounds ${query}` : 'Document Handling'), footer: "Ensuring you are perfectly prepared to fly." },
                    { p: 80, text: 'Ready', icon: 'fa-check-circle', title: 'Preparing Application Platform...', destText: (hasRoute ? `Ready for ${query}` : 'Options Ready'), footer: "Your visa application path is almost ready." }
                ];
                
                const timer = setInterval(() => {
                    progress += increment;
                    if (progress >= 100) progress = 100;
                    
                    if (percentEl) percentEl.textContent = Math.floor(progress) + '%';
                    if (barFill) barFill.style.width = progress + '%';
                    
                    let currentStage = 1;
                    if (progress >= 80) currentStage = 4;
                    else if (progress >= 55) currentStage = 3;
                    else if (progress >= 25) currentStage = 2;
                    
                    const stageData = loadingMessages[currentStage - 1];
                    if (titleEl && titleEl.textContent !== stageData.title) {
                        titleEl.textContent = stageData.title;
                        if (footerTextEl) footerTextEl.textContent = stageData.footer;
                        
                        // Update all step icons
                        for (let i = 1; i <= 4; i++) {
                            const stepEl = document.getElementById('lStep' + i);
                            if (!stepEl) continue;
                            
                            const icon = stepEl.querySelector('i');
                            if (!icon) continue;
                            
                            if (i < currentStage) {
                                // Completed step - show checkmark
                                icon.className = 'fas fa-check';
                            } else if (i === currentStage) {
                                // Current step - show stage icon
                                icon.className = 'fas ' + stageData.icon;
                            } else {
                                // Future step - show placeholder icon
                                icon.className = 'fas fa-circle';
                            }
                        }
                    }
                    
                    if (progress >= 100) {
                        clearInterval(timer);
                        
                        // Mark all steps as done
                        for (let i = 1; i <= 4; i++) {
                            const stepEl = document.getElementById('lStep' + i);
                            if (stepEl) {
                                const icon = stepEl.querySelector('i');
                                if (icon) icon.className = 'fas fa-check';
                            }
                        }

                        setTimeout(() => {
                            // Trigger animations for hero and service cards
                            const heroContainer = document.querySelector('.service-hero-container');
                            if (heroContainer) heroContainer.classList.add('animate-in');
                            
                            const cards = document.querySelectorAll('.service-card');
                            if ('IntersectionObserver' in window) {
                                const observer = new IntersectionObserver((entries) => {
                                    entries.forEach((entry) => {
                                        if (entry.isIntersecting) {
                                            entry.target.classList.add('animate-in');
                                            observer.unobserve(entry.target);
                                        }
                                    });
                                }, { threshold: 0.1 });
                                cards.forEach(card => observer.observe(card));
                            } else {
                                cards.forEach(card => card.classList.add('animate-in'));
                            }
                            
                            loader.classList.add('hide');
                            setTimeout(() => {
                                loader.remove();
                            }, 800);
                        }, 500);
                    }
                }, intervalTime);
            });
        })();
    </script>


    <header class="navbar" id="navbar">
        <div class="nav-left"><img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='../index.php'">
            <div class="company-name"><span class="line1">HeyDream Travel</span><span class="line2">and Tours</span>
            </div>
        </div>
    </header>

    <div class="service-hero-container">
        <section class="service-hero">

            <!-- Background Globe Image Wrapper -->
            <div class="hero-globe-wrapper">
                <img src="../images/globe-hero.png" alt="Global Destinations" class="hero-globe-bg"
                    onerror="this.style.display='none'">
            </div>

            <div class="hero-content">
                <div class="hero-header">
                    <div class="hero-icon-large">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h1>Visa Assistance</h1>
                </div>
                <p class="hero-subtitle">Expert visa processing services for hassle-free travel.<br>Let us handle the
                    paperwork, you focus on the journey.</p>

                <div class="hero-search">
                    <div class="hero-search-wrapper">
                        <i class="fas fa-search" style="color: #8A2BE2;"></i>
                        <input type="text" id="visaSearchInput"
                            placeholder="Where would you like to go? (e.g. Japan, France...)" oninput="filterVisas()"
                            onkeydown="handleDropdownKey(event)" autocomplete="off">
                        <div id="visaMatchCount" class="search-count" style="display:none;"></div>
                    </div>
                    <div class="search-dropdown" id="visaDropdown"></div>
                </div>
            </div>
        </section>
    </div>
    <!-- Continent Selection -->
    <div class="continents-section">
        <div class="continents-grid" id="continentGrid">
            <div class="continent-card start-card active" onclick="filterByContinent('All', this)">
                <div class="continent-card-content">
                    <div class="continent-icon"><i class="fas fa-globe"></i></div>
                    <h4>Explore by Region</h4>
                    <p>Browse all available visa services worldwide.</p>
                    <div class="explore-link">View all destinations <i class="fas fa-arrow-right"></i></div>
                </div>
            </div>
            <div class="continent-card" onclick="filterByContinent('Asia', this)">
                <div class="continent-card-content">
                    <div class="continent-icon"><i class="fas fa-torii-gate"></i></div>
                    <h4>Asia</h4>
                    <p>Top destinations in Asia</p>
                    <div class="explore-link">Explore <i class="fas fa-arrow-right"></i></div>
                </div>
                <div class="continent-card-image"
                    style="background-image: url('https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&q=80&w=400');">
                </div>
            </div>
            <div class="continent-card" onclick="filterByContinent('Europe', this)">
                <div class="continent-card-content">
                    <div class="continent-icon"><i class="fas fa-monument"></i></div>
                    <h4>Europe</h4>
                    <p>Timeless cities and cultures</p>
                    <div class="explore-link">Explore <i class="fas fa-arrow-right"></i></div>
                </div>
                <div class="continent-card-image"
                    style="background-image: url('https://images.unsplash.com/photo-1490642914619-7955a3fd483c?auto=format&fit=crop&q=80&w=400');">
                </div>
            </div>
            <div class="continent-card" onclick="filterByContinent('North America', this)">
                <div class="continent-card-content">
                    <div class="continent-icon"><i class="fas fa-city"></i></div>
                    <h4>North America</h4>
                    <p>Vibrant cities and landscapes</p>
                    <div class="explore-link">Explore <i class="fas fa-arrow-right"></i></div>
                </div>
                <div class="continent-card-image"
                    style="background-image: url('https://images.unsplash.com/photo-1501594907352-04cda38ebc29?auto=format&fit=crop&q=80&w=400');">
                </div>
            </div>
            <div class="continent-card" onclick="filterByContinent('South America', this)">
                <div class="continent-card-content">
                    <div class="continent-icon"><i class="fas fa-mountain"></i></div>
                    <h4>South America</h4>
                    <p>Wild adventures and heritage</p>
                    <div class="explore-link">Explore <i class="fas fa-arrow-right"></i></div>
                </div>
                <div class="continent-card-image"
                    style="background-image: url('https://images.unsplash.com/photo-1483729558449-99ef09a8c325?auto=format&fit=crop&q=80&w=400');">
                </div>
            </div>
            <div class="continent-card" onclick="filterByContinent('Africa', this)">
                <div class="continent-card-content">
                    <div class="continent-icon"><i class="fas fa-globe-africa"></i></div>
                    <h4>Africa</h4>
                    <p>Discover diverse cultures</p>
                    <div class="explore-link">Explore <i class="fas fa-arrow-right"></i></div>
                </div>
                <div class="continent-card-image"
                    style="background-image: url('https://images.unsplash.com/photo-1516026672322-bc52d61a55d5?auto=format&fit=crop&q=80&w=400');">
                </div>
            </div>
            <div class="continent-card" onclick="filterByContinent('Oceania', this)">
                <div class="continent-card-content">
                    <div class="continent-icon"><i class="fas fa-umbrella-beach"></i></div>
                    <h4>Oceania</h4>
                    <p>Beautiful coasts and islands</p>
                    <div class="explore-link">Explore <i class="fas fa-arrow-right"></i></div>
                </div>
                <div class="continent-card-image"
                    style="background-image: url('https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?auto=format&fit=crop&q=80&w=400');">
                </div>
            </div>
        </div>
    </div>

    <!-- No Results Feedback -->
    <div id="noResults" class="no-results"
        style="display:none; text-align:center; padding:60px 20px; flex-direction:column; align-items:center; gap:15px; color:#666;">
        <i class="fas fa-search-location" style="font-size:3.5rem; color:#ddd;"></i>
        <p style="font-size:1.1rem; font-weight:500;">No destinations match your search.</p>
        <button class="clear-search-btn" onclick="clearSearch()"
            style="background:#7c68ee; color:white; border:none; padding:8px 20px; border-radius:20px; cursor:pointer;">Clear
            Search</button>
    </div>

    <div class="service-content">
        <?php
        // ─── Global disclaimer/checklist fallback as JSON for JS ─────────────
        $global_disclaimer_js = json_encode($global_visa_disclaimer);
        $global_checklist_js = json_encode($global_visa_checklist);

        foreach ($active_categories as $cat):
            $stmt = $pdo->prepare("SELECT * FROM visas WHERE category = ? AND is_active = 1 ORDER BY display_order ASC");
            $stmt->execute([$cat]);
            $visas = $stmt->fetchAll();
            if (empty($visas))
                continue;
            ?>
            <h2 class="category-title">
                <?= htmlspecialchars($cat) ?> Destinations
            </h2>
            <div class="service-grid">
                <?php foreach ($visas as $visa):
                    $status = $visa['visa_status'] ?? 'required';
                    // Per-visa disclaimer falls back to global if empty
                    $visa_disclaimer = !empty($visa['disclaimer']) ? $visa['disclaimer'] : $global_visa_disclaimer;
                    // Per-visa requirements: if empty, fall back to global checklist JSON
                    $visa_requirements_json = !empty($visa['requirements']) ? $visa['requirements'] : json_encode($global_visa_checklist);
                    $visa_notes = $visa['important_notes'] ?? '';
                    ?>
                    <div class="service-card">
                        <div class="service-icon">
                            <?php if ($visa['icon_type'] === 'image' || $visa['icon_type'] === 'upload'): ?>
                                <img src="<?= (strpos($visa['icon_value'], 'http') === 0) ? $visa['icon_value'] : '../' . $visa['icon_value'] ?>"
                                    alt="<?= htmlspecialchars($visa['title']) ?>"
                                    style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                            <?php else: ?>
                                <i
                                    class="fas <?= !empty($visa['icon_value']) ? htmlspecialchars($visa['icon_value']) : 'fa-passport' ?>"></i>
                            <?php endif; ?>
                        </div>

                        <div class="service-card-body">
                            <div class="service-card-header">
                                <h3><?= htmlspecialchars($visa['title']) ?></h3>
                                <?php if ($status === 'free'): ?>
                                    <span class="visa-status-badge visa-badge-free">Visa-Free</span>
                                <?php else: ?>
                                    <span class="visa-status-badge visa-badge-required">Visa Required</span>
                                <?php endif; ?>
                            </div>

                            <p><?= htmlspecialchars($visa['description'] ?? '') ?></p>

                            <div class="price-section">
                                <div class="price-tag">
                                    <?= htmlspecialchars($visa['currency']) ?>         <?= number_format($visa['price'], 0) ?>
                                </div>
                                <button class="book-btn"
                                    onclick="window.location.href='visa-details.php?id=<?= intval($visa['id']) ?>'">
                                    View Details <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- No Results -->
        <div id="noResults" class="no-results">
            <i class="fas fa-map-marked-alt"></i>
            <p>Oops! We couldn't find any visa services for that destination.</p>
            <button onclick="clearSearch()"
                style="background:none;border:none;color:#6c5ce7;font-weight:600;cursor:pointer;text-decoration:underline;">Show
                everything</button>
        </div>

        <!-- Common Requirements (uses global checklist from DB if available) -->
        <div class="info-section">
            <h3><i class="fas fa-clipboard-list"></i> Common Requirements</h3>
            <div class="requirements">
                <?php if (!empty($global_visa_checklist)): ?>
                    <?php foreach ($global_visa_checklist as $item): ?>
                        <div class="req-item"><i class="fas fa-check-circle"></i>
                            <div>
                                <h4><?= htmlspecialchars($item) ?></h4>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="req-item"><i class="fas fa-passport"></i>
                        <div>
                            <h4>Valid Passport</h4>
                            <p>6 months validity</p>
                        </div>
                    </div>
                    <div class="req-item"><i class="fas fa-camera"></i>
                        <div>
                            <h4>Photos</h4>
                            <p>2x2 white background</p>
                        </div>
                    </div>
                    <div class="req-item"><i class="fas fa-plane"></i>
                        <div>
                            <h4>Flight Itinerary</h4>
                            <p>Round-trip booking</p>
                        </div>
                    </div>
                    <div class="req-item"><i class="fas fa-hotel"></i>
                        <div>
                            <h4>Hotel Booking</h4>
                            <p>Confirmed accommodation</p>
                        </div>
                    </div>
                    <div class="req-item"><i class="fas fa-file-invoice-dollar"></i>
                        <div>
                            <h4>Bank Statement</h4>
                            <p>3-6 months history</p>
                        </div>
                    </div>
                    <div class="req-item"><i class="fas fa-briefcase"></i>
                        <div>
                            <h4>Employment Proof</h4>
                            <p>Certificate of employment</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="back-button-container"><button class="back-button" onclick="window.location.href='../index.php'"><i
                class="fas fa-arrow-left"></i> Back to Home</button></div>

    <script>
        let dropdownHighlight = -1;

        // Collect all visa card titles + category from the page on load
        function getVisaList() {
            const list = [];
            document.querySelectorAll('.service-grid').forEach(grid => {
                const headline = grid.previousElementSibling;
                const cat = headline ? headline.innerText.replace(' Destinations', '').trim() : '';
                grid.querySelectorAll('.service-card').forEach(card => {
                    const title = card.querySelector('h3')?.innerText.trim() || '';
                    if (title) list.push({ title, cat, card });
                });
            });
            return list;
        }

        function highlightMatch(text, query) {
            if (!query) return text;
            const idx = text.toLowerCase().indexOf(query.toLowerCase());
            if (idx === -1) return text;
            return text.slice(0, idx) + '<em>' + text.slice(idx, idx + query.length) + '</em>' + text.slice(idx + query.length);
        }

        // Country name → ISO 2-letter code map (for flagcdn.com images)
        const countryFlagMap = {
            'afghanistan': 'af', 'albania': 'al', 'algeria': 'dz', 'andorra': 'ad', 'angola': 'ao',
            'argentina': 'ar', 'armenia': 'am', 'australia': 'au', 'austria': 'at', 'azerbaijan': 'az',
            'bahrain': 'bh', 'bangladesh': 'bd', 'belarus': 'by', 'belgium': 'be', 'belize': 'bz',
            'benin': 'bj', 'bhutan': 'bt', 'bolivia': 'bo', 'bosnia': 'ba', 'botswana': 'bw',
            'brazil': 'br', 'brunei': 'bn', 'bulgaria': 'bg', 'burkina faso': 'bf', 'burundi': 'bi',
            'cambodia': 'kh', 'cameroon': 'cm', 'canada': 'ca', 'chile': 'cl', 'china': 'cn',
            'colombia': 'co', 'costa rica': 'cr', 'croatia': 'hr', 'cuba': 'cu', 'cyprus': 'cy',
            'czech republic': 'cz', 'czechia': 'cz', 'denmark': 'dk', 'djibouti': 'dj',
            'dominican republic': 'do', 'ecuador': 'ec', 'egypt': 'eg', 'el salvador': 'sv',
            'estonia': 'ee', 'ethiopia': 'et', 'fiji': 'fj', 'finland': 'fi', 'france': 'fr',
            'gabon': 'ga', 'georgia': 'ge', 'germany': 'de', 'ghana': 'gh', 'greece': 'gr',
            'guatemala': 'gt', 'guinea': 'gn', 'haiti': 'ht', 'honduras': 'hn', 'hong kong': 'hk',
            'hungary': 'hu', 'iceland': 'is', 'india': 'in', 'indonesia': 'id', 'iran': 'ir',
            'iraq': 'iq', 'ireland': 'ie', 'israel': 'il', 'italy': 'it', 'jamaica': 'jm',
            'japan': 'jp', 'jordan': 'jo', 'kazakhstan': 'kz', 'kenya': 'ke', 'kuwait': 'kw',
            'kyrgyzstan': 'kg', 'laos': 'la', 'latvia': 'lv', 'lebanon': 'lb', 'libya': 'ly',
            'liechtenstein': 'li', 'lithuania': 'lt', 'luxembourg': 'lu', 'macau': 'mo',
            'madagascar': 'mg', 'malaysia': 'my', 'maldives': 'mv', 'mali': 'ml', 'malta': 'mt',
            'mauritius': 'mu', 'mexico': 'mx', 'moldova': 'md', 'monaco': 'mc', 'mongolia': 'mn',
            'montenegro': 'me', 'morocco': 'ma', 'mozambique': 'mz', 'myanmar': 'mm', 'namibia': 'na',
            'nepal': 'np', 'netherlands': 'nl', 'new zealand': 'nz', 'nicaragua': 'ni', 'niger': 'ne',
            'nigeria': 'ng', 'north korea': 'kp', 'north macedonia': 'mk', 'norway': 'no', 'oman': 'om',
            'pakistan': 'pk', 'panama': 'pa', 'papua new guinea': 'pg', 'paraguay': 'py', 'peru': 'pe',
            'philippines': 'ph', 'poland': 'pl', 'portugal': 'pt', 'qatar': 'qa', 'romania': 'ro',
            'russia': 'ru', 'rwanda': 'rw', 'saudi arabia': 'sa', 'senegal': 'sn', 'serbia': 'rs',
            'singapore': 'sg', 'slovakia': 'sk', 'slovenia': 'si', 'somalia': 'so', 'south africa': 'za',
            'south korea': 'kr', 'spain': 'es', 'sri lanka': 'lk', 'sudan': 'sd', 'sweden': 'se',
            'switzerland': 'ch', 'syria': 'sy', 'taiwan': 'tw', 'tajikistan': 'tj', 'tanzania': 'tz',
            'thailand': 'th', 'timor-leste': 'tl', 'togo': 'tg', 'trinidad and tobago': 'tt',
            'tunisia': 'tn', 'turkey': 'tr', 'turkmenistan': 'tm', 'uganda': 'ug', 'ukraine': 'ua',
            'united arab emirates': 'ae', 'uae': 'ae', 'united kingdom': 'gb', 'uk': 'gb',
            'united states': 'us', 'usa': 'us', 'us': 'us', 'uruguay': 'uy', 'uzbekistan': 'uz',
            'venezuela': 've', 'vietnam': 'vn', 'yemen': 'ye', 'zambia': 'zm', 'zimbabwe': 'zw'
        };

        function getCountryFlagImg(countryName) {
            let key = countryName.toLowerCase().replace('visa', '').trim();
            let code = countryFlagMap[key] || null;

            if (!code) {
                // Try to find a match where the country name is PART of the key or vice versa
                for (const [country, isoCode] of Object.entries(countryFlagMap)) {
                    if (key === country || key.startsWith(country + ' ') || key.endsWith(' ' + country) || key.includes(' ' + country + ' ')) {
                        code = isoCode;
                        break;
                    }
                }
            }

            if (!code) {
                // Last resort: check if any key in our map is contained in the title
                for (const [country, isoCode] of Object.entries(countryFlagMap)) {
                    if (country.length > 2 && key.includes(country)) {
                        code = isoCode;
                        break;
                    }
                }
            }

            if (code) {
                return `<img src="https://flagcdn.com/w40/${code}.png" 
                             srcset="https://flagcdn.com/w80/${code}.png 2x"
                             alt="${countryName} flag"
                             style="width:38px;height:38px;object-fit:cover;border-radius:50%;border:2px solid #e8eaf0;display:block;">`;
            }
            return `<div style="width:38px;height:38px;background:linear-gradient(135deg,#6c5ce7,#8a7cff);border-radius:50%;display:flex;align-items:center;justify-content:center;"><i class='fas fa-globe' style='color:white;font-size:0.9rem;'></i></div>`;
        }

        let currentContinent = 'All';

        function filterByContinent(continent, element) {
            currentContinent = continent;

            // Update active state
            document.querySelectorAll('.continent-card').forEach(card => card.classList.remove('active'));
            element.classList.add('active');

            // Clear search when changing continent to show all for that continent
            document.getElementById('visaSearchInput').value = '';

            // Perform filtering
            filterVisas();

            // Scroll to content on mobile
            if (window.innerWidth < 768) {
                document.querySelector('.service-content').scrollIntoView({ behavior: 'smooth' });
            }
        }

        function filterVisas() {
            const input = document.getElementById('visaSearchInput');
            if (!input) return;
            const filter = input.value.toLowerCase().trim();
            const continentFilter = currentContinent.toLowerCase();
            const dropdown = document.getElementById('visaDropdown');
            const matchCountEl = document.getElementById('visaMatchCount');
            const noResultsEl = document.getElementById('noResults');

            const visaList = getVisaList();
            let totalFound = 0;

            visaList.forEach(v => {
                const titleLower = v.title.toLowerCase();
                let titleMatch = false;

                if (filter.length === 0) {
                    titleMatch = true;
                } else if (filter.length === 1) {
                    // Strictly starting with the letter as requested
                    titleMatch = titleLower.startsWith(filter);
                } else {
                    // Including the text for multi-character search
                    titleMatch = titleLower.includes(filter);
                }

                const catLower = v.cat.toLowerCase();
                let continentMatch = continentFilter === 'all' || catLower.includes(continentFilter);

                // Map common country/region overlaps for easier filtering
                if (continentFilter === 'oceania' && catLower.includes('australia')) continentMatch = true;
                if (continentFilter === 'north america' && (catLower.includes('usa') || catLower.includes('united states'))) continentMatch = true;
                if (continentFilter === 'north america' && catLower.includes('canada')) continentMatch = true;

                if (titleMatch && continentMatch) {
                    v.card.classList.remove('hidden');
                    v.card.style.display = 'flex';
                    totalFound++;
                } else {
                    v.card.classList.add('hidden');
                    v.card.style.display = 'none';
                }
            });

            // Update section visibility
            document.querySelectorAll('.service-grid').forEach(grid => {
                const visibleInGrid = grid.querySelectorAll('.service-card:not(.hidden)').length;
                const title = grid.previousElementSibling;
                if (visibleInGrid === 0) {
                    grid.style.display = 'none';
                    if (title && title.classList.contains('category-title')) title.style.display = 'none';
                } else {
                    grid.style.display = 'grid';
                    if (title && title.classList.contains('category-title')) title.style.display = 'block';
                }
            });

            // Show/Hide No Results
            if (totalFound === 0) {
                noResultsEl.style.display = 'flex';
            } else {
                noResultsEl.style.display = 'none';
            }

            matchCountEl.style.display = filter.length > 0 ? 'block' : 'none';
            matchCountEl.innerText = `${totalFound} found`;

            // Handle dropdown if searching
            if (filter.length > 0) {
                const matches = visaList.filter(v => {
                    const t = v.title.toLowerCase();
                    const c = v.cat.toLowerCase();
                    let matchContinent = continentFilter === 'all' || c.includes(continentFilter);
                    if (continentFilter === 'oceania' && c.includes('australia')) matchContinent = true;
                    if (continentFilter === 'north america' && (c.includes('usa') || c.includes('united states') || c.includes('canada'))) matchContinent = true;
                    return (filter.length === 1 ? t.startsWith(filter) : t.includes(filter)) && matchContinent;
                });

                dropdown.innerHTML = '';
                if (matches.length > 0) {
                    const header = document.createElement('div');
                    header.className = 'dropdown-header';
                    header.innerHTML = `<i class="fas fa-map-marker-alt" style="margin-right:5px;color:#6c5ce7;"></i>${matches.length} destination${matches.length > 1 ? 's' : ''} found`;
                    dropdown.appendChild(header);

                    matches.forEach((v, i) => {
                        const item = document.createElement('div');
                        item.className = 'dropdown-item-visa';
                        item.setAttribute('data-index', i);
                        const flagHtml = getCountryFlagImg(v.title);
                        item.innerHTML = `
                            <div class="di-flag">${flagHtml}</div>
                            <div>
                                <div class="di-label">${highlightMatch(v.title, input.value.trim())}</div>
                                ${v.cat ? `<div class="di-cat"><i class="fas fa-tag" style="font-size:0.6rem;"></i> ${v.cat}</div>` : ''}
                            </div>`;
                        item.addEventListener('mousedown', () => selectDropdownItem(v.title));
                        dropdown.appendChild(item);
                    });
                } else {
                    dropdown.innerHTML = `<div class="dropdown-empty"><i class="fas fa-search"></i>No destinations match "${input.value.trim()}" in ${currentContinent}</div>`;
                }
                dropdown.classList.add('open');
            } else {
                dropdown.classList.remove('open');
                dropdown.innerHTML = '';
            }
        }

        function selectDropdownItem(title) {
            const input = document.getElementById('visaSearchInput');
            input.value = title;
            closeDropdown();
            filterVisas();
            // Scroll to first matching card
            const firstVisible = document.querySelector('.service-card:not(.hidden)');
            if (firstVisible) firstVisible.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function closeDropdown() {
            const d = document.getElementById('visaDropdown');
            d.classList.remove('open');
            d.innerHTML = '';
            dropdownHighlight = -1;
        }

        function handleDropdownKey(e) {
            const dropdown = document.getElementById('visaDropdown');
            const items = dropdown.querySelectorAll('.dropdown-item-visa');
            if (!dropdown.classList.contains('open') || items.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                dropdownHighlight = Math.min(dropdownHighlight + 1, items.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                dropdownHighlight = Math.max(dropdownHighlight - 1, 0);
            } else if (e.key === 'Enter') {
                if (dropdownHighlight >= 0) {
                    e.preventDefault();
                    const label = items[dropdownHighlight].querySelector('.di-label');
                    selectDropdownItem(label ? label.innerText : '');
                } else {
                    closeDropdown();
                }
                return;
            } else if (e.key === 'Escape') {
                closeDropdown(); return;
            }
            items.forEach((item, i) => item.classList.toggle('highlighted', i === dropdownHighlight));
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', e => {
            if (!e.target.closest('.search-container')) closeDropdown();
        });

        function clearSearch() {
            document.getElementById('visaSearchInput').value = '';
            closeDropdown();
            filterVisas();
            document.getElementById('visaSearchInput').focus();
        }
    </script>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-logo-section">
                <div class="footer-logo"><img src="../images/Heydream Logo.png" alt="HeyDream Logo"
                        class="footer-logo-img"><span class="footer-brand">HeyDream</span></div>
                <div class="footer-country"><i class="fas fa-globe"></i> Philippines (Pilipinas)</div>
            </div>
            <div class="footer-links-grid">
                <div class="footer-column">
                    <h4>Contact Us</h4>
                    <ul class="contact-list">
                        <li><i class="fas fa-map-marker-alt"></i> 3104 Tektite East Tower, Philippine Stock Exchange,
                            Ortigas</li>
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
                <div class="social-icons"><a href="#"><i class="fab fa-facebook-f"></i></a><a href="#"><i
                            class="fab fa-instagram"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a
                        href="#"><i class="fab fa-tiktok"></i></a></div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 HeyDream Travel &amp; Tours. All rights reserved.</p>
        </div>
    </footer>

    <!-- Visa Gate Modal -->
    <div class="gate-modal" id="visaGateModal">
        <div class="gate-content">
            <div class="gate-header">
                <h2><i class="fas fa-clipboard-check"></i> Application Checklist</h2>
                <span class="gate-close" onclick="closeGateModal()">&times;</span>
            </div>
            <div class="gate-body">
                <div class="gate-disclaimer" id="gateDisclaimerEl" style="display:none;">
                    <i class="fas fa-exclamation-triangle" style="margin-right:5px;"></i>
                    <span id="gateDisclaimerText"></span>
                </div>
                <div style="margin-bottom:20px; text-align:left;">
                    <h3 id="gateVisaTitle" style="color:#003580; margin-bottom:5px;"></h3>
                    <p id="gateVisaDesc" style="color:#555; margin-bottom:15px; font-size:0.95rem; display:none;"></p>
                    <div style="background:#f8f9fa; padding:15px; border-radius:8px;">
                        <h4 style="margin:0 0 10px; color:#333;">Required Documents:</h4>
                        <ul id="gateReqsContainer" style="margin:0; padding-left:20px; color:#555; font-size:0.9rem;">
                        </ul>
                    </div>
                </div>
                <div style="text-align:center; color:#555; margin-bottom:15px;">
                    <h4 style="color:#333; margin-bottom:5px;">Ready to proceed?</h4>
                    <p style="font-size:0.9rem;">Make sure you have the documents listed above before you start.</p>
                </div>
                <div id="gateImportantNotes"
                    style="display:none; background:#e8f4fd; border-left:4px solid #2196F3; padding:12px 15px; border-radius:0 8px 8px 0; margin-bottom:15px;">
                    <h4 style="margin:0 0 8px; color:#1565C0; font-size:0.9rem;"><i class="fas fa-info-circle"
                            style="margin-right:5px;"></i>Important Notes</h4>
                    <div id="gateNotesContent" style="color:#333; font-size:0.85rem; line-height:1.6;"></div>
                </div>
            </div>
            <div class="gate-footer">
                <button class="gate-proceed-btn" onclick="proceedToVisaBooking()">
                    Yes, Apply Now <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        let gateTargetVisa = null;

        function showVisaGate(btnElement, title, price, duration) {
            document.getElementById('gateVisaTitle').innerText = title + ' Visa Application';

            // Description
            const descText = btnElement.getAttribute('data-desc') || '';
            const descEl = document.getElementById('gateVisaDesc');
            if (descText.trim()) { descEl.innerText = descText; descEl.style.display = 'block'; }
            else descEl.style.display = 'none';

            // Disclaimer
            const disclaimerText = btnElement.getAttribute('data-disclaimer') || '';
            const discEl = document.getElementById('gateDisclaimerEl');
            if (disclaimerText.trim()) { document.getElementById('gateDisclaimerText').innerText = disclaimerText; discEl.style.display = 'block'; }
            else discEl.style.display = 'none';

            // Requirements
            let reqsArray = [];
            try { reqsArray = JSON.parse(btnElement.getAttribute('data-reqs') || '[]'); } catch (e) { }
            gateTargetVisa = { title, price, duration, requirements: reqsArray };
            const reqsContainer = document.getElementById('gateReqsContainer');
            reqsContainer.innerHTML = reqsArray.length
                ? reqsArray.map(item => `<li>${item.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</li>`).join('')
                : '<li style="list-style:none;margin-left:-20px;">No specific documents listed.</li>';

            // Important Notes
            const notesText = btnElement.getAttribute('data-notes') || '';
            const notesEl = document.getElementById('gateImportantNotes');
            if (notesText.trim()) {
                document.getElementById('gateNotesContent').innerHTML = notesText.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                notesEl.style.display = 'block';
            } else notesEl.style.display = 'none';

            document.getElementById('visaGateModal').classList.add('active');
        }

        function closeGateModal() { document.getElementById('visaGateModal').classList.remove('active'); gateTargetVisa = null; }

        function proceedToVisaBooking() {
            const v = gateTargetVisa;
            closeGateModal();
            if (v) requireLogin('showVisaBooking', v.title, v.price, v.duration, v.requirements);
        }

        // Inject user info
        window.currentUserEmail = '<?= isset($_SESSION['user_id']) ? $auth->getCurrentUser()['email'] : '' ?>';
        window.currentFullName = '<?= isset($_SESSION['user_id']) ? htmlspecialchars($auth->getCurrentUser()['full_name']) : '' ?>';
    </script>

    <script>
        let currentVisa = null, visaBookingData = null, selectedPayment = null, visaDocumentFiles = [];
        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
        function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { const btn = event.target; const orig = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#28a745'; btn.style.color = 'white'; setTimeout(() => { btn.textContent = orig; btn.style.background = '#e0e0e0'; btn.style.color = ''; }, 1500); }); }
        function handleFileUpload(event, paymentMethod) { const file = event.target.files[0]; if (file) { if (!file.type.match('image.*')) { alert('Please upload an image file.'); event.target.value = ''; return; } if (file.size > 5 * 1024 * 1024) { alert('File too large. Max 5MB.'); event.target.value = ''; return; } const reader = new FileReader(); reader.onload = e => { const p = document.getElementById(`preview-${paymentMethod}`); if (p) p.innerHTML = `<img src="${e.target.result}" alt="Payment Proof">`; }; reader.readAsDataURL(file); const fn = document.getElementById(`file-name-${paymentMethod}`); if (fn) fn.textContent = file.name; } }

        function updateVisaLiveTotal(val) {
            const num = parseInt(val) || 0;
            const price = currentVisa.price || 0;
            const display = document.getElementById('visa-live-total-val');
            if (display) {
                display.innerText = '₱' + (price * num).toLocaleString();
            }
        }

        function showVisaBooking(title, price, duration, requirements) {
            currentVisa = { title, price, duration, requirements: requirements || [] };
            visaDocumentFiles = [];
            let modal = document.getElementById('visaBookingModal');
            if (!modal) {
                modal = document.createElement('div'); modal.id = 'visaBookingModal'; modal.className = 'booking-modal';
                modal.innerHTML = `<div class="booking-modal-content"><div class="booking-modal-header"><span class="close-modal" onclick="closeVisaBookingModal()">&times;</span><h2><i class="fas fa-passport"></i> Visa Application</h2><p>Complete your application</p></div><div class="booking-modal-body"><div class="booking-steps"><div class="step active" id="step1Indicator"><div class="step-number">1</div><div class="step-label">Details</div><div class="step-line" style="width: 100%;"></div></div><div class="step" id="step2Indicator"><div class="step-number">2</div><div class="step-label">Documents</div><div class="step-line" style="width: 100%;"></div></div><div class="step" id="step3Indicator"><div class="step-number">3</div><div class="step-label">Review</div><div class="step-line" style="width: 100%;"></div></div><div class="step" id="step4Indicator"><div class="step-number">4</div><div class="step-label">Confirmation</div></div></div><div id="step1Content" class="step-content active"></div><div id="step2Content" class="step-content"></div><div id="step3Content" class="step-content"></div><div id="step4Content" class="step-content"></div></div></div>`;
                document.body.appendChild(modal);
                modal.addEventListener('click', e => { if (e.target === modal) closeVisaBookingModal(); });
            }
            renderVisaStep1(); modal.classList.add('active');
        }

        function renderVisaStep1() {
            document.getElementById('step1Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-passport"></i></div><div class="service-info"><h3>${currentVisa.title}</h3><p class="service-price">₱${formatNumber(currentVisa.price)}</p><p class="service-duration">${currentVisa.duration}</p></div></div>
                <form id="visaForm" onsubmit="return false;">
                    <div class="form-section"><h4><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></h4>
                        <div class="form-group"><input type="email" id="applicationEmail" value="${window.currentUserEmail || ''}" placeholder="Your email address" required></div>
                        <p style="font-size: 0.75rem; color: #666; margin-top: -10px;">The agents will contact you at this email address to confirm your application.</p>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-user"></i> Applicant Information</h4>
                        <div class="form-group"><label>Full Name <span class="required">*</span></label><input type="text" id="fullName" value="${window.currentFullName || ''}" placeholder="As in passport"></div>
                        <div class="form-group"><label>Phone <span class="required">*</span></label><input type="tel" id="phone" placeholder="+63 912 345 6789"></div>
                        <div class="form-row"><div class="form-group"><label>Date of Birth <span class="required">*</span></label><input type="date" id="dob" max="${new Date().toISOString().split('T')[0]}"></div>
                        <div class="form-group"><label>Passport Number <span class="required">*</span></label><input type="text" id="passportNum" placeholder="Passport number"></div></div>
                        <div class="form-row"><div class="form-group"><label>Passport Expiry <span class="required">*</span></label><input type="date" id="passportExpiry"></div>
                        <div class="form-group"><label>Address <span class="required">*</span></label><input type="text" id="address" placeholder="Complete address"></div></div>
                        <div class="form-group">
                            <label>Number of Applicants <span class="required">*</span></label>
                            <input type="number" id="applicants" value="1" min="1" max="50" oninput="updateVisaLiveTotal(this.value)">
                        </div>
                        <div id="visa-live-total-display" style="margin-top:15px; padding:15px; background:#f0f9ff; border-radius:12px; border:1px solid #bae6fd; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:700; color:#1e3a8a; font-size:0.85rem;">Estimated Total Fee:</span>
                            <span id="visa-live-total-val" style="font-size:1.1rem; font-weight:900; color:#006ce4;">₱${formatNumber(currentVisa.price)}</span>
                        </div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-map-marked-alt"></i> Travel Details</h4>
                        <div class="form-row"><div class="form-group"><label>Destination <span class="required">*</span></label><input type="text" id="destination" placeholder="Country name"></div>
                        <div class="form-group"><label>Embassy/Consulate</label><select id="embassy"><option value="manila">Manila</option><option value="cebu">Cebu</option><option value="davao">Davao</option></select></div></div>
                        <div class="form-row"><div class="form-group"><label>Target Travel Date (Optional)</label><input type="date" id="travelDate" min="${new Date().toISOString().split('T')[0]}"></div>
                        <div class="form-group"><label>Processing</label><select id="processing"><option value="regular">Regular (10-15 days)</option><option value="urgent">Urgent (3-5 days, +₱3,000)</option><option value="express">Express (24h, +₱5,000)</option></select></div></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-file-alt"></i> Additional Information</h4>
                        <div class="form-group"><label>Occupation</label><input type="text" id="occupation" placeholder="Your job title"></div>
                        <div class="form-group"><label>Travel History</label><textarea id="travelHistory" rows="2" placeholder="Countries visited in last 5 years"></textarea></div>
                    </div>
                    <div id="step1Errors" class="error-message" style="display:none;"></div>
                    <div class="action-buttons"><button type="button" class="btn-next" onclick="validateAndGoToStep2()">Review Application <i class="fas fa-arrow-right"></i></button></div>
                </form>`;
        }

        function validateAndGoToStep2() {
            const errors = [], fn = v => document.getElementById(v)?.value.trim();
            const fullName = fn('fullName'), phone = fn('phone'), dob = fn('dob'), passportNum = fn('passportNum'), passportExpiry = fn('passportExpiry'), address = fn('address'), destination = fn('destination'), travelDate = fn('travelDate') || '', email = fn('applicationEmail'), applicants = fn('applicants');
            if (!email) errors.push('Email is required');
            if (!fullName) errors.push('Full Name is required');
            if (!phone) errors.push('Phone number is required');
            if (!dob) errors.push('Date of Birth is required');
            if (!passportNum) errors.push('Passport Number is required');
            if (!passportExpiry) errors.push('Passport Expiry Date is required');
            if (!address) errors.push('Address is required');
            if (!destination) errors.push('Destination is required');
            if (!applicants || parseInt(applicants) < 1) errors.push('Please enter at least 1 applicant');

            document.querySelectorAll('.form-group input,.form-group select').forEach(f => f.classList.remove('error'));
            ['applicationEmail', 'fullName', 'phone', 'dob', 'passportNum', 'passportExpiry', 'address', 'destination'].forEach(id => { if (!fn(id)) document.getElementById(id)?.classList.add('error'); });
            if (errors.length > 0) { const e = document.getElementById('step1Errors'); e.style.display = 'flex'; e.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`; e.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
            renderVisaStep2Documents();
        }

        function handleVisaDocSelect(event, index) {
            const file = event.target.files[0];
            const nameEl = document.getElementById(`visa-doc-name-${index}`);
            if (!file) { if (nameEl) nameEl.textContent = 'No file selected'; return; }
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload a PDF or image file (JPG, PNG, WEBP).');
                event.target.value = '';
                if (nameEl) nameEl.textContent = 'No file selected';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                alert('File too large. Max 10MB.');
                event.target.value = '';
                if (nameEl) nameEl.textContent = 'No file selected';
                return;
            }
            if (nameEl) nameEl.textContent = file.name;
        }

        function renderVisaStep2Documents() {
            const reqs = (currentVisa.requirements && currentVisa.requirements.length > 0)
                ? currentVisa.requirements
                : ['Supporting Documents (optional)'];

            const uploadBlocks = reqs.map((label, index) => `
                <div class="form-group">
                    <label>${escapeHtml(label)}</label>
                    <div class="file-upload" onclick="document.getElementById('visaDoc${index}').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload (PDF or image)</p>
                        <p class="file-name" id="visa-doc-name-${index}">No file selected</p>
                        <input type="file" id="visaDoc${index}" accept="application/pdf,image/*" style="display:none" onchange="handleVisaDocSelect(event, ${index})">
                    </div>
                </div>
            `).join('');

            document.getElementById('step2Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-passport"></i></div><div class="service-info"><h3>${currentVisa.title}</h3><p class="service-price">${escapeHtml(currentVisa.duration || '')}</p></div></div>
                <div style="background: #e8f0fe; padding: 12px; border-radius: 10px; font-size: 0.8rem; color: #003580; margin-bottom: 15px;">
                    <i class="fas fa-info-circle"></i> Uploading now is optional and helps speed up review — you can also add or update these later from My Bookings.
                </div>
                <form onsubmit="return false;">
                    ${uploadBlocks}
                </form>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="goToVisaStep1()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-next" onclick="goToVisaStep3()">Continue to Review <i class="fas fa-arrow-right"></i></button></div>`;
            updateVisaSteps(2);
        }

        function goToVisaStep3() {
            const reqs = (currentVisa.requirements && currentVisa.requirements.length > 0)
                ? currentVisa.requirements
                : ['Supporting Documents (optional)'];
            visaDocumentFiles = [];
            reqs.forEach((label, index) => {
                const input = document.getElementById(`visaDoc${index}`);
                const file = input?.files?.[0];
                if (file) visaDocumentFiles.push({ file, label });
            });

            const gv = id => document.getElementById(id)?.value;
            const fullName = gv('fullName'), email = gv('applicationEmail'), phone = gv('phone'), dob = gv('dob'), passportNum = gv('passportNum'), passportExpiry = gv('passportExpiry'), address = gv('address'), destination = gv('destination'), embassy = gv('embassy'), travelDate = gv('travelDate'), processing = gv('processing'), occupation = gv('occupation'), travelHistory = gv('travelHistory'), applicants = parseInt(gv('applicants')) || 1;
            let addAmountPerPerson = 0, processingLabel = 'Regular';
            if (processing === 'urgent') { addAmountPerPerson = 3000; processingLabel = 'Urgent (3-5 days)'; } else if (processing === 'express') { addAmountPerPerson = 5000; processingLabel = 'Express (24h)'; }
            const total = (currentVisa.price + addAmountPerPerson) * applicants;
            visaBookingData = { fullName, email, phone, dob, passportNum, passportExpiry, address, destination, embassy, travelDate, processingLabel, occupation, travelHistory, total, applicants, addAmountPerPerson };
            document.getElementById('step3Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-passport"></i></div><div class="service-info"><h3>${currentVisa.title}</h3><p class="service-price">₱${formatNumber(total)}</p></div></div>
                <div class="review-details"><div class="review-section"><h4>Applicant Info</h4><div class="review-row"><div class="review-label">Name:</div><div class="review-value">${escapeHtml(fullName)}</div></div><div class="review-row"><div class="review-label">Applicants:</div><div class="review-value">${applicants} Person${applicants > 1 ? 's' : ''}</div></div><div class="review-row"><div class="review-label">Passport:</div><div class="review-value">${escapeHtml(passportNum)} (Exp: ${new Date(passportExpiry).toLocaleDateString()})</div></div><div class="review-row"><div class="review-label">Email:</div><div class="review-value">${escapeHtml(email)}</div></div><div class="review-row"><div class="review-label">Phone:</div><div class="review-value">${escapeHtml(phone)}</div></div></div>
                <div class="review-section"><h4>Travel Details</h4><div class="review-row"><div class="review-label">Destination:</div><div class="review-value">${escapeHtml(destination)}</div></div><div class="review-row"><div class="review-label">Embassy:</div><div class="review-value">${embassy === 'manila' ? 'Manila' : embassy === 'cebu' ? 'Cebu' : 'Davao'}</div></div><div class="review-row"><div class="review-label">Travel Date:</div><div class="review-value">${travelDate ? new Date(travelDate).toLocaleDateString() : 'To be determined'}</div></div><div class="review-row"><div class="review-label">Processing:</div><div class="review-value">${processingLabel}</div></div></div>
                <div class="review-section"><h4>Documents</h4><div class="review-row"><div class="review-label">Uploaded:</div><div class="review-value">${visaDocumentFiles.length} of ${reqs.length} document${reqs.length > 1 ? 's' : ''}</div></div></div>
                <div class="review-section"><h4>Fee Summary</h4><div class="review-row"><div class="review-label">Visa Fee:</div><div class="review-value">₱${formatNumber(currentVisa.price)} x ${applicants}</div></div>${visaBookingData.addAmountPerPerson > 0 ? `<div class="review-row"><div class="review-label">Processing:</div><div class="review-value">+₱${formatNumber(visaBookingData.addAmountPerPerson)} x ${applicants}</div></div>` : ''}<div class="review-row"><div class="review-label">Total to Pay:</div><div class="review-value" style="color:#ff9800; font-weight:800;">₱${formatNumber(total)}</div></div></div>
                <div style="background: #e8f0fe; padding: 12px; border-radius: 10px; font-size: 0.75rem; color: #003580; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> After submitting, an agent will review your application and contact you for the payment process and remaining document collection.
                </div>
                </div>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="updateVisaSteps(2)"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="submit-booking-btn" onclick="submitVisaApplication()"><i class="fas fa-paper-plane"></i> Submit Application</button></div>`;
            updateVisaSteps(3);
        }

        function goToVisaStep1() { updateVisaSteps(1); setTimeout(() => { if (visaBookingData) { ['fullName', 'phone', 'passportNum', 'destination', 'travelDate', 'applicationEmail'].forEach(id => { const el = document.getElementById(id); if (el) el.value = visaBookingData[id] || ''; }); } }, 50); }

        function uploadVisaDocuments(bookingNumber) {
            const uploads = visaDocumentFiles.map(({ file }) => {
                const fd = new FormData();
                fd.append('action', 'upload');
                fd.append('booking_number', bookingNumber);
                fd.append('document', file);
                return fetch('../User Account/api/upload-api.php', { method: 'POST', body: fd })
                    .catch(err => { console.error('Document upload failed:', err); });
            });
            return Promise.all(uploads);
        }

        function submitVisaApplication() {
            if (!currentVisa || !visaBookingData) {
                alert('Your application session has expired or was reset. Please close this window and start over.');
                return;
            }

            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            try {
                fetch('../api/save-service-booking.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_type: 'Visa Assistance', package_name: currentVisa.title,
                        package_duration: visaBookingData.processingLabel, price_per_person: currentVisa.price,
                        full_name: visaBookingData.fullName, email: visaBookingData.email, phone: visaBookingData.phone,
                        travelers: visaBookingData.applicants,
                        travel_date: visaBookingData.travelDate,
                        special_requests: `Applicants: ${visaBookingData.applicants}, Passport: ${visaBookingData.passportNum}, DOB: ${visaBookingData.dob}, Address: ${visaBookingData.address}, Destination: ${visaBookingData.destination}, Embassy: ${visaBookingData.embassy}, Occupation: ${visaBookingData.occupation}, Travel History: ${visaBookingData.travelHistory}`,
                        total_amount: visaBookingData.total, payment_method: 'Manual Agent Approval',
                        payment_reference: 'PENDING_AGENT'
                    })
                }).then(r => r.json()).then(async data => {
                    if (data.success) {
                        if (visaDocumentFiles.length > 0) {
                            await uploadVisaDocuments(data.booking_number);
                        }
                        document.getElementById('step4Content').innerHTML = `<div class="success-message"><i class="fas fa-clock" style="color:#ff9800;"></i><h2>📄 Application Received!</h2><p>Your application is now being reviewed by our agents.</p><div class="booking-number">Application Reference: ${data.booking_number}</div><div class="details-card"><h4>📋 Next Steps:</h4><p>1. Our expert agents will review your details manually.</p><p>2. You will receive an email at <strong>${visaBookingData.email}</strong> once approved.</p><p>3. Upon approval, we will guide you through the document collection and final payment.</p></div><div class="payment-status-pending" style="background: #e8f4fd; color: #004085;"><i class="fas fa-user-tie"></i> Please wait for the confirmation of our agents to approve your application.</div><div class="action-buttons"><button class="submit-booking-btn btn-primary" onclick="window.location.href='../User Account/profile.php?track=' + encodeURIComponent('${data.booking_number}')"><i class="fas fa-file-upload"></i> View My Application</button><button class="submit-booking-btn btn-secondary" onclick="closeVisaBookingModal();location.reload();"><i class="fas fa-check"></i> Understood</button></div></div>`;
                        updateVisaSteps(4);
                    } else { btn.disabled = false; btn.innerHTML = originalHtml; alert('Error: ' + data.message); }
                }).catch(() => { btn.disabled = false; btn.innerHTML = originalHtml; alert('Connection error. Please try again.'); });
            } catch (err) {
                btn.disabled = false; btn.innerHTML = originalHtml;
                console.error('Application submission error:', err);
                alert('Something went wrong while submitting your application: ' + err.message + '. Please try again.');
            }
        }

        function updateVisaSteps(step) { for (let i = 1; i <= 4; i++) { const ind = document.getElementById(`step${i}Indicator`), cont = document.getElementById(`step${i}Content`); if (i < step) { ind.classList.add('completed'); ind.classList.remove('active'); } else if (i === step) { ind.classList.add('active'); ind.classList.remove('completed'); } else { ind.classList.remove('active', 'completed'); } if (i === step) cont.classList.add('active'); else cont.classList.remove('active'); } }
        function closeVisaBookingModal() { const m = document.getElementById('visaBookingModal'); if (m) m.classList.remove('active'); visaBookingData = null; selectedPayment = null; visaDocumentFiles = []; }
    </script>
    <script src="../js/auth-menu.js"></script>
    <script src="../js/main.js"></script>

    <!-- ── Entrance Animation Stagger ── -->
    <script>
    (function () {
        // Stagger helper: adds .anim-in with an increasing delay
        function staggerIn(selector, baseDelay, step) {
            document.querySelectorAll(selector).forEach(function (el, i) {
                setTimeout(function () {
                    el.classList.add('anim-in');
                }, baseDelay + i * step);
            });
        }

        // IntersectionObserver for elements that might be below the fold
        function observeStagger(selector, step) {
            var items = document.querySelectorAll(selector);
            if (!items.length) return;
            var io = new IntersectionObserver(function (entries, observer) {
                var visibleEntries = entries
                    .filter(function (e) { return e.isIntersecting; })
                    .sort(function (a, b) {
                        return a.target.getBoundingClientRect().top - b.target.getBoundingClientRect().top;
                    });
                visibleEntries.forEach(function (entry, i) {
                    var el = entry.target;
                    setTimeout(function () {
                        el.classList.add('anim-in');
                    }, i * step);
                    observer.unobserve(el);
                });
            }, { threshold: 0.12 });
            items.forEach(function (el) { io.observe(el); });
        }

        // Continent cards: stagger from 750 ms (after hero lands)
        staggerIn('.continent-card', 750, 90);

        // Service cards: use IntersectionObserver so offscreen cards animate on scroll
        observeStagger('.service-card', 70);

        // Requirement items
        observeStagger('.req-item', 60);
    })();
    </script>
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
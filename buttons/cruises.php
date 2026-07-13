<?php
// ========================================
// FILE: buttons/cruises.php
// DESCRIPTION: Cruise Booking with Payment System
// ========================================
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = new Auth($pdo);

// Fetch cruises from advanced inventory
try {
    $stmt = $pdo->query("SELECT * FROM cruises WHERE is_published = 1 ORDER BY id DESC");
    $db_cruises = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_cruises = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Cruise Vacations</title>
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

        /* Premium Cruises Hero Section */
        .cruises-hero-container {
            padding: 100px 1% 20px;
            width: 100%;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease 0.1s, transform 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
        }

        .cruises-hero-container.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        .cruises-hero {
            background: linear-gradient(180deg, #008080 0%, #00B894 40%, #ffffff 100%);
            border-radius: 20px;
            margin: 0 auto;
            width: 98%;
            padding: 30px 5%;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            text-align: left;
            min-height: 319px;
            box-shadow: 0 15px 35px rgba(0, 145, 255, 0.15);
        }

        /* Cruise Ship Watermark Style (Like Flight) */
        .cruises-hero::before {
            content: '';
            position: absolute;
            right: -2%;
            top: 50%;
            transform: translateY(-50%);
            width: 70%;
            height: 200%;
            background: url('../images/cruise-hero.png') no-repeat center center;
            background-size: contain;
            opacity: 0.8;
            z-index: 1;
            pointer-events: none;
            /* High Visibility Highlighting */
            filter: brightness(1.1) contrast(1.05);
            -webkit-mask-image: linear-gradient(to left, black 40%, transparent 98%),
                radial-gradient(ellipse at center, black 50%, transparent 95%);
            mask-image: linear-gradient(to left, black 40%, transparent 98%),
                radial-gradient(ellipse at center, black 50%, transparent 95%);
            -webkit-mask-composite: source-in;
            mask-composite: intersect;
        }

        /* Vertical White Fade for Page Blending */
        .cruises-hero::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom,
                    transparent 40%,
                    rgba(255, 255, 255, 0.2) 60%,
                    rgba(255, 255, 255, 0.7) 85%,
                    #ffffff 100%);
            z-index: 2;
            pointer-events: none;
        }

        .hero-badge-white {
            width: 75px;
            height: 75px;
            background: #ffffff !important;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            margin-right: 18px;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.5);
            z-index: 10;
            position: relative;
        }

        .hero-badge-white i {
            font-size: 2.2rem;
            color: #008080;
        }

        .hero-content-flex {
            display: flex;
            align-items: center;
            width: 100%;
            z-index: 10;
            position: relative;
        }

        .hero-text-content h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin: 0;
            line-height: 1.1;
            letter-spacing: -1.5px;
            color: white !important;
            text-shadow: 0 5px 15px rgba(0, 77, 77, 0.3);
        }

        .hero-description {
            max-width: 600px;
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
            font-weight: 500;
            margin: 20px 0 0;
        }

        /* Partners Section */
        .partners-section {
            padding: 40px 0;
            text-align: center;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .partners-title {
            font-size: 1rem;
            font-weight: 800;
            color: #004d4d;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .partners-grid {
            display: flex;
            justify-content: center;
            flex-wrap: nowrap;
            gap: 20px;
            padding: 10px 10px 20px;
        }

        .partner-card {
            flex-shrink: 0;
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            color: #1a2b48;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            font-size: 0.95rem;
            border: 1px solid rgba(0, 0, 0, 0.03);
            white-space: nowrap;
        }

        .partner-card img {
            height: 24px;
            width: auto;
            object-fit: contain;
        }

        /* Service Cards Redesign */
        .service-content {
            padding: 40px 5%;
        }

        .service-grid {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.07);
            display: flex;
            flex-direction: column !important;
            border: 1px solid #f1f5f9;
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
        }

        .service-card.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Staggered animation delays for cards */
        .service-card:nth-child(1).animate-in {
            transition-delay: 0.55s;
        }

        .service-card:nth-child(2).animate-in {
            transition-delay: 0.70s;
        }

        .service-card:nth-child(3).animate-in {
            transition-delay: 0.85s;
        }

        .service-card:nth-child(4).animate-in {
            transition-delay: 1.00s;
        }

        .service-card:nth-child(5).animate-in {
            transition-delay: 1.15s;
        }

        .service-card:nth-child(6).animate-in {
            transition-delay: 1.30s;
        }

        .service-card:nth-child(7).animate-in {
            transition-delay: 1.45s;
        }

        .service-card:nth-child(8).animate-in {
            transition-delay: 1.60s;
        }

        .service-card:nth-child(9).animate-in {
            transition-delay: 1.75s;
        }

        .card-main-layout {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 0;
            gap: 0;
            width: 100%;
        }

        .service-image-container {
            width: 100%;
            height: 220px;
            border-radius: 0;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }

        .service-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .image-overlay-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 60%, transparent 100%);
            padding: 25px 20px 15px;
            display: flex;
            justify-content: space-between;
            color: white;
            z-index: 5;
        }

        .overlay-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.75rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
        }

        .overlay-item i {
            color: #00B894;
            font-size: 1.1rem;
        }

        .overlay-text {
            display: flex;
            flex-direction: column;
        }

        .overlay-label {
            font-weight: 700;
        }

        .overlay-sub {
            opacity: 0.8;
        }

        .service-card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 18px 18px 10px;
        }

        .service-card h3 {
            font-size: 1.2rem;
            color: #003580;
            margin-bottom: 6px;
            font-weight: 800;
        }

        .title-underline {
            width: 40px;
            height: 3px;
            background: #008080;
            margin-bottom: 10px;
        }

        .info-dashboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            margin: 10px 0;
        }

        .dash-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dash-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #008080;
            font-size: 1.1rem;
        }

        .dash-content {
            display: flex;
            flex-direction: column;
        }

        .dash-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .dash-value {
            font-size: 0.9rem;
            font-weight: 800;
            color: #1e293b;
        }

        .price-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: auto;
            padding-top: 10px;
        }

        .price-currency {
            font-size: 1.2rem;
            font-weight: 800;
            color: #008080;
        }

        .price-amount {
            font-size: 1.6rem;
            font-weight: 900;
            color: #008080;
            line-height: 1;
        }

        .price-per {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .card-footer-benefits {
            background: #f0fbfb;
            border-top: 1px solid #b2e0e0;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            width: 100%;
            flex-wrap: wrap;
            gap: 8px;
        }

        .benefit-col {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .benefit-icon {
            color: #008080;
            font-size: 1.2rem;
        }

        .benefit-info {
            display: flex;
            flex-direction: column;
        }

        .benefit-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: #1e293b;
        }

        .benefit-desc {
            font-size: 0.7rem;
            color: #64748b;
        }

        .service-short-desc {
            font-size: 0.82rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .view-details-btn {
            background: linear-gradient(90deg, #00B894, #008080) !important;
            color: white !important;
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 16px rgba(0, 128, 128, 0.3) !important;
            white-space: nowrap;
        }

        .view-details-btn:hover {
            background: linear-gradient(90deg, #00c29d, #006666) !important;
            transform: scale(1.05) !important;
            box-shadow: 0 10px 24px rgba(0, 128, 128, 0.4) !important;
            color: white !important;
        }

        /* ===================== TABLET RESPONSIVE ===================== */
        @media (max-width: 1024px) and (min-width: 769px) {
            .service-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 20px !important;
            }
        }

        /* ===================== MOBILE RESPONSIVE ===================== */
        @media (max-width: 768px) {
            .service-grid {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
                margin: 20px auto !important;
                width: 95% !important;
            }

            .service-card {
                flex-direction: column !important;
                padding: 16px !important;
                gap: 16px !important;
                border-radius: 25px !important;
            }

            .card-main-layout {
                flex-direction: column !important;
                padding: 0 !important;
            }

            .service-image-container {
                width: 100% !important;
                min-width: unset !important;
                max-width: 100% !important;
                height: 220px !important;
                min-height: unset !important;
                border-radius: 18px !important;
            }

            .image-overlay-info {
                flex-direction: row !important;
                gap: 8px !important;
                flex-wrap: wrap !important;
            }

            .overlay-item {
                padding: 6px 10px !important;
                gap: 6px !important;
            }

            .overlay-text {
                display: none !important;
            }

            .service-card-body {
                padding: 0 !important;
            }

            .service-card-body h3 {
                font-size: 1.3rem !important;
            }

            .card-info-dashboard {
                flex-wrap: wrap;
                gap: 10px;
            }
        }

        .book-btn:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
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

        .destinations-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .dest-tag {
            background: #f8f9fa;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #003580;
            border: 1px solid #e0e0e0;
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

        /* Booking Modal Styles */
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
            background: linear-gradient(135deg, #004d4d, #008080);
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
            color: #008080;
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
            background: #008080;
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
            color: #008080;
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
            background: linear-gradient(135deg, #008080, #00B894);
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
            color: #008080;
            margin-bottom: 3px;
            font-size: 1rem;
        }

        .service-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #008080;
        }

        .service-duration {
            color: #666;
            font-size: 0.7rem;
        }

        .form-section {
            margin-bottom: 20px;
        }

        .form-section h4 {
            color: #008080;
            margin-bottom: 12px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border-left: 3px solid #008080;
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
            border-color: #008080;
            box-shadow: 0 0 0 2px rgba(0, 128, 128, 0.1);
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
            color: #008080;
            margin-bottom: 10px;
            font-size: 0.85rem;
            border-left: 3px solid #008080;
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
            background: linear-gradient(135deg, #008080, #00B894);
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
            background: #004d4d;
            transform: translateY(-2px);
        }

        .submit-booking-btn {
            background: linear-gradient(135deg, #008080, #00B894);
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
            transition: all 0.3s ease;
        }

        .submit-booking-btn:hover {
            background: #004d4d;
            transform: translateY(-2px);
        }

        /* Payment Methods */
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
            border-color: #008080;
            background: #f0fbfb;
        }

        .payment-method.selected {
            border-color: #008080;
            background: #f0fbfb;
        }

        .payment-method input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #008080;
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

        .qr-code {
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
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

        .instruction-steps {
            margin: 15px 0;
        }

        .instruction-steps ol {
            margin-left: 20px;
            margin-top: 8px;
        }

        .instruction-steps li {
            margin: 5px 0;
            font-size: 0.8rem;
            color: #555;
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

        .details-card p {
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
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

        @media (max-width: 768px) {
            html {
                background-color: #004d4d !important;
            }

            body {
                overscroll-behavior: contain !important;
                overflow-x: hidden !important;
                width: 100% !important;
                position: relative !important;
            }

            .cruises-hero-container {
                padding: 0 !important;
            }

            .cruises-hero {
                padding: 65px 6% 30px !important;
                border-radius: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                min-height: auto !important;
                background: linear-gradient(180deg, #004d4d 0%, #006666 100%) !important;
            }

            .cruises-hero::before {
                opacity: 0.3 !important;
                width: 150% !important;
                height: 150% !important;
                right: -25% !important;
                top: 50% !important;
                -webkit-mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
                mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
            }

            .hero-content-flex {
                flex-direction: row !important;
                align-items: center !important;
                gap: 12px !important;
                margin-bottom: 10px !important;
                width: 100%;
            }

            .hero-badge-white {
                width: 45px !important;
                height: 45px !important;
                border-radius: 10px !important;
                margin: 0 !important;
            }

            .hero-badge-white i {
                font-size: 1.3rem !important;
            }

            .hero-text-content h1 {
                font-size: 2rem !important;
                text-align: left !important;
            }

            .hero-description {
                font-size: 0.95rem !important;
                text-align: left !important;
                margin: 5px 0 20px 0 !important;
            }

            .service-grid {
                grid-template-columns: 1fr !important;
                gap: 25px !important;
                margin: 30px auto !important;
                display: grid !important;
                flex-wrap: wrap !important;
                overflow-x: visible !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }

            .service-card {
                flex-direction: column !important;
                padding: 20px !important;
                gap: 20px !important;
                border-radius: 25px !important;
                width: 100% !important;
                flex: none !important;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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
        }

        @media (max-width: 550px) {
            .service-grid {
                gap: 12px;
            }

            .service-card {
                padding: 15px 12px;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef5 100%);
            font-family: 'Poppins', sans-serif;
        }

        .service-hero {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #3a6ea5 100%);
            padding: 100px 5% 60px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .service-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .service-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: linear-gradient(to top, rgba(245, 247, 250, 1), transparent);
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .service-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 12px;
            font-weight: 800;
            color: white !important;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }

        .service-hero h1 i {
            margin-right: 15px;
            animation: float 3s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .service-hero p {
            font-size: 0.9rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.95;
            line-height: 1.5;
        }

        .service-content {
            padding: 40px 5%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            padding: 25px 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(30, 60, 114, 0.15);
        }

        .service-icon {
            width: 100%;
            height: 180px;
            background: #f0f2f5;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            overflow: hidden;
            position: relative;
        }

        .service-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .service-icon i {
            font-size: 3rem;
            color: #003580;
            opacity: 0.2;
        }

        .service-card h3 {
            color: #003580;
            margin-bottom: 8px;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .service-card p {
            color: #666;
            line-height: 1.4;
            margin-bottom: 15px;
            font-size: 0.8rem;
        }

        .price-section {
            margin-top: auto;
            padding-top: 12px;
        }

        .price-tag {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ff9800;
            margin-bottom: 10px;
        }

        .book-btn {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
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
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
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

        .destinations-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .dest-tag {
            background: #f8f9fa;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #003580;
            border: 1px solid #e0e0e0;
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

        /* Booking Modal Styles */
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
            background: #f8fafc;
            border-radius: 30px;
            max-width: 650px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            animation: modalPopUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes modalPopUp {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .booking-modal-header {
            background: #008080;
            color: white;
            padding: 30px;
            border-radius: 30px 30px 0 0;
            position: relative;
        }

        .booking-modal-header h2 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }

        .booking-modal-header p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
            color: white;
        }

        .close-booking {
            position: absolute;
            top: 25px;
            right: 25px;
            font-size: 24px;
            cursor: pointer;
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
            color: white;
        }

        .close-booking:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .booking-steps-nav {
            display: flex;
            justify-content: center;
            padding: 30px 20px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .step-item {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-circle {
            width: 35px;
            height: 35px;
            background: #e2e8f0;
            color: #64748b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 800;
            font-size: 0.9rem;
            z-index: 2;
            position: relative;
            transition: 0.3s;
        }

        .step-item.active .step-circle {
            background: #008080;
            color: white;
            box-shadow: 0 0 0 5px rgba(0, 128, 128, 0.2);
        }

        .step-item.completed .step-circle {
            background: #22c55e;
            color: white;
        }

        .step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
        }

        .step-item.active .step-label {
            color: #008080;
        }

        .step-connector {
            position: absolute;
            top: 17px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step-item.completed .step-connector {
            background: #22c55e;
        }

        .booking-body {
            padding: 30px;
        }

        .step-content {
            display: none;
            animation: fadeIn 0.3s ease;
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

        .service-mini-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .mini-card-icon {
            width: 60px;
            height: 60px;
            background: #f0fbfb;
            color: #008080;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .mini-card-info h4 {
            margin: 0 0 5px;
            color: #008080;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .mini-card-info .mini-price {
            font-size: 1.3rem;
            font-weight: 900;
            color: #008080;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 25px 0 20px;
            padding-left: 10px;
            border-left: 4px solid #008080;
            color: #008080;
            font-weight: 800;
            font-size: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #1e293b;
        }

        .input-group label .required {
            color: #ff4444;
            margin-left: 2px;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: 0.3s;
            background: white;
            color: #1e293b;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: #008080;
            outline: none;
            box-shadow: 0 0 0 4px rgba(0, 128, 128, 0.1);
        }

        .input-group input.error,
        .input-group select.error {
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

        .summary-table {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 600;
            color: #64748b;
        }

        .summary-value {
            font-weight: 800;
            color: #1e293b;
            text-align: right;
        }

        .modal-footer {
            display: flex;
            gap: 15px;
            padding: 25px 30px;
            background: white;
            border-top: 1px solid #e2e8f0;
            border-radius: 0 0 30px 30px;
            margin: 20px -30px -30px;
        }

        .btn-back {
            flex: 1;
            padding: 15px;
            border: none;
            background: #94a3b8;
            color: white;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1rem;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: #64748b;
            transform: translateY(-2px);
        }

        .btn-proceed {
            flex: 2;
            padding: 15px;
            border: none;
            background: linear-gradient(135deg, #00B894, #008080);
            color: white;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(0, 128, 128, 0.2);
            font-size: 1rem;
            transition: 0.3s;
        }

        .btn-proceed:hover {
            background: #008080;
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 128, 128, 0.3);
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .pay-option {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pay-option:hover {
            border-color: #008080;
            background: #f0fbfb;
        }

        .pay-option.selected {
            border-color: #008080;
            background: #f0fbfb;
            box-shadow: 0 10px 20px rgba(0, 128, 128, 0.1);
        }

        .pay-radio {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            position: relative;
            flex-shrink: 0;
            transition: 0.3s;
        }

        .pay-option.selected .pay-radio {
            border-color: #008080;
            background: #008080;
        }

        .pay-option.selected .pay-radio::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .pay-icon {
            font-size: 1.5rem;
            color: #008080;
        }

        .pay-info {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .pay-name {
            font-weight: 800;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .pay-desc {
            font-size: 0.75rem;
            color: #64748b;
        }

        /* Payment Methods */
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
            border-color: #008080;
            background: #f0fbfb;
        }

        .payment-method.selected {
            border-color: #008080;
            background: #f0fbfb;
        }

        .payment-method input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #008080;
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
            color: #008080;
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
            border-bottom: 2px solid #008080;
        }

        .instruction-header i {
            font-size: 1.5rem;
            color: #008080;
        }

        .instruction-header h4 {
            color: #008080;
            margin: 0;
            font-size: 1rem;
        }

        .qr-code {
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
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
            color: #008080;
        }

        .account-details {
            background: #f0fbfb;
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
            color: #008080;
            background: #e6f7f7;
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
            background: #008080;
            color: white;
        }

        .instruction-steps {
            margin: 15px 0;
        }

        .instruction-steps ol {
            margin-left: 20px;
            margin-top: 8px;
        }

        .instruction-steps li {
            margin: 5px 0;
            font-size: 0.8rem;
            color: #555;
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
            border-color: #008080;
            background: #f0fbfb;
        }

        .file-upload i {
            font-size: 1.5rem;
            color: #008080;
            margin-bottom: 5px;
        }

        .file-upload p {
            font-size: 0.7rem;
            color: #666;
            margin: 0;
        }

        .file-upload .file-name {
            font-size: 0.65rem;
            color: #008080;
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
            background: #e6f7f7;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.7rem;
            color: #008080;
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

        .details-card p {
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
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

        @media (max-width: 768px) {
            .cruises-hero-container {
                padding: 0 !important;
            }

            .cruises-hero {
                padding: 65px 6% 30px !important;
                border-radius: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                min-height: 200px !important;
                background: linear-gradient(180deg, #004d4d 0%, #006b58 100%) !important;
            }

            .cruises-hero::before {
                opacity: 0.3 !important;
                width: 150% !important;
                height: 150% !important;
                right: -25% !important;
                top: 50% !important;
                -webkit-mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
                mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
            }

            .service-hero h1 {
                font-size: 1.8rem;
            }

            .service-grid {
                gap: 15px;
            }

            .service-card {
                padding: 20px 15px;
            }

            .image-overlay-info {
                flex-direction: row !important;
                gap: 8px !important;
                flex-wrap: wrap !important;
                opacity: 1 !important;
                transform: translateY(0) !important;
                background: linear-gradient(to top, rgba(0, 0, 0, 0.6) 0%, transparent 100%) !important;
                backdrop-filter: none !important;
                padding: 10px 12px !important;
            }

            .overlay-item {
                padding: 4px 8px !important;
                gap: 5px !important;
            }

            .overlay-text {
                display: none !important;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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
        }

        @media (max-width: 550px) {
            .service-grid {
                gap: 12px;
            }

            .service-card {
                padding: 15px 12px;
            }

            .service-icon {
                height: 140px;
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

        /* Details Modal Styles */
        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .details-modal.active {
            display: flex;
        }

        .details-modal-content {
            background: white;
            border-radius: 20px;
            max-width: 900px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
            position: relative;
        }

        .details-hero {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .details-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .details-hero-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
            padding: 30px;
            color: white;
        }

        .details-hero-overlay h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            color: #ffffff !important;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
        }

        .details-hero-overlay p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .details-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .details-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            color: #666;
            transition: 0.3s;
            border-bottom: 3px solid transparent;
        }

        .details-tab.active {
            color: #003580;
            border-bottom-color: #ff9800;
            background: white;
        }

        .details-body {
            padding: 30px;
        }

        .details-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .details-pane.active {
            display: block;
        }

        .itinerary-list {
            position: relative;
            padding-left: 30px;
        }

        .itinerary-list::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 10px;
            bottom: 10px;
            width: 2px;
            background: #e0e0e0;
        }

        .itinerary-day {
            position: relative;
            margin-bottom: 25px;
        }

        .itinerary-day::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 16px;
            height: 16px;
            background: #008080;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 2px #008080;
            z-index: 2;
        }

        .day-num {
            font-weight: 800;
            color: #008080;
            font-size: 0.75rem;
            text-transform: uppercase;
            margin-bottom: 4px;
            display: block;
        }

        .day-title {
            font-weight: 700;
            color: #003580;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .day-desc {
            color: #555;
            font-size: 0.85rem;
            line-height: 1.6;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 30px;
        }

        .details-section {
            margin-bottom: 25px;
        }

        .details-section h4 {
            color: #003580;
            margin-bottom: 12px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .details-section h4 i {
            color: #008080;
        }

        .details-list-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #555;
        }

        .details-list-item i {
            color: #28a745;
            margin-top: 4px;
        }

        .details-list-item.exclusion i {
            color: #dc3545;
        }

        .ship-info-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #eee;
        }

        .ship-stat {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
        }

        .ship-stat:last-child {
            border-bottom: none;
        }

        .ship-stat-label {
            font-weight: 600;
            color: #666;
        }

        .ship-stat-value {
            color: #003580;
            font-weight: 700;
        }

        .details-footer {
            padding: 20px 30px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            bottom: 0;
            z-index: 10;
        }

        .details-footer-price {
            display: flex;
            flex-direction: column;
        }

        .price-label {
            font-size: 0.75rem;
            color: #666;
        }

        .price-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: #008080;
        }

        .details-book-btn {
            background: linear-gradient(135deg, #00B894, #008080) !important;
            color: white !important;
            border: none;
            padding: 12px 30px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s ease;
        }

        .details-book-btn:hover {
            background: linear-gradient(135deg, #00c29d, #006666) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 5px 15px rgba(0, 128, 128, 0.3) !important;
            color: white !important;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            width: 100%;
            margin-top: 10px;
        }

        .btn-row .book-btn {
            flex: 2;
        }

        .details-btn {
            flex: 1;
            background: #f0f2f5;
            color: #003580;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            z-index: 5;
        }

        .details-btn:hover {
            background: #e2e8f0;
            border-color: #003580;
        }

        @media (max-width: 768px) {
            .details-hero {
                height: 200px;
            }

            .details-hero-overlay h2 {
                font-size: 1.4rem;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .details-tabs {
                overflow-x: auto;
            }

            .details-tab {
                min-width: 120px;
            }

            .details-footer {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .details-book-btn {
                width: 100%;
                justify-content: center;
            }

            /* Partners Mobile: Swipable Single Row */
            .partners-grid {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                padding: 10px 12px 15px !important;
                gap: 15px !important;
                margin: 0 auto !important;
                width: 100% !important;
                box-sizing: border-box !important;
                -webkit-overflow-scrolling: touch !important;
                scrollbar-width: none !important;
                justify-content: flex-start !important;
            }

            .partners-grid::-webkit-scrollbar {
                display: none !important;
            }

            .partner-card {
                flex: 0 0 auto !important;
                width: max-content !important;
                justify-content: flex-start !important;
                padding: 10px 18px !important;
                font-size: 0.85rem !important;
                white-space: nowrap !important;
                box-sizing: border-box !important;
            }

            .partner-card img {
                height: 20px !important;
                flex-shrink: 0 !important;
            }
        }
    </style>

    <!-- ===== ENTRANCE ANIMATION STYLES ===== -->
    <style>
        /* ============================================
           PREMIUM CRUISE ENTRANCE LOADER
        ============================================ */
        #page-preloader {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #14b8a6, #0f766e, #042f2e);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease, visibility 0.8s ease;
            overflow: hidden;
            color: white;
        }

        #page-preloader.hidden {
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

        .bg-ocean {
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            opacity: 0.05;
            filter: blur(50px);
            background: radial-gradient(white, transparent);
        }

        /* Floating particles (Bubbles) */
        .loader-particle {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.3;
            animation: floatBubble 8s linear infinite;
        }

        .lp-1 {
            bottom: -5%;
            left: 20%;
            width: 10px;
            height: 10px;
            animation-duration: 9s;
        }

        .lp-2 {
            bottom: -5%;
            left: 60%;
            width: 14px;
            height: 14px;
            animation-duration: 12s;
            animation-delay: 2s;
        }

        .lp-3 {
            bottom: -5%;
            right: 25%;
            width: 8px;
            height: 8px;
            animation-duration: 10s;
            animation-delay: 1s;
        }

        .lp-4 {
            bottom: -5%;
            right: 10%;
            width: 18px;
            height: 18px;
            animation-duration: 15s;
        }

        @keyframes floatBubble {
            0% {
                transform: translateY(0) scale(1);
                opacity: 0;
            }

            30% {
                opacity: 0.6;
            }

            100% {
                transform: translateY(-120vh) scale(1.5);
                opacity: 0;
            }
        }

        /* Top Logo */
        .loader-logo-area {
            position: absolute;
            top: 40px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 1s ease-out forwards;
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
        }

        .loader-logo-area h2 span {
            display: block;
            font-size: 0.65rem;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.9;
        }

        /* Animated Cruise Ship */
        .cruise-animation-container {
            position: relative;
            margin-bottom: 25px;
            animation: bobShip 4s ease-in-out infinite;
        }

        .cruise-icon-3d {
            font-size: 2.5rem;
            color: white;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.1));
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 15px 35px rgba(15, 118, 110, 0.5);
        }

        @keyframes bobShip {

            0%,
            100% {
                transform: translateY(0) rotate(-2deg);
            }

            50% {
                transform: translateY(-15px) rotate(2deg);
                box-shadow: 0 25px 45px rgba(15, 118, 110, 0.6);
            }
        }

        /* Text Area */
        .loader-text-area {
            text-align: center;
            margin-bottom: 30px;
            animation: slideUp 1s ease-out forwards;
        }

        .loader-text-area h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
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
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: visible;
            display: flex;
            align-items: center;
            padding: 6px;
            margin-bottom: 25px;
            animation: slideUp 1.2s ease-out forwards;
        }

        .loader-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #67e8f9 0%, #cffafe 100%);
            border-radius: 35px;
            width: 0%;
            position: relative;
            transition: width 0.1s linear;
            box-shadow: 0 0 20px rgba(103, 232, 249, 0.6);
            overflow: hidden;
        }

        .loader-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            100% {
                left: 200%;
            }
        }

        .loader-percent {
            position: absolute;
            right: 30px;
            font-weight: 800;
            font-size: 1.3rem;
            color: #0f766e;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
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
            font-size: 1.2rem;
        }

        .mockup-status {
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 24px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: white;
        }

        /* Loading Steps */
        /* Loading Steps - Single Dynamic Step */
        .loader-steps {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
            justify-content: center;
            align-items: center;
            animation: slideUp 1.2s ease-out forwards;
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
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.05) 100%);
            border: 2px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .loader-step.active .step-icon {
            background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.1) 100%);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 25px rgba(255,255,255,0.4);
            animation: iconPulse 1.5s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 25px rgba(255,255,255,0.4);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 0 40px rgba(255,255,255,0.6);
            }
        }

        .loader-step.done .step-icon {
            display: none;
        }

        /* Footer */
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
            animation: fadeIn 2s ease-out forwards;
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

        @keyframes slideDown {
            from {
                transform: translateY(-40px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(40px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @media (max-width: 650px) {
            .loader-steps { flex-direction: column; gap: 15px; text-align: center; align-items: center; height: auto; }
            .loader-text-area h1 { font-size: 1.7rem; }
            .loader-progress-container { max-width: 90%; }
            .step-icon { width: 45px; height: 45px; font-size: 1.2rem; }
            .loader-step.active { font-size: 1rem; }
        }

            .loader-progress-container,
            .search-mockup-pill {
                max-width: 90%;
            }
        }

        /* ---- Navbar entrance ---- */
        .navbar {
            animation: navbarSlideDown 0.7s cubic-bezier(0.22, 1, 0.36, 1) 1.2s both;
        }

        @keyframes navbarSlideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ---- Hero container entrance ---- */
        .cruises-hero-container {
            animation: heroFadeUp 0.9s cubic-bezier(0.22, 1, 0.36, 1) 1.4s both;
        }

        @keyframes heroFadeUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ---- Partners section entrance ---- */
        .partners-section {
            animation: fadeUpIn 0.8s ease 1.7s both;
        }

        /* ---- Partner cards stagger ---- */
        .partner-card {
            opacity: 0;
            animation: partnerPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .partner-card:nth-child(1) {
            animation-delay: 1.9s;
        }

        .partner-card:nth-child(2) {
            animation-delay: 2.0s;
        }

        .partner-card:nth-child(3) {
            animation-delay: 2.1s;
        }

        .partner-card:nth-child(4) {
            animation-delay: 2.2s;
        }

        @keyframes partnerPop {
            from {
                opacity: 0;
                transform: scale(0.85) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* ---- Service (cruise) cards scroll-triggered ---- */
        .service-card {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .service-card.card-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ---- Section headings entrance ---- */
        .service-content>h2,
        .service-content>.section-subtitle {
            opacity: 0;
            animation: fadeUpIn 0.7s ease 1.8s both;
        }
    </style>
    <!-- ===== END ENTRANCE ANIMATION STYLES ===== -->
</head>

<body>

    <!-- ===== PREMIUM CRUISE PAGE ENTRANCE LOADER ===== -->
    <div id="page-preloader">
        <div class="loader-bg-element bg-ocean"></div>
        <div class="loader-particle lp-1"></div>
        <div class="loader-particle lp-2"></div>
        <div class="loader-particle lp-3"></div>
        <div class="loader-particle lp-4"></div>

        <div class="loader-logo-area">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo">
            <style>
                .loader-dynamic-logo { height: 100px !important; width: auto !important; margin-left: 10px; }
                @media (max-width: 768px) { .loader-dynamic-logo { height: 60px !important; } }
                @media (max-width: 480px) { .loader-dynamic-logo { height: 45px !important; margin-left: 5px; } }
            </style>
            <img src="../images/Localista (1).png" alt="Localista" class="loader-dynamic-logo">
        </div>

        <div class="cruise-animation-container">
            <div class="cruise-icon-3d"><i class="fas fa-ship"></i></div>
        </div>

        <div class="loader-text-area">
            <h1 id="loaderTitle">Setting Sail To Paradise...</h1>
            <p id="loaderSubtext">We're finding the best ocean journeys for you</p>
        </div>

        <div class="loader-progress-container">
            <div class="loader-progress-fill" id="loaderBarFill"></div>
            <div class="loader-percent" id="loaderPercent">0%</div>
        </div>

        <div class="search-mockup-pill">
            <div class="mockup-route">
                <div
                    style="background: rgba(255,255,255,0.2); width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; margin-right:5px;">
                    <i class="fas fa-globe"></i></div>
                <span id="dynamicRouteText">Searching Cruises</span>
            </div>
            <div class="mockup-status">
                <span id="mockupStatusText">Scanning ports...</span>
                <i class="fas fa-ship" id="mockupStatusIcon"></i>
            </div>
        </div>

        <div class="loader-steps">
            <div class="loader-step active stage-c1" id="lStep1">
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <span>Exploring Options</span>
            </div>
            <div class="loader-step stage-c2" id="lStep2">
                <div class="step-icon"><i class="fas fa-tags"></i></div>
                <span>Comparing Deals</span>
            </div>
            <div class="loader-step stage-c3" id="lStep3">
                <div class="step-icon"><i class="fas fa-shield-alt"></i></div>
                <span>Securing Cabins</span>
            </div>
            <div class="loader-step stage-c4" id="lStep4">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <span>Finalizing Journey</span>
            </div>
        </div>

        <div class="loader-footer">
            <i class="fas fa-water"></i> <span id="loaderFooterText">Sit back and relax, we're crafting the best voyage
                for you.</span>
        </div>
    </div>
    <!-- ================================ -->
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='../index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
    </header>


    <div class="cruises-hero-container">
        <section class="cruises-hero">
            <div class="hero-content-flex">
                <div class="hero-badge-white">
                    <i class="fas fa-ship"></i>
                </div>
                <div class="hero-text-content">
                    <h1>Cruise Vacations</h1>
                    <p class="hero-description">
                        Embark on an unforgettable journey across the seas.
                        Luxury, adventure, and breathtaking views await you.
                    </p>
                </div>
            </div>
        </section>
    </div>

    <div class="service-content">
        <!-- Cruise Partners -->
        <div class="partners-section">
            <div class="partners-title">
                <i class="fas fa-anchor" style="font-size: 1.2rem; opacity: 0.5;"></i>
                Our Cruise Line Partners
                <i class="fas fa-anchor" style="font-size: 1.2rem; opacity: 0.5;"></i>
            </div>
            <style>
                .swipe-hint {
                    display: none;
                }

                @media (max-width: 768px) {
                    .swipe-hint {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        gap: 8px;
                        font-size: 0.75rem;
                        color: #64748b;
                        margin-top: -10px;
                        margin-bottom: 12px;
                        animation: pulseSwipe 2s infinite;
                    }

                    @keyframes pulseSwipe {
                        0% {
                            opacity: 0.5;
                            transform: translateX(-2px);
                        }

                        50% {
                            opacity: 1;
                            transform: translateX(2px);
                        }

                        100% {
                            opacity: 0.5;
                            transform: translateX(-2px);
                        }
                    }
                }
            </style>
            <div class="swipe-hint">
                <i class="fas fa-arrows-alt-h"></i> Swipe to see more
            </div>
            <div class="partners-grid">
                <div class="partner-card">
                    <img src="https://www.google.com/s2/favicons?domain=royalcaribbean.com&sz=128"
                        alt="Royal Caribbean">
                    Royal Caribbean
                </div>
                <div class="partner-card">
                    <img src="https://www.google.com/s2/favicons?domain=princess.com&sz=128" alt="Princess Cruises">
                    Princess Cruises
                </div>
                <div class="partner-card">
                    <img src="https://www.google.com/s2/favicons?domain=dreamcruiseline.com&sz=128" alt="Dream Cruises">
                    Dream Cruises
                </div>
                <div class="partner-card">
                    <img src="https://www.google.com/s2/favicons?domain=starcruises.com&sz=128" alt="Star Cruises">
                    Star Cruises
                </div>
            </div>
        </div>
        <div class="service-grid">
            <?php if (empty($db_cruises)): ?>
                <!-- Default placeholders if DB is empty -->
                <div class="service-card">
                    <div class="card-main-layout">
                        <div class="service-image-container">
                            <div class="card-badge">
                                <i class="fas fa-ship"></i> Cruise
                            </div>
                            <img src="../images/cruises-hero.jpg" alt="Caribbean Cruises">
                            <div class="image-overlay-info">
                                <div class="overlay-item">
                                    <i class="fas fa-anchor"></i>
                                    <div class="overlay-text">
                                        <span class="overlay-label">Ocean Voyage</span>
                                        <span class="overlay-sub">Top Ports</span>
                                    </div>
                                </div>
                                <div class="overlay-item">
                                    <i class="fas fa-ship"></i>
                                    <div class="overlay-text">
                                        <span class="overlay-label">Luxury Liner</span>
                                        <span class="overlay-sub">Full Board</span>
                                    </div>
                                </div>
                                <div class="overlay-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <div class="overlay-text">
                                        <span class="overlay-label">All-Inclusive</span>
                                        <span class="overlay-sub">Meals & Fun</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="service-card-body">
                            <h3>Caribbean Grand Voyage</h3>
                            <div class="title-underline"></div>
                            <div class="info-dashboard-row">
                                <div class="dash-item">
                                    <div class="dash-icon"><i class="fas fa-check"></i></div>
                                    <div class="dash-content">
                                        <span class="dash-label">Status</span>
                                        <span class="dash-value" style="color: #28a745;">Available</span>
                                    </div>
                                </div>
                                <div class="dash-item">
                                    <div class="dash-icon"><i class="fas fa-calendar-alt"></i></div>
                                    <div class="dash-content">
                                        <span class="dash-label">Duration</span>
                                        <span class="dash-value">7 Days / 6 Nights</span>
                                    </div>
                                </div>
                            </div>

                            <div class="price-section">
                                <div style="display: flex; flex-direction: column;">
                                    <span class="price-per">Starting from</span>
                                    <div style="display: flex; align-items: baseline; gap: 5px;">
                                        <span class="price-currency">₱</span>
                                        <span class="price-amount">35,999</span>
                                        <span class="price-per">/person</span>
                                    </div>
                                </div>

                                <button class="view-details-btn"
                                    onclick="requireLogin('showCruiseBooking', 'Caribbean Grand Voyage', 35999, '7 Days / 6 Nights')">
                                    View Details <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer-benefits">
                        <div class="benefit-col">
                            <i class="fas fa-award benefit-icon"></i>
                            <div class="benefit-info">
                                <span class="benefit-title">Best Price Guarantee</span>
                                <span class="benefit-desc">Get the best rates</span>
                            </div>
                        </div>
                        <div class="benefit-col">
                            <i class="fas fa-headset benefit-icon"></i>
                            <div class="benefit-info">
                                <span class="benefit-title">24/7 Support</span>
                                <span class="benefit-desc">We're here for you</span>
                            </div>
                        </div>
                        <div class="benefit-col">
                            <i class="fas fa-credit-card benefit-icon"></i>
                            <div class="benefit-info">
                                <span class="benefit-title">Secure Booking</span>
                                <span class="benefit-desc">Safe & hassle-free</span>
                            </div>
                        </div>

                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($db_cruises as $cruise):
                    $base = $cruise['base_price'];
                    $promo = $cruise['promo_price'];
                    $display_price = ($promo > 0) ? $promo : $base;
                    $has_promo = ($promo > 0 && $promo < $base);
                    ?>
                    <div class="service-card">
                        <div class="card-main-layout">
                            <div class="service-image-container">
                                <div class="card-badge">
                                    <i class="fas fa-ship"></i> <?= htmlspecialchars($cruise['category'] ?: 'Cruise') ?>
                                </div>
                                <?php if ($cruise['featured_image']): ?>
                                    <img src="../<?= htmlspecialchars($cruise['featured_image']) ?>"
                                        alt="<?= htmlspecialchars($cruise['title']) ?>">
                                <?php else: ?>
                                    <img src="../images/cruises-hero.jpg" alt="Cruise">
                                <?php endif; ?>
                                <div class="image-overlay-info">
                                    <div class="overlay-item">
                                        <i class="fas fa-anchor"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">Ocean Voyage</span>
                                            <span class="overlay-sub">Top Ports</span>
                                        </div>
                                    </div>
                                    <div class="overlay-item">
                                        <i class="fas fa-ship"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">Luxury Liner</span>
                                            <span class="overlay-sub">Full Board</span>
                                        </div>
                                    </div>
                                    <div class="overlay-item">
                                        <i class="fas fa-shield-alt"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">All-Inclusive</span>
                                            <span class="overlay-sub">Meals & Fun</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="service-card-body">
                                <h3><?= htmlspecialchars($cruise['title']) ?></h3>
                                <div class="title-underline"></div>
                                <div class="info-dashboard-row">
                                    <div class="dash-item">
                                        <div class="dash-icon"><i class="fas fa-check"></i></div>
                                        <div class="dash-content">
                                            <span class="dash-label">Status</span>
                                            <span class="dash-value" style="color: #28a745;">Available</span>
                                        </div>
                                    </div>
                                    <div class="dash-item">
                                        <div class="dash-icon"><i class="fas fa-calendar-alt"></i></div>
                                        <div class="dash-content">
                                            <span class="dash-label">Duration</span>
                                            <span
                                                class="dash-value"><?= htmlspecialchars($cruise['duration'] ?: 'N/A') ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="price-section">
                                    <div style="display: flex; flex-direction: column;">
                                        <span class="price-per">Starting from</span>
                                        <div style="display: flex; align-items: baseline; gap: 5px; flex-wrap: wrap;">
                                            <?php if ($has_promo): ?>
                                                <span class="price-original"
                                                    style="font-size: 0.9rem; color: #94a3b8; text-decoration: line-through; margin-right: 5px;">₱<?= number_format($base) ?></span>
                                            <?php endif; ?>
                                            <span class="price-currency">₱</span>
                                            <span class="price-amount"><?= number_format($display_price) ?></span>
                                            <span class="price-per">/person</span>
                                        </div>
                                    </div>

                                    <button class="view-details-btn" onclick="viewCruiseDetails(<?= $cruise['id'] ?>)">
                                        View Details <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer-benefits">
                            <div class="benefit-col">
                                <i class="fas fa-award benefit-icon"></i>
                                <div class="benefit-info">
                                    <span class="benefit-title">Best Price Guarantee</span>
                                    <span class="benefit-desc">Get the best rates</span>
                                </div>
                            </div>
                            <div class="benefit-col">
                                <i class="fas fa-headset benefit-icon"></i>
                                <div class="benefit-info">
                                    <span class="benefit-title">24/7 Support</span>
                                    <span class="benefit-desc">We're here for you</span>
                                </div>
                            </div>
                            <div class="benefit-col">
                                <i class="fas fa-credit-card benefit-icon"></i>
                                <div class="benefit-info">
                                    <span class="benefit-title">Secure Booking</span>
                                    <span class="benefit-desc">Safe & hassle-free</span>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <div class="back-button-container"><button class="back-button" onclick="window.location.href='../index.php'"><i
                class="fas fa-arrow-left"></i> Back to Home</button></div>

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
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Inject user info for booking forms
        window.currentUserEmail = '<?= isset($_SESSION['user_id']) ? $auth->getCurrentUser()['email'] : '' ?>';
        window.currentFullName = '<?= isset($_SESSION['user_id']) ? htmlspecialchars($auth->getCurrentUser()['full_name']) : '' ?>';
    </script>

    <script>
        let currentCruise = null, cruiseBookingData = null, selectedPayment = null;

        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
        function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { const btn = event.target; const originalText = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#28a745'; btn.style.color = 'white'; setTimeout(() => { btn.textContent = originalText; btn.style.background = '#e0e0e0'; btn.style.color = ''; }, 1500); }); }
        function handleFileUpload(event, paymentMethod) { const file = event.target.files[0]; if (file) { if (!file.type.match('image.*')) { alert('Please upload an image file (PNG, JPG, JPEG)'); event.target.value = ''; return; } if (file.size > 5 * 1024 * 1024) { alert('File is too large. Maximum size is 5MB.'); event.target.value = ''; return; } const reader = new FileReader(); reader.onload = function (e) { const previewDiv = document.getElementById(`preview-${paymentMethod}`); if (previewDiv) { previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof">`; } }; reader.readAsDataURL(file); const fileNameSpan = document.getElementById(`file-name-${paymentMethod}`); if (fileNameSpan) { fileNameSpan.textContent = file.name; } } }

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
            const fullName = document.getElementById('fullName')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const departureDate = document.getElementById('departureDate')?.value;
            const passengers = document.getElementById('passengers')?.value;
            if (!fullName) errors.push('Full Name is required');

            const email = window.currentUserEmail || '';
            if (!email) errors.push('Your account email could not be detected. Please log in again.');
            if (!phone) errors.push('Phone number is required');
            if (!departureDate) errors.push('Departure Date is required');
            if (!passengers || passengers < 1) errors.push('At least 1 passenger is required');

            document.querySelectorAll('.input-group input, .input-group select').forEach(f => f.classList.remove('error'));
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
            const email = window.currentUserEmail || '';
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
                            <!-- Will be dynamic -->
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

            // Reset inputs
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
                        <button class="btn-proceed" style="flex:1;" onclick="closeCruiseBookingModal(); location.reload();"><i class="fas fa-plus"></i> Book Another Cruise</button>
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

        // Cruise Details Functionality
        async function viewCruiseDetails(id) {
            console.log('viewCruiseDetails called with ID:', id);
            console.log('viewCruiseDetails called with ID:', id);
            let modal = document.getElementById('cruiseDetailsModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'cruiseDetailsModal';
                modal.className = 'details-modal';
                document.body.appendChild(modal);
            }

            modal.innerHTML = `<div class="details-modal-content" style="text-align:center; padding:50px;"><i class="fas fa-spinner fa-spin" style="font-size:3rem; color:#ff9800;"></i><p style="margin-top:20px;">Loading cruise details...</p></div>`;
            modal.classList.add('active');

            try {
                const response = await fetch(`../api/get-cruise-details.php?id=${id}`);
                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    renderCruiseDetails(data);
                } else {
                    modal.innerHTML = `<div class="details-modal-content" style="padding:50px; text-align:center;"><h3>Error</h3><p>${result.message}</p><button class="btn-prev" onclick="closeCruiseDetails()">Close</button></div>`;
                }
            } catch (error) {
                modal.innerHTML = `<div class="details-modal-content" style="padding:50px; text-align:center;"><h3>Connection Error</h3><p>Could not load details.</p><button class="btn-prev" onclick="closeCruiseDetails()">Close</button></div>`;
            }
        }

        function renderCruiseDetails(data) {
            const modal = document.getElementById('cruiseDetailsModal');
            const price = data.promo_price > 0 ? data.promo_price : data.base_price;
            const hasPromo = data.promo_price > 0;

            let gallery = [];
            try { gallery = data.gallery ? JSON.parse(data.gallery) : []; } catch (e) { gallery = []; }

            modal.innerHTML = `
                <div class="details-modal-content">
                    <span class="close-modal" style="position:absolute; z-index:20; right:20px; top:15px; color:white; text-shadow: 0 2px 5px rgba(0,0,0,0.5);" onclick="closeCruiseDetails()">&times;</span>
                        <div class="details-hero">
                            <img src="../${data.featured_image || 'images/placeholder-cruise.jpg'}" alt="${data.title}">
                            <div class="details-hero-overlay">
                                <h2>${data.title}</h2>
                                <p><i class="fas fa-map-marker-alt"></i> ${data.departure_port} | <i class="fas fa-clock"></i> ${data.duration}</p>
                            </div>
                        </div>
                        
                        <div class="details-tabs">
                            <div class="details-tab active" onclick="switchDetailTab(event, 'itinerary')">Itinerary</div>
                            <div class="details-tab" onclick="switchDetailTab(event, 'ship')">Ship Details</div>
                            <div class="details-tab" onclick="switchDetailTab(event, 'inclusions')">Inclusions</div>
                            <div class="details-tab" onclick="switchDetailTab(event, 'gallery')">Gallery</div>
                            <div class="details-tab" onclick="switchDetailTab(event, 'terms')">Policies</div>
                        </div>

                        <div class="details-body">
                            <!-- Itinerary -->
                            <div id="pane-itinerary" class="details-pane active">
                                <div class="itinerary-list">
                                    ${data.itinerary && data.itinerary.length > 0 ? data.itinerary.map(day => `
                                        <div class="itinerary-day">
                                            <span class="day-num">Day ${day.day_number}</span>
                                            <h5 class="day-title">${day.title}</h5>
                                            <p class="day-desc">${day.description}</p>
                                        </div>
                                    `).join('') : '<p>Itinerary details coming soon.</p>'}
                                </div>
                            </div>

                            <!-- Ship Details -->
                            <div id="pane-ship" class="details-pane">
                                <div class="details-grid">
                                    <div>
                                        <div class="details-section">
                                            <h4><i class="fas fa-info-circle"></i> About this Cruise</h4>
                                            <p style="font-size:0.9rem; line-height:1.7; color:#555; margin-bottom:20px;">${data.full_description || data.short_description}</p>
                                        </div>
                                        <div class="details-section">
                                            <h4><i class="fas fa-ship"></i> The Vessel</h4>
                                            <p style="font-size:0.9rem; line-height:1.7; color:#555;">${data.ship_description || 'Experience comfort and luxury aboard the ' + data.ship_name + '.'}</p>
                                        </div>
                                        <div class="details-section">
                                            <h4><i class="fas fa-star"></i> Highlights</h4>
                                            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                                ${(data.highlights || '').split('\n').map(h => h.trim() ? `<span class="dest-tag" style="background:#fff9e6; border-color:#ff9800;">${h}</span>` : '').join('')}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="ship-info-card" style="margin-bottom:20px;">
                                            <div class="ship-stat"><span class="ship-stat-label">Cruise Line</span><span class="ship-stat-value">${data.cruise_line}</span></div>
                                            <div class="ship-stat"><span class="ship-stat-label">Ship Name</span><span class="ship-stat-value">${data.ship_name}</span></div>
                                            <div class="ship-stat"><span class="ship-stat-label">Destinations</span><span class="ship-stat-value">${data.destinations}</span></div>
                                            <div class="ship-stat"><span class="ship-stat-label">Status</span><span class="ship-stat-value" style="color:#28a745;">${data.status}</span></div>
                                        </div>
                                        <div class="details-section">
                                            <h4><i class="fas fa-concierge-bell"></i> Amenities</h4>
                                            <div style="display:grid; grid-template-columns:1fr; gap:8px;">
                                                ${(data.amenities || '').split(',').map(a => `<div class="details-list-item"><i class="fas fa-check"></i> ${a.trim()}</div>`).join('')}
                                            </div>
                                        </div>
                                        <div class="details-section">
                                            <h4><i class="fas fa-bed"></i> Room Types</h4>
                                            ${(data.room_types || '').split(',').map(r => `<div class="details-list-item"><i class="fas fa-door-open"></i> ${r.trim()}</div>`).join('')}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Inclusions -->
                            <div id="pane-inclusions" class="details-pane">
                                <div class="details-grid">
                                    <div>
                                        <div class="details-section">
                                            <h4><i class="fas fa-check-circle"></i> What's Included</h4>
                                            ${(data.inclusions || '').split('\n').map(i => i.trim() ? `<div class="details-list-item"><i class="fas fa-check"></i> ${i}</div>` : '').join('')}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="details-section">
                                            <h4><i class="fas fa-times-circle"></i> Not Included</h4>
                                            ${(data.exclusions || '').split('\n').map(e => e.trim() ? `<div class="details-list-item exclusion"><i class="fas fa-times"></i> ${e}</div>` : '').join('')}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gallery Tab -->
                            <div id="pane-gallery" class="details-pane">
                                <div class="details-section">
                                    <h4><i class="fas fa-images"></i> Cruise Gallery</h4>
                                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:15px; margin-top:15px;">
                                        ${gallery.length > 0 ? gallery.map(img => `<img src="../${img}" style="width:100%; height:180px; object-fit:cover; border-radius:15px; cursor:pointer;" onclick="window.open(this.src, '_blank')">`).join('') : '<p style="color:#666;">No additional photos available.</p>'}
                                    </div>
                                </div>
                            </div>

                            <!-- Policies Tab -->
                            <div id="pane-terms" class="details-pane">
                                <div class="details-section">
                                    <h4><i class="fas fa-file-contract"></i> Booking Policies & Terms</h4>
                                    <div style="display:flex; flex-direction:column; gap:20px; margin-top:15px;">
                                        <div style="padding:20px; background:#fff5f5; border-radius:15px; border-left:5px solid #ff4444;">
                                            <h5 style="color:#ff4444; margin-bottom:5px; font-weight:700;"><i class="fas fa-info-circle"></i> Cancellation Policy</h5>
                                            <p style="color:#555; font-size:0.9rem; margin:0; line-height:1.6;">${data.cancellation_policy || 'Free cancellation up to 7 days before departure. Non-refundable if cancelled within 7 days.'}</p>
                                        </div>
                                        <div style="padding:20px; background:#f8fafc; border-radius:15px; border-left:5px solid #003580;">
                                            <h5 style="color:#003580; margin-bottom:5px; font-weight:700;"><i class="fas fa-clock"></i> Terms & Conditions</h5>
                                            <p style="color:#555; font-size:0.9rem; margin:0; line-height:1.6;">${data.terms_conditions || 'Boarding starts 3 hours prior to departure. Passengers must present valid travel documents.'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <div class="details-footer">
                        <div class="details-footer-price">
                            <span class="price-label">Price starting from</span>
                            <span class="price-val">₱${formatNumber(price)}</span>
                        </div>
                        <button class="details-book-btn" onclick="closeCruiseDetails(); requireLogin('showCruiseBooking', '${data.title.replace(/'/g, "\\'")}', ${price}, '${data.duration.replace(/'/g, "\\'")}')">
                            Book This Cruise <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        function switchDetailTab(event, tabId) {
            document.querySelectorAll('.details-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.details-pane').forEach(p => p.classList.remove('active'));

            const target = event.currentTarget || event.target;
            target.classList.add('active');
            document.getElementById('pane-' + tabId).classList.add('active');
        }

        function closeCruiseDetails() {
            document.getElementById('cruiseDetailsModal').classList.remove('active');
        }
    </script>
    <script src="../js/auth-menu.js"></script>
    <script src="../js/main.js"></script>

    <!-- ===== ENTRANCE ANIMATION SCRIPTS ===== -->
    <script>
        // --- Premium Cruise Loader Sequence ---
        (function () {
            window.addEventListener('load', function () {
                const loader = document.getElementById('page-preloader');
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
                    { p: 0, text: 'Scanning ports...', icon: 'fa-anchor', title: 'Setting Sail To Paradise...', destText: (hasRoute ? `Cruises navigating to ${query}` : 'Searching Cruises'), footer: "Sit back and relax, we're crafting the best voyage for you." },
                    { p: 25, text: 'Comparing voyages...', icon: 'fa-tags', title: 'Curating Luxury Ocean Journeys...', destText: (hasRoute ? `Top sailings to ${query}` : 'Global Sailings'), footer: "Comparing majestic ships across the oceans." },
                    { p: 55, text: 'Securing cabins...', icon: 'fa-shield-alt', title: 'Checking Cabin Availability...', destText: (hasRoute ? `Berths for ${query}` : 'Checking Ports'), footer: "Ensuring the most comfortable suite for your trip." },
                    { p: 80, text: 'Ready', icon: 'fa-check-circle', title: 'Preparing Your Itinerary...', destText: (hasRoute ? `Voyage Ready for ${query}` : 'Ready to Sail'), footer: "Your premium cruise is almost ready." }
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
                            loader.classList.add('hidden');

                            // Trigger hero animation
                            const heroContainer = document.querySelector('.cruises-hero-container');
                            if (heroContainer) heroContainer.classList.add('animate-in');

                            // Trigger card animations after loader hides
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

                            setTimeout(() => {
                                loader.remove();
                            }, 800);
                        }, 500);
                    }
                }, intervalTime);
            });
        })();
    </script>
    <!-- ===== END ENTRANCE ANIMATION SCRIPTS ===== -->
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
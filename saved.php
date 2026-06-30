<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - Saved Items</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Flatpickr for calendars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Glassmorphic Modal Styles for Saved Items Popups */
        .package-modal,
        .home-package-modal,
        .flash-deal-modal,
        .login-required-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            z-index: 99999999 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .package-modal.active,
        .home-package-modal.active,
        .flash-deal-modal.active,
        .login-required-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .package-modal-content,
        .home-package-modal-content,
        .flash-deal-modal-content,
        .login-required-modal-content {
            background: rgba(255, 255, 255, 0.98);
            width: 90%;
            max-width: 850px;
            max-height: 90vh;
            border-radius: 28px;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            padding: 0;
            animation: modalSlideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes modalSlideUp {
            from {
                transform: translateY(60px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .package-modal-header,
        .home-package-modal-header,
        .flash-deal-modal-header,
        .login-required-header {
            padding: 30px 40px;
            background: linear-gradient(135deg, #003580, #0056b3);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .package-modal-header h2,
        .home-package-modal-header h2,
        .flash-deal-modal-header h2 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .close-modal,
        .close-login-modal {
            position: absolute;
            right: 25px;
            top: 25px;
            font-size: 1.6rem;
            cursor: pointer;
            color: white;
            transition: transform 0.2s;
            line-height: 1;
        }

        .close-modal:hover {
            transform: rotate(90deg);
        }

        .package-modal-body,
        .home-package-modal-body,
        .flash-deal-modal-body {
            padding: 35px 40px;
        }

        /* Modal Content Styles */
        .itinerary-section h3,
        .inclusions-section h3,
        .remarks-section h3 {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #003580;
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 1.3rem;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
        }

        .itinerary-day {
            margin-bottom: 20px;
            padding-left: 10px;
            border-left: 3px solid #ff9800;
        }

        .day-badge {
            background: #ff9800;
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 10px;
            display: inline-block;
        }

        .itinerary-day h4 {
            margin-bottom: 10px;
            font-size: 1.1rem;
            color: #333;
        }

        .itinerary-day ul,
        .inclusions-list {
            list-style: none;
            padding-left: 0;
        }

        .itinerary-day li,
        .inclusions-list li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 10px;
            font-size: 0.95rem;
            color: #555;
        }

        .itinerary-day li:before,
        .inclusions-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }

        .booking-steps {
            display: flex;
            justify-content: space-between;
            margin: 40px 0;
            position: relative;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 0.9rem;
            font-weight: bold;
            transition: all 0.3s;
        }

        .step.active .step-number {
            background: #ff9800;
            color: white;
            box-shadow: 0 0 0 6px rgba(255, 152, 0, 0.2);
        }

        .step.completed .step-number {
            background: #4caf50;
            color: white;
        }

        .step-label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 600;
        }

        .step-line {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 3px;
            background: #e0e0e0;
            z-index: -1;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 35px;
        }

        .btn-next,
        .btn-prev,
        .book-now-btn {
            flex: 1;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .btn-next,
        .book-now-btn {
            background: #ff9800;
            color: white;
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.2);
            text-decoration: none;
        }

        .btn-prev {
            background: #f0f2f5;
            color: #333;
        }

        .btn-next:hover,
        .book-now-btn:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 152, 0, 0.3);
            color: white;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #003580;
            outline: none;
            background: #f0f7ff;
        }

        .package-price-card {
            background: linear-gradient(135deg, #fffde7, #fff8e1);
            border: 2px solid #ff9800;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.1);
        }

        .package-price-card .price {
            font-size: 2rem;
            font-weight: 800;
            color: #ff9800;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 25px;
        }

        .payment-method {
            border: 2.5px solid #f0f2f5;
            border-radius: 18px;
            padding: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }

        .payment-method.selected {
            border-color: #ff9800;
            background: #fff8e1;
        }

        .payment-details-box {
            display: none;
            margin-top: 25px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
        }

        .payment-details-box.show {
            display: block;
        }

        .package-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid #eee;
        }

        .detail-item i {
            font-size: 1.2rem;
            color: #ff9800;
            margin-bottom: 8px;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: #333;
        }

        /* Login required modal specific */
        .login-required-modal-content {
            max-width: 500px;
            text-align: center;
            padding: 0;
        }

        .icon-container {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
        }

        .login-required-body {
            padding: 40px;
        }

        .login-btn-primary {
            background: #003580;
            color: white;
            padding: 15px;
            border-radius: 12px;
            display: block;
            width: 100%;
            margin-bottom: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .login-btn-secondary {
            background: #f0f2f5;
            color: #333;
            padding: 15px;
            border-radius: 12px;
            display: block;
            width: 100%;
            margin-bottom: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .login-btn-text {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-weight: 600;
        }

        @media (max-width: 650px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }

            .package-modal-content {
                width: 95%;
                max-height: 95vh;
            }
        }

        /* =============================================
           SAVED PAGE — PROFESSIONAL REDESIGN
           ============================================= */

        * {
            box-sizing: border-box;
        }

        body {
            background: #f4f6fb;
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }

        /* ── Page Banner ── */
        .saved-page-banner {
            background: linear-gradient(135deg, #003580 0%, #0a4fa6 60%, #1a6bc9 100%);
            padding: 110px 6% 50px;
            position: relative;
            overflow: hidden;
        }

        .saved-page-banner::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
            top: -200px;
            right: -100px;
        }

        .saved-page-banner::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 152, 0, 0.08);
            border-radius: 50%;
            bottom: -120px;
            left: 5%;
        }

        .banner-inner {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .banner-text h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 8px;
            letter-spacing: -0.3px;
            animation: fadeInDown 0.5s ease both;
        }

        .banner-text p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.92rem;
            margin: 0;
            animation: fadeInDown 0.6s ease both;
        }

        .banner-stats {
            display: flex;
            gap: 16px;
            animation: fadeIn 0.7s ease both;
        }

        .banner-stat {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            padding: 14px 22px;
            text-align: center;
            color: white;
            min-width: 90px;
        }

        .banner-stat-num {
            font-size: 1.6rem;
            font-weight: 800;
            display: block;
            line-height: 1;
        }

        .banner-stat-label {
            font-size: 0.7rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
            display: block;
        }

        /* ── Main Content ── */
        .saved-page-section {
            max-width: 1380px;
            margin: 0 auto;
            padding: 36px 5% 70px;
        }

        /* ── Toolbar ── */
        .saved-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .saved-toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .saved-toolbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0d1b3e;
        }

        .saved-count-badge {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 11px;
            border-radius: 20px;
        }

        .explore-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #003580;
            color: white;
            padding: 10px 22px;
            border-radius: 30px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(0, 53, 128, 0.2);
        }

        .explore-btn:hover {
            background: #ff9800;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.3);
        }

        /* ── Grid ── */
        .saved-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(295px, 1fr));
            gap: 24px;
        }

        /* ── Card ── */
        .saved-item-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 53, 128, 0.07);
            border: 1px solid #edf0f7;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            animation: cardRise 0.5s ease both;
        }

        .saved-item-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0, 53, 128, 0.13);
        }

        .saved-item-card:nth-child(1) {
            animation-delay: 0.04s;
        }

        .saved-item-card:nth-child(2) {
            animation-delay: 0.08s;
        }

        .saved-item-card:nth-child(3) {
            animation-delay: 0.12s;
        }

        .saved-item-card:nth-child(4) {
            animation-delay: 0.16s;
        }

        .saved-item-card:nth-child(5) {
            animation-delay: 0.20s;
        }

        .saved-item-card:nth-child(6) {
            animation-delay: 0.24s;
        }

        @keyframes cardRise {
            from {
                opacity: 0;
                transform: translateY(24px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Card Image */
        .saved-item-image {
            position: relative;
            height: 195px;
            overflow: hidden;
        }

        .saved-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.45s ease;
        }

        .saved-item-card:hover .saved-item-image img {
            transform: scale(1.07);
        }

        .saved-item-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.38) 0%, transparent 55%);
        }

        /* Remove button */
        .remove-saved-btn {
            position: absolute;
            top: 11px;
            right: 11px;
            background: rgba(255, 255, 255, 0.92);
            border: none;
            border-radius: 50%;
            width: 33px;
            height: 33px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #e53935;
            z-index: 5;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        }

        .remove-saved-btn:hover {
            background: #e53935;
            color: white;
            transform: scale(1.12) rotate(8deg);
        }

        /* Card Body */
        .saved-item-content {
            padding: 18px 18px 15px;
        }

        .saved-item-category {
            display: inline-block;
            background: #eef2ff;
            color: #003580;
            font-size: 0.67rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 3px 11px;
            border-radius: 20px;
            margin-bottom: 9px;
        }

        .saved-item-location {
            font-size: 0.76rem;
            color: #9299a8;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

        .saved-item-location i {
            color: #ff9800;
            font-size: 0.72rem;
        }

        .saved-item-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0d1b3e;
            margin: 0 0 10px;
            line-height: 1.45;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .saved-item-price {
            display: flex;
            align-items: baseline;
            gap: 5px;
            margin-bottom: 14px;
        }

        .price-label {
            font-size: 0.72rem;
            color: #aaa;
        }

        .price-value {
            font-size: 1.15rem;
            font-weight: 800;
            color: #ff9800;
        }

        /* Card Footer */
        .saved-item-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid #f2f4f8;
        }

        .saved-date {
            font-size: 0.7rem;
            color: #c8cdd8;
        }

        .view-btn {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            font-family: 'Poppins', sans-serif;
        }

        .view-btn:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(255, 152, 0, 0.3);
        }

        /* ── Loading shimmer ── */
        .loading-saved {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            color: #aaa;
            font-size: 0.9rem;
        }

        /* ── Empty State ── */
        .empty-saved-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 24px;
            border: 1px solid #edf0f7;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            animation: fadeIn 0.5s ease;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 18px;
            display: block;
        }

        .empty-saved-state h2 {
            font-size: 1.5rem;
            color: #0d1b3e;
            margin-bottom: 8px;
        }

        .empty-saved-state p {
            color: #8a91a0;
            font-size: 0.92rem;
            margin-bottom: 26px;
        }

        .browse-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            padding: 13px 34px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 6px 18px rgba(0, 53, 128, 0.2);
        }

        .browse-btn:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(255, 152, 0, 0.3);
            color: white;
        }

        /* ── Back Button ── */
        .back-button-container {
            text-align: center;
            padding: 10px 15px 50px;
        }

        .back-button {
            background: none;
            border: 2px solid #003580;
            color: #003580;
            padding: 11px 32px;
            border-radius: 50px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .back-button:hover {
            background: #003580;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 53, 128, 0.2);
        }

        /* ── Keyframes ── */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .banner-text h1 {
                font-size: 1.5rem;
            }

            .saved-page-banner {
                padding: 95px 5% 40px;
            }

            .saved-items-grid {
                grid-template-columns: 1fr;
            }

            .banner-stats {
                gap: 10px;
            }

            .banner-stat-num {
                font-size: 1.3rem;
            }
        }

        body {
            background: #f0f4f8;
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }

        /* ---- Hero Banner ---- */
        .saved-hero {
            background: linear-gradient(135deg, #003580 0%, #1a4b8c 50%, #0056b3 100%);
            padding: 100px 5% 60px;
            position: relative;
            overflow: hidden;
            text-align: center;
            color: white;
        }

        .saved-hero::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 300px;
            height: 300px;
            background: rgba(255, 152, 0, 0.12);
            border-radius: 50%;
            animation: heroPulse 6s ease-in-out infinite;
        }

        .saved-hero::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -40px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
            animation: heroPulse 8s ease-in-out infinite reverse;
        }

        @keyframes heroPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.8;
            }

            50% {
                transform: scale(1.15);
                opacity: 1;
            }
        }

        .saved-hero-icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
            display: block;
            animation: iconBounce 2s ease-in-out infinite;
        }

        @keyframes iconBounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .saved-hero h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 10px;
            animation: fadeInDown 0.6s ease;
        }

        .saved-hero p {
            font-size: 1rem;
            opacity: 0.85;
            margin: 0;
            animation: fadeInUp 0.7s ease;
        }

        .saved-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(8px);
            padding: 8px 22px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 18px;
            animation: fadeIn 0.9s ease;
        }

        /* ---- Saved Section Wrapper ---- */
        .saved-page-section {
            padding: 40px 5% 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ---- Grid ---- */
        .saved-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 28px;
            margin-top: 10px;
        }

        /* ---- Card ---- */
        .saved-item-card {
            background: white;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 6px 24px rgba(0, 53, 128, 0.08);
            transition: transform 0.35s ease, box-shadow 0.35s ease;
            position: relative;
            animation: cardFadeUp 0.5s ease both;
        }

        .saved-item-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 18px 45px rgba(0, 53, 128, 0.15);
        }

        /* Staggered card animation */
        .saved-item-card:nth-child(1) {
            animation-delay: 0.05s;
        }

        .saved-item-card:nth-child(2) {
            animation-delay: 0.10s;
        }

        .saved-item-card:nth-child(3) {
            animation-delay: 0.15s;
        }

        .saved-item-card:nth-child(4) {
            animation-delay: 0.20s;
        }

        .saved-item-card:nth-child(5) {
            animation-delay: 0.25s;
        }

        .saved-item-card:nth-child(6) {
            animation-delay: 0.30s;
        }

        @keyframes cardFadeUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ---- Card Image ---- */
        .saved-item-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .saved-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .saved-item-card:hover .saved-item-image img {
            transform: scale(1.08);
        }

        /* Gradient overlay on image */
        .saved-item-image::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.35), transparent);
        }

        /* ---- Remove Button ---- */
        .remove-saved-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 34px;
            height: 34px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: #dc3545;
            z-index: 5;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .remove-saved-btn:hover {
            background: #dc3545;
            color: white;
            transform: scale(1.15);
        }

        /* ---- Card Content ---- */
        .saved-item-content {
            padding: 20px 20px 16px;
        }

        .saved-item-category {
            display: inline-block;
            background: linear-gradient(135deg, #e8f0fe, #d0e3ff);
            color: #003580;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 8px;
        }

        .saved-item-location {
            font-size: 0.78rem;
            color: #888;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 6px;
        }

        .saved-item-location i {
            color: #ff9800;
        }

        .saved-item-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 12px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .saved-item-price {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 16px;
        }

        .price-label {
            font-size: 0.75rem;
            color: #999;
        }

        .price-value {
            font-size: 1.2rem;
            font-weight: 800;
            color: #ff9800;
        }

        /* ---- Card Footer ---- */
        .saved-item-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 14px;
            border-top: 1px solid #f0f0f0;
        }

        .saved-date {
            font-size: 0.72rem;
            color: #bbb;
        }

        .view-btn {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-btn:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.35);
        }

        /* ---- Empty State ---- */
        .empty-saved-state {
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 28px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            animation: fadeIn 0.6s ease;
        }

        .empty-state-icon {
            font-size: 4.5rem;
            margin-bottom: 20px;
            animation: iconBounce 3s ease-in-out infinite;
            display: block;
        }

        .empty-saved-state h2 {
            font-size: 1.6rem;
            color: #1a1a2e;
            margin-bottom: 10px;
        }

        .empty-saved-state p {
            color: #888;
            margin-bottom: 28px;
            font-size: 0.95rem;
        }

        .browse-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            padding: 14px 36px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0, 53, 128, 0.2);
        }

        .browse-btn:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 152, 0, 0.35);
            color: white;
        }

        /* ---- Section Header ---- */
        .saved-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 26px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .saved-section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #003580;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .saved-count-chip {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            min-width: 28px;
            text-align: center;
        }

        /* ---- Back Button ---- */
        .back-button-container {
            text-align: center;
            padding: 40px 15px;
            margin-top: 10px;
        }

        .back-button {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 6px 20px rgba(0, 53, 128, 0.2);
        }

        .back-button:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 152, 0, 0.3);
            color: white;
        }

        /* ---- Global Animations ---- */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ---- Responsive ---- */
        @media (max-width: 768px) {
            .saved-hero h1 {
                font-size: 1.6rem;
            }

            .saved-hero {
                padding: 90px 5% 50px;
            }

            .saved-items-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='index.php'" style="cursor:pointer;">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
        <div class="nav-container">
            <div class="hamburger-menu">
                <button class="hamburger-icon" id="menuToggle" aria-label="Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <div class="panel-overlay" id="panelOverlay"></div>

    <!-- ══════════════════════════════════════
         MODERN COLLAPSIBLE SIDEBAR
         ══════════════════════════════════════ -->
    <div class="side-panel" id="sidePanel">

        <!-- Collapse Toggle Button -->

        <!-- Profile Header -->
        <div class="sidebar-profile">
            <div class="sidebar-avatar" id="sidebarAvatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="sidebar-user-info" id="sidebarUserInfo">
                <div class="sidebar-user-role" id="sidebarUserRole">Guest</div>
                <div class="sidebar-user-name" id="sidebarUserName">Welcome!</div>
            </div>
        </div>

        <!-- Nav Body -->
        <div class="sidebar-nav-body">

            <!-- ── MAIN Section ── -->
            <div class="sidebar-section-label">Main Menu</div>

            <a href="index.php" class="sidebar-nav-item" id="nav-home">
                <i class="fas fa-home sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Home</span>
                <span class="sidebar-tooltip">Home</span>
            </a>

            <a href="local-destination.php" class="sidebar-nav-item" id="nav-local">
                <i class="fas fa-map-marker-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Local Tours</span>
                <span class="sidebar-tooltip">Local Tours</span>
            </a>

            <a href="foreign-destinations.php" class="sidebar-nav-item" id="nav-foreign">
                <i class="fas fa-plane sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Foreign Tours</span>
                <span class="sidebar-tooltip">Foreign Tours</span>
            </a>

            <a href="flash-deals.php" class="sidebar-nav-item" id="nav-deals">
                <i class="fas fa-tag sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Flash Deals</span>
                <span class="sidebar-tooltip">Flash Deals</span>
            </a>

            <!-- My Booking Link -->
            <button class="sidebar-nav-item" id="nav-my-booking" onclick="requireLogin('goToProfile')"
                style="border:none; text-align:left; background:#ffffff; width:100%;">
                <i class="fas fa-calendar-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">My Booking</span>
                <span class="sidebar-tooltip">My Booking</span>
            </button>

            <!-- My Account dropdown -->
            <button class="sidebar-nav-item" id="nav-account-toggle"
                onclick="toggleSidebarDropdown('accountDropdown', this)">
                <i class="fas fa-user sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">My Account</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">My Account</span>
            </button>
            <div class="sidebar-dropdown-content" id="accountDropdown">
                <a href="User Account/my-profile.php" class="sidebar-sub-item">
                    <i class="fas fa-user-edit" style="color:#003580;font-size:0.8rem;"></i> My Profile
                </a>
                <button class="sidebar-sub-item" onclick="requireLogin('goToSaved')">
                    <i class="fas fa-star" style="color:#ff9800;font-size:0.8rem;"></i>
                    Saved
                    <span
                        style="background:#ff9800;color:white;padding:1px 7px;border-radius:20px;font-size:0.7rem;margin-left:6px;"
                        id="savedCount">0</span>
                </button>
            </div>

            <!-- Social Media dropdown -->
            <button class="sidebar-nav-item" id="nav-social-toggle"
                onclick="toggleSidebarDropdown('socialDropdown', this)">
                <i class="fas fa-share-nodes sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Social Media</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Social Media</span>
            </button>
            <div class="sidebar-dropdown-content" id="socialDropdown">
                <a href="https://www.facebook.com/profile.php?id=61583752858443" target="_blank"
                    class="sidebar-sub-item">
                    <i class="fab fa-facebook-f" style="color:#1877f2;font-size:0.8rem;"></i> Facebook
                </a>
                <a href="https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                    target="_blank" class="sidebar-sub-item">
                    <i class="fab fa-instagram" style="color:#e4405f;font-size:0.8rem;"></i> Instagram
                </a>
                <a href="https://x.com/HeyDreamTravel?s=20" target="_blank" class="sidebar-sub-item">
                    <i class="fa-brands fa-x-twitter" style="color:#000;font-size:0.8rem;"></i> X (Twitter)
                </a>
                <a href="https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc"
                    target="_blank" class="sidebar-sub-item">
                    <i class="fab fa-tiktok" style="color:#000;font-size:0.8rem;"></i> TikTok
                </a>
            </div>

            <!-- Help & Support dropdown -->
            <button class="sidebar-nav-item" id="nav-help-toggle" onclick="toggleSidebarDropdown('helpDropdown', this)">
                <i class="fas fa-headset sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Help &amp; Support</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Help &amp; Support</span>
            </button>
            <div class="sidebar-dropdown-content" id="helpDropdown">
                <a href="help-support.php" class="sidebar-sub-item">
                    <i class="fas fa-question-circle" style="color:#003580;font-size:0.8rem;"></i> FAQs
                </a>

            </div>

            <div class="sidebar-divider"></div>

            <!-- ── SETTINGS Section ── -->
            <div class="sidebar-section-label">Settings</div>

            <button class="sidebar-nav-item" id="nav-settings-toggle"
                onclick="toggleSidebarDropdown('settingsDropdown', this)">
                <i class="fas fa-cog sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Settings</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Settings</span>
            </button>
            <div class="sidebar-dropdown-content" id="settingsDropdown">
                <a href="buttons/about.php" class="sidebar-sub-item">
                    <i class="fas fa-info-circle" style="color:#003580;"></i> About Us
                </a>
                <a href="terms.php" class="sidebar-sub-item">
                    <i class="fas fa-file-alt" style="color:#003580;"></i> Terms of Service
                </a> <a href="User Account/change-password.php" class="sidebar-sub-item" id="nav-change-password"
                    style="<?php echo $auth->isLoggedIn() ? 'display:block;' : 'display:none;'; ?>">
                    <i class="fas fa-key" style="color:#003580;"></i> Change Password
                </a>
            </div>

        </div><!-- /sidebar-nav-body -->

        <!-- Footer: Logout -->
        <div class="sidebar-footer">
            <div class="sidebar-divider" style="margin:4px 0; opacity: 0.5;"></div>

            <a href="#" onclick="event.preventDefault(); showLogoutConfirmPopup();" class="sidebar-footer-item logout"
                id="sidebarLogoutBtn" style="display:none;">
                <i class="fas fa-sign-out-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Logout Account</span>
                <span class="sidebar-tooltip">Logout</span>
            </a>

            <a href="User Account/login.php" class="sidebar-footer-item" id="sidebarLoginBtn">
                <i class="fas fa-sign-in-alt sidebar-nav-icon" style="color:#003580;"></i>
                <span class="sidebar-nav-label">Sign In</span>
                <span class="sidebar-tooltip">Sign In</span>
            </a>
        </div>

        <!-- ── Bottom Illustration: Travel Scene ── -->
        <div class="sidebar-illustration" aria-hidden="true">
            <svg viewBox="0 0 290 115" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="skyGradNew" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#ffffff" />
                        <stop offset="100%" stop-color="#dce4ed" />
                    </linearGradient>
                    <linearGradient id="mtnGradBack" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#e2e8f0" />
                        <stop offset="100%" stop-color="#cbd5e1" />
                    </linearGradient>
                    <linearGradient id="mtnGradFront" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#cbd5e1" />
                        <stop offset="100%" stop-color="#94a3b8" />
                    </linearGradient>
                </defs>

                <!-- Background Gradient -->
                <rect width="290" height="115" fill="url(#skyGradNew)" />

                <!-- Back Mountains -->
                <path
                    d="M0,115 L0,80 L25,65 L50,85 L85,45 L115,65 L145,50 L175,70 L210,35 L245,65 L275,50 L290,60 L290,115 Z"
                    fill="url(#mtnGradBack)" opacity="0.6" />

                <!-- Front Mountains -->
                <path d="M0,115 L0,95 L35,70 L65,85 L105,55 L135,75 L170,45 L215,80 L255,60 L290,85 L290,115 Z"
                    fill="url(#mtnGradFront)" opacity="0.7" />

                <!-- Birds (Seagulls) -->
                <g fill="none" stroke="#94a3b8" stroke-width="1.5" opacity="0.5">
                    <path d="M60,30 Q65,25 70,30 Q65,27 60,30" />
                    <path d="M70,30 Q75,25 80,30 Q75,27 70,30" />

                    <path d="M180,45 Q185,40 190,45 Q185,42 180,45" />
                    <path d="M190,45 Q195,40 200,45 Q195,42 190,45" />

                    <path d="M260,35 Q264,31 268,35 Q264,32 260,35" stroke-width="1.2" />
                    <path d="M268,35 Q272,31 276,35 Q272,32 268,35" stroke-width="1.2" />

                    <path d="M45,50 Q48,47 51,50 Q48,48 45,50" stroke-width="1" />
                    <path d="M51,50 Q54,47 57,50 Q54,48 51,50" stroke-width="1" />
                </g>

                <!-- Dotted Flight Path -->
                <path d="M35,38 C60,85 120,85 160,55 C190,35 215,28 240,28" fill="none" stroke="#60a5fa"
                    stroke-width="2" stroke-dasharray="4,5" opacity="0.6" />

                <!-- Location Pin (Start) -->
                <g transform="translate(22, 20)">
                    <path d="M13,0 C5.8,0 0,5.8 0,13 C0,22.8 13,32 13,32 C13,32 26,22.8 26,13 C26,5.8 20.2,0 13,0 Z"
                        fill="#4285F4" />
                    <circle cx="13" cy="12" r="5" fill="white" />
                </g>

                <!-- Airplane (End) -->
                <g transform="translate(230, 14) rotate(10) scale(0.9)">
                    <!-- Airplane SVG shape -->
                    <path
                        d="M21.9,10.1 L15.6,8.7 L11.4,1.4 C11.1,0.8 10.5,0.5 10,0.5 C9.7,0.5 9.5,0.6 9.4,0.8 L9.3,2.4 L11.8,9.1 L6.4,9.6 L3.3,6.5 L1.8,6.8 L3.5,10.6 L0.8,11.2 C0.3,11.3 0,11.8 0,12.3 C0,12.7 0.3,13.1 0.7,13.2 L3.5,14 L1.8,17.8 L3.3,18.1 L6.4,15 L11.8,15.5 L9.3,22.2 L9.4,23.8 C9.5,24 9.7,24.1 10,24.1 C10.5,24.1 11.1,23.8 11.4,23.2 L15.6,15.9 L21.9,14.5 C23.2,14.2 24.1,13.1 24.1,11.8 C24.1,10.6 23.2,9.6 21.9,10.1 Z"
                        fill="#4285F4" />
                </g>
            </svg>
        </div>

    </div><!-- /side-panel -->



    <!-- Page Banner -->
    <div class="saved-page-banner">
        <div class="banner-inner">
            <div class="banner-text">
                <h1><i class="fas fa-heart" style="color:#ff9800; margin-right:10px;"></i>Saved Items</h1>
                <p>Your wishlist of favorite tours, destinations and deals</p>
            </div>
            <div class="banner-stats">
                <div class="banner-stat">
                    <span class="banner-stat-num" id="heroSavedCount">0</span>
                    <span class="banner-stat-label">Items Saved</span>
                </div>
            </div>
        </div>
    </div>

    <!-- SAVED PAGE CONTENT -->
    <section class="saved-page-section">
        <div class="saved-toolbar">
            <div class="saved-toolbar-left">
                <span class="saved-toolbar-title">My Wishlist</span>
                <span class="saved-count-badge" id="savedChipCount">0</span>
            </div>
            <a href="index.php" class="explore-btn">
                <i class="fas fa-compass"></i> Explore More
            </a>
        </div>

        <div id="saved-items-grid" class="saved-items-grid">
            <div class="loading-saved"><i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i>Loading your
                saved items...</div>
        </div>

        <div id="empty-saved-state" class="empty-saved-state" style="display: none;">
            <span class="empty-state-icon">📌</span>
            <h2>Nothing saved yet</h2>
            <p>Start exploring and bookmark your favorite tours and activities!</p>
            <a href="index.php" class="browse-btn">
                <i class="fas fa-compass"></i> Browse Tours
            </a>
        </div>
    </section>

    <div class="back-button-container">
        <button class="back-button" onclick="goBack()">
            <i class="fas fa-arrow-left"></i> Back to Home
        </button>
    </div>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-logo-section">
                <div class="footer-logo">
                    <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="footer-logo-img">
                    <span class="footer-brand">HeyDream</span>
                </div>
                <div class="footer-country">
                    <i class="fas fa-globe"></i> Philippines (Pilipinas)
                </div>
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
                        <li><a href="buttons/about.php">About Us</a></li>
                        <li><a href="career.php">Career</a></li>
                        <li><a href="privacy.php">Data Privacy Policy</a></li>
                        <li><a href="terms.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-social">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <!-- JavaScript Files -->
    <script src="js/main.js"></script>
    <script src="js/menu.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/auth-menu.js"></script>
    <script src="js/home-packages.js"></script>
    <script src="js/foreign-packages.js"></script>
    <script src="js/flash-deals.js"></script>
    <script src="js/saved.js"></script>
</body>

</html>
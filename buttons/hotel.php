<?php
// ========================================
// FILE: buttons/hotel.php
// DESCRIPTION: Hotel Bookings with Payment System
// ========================================
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = new Auth($pdo);

// Fetch premium services from database
try {
    $stmt = $pdo->query("SELECT * FROM site_services WHERE service_type = 'premium' AND is_active = 1 ORDER BY display_order, id DESC");
    $db_premium = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_premium = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Hotel</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f1f5f9;
            font-family: 'Poppins', sans-serif;
            color: #1e293b;
        }

        /* Final Hero Perfection */
        .hotel-hero-container {
            padding: 100px 1% 20px;
            width: 100%;
        }

        .hotel-hero {
            background: linear-gradient(135deg, #F5AF19 0%, #FFD700 100%) !important;
            border-radius: 20px;
            margin: 0 auto;
            width: 98%;
            padding: 30px 5% !important;
            color: white;
            position: relative;
            min-height: 319px;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left !important;
            overflow: hidden;
        }

        .hotel-hero::before {
            content: '';
            position: absolute;
            right: -2%;
            top: 50%;
            transform: translateY(-50%);
            width: 68%;
            height: 200%;
            background: url('../images/hotel-hero.png') no-repeat center center;
            background-size: contain;
            opacity: 0.9;
            z-index: 1;
            pointer-events: none;
            filter: brightness(1.05) contrast(1.05);
            -webkit-mask-image: linear-gradient(to left, black 40%, transparent 98%),
                radial-gradient(ellipse at center, black 50%, transparent 95%);
            mask-image: linear-gradient(to left, black 40%, transparent 98%),
                radial-gradient(ellipse at center, black 50%, transparent 95%);
            -webkit-mask-composite: source-in;
            mask-composite: intersect;
        }

        .hotel-hero::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom,
                    transparent 0%,
                    transparent 60%,
                    rgba(241, 245, 249, 0.2) 75%,
                    rgba(241, 245, 249, 0.8) 92%,
                    #f1f5f9 100%);
            z-index: 2;
            pointer-events: none;
        }

        .hotel-hero .hero-content {
            max-width: 500px !important;
            z-index: 5;
            margin-left: 0 !important;
            margin-right: auto !important;
            text-align: left !important;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .hero-title-area {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 20px !important;
            margin-bottom: 5px !important;
        }

        .hotel-hero-badge {
            width: 80px;
            height: 80px;
            background: white !important;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .hotel-hero-badge i {
            font-size: 2.2rem;
            color: #F5AF19;
        }

        .hotel-hero h1 {
            font-size: 4rem;
            font-weight: 900;
            margin: 0;
            color: white !important;
            font-family: 'Poppins', sans-serif;
            letter-spacing: -1.5px;
        }

        .hero-divider {
            width: 100%;
            max-width: 220px;
            height: 1px;
            background: rgba(255, 255, 255, 0.4) !important;
            margin: 20px 0 !important;
            position: relative;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .hero-divider::after {
            content: '\f521';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            background: #F5AF19;
            padding: 0 10px;
            font-size: 0.85rem;
            color: white;
        }

        .hero-description {
            font-size: 1rem;
            line-height: 1.6;
            color: white;
            opacity: 0.95;
            font-weight: 500;
            max-width: 420px;
            text-align: left !important;
        }

        /* Card Section Refinements */
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
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
            color: #FFC107;
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
            color: #002D62;
            margin-bottom: 6px;
            font-weight: 800;
        }

        .title-underline {
            width: 40px;
            height: 3px;
            background: #FFC107;
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
            color: #FFC107;
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
            color: #FFC107;
        }

        .price-amount {
            font-size: 1.6rem;
            font-weight: 900;
            color: #FFC107;
            line-height: 1;
        }

        .price-per {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .card-footer-benefits {
            background: #fffcf0;
            border-top: 1px solid #fff3c4;
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
            color: #FFC107;
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
            background: linear-gradient(90deg, #FFC107, #FF9800);
            color: white;
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 6px 16px rgba(255, 193, 7, 0.3);
            white-space: nowrap;
        }

        .view-details-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 24px rgba(255, 193, 7, 0.4);
        }

        .back-button-container {
            text-align: center;
            padding: 40px 15px;
            margin-top: 20px;
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

        /* Login Required Modal Custom Design */
        .login-required-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-required-modal.active {
            display: flex;
        }

        .login-required-modal-content {
            background: white;
            width: 100%;
            max-width: 450px;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            animation: modalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes modalPop {
            from {
                transform: scale(0.8);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .login-required-header {
            background: #003580;
            padding: 40px 20px;
            text-align: center;
            position: relative;
        }

        .close-login-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            color: rgba(255, 255, 255, 0.7);
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
        }

        .login-required-header .icon-container {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            color: #FF9800;
        }

        .login-required-header h2 {
            color: white;
            margin: 0;
            font-size: 1.8rem;
            font-weight: 800;
        }

        .login-required-body {
            padding: 35px;
            text-align: center;
        }

        .login-required-body h3 {
            color: #003580;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .login-required-body p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .login-required-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .login-btn-primary {
            background: linear-gradient(135deg, #FFC107, #FF9800);
            color: white !important;
            padding: 16px;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(255, 152, 0, 0.3);
            transition: 0.3s;
        }

        .login-btn-secondary {
            background: #f1f5f9;
            color: #003580 !important;
            padding: 16px;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 800;
            transition: 0.3s;
        }

        .login-btn-text {
            background: none;
            border: none;
            color: #94a3b8;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        /* Premium Multi-Step Booking Modal Styles (Flight Style) */
        .booking-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 5000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
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
            background: linear-gradient(135deg, #FFC107 0%, #FF9800 100%);
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
        }

        .booking-modal-header p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
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
        }

        .close-booking:hover {
            background: rgba(255, 255, 255, 0.2);
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
            background: #ff9800;
            color: white;
            box-shadow: 0 0 0 5px rgba(255, 152, 0, 0.2);
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
            color: #ff9800;
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
            background: #ffebee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff5252;
            font-size: 1.5rem;
        }

        .mini-card-info h4 {
            margin: 0;
            color: #ff9800;
            font-size: 1.1rem;
        }

        .mini-card-info .mini-price {
            font-size: 1.3rem;
            font-weight: 900;
            color: #ff9800;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-left: 10px;
            border-left: 4px solid #ff9800;
            color: #ff9800;
            font-weight: 800;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .input-group label .required {
            color: #ff5252;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 1rem;
            transition: 0.3s;
        }

        .input-group input:focus,
        .input-group select:focus {
            border-color: #ff9800;
            outline: none;
            box-shadow: 0 0 0 4px rgba(255, 152, 0, 0.15);
        }

        .summary-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .summary-row {
            display: flex;
            padding: 12px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            width: 140px;
            color: #64748b;
            font-size: 0.9rem;
        }

        .summary-value {
            flex: 1;
            font-weight: 600;
            color: #1e293b;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .pay-option {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: 0.3s;
            position: relative;
        }

        .pay-option:hover {
            border-color: #ff9800;
            background: #fffcf0;
        }

        .pay-option.selected {
            border-color: #ff9800;
            background: #fffcf0;
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.1);
        }

        .pay-radio {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pay-option.selected .pay-radio {
            border-color: #ff9800;
        }

        .pay-option.selected .pay-radio::after {
            content: '';
            width: 10px;
            height: 10px;
            background: #ff9800;
            border-radius: 50%;
        }

        .pay-icon {
            width: 45px;
            height: 45px;
            background: #f8fafc;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }

        .pay-info .pay-name {
            display: block;
            font-weight: 700;
            color: #ff9800;
            font-size: 0.95rem;
        }

        .pay-info .pay-desc {
            font-size: 0.75rem;
            color: #64748b;
        }

        .modal-footer {
            display: flex;
            gap: 15px;
            padding: 25px 30px;
            background: white;
            border-top: 1px solid #e2e8f0;
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
        }

        .btn-proceed {
            flex: 2;
            padding: 15px;
            border: none;
            background: linear-gradient(135deg, #FFC107 0%, #FF9800 100%);
            color: white;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(255, 152, 0, 0.25);
        }

        .back-button:hover {
            background: #ff9800;
            transform: translateY(-2px);
            color: white;
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
            z-index: 2000;
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
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .details-modal-scrollable {
            flex: 1;
            overflow-y: auto;
        }

        .sticky-modal-footer {
            padding: 10px 24px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 20px 20px;
            flex-shrink: 0;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 480px) {
            .sticky-modal-footer {
                flex-direction: column;
                gap: 10px;
                padding: 12px 16px;
                align-items: stretch;
            }

            .sticky-modal-footer>div:first-child {
                justify-content: center;
            }

            .sticky-modal-footer>div:last-child {
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }

            .sticky-modal-footer button {
                width: 100% !important;
                justify-content: center !important;
            }
        }

        .premium-gallery-container {
            display: grid;
            grid-template-columns: 1.8fr 1fr;
            gap: 15px;
            padding: 20px;
            background: #fff;
        }

        .hero-main-slot {
            position: relative;
            height: 320px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .hero-main-slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-overlay-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px 25px 20px;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            color: white;
        }

        .gallery-sidebar {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .sidebar-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 12px;
            height: 250px;
        }

        .sidebar-item {
            border-radius: 15px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
        }

        .sidebar-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            padding: 0 20px 30px;
        }

        .info-card {
            background: #fff;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
        }

        .tour-highlights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        .highlight-tag {
            background: #f8fafc;
            padding: 12px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }

        .highlight-tag i {
            font-size: 1.2rem;
            color: #ff9800;
        }

        .highlight-tag span {
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
        }

        .booking-cta-card {
            position: sticky;
            top: 20px;
            background: linear-gradient(135deg, #003580, #00255a);
            border-radius: 20px;
            padding: 25px;
            color: white;
        }

        .price-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #ff9800;
        }

        .premium-book-btn {
            width: 100%;
            background: #ff9800;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
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
            html {
                background-color: #F5AF19 !important;
            }

            body {
                overscroll-behavior: contain !important;
                overflow-x: hidden !important;
                width: 100% !important;
                position: relative !important;
            }

            .hotel-hero-container {
                padding: 0 !important;
            }

            .hotel-hero {
                padding: 65px 5% 25px !important;
            }

            /* Dim hero image on mobile */
            .hotel-hero::before {
                opacity: 0.3 !important;
                width: 150% !important;
                height: 150% !important;
                right: -25% !important;
                top: 50% !important;
                -webkit-mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
                mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
            }

            .hero-title-area {
                gap: 12px !important;
                margin-bottom: 12px !important;
            }

            .hero-title-area h1 {
                font-size: 2rem !important;
            }

            .hotel-hero-badge {
                width: 50px !important;
                height: 50px !important;
                border-radius: 14px !important;
            }

            .hotel-hero-badge i {
                font-size: 1.4rem !important;
            }

            .hero-description {
                font-size: 0.9rem !important;
                margin: 10px 0 0 !important;
            }

            /* Cards */
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
                opacity: 1 !important;
                transform: translateY(0) !important;
                background: linear-gradient(to top, rgba(0, 0, 0, 0.6) 0%, transparent 100%) !important;
                backdrop-filter: none !important;
                padding: 10px 12px !important;
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

            .btn-row {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .form-row {
                grid-template-columns: 1fr !important;
            }

            /* Modal */
            .premium-gallery-container {
                grid-template-columns: 1fr !important;
            }

            .modal-info-grid {
                grid-template-columns: 1fr !important;
            }

            .booking-cta-card {
                position: static !important;
            }
        }

        @media (max-width: 480px) {
            .hero-title-area h1 {
                font-size: 1.6rem !important;
            }

            .service-card {
                padding: 12px !important;
                border-radius: 20px !important;
            }

            .service-image-container {
                height: 180px !important;
            }

            /* Details modal mobile fixes */
            #serviceDetailsContent [style*="height:350px"] {
                height: 180px !important;
            }

            #serviceDetailsContent [style*="padding:0 40px"] {
                padding: 0 12px !important;
            }

            #serviceDetailsContent [style*="padding:40px"] {
                padding: 16px !important;
            }

            #serviceDetailsContent [style*="grid-template-columns: 2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            #serviceDetailsContent [style*="font-size:2.5rem"] {
                font-size: 1.3rem !important;
            }

            #serviceDetailsContent [style*="padding:25px 40px"] {
                padding: 12px 16px !important;
                flex-direction: column !important;
                gap: 10px !important;
            }

            #serviceDetailsContent [style*="font-size:1.8rem"] {
                font-size: 1.2rem !important;
            }

            #serviceDetailsContent [style*="padding:18px 45px"] {
                padding: 12px 20px !important;
                font-size: 0.9rem !important;
            }
        }

        /* ============================================
           PAGE ENTRANCE ANIMATIONS
        ============================================ */

        /* ============================================
           PREMIUM HOTEL ENTRANCE LOADER
        ============================================ */
        #page-loader {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #FACC15, #F59E0B, #D97706);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease, visibility 0.8s ease;
            overflow: hidden;
            color: white;
        }

        #page-loader.hidden {
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

        .bg-room {
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            mix-blend-mode: overlay;
            opacity: 0.15;
        }

        /* Floating particles */
        .loader-particle {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.3;
            animation: floatParticle 10s linear infinite;
        }

        .lp-1 {
            top: 20%;
            left: 20%;
            width: 4px;
            height: 4px;
            animation-duration: 8s;
        }

        .lp-2 {
            top: 70%;
            left: 10%;
            width: 6px;
            height: 6px;
            animation-duration: 12s;
            animation-delay: 2s;
        }

        .lp-3 {
            top: 40%;
            right: 20%;
            width: 5px;
            height: 5px;
            animation-duration: 9s;
            animation-delay: 1s;
        }

        .lp-4 {
            top: 80%;
            right: 30%;
            width: 7px;
            height: 7px;
            animation-duration: 15s;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(0) scale(1);
                opacity: 0;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                transform: translateY(-100px) scale(0.5);
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
            animation: slideDownHotel 1s ease-out forwards;
        }

        @keyframes slideDownHotel {
            from {
                transform: translateY(-40px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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

        /* Animated Hotel */
        .hotel-animation-container {
            position: relative;
            margin-bottom: 25px;
            animation: floatHotel 4s ease-in-out infinite;
        }

        .hotel-icon-3d {
            font-size: 2.5rem;
            color: white;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.1));
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 15px 35px rgba(217, 119, 6, 0.3);
        }

        @keyframes floatHotel {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-15px);
                box-shadow: 0 25px 45px rgba(217, 119, 6, 0.4);
            }
        }

        /* Text Area */
        .loader-text-area {
            display: none;
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
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: visible;
            display: flex;
            align-items: center;
            padding: 6px;
            margin-bottom: 25px;
            animation: slideUp 1.2s ease-out forwards;
        }

        .loader-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #FDE047 0%, #FFFBEB 100%);
            border-radius: 35px;
            width: 0%;
            position: relative;
            transition: width 0.1s linear;
            box-shadow: 0 0 20px rgba(253, 224, 71, 0.6);
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
            color: #d97706;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
            z-index: 10;
        }

        /* Search Mockup Pill */
        .search-mockup-pill {
            display: flex;
            flex-direction: column;
            background: transparent;
            border: none;
            border-radius: 0;
            align-items: center;
            justify-content: center;
            padding: 0;
            gap: 10px;
            margin-bottom: 40px;
            font-size: 0.95rem;
            width: 100%;
            max-width: 600px;
            animation: slideUp 1.4s ease-out forwards;
        }

        .mockup-route {
            display: none;
        }

        .mockup-route i {
            font-size: 1.2rem;
        }

        .mockup-status {
            background: transparent;
            padding: 0;
            border-radius: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-weight: 800;
            font-size: 1.8rem;
            color: #000000;
            text-align: center;
        }

        .mockup-status i {
            display: none;
        }

        /* Loading Steps - Single Dynamic Step */
        .loader-steps {
            display: none;
            gap: 20px;
            margin-bottom: 40px;
            justify-content: center;
            align-items: center;
            animation: slideUpHotel 1.2s ease-out forwards;
            height: 80px;
            min-height: 80px;
        }

        /* Mobile Status Card */
        .mobile-status-card {
            display: none;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 24px;
            padding: 16px 20px;
            width: 100%;
            max-width: 90%;
            margin-bottom: 30px;
            align-items: center;
            gap: 18px;
            animation: slideUp 1.6s ease-out forwards;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .msc-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: white;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            flex-shrink: 0;
            position: relative;
        }

        .msc-icon-wrapper.stage-1 {
            color: #0284c7;
            box-shadow: 0 0 20px rgba(2, 132, 199, 0.4);
        }

        .msc-icon-wrapper.stage-2 {
            color: #059669;
            box-shadow: 0 0 20px rgba(5, 150, 105, 0.4);
        }

        .msc-icon-wrapper.stage-3 {
            color: #d97706;
            box-shadow: 0 0 20px rgba(217, 119, 6, 0.4);
        }

        .msc-icon-wrapper.stage-4 {
            color: #9333ea;
            box-shadow: 0 0 20px rgba(147, 51, 234, 0.4);
        }

        .msc-icon-wrapper.stage-5 {
            color: #16a34a;
            box-shadow: 0 0 20px rgba(22, 163, 74, 0.5);
        }

        .msc-icon-wrapper i {
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .msc-text {
            flex: 1;
            text-align: left;
            overflow: hidden;
            position: relative;
        }

        .msc-stage-indicator {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            opacity: 0.8;
            margin-bottom: 4px;
            color: rgba(255, 255, 255, 0.9);
        }

        .msc-title {
            font-weight: 800;
            font-size: 1.1rem;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .msc-desc {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 2px;
            transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .msc-content-group.anim-out {
            opacity: 0;
            transform: translateY(-15px);
        }

        .msc-content-group.anim-in {
            opacity: 0;
            transform: translateY(15px);
        }

        .msc-check-overlay {
            position: absolute;
            inset: 0;
            background: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            opacity: 0;
            transform: scale(0.5) rotate(-45deg);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 5;
        }

        .msc-check-overlay.show {
            opacity: 1;
            transform: scale(1) rotate(0deg);
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
            animation: stepFadeInHotel 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            font-weight: 700;
            color: white;
        }

        .loader-step.done {
            display: none;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #F5AF19 0%, #FFD700 100%);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            box-shadow: 0 8px 32px rgba(245, 175, 25, 0.4);
            transition: all 0.4s ease;
        }

        .loader-step.active .step-icon {
            animation: iconPulseHotel 1.5s ease-in-out infinite;
        }

        /* Footer */
        .loader-footer {
            position: relative;
            font-size: 0.95rem;
            opacity: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
            margin-top: 25px;
            color: white;
            animation: fadeIn 2s ease-out forwards;
            transition: opacity 0.4s ease, transform 0.4s ease;
            text-align: center;
            max-width: 280px;
        }

        @keyframes stepFadeInHotel {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes iconPulseHotel {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 8px 32px rgba(245, 175, 25, 0.4);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 12px 48px rgba(245, 175, 25, 0.6);
            }
        }

        @keyframes slideUpHotel {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            #page-loader {
                padding: 20px;
            }

            .loader-logo-area {
                top: 20px;
                gap: 8px;
            }

            .loader-logo-area img {
                width: 35px;
            }

            .loader-logo-area h2 {
                font-size: 1.1rem;
            }

            .loader-steps {
                gap: 15px;
                margin-bottom: 20px;
                max-width: 90%;
                height: auto;
            }

            .loader-step {
                font-size: 0.75rem;
            }

            .step-icon {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }

            .loader-footer {
                font-size: 0.8rem;
                margin-top: 15px;
                gap: 8px;
            }
        }

        /* --- Hero entrance --- */
        .hotel-hero-container {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease 0.1s, transform 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
        }

        .hotel-hero-container.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- Hero content inner elements --- */
        .hotel-hero .hero-content .hotel-hero-badge {
            opacity: 0;
            transform: scale(0.7) rotate(-10deg);
            transition: opacity 0.5s ease 0.4s, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.4s;
        }

        .hotel-hero-container.animate-in .hotel-hero-badge {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }

        .hotel-hero h1 {
            opacity: 0;
            transform: translateX(-20px);
            transition: opacity 0.5s ease 0.5s, transform 0.5s ease 0.5s;
        }

        .hotel-hero-container.animate-in h1 {
            opacity: 1;
            transform: translateX(0);
        }

        .hero-divider,
        .hero-description {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.5s ease 0.65s, transform 0.5s ease 0.65s;
        }

        .hotel-hero-container.animate-in .hero-divider,
        .hotel-hero-container.animate-in .hero-description {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- Section title fade in --- */
        .section-title-area {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease 0.5s, transform 0.6s ease 0.5s;
        }

        .section-title-area.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- Cards staggered slide-up --- */
        .service-card {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .service-card.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* stagger delays 1-9 */
        .service-card:nth-child(1) {
            transition-delay: 0.55s;
        }

        .service-card:nth-child(2) {
            transition-delay: 0.70s;
        }

        .service-card:nth-child(3) {
            transition-delay: 0.85s;
        }

        .service-card:nth-child(4) {
            transition-delay: 1.00s;
        }

        .service-card:nth-child(5) {
            transition-delay: 1.15s;
        }

        .service-card:nth-child(6) {
            transition-delay: 1.30s;
        }

        .service-card:nth-child(7) {
            transition-delay: 1.45s;
        }

        .service-card:nth-child(8) {
            transition-delay: 1.60s;
        }

        .service-card:nth-child(9) {
            transition-delay: 1.75s;
        }

        /* override delay when animating in (already set per child above) */
        .service-card.animate-in {}
    </style>
</head>

<body>

    <!-- ===== PREMIUM HOTEL PAGE ENTRANCE LOADER ===== -->
    <div id="page-loader">
        <div class="loader-bg-element bg-room"></div>
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

        <div class="hotel-animation-container">
            <div class="hotel-icon-3d"><i class="fas fa-hotel"></i></div>
        </div>

        <div class="loader-text-area">
            <h1 id="loaderTitle">Finding Your Perfect Stay...</h1>
            <p id="loaderSubtext">We're searching the best hotels for you</p>
        </div>

        <div class="search-mockup-pill">
            <div class="mockup-route">
                <div
                    style="background: rgba(255,255,255,0.2); width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; margin-right:5px;">
                    <i class="fas fa-map-marker-alt"></i></div>
                <span id="dynamicRouteText">Searching Hotels</span>
            </div>
            <div class="mockup-status">
                <span id="mockupStatusText">Scanning stays...</span>
                <i class="fas fa-bed" id="mockupStatusIcon"></i>
            </div>
        </div>

        <div class="loader-progress-container">
            <div class="loader-progress-fill" id="loaderBarFill"></div>
            <div class="loader-percent" id="loaderPercent">0%</div>
        </div>

        <div class="loader-steps">
            <div class="loader-step active stage-1" id="lStep1">
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <span>Searching Hotels</span>
            </div>
            <div class="loader-step stage-2" id="lStep2">
                <div class="step-icon"><i class="fas fa-tags"></i></div>
                <span>Comparing Prices</span>
            </div>
            <div class="loader-step stage-3" id="lStep3">
                <div class="step-icon"><i class="fas fa-shield-alt"></i></div>
                <span>Securing Options</span>
            </div>
            <div class="loader-step stage-4" id="lStep4">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <span>Finalizing Results</span>
            </div>
        </div>

        <div class="loader-footer">
            <i class="fas fa-concierge-bell"></i> <span id="loaderFooterText">Sit back and relax, we're finding the best
                hotel experience for you.</span>
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

    <div class="hotel-hero-container">
        <div class="hotel-hero">
            <div class="hero-content">
                <div class="hero-title-area">
                    <div class="hotel-hero-badge">
                        <i class="fas fa-hotel"></i>
                    </div>
                    <h1>Hotel</h1>
                </div>
                <div class="hero-divider"></div>
                <p class="hero-description">
                    Find your perfect stay with our curated selection of luxury hotels and exclusive accommodations
                    worldwide.
                </p>
            </div>
        </div>
    </div>

    <div class="service-content">
        <div class="service-grid">
            <?php if (!empty($db_premium)): ?>
                <?php foreach ($db_premium as $service): ?>
                    <div class="service-card">
                        <div class="card-main-layout">
                            <div class="service-image-container">
                                <div class="card-badge">
                                    <i class="fas fa-hotel"></i> Hotel
                                </div>
                                <img src="../<?php echo htmlspecialchars($service['featured_image']); ?>"
                                    alt="<?php echo htmlspecialchars($service['title']); ?>">
                                <div class="image-overlay-info">
                                    <div class="overlay-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">Prime Location</span>
                                            <span class="overlay-sub">Top Destination</span>
                                        </div>
                                    </div>
                                    <div class="overlay-item">
                                        <i class="fas fa-bed"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">Luxury Stay</span>
                                            <span class="overlay-sub">5-Star Comfort</span>
                                        </div>
                                    </div>
                                    <div class="overlay-item">
                                        <i class="fas fa-shield-alt"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">Trusted Quality</span>
                                            <span class="overlay-sub">Verified Properties</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="service-card-body">
                                <h3><?php echo htmlspecialchars($service['title']); ?></h3>
                                <div class="title-underline"></div>
                                <p class="service-short-desc">
                                    <?php echo htmlspecialchars($service['description']); ?>
                                </p>

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
                                            <span class="dash-label">Type</span>
                                            <span
                                                class="dash-value"><?php echo htmlspecialchars($service['duration'] ?: 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="price-section">
                                    <div style="display: flex; flex-direction: column;">
                                        <span class="price-per">Starting from</span>
                                        <div style="display: flex; align-items: baseline; gap: 5px;">
                                            <span class="price-currency">₱</span>
                                            <span class="price-amount"><?php echo number_format($service['price']); ?></span>
                                            <span class="price-per">/person</span>
                                        </div>
                                    </div>

                                    <button class="view-details-btn"
                                        onclick="viewServiceDetails(<?php echo $service['id']; ?>)">
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
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 20px; width: 100%;">
                    <i class="fas fa-hotel" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 20px;"></i>
                    <p style="color: #64748b; font-weight: 500;">No hotel services available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="back-button-container">
            <a href="../index.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div> <!-- Details Modal -->
    <div id="serviceDetailsModal" class="details-modal">
        <div id="serviceDetailsContent" class="details-modal-content">
            <!-- Dynamic Content -->
        </div>
    </div>

    <!-- Multi-Step Booking Modal (Redesigned) -->
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

            <div class="booking-body" id="step-contents-container">
                <!-- Contents injected via JS -->
            </div>

            <div class="modal-footer" id="modal-footer-container">
                <!-- Buttons injected via JS -->
            </div>
        </div>
    </div>

    <script src="../js/auth-menu.js"></script>
    <script src="../js/main.js"></script>
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
            // Robust price cleaning: remove anything not a digit or dot
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
            const name = document.getElementById('fullName').value;
            const phone = document.getElementById('phone').value;
            const checkIn = document.getElementById('checkIn').value;
            const checkOut = document.getElementById('checkOut').value;
            const travelers = document.getElementById('travelers').value;

            if (!name || !phone || !checkIn || !checkOut) {
                alert('Please fill in all required fields.');
                return;
            }

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
            updateStepIndicators(4);
            const container = document.getElementById('step-contents-container');
            const footer = document.getElementById('modal-footer-container');

            // Show loading state
            container.innerHTML = `
                <div style="text-align:center; padding:60px 20px;">
                    <div class="loading-spinner" style="width:60px; height:60px; border:5px solid #f3f3f3; border-top:5px solid #ff9800; border-radius:50%; margin:0 auto 20px; animation: spin 1s linear infinite;"></div>
                    <h3 style="color:#1e293b;">Processing Your Booking...</h3>
                    <p style="color:#64748b;">Please don't close this window.</p>
                </div>
            `;
            footer.innerHTML = '';

            // Prepare Data
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

        function viewServiceDetails(id) {
            console.log('Fetching details for ID:', id);
            fetch(`../api/get-service-details.php?id=${id}`)
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Server returned non-JSON:', text);
                        throw new Error('Server Error: ' + text.substring(0, 100));
                    }
                })
                .then(result => {
                    if (result.success) {
                        renderServiceDetails(result.data);
                        document.getElementById('serviceDetailsModal').classList.add('active');
                    } else {
                        alert('Database Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('Error: ' + error.message);
                });
        }

        function closeServiceDetails() {
            document.getElementById('serviceDetailsModal').classList.remove('active');
        }

        function getHighlightIcon(text) {
            text = text.toLowerCase();
            if (text.includes('wifi')) return 'fa-wifi';
            if (text.includes('pool')) return 'fa-swimming-pool';
            if (text.includes('breakfast')) return 'fa-utensils';
            if (text.includes('gym')) return 'fa-dumbbell';
            if (text.includes('transfer')) return 'fa-car';
            return 'fa-check-circle';
        }

        function renderServiceDetails(data) {
            const content = document.getElementById('serviceDetailsContent');
            let gallery = [];
            try { gallery = data.image_gallery ? JSON.parse(data.image_gallery) : []; } catch (e) { gallery = []; }

            const featured_img = data.featured_image ? '../' + data.featured_image : '../images/placeholder-hotel.jpg';
            const highlights = data.highlights ? data.highlights.split('\n').filter(t => t.trim()) : [];
            const amenities = data.amenities ? data.amenities.split('\n').filter(t => t.trim()) : [];
            const price = Number(data.price).toLocaleString();

            content.innerHTML = `
                <div class="details-modal-scrollable">
                <!-- Modal Header -->
                <div style="position:relative; height:350px; overflow:hidden; border-radius:20px 20px 0 0;">
                    <img src="${featured_img}" style="width:100%; height:100%; object-fit:cover;">
                    <div style="position:absolute; bottom:0; left:0; right:0; padding:40px; background:linear-gradient(transparent, rgba(0,0,0,0.8)); color:white;">
                        <h2 style="font-size:2.5rem; margin-bottom:10px; color:white;">${data.title}</h2>
                        <div style="display:flex; gap:20px; font-size:0.9rem; opacity:0.9;">
                            <span><i class="fas fa-clock"></i> 24/7 Check-in</span>
                            <span><i class="fas fa-map-marker-alt"></i> Multiple Locations</span>
                        </div>
                    </div>
                    <button onclick="closeServiceDetails()" style="position:absolute; top:20px; right:20px; background:rgba(0,0,0,0.5); color:white; border:none; width:40px; height:40px; border-radius:50%; cursor:pointer; z-index:10;"><i class="fas fa-times"></i></button>
                </div>

                <!-- Modal Tabs -->
                <div style="display:flex; border-bottom:1px solid #eee; padding:0 40px; background:white; overflow-x:auto; white-space:nowrap; -webkit-overflow-scrolling:touch; scrollbar-width:none;">
                    <div class="modal-tab active" onclick="switchTab('overview')" data-tab="overview" style="padding:20px 0; margin-right:40px; font-weight:700; color:#003580; cursor:pointer; border-bottom:3px solid #FFC107; flex-shrink:0;">Overview</div>
                    <div class="modal-tab" onclick="switchTab('amenities')" data-tab="amenities" style="padding:20px 0; margin-right:40px; font-weight:700; color:#64748b; cursor:pointer; flex-shrink:0;">Amenities</div>
                    <div class="modal-tab" onclick="switchTab('gallery')" data-tab="gallery" style="padding:20px 0; margin-right:40px; font-weight:700; color:#64748b; cursor:pointer; flex-shrink:0;">Gallery</div>
                    <div class="modal-tab" onclick="switchTab('policies')" data-tab="policies" style="padding:20px 0; font-weight:700; color:#64748b; cursor:pointer; flex-shrink:0;">Policies</div>
                </div>

                <!-- Modal Body -->
                <div id="modalBodyContent" style="padding:40px; background:white; min-height:400px;">
                    <!-- Overview Tab -->
                    <div id="tab-overview" class="tab-pane active" style="display:grid; grid-template-columns: 2fr 1fr; gap:40px;">
                        <div>
                            <h4 style="color:#003580; margin-bottom:15px; font-size:1.2rem;">Service Description</h4>
                            <p style="color:#64748b; line-height:1.7; margin-bottom:30px;">${data.description}</p>
                            
                            <h4 style="color:#003580; margin-bottom:15px; font-size:1.2rem;">What's Included</h4>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                ${highlights.map(h => `<div style="color:#64748b; font-size:0.9rem;"><i class="fas fa-check" style="color:#28a745; margin-right:8px;"></i> ${h}</div>`).join('')}
                            </div>
                        </div>
                        
                        <div style="background:#f8fafc; border-radius:20px; padding:30px; border:1px solid #e2e8f0; height:fit-content;">
                            <h4 style="color:#003580; margin-bottom:20px; font-size:1.1rem;">Quick Info</h4>
                            <div style="display:flex; flex-direction:column; gap:15px;">
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;"><span style="color:#64748b;">Code:</span><span style="font-weight:700; color:#1e293b;">H-${data.id.toString().padStart(4, '0')}</span></div>
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;"><span style="color:#64748b;">Duration:</span><span style="font-weight:700; color:#1e293b;">${data.duration || 'Flexible'}</span></div>
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;"><span style="color:#64748b;">Confirmation:</span><span style="font-weight:700; color:#28a745;">Instant</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Amenities Tab -->
                    <div id="tab-amenities" class="tab-pane" style="display:none;">
                        <h4 style="color:#003580; margin-bottom:20px; font-size:1.2rem;">Hotel Amenities &amp; Features</h4>
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:20px;">
                            ${amenities.length > 0 ? amenities.map(am => `
                                <div style="padding:20px; background:#f8fafc; border-radius:15px; border:1px solid #e2e8f0; text-align:center;">
                                    <i class="fas ${getHighlightIcon(am)}" style="font-size:2.2rem; color:#FFC107; margin-bottom:10px;"></i>
                                    <div style="font-weight:700; color:#003580;">${am}</div>
                                </div>
                            `).join('') : `
                                <div style="padding:20px; background:#f8fafc; border-radius:15px; border:1px solid #e2e8f0; text-align:center;">
                                    <i class="fas fa-wifi" style="font-size:2rem; color:#FFC107; margin-bottom:10px;"></i>
                                    <div style="font-weight:700; color:#003580;">Free High-Speed WiFi</div>
                                </div>
                                <div style="padding:20px; background:#f8fafc; border-radius:15px; border:1px solid #e2e8f0; text-align:center;">
                                    <i class="fas fa-swimming-pool" style="font-size:2rem; color:#FFC107; margin-bottom:10px;"></i>
                                    <div style="font-weight:700; color:#003580;">Infinity Pool</div>
                                </div>
                                <div style="padding:20px; background:#f8fafc; border-radius:15px; border:1px solid #e2e8f0; text-align:center;">
                                    <i class="fas fa-utensils" style="font-size:2rem; color:#FFC107; margin-bottom:10px;"></i>
                                    <div style="font-weight:700; color:#003580;">Fine Dining</div>
                                </div>
                                <div style="padding:20px; background:#f8fafc; border-radius:15px; border:1px solid #e2e8f0; text-align:center;">
                                    <i class="fas fa-spa" style="font-size:2rem; color:#FFC107; margin-bottom:10px;"></i>
                                    <div style="font-weight:700; color:#003580;">Luxury Spa</div>
                                </div>
                            `}
                        </div>
                    </div>

                    <!-- Gallery Tab -->
                    <div id="tab-gallery" class="tab-pane" style="display:none;">
                        <h4 style="color:#003580; margin-bottom:20px; font-size:1.2rem;">Photo Gallery</h4>
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:15px;">
                            ${gallery.length > 0 ? gallery.map(img => `<img src="../${img}" style="width:100%; height:180px; object-fit:cover; border-radius:15px; cursor:pointer;" onclick="window.open(this.src, '_blank')">`).join('') : '<p style="color:#64748b;">No additional photos available.</p>'}
                        </div>
                    </div>

                    <!-- Policies Tab -->
                    <div id="tab-policies" class="tab-pane" style="display:none;">
                        <h4 style="color:#003580; margin-bottom:20px; font-size:1.2rem;">Booking Policies</h4>
                        <div style="display:flex; flex-direction:column; gap:20px;">
                            <div style="padding:20px; background:#fff5f5; border-radius:15px; border-left:5px solid #ff4444;">
                                <h5 style="color:#ff4444; margin-bottom:5px;"><i class="fas fa-info-circle"></i> Cancellation Policy</h5>
                                <p style="color:#64748b; font-size:0.9rem; margin:0;">${data.cancellation_policy || 'Free cancellation up to 48 hours before check-in. Non-refundable if cancelled within 48 hours.'}</p>
                            </div>
                            <div style="padding:20px; background:#f8fafc; border-radius:15px; border-left:5px solid #003580;">
                                <h5 style="color:#003580; margin-bottom:5px;"><i class="fas fa-clock"></i> Check-in / Check-out</h5>
                                <p style="color:#64748b; font-size:0.9rem; margin:0;">${data.terms_conditions || 'Check-in: 2:00 PM | Check-out: 12:00 PM'}</p>
                            </div>
                        </div>
                    </div>
                </div>
                </div><!-- end scrollable -->

                <!-- Sticky Modal Footer -->
                <div class="sticky-modal-footer">
                    <div style="display:flex; gap:16px;">
                        <div style="display:flex; align-items:center; gap:7px; color:#003580; font-weight:700; font-size:0.82rem;">
                            <i class="fas fa-hotel" style="color:#FFC107;"></i> 4-Star Hotel
                        </div>
                        <div style="display:flex; align-items:center; gap:7px; color:#003580; font-weight:700; font-size:0.82rem;">
                            <i class="fas fa-utensils" style="color:#FFC107;"></i> Daily Breakfast
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="text-align:right;">
                            <span style="display:block; font-size:0.7rem; color:#64748b;">Price starting from</span>
                            <span style="font-size:1.2rem; font-weight:900; color:#FFC107;">₱${price}</span>
                        </div>
                        <button onclick="requireLogin('openBookingModal', ${data.id}, '${data.title.replace(/'/g, "\\'")}', ${data.price})" style="background:linear-gradient(135deg, #FFC107 0%, #FF9800 100%); color:white; border:none; padding:10px 22px; border-radius:50px; font-weight:700; font-size:0.88rem; cursor:pointer; display:flex; align-items:center; gap:8px; box-shadow:0 6px 15px rgba(255,152,0,0.25);">
                            Book Now <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        function switchTab(tabId) {
            // Update Tab Styling
            document.querySelectorAll('.modal-tab').forEach(t => {
                t.style.color = '#64748b';
                t.style.borderBottom = 'none';
            });
            const activeTab = document.querySelector(`.modal-tab[data-tab="${tabId}"]`);
            if (activeTab) {
                activeTab.style.color = '#003580';
                activeTab.style.borderBottom = '3px solid #FFC107';
            }

            // Update Content Visibility
            document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
            const targetPane = document.getElementById(`tab-${tabId}`);
            if (targetPane) {
                if (tabId === 'overview') targetPane.style.display = 'grid';
                else targetPane.style.display = 'block';
            }
        }

        // ============================================
        // PREMIUM HOTEL ENTRANCE ANIMATION CONTROLLER
        // ============================================
        (function () {
            window.addEventListener('load', function () {
                const loader = document.getElementById('page-loader');
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
                    { p: 0, text: 'Scanning stays...', icon: 'fa-bed', title: 'Finding Your Perfect Stay...', destText: (hasRoute ? `Hotels in ${query}` : 'Searching Hotels'), footer: "Sit back and relax, we're finding the best hotel experience for you." },
                    { p: 25, text: 'Comparing rates...', icon: 'fa-tags', title: 'Searching Premium Hotels...', destText: (hasRoute ? `Top picks in ${query}` : 'Multiple Destinations'), footer: "Comparing thousands of accommodations worldwide." },
                    { p: 55, text: 'Securing rooms...', icon: 'fa-shield-alt', title: 'Checking Room Availability...', destText: (hasRoute ? `Availability for ${query}` : 'Worldwide Availability'), footer: "Ensuring the most luxurious and comfortable options." },
                    { p: 80, text: 'Ready', icon: 'fa-check-circle', title: 'Preparing Accommodation Options...', destText: (hasRoute ? `Options Ready for ${query}` : 'Ready for Booking'), footer: "Your premium stays are almost ready." }
                ];

                let lastStage = 0;
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

                    // Update UI only when stage changes (more robust than relying on text equality)
                    if (currentStage !== lastStage) {
                        if (mockupStatusText) mockupStatusText.textContent = stageData.text;
                        if (dynamicRouteEl) dynamicRouteEl.textContent = stageData.destText;
                        if (mockupStatusIcon) mockupStatusIcon.className = 'fas ' + stageData.icon;
                        if (titleEl) titleEl.textContent = stageData.title;
                        if (footerTextEl) footerTextEl.textContent = stageData.footer;

                        for (let i = 1; i <= 4; i++) {
                            const stepEl = document.getElementById('lStep' + i);
                            if (!stepEl) continue;

                            if (i < currentStage) {
                                stepEl.className = 'loader-step done stage-' + i;
                                stepEl.querySelector('i').className = 'fas fa-check';
                            } else if (i === currentStage) {
                                stepEl.className = 'loader-step active stage-' + i;
                            } else {
                                stepEl.className = 'loader-step stage-' + i;
                            }
                        }

                        lastStage = currentStage;
                    }

                    if (progress >= 100) {
                        clearInterval(timer);

                        // Mark all complete with success green styling
                        for (let i = 1; i <= 4; i++) {
                            const stepEl = document.getElementById('lStep' + i);
                            if (stepEl) {
                                stepEl.className = 'loader-step done stage-5';
                                stepEl.querySelector('i').className = 'fas fa-check';
                            }
                        }

                        setTimeout(() => {
                            loader.classList.add('hidden');

                            const heroContainer = document.querySelector('.hotel-hero-container');
                            if (heroContainer) heroContainer.classList.add('animate-in');
                            const sectionTitle = document.querySelector('.section-title-area');
                            if (sectionTitle) sectionTitle.classList.add('animate-in');

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

                            setTimeout(() => loader.remove(), 800);
                        }, 500);
                    }
                }, intervalTime);
            });
        })();
    </script>
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
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                        target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://x.com/HeyDreamTravel?s=20" target="_blank"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc"
                        target="_blank"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
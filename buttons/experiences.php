<?php
// ========================================
// FILE: buttons/experiences.php
// DESCRIPTION: Travel Experiences with Dynamic Database Integration
// ========================================
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = new Auth($pdo);

// Fetch experience services from database
try {
    $stmt = $pdo->query("SELECT * FROM site_services WHERE service_type = 'experience' AND is_active = 1 ORDER BY display_order, id DESC");
    $db_experiences = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_experiences = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Travel Experiences</title>
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

        /* Premium Hero Perfection - Applied from Hotel Design */
        .experience-hero-container {
            padding: 100px 1% 20px;
            width: 100%;
        }

        .experience-hero {
            background: linear-gradient(135deg, #addb4c 0%, #14c492 100%) !important;
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

        .experience-hero::before {
            content: '';
            position: absolute;
            right: -2%;
            top: 50%;
            transform: translateY(-50%);
            width: 68%;
            height: 200%;
            background: url('../images/experience-hero.png') no-repeat center center;
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

        .experience-hero::after {
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

        .experience-hero .hero-content {
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

        .experience-hero-badge {
            width: 80px;
            height: 80px;
            background: white !important;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .experience-hero-badge i {
            font-size: 2.2rem;
            color: #14c492;
        }

        .experience-hero h1 {
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
            background: #addb4c;
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

        .service-content {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 80px;
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

        .image-overlay-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            padding: 15px 20px;
            display: flex;
            justify-content: flex-start;
            gap: 40px;
            color: white;
            z-index: 5;
        }

        .overlay-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.75rem;
        }

        .overlay-item i {
            color: #addb4c;
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
            background: #14c492;
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
            color: #14c492;
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
            color: #14c492;
        }

        .price-amount {
            font-size: 1.6rem;
            font-weight: 900;
            color: #14c492;
            line-height: 1;
        }

        .price-per {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .card-footer-benefits {
            background: #f0fff4;
            border-top: 1px solid #c6f6d5;
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
            color: #14c492;
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
            background: linear-gradient(90deg, #addb4c, #14c492) !important;
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
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 6px 16px rgba(20, 196, 146, 0.3) !important;
            white-space: nowrap;
        }

        .view-details-btn:hover {
            background: linear-gradient(90deg, #b2e650, #16d9a2) !important;
            transform: scale(1.05) !important;
            box-shadow: 0 10px 24px rgba(20, 196, 146, 0.4) !important;
            color: white !important;
        }

        /* Experience Details Modal Book Now Button */
        .details-book-btn {
            background: linear-gradient(135deg, #addb4c, #14c492) !important;
            color: white !important;
            border: none;
            padding: 18px 45px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(20, 196, 146, 0.3) !important;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .details-book-btn:hover {
            background: linear-gradient(135deg, #b2e650, #16d9a2) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 24px rgba(20, 196, 146, 0.4) !important;
            color: white !important;
        }

        .card-price-container {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-left i {
            color: #ff9800;
            width: 14px;
        }

        .card-price-tag {
            font-size: 1.2rem;
            font-weight: 800;
            color: #003580;
        }

        .card-price-tag small {
            font-size: 0.7rem;
            color: #888;
            font-weight: 400;
            display: block;
            text-align: right;
            margin-bottom: -2px;
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
            background: #f57c00;
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

        .benefits-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        .benefit-item {
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #003580;
            border: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }

        .benefit-item:hover {
            background: white;
            border-color: #14c492;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .benefit-item i {
            font-size: 1.1rem;
            color: #14c492;
        }

        .benefit-item h4 {
            font-size: 0.85rem;
            margin: 0;
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
            background: linear-gradient(135deg, #addb4c, #14c492);
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
            background: linear-gradient(135deg, #addb4c, #14c492);
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
            border-left: 3px solid #14c492;
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
            border-color: #14c492;
            box-shadow: 0 0 0 2px rgba(20, 196, 146, 0.1);
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
            background: linear-gradient(135deg, #addb4c, #14c492);
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

        /* Premium Multi-Step Booking Modal Styles (Flight Style) */
        .premium-booking-modal {
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

        .premium-booking-modal.active {
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
            background: linear-gradient(135deg, #addb4c, #14c492);
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
            background: #e6fcf5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #14c492;
            font-size: 1.5rem;
        }

        .mini-card-info h4 {
            margin: 0;
            color: #14c492;
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
            color: #14c492;
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
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 1rem;
            transition: 0.3s;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: #14c492;
            outline: none;
            box-shadow: 0 0 0 4px rgba(20, 196, 146, 0.1);
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
            border-color: #14c492;
            background: #f0fdf4;
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
            color: #14c492;
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
            flex: 2 !important;
            padding: 15px !important;
            border: none !important;
            background: linear-gradient(135deg, #addb4c, #14c492) !important;
            color: white !important;
            border-radius: 12px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 10px !important;
            box-shadow: 0 10px 20px rgba(20, 196, 146, 0.2) !important;
            transition: all 0.3s ease !important;
        }

        .btn-proceed:hover {
            background: linear-gradient(135deg, #b2e650, #16d9a2) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 24px rgba(20, 196, 146, 0.35) !important;
            color: white !important;
        }

        .btn-proceed:disabled {
            background: #cbd5e1 !important;
            box-shadow: none !important;
            cursor: not-allowed !important;
            transform: none !important;
        }

        /* Details Modal Styles */
        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
            padding: 20px;
        }

        .details-modal.active {
            display: flex;
        }

        .details-modal-content {
            background: white;
            width: 100%;
            max-width: 860px;
            max-height: 92vh;
            border-radius: 24px;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        .details-hero {
            position: relative;
            height: 280px;
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
            padding: 25px 30px;
            color: white;
        }

        .details-hero-overlay h2 {
            font-size: 1.7rem;
            margin-bottom: 5px;
            font-weight: 800;
        }

        .details-hero-overlay p {
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .details-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .details-modal-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 0 20px 2px;
            background: white;
            overflow-x: auto;
            overflow-y: hidden !important;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            /* Firefox */
            flex-shrink: 0 !important;
        }

        .details-modal-tabs::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari, Opera */
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

        .exp-modal-scrollable {
            flex: 1;
            overflow-y: auto;
        }

        .exp-sticky-footer {
            padding: 25px 40px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 24px 24px;
            flex-shrink: 0;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.05);
            position: sticky;
            bottom: 0;
            z-index: 100;
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
            background: #ff9800;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 2px #ff9800;
            z-index: 2;
        }

        .day-num {
            font-weight: 800;
            color: #ff9800;
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
            color: #ff9800;
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
            color: #ff9800;
        }

        .details-book-btn {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }

        .details-book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            z-index: 100;
            transition: 0.3s;
        }

        .modal-close:hover {
            background: #ff4444;
            transform: rotate(90deg);
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
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
            position: relative;
        }

        /* Premium Gallery Layout */
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
            transition: transform 0.6s ease;
        }

        .hero-main-slot:hover img {
            transform: scale(1.05);
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

        .hero-overlay-content h2 {
            font-size: 2.2rem;
            margin: 0 0 10px;
            font-weight: 700;
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .sidebar-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .sidebar-item:hover img {
            transform: scale(1.1);
        }

        .view-all-photos-btn {
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            padding: 12px;
            border-radius: 12px;
            color: #003580;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-all-photos-btn:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        /* Modal Info Grid */
        .modal-info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            padding: 0 20px 30px;
        }

        .info-main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .info-card {
            background: #fff;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
        }

        .info-card h4 {
            color: #003580;
            font-size: 1.1rem;
            margin: 0 0 15px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            transition: all 0.3s ease;
        }

        .highlight-tag:hover {
            background: #fff;
            border-color: #ff9800;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.1);
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
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.2);
        }

        .price-breakdown {
            margin-bottom: 20px;
        }

        .price-label {
            font-size: 0.9rem;
            opacity: 0.8;
            display: block;
        }

        .price-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #ff9800;
        }

        .cta-details {
            margin: 20px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }

        .cta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .premium-book-btn {
            width: 100%;
            background: #ff9800;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .premium-book-btn:hover {
            background: #f57c00;
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }

        @media (max-width: 850px) {
            .premium-gallery-container {
                grid-template-columns: 1fr;
            }

            .modal-info-grid {
                grid-template-columns: 1fr;
            }

            .hero-main-slot {
                height: 220px;
            }

            .hero-overlay-content {
                padding: 15px;
            }

            .hero-overlay-content h2 {
                font-size: 1.3rem;
                margin-bottom: 5px;
            }

            .hero-overlay-content p {
                font-size: 0.75rem;
            }

            .sidebar-grid {
                height: 160px;
            }
        }

        @media (max-width: 550px) {
            .service-grid {
                gap: 12px;
            }

            .service-card {
                padding: 15px 12px;
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

        /* ===================== TABLET ===================== */
        @media (max-width: 1024px) {
            .service-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        /* ===================== MOBILE RESPONSIVE ===================== */
        @media (max-width: 768px) {
            html {
                background-color: #A3CB38 !important;
            }

            body {
                overscroll-behavior: contain !important;
                overflow-x: hidden !important;
                width: 100% !important;
                position: relative !important;
            }

            /* Hero */
            .experience-hero-container {
                padding: 0 !important;
            }

            .experience-hero {
                border-radius: 0 0 24px 24px !important;
                min-height: 260px !important;
                padding: 75px 5% 30px !important;
            }

            .experience-hero::before {
                opacity: 0.25 !important;
                width: 100% !important;
                height: 100% !important;
                right: 0 !important;
                -webkit-mask-image: linear-gradient(to bottom, black 30%, transparent 100%) !important;
                mask-image: linear-gradient(to bottom, black 30%, transparent 100%) !important;
            }

            .hero-title-area {
                gap: 12px !important;
                margin-bottom: 10px !important;
            }

            .experience-hero h1 {
                font-size: 2.2rem !important;
            }

            .experience-hero-badge {
                width: 52px !important;
                height: 52px !important;
                border-radius: 14px !important;
            }

            .experience-hero-badge i {
                font-size: 1.4rem !important;
            }

            .hero-description {
                font-size: 0.88rem !important;
                max-width: 100% !important;
                margin: 8px 0 0 !important;
            }

            /* Card Grid — single column on mobile */
            .service-content {
                padding: 24px 4% !important;
            }

            .service-grid {
                grid-template-columns: 1fr !important;
                gap: 18px !important;
                max-width: 100% !important;
            }

            .service-card {
                border-radius: 20px !important;
            }

            .service-image-container {
                width: 100% !important;
                height: 200px !important;
                border-radius: 0 !important;
            }

            .service-card-body {
                padding: 14px 16px 10px !important;
            }

            .service-card h3 {
                font-size: 1.1rem !important;
            }

            .info-dashboard-row {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }

            .price-section {
                flex-direction: row !important;
                align-items: center !important;
                gap: 10px !important;
            }

            .view-details-btn {
                padding: 9px 14px !important;
                font-size: 0.78rem !important;
            }

            .card-footer-benefits {
                flex-direction: column !important;
                gap: 10px !important;
            }

            /* Benefits section */
            .benefits-grid {
                gap: 8px !important;
            }

            .benefit-item {
                padding: 8px 14px !important;
                font-size: 0.8rem !important;
            }

            /* Booking modal */
            .booking-modal-content {
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 20px 20px 0 0 !important;
                max-height: 92vh !important;
                position: fixed;
                bottom: 0;
                left: 0;
            }

            .booking-modal-header {
                padding: 20px !important;
            }

            .booking-modal-header h2 {
                font-size: 1.1rem !important;
            }

            .booking-steps-nav {
                padding: 16px 10px !important;
            }

            .step-label {
                font-size: 0.65rem !important;
            }

            .booking-body {
                padding: 16px !important;
            }

            .form-row {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }

            .card-row {
                grid-template-columns: 1fr !important;
            }

            .payment-methods {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 10px !important;
            }

            .action-buttons button {
                width: 100% !important;
                justify-content: center !important;
            }

            /* Details modal */
            .details-modal {
                padding: 0 !important;
            }

            .details-modal-content {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 20px 20px 0 0 !important;
                max-height: 90vh !important;
                position: fixed;
                bottom: 0;
                left: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
            }

            .premium-gallery-container {
                grid-template-columns: 1fr !important;
            }

            .hero-main-slot {
                height: 220px !important;
            }

            .sidebar-grid {
                height: 130px !important;
            }

            .modal-info-grid {
                grid-template-columns: 1fr !important;
            }

            .details-grid {
                grid-template-columns: 1fr !important;
            }

            .details-footer {
                flex-direction: column !important;
                gap: 12px !important;
                align-items: stretch !important;
            }

            .details-book-btn {
                width: 100% !important;
                justify-content: center !important;
            }

            /* Back button */
            .back-button-container {
                padding: 24px 15px !important;
            }

            .exp-sticky-footer {
                padding: 12px 16px !important;
                flex-direction: column !important;
                gap: 10px !important;
                align-items: stretch !important;
                position: sticky !important;
                bottom: 0 !important;
                z-index: 100 !important;
                background: white !important;
            }

            .exp-sticky-footer>div:first-child {
                display: none !important;
            }

            .exp-sticky-footer>div:last-child {
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                width: 100% !important;
            }

            .exp-sticky-footer button {
                width: auto !important;
                justify-content: center !important;
            }

            /* Details modal mobile spacing fixes */
            #modalBodyContent {
                min-height: unset !important;
            }

            #serviceDetailsContent [style*="height:350px"] {
                height: 180px !important;
            }

            #serviceDetailsContent [style*="padding:40px"] {
                padding: 16px !important;
            }

            #serviceDetailsContent [style*="padding:0 40px"] {
                padding: 0 12px !important;
            }

            #serviceDetailsContent [style*="grid-template-columns: 2fr 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
            }

            #serviceDetailsContent [style*="grid-template-columns:1fr 1fr"],
            #serviceDetailsContent [style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }

            #serviceDetailsContent [style*="font-size:2.5rem"] {
                font-size: 1.3rem !important;
            }
        }

        @media (max-width: 480px) {
            .experience-hero h1 {
                font-size: 1.8rem !important;
            }

            .hero-description {
                font-size: 0.82rem !important;
            }

            .experience-hero {
                min-height: 230px !important;
            }

            .service-image-container {
                height: 180px !important;
            }

            .price-amount {
                font-size: 1.3rem !important;
            }
        }

        /* =============================================
           ENTRANCE ANIMATIONS
        ============================================= */

        /* ============================================
           PREMIUM EXPERIENCE LOADER CSS
        ============================================ */
        #page-loader {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #10b981, #059669, #064e3b);
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

        #page-loader.hide {
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
        .bg-exp { top: -20%; left: -10%; width: 140%; height: 140%; border-radius: 50%; opacity: 0.05; filter: blur(60px); background: radial-gradient(white, transparent); }

        /* Floating particles */
        .loader-particle {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.3;
            animation: floatExpParticle 8s linear infinite;
        }
        .lp-e1 { top: 15%; left: 20%; width: 8px; height: 8px; animation-duration: 9s; }
        .lp-e2 { top: 70%; left: 15%; width: 15px; height: 15px; animation-duration: 11s; animation-delay: 2s; }
        .lp-e3 { top: 25%; right: 25%; width: 10px; height: 10px; animation-duration: 10s; animation-delay: 1s; }
        .lp-e4 { top: 60%; right: 15%; width: 12px; height: 12px; animation-duration: 14s; }

        @keyframes floatExpParticle {
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
            animation: slideDownExp 1s ease-out forwards;
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

        /* Animated Experience Icon */
        .exp-animation-container {
            position: relative;
            margin-bottom: 25px;
            animation: bobExp 4s ease-in-out infinite;
        }
        
        .exp-icon-3d {
            font-size: 2.5rem;
            color: white;
            text-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1));
            backdrop-filter: blur(10px);
            padding: 15px 18px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 15px 35px rgba(5, 150, 105, 0.4);
        }
        
        @keyframes bobExp {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); box-shadow: 0 25px 50px rgba(5, 150, 105, 0.6); }
        }

        /* Text Area */
        .loader-text-area {
            text-align: center;
            margin-bottom: 30px;
            animation: slideUpExp 1s ease-out forwards;
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
            animation: slideUpExp 1.2s ease-out forwards;
        }

        .loader-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #6ee7b7 0%, #d1fae5 100%);
            border-radius: 35px;
            width: 0%;
            position: relative;
            transition: width 0.1s linear;
            box-shadow: 0 0 20px rgba(110, 231, 183, 0.6);
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
            animation: shimmerExp 1.5s infinite;
        }

        @keyframes shimmerExp {
            100% { left: 200%; }
        }

        .loader-percent {
            position: absolute;
            right: 30px;
            font-weight: 800;
            font-size: 1.3rem;
            color: #047857;
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
            animation: slideUpExp 1.2s ease-out forwards;
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
            animation: fadeInExp 2s ease-out forwards;
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

        @keyframes slideDownExp {
            from { transform: translateY(-40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideUpExp {
            from { transform: translateY(40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes fadeInExp {
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
            .loader-progress-container, .search-mockup-pill { max-width: 90%; }
        }

        /* --- Hero entrance --- */
        .experience-hero-container {
            opacity: 0;
            transform: translateY(-28px);
            transition: opacity 0.7s ease 0.15s, transform 0.7s ease 0.15s;
        }
        .experience-hero-container.anim-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Hero badge */
        .experience-hero-badge {
            opacity: 0;
            transform: scale(0.6) rotate(-15deg);
            transition: opacity 0.55s ease 0.45s, transform 0.55s cubic-bezier(0.34,1.56,0.64,1) 0.45s;
        }
        .anim-in .experience-hero-badge {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }

        /* Hero h1 */
        .experience-hero h1 {
            opacity: 0;
            transform: translateX(-30px);
            transition: opacity 0.6s ease 0.55s, transform 0.6s ease 0.55s;
        }
        .anim-in .experience-hero h1 {
            opacity: 1;
            transform: translateX(0);
        }

        /* Hero divider */
        .hero-divider {
            opacity: 0;
            transform: scaleX(0);
            transform-origin: left;
            transition: opacity 0.5s ease 0.75s, transform 0.5s ease 0.75s;
        }
        .anim-in .hero-divider {
            opacity: 1;
            transform: scaleX(1);
        }

        /* Hero description */
        .hero-description {
            opacity: 0;
            transform: translateY(14px);
            transition: opacity 0.6s ease 0.9s, transform 0.6s ease 0.9s;
        }
        .anim-in .hero-description {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- Service cards stagger --- */
        .service-card {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.55s ease, transform 0.55s cubic-bezier(0.22,1,0.36,1),
                        box-shadow 0.3s ease; /* keep hover shadow transition */
        }
        .service-card.card-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Section title / service-content fade */
        .service-content {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease 0.2s, transform 0.6s ease 0.2s;
        }
        .service-content.anim-in {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body>
    <!-- ===== PREMIUM EXPERIENCE LOADER ===== -->
    <div id="page-loader">
        <div class="loader-bg-element bg-exp"></div>
        <div class="loader-particle lp-e1"></div>
        <div class="loader-particle lp-e2"></div>
        <div class="loader-particle lp-e3"></div>
        <div class="loader-particle lp-e4"></div>

        <div class="loader-logo-area">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo">
            <style>
                .loader-dynamic-logo { height: 100px !important; width: auto !important; margin-left: 10px; }
                @media (max-width: 768px) { .loader-dynamic-logo { height: 60px !important; } }
                @media (max-width: 480px) { .loader-dynamic-logo { height: 45px !important; margin-left: 5px; } }
            </style>
            <img src="../images/Localista (1).png" alt="Localista" class="loader-dynamic-logo">
        </div>

        <div class="exp-animation-container">
            <div class="exp-icon-3d"><i class="fas fa-hiking"></i></div>
        </div>

        <div class="loader-text-area">
            <h1 id="loaderTitle">Curating Your Adventure...</h1>
            <p id="loaderSubtext">We're finding the best local experiences for you</p>
        </div>

        <div class="loader-progress-container">
            <div class="loader-progress-fill" id="loaderBarFill"></div>
            <div class="loader-percent" id="loaderPercent">0%</div>
        </div>

        <div class="search-mockup-pill">
            <div class="mockup-route">
                <div style="background: rgba(255,255,255,0.2); width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; margin-right:5px;"><i class="fas fa-map"></i></div>
                <span id="dynamicRouteText">Exploring Catalog</span>
            </div>
            <div class="mockup-status">
                <span id="mockupStatusText">Finding activities...</span>
                <i class="fas fa-mountain" id="mockupStatusIcon"></i>
            </div>
        </div>

        <div class="loader-steps">
            <div class="loader-step active stage-e1" id="lStep1">
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <span>Discover</span>
            </div>
            <div class="loader-step stage-e2" id="lStep2">
                <div class="step-icon"><i class="fas fa-star"></i></div>
                <span>Curate</span>
            </div>
            <div class="loader-step stage-e3" id="lStep3">
                <div class="step-icon"><i class="fas fa-calendar-check"></i></div>
                <span>Verify</span>
            </div>
            <div class="loader-step stage-e4" id="lStep4">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <span>Ready</span>
            </div>
        </div>

        <div class="loader-footer">
            <i class="fas fa-camera"></i> <span id="loaderFooterText">Get ready for unforgettable moments and curated vibes.</span>
        </div>
    </div>
    <header class="navbar" id="navbar">
        <div class="nav-left"><img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='../index.php'">
            <div class="company-name"><span class="line1">HeyDream Travel</span><span class="line2">and Tours</span>
            </div>
        </div>
    </header>


    <!-- Premium Hero Section -->
    <div class="experience-hero-container">
        <div class="experience-hero">
            <div class="hero-content">
                <div class="hero-title-area">
                    <div class="experience-hero-badge">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h1>Experience</h1>
                </div>
                <div class="hero-divider"></div>
                <p class="hero-description">
                    Discover unforgettable activities and curated local adventures tailored just for you. Find your next
                    dream experience today.
                </p>
            </div>
        </div>
    </div>

    <!-- Main Service Section -->
    <section class="service-content">
        <div class="service-grid">
            <?php if (!empty($db_experiences)): ?>
                <?php foreach ($db_experiences as $service): ?>
                    <div class="service-card" onclick="viewServiceDetails(<?= $service['id'] ?>)">
                        <div class="card-main-layout">
                            <div class="service-image-container">
                                <div class="card-badge">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= htmlspecialchars($service['badge_text'] ?: 'Experience') ?>
                                </div>
                                <?php if ($service['featured_image']): ?>
                                    <img src="../<?= htmlspecialchars($service['featured_image']) ?>"
                                        alt="<?= htmlspecialchars($service['title']) ?>">
                                <?php else: ?>
                                    <div
                                        style="width:100%; height:100%; background:#f1f5f9; display:flex; align-items:center; justify-content:center;">
                                        <i class="fas fa-star" style="font-size:3rem; color:#cbd5e1;"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Premium Info Overlay -->
                                <div class="image-overlay-info">
                                    <div class="overlay-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">Top Destination</span>
                                            <span class="overlay-sub">Curated Location</span>
                                        </div>
                                    </div>
                                    <div class="overlay-item">
                                        <i class="fas fa-check-circle"></i>
                                        <div class="overlay-text">
                                            <span class="overlay-label">Verified</span>
                                            <span class="overlay-sub">Secure Booking</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="service-card-body">
                                <h3><?= htmlspecialchars($service['title']) ?></h3>
                                <div class="title-underline"></div>
                                <p class="service-short-desc">
                                    <?= htmlspecialchars($service['description'] ?? 'Experience the beauty of this amazing activity with our curated premium tour package.') ?>
                                </p>

                                <!-- Info Dashboard -->
                                <div class="info-dashboard-row">
                                    <div class="dash-item">
                                        <div class="dash-icon"><i class="fas fa-clock"></i></div>
                                        <div class="dash-content">
                                            <span class="dash-label">Duration</span>
                                            <span
                                                class="dash-value"><?= htmlspecialchars($service['duration'] ?? 'Day Tour') ?></span>
                                        </div>
                                    </div>
                                    <div class="dash-item">
                                        <div class="dash-icon"><i class="fas fa-users"></i></div>
                                        <div class="dash-content">
                                            <span class="dash-label">Status</span>
                                            <span
                                                class="dash-value"><?= htmlspecialchars($service['status_text'] ?: 'Available') ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="price-section">
                                    <div class="price-info">
                                        <div class="price-per">Starting from</div>
                                        <div class="price-display">
                                            <span class="price-currency"><?= htmlspecialchars($service['currency']) ?></span>
                                            <span class="price-amount"><?= number_format($service['price']) ?></span>
                                        </div>
                                    </div>
                                    <button class="view-details-btn">
                                        View Details <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Premium Footer Benefits -->
                            <div class="card-footer-benefits">
                                <div class="benefit-col">
                                    <div class="benefit-icon"><i class="fas fa-award"></i></div>
                                    <div class="benefit-info">
                                        <span class="benefit-title">Best Price</span>
                                        <span class="benefit-desc">Guaranteed</span>
                                    </div>
                                </div>
                                <div class="benefit-col">
                                    <div class="benefit-icon"><i class="fas fa-headset"></i></div>
                                    <div class="benefit-info">
                                        <span class="benefit-title">24/7 Support</span>
                                        <span class="benefit-desc">Live assistance</span>
                                    </div>
                                </div>
                                <div class="benefit-col">
                                    <div class="benefit-icon"><i class="fas fa-shield-alt"></i></div>
                                    <div class="benefit-info">
                                        <span class="benefit-title">Secure</span>
                                        <span class="benefit-desc">Safe booking</span>
                                    </div>
                                </div>
                                <div class="benefit-col">
                                    <div class="benefit-icon"><i class="fas fa-undo"></i></div>
                                    <div class="benefit-info">
                                        <span class="benefit-title">Flexible</span>
                                        <span class="benefit-desc">Easy cancel</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div
                    style="grid-column: 1 / -1; text-align: center; padding: 100px 20px; background: white; border-radius: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                    <i class="fas fa-calendar-times"
                        style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px; display: block;"></i>
                    <h3 style="color: #475569; font-size: 1.5rem; font-weight: 700;">No Experiences Found</h3>
                    <p style="color: #94a3b8; max-width: 400px; margin: 10px auto;">We're currently updating our activity
                        catalog. Please check back later for exciting new adventures!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

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

    <div id="serviceDetailsModal" class="details-modal">
        <div id="serviceDetailsContent" class="details-modal-content"></div>
    </div>

    <script>
        // Inject user info for booking forms
        window.currentUserEmail = '<?= isset($_SESSION['user_id']) ? $auth->getCurrentUser()['email'] : '' ?>';
        window.currentFullName = '<?= isset($_SESSION['user_id']) ? htmlspecialchars($auth->getCurrentUser()['full_name']) : '' ?>';

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

        function viewServiceDetails(id) {
            fetch(`../api/get-service-details.php?id=${id}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        renderServiceDetails(result.data);
                        document.getElementById('serviceDetailsModal').classList.add('active');
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to fetch details.');
                });
        }

        function getHighlightIcon(text) {
            text = text.toLowerCase();
            if (text.includes('food') || text.includes('culinary') || text.includes('meal')) return 'fa-utensils';
            if (text.includes('nature') || text.includes('hiking') || text.includes('mountain')) return 'fa-mountain';
            if (text.includes('water') || text.includes('diving') || text.includes('beach')) return 'fa-water';
            if (text.includes('photo') || text.includes('camera')) return 'fa-camera-retro';
            if (text.includes('guide') || text.includes('local')) return 'fa-user-friends';
            if (text.includes('transfer') || text.includes('hotel')) return 'fa-bus';
            return 'fa-star';
        }

        function renderServiceDetails(data) {
            const content = document.getElementById('serviceDetailsContent');
            let gallery = [];
            try { gallery = data.image_gallery ? JSON.parse(data.image_gallery) : []; } catch (e) { gallery = []; }

            const featured_img = data.featured_image ? '../' + data.featured_image : '../images/placeholder-experience.jpg';
            const inclusions = data.inclusions ? data.inclusions.split('\n').filter(t => t.trim()) : [];
            const exclusions = data.exclusions ? data.exclusions.split('\n').filter(t => t.trim()) : [];
            const highlights = data.highlights ? data.highlights.split('\n').filter(t => t.trim()) : [];
            const itinerary = data.itinerary || [];
            const price = Number(data.price).toLocaleString();

            content.innerHTML = `
                <!-- Modal Header -->
                <div style="position:relative; height:350px; overflow:hidden; border-radius:20px 20px 0 0; flex-shrink:0;">
                    <img src="${featured_img}" style="width:100%; height:100%; object-fit:cover;">
                    <div style="position:absolute; bottom:0; left:0; right:0; padding:40px; background:linear-gradient(transparent, rgba(0,0,0,0.8)); color:white;">
                        <h2 style="font-size:2.5rem; margin-bottom:10px; color:white;">${data.title}</h2>
                        <div style="display:flex; gap:20px; font-size:0.9rem; opacity:0.9;">
                            <span><i class="fas fa-clock"></i> ${data.duration}</span>
                            <span><i class="fas fa-map-marker-alt"></i> Multiple Locations</span>
                        </div>
                    </div>
                    <button onclick="closeDetailsModal()" style="position:absolute; top:20px; right:20px; background:rgba(0,0,0,0.5); color:white; border:none; width:40px; height:40px; border-radius:50%; cursor:pointer; z-index:10;"><i class="fas fa-times"></i></button>
                </div>

                <!-- Modal Tabs -->
                <div class="details-modal-tabs">
                    <div class="modal-tab active" onclick="switchTab('overview')" data-tab="overview" style="padding:20px 0; margin-right:30px; font-weight:700; color:#003580; cursor:pointer; border-bottom:3px solid #14c492; flex-shrink:0;">Overview</div>
                    <div class="modal-tab" onclick="switchTab('itinerary')" data-tab="itinerary" style="padding:20px 0; margin-right:30px; font-weight:700; color:#64748b; cursor:pointer; flex-shrink:0;">Itinerary</div>
                    <div class="modal-tab" onclick="switchTab('gallery')" data-tab="gallery" style="padding:20px 0; margin-right:30px; font-weight:700; color:#64748b; cursor:pointer; flex-shrink:0;">Gallery</div>
                    <div class="modal-tab" onclick="switchTab('policies')" data-tab="policies" style="padding:20px 0; font-weight:700; color:#64748b; cursor:pointer; flex-shrink:0;">Policies</div>
                </div>

                <!-- Modal Body -->
                <div id="modalBodyContent" class="exp-modal-scrollable" style="padding:40px; background:white; min-height:400px;">
                    <!-- Overview Tab -->
                    <div id="tab-overview" class="tab-pane active" style="display:grid; grid-template-columns: 2fr 1fr; gap:40px;">
                        <div>
                            <h4 style="color:#003580; margin-bottom:15px; font-size:1.2rem;">Service Description</h4>
                            <p style="color:#64748b; line-height:1.7; margin-bottom:30px;">${data.full_description || data.description || 'Experience the beauty of this amazing activity with our curated premium tour package.'}</p>
                            
                            ${highlights.length > 0 ? `
                                <h4 style="color:#003580; margin-bottom:15px; font-size:1.2rem;">Experience Highlights</h4>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:30px;">
                                    ${highlights.map(h => `<div style="color:#64748b; font-size:0.9rem; display:flex; align-items:center; gap:10px;"><i class="fas ${getHighlightIcon(h)}" style="color:#14c492; width:20px;"></i> ${h}</div>`).join('')}
                                </div>
                            ` : ''}

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">
                                <div>
                                    <h4 style="color:#003580; margin-bottom:15px; font-size:1.1rem;">What's Included</h4>
                                    <div style="display:flex; flex-direction:column; gap:10px;">
                                        ${inclusions.map(h => `<div style="color:#64748b; font-size:0.9rem;"><i class="fas fa-check" style="color:#14c492; margin-right:8px;"></i> ${h}</div>`).join('')}
                                        ${inclusions.length === 0 ? '<p style="color:#64748b; font-size:0.85rem;">Standard inclusions apply.</p>' : ''}
                                    </div>
                                </div>
                                <div>
                                    <h4 style="color:#003580; margin-bottom:15px; font-size:1.1rem;">What's Excluded</h4>
                                    <div style="display:flex; flex-direction:column; gap:10px;">
                                        ${exclusions.map(h => `<div style="color:#64748b; font-size:0.9rem;"><i class="fas fa-times" style="color:#ef4444; margin-right:8px;"></i> ${h}</div>`).join('')}
                                        ${exclusions.length === 0 ? '<p style="color:#64748b; font-size:0.85rem;">Personal expenses & tips.</p>' : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background:#f8fafc; border-radius:20px; padding:30px; border:1px solid #e2e8f0; height:fit-content;">
                            <h4 style="color:#003580; margin-bottom:20px; font-size:1.1rem;">Quick Info</h4>
                            <div style="display:flex; flex-direction:column; gap:15px;">
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;"><span style="color:#64748b;">Code:</span><span style="font-weight:700; color:#1e293b;">${data.service_code || 'E-' + data.id.toString().padStart(4, '0')}</span></div>
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;"><span style="color:#64748b;">Duration:</span><span style="font-weight:700; color:#1e293b;">${data.duration || 'Day Tour'}</span></div>
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;"><span style="color:#64748b;">Confirmation:</span><span style="font-weight:700; color:#14c492;">Instant</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Itinerary Tab -->
                    <div id="tab-itinerary" class="tab-pane" style="display:none;">
                        <h4 style="color:#003580; margin-bottom:20px; font-size:1.2rem;">Experience Timeline</h4>
                        <div class="itinerary-list">
                            ${itinerary.length > 0 ? itinerary.map(item => `
                                <div style="margin-bottom:20px; padding-left:20px; border-left:3px solid #14c492; position:relative;">
                                    <div style="position:absolute; left:-7px; top:0; width:11px; height:11px; background:#14c492; border-radius:50%; border:2px solid white;"></div>
                                    <h5 style="color:#003580; margin-bottom:5px; font-weight:700;">Day ${item.day_number || ''}: ${item.title || 'Activity'}</h5>
                                    <p style="color:#64748b; font-size:0.9rem; line-height:1.6;">${item.description || item.content || ''}</p>
                                </div>
                            `).join('') : '<p style="color:#64748b;">Itinerary details will be provided upon booking.</p>'}
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
                        <h4 style="color:#003580; margin-bottom:20px; font-size:1.2rem;">Booking & Travel Policies</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div style="padding:20px; background:#f8fafc; border-radius:15px; border-left:5px solid #003580;">
                                <h5 style="color:#003580; margin-bottom:8px;"><i class="fas fa-undo"></i> Cancellation Policy</h5>
                                <p style="color:#64748b; font-size:0.85rem; margin:0; line-height:1.6;">${data.cancellation_policy || 'Standard cancellation policies apply. Please contact support for specific details regarding this experience.'}</p>
                            </div>
                            <div style="padding:20px; background:#f0fdf4; border-radius:15px; border-left:5px solid #14c492;">
                                <h5 style="color:#14c492; margin-bottom:8px;"><i class="fas fa-file-alt"></i> Requirements</h5>
                                <p style="color:#64748b; font-size:0.85rem; margin:0; line-height:1.6;">${data.required_documents || 'Please bring a valid ID and your booking confirmation.'}</p>
                            </div>
                            <div style="padding:20px; background:#fff7ed; border-radius:15px; border-left:5px solid #f97316;">
                                <h5 style="color:#f97316; margin-bottom:8px;"><i class="fas fa-shield-alt"></i> Travel Requirements</h5>
                                <p style="color:#64748b; font-size:0.85rem; margin:0; line-height:1.6;">${data.travel_requirements || 'Please ensure you comply with local health and safety protocols.'}</p>
                            </div>
                            <div style="padding:20px; background:#f5f3ff; border-radius:15px; border-left:5px solid #8b5cf6;">
                                <h5 style="color:#8b5cf6; margin-bottom:8px;"><i class="fas fa-gavel"></i> Terms & Conditions</h5>
                                <p style="color:#64748b; font-size:0.85rem; margin:0; line-height:1.6;">${data.terms_conditions || 'By booking this experience, you agree to our standard terms and conditions.'}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="exp-sticky-footer">
                    <div style="display:flex; gap:30px;">
                        <div style="display:flex; align-items:center; gap:10px; color:#003580; font-weight:700;">
                            <i class="fas fa-award" style="color:#14c492;"></i> Best Price
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; color:#003580; font-weight:700;">
                            <i class="fas fa-headset" style="color:#14c492;"></i> 24/7 Support
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:20px;">
                        <div style="text-align:right;">
                            <span style="display:block; font-size:0.8rem; color:#64748b;">Starting from</span>
                            <span style="font-size:1.8rem; font-weight:900; color:#14c492;">${data.currency || '₱'}${price}</span>
                        </div>
                        <button class="details-book-btn" onclick="closeDetailsModal(); requireLogin('showExperienceBooking', '${escapeJs(data.title)}', ${data.price}, '${escapeJs(data.duration)}')" style="display:flex; align-items:center; gap:10px;">
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
                activeTab.style.borderBottom = '3px solid #14c492';
            }

            // Update Content Visibility
            document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
            const targetPane = document.getElementById(`tab-${tabId}`);
            if (targetPane) {
                if (tabId === 'overview') targetPane.style.display = 'grid';
                else targetPane.style.display = 'block';
            }
        }

        function escapeJs(str) {
            return str ? str.replace(/'/g, "\\'") : '';
        }

        function closeDetailsModal() {
            document.getElementById('serviceDetailsModal').classList.remove('active');
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
                <button class="btn-proceed" id="finalPaymentBtn" style="opacity:0.5; pointer-events:none;" onclick="renderExpStep4()">Complete Payment <i class="fas fa-check-circle"></i></button>
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
            if (event.target == document.getElementById('serviceDetailsModal')) closeDetailsModal();
            if (event.target == document.getElementById('expBookingModal')) closeExpBookingModal();
        }
    </script>
    <script src="../js/auth-menu.js"></script>
    <script src="../js/main.js"></script>

    <!-- ===== ENTRANCE ANIMATION SCRIPT ===== -->
    <script>
        (function () {
            window.addEventListener('load', function () {
                const loader = document.getElementById('page-loader');
                var hero   = document.querySelector('.experience-hero-container');
                var section = document.querySelector('.service-content');
                var cards  = document.querySelectorAll('.service-card');
                
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
                
                // Get general search term from possible variables. Experiences page doesn't have a built-in search yet, but this handles future implementation.
                const urlParams = new URLSearchParams(window.location.search);
                const query = urlParams.get('search') || urlParams.get('category') || urlParams.get('q');
                const hasRoute = query && query.trim().length > 0;
                
                const dynamicRouteEl = document.getElementById('dynamicRouteText');
                const footerTextEl = document.getElementById('loaderFooterText');
                
                const loadingMessages = [
                    { p: 0, text: 'Finding activities...', icon: 'fa-mountain', title: 'Curating Your Adventure...', destText: (hasRoute ? `Events in ${query}` : 'Exploring Catalog'), footer: "Get ready for unforgettable moments and curated vibes." },
                    { p: 25, text: 'Selecting best spots...', icon: 'fa-camera-retro', title: 'Connecting Local Guides...', destText: (hasRoute ? `Top picks for ${query}` : 'Local Experiences'), footer: "Ensuring you get the most authentic moments." },
                    { p: 55, text: 'Checking availability...', icon: 'fa-calendar-check', title: 'Securing Time Slots...', destText: (hasRoute ? `Slots for ${query}` : 'Availability Check'), footer: "Confirming bookings for your adventure." },
                    { p: 80, text: 'Ready to explore', icon: 'fa-check-circle', title: 'Finalizing Experience Page...', destText: (hasRoute ? `Ready ${query}` : 'Catalog Ready'), footer: "Your adventure awaits." }
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
                            loader.classList.add('hide');
                            
                            // Trigger hero animation just as loader hides
                            if (hero) hero.classList.add('anim-in');
                            if (section) section.classList.add('anim-in');
                            
                            setTimeout(() => {
                                loader.remove();
                            }, 800);
                        }, 500);
                    }
                }, intervalTime);
            });

            // Stagger cards with IntersectionObserver
            var cards  = document.querySelectorAll('.service-card');
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var card = entry.target;
                        var idx  = parseInt(card.dataset.cardIdx || 0);
                        setTimeout(function () {
                            card.classList.add('card-visible');
                        }, 200 + idx * 110);
                        observer.unobserve(card);
                    }
                });
            }, { threshold: 0.1 });

            cards.forEach(function (card, i) {
                card.dataset.cardIdx = i;
                observer.observe(card);
            });
        })();
    </script>
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
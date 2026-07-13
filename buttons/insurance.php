<?php
// ========================================
// FILE: buttons/insurance.php
// DESCRIPTION: Travel Insurance with Payment System
// ========================================
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = new Auth($pdo);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Travel Insurance</title>
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

        .service-hero {
            background: linear-gradient(180deg, #FF7F50 0%, #FF6B6B 40%, #ffffff 100%);
            padding: 100px 5% 60px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease 0.1s, transform 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
        }

        .service-hero.animate-in {
            opacity: 1;
            transform: translateY(0);
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
            font-size: 2.8rem;
            margin-bottom: 12px;
            font-weight: 800;
            color: #ffffff !important;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .service-hero h1 i {
            margin-right: 15px;
            animation: pulse 2s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
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
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(255, 127, 80, 0.15);
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
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #FF7F50, #FF6B6B);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .service-icon i {
            font-size: 1.8rem;
            color: white;
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

        .coverage-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .coverage-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            font-size: 0.75rem;
        }

        .coverage-item i {
            color: #FF7F50;
            width: 24px;
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

        /* Booking Modal Styles (same as cruise page) */
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
            background: linear-gradient(135deg, #FF7F50, #FF6B6B);
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
            background: linear-gradient(135deg, #FF7F50, #FF6B6B);
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
            background: linear-gradient(135deg, #28a745, #20c997);
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
            .service-hero {
                padding: 80px 5% 50px;
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

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .coverage-list {
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
        }

        @media (max-width: 550px) {
            .service-grid {
                gap: 12px;
            }

            .service-card {
                padding: 15px 12px;
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

    <!-- ===== PREMIUM INSURANCE LOADER STYLES ===== -->
    <style>
        /* ============================================
           PREMIUM INSURANCE ENTRANCE LOADER
        ============================================ */
        #page-preloader {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #ff7f50, #f87171, #991b1b);
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
        .bg-ins { top: 0%; left: -20%; width: 140%; height: 140%; border-radius: 50%; opacity: 0.05; filter: blur(60px); background: radial-gradient(white, transparent); }

        /* Floating particles */
        .loader-particle {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.3;
            animation: floatInsParticle 8s linear infinite;
        }
        .lp-i1 { top: 25%; left: 10%; width: 6px; height: 6px; animation-duration: 9s; }
        .lp-i2 { top: 75%; left: 15%; width: 14px; height: 14px; animation-duration: 11s; animation-delay: 2s; }
        .lp-i3 { top: 30%; right: 20%; width: 8px; height: 8px; animation-duration: 10s; animation-delay: 1s; }
        .lp-i4 { top: 65%; right: 10%; width: 10px; height: 10px; animation-duration: 14s; }

        @keyframes floatInsParticle {
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
            animation: slideDownIns 1s ease-out forwards;
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

        /* Animated Insurance Icon */
        .ins-animation-container {
            position: relative;
            margin-bottom: 25px;
            animation: bobIns 4s ease-in-out infinite;
        }
        
        .ins-icon-3d {
            font-size: 2.5rem;
            color: white;
            text-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1));
            backdrop-filter: blur(10px);
            padding: 15px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 15px 35px rgba(153, 27, 27, 0.4);
        }
        
        @keyframes bobIns {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); box-shadow: 0 25px 50px rgba(153, 27, 27, 0.6); }
        }

        /* Text Area */
        .loader-text-area {
            text-align: center;
            margin-bottom: 30px;
            animation: slideUpIns 1s ease-out forwards;
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
            animation: slideUpIns 1.2s ease-out forwards;
        }

        .loader-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #fdba74 0%, #ffedd5 100%);
            border-radius: 35px;
            width: 0%;
            position: relative;
            transition: width 0.1s linear;
            box-shadow: 0 0 20px rgba(253, 186, 116, 0.6);
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
            animation: shimmerIns 1.5s infinite;
        }

        @keyframes shimmerIns {
            100% { left: 200%; }
        }

        .loader-percent {
            position: absolute;
            right: 30px;
            font-weight: 800;
            font-size: 1.3rem;
            color: #991b1b;
            text-shadow: 0 1px 2px rgba(255,255,255,0.9);
            z-index: 10;
        }

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
            animation: slideUpIns 1.2s ease-out forwards;
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
            animation: fadeInIns 2s ease-out forwards;
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

        @keyframes slideDownIns {
            from { transform: translateY(-40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideUpIns {
            from { transform: translateY(40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes fadeInIns {
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
                transform: translateY(0);
            }

            50% {
                transform: translateY(-18px);
            }
        }

        /* ---- Navbar ---- */
        .navbar {
            animation: navSlideDown 0.7s cubic-bezier(0.22, 1, 0.36, 1) 1.3s both;
        }

        @keyframes navSlideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ---- Hero section ---- */
        .service-hero {
            animation: heroReveal 1s cubic-bezier(0.22, 1, 0.36, 1) 1.5s both;
        }

        @keyframes heroReveal {
            from {
                opacity: 0;
                transform: translateY(35px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* hero h1 and p stagger */
        .service-hero h1 {
            opacity: 0;
            animation: slideUpFade 0.6s ease 1.9s forwards;
        }

        .service-hero p {
            opacity: 0;
            animation: slideUpFade 0.6s ease 2.1s forwards;
        }

        /* ---- Service cards (scroll-triggered) ---- */
        .service-card {
            opacity: 0;
            transform: translateY(45px);
            transition: opacity 0.65s ease, transform 0.65s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease;
            /* preserve hover transition */
        }

        .service-card.card-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ---- Info section (scroll-triggered) ---- */
        .info-section {
            opacity: 0;
            transform: translateY(35px);
            transition: opacity 0.7s ease 0.15s, transform 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.15s;
        }

        .info-section.section-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* coverage items stagger once parent visible */
        .coverage-item {
            opacity: 0;
            transform: translateX(-15px);
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        .info-section.section-visible .coverage-item:nth-child(1) {
            opacity: 1;
            transform: none;
            transition-delay: 0.10s;
        }

        .info-section.section-visible .coverage-item:nth-child(2) {
            opacity: 1;
            transform: none;
            transition-delay: 0.18s;
        }

        .info-section.section-visible .coverage-item:nth-child(3) {
            opacity: 1;
            transform: none;
            transition-delay: 0.26s;
        }

        .info-section.section-visible .coverage-item:nth-child(4) {
            opacity: 1;
            transform: none;
            transition-delay: 0.34s;
        }

        .info-section.section-visible .coverage-item:nth-child(5) {
            opacity: 1;
            transform: none;
            transition-delay: 0.42s;
        }

        .info-section.section-visible .coverage-item:nth-child(6) {
            opacity: 1;
            transform: none;
            transition-delay: 0.50s;
        }

        /* ---- Back button ---- */
        .back-button-container {
            opacity: 0;
            animation: slideUpFade 0.6s ease 2.3s forwards;
        }
    </style>
    <!-- ===== END ENTRANCE ANIMATION STYLES ===== -->
</head>

<body>

    <!-- ===== PREMIUM INSURANCE LOADER ===== -->
    <div id="page-preloader">
        <div class="loader-bg-element bg-ins"></div>
        <div class="loader-particle lp-i1"></div>
        <div class="loader-particle lp-i2"></div>
        <div class="loader-particle lp-i3"></div>
        <div class="loader-particle lp-i4"></div>

        <div class="loader-logo-area">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo">
            <style>
                .loader-dynamic-logo { height: 100px !important; width: auto !important; margin-left: 10px; }
                @media (max-width: 768px) { .loader-dynamic-logo { height: 60px !important; } }
                @media (max-width: 480px) { .loader-dynamic-logo { height: 45px !important; margin-left: 5px; } }
            </style>
            <img src="../images/Localista (1).png" alt="Localista" class="loader-dynamic-logo">
        </div>

        <div class="ins-animation-container">
            <div class="ins-icon-3d"><i class="fas fa-shield-alt"></i></div>
        </div>

        <div class="loader-text-area">
            <h1 id="loaderTitle">Securing Your Peace of Mind...</h1>
            <p id="loaderSubtext">We're tailoring the perfect coverage for you</p>
        </div>

        <div class="loader-progress-container">
            <div class="loader-progress-fill" id="loaderBarFill"></div>
            <div class="loader-percent" id="loaderPercent">0%</div>
        </div>

        <div class="search-mockup-pill">
            <div class="mockup-route">
                <div style="background: rgba(255,255,255,0.2); width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; margin-right:5px;"><i class="fas fa-heartbeat"></i></div>
                <span id="dynamicRouteText">Premium Protection</span>
            </div>
            <div class="mockup-status">
                <span id="mockupStatusText">Scanning providers...</span>
                <i class="fas fa-shield-check" id="mockupStatusIcon"></i>
            </div>
        </div>

        <div class="loader-steps">
            <div class="loader-step active stage-i1" id="lStep1">
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <span>Discover</span>
            </div>
            <div class="loader-step stage-i2" id="lStep2">
                <div class="step-icon"><i class="fas fa-clipboard-list"></i></div>
                <span>Assess</span>
            </div>
            <div class="loader-step stage-i3" id="lStep3">
                <div class="step-icon"><i class="fas fa-user-shield"></i></div>
                <span>Protect</span>
            </div>
            <div class="loader-step stage-i4" id="lStep4">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <span>Ready</span>
            </div>
        </div>

        <div class="loader-footer">
            <i class="fas fa-hands-helping"></i> <span id="loaderFooterText">Travel confidently, knowing you are protected anywhere.</span>
        </div>
    </div>
    <!-- ===== END PRELOADER ===== -->
    <header class="navbar" id="navbar">
        <div class="nav-left"><img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='../index.php'">
            <div class="company-name"><span class="line1">HeyDream Travel</span><span class="line2">and Tours</span>
            </div>
        </div>
    </header>


    <section class="service-hero">
        <h1><i class="fas fa-shield-alt"></i> Travel Insurance</h1>
        <p>Protect your journey with comprehensive travel insurance. Peace of mind for every adventure.</p>
    </section>

    <div class="service-content">
        <div class="service-grid">
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-medkit"></i></div>
                <h3>Medical Coverage</h3>
                <p>Emergency medical expenses, hospitalization, and medical evacuation.</p>
                <div class="price-section">
                    <div class="price-tag">From ₱599</div><button class="book-btn"
                        onclick="requireLogin('showInsuranceBooking', 'Medical Coverage', 599, 'Single Trip')">Get Quote
                        <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-suitcase"></i></div>
                <h3>Trip Protection</h3>
                <p>Cancel for any reason, trip interruption, and baggage loss coverage.</p>
                <div class="price-section">
                    <div class="price-tag">From ₱899</div><button class="book-btn"
                        onclick="requireLogin('showInsuranceBooking', 'Trip Protection', 899, 'Annual Plan')">Get Quote
                        <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        <div class="info-section">
            <h3><i class="fas fa-check-circle"></i> What's Covered</h3>
            <div class="coverage-list">
                <div class="coverage-item"><i class="fas fa-ambulance"></i><span>Emergency Medical</span></div>
                <div class="coverage-item"><i class="fas fa-plane"></i><span>Trip Cancellation</span></div>
                <div class="coverage-item"><i class="fas fa-baggage"></i><span>Lost Baggage</span></div>
                <div class="coverage-item"><i class="fas fa-clock"></i><span>Travel Delay</span></div>
                <div class="coverage-item"><i class="fas fa-heartbeat"></i><span>COVID-19 Coverage</span></div>
                <div class="coverage-item"><i class="fas fa-umbrella-beach"></i><span>Adventure Sports</span></div>
            </div>
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
        let currentInsurance = null, insuranceBookingData = null, selectedPayment = null;

        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
        function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { const btn = event.target; const originalText = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#28a745'; btn.style.color = 'white'; setTimeout(() => { btn.textContent = originalText; btn.style.background = '#e0e0e0'; btn.style.color = ''; }, 1500); }); }
        function handleFileUpload(event, paymentMethod) { const file = event.target.files[0]; if (file) { if (!file.type.match('image.*')) { alert('Please upload an image file (PNG, JPG, JPEG)'); event.target.value = ''; return; } if (file.size > 5 * 1024 * 1024) { alert('File is too large. Maximum size is 5MB.'); event.target.value = ''; return; } const reader = new FileReader(); reader.onload = function (e) { const previewDiv = document.getElementById(`preview-${paymentMethod}`); if (previewDiv) { previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof">`; } }; reader.readAsDataURL(file); const fileNameSpan = document.getElementById(`file-name-${paymentMethod}`); if (fileNameSpan) { fileNameSpan.textContent = file.name; } } }

        function showInsuranceBooking(title, price, duration) {
            currentInsurance = { title, price, duration };
            let modal = document.getElementById('insuranceBookingModal');
            if (!modal) {
                modal = document.createElement('div'); modal.id = 'insuranceBookingModal'; modal.className = 'booking-modal';
                modal.innerHTML = `<div class="booking-modal-content"><div class="booking-modal-header"><span class="close-modal" onclick="closeInsuranceBookingModal()">&times;</span><h2><i class="fas fa-shield-alt"></i> Get Insurance</h2><p>Complete your application</p></div><div class="booking-modal-body"><div class="booking-steps"><div class="step active" id="step1Indicator"><div class="step-number">1</div><div class="step-label">Details</div><div class="step-line"></div></div><div class="step" id="step2Indicator"><div class="step-number">2</div><div class="step-label">Review</div><div class="step-line"></div></div><div class="step" id="step3Indicator"><div class="step-number">3</div><div class="step-label">Payment</div><div class="step-line"></div></div><div class="step" id="step4Indicator"><div class="step-number">4</div><div class="step-label">Confirm</div></div></div><div id="step1Content" class="step-content active"></div><div id="step2Content" class="step-content"></div><div id="step3Content" class="step-content"></div><div id="step4Content" class="step-content"></div></div></div>`;
                document.body.appendChild(modal);
                modal.addEventListener('click', function (e) { if (e.target === modal) closeInsuranceBookingModal(); });
            }
            renderInsuranceStep1();
            modal.classList.add('active');
        }

        function renderInsuranceStep1() {
            document.getElementById('step1Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-shield-alt"></i></div><div class="service-info"><h3>${currentInsurance.title}</h3><p class="service-price">₱${formatNumber(currentInsurance.price)}</p><p class="service-duration">${currentInsurance.duration}</p></div></div>
                <form id="insuranceForm" onsubmit="return false;">
                    <div class="form-section"><h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-group"><label>Email Address <span class="required">*</span></label><input type="email" id="applicationEmail" value="${window.currentUserEmail || ''}" placeholder="Your email address"></div>
                        <div class="form-group"><label>Full Name <span class="required">*</span></label><input type="text" id="fullName" placeholder="As per ID" value="${window.currentFullName || ''}"></div>
                        <div class="form-group"><label>Phone <span class="required">*</span></label><input type="tel" id="phone" placeholder="+63 912 345 6789"></div>
                        <div class="form-group"><label>Date of Birth <span class="required">*</span></label><input type="date" id="dob" max="${new Date().toISOString().split('T')[0]}"></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-calendar-alt"></i> Travel Details</h4>
                        <div class="form-row"><div class="form-group"><label>Start Date <span class="required">*</span></label><input type="date" id="startDate" min="${new Date().toISOString().split('T')[0]}"></div>
                        <div class="form-group"><label>End Date <span class="required">*</span></label><input type="date" id="endDate"></div></div>
                        <div class="form-group"><label>Destination <span class="required">*</span></label><input type="text" id="destination" placeholder="e.g., Japan, USA"></div>
                        <div class="form-group"><label>Travelers <span class="required">*</span></label><input type="number" id="travelers" min="1" value="1"></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-heartbeat"></i> Medical Information</h4>
                        <div class="form-group"><label>Pre-existing Conditions</label><textarea id="conditions" rows="2" placeholder="List any medical conditions"></textarea></div>
                        <div class="form-group"><label>Additional Coverage</label><select id="addCoverage"><option value="none">Standard</option><option value="adventure">Adventure (+₱500)</option><option value="full">Full Coverage (+₱2,000)</option></select></div>
                    </div>
                    <div id="step1Errors" class="error-message" style="display: none;"></div>
                    <div class="action-buttons"><button type="button" class="btn-next" onclick="validateAndGoToStep2()">Review Quote <i class="fas fa-arrow-right"></i></button></div>
                </form>`;
        }

        function validateAndGoToStep2() {
            const errors = [];
            const email = document.getElementById('applicationEmail')?.value.trim();
            const fullName = document.getElementById('fullName')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const dob = document.getElementById('dob')?.value;
            const startDate = document.getElementById('startDate')?.value;
            const endDate = document.getElementById('endDate')?.value;
            const destination = document.getElementById('destination')?.value.trim();
            const travelers = document.getElementById('travelers')?.value;
            if (!email) errors.push('Email address is required');
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Please enter a valid email address');
            if (!fullName) errors.push('Full Name is required');
            if (!phone) errors.push('Phone number is required');
            if (!dob) errors.push('Date of Birth is required');
            if (!startDate) errors.push('Start Date is required');
            if (!endDate) errors.push('End Date is required');
            if (!destination) errors.push('Destination is required');
            if (!travelers || travelers < 1) errors.push('At least 1 traveler is required');
            document.querySelectorAll('.form-group input, .form-group select').forEach(f => f.classList.remove('error'));
            if (!email) document.getElementById('applicationEmail')?.classList.add('error');
            if (!fullName) document.getElementById('fullName')?.classList.add('error');
            if (!phone) document.getElementById('phone')?.classList.add('error');
            if (!dob) document.getElementById('dob')?.classList.add('error');
            if (!startDate) document.getElementById('startDate')?.classList.add('error');
            if (!endDate) document.getElementById('endDate')?.classList.add('error');
            if (!destination) document.getElementById('destination')?.classList.add('error');
            if (!travelers || travelers < 1) document.getElementById('travelers')?.classList.add('error');
            if (errors.length > 0) { const errorDiv = document.getElementById('step1Errors'); errorDiv.style.display = 'flex'; errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`; errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
            goToInsuranceStep2();
        }

        function goToInsuranceStep2() {
            const fullName = document.getElementById('fullName')?.value;
            const email = document.getElementById('applicationEmail')?.value.trim() || window.currentUserEmail || '';
            const phone = document.getElementById('phone')?.value;
            const dob = document.getElementById('dob')?.value;
            const startDate = document.getElementById('startDate')?.value;
            const endDate = document.getElementById('endDate')?.value;
            const destination = document.getElementById('destination')?.value;
            const travelers = parseInt(document.getElementById('travelers')?.value) || 1;
            const conditions = document.getElementById('conditions')?.value;
            const addCoverage = document.getElementById('addCoverage')?.value;
            let addAmount = 0, coverageLabel = 'Standard';
            if (addCoverage === 'adventure') { addAmount = 500; coverageLabel = 'Adventure Sports'; }
            else if (addCoverage === 'full') { addAmount = 2000; coverageLabel = 'Full Coverage'; }
            const total = (currentInsurance.price + addAmount) * travelers;
            insuranceBookingData = { fullName, email, phone, dob, startDate, endDate, destination, travelers, conditions, coverageLabel, total };
            document.getElementById('step2Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-shield-alt"></i></div><div class="service-info"><h3>${currentInsurance.title}</h3><p class="service-price">₱${formatNumber(currentInsurance.price)}</p></div></div>
                <div class="review-details"><div class="review-section"><h4>Personal Info</h4><div class="review-row"><div class="review-label">Name:</div><div class="review-value">${escapeHtml(fullName)}</div></div><div class="review-row"><div class="review-label">Email:</div><div class="review-value">${escapeHtml(email)}</div></div><div class="review-row"><div class="review-label">Phone:</div><div class="review-value">${escapeHtml(phone)}</div></div><div class="review-row"><div class="review-label">DOB:</div><div class="review-value">${new Date(dob).toLocaleDateString()}</div></div></div>
                <div class="review-section"><h4>Travel Details</h4><div class="review-row"><div class="review-label">Period:</div><div class="review-value">${new Date(startDate).toLocaleDateString()} - ${new Date(endDate).toLocaleDateString()}</div></div><div class="review-row"><div class="review-label">Destination:</div><div class="review-value">${escapeHtml(destination)}</div></div><div class="review-row"><div class="review-label">Travelers:</div><div class="review-value">${travelers}</div></div><div class="review-row"><div class="review-label">Coverage:</div><div class="review-value">${coverageLabel}</div></div></div>
                <div class="review-section"><h4>Payment Summary</h4><div class="review-row"><div class="review-label">Base Premium:</div><div class="review-value">₱${formatNumber(currentInsurance.price)}</div></div>${addAmount > 0 ? `<div class="review-row"><div class="review-label">Add-on:</div><div class="review-value">+₱${formatNumber(addAmount)}</div></div>` : ''}<div class="review-row total"><div class="review-label">Total:</div><div class="review-value" style="color:#ff9800;">₱${formatNumber(total)}</div></div></div></div>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="goToInsuranceStep1()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-next" onclick="goToInsuranceStep3()">Proceed to Payment <i class="fas fa-credit-card"></i></button></div>`;
            updateInsuranceSteps(2);
        }

        function goToInsuranceStep1() { updateInsuranceSteps(1); setTimeout(() => { if (insuranceBookingData) { if (document.getElementById('fullName')) document.getElementById('fullName').value = insuranceBookingData.fullName || ''; if (document.getElementById('applicationEmail')) document.getElementById('applicationEmail').value = insuranceBookingData.email || ''; if (document.getElementById('phone')) document.getElementById('phone').value = insuranceBookingData.phone || ''; if (document.getElementById('startDate')) document.getElementById('startDate').value = insuranceBookingData.startDate || ''; if (document.getElementById('endDate')) document.getElementById('endDate').value = insuranceBookingData.endDate || ''; if (document.getElementById('destination')) document.getElementById('destination').value = insuranceBookingData.destination || ''; } }, 50); }

        function goToInsuranceStep3() {
            document.getElementById('step3Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-shield-alt"></i></div><div class="service-info"><h3>${currentInsurance.title}</h3><p class="service-price">₱${formatNumber(currentInsurance.price)}</p></div></div>
                <div class="form-section"><h4><i class="fas fa-credit-card"></i> Select Payment Method</h4>
                    <div class="payment-methods"><div class="payment-method" onclick="selectPaymentMethod('gcash')"><input type="radio" name="payment" value="gcash" id="gcashRadio"><div class="payment-icon"><i class="fas fa-mobile-alt"></i></div><div class="payment-details"><div class="payment-name">GCash</div><div class="payment-desc">Scan QR code to pay</div></div></div>
                    <div class="payment-method" onclick="selectPaymentMethod('paymaya')"><input type="radio" name="payment" value="paymaya" id="paymayaRadio"><div class="payment-icon"><i class="fas fa-mobile-alt"></i></div><div class="payment-details"><div class="payment-name">PayMaya</div><div class="payment-desc">Scan QR code to pay</div></div></div>
                    <div class="payment-method disabled" onclick="alert('Credit/Debit Card payment is coming soon! Please use other payment methods for now.')" style="opacity: 0.6; cursor: not-allowed; filter: grayscale(0.5);"><input type="radio" name="payment" value="card" id="cardRadio" disabled><div class="payment-icon"><i class="fas fa-credit-card"></i></div><div class="payment-details"><div class="payment-name">Credit / Debit Card <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div><div class="payment-desc">Coming Soon</div></div></div>
                    <div class="payment-method" onclick="selectPaymentMethod('bank')"><input type="radio" name="payment" value="bank" id="bankRadio"><div class="payment-icon"><i class="fas fa-university"></i></div><div class="payment-details"><div class="payment-name">Bank Transfer</div><div class="payment-desc">BPI, BDO, Metrobank</div></div></div></div>
                    <div id="gcashDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>GCash Payment</h4></div><div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>GCash QR Code</p><p>0945 776 4140</p></div></div><div class="account-details"><p><strong>GCash Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboard('0945 776 4140')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(insuranceBookingData.total)}</span></p></div><div class="form-group"><label>Reference Number *</label><input type="text" id="paymentRefGcash" placeholder="Enter GCash reference number"></div><div class="file-upload" onclick="document.getElementById('proofGcash').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-gcash">No file selected</p><div id="preview-gcash" class="upload-preview"></div><input type="file" id="proofGcash" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'gcash')"></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div></div></div>
                    <div id="paymayaDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>PayMaya Payment</h4></div><div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>PayMaya QR Code</p><p>0945 776 4140</p></div></div><div class="account-details"><p><strong>PayMaya Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboard('0945 776 4140')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(insuranceBookingData.total)}</span></p></div><div class="form-group"><label>Reference Number *</label><input type="text" id="paymentRefPaymaya" placeholder="Enter PayMaya reference number"></div><div class="file-upload" onclick="document.getElementById('proofPaymaya').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-paymaya">No file selected</p><div id="preview-paymaya" class="upload-preview"></div><input type="file" id="proofPaymaya" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'paymaya')"></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div></div></div>
                    <div id="cardDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-credit-card"></i><h4>Card Payment</h4></div><div class="form-group"><label>Card Number *</label><input type="text" id="cardNumber" placeholder="1234 5678 9012 3456"></div><div class="card-row"><div class="form-group"><label>Expiry *</label><input type="text" id="expiryDate" placeholder="MM/YY"></div><div class="form-group"><label>CVV *</label><input type="text" id="cvv" placeholder="123"></div></div><div class="form-group"><label>Cardholder Name *</label><input type="text" id="cardName" placeholder="Name on card"></div></div></div>
                    <div id="bankDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-university"></i><h4>Bank Transfer</h4></div><div class="account-details"><p><strong>BPI:</strong> 1234 5678 90 <button class="copy-btn" onclick="copyToClipboard('1234 5678 90')">Copy</button></p><p><strong>BDO:</strong> 5678 1234 56 <button class="copy-btn" onclick="copyToClipboard('5678 1234 56')">Copy</button></p><p><strong>Metrobank:</strong> 9012 3456 78 <button class="copy-btn" onclick="copyToClipboard('9012 3456 78')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(insuranceBookingData.total)}</span></p></div><div class="form-group"><label>Reference Number *</label><input type="text" id="bankRef" placeholder="Enter bank reference number"></div><div class="file-upload" onclick="document.getElementById('proofBank').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-bank">No file selected</p><div id="preview-bank" class="upload-preview"></div><input type="file" id="proofBank" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'bank')"></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of bank transfer confirmation</div></div></div>
                </div>
                <div id="step3Errors" class="error-message" style="display: none;"></div>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="goToInsuranceStep2()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-next" onclick="validateAndGoToStep4()">Complete Payment <i class="fas fa-check-circle"></i></button></div>`;
            updateInsuranceSteps(3);
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
            if (selectedPayment === 'gcash') { const ref = document.getElementById('paymentRefGcash')?.value.trim(); const file = document.getElementById('proofGcash')?.files[0]; if (!ref) errors.push('Please enter the GCash reference number'); if (!file) errors.push('Please upload proof of payment'); }
            if (selectedPayment === 'paymaya') { const ref = document.getElementById('paymentRefPaymaya')?.value.trim(); const file = document.getElementById('proofPaymaya')?.files[0]; if (!ref) errors.push('Please enter the PayMaya reference number'); if (!file) errors.push('Please upload proof of payment'); }
            if (selectedPayment === 'card') { if (!document.getElementById('cardNumber')?.value.trim()) errors.push('Card Number is required'); if (!document.getElementById('expiryDate')?.value.trim()) errors.push('Expiry Date is required'); if (!document.getElementById('cvv')?.value.trim()) errors.push('CVV is required'); if (!document.getElementById('cardName')?.value.trim()) errors.push('Cardholder Name is required'); }
            if (selectedPayment === 'bank') { const ref = document.getElementById('bankRef')?.value.trim(); const file = document.getElementById('proofBank')?.files[0]; if (!ref) errors.push('Reference Number is required'); if (!file) errors.push('Please upload proof of payment'); }
            if (errors.length > 0) { const errorDiv = document.getElementById('step3Errors'); errorDiv.style.display = 'flex'; errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`; errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
            let paymentMethodName = '';
            if (selectedPayment === 'gcash') paymentMethodName = 'GCash';
            else if (selectedPayment === 'paymaya') paymentMethodName = 'PayMaya';
            else if (selectedPayment === 'card') paymentMethodName = 'Credit/Debit Card';
            else if (selectedPayment === 'bank') paymentMethodName = 'Bank Transfer';
            insuranceBookingData.paymentMethod = paymentMethodName;
            goToInsuranceStep4();
        }

        function goToInsuranceStep4() {
            if (!currentInsurance || !insuranceBookingData) {
                alert('Your booking session has expired or was reset. Please close this window and start the booking again.');
                return;
            }

            try {
                // Save to server
                fetch('../api/save-service-booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_type: 'Travel Insurance',
                        package_name: currentInsurance.title,
                        package_duration: `${insuranceBookingData.startDate} to ${insuranceBookingData.endDate}`,
                        price_per_person: currentInsurance.price,
                        full_name: insuranceBookingData.fullName,
                        email: insuranceBookingData.email,
                        phone: insuranceBookingData.phone,
                        travel_date: insuranceBookingData.startDate,
                        number_of_travelers: insuranceBookingData.travelers,
                        special_requests: `DOB: ${insuranceBookingData.dob}, Conditions: ${insuranceBookingData.conditions}, Coverage: ${insuranceBookingData.coverageLabel}`,
                        total_amount: insuranceBookingData.total,
                        payment_method: selectedPayment,
                        payment_reference: document.getElementById(`paymentRef${selectedPayment.charAt(0).toUpperCase() + selectedPayment.slice(1)}`)?.value || ''
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const bookingNumber = data.booking_number;
                            document.getElementById('step4Content').innerHTML = `<div class="success-message"><i class="fas fa-check-circle"></i><h2>⏳ Policy Received!</h2><p>Your insurance application has been received and saved.</p><div class="booking-number">Policy: ${bookingNumber}</div><div class="details-card"><h4>📋 Policy Details:</h4><p><strong>Plan:</strong> ${currentInsurance.title}</p><p><strong>Coverage:</strong> ${new Date(insuranceBookingData.startDate).toLocaleDateString()} - ${new Date(insuranceBookingData.endDate).toLocaleDateString()}</p><p><strong>Destination:</strong> ${escapeHtml(insuranceBookingData.destination)}</p><p><strong>Travelers:</strong> ${insuranceBookingData.travelers}</p><p><strong>Total Premium:</strong> <span style="color:#ff9800;">₱${formatNumber(insuranceBookingData.total)}</span></p><p><strong>Payment Method:</strong> ${insuranceBookingData.paymentMethod}</p><p><strong>Payment Status:</strong> <span style="color:#ff9800;">Pending Verification</span></p><p><strong>Insured:</strong> ${escapeHtml(insuranceBookingData.fullName)}</p></div><div class="payment-status-pending"><i class="fas fa-info-circle"></i> Your payment is pending verification. Our team will review your payment proof and send confirmation within 24 hours. A confirmation has been sent to ${insuranceBookingData.email}.</div><div class="action-buttons"><button class="submit-booking-btn btn-primary" onclick="window.location.href='../User Account/profile.php?track=' + encodeURIComponent('${bookingNumber}')"><i class="fas fa-file-upload"></i> View My Booking</button><button class="submit-booking-btn btn-secondary" onclick="closeInsuranceBookingModal(); location.reload();"><i class="fas fa-plus"></i> Get Another Quote</button><button class="submit-booking-btn btn-secondary" onclick="closeInsuranceBookingModal()"><i class="fas fa-times"></i> Close</button></div></div>`;
                            updateInsuranceSteps(4);
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

        function updateInsuranceSteps(step) { for (let i = 1; i <= 4; i++) { const ind = document.getElementById(`step${i}Indicator`), cont = document.getElementById(`step${i}Content`); if (i < step) { ind.classList.add('completed'); ind.classList.remove('active'); } else if (i === step) { ind.classList.add('active'); ind.classList.remove('completed'); } else { ind.classList.remove('active', 'completed'); } if (i === step) cont.classList.add('active'); else cont.classList.remove('active'); } }
        function closeInsuranceBookingModal() { const modal = document.getElementById('insuranceBookingModal'); if (modal) modal.classList.remove('active'); insuranceBookingData = null; selectedPayment = null; }
    </script>
    <script src="../js/auth-menu.js"></script>
    <script src="../js/main.js"></script>

    <!-- ===== ENTRANCE ANIMATION SCRIPTS ===== -->
    <script>
        // --- Premium Insurance Loader Sequence ---
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
                    { p: 0, text: 'Scanning providers...', icon: 'fa-globe', title: 'Securing Your Peace of Mind...', destText: (hasRoute ? `Coverage for ${query}` : 'Policy Options'), footer: "Travel confidently, knowing you are protected anywhere." },
                    { p: 25, text: 'Calculating risks...', icon: 'fa-file-signature', title: 'Curating Insurance Plans...', destText: (hasRoute ? `Plan for ${query}` : 'Premium Protection'), footer: "Filtering plans tailored to your needs." },
                    { p: 55, text: 'Adding benefits...', icon: 'fa-medkit', title: 'Verifying Health Benefits...', destText: (hasRoute ? `Limits for ${query}` : 'Coverage Limits'), footer: "Securing emergency assistance rules." },
                    { p: 80, text: 'Ready', icon: 'fa-check-circle', title: 'Finalizing Policies...', destText: (hasRoute ? `Ready for ${query}` : 'Options Ready'), footer: "Your insurance plans are ready to view." }
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
                            const serviceHero = document.querySelector('.service-hero');
                            if (serviceHero) serviceHero.classList.add('animate-in');
                            
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
                            
                            loader.classList.add('hidden');
                            setTimeout(() => {
                                loader.remove();
                            }, 800);
                        }, 500);
                    }
                }, intervalTime);
            });
        })();

        // --- IntersectionObserver: service cards ---
        (function () {
            var cards = document.querySelectorAll('.service-card');
            if (!cards.length) return;

            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var card = entry.target;
                        var idx = Array.prototype.indexOf.call(cards, card);
                        var delay = (idx % 2) * 130; // stagger left/right columns
                        setTimeout(function () { card.classList.add('card-visible'); }, delay);
                        obs.unobserve(card);
                    }
                });
            }, { threshold: 0.12 });

            cards.forEach(function (c) { obs.observe(c); });
        })();

        // --- IntersectionObserver: info section ---
        (function () {
            var section = document.querySelector('.info-section');
            if (!section) return;

            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        section.classList.add('section-visible');
                        obs.unobserve(section);
                    }
                });
            }, { threshold: 0.1 });

            obs.observe(section);
        })();
    </script>
    <!-- ===== END ENTRANCE ANIMATION SCRIPTS ===== -->
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
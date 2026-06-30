<?php
// ========================================
// FILE: buttons/premium.php
// DESCRIPTION: Premium Travel Services with Payment System
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
    <title>HeyDream - Premium Services</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../css/sidepanel.css">
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
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #FFB347 100%);
            padding: 100px 5% 60px;
            text-align: center;
            color: #003580;
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
            background: radial-gradient(circle, rgba(255, 215, 0, 0.2) 0%, transparent 70%);
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
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }


        .service-hero h1 i {
            margin-right: 15px;
            animation: crownFloat 3s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes crownFloat {

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
            opacity: 0.9;
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
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 215, 0, 0.3);
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(255, 152, 0, 0.2);
            border-color: #ff9800;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 215, 0, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .service-card:hover::before {
            left: 100%;
        }

        .premium-badge {
            display: inline-block;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #003580;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 15px;
            flex-shrink: 0;
        }

        .service-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            transition: all 0.3s ease;
        }

        .service-icon i {
            font-size: 1.8rem;
            color: #003580;
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

        .benefits-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
        }

        .benefits-section h3 {
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
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .benefit-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .benefit-item i {
            font-size: 1.3rem;
            color: #ff9800;
            margin-bottom: 8px;
        }

        .benefit-item h4 {
            color: #003580;
            font-size: 0.8rem;
            margin-bottom: 4px;
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

        /* Booking Modal Styles (same as previous pages) */
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
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #003580;
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
            color: #003580;
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
            background: linear-gradient(135deg, #FFD700, #FFA500);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .service-icon-large i {
            font-size: 1.4rem;
            color: #003580;
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
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #003580;
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
            color: white;
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

            .benefits-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .benefits-grid {
                grid-template-columns: repeat(2, 1fr);
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
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left"><img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='../index.php'">
            <div class="company-name"><span class="line1">HeyDream Travel</span><span class="line2">and Tours</span>
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

            <a href="../index.php" class="sidebar-nav-item active" id="nav-home">
                <i class="fas fa-home sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Home</span>
                <span class="sidebar-tooltip">Home</span>
            </a>

            <a href="../local-destination.php" class="sidebar-nav-item" id="nav-local">
                <i class="fas fa-map-marker-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Local Tours</span>
                <span class="sidebar-tooltip">Local Tours</span>
            </a>

            <a href="../foreign-destinations.php" class="sidebar-nav-item" id="nav-foreign">
                <i class="fas fa-plane sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Foreign Tours</span>
                <span class="sidebar-tooltip">Foreign Tours</span>
            </a>

            <a href="../flash-deals.php" class="sidebar-nav-item" id="nav-deals">
                <i class="fas fa-tag sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Flash Deals</span>
                <span class="sidebar-tooltip">Flash Deals</span>
            </a>

            <!-- My Booking Link -->
            <button class="sidebar-nav-item" id="nav-my-booking" onclick="requireLogin('goToProfile')" style="border:none; text-align:left; background:#ffffff; width:100%;">
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
                <a href="../User Account/my-profile.php" class="sidebar-sub-item">
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
                <a href="../help-support.php" class="sidebar-sub-item">
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
                <a href="about.php" class="sidebar-sub-item">
                    <i class="fas fa-info-circle" style="color:#003580;"></i> About Us
                </a>
                <a href="../terms.php" class="sidebar-sub-item">
                    <i class="fas fa-file-alt" style="color:#003580;"></i> Terms of Service
                </a> <a href="../User Account/change-password.php" class="sidebar-sub-item" id="nav-change-password"
                    style="<?php echo (isset($auth) && $auth->isLoggedIn()) ? 'display:block;' : 'display:none;'; ?>">
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

            <a href="../User Account/login.php" class="sidebar-footer-item" id="sidebarLoginBtn">
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


    <section class="service-hero">
        <h1><i class="fas fa-crown"></i> Premium Travel Services</h1>
        <p>Experience luxury travel like never before. Exclusive perks, VIP treatment, and unforgettable journeys.</p>
    </section>

    <div class="service-content">
        <div class="service-grid">
            <div class="service-card">
                <div class="premium-badge">⭐ VIP ACCESS</div>
                <div class="service-icon"><i class="fas fa-plane"></i></div>
                <h3>First Class Flights</h3>
                <p>Lie-flat seats, gourmet dining, and exclusive lounge access.</p>
                <div class="price-section">
                    <div class="price-tag">From ₱45,999</div><button class="book-btn"
                        onclick="requireLogin('showPremiumBooking', 'First Class Flights', 45999, 'One-way')">Inquire
                        Now <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            <div class="service-card">
                <div class="premium-badge">🏨 5-STAR</div>
                <div class="service-icon"><i class="fas fa-hotel"></i></div>
                <h3>Luxury Accommodations</h3>
                <p>World's finest hotels with exclusive amenities and upgrades.</p>
                <div class="price-section">
                    <div class="price-tag">From ₱12,999/night</div><button class="book-btn"
                        onclick="requireLogin('showPremiumBooking', 'Luxury Accommodations', 12999, 'Per Night')">Inquire
                        Now <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        <div class="benefits-section">
            <h3><i class="fas fa-gem"></i> Premium Benefits</h3>
            <div class="benefits-grid">
                <div class="benefit-item"><i class="fas fa-ticket-alt"></i>
                    <h4>Priority Booking</h4>
                </div>
                <div class="benefit-item"><i class="fas fa-gift"></i>
                    <h4>Welcome Gifts</h4>
                </div>
                <div class="benefit-item"><i class="fas fa-headset"></i>
                    <h4>Dedicated Support</h4>
                </div>
                <div class="benefit-item"><i class="fas fa-chart-line"></i>
                    <h4>Upgrade Credits</h4>
                </div>
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
                            class="fab fa-instagram"></i></a><a href="#"><i class="fa-brands fa-x-twitter"></i></a><a
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
        let currentPremium = null, premiumBookingData = null, selectedPayment = null;

        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
        function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { const btn = event.target; const originalText = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#28a745'; btn.style.color = 'white'; setTimeout(() => { btn.textContent = originalText; btn.style.background = '#e0e0e0'; btn.style.color = ''; }, 1500); }); }
        function handleFileUpload(event, paymentMethod) { const file = event.target.files[0]; if (file) { if (!file.type.match('image.*')) { alert('Please upload an image file (PNG, JPG, JPEG)'); event.target.value = ''; return; } if (file.size > 5 * 1024 * 1024) { alert('File is too large. Maximum size is 5MB.'); event.target.value = ''; return; } const reader = new FileReader(); reader.onload = function (e) { const previewDiv = document.getElementById(`preview-${paymentMethod}`); if (previewDiv) { previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof">`; } }; reader.readAsDataURL(file); const fileNameSpan = document.getElementById(`file-name-${paymentMethod}`); if (fileNameSpan) { fileNameSpan.textContent = file.name; } } }

        function showPremiumBooking(title, price, duration) {
            currentPremium = { title, price, duration };
            let modal = document.getElementById('premiumBookingModal');
            if (!modal) {
                modal = document.createElement('div'); modal.id = 'premiumBookingModal'; modal.className = 'booking-modal';
                modal.innerHTML = `<div class="booking-modal-content"><div class="booking-modal-header"><span class="close-modal" onclick="closePremiumBookingModal()">&times;</span><h2><i class="fas fa-crown"></i> Premium Service</h2><p>Complete your inquiry</p></div><div class="booking-modal-body"><div class="booking-steps"><div class="step active" id="step1Indicator"><div class="step-number">1</div><div class="step-label">Details</div><div class="step-line"></div></div><div class="step" id="step2Indicator"><div class="step-number">2</div><div class="step-label">Review</div><div class="step-line"></div></div><div class="step" id="step3Indicator"><div class="step-number">3</div><div class="step-label">Payment</div><div class="step-line"></div></div><div class="step" id="step4Indicator"><div class="step-number">4</div><div class="step-label">Confirm</div></div></div><div id="step1Content" class="step-content active"></div><div id="step2Content" class="step-content"></div><div id="step3Content" class="step-content"></div><div id="step4Content" class="step-content"></div></div></div>`;
                document.body.appendChild(modal);
                modal.addEventListener('click', function (e) { if (e.target === modal) closePremiumBookingModal(); });
            }
            renderPremiumStep1();
            modal.classList.add('active');
        }

        function renderPremiumStep1() {
            document.getElementById('step1Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-crown"></i></div><div class="service-info"><h3>${currentPremium.title}</h3><p class="service-price">₱${formatNumber(currentPremium.price)}</p><p class="service-duration">${currentPremium.duration}</p></div></div>
                <form id="premiumForm" onsubmit="return false;">
                    <div class="form-section"><h4><i class="fas fa-user"></i> Your Information</h4>
                        <div class="form-group"><label>Full Name <span class="required">*</span></label><input type="text" id="fullName" placeholder="Enter your full name" value="${window.currentFullName || ''}"></div>
                        <div class="form-group"><label>Phone <span class="required">*</span></label><input type="tel" id="phone" placeholder="+63 912 345 6789"></div>
                        <div class="form-group"><label>Preferred Contact</label><select id="contactPref"><option value="email">Email</option><option value="phone">Phone</option><option value="whatsapp">WhatsApp</option></select></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-calendar-alt"></i> Travel Details</h4>
                        <div class="form-row"><div class="form-group"><label>Travel Date <span class="required">*</span></label><input type="date" id="travelDate" min="${new Date().toISOString().split('T')[0]}"></div>
                        <div class="form-group"><label>Travelers <span class="required">*</span></label><input type="number" id="travelers" min="1" value="1"></div></div>
                        <div class="form-group"><label>Destination <span class="required">*</span></label><input type="text" id="destination" placeholder="e.g., Paris, Maldives"></div>
                        <div class="form-group"><label>Membership Tier</label><select id="tier"><option value="silver">Silver (5% off)</option><option value="gold">Gold (10% off)</option><option value="platinum">Platinum (15% off)</option></select></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-star"></i> Special Requests</h4>
                        <div class="form-group"><label>Additional Services</label><textarea id="services" rows="2" placeholder="Private transfers, personal assistant, special occasions..."></textarea></div>
                        <div class="form-group"><label>Budget Range</label><select id="budget"><option value="50k-100k">₱50,000 - ₱100,000</option><option value="100k-200k">₱100,000 - ₱200,000</option><option value="200k+">₱200,000+</option></select></div>
                    </div>
                    <div id="step1Errors" class="error-message" style="display: none;"></div>
                    <div class="action-buttons"><button type="button" class="btn-next" onclick="validateAndGoToStep2()">Review Inquiry <i class="fas fa-arrow-right"></i></button></div>
                </form>`;
        }

        function validateAndGoToStep2() {
            const errors = [];
            const fullName = document.getElementById('fullName')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const travelDate = document.getElementById('travelDate')?.value;
            const destination = document.getElementById('destination')?.value.trim();
            const travelers = document.getElementById('travelers')?.value;
            if (!fullName) errors.push('Full Name is required');

            // Use auto-detected email
            const email = window.currentUserEmail || '';
            if (!email) errors.push('Your account email could not be detected. Please log in again.');
            if (!phone) errors.push('Phone number is required');
            if (!travelDate) errors.push('Travel Date is required');
            if (!destination) errors.push('Destination is required');
            if (!travelers || travelers < 1) errors.push('At least 1 traveler is required');
            document.querySelectorAll('.form-group input, .form-group select').forEach(f => f.classList.remove('error'));
            if (!fullName) document.getElementById('fullName')?.classList.add('error');
            if (!phone) document.getElementById('phone')?.classList.add('error');
            if (!travelDate) document.getElementById('travelDate')?.classList.add('error');
            if (!destination) document.getElementById('destination')?.classList.add('error');
            if (!travelers || travelers < 1) document.getElementById('travelers')?.classList.add('error');
            if (errors.length > 0) { const errorDiv = document.getElementById('step1Errors'); errorDiv.style.display = 'flex'; errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`; errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
            goToPremiumStep2();
        }

        function goToPremiumStep2() {
            const fullName = document.getElementById('fullName')?.value;
            const email = window.currentUserEmail || '';
            const phone = document.getElementById('phone')?.value;
            const contactPref = document.getElementById('contactPref')?.value;
            const travelDate = document.getElementById('travelDate')?.value;
            const travelers = parseInt(document.getElementById('travelers')?.value) || 1;
            const destination = document.getElementById('destination')?.value;
            const tier = document.getElementById('tier')?.value;
            const services = document.getElementById('services')?.value;
            const budget = document.getElementById('budget')?.value;
            let discount = 0, tierLabel = 'Silver';
            if (tier === 'gold') { discount = 0.10; tierLabel = 'Gold (10% off)'; }
            else if (tier === 'platinum') { discount = 0.15; tierLabel = 'Platinum (15% off)'; }
            const discountedPrice = currentPremium.price * (1 - discount);
            const total = discountedPrice * travelers;
            premiumBookingData = { fullName, email, phone, contactPref, travelDate, travelers, destination, tierLabel, services, budget, discountedPrice, total };
            document.getElementById('step2Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-crown"></i></div><div class="service-info"><h3>${currentPremium.title}</h3><p class="service-price">₱${formatNumber(currentPremium.price)}</p></div></div>
                <div class="review-details"><div class="review-section"><h4>Contact Information</h4><div class="review-row"><div class="review-label">Name:</div><div class="review-value">${escapeHtml(fullName)}</div></div><div class="review-row"><div class="review-label">Email:</div><div class="review-value">${escapeHtml(email)}</div></div><div class="review-row"><div class="review-label">Phone:</div><div class="review-value">${escapeHtml(phone)}</div></div><div class="review-row"><div class="review-label">Contact via:</div><div class="review-value">${contactPref === 'email' ? 'Email' : contactPref === 'phone' ? 'Phone' : 'WhatsApp'}</div></div></div>
                <div class="review-section"><h4>Travel Details</h4><div class="review-row"><div class="review-label">Travel Date:</div><div class="review-value">${new Date(travelDate).toLocaleDateString()}</div></div><div class="review-row"><div class="review-label">Destination:</div><div class="review-value">${escapeHtml(destination)}</div></div><div class="review-row"><div class="review-label">Travelers:</div><div class="review-value">${travelers}</div></div><div class="review-row"><div class="review-label">Membership:</div><div class="review-value">${tierLabel}</div></div><div class="review-row"><div class="review-label">Budget:</div><div class="review-value">${budget === '50k-100k' ? '₱50,000 - ₱100,000' : budget === '100k-200k' ? '₱100,000 - ₱200,000' : '₱200,000+'}</div></div></div>
                <div class="review-section"><h4>Price Summary</h4><div class="review-row"><div class="review-label">Base Price:</div><div class="review-value">₱${formatNumber(currentPremium.price)}</div></div>${discount > 0 ? `<div class="review-row"><div class="review-label">Member Discount:</div><div class="review-value">${discount * 100}% off</div></div><div class="review-row"><div class="review-label">Discounted:</div><div class="review-value">₱${formatNumber(discountedPrice)}</div></div>` : ''}<div class="review-row total"><div class="review-label">Total Estimate:</div><div class="review-value" style="color:#ff9800;">₱${formatNumber(total)}</div></div></div></div>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="goToPremiumStep1()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-next" onclick="goToPremiumStep3()">Proceed to Payment <i class="fas fa-credit-card"></i></button></div>`;
            updatePremiumSteps(2);
        }

        function goToPremiumStep1() { updatePremiumSteps(1); setTimeout(() => { if (premiumBookingData) { if (document.getElementById('fullName')) document.getElementById('fullName').value = premiumBookingData.fullName || ''; if (document.getElementById('phone')) document.getElementById('phone').value = premiumBookingData.phone || ''; if (document.getElementById('travelDate')) document.getElementById('travelDate').value = premiumBookingData.travelDate || ''; if (document.getElementById('destination')) document.getElementById('destination').value = premiumBookingData.destination || ''; } }, 50); }

        function goToPremiumStep3() {
            document.getElementById('step3Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-crown"></i></div><div class="service-info"><h3>${currentPremium.title}</h3><p class="service-price">₱${formatNumber(currentPremium.price)}</p></div></div>
                <div class="form-section"><h4><i class="fas fa-credit-card"></i> Select Payment Method</h4>
                    <div class="payment-methods"><div class="payment-method" onclick="selectPaymentMethod('gcash')"><input type="radio" name="payment" value="gcash" id="gcashRadio"><div class="payment-icon"><i class="fas fa-mobile-alt"></i></div><div class="payment-details"><div class="payment-name">GCash</div><div class="payment-desc">Scan QR code to pay</div></div></div>
                    <div class="payment-method" onclick="selectPaymentMethod('paymaya')"><input type="radio" name="payment" value="paymaya" id="paymayaRadio"><div class="payment-icon"><i class="fas fa-mobile-alt"></i></div><div class="payment-details"><div class="payment-name">PayMaya</div><div class="payment-desc">Scan QR code to pay</div></div></div>
                    <div class="payment-method" onclick="selectPaymentMethod('card')"><input type="radio" name="payment" value="card" id="cardRadio"><div class="payment-icon"><i class="fas fa-credit-card"></i></div><div class="payment-details"><div class="payment-name">Credit / Debit Card</div><div class="payment-desc">Visa, Mastercard, JCB</div></div></div>
                    <div class="payment-method" onclick="selectPaymentMethod('bank')"><input type="radio" name="payment" value="bank" id="bankRadio"><div class="payment-icon"><i class="fas fa-university"></i></div><div class="payment-details"><div class="payment-name">Bank Transfer</div><div class="payment-desc">BPI, BDO, Metrobank</div></div></div></div>
                    <div id="gcashDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>GCash Payment</h4></div><div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>GCash QR Code</p><p>0945 776 4140</p></div></div><div class="account-details"><p><strong>GCash Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboard('0945 776 4140')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(premiumBookingData.total)}</span></p></div><div class="form-group"><label>Reference Number *</label><input type="text" id="paymentRefGcash" placeholder="Enter GCash reference number"></div><div class="file-upload" onclick="document.getElementById('proofGcash').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-gcash">No file selected</p><div id="preview-gcash" class="upload-preview"></div><input type="file" id="proofGcash" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'gcash')"></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div></div></div>
                    <div id="paymayaDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>PayMaya Payment</h4></div><div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>PayMaya QR Code</p><p>0945 776 4140</p></div></div><div class="account-details"><p><strong>PayMaya Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboard('0945 776 4140')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(premiumBookingData.total)}</span></p></div><div class="form-group"><label>Reference Number *</label><input type="text" id="paymentRefPaymaya" placeholder="Enter PayMaya reference number"></div><div class="file-upload" onclick="document.getElementById('proofPaymaya').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-paymaya">No file selected</p><div id="preview-paymaya" class="upload-preview"></div><input type="file" id="proofPaymaya" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'paymaya')"></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div></div></div>
                    <div id="cardDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-credit-card"></i><h4>Card Payment</h4></div><div class="form-group"><label>Card Number *</label><input type="text" id="cardNumber" placeholder="1234 5678 9012 3456"></div><div class="card-row"><div class="form-group"><label>Expiry *</label><input type="text" id="expiryDate" placeholder="MM/YY"></div><div class="form-group"><label>CVV *</label><input type="text" id="cvv" placeholder="123"></div></div><div class="form-group"><label>Cardholder Name *</label><input type="text" id="cardName" placeholder="Name on card"></div></div></div>
                    <div id="bankDetails" class="payment-details-box"><div class="payment-instructions"><div class="instruction-header"><i class="fas fa-university"></i><h4>Bank Transfer</h4></div><div class="account-details"><p><strong>BPI:</strong> 1234 5678 90 <button class="copy-btn" onclick="copyToClipboard('1234 5678 90')">Copy</button></p><p><strong>BDO:</strong> 5678 1234 56 <button class="copy-btn" onclick="copyToClipboard('5678 1234 56')">Copy</button></p><p><strong>Metrobank:</strong> 9012 3456 78 <button class="copy-btn" onclick="copyToClipboard('9012 3456 78')">Copy</button></p><p><strong>Account Name:</strong> HeyDream Travel & Tours</p><p><strong>Amount:</strong> <span style="color:#ff9800;">₱${formatNumber(premiumBookingData.total)}</span></p></div><div class="form-group"><label>Reference Number *</label><input type="text" id="bankRef" placeholder="Enter bank reference number"></div><div class="file-upload" onclick="document.getElementById('proofBank').click()"><i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p><p class="file-name" id="file-name-bank">No file selected</p><div id="preview-bank" class="upload-preview"></div><input type="file" id="proofBank" accept="image/*" style="display:none" onchange="handleFileUpload(event, 'bank')"></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of bank transfer confirmation</div></div></div>
                </div>
                <div id="step3Errors" class="error-message" style="display: none;"></div>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="goToPremiumStep2()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-next" onclick="validateAndGoToStep4()">Complete Payment <i class="fas fa-check-circle"></i></button></div>`;
            updatePremiumSteps(3);
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
            premiumBookingData.paymentMethod = paymentMethodName;
            goToPremiumStep4();
        }

        function goToPremiumStep4() {
            // Save to server
            fetch('../api/save-service-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    service_type: 'Premium Service',
                    package_name: currentPremium.title,
                    package_duration: currentPremium.duration,
                    price_per_person: currentPremium.price,
                    full_name: premiumBookingData.fullName,
                    email: premiumBookingData.email,
                    phone: premiumBookingData.phone,
                    travel_date: premiumBookingData.travelDate,
                    number_of_travelers: premiumBookingData.travelers,
                    special_requests: `Dest: ${premiumBookingData.destination}, Tier: ${premiumBookingData.tierLabel}, Contact: ${premiumBookingData.contactPref}, Services: ${premiumBookingData.services}, Budget: ${premiumBookingData.budget}`,
                    total_amount: premiumBookingData.total,
                    payment_method: premiumBookingData.paymentMethod,
                    payment_reference: document.getElementById(`paymentRef${selectedPayment.charAt(0).toUpperCase() + selectedPayment.slice(1)}`)?.value || ''
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const bookingNumber = data.booking_number;
                        document.getElementById('step4Content').innerHTML = `<div class="success-message"><i class="fas fa-check-circle"></i><h2>👑 Premium Inquiry Saved!</h2><p>Your premium inquiry has been saved and confirmed.</p><div class="booking-number">Reference: ${bookingNumber}</div><div class="details-card"><h4>📋 Inquiry Details:</h4><p><strong>Service:</strong> ${currentPremium.title}</p><p><strong>Destination:</strong> ${escapeHtml(premiumBookingData.destination)}</p><p><strong>Travel Date:</strong> ${new Date(premiumBookingData.travelDate).toLocaleDateString()}</p><p><strong>Travelers:</strong> ${premiumBookingData.travelers}</p><p><strong>Estimated Total:</strong> <span style="color:#ff9800;">₱${formatNumber(premiumBookingData.total)}</span></p><p><strong>Payment Method:</strong> ${premiumBookingData.paymentMethod}</p><p><strong>Status:</strong> <span style="color:#28a745;">Confirmed</span></p><p><strong>Booked By:</strong> ${escapeHtml(premiumBookingData.fullName)}</p></div><div class="instruction-note"><i class="fas fa-info-circle"></i> A confirmation email has been sent to ${premiumBookingData.email}. Our concierge will be in touch shortly.</div><div class="action-buttons"><button class="submit-booking-btn btn-primary" onclick="closePremiumBookingModal(); location.reload();"><i class="fas fa-plus"></i> New Inquiry</button><button class="submit-booking-btn btn-secondary" onclick="closePremiumBookingModal()"><i class="fas fa-times"></i> Close</button></div></div>`;
                        updatePremiumSteps(4);
                    } else {
                        alert('Error saving booking: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Connection error. Please try again.');
                });
        }

        function updatePremiumSteps(step) { for (let i = 1; i <= 4; i++) { const ind = document.getElementById(`step${i}Indicator`), cont = document.getElementById(`step${i}Content`); if (i < step) { ind.classList.add('completed'); ind.classList.remove('active'); } else if (i === step) { ind.classList.add('active'); ind.classList.remove('completed'); } else { ind.classList.remove('active', 'completed'); } if (i === step) cont.classList.add('active'); else cont.classList.remove('active'); } }
        function closePremiumBookingModal() { const modal = document.getElementById('premiumBookingModal'); if (modal) modal.classList.remove('active'); premiumBookingData = null; selectedPayment = null; }
    </script>
    <script src="../js/menu.js"></script>
    <script src="../js/auth-menu.js"></script>
    <script src="../js/main.js"></script>
</body>

</html>
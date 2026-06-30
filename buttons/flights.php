<?php
// ========================================
// FILE: buttons/flights.php
// DESCRIPTION: Flight Booking with Payment Proof Upload
// ========================================
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = new Auth($pdo);

// Fetch flight packages from database
try {
    $stmt = $pdo->query("SELECT * FROM site_services WHERE service_type = 'flight' AND is_active = 1 ORDER BY display_order, id DESC");
    $db_flights = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_flights = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Flight Booking</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f8fbff;
            font-family: 'Outfit', 'Poppins', sans-serif;
            color: #1a2b48;
            position: relative;
        }

        /* Navbar Customization */
        .navbar {
            background: white !important;
            padding: 10px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            box-sizing: border-box;
        }

        .flights-hero-container {
            width: 100%;
            padding: 20px 1%;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease 0.1s, transform 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
        }

        .flights-hero-container.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Hero content inner elements - staggered animations */
        .flights-hero .flight-badge {
            opacity: 0;
            transform: scale(0.7) rotate(-10deg);
            transition: opacity 0.5s ease 0.4s, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.4s;
        }

        .flights-hero-container.animate-in .flight-badge {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }

        .flights-hero h1 {
            opacity: 0;
            transform: translateX(-20px);
            transition: opacity 0.5s ease 0.5s, transform 0.5s ease 0.5s;
        }

        .flights-hero-container.animate-in h1 {
            opacity: 1;
            transform: translateX(0);
        }

        .flights-hero .hero-description {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.5s ease 0.65s, transform 0.5s ease 0.65s;
        }

        .flights-hero-container.animate-in .hero-description {
            opacity: 1;
            transform: translateY(0);
        }

        /* Simplified Hero Design for Visibility */
        .flights-hero {
            background: linear-gradient(180deg, #00d2ff 0%, #3a7bd5 40%, #ffffff 100%);
            border-radius: 20px;
            margin: 0 auto;
            width: 98%;
            max-width: 100%;
            box-sizing: border-box;
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

        /* Plane Watermark */
        .flights-hero::before {
            content: '';
            position: absolute;
            right: -2%;
            top: 50%;
            transform: translateY(-50%);
            width: 68%;
            height: 200%;
            background: url('../images/flight-hero.png') no-repeat center center;
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
        .flights-hero::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent 40%, rgba(255, 255, 255, 0.2) 60%, rgba(255, 255, 255, 0.7) 85%, #ffffff 100%);
            z-index: 2;
            pointer-events: none;
        }

        .flights-hero .hero-content {
            align-items: flex-start !important;
            text-align: left !important;
            margin-left: 0 !important;
            margin-right: auto !important;
            max-width: 100% !important;
            width: 100%;
        }

        .hero-title-area,
        .hero-description {
            position: relative;
            z-index: 3;
            text-align: left;
        }

        .hero-title-area {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .hero-title-area h1 {
            font-size: 4rem;
            font-weight: 800;
            margin: 0;
            color: white !important;
            letter-spacing: -1px;
            line-height: 1.1;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .flight-badge {
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

        .flight-badge i {
            font-size: 2.2rem;
            color: #3a7bd5;
        }

        .hero-description {
            max-width: 600px;
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
            font-weight: 500;
            margin: 20px 0 40px;
        }






        /* Hide the old trust box CSS as it's not in this design */
        .trust-box {
            display: none;
        }

        /* Trust Box */
        .trust-box {
            background: white;
            padding: 12px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            display: inline-flex;
            gap: 30px;
            position: absolute;
            bottom: 15px;
            left: 5%;
            z-index: 10;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #1a2b48;
        }

        .trust-icon {
            font-size: 1.2rem;
            color: #002147;
        }

        .trust-text {
            display: flex;
            flex-direction: column;
        }

        .trust-text span:first-child {
            font-size: 0.65rem;
            color: #64748b;
        }

        .trust-text span:last-child {
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* Service Content Wrapper */
        .service-content {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 80px;
        }

        /* Premium Horizontal Service Card */
        .service-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(min(650px, 100%), 1fr)) !important;
            gap: 40px !important;
            margin: 60px auto !important;
            width: 95% !important;
            max-width: 1400px !important;
            box-sizing: border-box !important;
            position: relative !important;
            z-index: 5 !important;
        }

        .service-card {
            background: white !important;
            border-radius: 35px !important;
            display: flex !important;
            flex-direction: row !important;
            overflow: visible !important;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #f1f5f9 !important;
            padding: 30px !important;
            gap: 40px !important;
            opacity: 0 !important;
            transform: translateY(40px) !important;
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .service-card.animate-in {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }

        .service-card:nth-child(1).animate-in { transition-delay: 0.55s; }
        .service-card:nth-child(2).animate-in { transition-delay: 0.70s; }
        .service-card:nth-child(3).animate-in { transition-delay: 0.85s; }
        .service-card:nth-child(4).animate-in { transition-delay: 1.00s; }
        .service-card:nth-child(5).animate-in { transition-delay: 1.15s; }
        .service-card:nth-child(6).animate-in { transition-delay: 1.30s; }

        .service-image-container {
            width: 35%;
            min-width: 250px;
            height: 320px;
            border-radius: 25px;
            overflow: hidden;
            flex-shrink: 0;
            position: relative;
        }

        .service-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .service-card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 10px 15px;
        }

        .card-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            color: #0091ff;
            padding: 8px 18px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            z-index: 20;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.5px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .service-card-body h3 {
            font-size: 1.8rem;
            color: #0091ff;
            font-weight: 800;
            margin-bottom: 12px;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .service-card-body p {
            color: #64748b;
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 25px;
        }

        /* The Info Dashboard */
        .card-info-dashboard {
            background: #f0f7ff;
            border-radius: 20px;
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            border: 1px solid rgba(0, 53, 128, 0.05);
        }

        .dashboard-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-icon {
            font-size: 1.6rem;
            color: #0091ff;
        }

        .dashboard-text {
            display: flex;
            flex-direction: column;
        }

        .dashboard-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .dashboard-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0091ff;
        }

        .dashboard-divider {
            width: 1px;
            height: 45px;
            background: rgba(0, 53, 128, 0.1);
        }

        .card-action-btn {
            background: #0091ff;
            color: white;
            width: 100%;
            padding: 18px;
            border-radius: 18px;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .card-action-btn:hover {
            background: #002560;
            box-shadow: 0 10px 25px rgba(0, 53, 128, 0.25);
            transform: translateY(-2px);
        }

        .view-details-btn:hover {
            background: #002560;
            box-shadow: 0 10px 20px rgba(0, 53, 128, 0.2);
        }

        /* Partners Section */
        .partners-section {
            padding: 30px 0;
            text-align: center;
        }

        .partners-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 1.4rem;
            font-weight: 800;
            color: #0091ff;
            margin-bottom: 20px;
        }

        .partners-grid {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .partner-card {
            background: white;
            padding: 12px 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #1a2b48;
            border: 1px solid #f1f5f9;
            transition: all 0.3s;
        }

        .partner-card:hover {
            transform: translateY(-5px);
            border-color: #0091ff;
        }

        /* Help CTA */
        .help-cta {
            background: #0091ff;
            border-radius: 20px;
            padding: 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }

        .help-cta::after {
            content: '\f5b0';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 200px;
            bottom: -20px;
            font-size: 120px;
            opacity: 0.05;
            transform: rotate(-15deg);
        }

        .help-left {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .flight-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .hero-title-area {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .headset-circle {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            backdrop-filter: blur(5px);
        }

        .help-text-content h3 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .help-text-content p {
            opacity: 0.8;
            font-size: 1rem;
        }

        .contact-btn {
            background: white;
            color: #0091ff;
            padding: 15px 40px;
            border-radius: 40px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
            z-index: 2;
        }

        .contact-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        /* Flight Grid Updates */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .service-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid #f1f5f9;
        }


        .service-card-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 15px;
            border-top: 1px solid #f0f2f5;
            margin-bottom: 20px;
        }

        .info-left {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #555;
            font-weight: 600;
        }

        .info-item i {
            color: #ff9800;
            font-size: 0.9rem;
        }

        .info-right {
            text-align: right;
        }

        .starting-text {
            display: block;
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 2px;
            font-weight: 500;
        }

        .price-tag {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0091ff;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .currency-symbol {
            font-size: 1.1rem;
            color: #0091ff;
        }

        .details-btn {
            background: #0091ff;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 15px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
        }

        .details-btn:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 124, 0, 0.3);
        }

        .info-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
        }

        .info-section h3 {
            color: #0091ff;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .info-list {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }

        .info-list li {
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            border: 1px solid #e0e0e0;
        }

        .info-list li i {
            color: #ff9800;
            width: 20px;
        }

        .partners-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .partner-badge {
            background: #f8f9fa;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #0091ff;
            border: 1px solid #e0e0e0;
        }

        .back-button-container {
            text-align: center;
            padding: 40px 15px;
            margin-top: 20px;
            background: #f8f9fa;
        }

        .back-button {
            background: linear-gradient(135deg, #0091ff, #1a4b8c);
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

        .booking-modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .booking-modal-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .booking-modal-content::-webkit-scrollbar-thumb {
            background: rgba(0, 53, 128, 0.2);
            border-radius: 10px;
        }

        .booking-modal-content::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 53, 128, 0.4);
        }

        .booking-modal-header {
            background: linear-gradient(135deg, #0091ff, #1a4b8c);
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
            position: relative;
            z-index: 2;
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
            background: white;
            padding: 20px;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            padding: 25px 30px;
            background: white;
            border-top: 1px solid #e2e8f0;
            justify-content: flex-end;
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

        .btn-next:hover {
            background: #ff9800;
            transform: translateY(-2px);
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

        .step.completed .step-label {
            color: #28a745;
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
            background: linear-gradient(135deg, #FF6B6B, #FF8E8E);
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
            color: #0091ff;
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
            color: #0091ff;
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
            color: #0091ff;
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
            background: linear-gradient(135deg, #0091ff, #1a4b8c);
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

        /* Payment Methods - 2x2 Grid */
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
            color: #0091ff;
            margin-bottom: 2px;
            font-size: 0.85rem;
        }

        .payment-desc {
            font-size: 0.65rem;
            color: #666;
        }

        /* Payment Details Box */
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

        /* Premium Tabbed Modal Styles */
        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 2500;
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .details-modal.active {
            display: flex;
        }

        .details-modal-content {
            background: white;
            width: 100%;
            max-width: 1000px;
            max-height: 90vh;
            border-radius: 24px;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .details-modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .details-modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .details-modal-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .modal-hero {
            position: relative;
            height: 450px;
            width: 100%;
        }

        .modal-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-hero-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 50px 40px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, transparent 100%);
            color: white;
        }

        .modal-hero-overlay h2 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -1px;
            color: white !important;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        }

        .modal-meta {
            display: flex;
            gap: 25px;
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
            color: white !important;
        }

        .modal-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Tabs */
        .modal-tabs {
            display: flex;
            background: white;
            border-bottom: 1px solid #f1f5f9;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .tab-btn {
            flex: 1;
            padding: 20px;
            border: none;
            background: transparent;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            border-bottom: 3px solid transparent;
            font-family: 'Outfit', sans-serif;
        }

        .tab-btn:hover {
            color: #0091ff;
            background: #f8fbff;
        }

        .tab-btn.active {
            color: #0091ff;
            border-bottom-color: #ff9800;
        }

        .tab-content {
            padding: 40px;
            min-height: 400px;
        }

        .tab-pane {
            display: none;
            animation: modalFadeIn 0.4s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Gallery Premium */
        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .gallery-header h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0091ff;
        }

        .photo-count-badge {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }


        .premium-gallery-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            grid-template-rows: repeat(2, 200px);
            gap: 15px;
            margin-bottom: 30px;
        }

        .gallery-item-large {
            grid-row: span 2;
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            cursor: pointer;
        }

        .gallery-item-small {
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            cursor: pointer;
        }

        .premium-gallery-grid img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .premium-gallery-grid div:hover img {
            transform: scale(1.1);
        }

        .img-overlay-text {
            position: absolute;
            bottom: 20px;
            left: 20px;
            color: white;
            font-weight: 600;
            z-index: 2;
            font-size: 0.9rem;
        }

        .view-all-photos-bar {
            width: 100%;
            padding: 18px;
            background: #f8fbff;
            border-radius: 15px;
            border: none;
            color: #0091ff;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-all-photos-bar:hover {
            background: #f0f7ff;
            transform: scale(1.01);
        }

        /* Sticky Bottom Pricing Bar */
        .modal-sticky-footer {
            position: sticky;
            bottom: 0;
            width: 100%;
            background: white;
            padding: 25px 40px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.05);
        }

        .footer-highlights {
            display: flex;
            gap: 35px;
        }

        .partner-card {
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

        .footer-highlight-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .footer-highlight-item i {
            color: #ff9800;
            font-size: 1.2rem;
        }

        .footer-price-area {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .price-label-group {
            display: flex;
            flex-direction: column;
            text-align: right;
        }

        .price-label-group span:first-child {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }

        .price-label-group span:last-child {
            font-size: 2.2rem;
            font-weight: 900;
            color: #ff9800;
            line-height: 1;
        }

        .book-now-modal-btn {
            background: #0091ff;
            color: white;
            padding: 18px 40px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(0, 53, 128, 0.2);
        }

        .book-now-modal-btn:hover {
            background: #ff9800;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255, 152, 0, 0.3);
        }

        /* Overview Pane specific styles */
        .overview-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        .overview-main {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .overview-section h4 {
            font-size: 1.4rem;
            color: #0091ff;
            margin-bottom: 20px;
            font-weight: 800;
        }

        .overview-text {
            color: #475569;
            line-height: 1.8;
            font-size: 1.05rem;
        }

        .inclusions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .inclusion-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            color: #475569;
        }

        .inclusion-item i {
            color: #10b981;
        }

        .quick-info-box {
            background: #f8fbff;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 100px;
        }

        .quick-info-box h5 {
            color: #0091ff;
            margin-bottom: 15px;
            font-weight: 800;
            font-size: 1.1rem;
        }

        .quick-info-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .quick-info-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .quick-info-label {
            color: #64748b;
        }

        .quick-info-value {
            font-weight: 700;
        }

        .itinerary-list {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .itinerary-item {
            display: flex;
            gap: 20px;
        }

        .itinerary-number {
            width: 40px;
            height: 40px;
            background: #0091ff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: 800;
        }

        .itinerary-info h5 {
            margin-bottom: 5px;
            font-weight: 700;
            font-size: 1rem;
            color: #1e293b;
        }

        .itinerary-info p {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.6;
        }

        .instruction-header h4 {
            color: #0091ff;
            margin: 0;
            font-size: 1rem;
        }

        /* QR Code Styles */
        .qr-code {
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .qr-code img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            margin: 0 auto;
            display: block;
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

        .qr-placeholder p {
            font-size: 0.7rem;
            color: #666;
            margin: 0;
        }

        .qr-code-note {
            font-size: 0.7rem;
            color: #888;
            margin-top: 8px;
            text-align: center;
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
            color: #0091ff;
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

        /* File Upload Styles */
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
            color: #0091ff;
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
            color: #0091ff;
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
                background-color: #002147 !important;
            }

            body {
                overscroll-behavior: contain !important;
                overflow-x: hidden !important;
                width: 100% !important;
                position: relative !important;
            }

            .flights-hero-container {
                padding: 0 !important;
            }

            .flights-hero {
                padding: 65px 6% 30px !important;
                border-radius: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                min-height: auto !important;
                background: linear-gradient(180deg, #002147 0%, #003366 100%) !important;
            }

            .flights-hero::before {
                opacity: 0.3 !important;
                width: 150% !important;
                height: 150% !important;
                right: -25% !important;
                top: 50% !important;
                -webkit-mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
                mask-image: linear-gradient(to bottom, black 40%, transparent 100%) !important;
            }

            .hero-title-area {
                flex-direction: row !important;
                align-items: center !important;
                gap: 12px !important;
                margin-bottom: 10px !important;
            }

            .flight-badge {
                width: 45px !important;
                height: 45px !important;
                border-radius: 10px !important;
            }

            .flight-badge i {
                font-size: 1.3rem !important;
            }

            .flights-hero h1 {
                font-size: 2rem !important;
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

            .service-card {
                flex-direction: column !important;
                padding: 20px !important;
                gap: 20px !important;
                border-radius: 25px !important;
            }

            .service-image-container {
                width: 100% !important;
                min-width: auto !important;
                height: 220px !important;
                border-radius: 20px !important;
            }

            .service-card-body {
                padding: 5px 0 !important;
                text-align: left;
                align-items: flex-start;
            }

            .service-card-body h3 {
                font-size: 1.6rem !important;
                margin-bottom: 12px !important;
            }

            .service-card-body p {
                font-size: 0.95rem !important;
                -webkit-line-clamp: 3;
                margin-bottom: 20px !important;
            }

            .card-info-dashboard {
                padding: 15px !important;
                gap: 15px !important;
                flex-direction: row !important;
                flex-wrap: wrap;
                width: 100%;
            }

            .dashboard-item {
                gap: 10px !important;
                flex: 1;
                min-width: 120px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .info-list {
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

            /* Premium Modal Mobile Optimization */
            .details-modal {
                padding: 10px;
            }

            .details-modal-content {
                max-height: 95vh;
                border-radius: 16px;
            }

            .modal-hero {
                height: 250px;
            }

            .modal-hero-overlay {
                padding: 25px 20px;
            }

            .modal-hero-overlay h2 {
                font-size: 1.8rem;
            }

            .modal-meta {
                gap: 15px;
                font-size: 0.85rem;
                flex-wrap: wrap;
            }

            .modal-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }

            .modal-tabs::-webkit-scrollbar {
                display: none;
            }

            .tab-btn {
                min-width: 110px;
                padding: 15px 10px;
                font-size: 0.85rem;
            }

            .tab-content {
                padding: 20px;
            }

            .overview-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .overview-main {
                gap: 25px;
            }

            .overview-section h4 {
                font-size: 1.2rem;
            }

            .inclusions-grid {
                grid-template-columns: 1fr;
            }

            .quick-info-box {
                position: static;
                padding: 20px;
            }

            .premium-gallery-grid {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: repeat(3, 150px);
                gap: 10px;
            }

            .gallery-item-large {
                grid-row: span 1;
                grid-column: span 2;
            }

            .modal-sticky-footer {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }

            .footer-highlights {
                display: none;
                /* Hide highlights on small screens to save space */
            }

            .footer-price-area {
                width: 100%;
                justify-content: space-between;
                gap: 15px;
            }

            .price-label-group span:last-child {
                font-size: 1.6rem;
            }

            .book-now-modal-btn {
                flex: 1;
                padding: 12px 25px;
                font-size: 1rem;
                text-align: center;
            }

            .modal-close {
                top: 15px !important;
                right: 15px !important;
                width: 35px !important;
                height: 35px !important;
                font-size: 1rem !important;
            }

            .partners-grid {
                display: flex !important;
                flex-direction: row !important;
                justify-content: flex-start !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                padding: 15px 20px !important;
                gap: 12px !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                scrollbar-width: thin;
                scrollbar-color: #ff9800 transparent;
            }

            .partners-grid::-webkit-scrollbar {
                height: 3px;
                display: block !important;
            }

            .partners-grid::-webkit-scrollbar-thumb {
                background: #ff9800;
                border-radius: 10px;
            }

            .partner-card,
            .partner-badge {
                flex-shrink: 0 !important;
                min-width: max-content !important;
            }
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

            .qr-code img {
                width: 140px;
                height: 140px;
            }
        }

        /* Removed Legacy Details Modal Styles */

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
            background: #003580;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
            margin-top: 20px;
        }

        .details-btn:hover {
            background: #ff9800;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.3);
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
            color: white !important;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
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

        /* ==========================================
           ENTRANCE ANIMATIONS
        ========================================== */

        /* Page Loader Overlay - Premium Design */
        #page-loader {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #0968f5, #0017a4);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease, visibility 0.8s ease;
            overflow: hidden;
            color: white;
        }

        #page-loader.fade-out {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        /* Animated Clouds / Particles */
        .loader-cloud {
            position: absolute;
            background: url('../images/cloud.png') no-repeat center center;
            background-size: contain;
            opacity: 0.15;
            z-index: -1;
            animation: cloudFloat 30s linear infinite;
        }
        .lc-1 { top: 15%; left: -100px; width: 250px; height: 150px; }
        .lc-2 { top: 60%; right: -150px; width: 350px; height: 200px; animation-duration: 40s; animation-direction: reverse; }
        .lc-3 { top: 30%; left: 60%; width: 200px; height: 100px; animation-duration: 25s; }

        @keyframes cloudFloat {
            0% { transform: translateX(0); }
            100% { transform: translateX(100vw); }
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
            opacity: 0.8;
        }

        /* Flight Path & Plane */
        .flight-path-container {
            position: relative;
            width: 450px;
            height: 120px;
            margin-bottom: 20px;
        }

        .flight-path-svg {
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .dashed-path {
            fill: none;
            stroke: rgba(255, 255, 255, 0.4);
            stroke-width: 2.5;
            stroke-dasharray: 8 8;
        }

        .path-pin {
            fill: white;
        }

        .animated-plane {
            position: absolute;
            left: 0;
            top: 0;
            width: 32px;
            height: 32px;
            color: white;
            font-size: 2.2rem;
            transform-origin: center;
            transform: translate(-16px, -16px);
            offset-path: path("M 20 100 Q 225 -40 430 100");
            animation: flyPlane 4s ease-in-out infinite;
        }

        @keyframes flyPlane {
            0% { offset-distance: 0%; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { offset-distance: 100%; opacity: 0; }
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
            text-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .loader-text-area p {
            display: none;
        }

        /* Glassmorphism Progress Bar */
        .loader-progress-container {
            width: 100%;
            max-width: 600px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 40px;
            height: 65px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: visible;
            display: flex;
            align-items: center;
            padding: 6px;
            margin-bottom: 25px;
            animation: slideUp 1.2s ease-out forwards;
        }

        .loader-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #60efff 0%, #0061ff 100%);
            border-radius: 35px;
            width: 0%;
            position: relative;
            transition: width 0.1s linear;
            box-shadow: 0 0 20px rgba(96, 239, 255, 0.5);
            overflow: hidden;
        }
        
        .loader-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.7), transparent);
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            100% { left: 200%; }
        }

        .loader-percent {
            position: absolute;
            right: 30px;
            font-weight: 800;
            font-size: 1.3rem;
            color: white;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
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
            from { transform: translateY(-40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 650px) {
            .loader-steps { flex-direction: column; gap: 15px; text-align: center; align-items: center; height: auto; }
            .flight-path-container { width: 300px; height: 90px; }
            .animated-plane { offset-path: path("M 20 80 Q 150 -20 280 80"); }
            .loader-text-area h1 { font-size: 1.7rem; }
            .loader-progress-container { max-width: 90%; }
            .loader-progress-container, .search-mockup-pill { max-width: 90%; }
            .step-icon { width: 45px; height: 45px; font-size: 1.2rem; }
            .loader-step.active { font-size: 1rem; }
        }

        /* Navbar entrance */
        .navbar {
            animation: navSlideDown 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
            animation-delay: 0.1s;
        }

        @keyframes navSlideDown {
            from {
                transform: translateY(-80px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Hero entrance */
        .flights-hero-container {
            animation: heroFadeUp 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
            animation-delay: 0.35s;
        }

        @keyframes heroFadeUp {
            from {
                transform: translateY(40px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Hero title elements stagger */
        .flight-badge {
            animation: badgePop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            animation-delay: 0.65s;
        }

        @keyframes badgePop {
            from {
                transform: scale(0.5) rotate(-10deg);
                opacity: 0;
            }

            to {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .hero-title-area h1 {
            animation: slideInLeft 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
            animation-delay: 0.75s;
        }

        .hero-description {
            animation: slideInLeft 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
            animation-delay: 0.9s;
        }

        @keyframes slideInLeft {
            from {
                transform: translateX(-30px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Partners section entrance */
        .partners-section {
            animation: fadeUp 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
            animation-delay: 1s;
        }

        .partner-card {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease, border-color 0.3s, box-shadow 0.3s;
        }

        .partner-card.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Service cards scroll-triggered animation */
        .service-card {
            opacity: 0;
            transform: translateY(50px);
            transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                transform 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease !important;
        }

        .service-card.animated {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }

        /* Help CTA entrance */
        .help-cta {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }

        .help-cta.animated {
            opacity: 1;
            transform: translateY(0);
        }

        @keyframes fadeUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Floating clouds in hero */
        .hero-cloud {
            position: absolute;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 999px;
            pointer-events: none;
            z-index: 1;
        }

        .hero-cloud-1 {
            width: 120px;
            height: 40px;
            top: 20%;
            left: 10%;
            animation: cloudFloat 8s ease-in-out infinite;
        }

        .hero-cloud-2 {
            width: 80px;
            height: 28px;
            top: 50%;
            left: 5%;
            animation: cloudFloat 12s ease-in-out infinite reverse;
        }

        @keyframes cloudFloat {

            0%,
            100% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(18px);
            }
        }

        /* ==========================================
           END ENTRANCE ANIMATIONS
        ========================================== */

        /* Airline Partners Branding */
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
            color: #002147;
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
        }

        @media (max-width: 768px) {

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

</head>

<body>

    <!-- ✈️ Modern Flight Loading Experience -->
    <div id="page-loader">
        <div class="loader-cloud lc-1"></div>
        <div class="loader-cloud lc-2"></div>
        <div class="loader-cloud lc-3"></div>

        <div class="loader-logo-area">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo">
            <style>
                .loader-dynamic-logo { height: 100px !important; width: auto !important; margin-left: 10px; }
                @media (max-width: 768px) { .loader-dynamic-logo { height: 60px !important; } }
                @media (max-width: 480px) { .loader-dynamic-logo { height: 45px !important; margin-left: 5px; } }
            </style>
            <img src="../images/Localista (1).png" alt="Localista" class="loader-dynamic-logo">
        </div>

        <div class="flight-path-container">
            <svg class="flight-path-svg" viewBox="0 0 450 120">
                <path class="dashed-path" d="M 20 100 Q 225 -40 430 100" />
                <circle class="path-pin" cx="20" cy="100" r="5" />
                <circle class="path-pin" cx="430" cy="100" r="5" />
                <!-- Concentric rings for pins -->
                <circle cx="20" cy="100" r="14" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="2" />
                <circle cx="430" cy="100" r="14" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="2" />
            </svg>
            <div class="animated-plane"><i class="fas fa-plane"></i></div>
        </div>

        <div class="loader-text-area">
            <h1 id="loaderTitle">Searching Flights...</h1>
        </div>

        <div class="loader-progress-container">
            <div class="loader-progress-fill" id="loaderBarFill"></div>
            <div class="loader-percent" id="loaderPercent">0%</div>
        </div>

        <div class="loader-steps">
            <div class="loader-step active" id="lStep1">
                <div class="step-icon"><i class="fas fa-plane-departure"></i></div>
                <span>Searching Flights</span>
            </div>
            <div class="loader-step" id="lStep2">
                <div class="step-icon"><i class="fas fa-chart-line"></i></div>
                <span>Comparing Prices</span>
            </div>
            <div class="loader-step" id="lStep3">
                <div class="step-icon"><i class="fas fa-lock"></i></div>
                <span>Securing Options</span>
            </div>
            <div class="loader-step" id="lStep4">
                <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                <span>Finalizing Results</span>
            </div>
        </div>

        <div class="loader-footer">
            Finding your perfect flight...
        </div>
    </div>

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

    <div class="flights-hero-container">
        <section class="flights-hero">
            <!-- Floating ambient clouds -->
            <div class="hero-cloud hero-cloud-1"></div>
            <div class="hero-cloud hero-cloud-2"></div>
            <div class="hero-content" style="position: relative; z-index: 3;">
                <div class="hero-title-area">
                    <div class="flight-badge">
                        <i class="fas fa-plane-departure"></i>
                    </div>
                    <h1>Flight Bookings</h1>
                </div>

                <p class="hero-description">
                    Expert flight booking services for hassle-free travel.
                    Let us handle the details, you focus on the journey.
                </p>
            </div>
        </section>
    </div>

    <div class="service-content">
        <!-- Featured Deal Removed as requested -->

        <!-- Airline Partners -->
        <div class="partners-section">
            <div class="partners-title">
                <i class="fas fa-plane" style="font-size: 1.2rem; opacity: 0.5;"></i>
                Our Airline Partners
                <i class="fas fa-plane" style="font-size: 1.2rem; opacity: 0.5;"></i>
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
                    <img src="https://www.google.com/s2/favicons?domain=philippineairlines.com&sz=128"
                        alt="Philippine Airlines">
                    Philippine Airlines
                </div>
                <div class="partner-card">
                    <img src="https://www.google.com/s2/favicons?domain=cebupacificair.com&sz=128" alt="Cebu Pacific">
                    Cebu Pacific
                </div>
                <div class="partner-card">
                    <img src="https://www.google.com/s2/favicons?domain=airasia.com&sz=128" alt="AirAsia">
                    AirAsia
                </div>
                <div class="partner-card">
                    <img src="https://www.google.com/s2/favicons?domain=singaporeair.com&sz=128"
                        alt="Singapore Airlines">
                    Singapore Airlines
                </div>
            </div>
        </div>





        <div class="service-grid">
            <?php if (empty($db_flights)): ?>
                <!-- Default placeholders if DB is empty -->
                <div class="service-card">
                    <div class="service-image-container">
                        <div class="card-badge">Featured Deal</div>
                        <img src="../images/flights-hero.jpg" alt="Domestic Flights">
                    </div>
                    <div class="service-card-body">
                        <h3>Manila to Da Nang – Central Vietnam Gateway</h3>
                        <p>No detailed information available for this package.</p>

                        <div class="card-info-dashboard">
                            <div class="dashboard-item">
                                <div class="dashboard-icon"><i class="fas fa-couch"></i></div>
                                <div class="dashboard-text">
                                    <span class="dashboard-label">20/IN</span>
                                    <span class="dashboard-value">Available</span>
                                </div>
                            </div>
                            <div class="dashboard-divider"></div>
                            <div class="dashboard-item">
                                <div class="dashboard-icon"><i class="fas fa-calendar-alt"></i></div>
                                <div class="dashboard-text">
                                    <span class="dashboard-label">Starting from</span>
                                    <span class="dashboard-value">₱ 1,299</span>
                                </div>
                            </div>
                        </div>

                        <button class="card-action-btn" onclick="showFlightBooking('Domestic Flights', 1299, 'One-way')">
                            View Details <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div class="service-card">
                    <div class="service-image-container">
                        <div class="card-badge">International</div>
                        <img src="../images/international-flights.jpg" alt="International Flights">
                    </div>
                    <div class="service-card-body">
                        <h3>Global Exploration – Japan & Korea Specials</h3>
                        <p>Explore Asia and beyond. Unbeatable rates for Japan, Korea, Thailand, and Singapore.</p>

                        <div class="card-info-dashboard">
                            <div class="dashboard-item">
                                <div class="dashboard-icon"><i class="fas fa-couch"></i></div>
                                <div class="dashboard-text">
                                    <span class="dashboard-label">30/IN</span>
                                    <span class="dashboard-value">Limited Seats</span>
                                </div>
                            </div>
                            <div class="dashboard-divider"></div>
                            <div class="dashboard-item">
                                <div class="dashboard-icon"><i class="fas fa-calendar-alt"></i></div>
                                <div class="dashboard-text">
                                    <span class="dashboard-label">Starting from</span>
                                    <span class="dashboard-value">₱ 5,999</span>
                                </div>
                            </div>
                        </div>

                        <button class="card-action-btn"
                            onclick="showFlightBooking('International Flights', 5999, 'Round-trip')">
                            View Details <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($db_flights as $flight): ?>
                    <div class="service-card">
                        <div class="service-image-container">
                            <div class="card-badge"><?= htmlspecialchars($flight['service_type'] ?: 'Flight') ?></div>
                            <?php if ($flight['featured_image']): ?>
                                <img src="../<?= htmlspecialchars($flight['featured_image']) ?>"
                                    alt="<?= htmlspecialchars($flight['title']) ?>">
                            <?php else: ?>
                                <img src="../images/flights-hero.jpg" alt="Flight">
                            <?php endif; ?>
                        </div>

                        <div class="service-card-body">
                            <h3><?= htmlspecialchars($flight['title']) ?></h3>
                            <p><?= htmlspecialchars($flight['description']) ?></p>

                            <div class="card-info-dashboard">
                                <div class="dashboard-item">
                                    <div class="dashboard-icon"><i class="fas fa-couch"></i></div>
                                    <div class="dashboard-text">
                                        <span class="dashboard-label">Available</span>
                                        <span
                                            class="dashboard-value"><?= htmlspecialchars($flight['duration'] ?: 'Booking Open') ?></span>
                                    </div>
                                </div>
                                <div class="dashboard-divider"></div>
                                <div class="dashboard-item">
                                    <div class="dashboard-icon"><i class="fas fa-calendar-alt"></i></div>
                                    <div class="dashboard-text">
                                        <span class="dashboard-label">Starting from</span>
                                        <span class="dashboard-value">
                                            <span
                                                class="currency-symbol"><?= htmlspecialchars($flight['currency'] ?: '₱') ?></span>
                                            <?= number_format($flight['price']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button class="card-action-btn" onclick="viewServiceDetails(<?= $flight['id'] ?>)">
                                View Details <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="back-button-container" style="background: white; padding: 60px 0;">
        <button class="back-button" onclick="window.location.href='../index.php'">
            <i class="fas fa-arrow-left"></i> Back to Home
        </button>
    </div>




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
            const fullName = document.getElementById('fullName')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const departureDate = document.getElementById('departureDate')?.value;
            const fromCity = document.getElementById('fromCity')?.value.trim();
            const toCity = document.getElementById('toCity')?.value.trim();
            const passengers = document.getElementById('passengers')?.value;

            if (!fullName) errors.push('Full Name is required');

            // Use auto-detected email
            const email = window.currentUserEmail || '';
            if (!email) errors.push('Your account email could not be detected. Please log in again.');
            if (!phone) errors.push('Phone number is required');
            if (!departureDate) errors.push('Departure Date is required');
            if (!fromCity) errors.push('Departure City is required');
            if (!toCity) errors.push('Destination is required');
            if (!passengers || passengers < 1) errors.push('At least 1 traveler is required');

            document.querySelectorAll('.form-group input, .form-group select').forEach(f => f.classList.remove('error'));
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
            const email = window.currentUserEmail || '';
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
                    if (document.getElementById('email')) document.getElementById('email').value = flightBookingData.email || '';
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
                        document.getElementById('step4Content').innerHTML = `<div class="success-message"><i class="fas fa-check-circle"></i><h2>✈️ Booking Confirmed!</h2><p>Your flight booking has been confirmed and saved.</p><div class="booking-number">Ticket Reference: ${bookingNumber}</div><div class="details-card"><h4>📋 Ticket Details:</h4><p><strong>Route:</strong> ${currentFlight.title}</p><p><strong>Departure:</strong> ${new Date(flightBookingData.departureDate).toLocaleDateString()}</p><p><strong>Travelers:</strong> ${flightBookingData.passengers}</p><p><strong>Class:</strong> ${flightBookingData.classLabel}</p><p><strong>Total Fare:</strong> <span style="color:#ff9800;">₱${formatNumber(flightBookingData.total)}</span></p><p><strong>Payment Status:</strong> <span style="color:#28a745;">Confirmed</span></p><p><strong>Booked By:</strong> ${escapeHtml(flightBookingData.fullName)}</p></div><div class="instruction-note"><i class="fas fa-info-circle"></i> Your e-ticket will be sent to your email (${flightBookingData.email}) within 2 hours.</div><div class="action-buttons"><button class="submit-booking-btn btn-primary" onclick="closeFlightBookingModal(); location.reload();"><i class="fas fa-plus"></i> Book Another Flight</button><button class="submit-booking-btn btn-secondary" onclick="closeFlightBookingModal()"><i class="fas fa-times"></i> Close</button></div></div>`;
                        updateFlightSteps(4);
                    } else {
                        alert('Error saving booking: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Connection error. Please try again.');
                });
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
    <script src="../js/auth-menu.js"></script>
    <script src="../js/main.js"></script>

    <!-- Details Modal -->
    <div id="serviceDetailsModal" class="details-modal">
        <div id="serviceDetailsContent" class="details-modal-content">
            <!-- Dynamic Content -->
        </div>
    </div>

    <script>
        function viewServiceDetails(id) {
            Swal.fire({ title: 'Loading...', didOpen: () => { Swal.showLoading(); } });
            fetch(`../api/get-service-details.php?id=${id}`)
                .then(response => response.json())
                .then(result => {
                    Swal.close();
                    if (result.success) {
                        renderServiceDetails(result.data);
                        document.getElementById('serviceDetailsModal').classList.add('active');
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    alert('Connection error');
                });
        }

        function getHighlightIcon(text) {
            text = text.toLowerCase();
            if (text.includes('hotel') || text.includes('accommodation')) return 'fa-hotel';
            if (text.includes('breakfast') || text.includes('meal') || text.includes('dinner')) return 'fa-utensils';
            if (text.includes('airport') || text.includes('transfer')) return 'fa-shuttle-van';
            if (text.includes('tour') || text.includes('sightseeing')) return 'fa-camera-retro';
            if (text.includes('flight') || text.includes('airline')) return 'fa-plane';
            if (text.includes('guide')) return 'fa-user-tie';
            if (text.includes('visa')) return 'fa-passport';
            return 'fa-check-circle';
        }

        function renderServiceDetails(data) {
            const price = parseFloat(data.price);
            const container = document.getElementById('serviceDetailsContent');

            // Handle Gallery Images
            let gallery = [];
            try {
                gallery = data.image_gallery ? JSON.parse(data.image_gallery) : [];
            } catch (e) { gallery = []; }

            const featured_img = data.featured_image ? '../' + data.featured_image : '../images/flights-hero.jpg';
            const sidebar_imgs = gallery.length > 0 ? gallery : [featured_img, featured_img, featured_img, featured_img];

            // Content processing
            const highlightItems = data.highlights ? data.highlights.split('\n').filter(t => t.trim()) : [];
            const inclusions = data.inclusions ? data.inclusions.split('\n').filter(t => t.trim()) : [];
            const exclusions = data.exclusions ? data.exclusions.split('\n').filter(t => t.trim()) : [];

            container.innerHTML = `
                <!-- Close Button -->
                <button class="modal-close" onclick="closeServiceDetails()" style="position:absolute; top:25px; right:25px; background:rgba(0,0,0,0.6); color:white; border:none; width:45px; height:45px; border-radius:50%; cursor:pointer; font-size:1.2rem; z-index:1000; backdrop-filter:blur(10px); display:flex; align-items:center; justify-content:center; transition: 0.3s;"><i class="fas fa-times"></i></button>

                    <!-- 1. Hero Header -->
                    <div class="modal-hero">
                    <img src="${featured_img}" alt="${data.title}">
                    <div class="modal-hero-overlay">
                        <h2>${data.title}</h2>
                        <div class="modal-meta">
                            <span><i class="fas fa-clock"></i> ${data.duration || '2D/1N'}</span>
                            <span><i class="fas fa-map-marker-alt"></i> ${data.location || 'Multiple Destinations'}</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Navigation Tabs -->
                <div class="modal-tabs">
                    <button class="tab-btn active" onclick="switchDetailTab('overview', this)">Overview</button>
                    <button class="tab-btn" onclick="switchDetailTab('itinerary', this)">Itinerary</button>
                    <button class="tab-btn" onclick="switchDetailTab('gallery', this)">Gallery</button>
                    <button class="tab-btn" onclick="switchDetailTab('policies', this)">Policies</button>
                </div>

                <!-- 3. Tab Panes -->
                <div class="tab-content">
                    
                    <!-- Overview Pane -->
                    <div id="pane-overview" class="tab-pane active">
                        <div class="overview-grid">
                            <div class="overview-main">
                                <div class="overview-section">
                                    <h4>Service Description</h4>
                                    <div class="overview-text">
                                        ${(data.full_description || data.description || 'Experience the beauty of travel with our exclusive package. Enjoy world-class service and explore breathtaking landscapes.').replace(/\n/g, '<br>')}
                                    </div>
                                </div>
                                
                                <div class="overview-section">
                                    <h4>What's Included</h4>
                                    <div class="inclusions-grid">
                                        ${inclusions.map(inc => `
                                            <div class="inclusion-item">
                                                <i class="fas fa-check-circle"></i> ${inc}
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="overview-sidebar">
                                <div class="quick-info-box">
                                    <h5>Quick Info</h5>
                                    <div class="quick-info-list">
                                        <div class="quick-info-item">
                                            <span class="quick-info-label">Code:</span>
                                            <span class="quick-info-value">${data.service_code || 'FD-101'}</span>
                                        </div>
                                        <div class="quick-info-item">
                                            <span class="quick-info-label">Duration:</span>
                                            <span class="quick-info-value">${data.duration || '2 Days'}</span>
                                        </div>
                                        <div class="quick-info-item">
                                            <span class="quick-info-label">Confirmation:</span>
                                            <span class="quick-info-value" style="color:#10b981;">Instant</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>

                    <!-- Itinerary Pane -->
                    <div id="pane-itinerary" class="tab-pane">
                        <div class="overview-section">
                            <h4>Suggested Itinerary</h4>
                            <p style="color:#64748b;">A perfectly curated journey for your trip.</p>
                            <div class="itinerary-list">
                                <div class="itinerary-item">
                                    <div class="itinerary-number">1</div>
                                    <div class="itinerary-info">
                                        <h5>Arrival & Check-in</h5>
                                        <p>Transfer from airport to hotel and enjoy a welcome dinner.</p>
                                    </div>
                                </div>
                                <div class="itinerary-item">
                                    <div class="itinerary-number">2</div>
                                    <div class="itinerary-info">
                                        <h5>Full Day City Tour</h5>
                                        <p>Explore the most iconic landmarks and hidden gems of the city.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gallery Pane -->
                    <div id="pane-gallery" class="tab-pane">
                        <div class="gallery-header">
                            <h3>Photo Gallery</h3>
                            <div class="photo-count-badge"><i class="fas fa-camera"></i> ${gallery.length || 5} Photos</div>
                        </div>
                        
                        
                        <div class="premium-gallery-grid">
                            <div class="gallery-item-large" onclick="window.open('../${sidebar_imgs[0]}', '_blank')">
                                <img src="../${sidebar_imgs[0]}" alt="Gallery 1">
                                <div class="img-overlay-text">Relax and unwind in comfort</div>
                            </div>
                            <div class="gallery-item-small" onclick="window.open('../${sidebar_imgs[1]}', '_blank')">
                                <img src="../${sidebar_imgs[1] || featured_img}" alt="Gallery 2">
                            </div>
                            <div class="gallery-item-small" onclick="window.open('../${sidebar_imgs[2]}', '_blank')">
                                <img src="../${sidebar_imgs[2] || featured_img}" alt="Gallery 3">
                            </div>
                            <div class="gallery-item-small" onclick="window.open('../${sidebar_imgs[3]}', '_blank')">
                                <img src="../${sidebar_imgs[3] || featured_img}" alt="Gallery 4">
                            </div>
                            <div class="gallery-item-small" onclick="window.open('../${sidebar_imgs[0]}', '_blank')">
                                <img src="../${sidebar_imgs[0]}" alt="Gallery 5">
                            </div>
                        </div>
                        
                        <button class="view-all-photos-bar">
                            <i class="fas fa-th"></i> View all photos
                        </button>
                    </div>

                    <!-- Policies Pane -->
                    <div id="pane-policies" class="tab-pane">
                        <div class="overview-section" style="margin-bottom:30px;">
                            <h4>Cancellation Policy</h4>
                            <p style="font-size:0.95rem; line-height:1.7; color:#475569;">${data.cancellation_policy || 'Free cancellation up to 48 hours before departure. After that, 50% charge applies.'}</p>
                        </div>
                        <div class="overview-section">
                            <h4>Travel Requirements</h4>
                            <p style="font-size:0.95rem; line-height:1.7; color:#475569;">${data.travel_requirements || 'Valid passport (at least 6 months), Visa if applicable, and Travel Insurance.'}</p>
                        </div>
                    </div>

                </div>

                <!-- 4. Sticky Bottom Bar -->
                <div class="modal-sticky-footer">
                    <div class="footer-highlights">
                        <div class="footer-highlight-item">
                            <i class="fas fa-hotel"></i>
                            <span>4-Star Hotel</span>
                        </div>
                        <div class="footer-highlight-item">
                            <i class="fas fa-utensils"></i>
                            <span>Daily Breakfast</span>
                        </div>
                    </div>
                    
                    <div class="footer-price-area">
                        <div class="price-label-group">
                            <span>Price starting from</span>
                            <span>₱${formatNumber(price)}</span>
                        </div>
                        <button class="book-now-modal-btn" onclick="closeServiceDetails(); requireLogin('showFlightBooking', '${data.title.replace(/'/g, "\\'")}', ${price}, '${data.duration ? data.duration.replace(/'/g, "\\'") : ''}')">
                            Book Now <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        function switchDetailTab(paneId, btn) {
            // Update buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');

            // Update panes
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            document.getElementById('pane-' + paneId).classList.add('active');
        }

        function closeServiceDetails() {
            document.getElementById('serviceDetailsModal').classList.remove('active');
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('en-PH').format(num);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
    <script>
        /* ==========================================
           ENTRANCE ANIMATION CONTROLLER
        ========================================== */
        (function () {
            // Modern Flight Loading Experience
            window.addEventListener('load', function () {
                const loader = document.getElementById('page-loader');
                if (!loader) return;
                
                const percentEl = document.getElementById('loaderPercent');
                const barFill = document.getElementById('loaderBarFill');
                const titleEl = document.getElementById('loaderTitle');
                
                let progress = 0;
                // Total duration = 2000ms for faster loading
                const duration = 2000; 
                const intervalTime = 20;
                const increment = (100 / (duration / intervalTime));
                
                const urlParams = new URLSearchParams(window.location.search);
                const query = urlParams.get('search') || urlParams.get('destination') || urlParams.get('q');
                const hasRoute = query && query.trim().length > 0;
                
                const loadingMessages = [
                    { p: 0, text: 'Searching Flights', icon: 'fa-plane-departure', title: 'Searching Flights...', destText: (hasRoute ? `Scanning options for ${query}` : 'Scanning global flight options') },
                    { p: 25, text: 'Comparing Prices', icon: 'fa-chart-line', title: 'Comparing Prices...', destText: (hasRoute ? `Finding best prices to ${query}` : 'Finding best deals') },
                    { p: 55, text: 'Securing Options', icon: 'fa-lock', title: 'Securing Options...', destText: (hasRoute ? `Confirming seats for ${query}` : 'Securing availability') },
                    { p: 80, text: 'Finalizing Results', icon: 'fa-check-circle', title: 'Finalizing Results...', destText: (hasRoute ? `Ready for ${query}` : 'All set') }
                ];
                
                const timer = setInterval(() => {
                    progress += increment;
                    if (progress >= 100) progress = 100;
                    
                    // Update Progress Bar
                    if (percentEl) percentEl.textContent = Math.floor(progress) + '%';
                    if (barFill) barFill.style.width = progress + '%';
                    
                    // Update Steps & Text
                    let currentStage = 1;
                    if (progress >= 80) currentStage = 4;
                    else if (progress >= 55) currentStage = 3;
                    else if (progress >= 25) currentStage = 2;
                    
                    const stageData = loadingMessages[currentStage - 1];
                    if (titleEl && titleEl.textContent !== stageData.title) {
                        if (titleEl) titleEl.textContent = stageData.title;
                        
                        // Update only the active step (single dynamic step display)
                        for (let i = 1; i <= 4; i++) {
                            const stepEl = document.getElementById('lStep' + i);
                            if (!stepEl) continue;
                            
                            if (i === currentStage) {
                                stepEl.className = 'loader-step active';
                                const iconEl = stepEl.querySelector('.step-icon i');
                                if (iconEl) iconEl.className = 'fas ' + stageData.icon;
                                const textEl = stepEl.querySelector('span');
                                if (textEl) textEl.textContent = stageData.text;
                            } else {
                                stepEl.className = 'loader-step';
                            }
                        }
                    }
                    
                    if (progress >= 100) {
                        clearInterval(timer);
                        setTimeout(() => {
                            // Trigger animations for hero and service cards
                            const heroContainer = document.querySelector('.flights-hero-container');
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
                            
                            loader.classList.add('fade-out');
                            setTimeout(() => loader.remove(), 800);
                        }, 500);
                    }
                }, intervalTime);
            });

            // 2. Stagger-animate partner cards shortly after page load
            window.addEventListener('load', function () {
                setTimeout(function () {
                    var partners = document.querySelectorAll('.partner-card');
                    partners.forEach(function (card, i) {
                        setTimeout(function () {
                            card.classList.add('animated');
                        }, 1100 + i * 100);
                    });
                }, 200);
            });

            // 3. IntersectionObserver for service cards & help CTA
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        // Stagger sibling cards
                        var el = entry.target;
                        if (el.classList.contains('service-card')) {
                            var siblings = Array.from(el.parentElement.querySelectorAll('.service-card'));
                            var idx = siblings.indexOf(el);
                            setTimeout(function () {
                                el.classList.add('animated');
                            }, idx * 120);
                        } else {
                            el.classList.add('animated');
                        }
                        observer.unobserve(el);
                    }
                });
            }, { threshold: 0.12 });

            // Observe cards and help CTA
            document.querySelectorAll('.service-card, .help-cta').forEach(function (el) {
                observer.observe(el);
            });
        })();
        /* ==========================================
           END ENTRANCE ANIMATION CONTROLLER
        ========================================== */
    </script>
    <script>
        function filterFlights(query) {
            const cards = document.querySelectorAll('.service-card');
            const lowerQuery = query.toLowerCase();

            cards.forEach(card => {
                const title = card.querySelector('h3').innerText.toLowerCase();
                const desc = card.querySelector('p').innerText.toLowerCase();

                if (title.includes(lowerQuery) || desc.includes(lowerQuery)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
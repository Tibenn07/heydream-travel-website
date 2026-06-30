<?php
// ========================================
// FILE: buttons/about.php
// DESCRIPTION: About HeyDream Travel & Tours
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
    <title>HeyDream - About Us</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

        .about-hero {
            background: linear-gradient(180deg, #4facfe 0%, #00f2fe 40%, #ffffff 100%);
            padding: 120px 5% 80px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .about-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
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

        .about-hero h1 {
            font-size: 3rem;
            margin-bottom: 15px;
            font-weight: 800;
            position: relative;
            z-index: 1;
            color: #ffffff !important;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }


        .about-hero h1 i {
            margin-right: 15px;
            animation: heartBeat 1.5s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes heartBeat {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .about-hero p {
            font-size: 1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.95;
            line-height: 1.6;
            position: relative;
            z-index: 1;
            color: #003580 !important; /* Dark blue for the subtitle */
            font-weight: 600;
        }

        .about-content {
            padding: 60px 5%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .story-section {
            background: white;
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 50px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .story-section:hover {
            transform: translateY(-5px);
        }

        .story-section h2 {
            color: #003580;
            margin-bottom: 20px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .story-section h2 i {
            color: #ff9800;
            margin-right: 10px;
        }

        .story-section p {
            color: #666;
            line-height: 1.8;
            max-width: 800px;
            margin: 0 auto;
            font-size: 1rem;
        }

        /* Mission & Vision - 2 columns always */
        .mission-vision {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 50px;
        }

        .mv-card {
            background: white;
            border-radius: 24px;
            padding: 35px 30px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .mv-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .mv-card:hover::before {
            transform: scaleX(1);
        }

        .mv-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 50px rgba(0, 53, 128, 0.15);
        }

        .mv-card i {
            font-size: 3rem;
            color: #ff9800;
            margin-bottom: 20px;
        }

        .mv-card h3 {
            color: #003580;
            margin-bottom: 15px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .mv-card p {
            color: #666;
            line-height: 1.6;
            font-size: 0.9rem;
        }

        /* Stats Grid - 4 columns on desktop, 2 on tablet, 2 on mobile */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin: 50px 0;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(0, 53, 128, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ff9800;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #666;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Values List - 4 columns on desktop, 2 on tablet/mobile */
        .values-section {
            text-align: center;
            margin: 50px 0;
        }

        .values-section h2 {
            color: #003580;
            margin-bottom: 30px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .values-section h2 i {
            color: #ff9800;
            margin-right: 10px;
        }

        .values-list {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .value-item {
            background: white;
            border-radius: 20px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .value-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(0, 53, 128, 0.1);
        }

        .value-item i {
            font-size: 2rem;
            color: #ff9800;
            margin-bottom: 15px;
        }

        .value-item h4 {
            color: #003580;
            margin-bottom: 8px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .value-item p {
            color: #666;
            font-size: 0.8rem;
        }

        /* Team Grid - 4 columns on desktop, 2 on tablet/mobile */
        .team-section {
            text-align: center;
            margin: 50px 0;
        }

        .team-section h2 {
            color: #003580;
            margin-bottom: 30px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .team-section h2 i {
            color: #ff9800;
            margin-right: 10px;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .team-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .team-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 40px rgba(0, 53, 128, 0.15);
        }

        .team-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #003580, #1a4b8c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            transition: all 0.3s ease;
        }

        .team-card:hover .team-avatar {
            transform: scale(1.05);
        }

        .team-avatar i {
            font-size: 2.5rem;
            color: white;
        }

        .team-card h4 {
            color: #003580;
            margin-bottom: 5px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .team-card p {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .team-card .role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .back-button-container {
            text-align: center;
            padding: 30px 15px 60px;
        }

        .back-button {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 53, 128, 0.3);
            min-width: 220px;
            position: relative;
            overflow: hidden;
        }

        .back-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .back-button:hover::before {
            width: 300px;
            height: 300px;
        }

        .back-button:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255, 152, 0, 0.4);
        }

        .back-button i {
            transition: transform 0.3s ease;
        }

        .back-button:hover i {
            transform: translateX(-5px);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }

            .values-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .team-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .about-hero {
                padding: 100px 5% 60px;
            }

            .about-hero h1 {
                font-size: 2rem;
            }

            .about-hero p {
                font-size: 0.85rem;
            }

            .about-content {
                padding: 40px 4%;
            }

            .story-section {
                padding: 25px;
            }

            .story-section h2 {
                font-size: 1.5rem;
            }

            .story-section p {
                font-size: 0.9rem;
            }

            .mission-vision {
                gap: 20px;
            }

            .mv-card {
                padding: 25px 20px;
            }

            .mv-card i {
                font-size: 2.5rem;
            }

            .mv-card h3 {
                font-size: 1.3rem;
            }

            .stats-grid {
                gap: 15px;
            }

            .stat-number {
                font-size: 2rem;
            }

            .values-list {
                gap: 12px;
            }

            .value-item {
                padding: 20px 15px;
            }

            .team-grid {
                gap: 15px;
            }

            .team-card {
                padding: 20px 15px;
            }

            .team-avatar {
                width: 80px;
                height: 80px;
            }

            .team-avatar i {
                font-size: 2rem;
            }

            .back-button {
                padding: 12px 30px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 550px) {
            .mission-vision {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-card {
                padding: 20px 15px;
            }

            .stat-number {
                font-size: 1.6rem;
            }

            .values-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .value-item {
                padding: 15px 10px;
            }

            .value-item i {
                font-size: 1.5rem;
            }

            .value-item h4 {
                font-size: 0.9rem;
            }

            .team-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .team-card {
                padding: 15px 12px;
            }

            .team-avatar {
                width: 65px;
                height: 65px;
            }

            .team-avatar i {
                font-size: 1.6rem;
            }

            .team-card h4 {
                font-size: 0.9rem;
            }

            .team-card p {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 400px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .values-list {
                grid-template-columns: 1fr;
            }

            .team-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ══════════════════════════════
           PAGE ENTRANCE ANIMATIONS
        ══════════════════════════════ */

        /* Page preloader overlay */
        #page-preloader {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #003580 0%, #4facfe 100%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            animation: preloaderFadeOut 0.6s ease 0.9s forwards;
            pointer-events: none;
        }

        #page-preloader .loader-plane {
            font-size: 3rem;
            color: #fff;
            animation: loaderPlane 0.9s ease-in-out infinite alternate;
        }

        #page-preloader .loader-dots {
            display: flex;
            gap: 10px;
        }

        #page-preloader .loader-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.8);
            animation: dotBounce 0.6s ease-in-out infinite alternate;
        }

        #page-preloader .loader-dots span:nth-child(2) { animation-delay: 0.15s; }
        #page-preloader .loader-dots span:nth-child(3) { animation-delay: 0.3s; }

        @keyframes loaderPlane {
            from { transform: translateY(-8px) rotate(-5deg); }
            to   { transform: translateY(8px) rotate(5deg); }
        }

        @keyframes dotBounce {
            from { transform: scaleY(1); opacity: 0.5; }
            to   { transform: scaleY(1.6); opacity: 1; }
        }

        @keyframes preloaderFadeOut {
            to { opacity: 0; visibility: hidden; }
        }

        /* Body hidden until preloader done */
        body.page-loading .about-hero,
        body.page-loading .about-content,
        body.page-loading .back-button-container,
        body.page-loading .footer {
            opacity: 0;
        }

        /* Hero entrance */
        @keyframes heroSlideDown {
            from { opacity: 0; transform: translateY(-40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes heroTextUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .about-hero {
            animation: heroSlideDown 0.8s cubic-bezier(0.22, 1, 0.36, 1) 1s both;
        }

        .about-hero h1 {
            animation: heroTextUp 0.7s ease 1.2s both;
        }

        .about-hero p {
            animation: heroTextUp 0.7s ease 1.4s both;
        }

        /* Scroll-triggered entrance base state */
        .anim-fade-up {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.65s ease, transform 0.65s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .anim-fade-up.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Stagger delays for grid children */
        .anim-stagger > *:nth-child(1) { transition-delay: 0s; }
        .anim-stagger > *:nth-child(2) { transition-delay: 0.1s; }
        .anim-stagger > *:nth-child(3) { transition-delay: 0.2s; }
        .anim-stagger > *:nth-child(4) { transition-delay: 0.3s; }
        .anim-stagger > *:nth-child(5) { transition-delay: 0.4s; }
        .anim-stagger > *:nth-child(6) { transition-delay: 0.5s; }
        .anim-stagger > *:nth-child(7) { transition-delay: 0.6s; }
        .anim-stagger > *:nth-child(8) { transition-delay: 0.7s; }
        .anim-stagger > *:nth-child(9) { transition-delay: 0.8s; }

        /* Back button & footer entrance */
        .back-button-container,
        .footer {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .back-button-container.is-visible,
        .footer.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body class="page-loading">

    <!-- Page Preloader -->
    <div id="page-preloader">
        <div class="loader-plane"><i class="fas fa-book-open"></i></div>
        <div class="loader-dots">
            <span></span><span></span><span></span>
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
            <div class="sidebar-section-label">Main</div>

            <a href="../index.php" class="sidebar-nav-item active" id="nav-home">
                <i class="fas fa-home sidebar-nav-icon" style="color: #FFF59D;"></i>
                <span class="sidebar-nav-label">Home</span>
                <span class="sidebar-tooltip">Home</span>
            </a>

            <a href="../local-destination.php" class="sidebar-nav-item" id="nav-local">
                <i class="fas fa-umbrella-beach sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Local Tours</span>
                <span class="sidebar-tooltip">Local Tours</span>
            </a>

            <a href="../foreign-destinations.php" class="sidebar-nav-item" id="nav-foreign">
                <i class="fas fa-plane sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Foreign Tours</span>
                <span class="sidebar-tooltip">Foreign Tours</span>
            </a>

            <a href="../flash-deals.php" class="sidebar-nav-item" id="nav-deals">
                <i class="fas fa-bolt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Flash Deals</span>
                <span class="sidebar-tooltip">Flash Deals</span>
            </a>

            <!-- My Booking Link -->
            <button class="sidebar-nav-item" id="nav-my-booking" onclick="requireLogin('goToProfile')"
                style="border:none; text-align:left; background:transparent; width:100%;">
                <i class="fas fa-clipboard-list sidebar-nav-icon"></i>
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
                <i class="fas fa-globe sidebar-nav-icon"></i>
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
                <i class="fas fa-life-ring sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Help &amp; Support</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Help &amp; Support</span>
            </button>
            <div class="sidebar-dropdown-content" id="helpDropdown">
                <a href="../help-support.php" class="sidebar-sub-item">
                    <i class="fas fa-question-circle" style="color:#003580;font-size:0.8rem;"></i> FAQs
                </a>
                <a href="#" onclick="openSupportChat(event); return false;" class="sidebar-sub-item">
                    <i class="fas fa-headset" style="color:#003580;font-size:0.8rem;"></i> Contact Support
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

        <!-- Footer: Help + Live Chat + Call + Logout -->
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

    </div><!-- /side-panel -->




    <section class="about-hero">
        <h1><i class="fas fa-book-open"></i> About HeyDream Travel & Tours</h1>
        <p>Making Travel Easy, Affordable, and Memorable.</p>
    </section>

    <div class="about-content">
        <!-- Our Story Section -->
        <div class="story-section anim-fade-up">
            <h2><i class="fas fa-book-open"></i> About HeyDream Travel and Tours</h2>
            <p>HeyDream Travel and Tours is a modern travel agency based in the Philippines dedicated to providing
                seamless travel solutions for individuals, families, and corporate clients. Our company focuses on
                delivering reliable travel services with competitive pricing and personalized customer support.</p>
            <br>
            <p>We specialize in international and domestic travel packages, airline ticketing, visa assistance, hotel
                reservations, and customized travel planning. Our goal is to make travel planning convenient, efficient,
                and enjoyable.</p>
        </div>

        <!-- Mission & Vision (2 columns always) -->
        <div class="mission-vision anim-stagger">
            <div class="mv-card anim-fade-up">
                <i class="fas fa-bullseye"></i>
                <h3>Mission</h3>
                <p>• Provide reliable and affordable travel services.</p>
                <p>• Create personalized travel experiences for every client.</p>
                <p>• Build strong partnerships with international travel suppliers.</p>
                <p>• Maintain excellent customer service and travel support.</p>
            </div>
            <div class="mv-card anim-fade-up">
                <i class="fas fa-eye"></i>
                <h3>Vision</h3>
                <p>To become a trusted and recognized travel agency known for delivering excellent travel services and
                    unforgettable experiences worldwide.</p>
            </div>
        </div>

        <!-- Why Choose Us -->
        <div class="team-section anim-fade-up">
            <h2><i class="fas fa-award"></i> Why Choose HeyDream</h2>
            <div class="team-grid anim-stagger">
                <div class="team-card anim-fade-up">
                    <div class="team-avatar"><i class="fas fa-user-tie"></i></div>
                    <h4>Professional Support</h4>
                    <p>Professional and reliable travel support</p>
                </div>
                <div class="team-card anim-fade-up">
                    <div class="team-avatar"><i class="fas fa-tags"></i></div>
                    <h4>Affordable Packages</h4>
                    <p>Affordable international and domestic packages</p>
                </div>
                <div class="team-card anim-fade-up">
                    <div class="team-avatar"><i class="fas fa-handshake"></i></div>
                    <h4>Trusted Partners</h4>
                    <p>Trusted travel industry partners</p>
                </div>
                <div class="team-card anim-fade-up">
                    <div class="team-avatar"><i class="fas fa-map-marked-alt"></i></div>
                    <h4>Customized Planning</h4>
                    <p>Customized travel planning</p>
                </div>
            </div>
        </div>

        <!-- Travel Services -->
        <div class="values-section anim-fade-up">
            <h2><i class="fas fa-concierge-bell"></i> Our Travel Services</h2>
            <div class="values-list anim-stagger">
                <div class="value-item anim-fade-up">
                    <i class="fas fa-globe"></i>
                    <h4>International Tour Packages</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-map-marker-alt"></i>
                    <h4>Domestic Tour Packages</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-ticket-alt"></i>
                    <h4>Airline Ticket Booking</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-hotel"></i>
                    <h4>Hotel Reservations</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-passport"></i>
                    <h4>Visa Assistance</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-shuttle-van"></i>
                    <h4>Airport Transfers</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-users"></i>
                    <h4>Group Tours</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-building"></i>
                    <h4>Corporate Travel Services</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <i class="fas fa-calendar-check"></i>
                    <h4>Customized Travel Planning</h4>
                </div>
            </div>
        </div>

        <!-- Popular Destinations -->
        <div class="values-section anim-fade-up">
            <h2><i class="fas fa-star"></i> Popular Destinations</h2>
            <div class="values-list anim-stagger" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div class="value-item anim-fade-up">
                    <h4>South Korea</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <h4>Japan</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <h4>Thailand</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <h4>Singapore</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <h4>Vietnam</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <h4>Hong Kong</h4>
                </div>
                <div class="value-item anim-fade-up">
                    <h4>Philippines</h4>
                </div>
            </div>
        </div>

        <!-- Partnership -->
        <div class="story-section anim-fade-up" style="background: #e3f2fd;">
            <h2><i class="fas fa-briefcase"></i> Partnership Opportunities</h2>
            <p>HeyDream Travel and Tours welcomes partnerships with travel agencies, tour operators, airlines, hotels,
                and corporate organizations. We are open to B2B collaborations to expand travel opportunities for our
                clients and partners.</p>
        </div>
    </div>

    <div class="back-button-container">
        <button class="back-button" onclick="window.location.href='../index.php'">
            <i class="fas fa-arrow-left"></i> Back to Home
        </button>
    </div>

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

    <script src="../js/main.js"></script>
    <script src="../js/menu.js"></script>
    <script src="../js/auth-menu.js"></script>

    <script>
        // ── Remove page-loading class after preloader fades out ──
        window.addEventListener('load', function () {
            setTimeout(function () {
                document.body.classList.remove('page-loading');
            }, 1000);
        });

        // ── Intersection Observer for scroll-triggered animations ──
        const animTargets = document.querySelectorAll(
            '.anim-fade-up, .back-button-container, .footer'
        );

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target); // animate once
                }
            });
        }, { threshold: 0.12 });

        animTargets.forEach(function (el) {
            observer.observe(el);
        });
    </script>
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
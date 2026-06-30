<?php
// ========================================
// FILE: help-support.php
// DESCRIPTION: Help & Support Hub Navigation
// ========================================
require_once __DIR__ . '/config/database.php';
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
    <title>HeyDream - Help & Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
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
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f7ff 100%);
            font-family: 'Poppins', sans-serif;
        }

        .service-hero {
            background: linear-gradient(135deg, #008b8b 0%, #17a2b8 50%, #20c997 100%);
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
            background: linear-gradient(to top, #f0f8ff, transparent);
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
        }

        .service-hero h1 i {
            margin-right: 15px;
            animation: bounce 2s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .service-hero p {
            font-size: 0.95rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.95;
            line-height: 1.5;
        }

        .service-content {
            padding: 40px 5%;
            max-width: 1000px;
            margin: 0 auto;
            z-index: 10;
            position: relative;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: -50px;
            margin-bottom: 60px;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .service-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 25px 45px rgba(23, 162, 184, 0.2);
            border-color: #17a2b8;
        }

        .service-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #008b8b, #17a2b8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            overflow: hidden;
        }

        .service-icon i {
            font-size: 2rem;
            color: white;
        }

        .service-card h3 {
            color: #003580;
            margin-bottom: 10px;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .service-card p {
            color: #666;
            line-height: 1.5;
            font-size: 0.85rem;
        }

        /* FAQ Section Styles */
        .faq-section {
            max-width: 800px;
            margin: 0 auto;
            text-align: left;
            padding-top: 20px;
        }

        .faq-title {
            font-size: 1.4rem;
            color: #000;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .faq-list {
            display: flex;
            flex-direction: column;
            border-top: 1px solid #ebebeb;
        }

        .faq-item {
            border-bottom: 1px solid #ebebeb;
            transition: all 0.3s ease;
        }

        .faq-question {
            width: 100%;
            background: none;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            color: #202124;
            padding: 20px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            text-align: left;
        }

        .faq-icon {
            font-size: 1rem;
            color: #202124;
            transition: transform 0.3s ease;
        }

        .faq-answer-container {
            display: none;
        }

        .faq-item.active {
            border-bottom: 1px solid #ebebeb !important;
            background: #ffffff !important;
            margin: 10px 0 !important;
            border-radius: 12px !important;
            box-shadow: none !important;
        }

        .faq-item.active .faq-question,
        .faq-question,
        .faq-item.active .faq-question:hover,
        .faq-item.active .faq-question:focus {
            background: #ffffff !important;
            background-color: #ffffff !important;
            color: #1a1a1a !important;
            padding: 20px !important;
            font-weight: 600 !important;
            border: none !important;
            outline: none !important;
        }

        .faq-item.active .faq-icon {
            color: #1a1a1a !important;
            transform: rotate(180deg) !important;
        }

        .faq-item.active .faq-answer-container {
            display: block !important;
            background-color: #ffffff !important;
            padding: 0 20px 20px !important;
            border-radius: 0 0 12px 12px !important;
        }

        .faq-answer-container {
            display: none;
        }

        .faq-answer {
            color: #4d5156;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .faq-link {
            color: #1a0dab;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }

        .faq-link:hover {
            text-decoration: underline;
        }

        .faq-article-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #ebebeb;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .faq-article-title {
            color: #430297;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
        }

        .faq-article-title:hover {
            text-decoration: underline;
        }

        .faq-article-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #70757a;
        }

        .faq-article-meta img {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #eee;
        }

        .back-button-container {
            text-align: center;
            padding: 40px 15px;
            margin-top: 20px;
            background: #f0f8ff;
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
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left"><img src="images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='index.php'">
            <div class="company-name"><span class="line1">HeyDream Travel</span><span class="line2">and Tours</span>
            </div>
        </div>
        <div class="nav-container">
            <div class="hamburger-menu"><button class="hamburger-icon"
                    id="menuToggle"><span></span><span></span><span></span></button></div>
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

            <a href="index.php" class="sidebar-nav-item active" id="nav-home">
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

    <section class="service-hero">
        <h1><i class="fas fa-headset"></i> Help & Support</h1>
        <p>We are here to help you! Choose from the options below to get the assistance you need for a smooth and
            hassle-free travel experience.</p>
    </section>

    <div class="service-content">
        <div class="service-grid">
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-question-circle"></i></div>
                <h3>FAQ Center</h3>
                <p>Have questions about bookings, payments, or travel requirements? Browse our comprehensive help center
                    for quick answers. Kindly <a href="index.php?startchat=1"
                        style="color: #1a0dab; font-weight: 600; text-decoration: underline;">Chat with us here</a>.</p>
            </div>

            <div class="service-card">
                <div class="service-icon"><i class="fas fa-envelope"></i></div>
                <h3>Email Support</h3>
                <p>haedreamtravelconsultancy@gmail.com</p>
                <br>
                <p>Send us an email regarding your itinerary, refunds, or special requests. We typically reply within 24
                    hours.</p>
            </div>

            <div class="service-card">
                <div class="service-icon"><i class="fas fa-phone-alt"></i></div>
                <h3>Call Us</h3>
                <p>Contact Number: 09457764140</p>
                <br>
                <p>Prefer talking on the phone? Call our dedicated support line to speak with a travel consultant
                    immediately.</p>
            </div>
        </div>
    </div>

    <div class="back-button-container">
        <button class="back-button" onclick="window.location.href='index.php'"><i class="fas fa-arrow-left"></i> Back to
            Home</button>
    </div>



    <footer class="footer">
        <div class="footer-container">
            <div class="footer-logo-section">
                <div class="footer-logo">
                    <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="footer-logo-img">
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

    <script src="js/main.js"></script>
    <script src="js/menu.js"></script>
    <script src="js/auth-menu.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const faqQuestions = document.querySelectorAll('.faq-question');
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const item = question.closest('.faq-item');
                    const isActive = item.classList.contains('active');

                    document.querySelectorAll('.faq-item').forEach(faq => {
                        faq.classList.remove('active');
                    });

                    if (!isActive) {
                        item.classList.add('active');
                    }
                });
            });
        });
    </script>


</body>

</html>
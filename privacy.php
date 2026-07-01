<?php
// ========================================
// FILE: privacy.php
// DESCRIPTION: Data Privacy Policy for HeyDream Travel & Tours
// ========================================

require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Data Privacy Policy</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
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
            color: #333;
        }

        .privacy-hero {
            background: linear-gradient(135deg, #003580 0%, #1a4b8c 50%, #2c5a9e 100%);
            padding: 100px 5% 60px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .privacy-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .privacy-hero h1 {
            font-size: 2.8rem;
            margin-bottom: 15px;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }

        .privacy-hero p {
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .privacy-container {
            max-width: 1000px;
            margin: -30px auto 60px;
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 2;
        }

        .privacy-section {
            margin-bottom: 40px;
        }

        .privacy-section h2 {
            color: #003580;
            font-size: 1.6rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .privacy-section h2 i {
            color: #ff9800;
            font-size: 1.4rem;
        }

        .privacy-section p {
            color: #555;
            line-height: 1.7;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .privacy-section ul {
            margin-left: 20px;
            margin-bottom: 15px;
            color: #555;
        }

        .privacy-section ul li {
            margin-bottom: 8px;
            line-height: 1.6;
            list-style-type: disc;
        }

        .privacy-section strong {
            color: #333;
        }

        .last-updated {
            text-align: right;
            font-size: 0.9rem;
            color: #888;
            font-style: italic;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .privacy-container {
                padding: 30px 20px;
                margin-top: -20px;
                width: 95%;
            }

            .privacy-hero h1 {
                font-size: 2.2rem;
            }

            .privacy-section h2 {
                font-size: 1.4rem;
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
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
        <div class="nav-links-desktop">
            <a href="index.php" class="nav-link-item">Home</a>
            <a href="local-destination.php" class="nav-link-item">Local Tours</a>
            <a href="foreign-destinations.php" class="nav-link-item">Foreign Tours</a>
            <a href="buttons/visa.php" class="nav-link-item">Visa Assistance</a>
            <a href="buttons/about.php" class="nav-link-item">About Us</a>
        </div>
        <div class="nav-container">
            <div class="nav-auth-desktop">
                <?php if ($auth->isLoggedIn()): ?>
                    <?php $currentUser = $auth->getCurrentUser(); ?>
                    <div class="user-menu-wrapper">
                        <button class="user-profile-btn" id="userMenuBtn">
                            <i class="fas fa-user-circle"></i>
                            <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="User Account/profile.php"><i class="fas fa-user-edit"></i> My Profile</a>
                            <a href="saved.php"><i class="fas fa-heart"></i> Saved Items</a>
                            <a href="User Account/vouchers.php"><i class="fas fa-ticket-alt"></i> Vouchers</a>
                            <div class="dropdown-divider"></div>
                            <a href="User Account/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i>
                                Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="User Account/login.php" class="nav-login-btn"><i class="fas fa-user-circle"></i> Sign In</a>
                <?php endif; ?>
            </div>
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

    <div class="side-panel" id="sidePanel">
        <div class="panel-header">
            <div class="panel-logo">
                <span class="panel-logo-text">HeyDream</span>
            </div>
        </div>
        <div class="panel-body">
            <div id="auth-section"></div>
            <div class="compact-menu">
                <button class="compact-menu-btn saved-btn" onclick="window.location.href='saved.php'">
                    <span class="menu-icon"><i class="fas fa-star" style="color: #ff9800;"></i></span>
                    <span class="menu-text">Saved</span>
                </button>
                <button class="compact-menu-btn ticket-btn" onclick="window.location.href='User Account/profile.php'">
                    <span class="menu-icon"><i class="fas fa-clipboard-list" style="color: #003580;"></i></span>
                    <span class="menu-text">My Bookings</span>
                </button>
                <button class="compact-menu-btn live-chat-btn"
                    onclick="if(typeof window.toggleHeyDreamChatbot === 'function') window.toggleHeyDreamChatbot();">
                    <span class="menu-icon"><i class="fas fa-comments" style="color: #28a745;"></i></span>
                    <span class="menu-text">Live Chat</span>
                </button>
                <div class="compact-menu-item">
                    <a href="help-support.php" class="compact-menu-btn link-btn">
                        <span class="menu-icon"><i class="fas fa-life-ring" style="color: #dc3545;"></i></span>
                        <span class="menu-text">Help & Support</span>
                    </a>
                </div>
                <div class="compact-dropdown">
                    <button class="compact-menu-btn dropdown-toggle">
                        <span class="menu-icon"><i class="fas fa-globe" style="color: #17a2b8;"></i></span>
                        <span class="menu-text">Social Media</span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-content">
                        <a href="https://www.facebook.com/profile.php?id=61583752858443" target="_blank"
                            class="dropdown-item">
                            <span class="item-icon"><i class="fab fa-facebook-f"></i></span> Facebook
                        </a>
                        <a href="https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                            target="_blank" class="dropdown-item"><span class="item-icon"><i
                                    class="fab fa-instagram"></i></span>
                            Instagram</a>
                        <a href="https://x.com/HeyDreamTravel?s=20" target="_blank" class="dropdown-item"><span
                                class="item-icon"><i class="fa-brands fa-x-twitter"></i></span>
                            Twitter</a>
                        <a href="https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc"
                            target="_blank" class="dropdown-item"><span class="item-icon"><i
                                    class="fab fa-tiktok"></i></span>
                            TikTok</a>
                    </div>
                </div>
                <div class="compact-dropdown">
                    <button class="compact-menu-btn dropdown-toggle">
                        <span class="menu-icon"><i class="fas fa-calendar-alt" style="color: #ff9800;"></i></span>
                        <span class="menu-text">Booking</span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-content">
                        <a href="foreign-destinations.php" class="dropdown-item"><span class="item-icon"><i
                                    class="fas fa-plane" style="color: #17a2b8;"></i></span> Foreign
                            Tours</a>
                        <a href="local-destination.php" class="dropdown-item"><span class="item-icon"><i
                                    class="fas fa-umbrella-beach" style="color: #ff9800;"></i></span> Local
                            Tours</a>
                    </div>
                </div>
                <a href="tel:+639457764140" class="compact-menu-btn call-btn">
                    <span class="menu-icon"><i class="fas fa-phone-alt" style="color: #003580;"></i></span>
                    <span class="menu-text">Call Us</span>
                    <span class="call-number">0945 776 4140</span>
                </a>
            </div>
        </div>
    </div>

    <section class="privacy-hero">
        <h1><i class="fas fa-shield-alt"></i> Data Privacy Policy</h1>
        <p>Your privacy and trust are our top priorities at HeyDream Travel & Tours.</p>
    </section>

    <div class="privacy-container">
        <div class="last-updated">Last Updated: April 23, 2026</div>

        <div class="privacy-section">
            <h2><i class="fas fa-info-circle"></i> 1. Introduction</h2>
            <p>Welcome to HeyDream Travel & Tours ("we," "our," or "us"). We are committed to protecting your personal
                data and respecting your privacy. This comprehensive Data Privacy Policy outlines how we collect, use,
                disclose, retain, and safeguard your personal information when you use our website, book our travel
                packages, utilize our consultancy services, or interact with us in any capacity.</p>
            <p>We strictly comply with the Data Privacy Act of 2012 (Republic Act No. 10173) of the Philippines, its
                Implementing Rules and Regulations, and other applicable international data protection regulations,
                including the General Data Protection Regulation (GDPR) for our European clients.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-globe"></i> 2. Scope of this Policy</h2>
            <p>This policy applies to all personal data collected through our digital platforms, including our website,
                mobile applications, social media pages, as well as data collected offline via telephone, email, and
                in-person consultations at our physical offices. By accessing our services, you acknowledge that you
                have read and understood the terms of this Privacy Policy.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-list-ul"></i> 3. Information We Collect</h2>
            <p>We collect various types of information to provide you with seamless, personalized travel experiences.
                The data we collect includes, but is not limited to:</p>
            <ul>
                <li><strong>Identity Data:</strong> First name, middle name, last name, date of birth, gender, marital
                    status, nationality, and government-issued identification numbers (such as passport details,
                    driver's licenses, and national IDs) strictly required for travel bookings and visa processing.</li>
                <li><strong>Contact Data:</strong> Residential and billing addresses, email addresses, mobile numbers,
                    telephone numbers, and emergency contact details.</li>
                <li><strong>Health & Dietary Data:</strong> Information regarding medical conditions, physical
                    limitations, or dietary requirements (such as halal, vegan, or allergy-specific meals) to ensure
                    your safety and comfort during travel.</li>
                <li><strong>Transaction & Financial Data:</strong> Credit card details, bank account information,
                    e-wallet IDs, payment history, and billing records. Please note that payments are processed through
                    PCI-DSS compliant secure third-party gateways; we do not store your full, raw credit card numbers on
                    our local servers.</li>
                <li><strong>Profile & Preference Data:</strong> Usernames, passwords, travel preferences, frequent flyer
                    numbers, hotel loyalty memberships, preferred seating, and feedback or survey responses.</li>
                <li><strong>Technical Data:</strong> Internet Protocol (IP) address, browser type and version, time zone
                    setting, browser plug-in types, operating system, and device identifiers used to access our website.
                </li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-database"></i> 4. How We Collect Your Information</h2>
            <p>We gather personal data through the following methods:</p>
            <ul>
                <li><strong>Direct Interactions:</strong> When you fill out booking forms, apply for a visa through our
                    agency, create an account, subscribe to our newsletter, or contact our customer support team.</li>
                <li><strong>Automated Technologies:</strong> As you interact with our website, we may automatically
                    collect technical data about your equipment and browsing actions using cookies, server logs, and
                    similar tracking technologies.</li>
                <li><strong>Third Parties & Publicly Available Sources:</strong> We may receive personal data about you
                    from third parties, such as airlines, hotels, analytics providers (e.g., Google), and advertising
                    networks.</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-cog"></i> 5. How We Use Your Information</h2>
            <p>We process your personal data under strict legal parameters, primarily for the following purposes:</p>
            <ul>
                <li><strong>Service Delivery:</strong> To process, confirm, manage, and execute your flight, hotel, tour
                    reservations, cruise bookings, and travel insurance.</li>
                <li><strong>Customer Support:</strong> To communicate with you regarding your booking status, itinerary
                    changes, travel advisories, and to respond to your inquiries or complaints.</li>
                <li><strong>Visa & Documentation Assistance:</strong> To facilitate visa applications, passport
                    renewals, and other travel-related documentation on your behalf, requiring submission to relevant
                    embassies and consulates.</li>
                <li><strong>Marketing & Promotions:</strong> To send you personalized promotional offers, newsletters,
                    and exclusive travel deals. You may opt-out of these communications at any time.</li>
                <li><strong>Service Improvement:</strong> To analyze website usage, track booking trends, and conduct
                    market research to enhance our service offerings and website functionality.</li>
                <li><strong>Legal & Security:</strong> To comply with legal obligations, regulatory requirements, fraud
                    prevention, and to protect the rights, property, and safety of HeyDream Travel & Tours, our clients,
                    and others.</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-share-alt"></i> 6. Information Sharing and Disclosure</h2>
            <p>We strictly do not sell, trade, or rent your personal data. We only share your information with trusted
                third parties under the following circumstances:</p>
            <ul>
                <li><strong>Travel Service Providers:</strong> Airlines, hotels, car rental agencies, tour operators,
                    and local guides who require your details to fulfill the travel services you have booked.</li>
                <li><strong>Government & Regulatory Authorities:</strong> Embassies, consulates, immigration
                    departments, customs, and security agencies (such as the TSA) when legally mandated for travel or
                    visa processing.</li>
                <li><strong>Third-Party Vendors:</strong> IT service providers, cloud hosting services, payment
                    gateways, and marketing agencies bound by strict Data Processing Agreements (DPAs) to ensure data
                    confidentiality.</li>
                <li><strong>Business Transfers:</strong> In the event of a merger, acquisition, or sale of company
                    assets, your personal data may be transferred to the acquiring entity under the same privacy
                    obligations.</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-lock"></i> 7. Data Security Measures</h2>
            <p>We have implemented comprehensive technical, physical, and organizational security measures designed to
                secure your personal information from accidental loss, unauthorized access, use, alteration, and
                disclosure.</p>
            <ul>
                <li>All data transmitted between your browser and our website is encrypted using Secure Socket Layer
                    (SSL) technology.</li>
                <li>Access to your personal data is restricted to authorized employees, agents, and contractors strictly
                    on a "need-to-know" basis.</li>
                <li>We conduct regular security assessments, vulnerability scans, and employee training on data privacy
                    protocols.</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-archive"></i> 8. Data Retention Policy</h2>
            <p>We will retain your personal information only for as long as reasonably necessary to fulfill the purposes
                we collected it for, including for the purposes of satisfying any legal, regulatory, tax, accounting, or
                reporting requirements. Typically, booking records and financial transaction data are retained for a
                minimum of five (5) to ten (10) years in compliance with Philippine tax laws. Once data is no longer
                necessary, it is securely destroyed or anonymized.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-cookie-bite"></i> 9. Cookies and Tracking Technologies</h2>
            <p>Our website utilizes cookies to distinguish you from other users, providing a personalized and efficient
                browsing experience. Cookies help us remember your preferences, keep you logged in, and analyze site
                traffic. You can set your browser to refuse all or some browser cookies, or to alert you when websites
                set or access cookies. If you disable or refuse cookies, please note that some parts of this website may
                become inaccessible or not function properly.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-user-check"></i> 10. Your Rights as a Data Subject</h2>
            <p>We respect your rights regarding your personal data. Under the Data Privacy Act of 2012, you possess the
                following rights:</p>
            <ul>
                <li><strong>Right to be Informed:</strong> To know whether your personal data is being, or has been,
                    processed.</li>
                <li><strong>Right to Access:</strong> To request a copy of the personal data we hold about you.</li>
                <li><strong>Right to Object:</strong> To object to the processing of your personal data, including for
                    direct marketing purposes.</li>
                <li><strong>Right to Erasure or Blocking:</strong> To suspend, withdraw, or order the blocking, removal,
                    or destruction of your personal data from our filing systems.</li>
                <li><strong>Right to Damages:</strong> To claim compensation if you suffered damages due to inaccurate,
                    incomplete, outdated, false, unlawfully obtained, or unauthorized use of personal data.</li>
                <li><strong>Right to Data Portability:</strong> To obtain and electronically move, copy, or transfer
                    your data in a secure manner.</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-child"></i> 11. Children's Privacy</h2>
            <p>Our services are generally not directed to individuals under the age of 18 without parental consent. When
                booking travel for minors, we only collect personal data provided directly by a parent or legal guardian
                for the strict purpose of fulfilling travel requirements.</p>
        </div>

        <div class="privacy-section">
            <h2><i class="fas fa-envelope"></i> 12. Contact Us</h2>
            <p>We have appointed a Data Protection Officer (DPO) who is responsible for overseeing questions in relation
                to this privacy policy. If you wish to exercise any of your data privacy rights, or if you have any
                questions, concerns, or complaints, please contact us immediately:</p>
            <p>
                <strong>Data Protection Officer</strong><br>
                <strong>Email:</strong> heydreamconsultancy@gmail.com<br>
                <strong>Phone:</strong> 0945 776 4140<br>
                <strong>Address:</strong> 3104 Tektite East Tower, Philippine Stock Exchange, Ortigas, Philippines
            </p>
            <p>We strive to resolve any privacy concerns swiftly and transparently.</p>
        </div>
    </div>

    <div class="back-button-container">
        <button class="back-button" onclick="window.location.href='index.php'">
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
                    <a href="https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                        target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://x.com/HeyDreamTravel?s=20" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc"
                        target="_blank"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/menu.js"></script>
    <script src="js/saved.js"></script>
    <script src="js/auth-menu.js"></script>

    <!-- Floating AI Chatbot Script -->
    <script src="js/floating_chatbot.js"></script>
</body>

</html>


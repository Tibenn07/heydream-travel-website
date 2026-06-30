<?php
// ========================================
// FILE: terms.php
// DESCRIPTION: Terms and Conditions for HeyDream Travel & Tours
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
    <title>HeyDream - Terms & Conditions</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef5 100%);
            font-family: 'Poppins', sans-serif;
            color: #333;
        }

        .terms-hero {
            background: linear-gradient(135deg, #003580 0%, #1a4b8c 50%, #2c5a9e 100%);
            padding: 100px 5% 60px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .terms-hero::before {
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

        .terms-hero h1 {
            font-size: 2.8rem;
            margin-bottom: 15px;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }

        .terms-hero p {
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .terms-container {
            max-width: 1000px;
            margin: -30px auto 60px;
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 2;
        }

        .terms-section {
            margin-bottom: 40px;
        }

        .terms-section h2 {
            color: #003580;
            font-size: 1.6rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terms-section h2 i {
            color: #ff9800;
            font-size: 1.4rem;
        }

        .terms-section p {
            color: #555;
            line-height: 1.7;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .terms-section ul {
            margin-left: 20px;
            margin-bottom: 15px;
            color: #555;
        }

        .terms-section ul li {
            margin-bottom: 8px;
            line-height: 1.6;
            list-style-type: disc;
        }

        .terms-section strong {
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
            .terms-container {
                padding: 30px 20px;
                margin-top: -20px;
                width: 95%;
            }

            .terms-hero h1 {
                font-size: 2.2rem;
            }

            .terms-section h2 {
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

    <section class="terms-hero">
        <h1><i class="fas fa-file-contract"></i> Terms & Conditions</h1>
        <p>Please read these terms carefully before booking your next journey with us.</p>
    </section>

    <div class="terms-container">
        <div class="last-updated">Last Updated: April 23, 2026</div>

        <div class="terms-section">
            <h2><i class="fas fa-handshake"></i> 1. Acceptance of Terms</h2>
            <p>Welcome to HeyDream Travel & Tours. By accessing, browsing, or using our website, or by booking any
                travel package, flight, accommodation, or service through our agency (whether online, by phone, or
                in-person), you explicitly agree to be bound by these Terms and Conditions ("Terms"). If you do not
                agree with any part of these Terms, you must not proceed with your booking or use of our services.</p>
            <p>We act as an agent on behalf of third-party suppliers (such as airlines, hotels, tour operators, and
                cruise lines). Your booking is subject to both our Terms and the specific terms and conditions of these
                respective suppliers.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-calendar-check"></i> 2. Booking Procedures and Reservations</h2>
            <p>All bookings are strictly subject to availability at the time of processing. A booking is only considered
                confirmed once you receive a formal "Booking Confirmation" email or document from us, accompanied by an
                invoice or receipt.</p>
            <ul>
                <li>You must be at least 18 years of age to make a booking.</li>
                <li>You are entirely responsible for ensuring that all names (exactly as they appear on passports/IDs),
                    dates, and travel details are correct at the time of booking.</li>
                <li>Name changes after ticketing are generally not permitted by airlines and may result in the
                    forfeiture of the ticket value.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-tags"></i> 3. Pricing, Taxes, and Fees</h2>
            <p>Prices quoted on our website or by our travel consultants are subject to change without prior notice
                until full payment is received and the booking is ticketed or confirmed. Fluctuations in currency
                exchange rates, fuel surcharges, government taxes, and supplier tariffs can impact the final price.</p>
            <ul>
                <li><strong>Inclusions:</strong> Items included in your package will be explicitly stated in your
                    itinerary. If it is not listed, it is not included.</li>
                <li><strong>Exclusions:</strong> Unless otherwise stated, prices generally exclude personal expenses,
                    tipping, optional tours, visa fees, travel insurance, and resort fees payable directly to the hotel.
                </li>
            </ul>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-credit-card"></i> 4. Payment Terms</h2>
            <p>We require a downpayment (deposit) or full payment to secure a booking, depending on the promotion or
                supplier requirements. Specific payment deadlines will be outlined in your invoice.</p>
            <ul>
                <li>Failure to pay the balance by the specified due date will result in automatic cancellation of the
                    booking, and forfeiture of the initial deposit.</li>
                <li>We accept payments via bank transfer, major credit cards (subject to processing fees), and
                    authorized e-wallets.</li>
                <li>In cases of fraudulent transactions or chargebacks, we reserve the right to cancel your booking and
                    report the incident to authorities.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-undo-alt"></i> 5. Cancellations, Refunds, and Alterations</h2>
            <p><strong>Cancellations by You:</strong> Cancellation requests must be submitted in writing. Cancellation
                penalties apply and depend strictly on the policies of the third-party suppliers (airlines, hotels,
                etc.) and how close the cancellation is to the departure date. Promotional "Flash Deals" and low-cost
                carrier flights are generally 100% non-refundable.</p>
            <p><strong>Cancellations by Us or Suppliers:</strong> We reserve the right to cancel a tour or booking due
                to insufficient participation, operational reasons, or unforeseen supplier issues. In such cases, we
                will offer an alternative date/package or a full refund of the amount paid to us. We are not liable for
                incidental expenses you may have incurred (e.g., non-refundable connecting flights, visa fees).</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-passport"></i> 6. Travel Documents, Visas, and Passports</h2>
            <p>It is your sole responsibility to ensure you have all necessary travel documents. We accept no liability
                if you are denied boarding or entry into a country due to inadequate documentation.</p>
            <ul>
                <li><strong>Passports:</strong> Your passport must be valid for at least six (6) months beyond your
                    intended return date.</li>
                <li><strong>Visas:</strong> While we offer visa assistance services, the issuance of a visa is entirely
                    at the discretion of the respective embassy or consulate. We cannot guarantee visa approval. Visa
                    application fees and our processing fees are non-refundable, regardless of the outcome.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-umbrella-beach"></i> 7. Travel Insurance</h2>
            <p>We strongly recommend purchasing comprehensive travel insurance at the time of booking to cover
                unforeseen events such as medical emergencies, trip cancellations, lost baggage, or flight delays. If
                you choose to decline travel insurance, you assume full financial responsibility for any related losses
                or expenses.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-heartbeat"></i> 8. Health, Safety, and Medical Conditions</h2>
            <p>You must ensure you are medically and physically fit to travel. Certain tours or destinations may have
                health requirements (e.g., specific vaccinations or PCR testing). It is your responsibility to consult
                with a medical professional regarding these requirements prior to travel.</p>
            <p>If you have a medical condition, disability, or dietary requirement, you must inform us at the time of
                booking so we can relay this to the suppliers. However, we cannot guarantee that all specific needs will
                be accommodated by third-party providers.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-exclamation-triangle"></i> 9. Limitation of Liability</h2>
            <p>HeyDream Travel & Tours acts solely as an intermediary and agent for independent suppliers (airlines,
                transport operators, hotels, etc.). We do not own, manage, or control these suppliers. Therefore, we
                shall not be held liable for:</p>
            <ul>
                <li>Any injury, damage, loss, accident, delay, or irregularity caused by the negligence, default, or
                    omission of any supplier.</li>
                <li>Changes in itineraries, flight schedules, or accommodations initiated by the suppliers.</li>
                <li>Loss of enjoyment, mental distress, or incidental damages resulting from your travel arrangements.
                </li>
            </ul>
            <p>Our maximum liability, in any event, shall not exceed the total amount paid by you for the specific
                booking in question.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-bolt"></i> 10. Force Majeure</h2>
            <p>We shall not be liable for any failure to perform our obligations, cancellations, or delays resulting
                from "Force Majeure" events. This includes, but is not limited to: acts of God, extreme weather
                conditions, natural disasters, war, terrorism, civil unrest, labor strikes, pandemics, epidemics,
                government mandates, border closures, or any other circumstances beyond our reasonable control.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-laptop-code"></i> 11. Website Use and Intellectual Property</h2>
            <p>All content on this website, including text, graphics, logos, images, and software, is the property of
                HeyDream Travel & Tours or its content suppliers and is protected by intellectual property laws. You may
                not reproduce, distribute, or modify any content without our express written consent. Unauthorized use
                of this website may give rise to a claim for damages and/or be a criminal offense.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-gavel"></i> 12. Governing Law and Dispute Resolution</h2>
            <p>These Terms and Conditions shall be governed by and construed in accordance with the laws of the Republic
                of the Philippines. Any disputes, claims, or controversies arising out of or relating to these Terms or
                your booking shall be subject to the exclusive jurisdiction of the competent courts of Pasig City,
                Philippines.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-edit"></i> 13. Amendments to Terms</h2>
            <p>We reserve the right to update or modify these Terms and Conditions at any time without prior notice. Any
                changes will be effective immediately upon posting on our website. Your continued use of our services
                following the posting of changes constitutes your acceptance of such changes.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-envelope"></i> 14. Contact Information</h2>
            <p>If you have any questions or concerns regarding these Terms and Conditions, please contact us:</p>
            <p>
                <strong>HeyDream Travel & Tours Legal Department</strong><br>
                <strong>Email:</strong> legal@heydreamtravel.com<br>
                <strong>Phone:</strong> 0945 776 4140<br>
                <strong>Address:</strong> 3104 Tektite East Tower, Philippine Stock Exchange, Ortigas, Philippines
            </p>
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
                    <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
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
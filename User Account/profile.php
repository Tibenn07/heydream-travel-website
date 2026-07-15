<?php
require_once __DIR__ . '/../config/database.php';

requireLogin();

$user = $auth->getCurrentUser();
if (!$user) {
    $auth->logout();
    header('Location: login.php');
    exit;
}
$all_bookings = $auth->getUserBookings($_SESSION['user_id']);

// Filter out completed bookings, and confirmed bookings UNLESS they are Visa Assistance
$bookings = array_filter($all_bookings, function ($b) {
    $status = strtolower($b['booking_status'] ?? '');
    $isVisa = ($b['destination_name'] === 'Visa Assistance' || stripos($b['package_name'], 'visa') !== false);

    if ($status === 'completed')
        return false;
    if ($status === 'confirmed' && !$isVisa)
        return false;

    return true;
});


// Booking cancellation and removal are now handled by staff/customer service.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - My Profile</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../css/sidepanel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .profile-container {
            padding: 120px 5% 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .profile-header {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            border-radius: 24px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: #ff9800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: #003580;
        }

        .profile-details h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .profile-details p {
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .back-home-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-home-btn:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logout-btn:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 1.5rem;
            color: #003580;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .booking-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.12);
        }

        .booking-header {
            background: linear-gradient(135deg, #f8f9fa, #fff);
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .booking-number {
            font-weight: 700;
            color: #003580;
            font-size: 0.9rem;
        }

        .booking-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #cce5ff;
            color: #004085;
        }

        .booking-body {
            padding: 20px;
        }

        .booking-destination {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .booking-package {
            color: #666;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-details {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
            margin-top: 10px;
        }

        .booking-detail-item {
            text-align: center;
        }

        .booking-detail-label {
            font-size: 0.7rem;
            color: #999;
            text-transform: uppercase;
        }

        .booking-detail-value {
            font-weight: 600;
            color: #333;
        }

        .booking-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .booking-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ff9800;
        }

        .track-btn,
        .cancel-btn {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .track-btn {
            background: #003580;
            color: white;
        }

        .track-btn:hover {
            background: #ff9800;
        }

        .cancel-btn {
            background: #dc3545;
            color: white;
        }

        .cancel-btn:hover {
            background: #c82333;
        }

        .empty-bookings {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
        }

        .empty-bookings i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-bookings h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-bookings p {
            color: #666;
            margin-bottom: 20px;
        }

        .browse-btn {
            display: inline-block;
            background: #003580;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .browse-btn:hover {
            background: #ff9800;
        }

        .tracking-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .tracking-modal.active {
            display: flex;
        }

        .tracking-modal-content {
            background: white;
            border-radius: 24px;
            max-width: 550px;
            width: 95%;
            padding: 30px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.3s ease;
        }

        /* Responsive fixes: ensure profile header text wraps on small screens */
        .profile-details {
            min-width: 0;
        }

        .profile-details h1 {
            word-break: break-word;
            white-space: normal;
        }

        .profile-details p {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 0;
        }

        .profile-details p i {
            flex: 0 0 auto;
        }

        .profile-details p span,
        .profile-details p strong {
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        @media (max-width: 600px) {
            .profile-container {
                padding: 80px 4% 40px;
            }

            .profile-header {
                padding: 20px;
                border-radius: 16px;
            }

            .profile-avatar {
                width: 70px;
                height: 70px;
                font-size: 1.6rem;
            }

            .profile-details h1 {
                font-size: 1.2rem;
                margin-bottom: 6px;
            }

            .profile-details p {
                font-size: 0.95rem;
                gap: 8px;
            }

            .profile-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 8px;
            }

            .back-home-btn,
            .logout-btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
        }

        .tracking-steps {
            margin: 25px 0;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .tracking-step {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 10px 0;
            position: relative;
        }

        .tracking-step::before {
            content: '';
            position: absolute;
            left: 19px;
            top: 45px;
            bottom: -15px;
            width: 2px;
            background: #eee;
            z-index: 0;
        }

        .tracking-step:last-child::before {
            display: none;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            flex-shrink: 0;
            z-index: 1;
            transition: all 0.3s ease;
            position: relative;
            background: white;
            border: 2px solid #eee;
        }

        .step-icon.completed {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .step-icon.active {
            background: #003580;
            color: white;
            border-color: #003580;
            box-shadow: 0 0 15px rgba(0, 53, 128, 0.3);
        }

        .step-info {
            flex-grow: 1;
        }

        .step-info h4 {
            margin: 0 0 4px 0;
            font-size: 1.05rem;
            color: #333;
        }

        .step-info p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
        }

        /* Upload UI Styles */
        .upload-container {
            margin-top: 15px;
            padding: 20px;
            border: 2px dashed #00358044;
            border-radius: 16px;
            text-align: center;
            background: #f8fbff;
            transition: all 0.3s ease;
            display: none;
        }

        .upload-container:hover,
        .upload-container.dragover {
            border-color: #003580;
            background: #f0f7ff;
        }

        .upload-box {
            cursor: pointer;
        }

        .upload-box i {
            font-size: 2.2rem;
            color: #003580;
            margin-bottom: 12px;
            display: block;
        }

        .upload-box p {
            font-size: 0.85rem;
            color: #444;
            margin-bottom: 12px;
        }

        .btn-upload {
            background: #003580;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 53, 128, 0.15);
        }

        .btn-upload:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }

        .file-list {
            margin-top: 20px;
            text-align: left;
            border-top: 1px solid #eee;
            padding-top: 15px;
            display: none;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 8px;
            font-size: 0.85rem;
            border: 1px solid #eee;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        }

        .file-item i {
            color: #28a745;
            margin-right: 10px;
        }

        .file-name {
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-right: 15px;
            font-weight: 500;
        }

        .view-link {
            color: #003580;
            text-decoration: none;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 6px;
            background: #f0f7ff;
            transition: all 0.2s ease;
        }

        .view-link:hover {
            background: #003580;
            color: white;
        }

        /* Progress Bar */
        .upload-progress {
            height: 6px;
            width: 100%;
            background: #eee;
            border-radius: 10px;
            margin-top: 15px;
            display: none;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #003580, #0077ff);
            transition: width 0.3s ease;
        }

        .congrats-banner {
            display: none;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
            animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .congrats-banner h4 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
        }

        .congrats-banner p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
            color: white;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-info {
                flex-direction: column;
            }

            .profile-actions {
                width: 100%;
                justify-content: center;
            }

            .bookings-grid {
                grid-template-columns: 1fr;
            }
        }

        .back-button-container {
            max-width: 1400px;
            margin: 0 auto 20px;
            padding: 0 5%;
        }

        .back-button {
            background: none;
            border: none;
            color: #003580;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .back-button:hover {
            color: #ff9800;
            transform: translateX(-5px);
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo" style="height: 37px; width: auto;"
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

        <!-- Collapse Toggle Button -->

        <!-- Profile Header (pre-populated from PHP session) -->
        <div class="sidebar-profile">
            <div class="sidebar-avatar" id="sidebarAvatar">
                <?php if (!empty($user['profile_pic'])): ?>
                    <img src="../<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile"
                        style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(substr($user['full_name'], 0, 2))) ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info" id="sidebarUserInfo">
                <div class="sidebar-user-role" id="sidebarUserRole">Member</div>
                <div class="sidebar-user-name" id="sidebarUserName"><?= htmlspecialchars($user['full_name']) ?></div>
            </div>
        </div>

        <!-- Nav Body -->
        <div class="sidebar-nav-body">

            <!-- ── MAIN Section ── -->
            <div class="sidebar-section-label">Main Menu</div>

            <a href="../index.php" class="sidebar-nav-item" id="nav-home">
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
            <button class="sidebar-nav-item active" id="nav-my-booking" onclick="window.location.href='profile.php'"
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
                <a href="my-profile.php" class="sidebar-sub-item">
                    <i class="fas fa-user-edit" style="color:#003580;font-size:0.8rem;"></i> My Profile
                </a>
                <button class="sidebar-sub-item" onclick="window.location.href='vouchers.php'">
                    <i class="fas fa-ticket-alt" style="color:#003580;font-size:0.8rem;"></i>
                    Vouchers
                </button>
                <button class="sidebar-sub-item" onclick="window.location.href='../saved.php'">
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
                <a href="../buttons/about.php" class="sidebar-sub-item">
                    <i class="fas fa-info-circle" style="color:#003580;"></i> About Us
                </a>
                <a href="../terms.php" class="sidebar-sub-item">
                    <i class="fas fa-file-alt" style="color:#003580;"></i> Terms of Service
                </a> <a href="change-password.php" class="sidebar-sub-item" id="nav-change-password"
                    style="<?php echo $auth->isLoggedIn() ? 'display:block;' : 'display:none;'; ?>">
                    <i class="fas fa-key" style="color:#003580;"></i> Change Password
                </a>
            </div>

        </div><!-- /sidebar-nav-body -->

        <!-- Footer: Logout -->
        <div class="sidebar-footer">
            <div class="sidebar-divider" style="margin:4px 0; opacity: 0.5;"></div>

            <form method="POST" action="logout.php" id="sidebarLogoutForm" style="display:none;"></form>
            <a href="#" onclick="event.preventDefault(); document.getElementById('sidebarLogoutForm').submit();"
                class="sidebar-footer-item logout" id="sidebarLogoutBtn" style="display:none;">
                <i class="fas fa-sign-out-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Logout Account</span>
                <span class="sidebar-tooltip">Logout</span>
            </a>

            <a href="login.php" class="sidebar-footer-item" id="sidebarLoginBtn">
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

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php if (!empty($user['profile_pic'])): ?>
                        <img src="../<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile"
                            style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <?= htmlspecialchars(strtoupper(substr($user['full_name'], 0, 2))) ?>
                    <?php endif; ?>
                </div>
                <div class="profile-details">
                    <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h1>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                    <?php if ($user['phone']): ?>
                        <p><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></p>
                    <?php endif; ?>
                    <p><i class="fas fa-calendar-alt"></i> Member since
                        <?= date('F Y', strtotime($user['created_at'])) ?>
                    </p>
                </div>
            </div>
            <div class="profile-actions">
                <button class="back-home-btn" onclick="window.location.href='../index.php'">
                    <i class="fas fa-home"></i> Back to Home
                </button>
            </div>
        </div>

        <div class="section-title">
            <i class="fas fa-history"></i>
            <h2>MY BOOKING</h2>
        </div>

        <?php if (count($bookings) > 0): ?>
            <div class="bookings-grid">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $statusClass = '';
                    switch ($booking['booking_status']) {
                        case 'pending':
                            $statusClass = 'status-pending';
                            break;
                        case 'confirmed':
                            $statusClass = 'status-confirmed';
                            break;
                        case 'cancelled':
                            $statusClass = 'status-cancelled';
                            break;
                        case 'completed':
                            $statusClass = 'status-completed';
                            break;
                        default:
                            $statusClass = 'status-pending';
                    }
                    ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <span class="booking-number"><?= $booking['booking_number'] ?></span>
                            <?php
                            $vStatusProfile = strtoupper($booking['visa_status'] ?? 'PENDING');
                            if ($vStatusProfile === 'PAID')
                                $vStatusProfile = 'APPROVED';
                            ?>
                            <span
                                class="booking-status status-<?= ($vStatusProfile === 'APPROVED') ? 'confirmed' : (($vStatusProfile === 'DECLINED') ? 'cancelled' : 'pending') ?>"
                                style="margin-right: 8px;">
                                <?= $vStatusProfile ?>
                            </span>
                            <span class="booking-status <?= $statusClass ?>">
                                <?= ucfirst($booking['booking_status']) ?>
                            </span>
                        </div>
                        <div class="booking-body">
                            <div class="booking-destination">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($booking['destination_name']) ?>
                            </div>
                            <div class="booking-package">
                                <i class="fas fa-box-open"></i> <?= htmlspecialchars($booking['package_name']) ?>
                                <span>(<?= $booking['package_duration'] ?>)</span>
                            </div>
                            <div class="booking-details">
                                <div class="booking-detail-item">
                                    <div class="booking-detail-label">Travel Date</div>
                                    <div class="booking-detail-value"><?= date('M d, Y', strtotime($booking['travel_date'])) ?>
                                    </div>
                                </div>
                                <div class="booking-detail-item">
                                    <div class="booking-detail-label">Travelers</div>
                                    <div class="booking-detail-value"><?= $booking['number_of_travelers'] ?></div>
                                </div>
                                <div class="booking-detail-item">
                                    <div class="booking-detail-label">Visa Status</div>
                                    <div class="booking-detail-value"><?= $vStatusProfile ?>
                                    </div>
                                </div>
                                <div class="booking-detail-item">
                                    <div class="booking-detail-label">Payment Status</div>
                                    <div class="booking-detail-value"><?= ucfirst($booking['payment_status']) ?></div>
                                </div>
                                <?php if (!empty($booking['payment_proof'])): ?>
                                    <div class="booking-detail-item"
                                        style="grid-column: span 2; margin-top: 10px; border-top: 1px dashed #eee; padding-top: 10px; width: 100%; text-align: left;">
                                        <div class="booking-detail-label">Payment Proof Submitted:</div>
                                        <div style="margin-top: 5px;">
                                            <?php
                                            $filePath = '../' . $booking['payment_proof'];
                                            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                <button type="button" class="view-receipt-btn"
                                                    data-src="<?= htmlspecialchars($filePath) ?>"
                                                    style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: #003580; font-weight: 600; font-size: 0.95rem; background: #f0f7ff; padding: 8px 14px; border-radius: 10px; border: 1px solid #cce5ff; width: fit-content; cursor: pointer;">
                                                    <i class="fas fa-image"></i> View Receipt Screenshot
                                                </button>
                                            <?php else: ?>
                                                <a href="<?= $filePath ?>" target="_blank"
                                                    style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: #003580; font-weight: 600; font-size: 0.85rem; background: #f0f7ff; padding: 6px 12px; border-radius: 8px; border: 1px solid #cce5ff; width: fit-content;">
                                                    <i class="fas fa-file-pdf"></i> Open Payment Document
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="booking-footer" style="position: relative;">
                            <div class="booking-price">₱<?= number_format($booking['total_amount'], 2) ?></div>
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                <button class="track-btn"
                                    data-booking-number="<?= htmlspecialchars($booking['booking_number']) ?>"
                                    onclick="showTracking('<?= $booking['booking_number'] ?>', '<?= $booking['booking_status'] ?>', '<?= $booking['payment_status'] ?>', <?= intval($booking['travel_documents'] ?? 0) ?>, <?= intval($booking['ready_for_travel'] ?? 0) ?>, '<?= $booking['payment_proof'] ? '../' . $booking['payment_proof'] : '' ?>')"
                                    style="padding: 8px 20px; border-radius: 12px; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(0, 53, 128, 0.1);">
                                    <i class="fas fa-map-pin"></i> Track
                                </button>
                                <div
                                    style="font-size: 0.65rem; color: #888; text-align: right; max-width: 120px; line-height: 1.2;">
                                    Contact staff for cancellation
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-bookings">
                <i class="fas fa-ticket-alt"></i>
                <h3>No bookings yet</h3>
                <p>Start your travel journey by booking your first trip!</p>
                <a href="../foreign-destinations.php" class="browse-btn">Explore Destinations</a>
            </div>
        <?php endif; ?>

    <!-- Image Modal (inline viewer) -->
    <div id="imageModal" class="tracking-modal" style="display:none;">
        <div class="tracking-modal-content"
            style="max-width:1600px; padding:0; background: transparent; box-shadow: none;">
            <div style="display:flex; justify-content:flex-end; margin-bottom:6px;">
                <button onclick="closeImageModal()"
                    style="background:none;border:none;font-size:2rem;color:white;cursor:pointer;">&times;</button>
            </div>
            <div id="imageModalInner"
                style="background:transparent; padding:0; border-radius:10px; display:flex; justify-content:center;">
                <img id="imageModalImg" src="" alt="Receipt"
                    style="width:100%; max-width:1520px; height:auto; display:block; border-radius:6px; max-height:97vh; object-fit:contain;">
            </div>
        </div>
    </div>
    <!-- Tracking Modal -->
    <div id="trackingModal" class="tracking-modal">
        <div class="tracking-modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #003580;"><i class="fas fa-map-pin"></i> Booking Tracking</h3>
                <button onclick="closeTracking()"
                    style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="trackingContent">
                <div id="congratsBanner" class="congrats-banner">
                    <h4><i class="fas fa-plane-departure"></i> Congratulations!</h4>
                    <p>You are officially ready to go. Pack your bags and enjoy your trip!</p>
                </div>
                <div class="tracking-steps">
                    <!-- Step 1: Booking Confirmed -->
                    <div class="tracking-step" id="step1">
                        <div class="step-icon"><i class="fas fa-check"></i></div>
                        <div class="step-info">
                            <h4>Booking Confirmed</h4>
                            <p>Your booking has been received and confirmed.</p>
                        </div>
                    </div>

                    <!-- Step 2: Travel Documents (Rearranged & Enhanced) -->
                    <div class="tracking-step" id="step2">
                        <div class="step-icon"><i class="fas fa-file-upload"></i></div>
                        <div class="step-info" style="width: 100%;">
                            <h4>Travel Documents</h4>
                            <p>Please upload your travel requirements here.</p>

                            <!-- Upload Area -->
                            <div id="uploadContainer" class="upload-container">
                                <div class="upload-box" id="dropZone"
                                    onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag and drop your documents here or <strong>browse</strong></p>
                                    <input type="file" id="fileInput" hidden multiple accept=".pdf,image/*">
                                    <button type="button" class="btn-upload">Select Files</button>
                                </div>
                                <div class="upload-progress" id="uploadProgress">
                                    <div class="progress-bar" id="progressBar"></div>
                                </div>
                                <div id="fileList" class="file-list">
                                    <div id="fileItems"></div>
                                </div>
                                <!-- Submit Button -->
                                <div id="submitDocumentsWrapper" style="display: none; margin-top: 16px;">
                                    <button type="button" id="submitDocsBtn" onclick="submitDocuments()" style="
                                        width: 100%;
                                        background: linear-gradient(135deg, #003580, #0055c8);
                                        color: white;
                                        border: none;
                                        padding: 13px 24px;
                                        border-radius: 30px;
                                        font-size: 0.95rem;
                                        font-weight: 700;
                                        cursor: pointer;
                                        transition: all 0.3s ease;
                                        box-shadow: 0 6px 18px rgba(0, 53, 128, 0.25);
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        gap: 10px;
                                    ">
                                        <i class="fas fa-paper-plane"></i> Submit Documents
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Payment Processed (Rearranged) -->
                    <div class="tracking-step" id="step3">
                        <div class="step-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="step-info">
                            <h4>Payment Processed</h4>
                            <p>Verification of your transaction.</p>
                        </div>
                    </div>

                    <!-- Step 4: Ready for Travel -->
                    <div class="tracking-step" id="step4">
                        <div class="step-icon"><i class="fas fa-plane"></i></div>
                        <div class="step-info">
                            <h4>Ready for Travel</h4>
                            <p>Enjoy your trip! Safe travels!</p>
                        </div>
                    </div>
                </div>
                <div id="bookingNumberDisplay"
                    style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; font-size: 0.85rem; color: #666;">
                </div>
            </div>
        </div>
    </div>

    <div class="back-button-container">
        <a href="../index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
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
                        <li><a href="../buttons/about.php">About Us</a></li>
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
                    <a href="https://x.com/HeyDreamTravel?s=20" target="_blank"><i
                            class="fa-brands fa-x-twitter"></i></a>
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
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        let currentBookingNumber = '';

        /* Image modal: open inline viewer for receipt images */
        function openImageModal(src) {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('imageModalImg');
            const inner = document.getElementById('imageModalInner');
            img.src = src;
            // remove inner padding so the image fills the modal (no outline)
            if (inner) inner.style.padding = '0';
            modal.style.display = 'flex';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('imageModalImg');
            modal.classList.remove('active');
            modal.style.display = 'none';
            img.src = '';
            document.body.style.overflow = '';
        }

        // delegate clicks for any .view-receipt-btn
        document.addEventListener('click', function (e) {
            const btn = e.target.closest && e.target.closest('.view-receipt-btn');
            if (btn) {
                const src = btn.getAttribute('data-src');
                if (src) openImageModal(src);
            }
        });

        function showTracking(bookingNumber, status, paymentStatus, travelDocs, readyForTravel, paymentProof) {
            currentBookingNumber = bookingNumber;
            const modal = document.getElementById('trackingModal');
            const steps = document.querySelectorAll('.tracking-step');
            const congratsBanner = document.getElementById('congratsBanner');
            const uploadContainer = document.getElementById('uploadContainer');

            // Reset all steps and banners
            congratsBanner.style.display = 'none';
            uploadContainer.style.display = 'none';
            steps.forEach(step => {
                const icon = step.querySelector('.step-icon');
                icon.classList.remove('completed', 'active');
                icon.style.background = '';
            });

            if (status === 'cancelled') {
                steps.forEach(step => {
                    const icon = step.querySelector('.step-icon');
                    icon.classList.add('completed');
                    icon.style.background = '#dc3545';
                });
                document.getElementById('bookingNumberDisplay').innerHTML = `<strong>Status:</strong> This booking has been cancelled.`;
            } else {
                // Step 1: Booking Received (Always Completed)
                steps[0].querySelector('.step-icon').classList.add('completed');

                // Step 2: Travel Documents (Driven by upload and admin check)
                if (travelDocs) {
                    steps[1].querySelector('.step-icon').classList.add('completed');
                } else {
                    steps[1].querySelector('.step-icon').classList.add('active');
                }

                // Show upload container for confirmed bookings that aren't completed
                if (status !== 'completed') {
                    uploadContainer.style.display = 'block';
                    loadDocuments(bookingNumber);
                }

                // Step 3: Payment Processed (Driven by payment status)
                if (paymentStatus === 'paid') {
                    steps[2].querySelector('.step-icon').classList.add('completed');
                } else if (status === 'confirmed') {
                    steps[2].querySelector('.step-icon').classList.add('active');
                }

                // Show payment proof link if it exists
                if (paymentProof) {
                    const step3Info = steps[2].querySelector('.step-info');
                    // Reset to original text before adding proof link
                    step3Info.innerHTML = `
                        <h4>Payment Processed</h4>
                        <p>Verification of your transaction.</p>
                        <div style="margin-top: 10px;">
                            <a href="${paymentProof}" target="_blank" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: #003580; font-weight: 600; font-size: 0.8rem; background: #f0f7ff; padding: 6px 12px; border-radius: 6px; border: 1px solid #cce5ff; width: fit-content;">
                                <i class="fas fa-receipt"></i> View Uploaded Payment
                            </a>
                        </div>
                    `;
                } else {
                    const step3Info = steps[2].querySelector('.step-info');
                    step3Info.innerHTML = `
                        <h4>Payment Processed</h4>
                        <p>Verification of your transaction.</p>
                    `;
                }

                // Step 4: Ready for Travel (Driven by admin checkbox)
                if (readyForTravel) {
                    steps[3].querySelector('.step-icon').classList.add('completed');
                    steps[3].querySelector('.step-icon').style.background = '#28a745';
                    congratsBanner.style.display = 'block';
                    triggerConfetti();
                } else if (travelDocs && paymentStatus === 'paid') {
                    steps[3].querySelector('.step-icon').classList.add('active');
                }

                document.getElementById('bookingNumberDisplay').innerHTML = `Booking #: <strong>${bookingNumber}</strong>`;
            }

            modal.classList.add('active');
            initUploadHandlers();
        }

        // ---------- Document Upload Logic ----------

        let pendingFiles = [];

        function initUploadHandlers() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');

            if (!dropZone || !fileInput) return;

            // Drag and drop events
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.parentElement.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.parentElement.classList.remove('dragover'), false);
            });

            dropZone.addEventListener('drop', handleDrop, false);
            fileInput.onchange = handleFiles;
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files } });
        }

        function handleFiles(e) {
            const newFiles = [...e.target.files];
            pendingFiles = [...pendingFiles, ...newFiles]; // Stage files
            renderPendingFiles();
        }

        function renderPendingFiles() {
            const items = document.getElementById('fileItems');
            const submitWrapper = document.getElementById('submitDocumentsWrapper');

            if (pendingFiles.length === 0) {
                submitWrapper.style.display = 'none';
                items.innerHTML = '';
                // Load already uploaded documents if any
                loadDocuments(currentBookingNumber);
                return;
            }

            submitWrapper.style.display = 'block';
            items.innerHTML = `
                <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">STAGED FOR UPLOAD (${pendingFiles.length})</span>
                    <button onclick="clearPendingFiles()" style="background: none; border: none; color: #ef4444; font-size: 0.7rem; cursor: pointer; font-weight: 600;"><i class="fas fa-trash-alt"></i> Clear All</button>
                </div>
                ${pendingFiles.map((file, idx) => {
                const blobUrl = URL.createObjectURL(file);
                return `
                        <div class="file-item" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 6px 10px; margin-bottom: 8px; background: #f8fafc; padding: 8px 12px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                            <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; min-width: 0; flex: 1 1 140px;">
                                <i class="fas fa-file-alt" style="color: #64748b; flex-shrink: 0;"></i>
                                <span class="file-name" style="font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;">${file.name}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0; margin-left: auto;">
                                <a href="${blobUrl}" target="_blank" class="view-link" style="color: #0284c7; font-size: 0.8rem; font-weight: 600; text-decoration: none;">View</a>
                                <span style="color: #94a3b8; font-size: 0.75rem; cursor:pointer;" onclick="removePendingFile(${idx})"><i class="fas fa-times"></i></span>
                            </div>
                        </div>
                    `;
            }).join('')}
            `;
        }

        window.clearPendingFiles = function () {
            pendingFiles = [];
            renderPendingFiles();
        };

        function removePendingFile(idx) {
            pendingFiles.splice(idx, 1);
            renderPendingFiles();
        }

        async function submitDocuments() {
            if (!pendingFiles.length) return;

            const btn = document.getElementById('submitDocsBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            const progress = document.getElementById('uploadProgress');
            const bar = document.getElementById('progressBar');
            progress.style.display = 'block';
            bar.style.width = '10%';

            let uploadedCount = 0;
            let failed = 0;

            for (const file of pendingFiles) {
                const formData = new FormData();
                formData.append('document', file);
                formData.append('booking_number', currentBookingNumber);
                formData.append('action', 'upload');

                try {
                    const res = await fetch('api/upload-api.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) uploadedCount++;
                    else failed++;
                } catch (e) {
                    failed++;
                }

                bar.style.width = `${Math.round(((uploadedCount + failed) / pendingFiles.length) * 100)}%`;
            }

            pendingFiles = [];
            document.getElementById('submitDocumentsWrapper').style.display = 'none';
            progress.style.display = 'none';

            if (uploadedCount > 0) {
                loadDocuments(currentBookingNumber);
                Swal.fire({
                    icon: 'success',
                    title: 'Submitted!',
                    text: `${uploadedCount} document(s) submitted successfully.`,
                    timer: 2500,
                    showConfirmButton: false
                });
            }
            if (failed > 0) {
                Swal.fire('Warning', `${failed} file(s) failed to upload.`, 'warning');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Documents';
        }

        function loadDocuments(bookingNumber) {
            fetch(`api/upload-api.php?action=list&booking_number=${bookingNumber}`)
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('fileList');
                    const items = document.getElementById('fileItems');

                    if (data.success && data.documents.length > 0) {
                        list.style.display = 'block';
                        items.innerHTML = `
                            <div style="margin-bottom: 10px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size: 0.75rem; color: #166534; font-weight: 600; text-transform:uppercase; letter-spacing:0.5px;">
                                    <i class="fas fa-folder-open" style="margin-right:5px;"></i>Uploaded Documents (${data.documents.length})
                                </span>
                            </div>
                            ${data.documents.map(doc => `
                                <div id="doc-item-${doc.id}" style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:6px 8px;
                                     margin-bottom:8px; background:#f0fdf4; padding:10px 12px; border-radius:10px; border:1px solid #bbf7d0;
                                     transition: all 0.2s;">
                                    <div style="display:flex; align-items:center; gap:10px; overflow:hidden; min-width:0; flex:1 1 140px;">
                                        <i class="fas fa-${doc.file_name.match(/\.(pdf)$/i) ? 'file-pdf' : 'file-image'}"
                                           style="color:${doc.file_name.match(/\.(pdf)$/i) ? '#ef4444' : '#22c55e'}; flex-shrink:0;"></i>
                                        <span style="font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                                              color:#166534; font-weight:500; min-width:0;">${doc.file_name}</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0; margin-left:auto;">
                                        <a href="../${doc.file_path}" target="_blank"
                                           style="color:#15803d; font-size:0.78rem; font-weight:600; text-decoration:none;
                                                  background:white; padding:4px 10px; border-radius:6px; border:1px solid #bbf7d0;
                                                  display:inline-flex; align-items:center; gap:4px;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <button onclick="deleteDocument(${doc.id}, '${bookingNumber}')"
                                                style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; font-size:0.78rem;
                                                       font-weight:600; padding:4px 10px; border-radius:6px; cursor:pointer;
                                                       display:inline-flex; align-items:center; gap:4px; transition:all 0.2s;"
                                                onmouseover="this.style.background='#dc2626';this.style.color='white';"
                                                onmouseout="this.style.background='#fee2e2';this.style.color='#dc2626';"
                                                title="Remove this document">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        `;
                    } else {
                        list.style.display = 'block';
                        items.innerHTML = `
                            <div style="text-align:center; padding:16px 10px; color:#64748b; font-size:0.88rem;
                                        background:#f8fafc; border-radius:10px; border:1px dashed #cbd5e1;">
                                <i class="fas fa-inbox" style="font-size:1.5rem; opacity:0.4; display:block; margin-bottom:6px;"></i>
                                No documents uploaded yet. Use the area above to upload your travel requirements.
                            </div>`;
                    }
                })
                .catch(() => {
                    document.getElementById('fileList').style.display = 'none';
                });
        }

        async function deleteDocument(docId, bookingNumber) {
            const confirmed = await Swal.fire({
                title: 'Remove Document?',
                text: 'This will permanently delete this document. Are you sure?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-trash-alt"></i> Yes, Remove',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            });

            if (!confirmed.isConfirmed) return;

            // Animate out
            const el = document.getElementById(`doc-item-${docId}`);
            if (el) { el.style.opacity = '0.4'; el.style.pointerEvents = 'none'; }

            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', docId);
                formData.append('booking_number', bookingNumber);

                const res = await fetch('api/upload-api.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    loadDocuments(bookingNumber); // Refresh the list
                    if (data.remaining === 0) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Removed',
                            text: 'Document removed. Please upload your travel requirements to proceed.',
                            timer: 2500,
                            showConfirmButton: false
                        });
                    }
                } else {
                    if (el) { el.style.opacity = '1'; el.style.pointerEvents = ''; }
                    Swal.fire('Error', data.message || 'Failed to delete document', 'error');
                }
            } catch (err) {
                if (el) { el.style.opacity = '1'; el.style.pointerEvents = ''; }
                Swal.fire('Error', 'Connection error. Please try again.', 'error');
            }
        }

        function closeTracking() {
            document.getElementById('trackingModal').classList.remove('active');
        }

        function triggerConfetti() {
            var duration = 3 * 1000;
            var animationEnd = Date.now() + duration;
            var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 3000 };

            function randomInRange(min, max) {
                return Math.random() * (max - min) + min;
            }

            var interval = setInterval(function () {
                var timeLeft = animationEnd - Date.now();

                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }

                var particleCount = 50 * (timeLeft / duration);
                confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
                confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
            }, 250);
        }

        // Close modal on overlay click
        document.getElementById('trackingModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeTracking();
            }
        });

        // Update saved count
        function updateSavedCount() {
            const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
            const savedCountElements = document.querySelectorAll('#savedCount');
            savedCountElements.forEach(el => {
                if (el) el.textContent = savedItems.length;
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            updateSavedCount();

            // Check for cancellation success
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('cancelled') === '1') {
                Swal.fire({
                    icon: 'success',
                    title: 'Booking Cancelled',
                    text: 'Your booking has been successfully cancelled.',
                    confirmButtonColor: '#0077b6'
                });
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('removed') === '1') {
                Swal.fire({
                    icon: 'success',
                    title: 'Booking Removed',
                    text: 'The booking record has been permanently removed from your history.',
                    confirmButtonColor: '#0077b6'
                });
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Auto-open tracking modal if booking_number is in URL
            const bookingParam = urlParams.get('booking_number') || urlParams.get('track');
            if (bookingParam) {
                const foundBtn = document.querySelector(`button.track-btn[data-booking-number="${CSS.escape(bookingParam)}"]`);

                if (foundBtn) {
                    setTimeout(() => {
                        foundBtn.click();
                        const card = foundBtn.closest('.booking-card');
                        if (card) {
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            card.style.boxShadow = '0 0 15px rgba(0, 119, 182, 0.5)';
                            setTimeout(() => card.style.boxShadow = '', 3000);
                        }
                    }, 300);
                }
            }
        });
    </script>
</body>

</html>
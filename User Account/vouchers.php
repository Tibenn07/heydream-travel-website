<?php
require_once __DIR__ . '/../config/database.php';

$user = $auth->getCurrentUser();
$guestMode = empty($user);

if ($guestMode) {
    $displayName = 'Guest';
    $displayRole = 'Guest';
    $profileInitials = 'G';
    $profilePic = '';
} else {
    $displayName = $user['full_name'] ?? 'Member';
    $displayRole = 'Member';
    $profileInitials = strtoupper(substr($displayName, 0, 2));
    $profilePic = !empty($user['profile_pic']) ? '../' . $user['profile_pic'] : '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - My Vouchers</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../css/sidepanel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }

        .page-container {
            padding: 120px 5% 60px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header - Centered */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #003580;
            margin: 0 0 6px 0;
        }

        .page-header .page-subtitle {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 300;
            margin: 0 0 20px 0;
        }

        /* Tabs - Centered */
        .voucher-tabs {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 36px;
        }

        .voucher-tab-button {
            border: none;
            background: transparent;
            color: #94a3b8;
            padding: 10px 32px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.25s ease;
            font-weight: 500;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
        }

        .voucher-tab-button:hover {
            color: #003580;
            background: #f1f5f9;
        }

        .voucher-tab-button.active {
            background: #003580;
            color: white;
        }

        /* Voucher Grid - Centered with justified cards */
        .voucher-grid-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            width: 100%;
            max-width: 1024px;
        }

        /* When only 1-2 items, center them */
        .voucher-grid:has(.voucher-card:only-child) {
            grid-template-columns: minmax(320px, 420px);
            justify-content: center;
        }

        .voucher-grid:has(.voucher-card:nth-child(2):last-child) {
            grid-template-columns: repeat(2, minmax(300px, 1fr));
            max-width: 720px;
            justify-content: center;
        }

        /* Voucher Card */
        .voucher-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid #edf2f7;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
            height: 100%;
            min-height: 280px;
        }

        .voucher-card:hover {
            box-shadow: 0 4px 20px rgba(0, 53, 128, 0.08);
            border-color: #e2e8f0;
            transform: translateY(-2px);
        }

        /* Banner */
        .voucher-banner {
            padding: 20px 22px;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 90px;
        }

        .voucher-banner-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 1;
        }

        .voucher-discount {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .voucher-discount-label {
            font-size: 0.85rem;
            font-weight: 500;
            opacity: 0.9;
            margin-top: 2px;
        }

        .voucher-icon {
            font-size: 2rem;
            opacity: 0.15;
            flex-shrink: 0;
        }

        /* Divider */
        .voucher-divider {
            display: flex;
            align-items: center;
            padding: 0 14px;
            background: #fafbfc;
            height: 16px;
            flex-shrink: 0;
        }

        .voucher-divider .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #fff;
            border: 1.5px dashed #e2e8f0;
            flex-shrink: 0;
        }

        .voucher-divider .dashes {
            flex-grow: 1;
            border-top: 1.5px dashed #e2e8f0;
            margin: 0 4px;
        }

        /* Body */
        .voucher-body {
            padding: 16px 22px 12px;
            flex-grow: 1;
        }

        .voucher-code-pill {
            display: inline-block;
            background: #f1f4f9;
            border-radius: 999px;
            padding: 4px 14px;
            font-weight: 600;
            font-size: 0.75rem;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.3px;
        }

        .voucher-min-spend {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 8px;
        }

        .voucher-description {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 4px;
            line-height: 1.4;
        }

        /* Footer */
        .voucher-footer {
            padding: 12px 22px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f3f5;
            flex-shrink: 0;
            flex-wrap: wrap;
            gap: 8px;
        }

        .voucher-badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .voucher-badge-status .dot-indicator {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            display: inline-block;
        }

        .voucher-time-label {
            font-size: 0.7rem;
            color: #cbd5e1;
            white-space: nowrap;
        }

        .btn-claim-voucher {
            border: none;
            background: #003580;
            color: white;
            padding: 6px 20px;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s ease;
        }

        .btn-claim-voucher:hover {
            background: #0f4588;
        }

        .btn-claim-voucher:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
        .voucher-empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid #edf2f7;
        }

        .voucher-empty-state i {
            font-size: 2.5rem;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .voucher-empty-state .empty-title {
            font-weight: 600;
            font-size: 1rem;
            color: #1e293b;
        }

        .voucher-empty-state .empty-sub {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 300;
            margin-top: 4px;
        }

        /* ===== PAGINATION - MATCHES LOCAL-DESTINATION.PHP ===== */
        .pagination-wrapper {
            grid-column: 1 / -1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin-top: 32px;
            padding: 10px 0;
        }

        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 30px 0 20px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            background: #003580;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #ff9800;
            transform: translateY(-2px);
        }

        .pagination-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .page-numbers {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .page-number {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #666;
            background: white;
            border: 1px solid #e0e0e0;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }

        .page-number:hover {
            background: #003580;
            color: white;
            border-color: #003580;
        }

        .page-number.active {
            background: #003580;
            color: white;
            border-color: #003580;
        }

        .page-dots {
            color: #666;
            font-weight: 600;
            padding: 0 4px;
        }

        .pagination-info {
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 400;
            text-align: center;
        }

        /* Back Button - Below Pagination */
        .back-button-container {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            margin-top: 4px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #003580;
            border: 1.5px solid #e2e8f0;
            border-radius: 999px;
            padding: 8px 22px;
            color: #c8cdd3;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .back-button:hover {
            background: #ff9100;
            color: white;
            border-color: #003580;
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .voucher-grid {
                grid-template-columns: repeat(2, 1fr);
                max-width: 720px;
            }
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 100px 4% 40px;
            }

            .voucher-grid {
                grid-template-columns: 1fr;
                max-width: 420px;
            }

            .voucher-grid:has(.voucher-card:nth-child(2):last-child) {
                grid-template-columns: 1fr;
                max-width: 420px;
            }

            .page-header .page-title {
                font-size: 1.6rem;
            }

            .voucher-tab-button {
                padding: 8px 20px;
                font-size: 0.8rem;
            }

            .voucher-banner {
                padding: 16px 18px;
                min-height: 75px;
            }

            .voucher-discount {
                font-size: 1.3rem;
            }

            .voucher-body {
                padding: 12px 18px 10px;
            }

            .voucher-footer {
                padding: 10px 18px 14px;
            }

            .pagination-controls {
                gap: 10px;
            }

            .pagination-btn {
                padding: 6px 15px;
                font-size: 0.8rem;
            }

            .page-number {
                width: 35px;
                height: 35px;
                font-size: 0.85rem;
            }

            .pagination-info {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .voucher-grid {
                max-width: 100%;
            }

            .pagination-controls {
                gap: 6px;
            }

            .pagination-btn {
                padding: 5px 12px;
                font-size: 0.7rem;
            }

            .page-number {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
            }

            .page-dots {
                font-size: 0.75rem;
            }
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

    <div class="side-panel" id="sidePanel">
        <div class="sidebar-profile">
            <div class="sidebar-avatar" id="sidebarAvatar">
                <?php if (!empty($profilePic)): ?>
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile"
                        style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <?= htmlspecialchars($profileInitials) ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info" id="sidebarUserInfo">
                <div class="sidebar-user-role" id="sidebarUserRole"><?= htmlspecialchars($displayRole) ?></div>
                <div class="sidebar-user-name" id="sidebarUserName"><?= htmlspecialchars($displayName) ?></div>
            </div>
        </div>

        <div class="sidebar-nav-body">
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

            <button class="sidebar-nav-item" id="nav-my-booking" onclick="window.location.href='profile.php'"
                style="border:none; text-align:left; background:#ffffff; width:100%;">
                <i class="fas fa-calendar-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">My Booking</span>
                <span class="sidebar-tooltip">My Booking</span>
            </button>

            <button class="sidebar-nav-item active" id="nav-account-toggle"
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
                <button class="sidebar-sub-item active" onclick="window.location.href='vouchers.php'">
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

            <button class="sidebar-nav-item" id="nav-help-toggle" onclick="toggleSidebarDropdown('helpDropdown', this)">
                <i class="fas fa-headset sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Help &amp; Support</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Help &amp; Support</span>
            </button>
            <div class="sidebar-dropdown-content" id="helpDropdown">
                <a href="../help-support.php" class="sidebar-sub-item">
                    <i class="fas fa-question-circle" style="color:#003580;font-size:0.8rem;"></i> Help Center
                </a>
                <a href="../support.php" class="sidebar-sub-item">
                    <i class="fas fa-headset" style="color:#003580;font-size:0.8rem;"></i> Contact Support
                </a>
            </div>

            <button class="sidebar-nav-item" id="nav-settings-toggle" onclick="toggleSidebarDropdown('settingsDropdown', this)">
                <i class="fas fa-cog sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Settings</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Settings</span>
            </button>
            <div class="sidebar-dropdown-content" id="settingsDropdown">
                <a href="../buttons/about.php" class="sidebar-sub-item">
                    <i class="fas fa-info-circle" style="color:#003580;font-size:0.8rem;"></i> About Us
                </a>
                <a href="../terms.php" class="sidebar-sub-item">
                    <i class="fas fa-file-contract" style="color:#003580;font-size:0.8rem;"></i> Terms
                </a>
                <a href="change-password.php" class="sidebar-sub-item" id="nav-change-password">
                    <i class="fas fa-lock" style="color:#003580;font-size:0.8rem;"></i> Change Password
                </a>
            </div>
        </div>
    </div>

    <main class="page-container">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Your Vouchers</h1>
            <p class="page-subtitle">Collect and manage your exclusive travel deals</p>
        </div>

        <!-- Tabs -->
        <div class="voucher-tabs">
            <button id="vt-collected" class="voucher-tab-button active" onclick="switchVoucherTab('collected')">Collected</button>
            <button id="vt-available" class="voucher-tab-button" onclick="switchVoucherTab('available')">Available to Claim</button>
        </div>

        <!-- Voucher Grid -->
        <div class="voucher-grid-wrapper">
            <div id="voucher-tab-collected" style="display:block; width:100%;">
                <div class="voucher-grid" id="collected-vouchers-container"></div>
            </div>
            <div id="voucher-tab-available" style="display:none; width:100%;">
                <div class="voucher-grid" id="available-vouchers-container"></div>
            </div>
        </div>
    </main>

    <script src="../js/main.js"></script>
    <script src="../js/menu.js"></script>
    <script src="../js/auth-menu.js"></script>
    <script>
    (function() {
        let voucherTabActive = 'collected';
        let allMyVouchers = [];
        let allAvailableVouchers = [];
        let currentPageMy = 1;
        let currentPageAvailable = 1;
        
        // Responsive items per page: desktop 6 (2x3), mobile 3 (1x3)
        function getItemsPerPage() {
            return window.innerWidth <= 768 ? 3 : 6;
        }

        function renderPagination(totalItems, currentPage, pageChangeCallback) {
            const ITEMS_PER_PAGE = getItemsPerPage();
            const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
            
            if (totalPages <= 1) {
                return `
                    <div class="pagination-wrapper">
                        <div class="back-button-container">
                            <a href="../index.php" class="back-button">
                                <i class="fas fa-arrow-left"></i> Back to Home
                            </a>
                        </div>
                    </div>
                `;
            }
            
            const startItem = ((currentPage - 1) * ITEMS_PER_PAGE) + 1;
            const endItem = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);
            
            let html = '<div class="pagination-wrapper">';
            
            // Pagination controls - Matching local-destination.php style
            html += '<div class="pagination-controls">';
            
            // Previous button
            html += `<button class="pagination-btn" onclick="${currentPage > 1 ? pageChangeCallback + '(' + (currentPage - 1) + ')' : 'return false;'}" ${currentPage === 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i> Previous
            </button>`;
            
            // Page numbers
            html += '<div class="page-numbers">';
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += `<div class="page-number" onclick="${pageChangeCallback}(1)">1</div>`;
                if (startPage > 2) {
                    html += `<span class="page-dots">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<div class="page-number ${i === currentPage ? 'active' : ''}" onclick="${pageChangeCallback}(${i})">${i}</div>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="page-dots">...</span>`;
                }
                html += `<div class="page-number" onclick="${pageChangeCallback}(${totalPages})">${totalPages}</div>`;
            }
            html += '</div>';
            
            // Next button
            html += `<button class="pagination-btn" onclick="${currentPage < totalPages ? pageChangeCallback + '(' + (currentPage + 1) + ')' : 'return false;'}" ${currentPage === totalPages ? 'disabled' : ''}>
                Next <i class="fas fa-chevron-right"></i>
            </button>`;
            
            html += '</div>';
            
            // Items info
            html += `<div class="pagination-info">Showing ${startItem} - ${endItem} of ${totalItems} vouchers</div>`;
            
            // Back button below pagination
            html += `
                <div class="back-button-container">
                    <a href="../index.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            `;
            
            html += '</div>';
            
            return html;
        }

        window.changePageMy = function(page) {
            currentPageMy = page;
            renderMyVouchers();
            // Scroll to top of vouchers
            const wrapper = document.querySelector('.voucher-grid-wrapper');
            if (wrapper) {
                wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        window.changePageAvailable = function(page) {
            currentPageAvailable = page;
            renderAvailableVouchers();
            // Scroll to top of vouchers
            const wrapper = document.querySelector('.voucher-grid-wrapper');
            if (wrapper) {
                wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        // Handle window resize to re-render with correct items per page
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (voucherTabActive === 'collected' && allMyVouchers.length > 0) {
                    currentPageMy = 1;
                    renderMyVouchers();
                } else if (voucherTabActive === 'available' && allAvailableVouchers.length > 0) {
                    currentPageAvailable = 1;
                    renderAvailableVouchers();
                }
            }, 300);
        });

        function renderMyVouchers() {
            const container = document.getElementById('collected-vouchers-container');
            const ITEMS_PER_PAGE = getItemsPerPage();
            const startIndex = (currentPageMy - 1) * ITEMS_PER_PAGE;
            const paginated = allMyVouchers.slice(startIndex, startIndex + ITEMS_PER_PAGE);
            let html = paginated.map(v => buildVoucherCard(v, 'collected')).join('');
            html += renderPagination(allMyVouchers.length, currentPageMy, 'changePageMy');
            container.innerHTML = html;
        }

        function renderAvailableVouchers() {
            const container = document.getElementById('available-vouchers-container');
            const ITEMS_PER_PAGE = getItemsPerPage();
            const startIndex = (currentPageAvailable - 1) * ITEMS_PER_PAGE;
            const paginated = allAvailableVouchers.slice(startIndex, startIndex + ITEMS_PER_PAGE);
            let html = paginated.map(v => buildVoucherCard(v, 'available')).join('');
            html += renderPagination(allAvailableVouchers.length, currentPageAvailable, 'changePageAvailable');
            container.innerHTML = html;
        }

        function switchVoucherTab(tab) {
            voucherTabActive = tab;
            const collected = document.getElementById('voucher-tab-collected');
            const available = document.getElementById('voucher-tab-available');
            const btnCollected = document.getElementById('vt-collected');
            const btnAvailable = document.getElementById('vt-available');

            if (tab === 'collected') {
                collected.style.display = 'block';
                available.style.display = 'none';
                btnCollected.classList.add('active');
                btnAvailable.classList.remove('active');
                if (allMyVouchers.length === 0) loadMyVouchers();
            } else {
                collected.style.display = 'none';
                available.style.display = 'block';
                btnAvailable.classList.add('active');
                btnCollected.classList.remove('active');
                if (allAvailableVouchers.length === 0) loadAvailableVouchers();
            }
        }
        window.switchVoucherTab = switchVoucherTab;

        function parseVoucherDate(value) {
            if (!value) return null;
            const normalized = String(value).trim().replace(' ', 'T');
            const parsed = new Date(normalized);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        }

        function getVoucherStatus(startTime, endTime, nowMs) {
            if (!startTime || !endTime) return 'expired';
            if (startTime.getTime() > nowMs) return 'upcoming';
            let effectiveEnd = endTime;
            if (endTime.getHours() === 0 && endTime.getMinutes() === 0 && endTime.getSeconds() === 0) {
                effectiveEnd = new Date(endTime.getFullYear(), endTime.getMonth(), endTime.getDate(), 23, 59, 59, 999);
            }
            return effectiveEnd.getTime() >= nowMs ? 'active' : 'expired';
        }

        function buildVoucherCard(v, mode) {
            const color = v.color_theme || '#003580';
            const discountText = v.discount_type === 'percentage'
                ? `${parseFloat(v.discount_value)}% OFF`
                : `₱${parseFloat(v.discount_value).toLocaleString()} OFF`;
            const minSpend = parseFloat(v.minimum_spend) > 0
                ? `Min. spend ₱${parseFloat(v.minimum_spend).toLocaleString()}`
                : 'No minimum spend';
            const startTime = parseVoucherDate(v.start_date);
            const endTime = parseVoucherDate(v.end_date);
            const now = Date.now();
            const voucherStatus = getVoucherStatus(startTime, endTime, now);
            const isExpired = voucherStatus === 'expired';
            const timeLabel = (!startTime || startTime.getTime() <= now)
                ? `Ends ${endTime ? endTime.toLocaleString(undefined, {month:'short', day:'numeric'}) : 'Soon'}`
                : `Starts ${startTime ? startTime.toLocaleString(undefined, {month:'short', day:'numeric'}) : 'Soon'}`;

            let footerHtml = '';
            if (mode === 'collected') {
                const statusBg = isExpired ? '#fef2f2' : (v.is_used ? '#f8fafc' : '#ecfdf5');
                const statusColor = isExpired ? '#dc2626' : (v.is_used ? '#94a3b8' : '#059669');
                const statusText = isExpired ? 'Expired' : (v.is_used ? 'Used' : 'Ready');
                const dotColor = isExpired ? '#dc2626' : (v.is_used ? '#94a3b8' : '#059669');
                footerHtml = `
                    <span class="voucher-badge-status" style="background:${statusBg}; color:${statusColor};">
                        <span class="dot-indicator" style="background:${dotColor};"></span> ${statusText}
                    </span>
                    <span class="voucher-time-label">${timeLabel}</span>
                `;
            } else {
                const alreadyClaimed = v.already_claimed;
                const isAutoApply = v.collection_method === 'auto_available';
                footerHtml = alreadyClaimed
                    ? `<span class="voucher-badge-status" style="background:#ecfdf5;color:#059669;"><span class="dot-indicator" style="background:#059669;"></span> Claimed</span>`
                    : isAutoApply
                    ? `<span class="voucher-badge-status" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-bolt" style="font-size:0.6rem;"></i> Auto</span>`
                    : `<button class="btn-claim-voucher" data-voucher-id="${v.id}" onclick="requireLogin('handleVoucherClaim', ${v.id})">Claim</button>`;
                footerHtml += `<span class="voucher-time-label">${timeLabel}</span>`;
            }

            return `
                <div class="voucher-card">
                    <div class="voucher-banner" style="background: linear-gradient(135deg, ${color}, ${color}dd);">
                        <div class="voucher-banner-content">
                            <div>
                                <div class="voucher-discount">${discountText}</div>
                                <div class="voucher-discount-label">${v.voucher_name}</div>
                            </div>
                            <i class="fas fa-ticket-alt voucher-icon"></i>
                        </div>
                    </div>
                    <div class="voucher-divider">
                        <div class="dot"></div>
                        <div class="dashes"></div>
                        <div class="dot"></div>
                    </div>
                    <div class="voucher-body">
                        <div class="voucher-code-pill">#${v.voucher_code}</div>
                        <div class="voucher-min-spend">${minSpend}</div>
                        ${v.description ? `<div class="voucher-description">${v.description}</div>` : ''}
                    </div>
                    <div class="voucher-footer">
                        ${footerHtml}
                    </div>
                </div>
            `;
        }

        function loadMyVouchers() {
            const container = document.getElementById('collected-vouchers-container');
            container.innerHTML = `<div class="voucher-empty-state"><i class="fas fa-spinner fa-spin"></i><p class="empty-title">Loading...</p></div>`;
            fetch('../api/user_voucher_api.php?action=get_my_vouchers')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        container.innerHTML = `<div class="voucher-empty-state">${res.message || 'Failed to load.'}</div>`;
                        return;
                    }
                    if (!res.data || res.data.length === 0) {
                        container.innerHTML = `
                            <div class="voucher-empty-state">
                                <i class="fas fa-wallet"></i>
                                <p class="empty-title">No vouchers yet</p>
                                <p class="empty-sub">Switch to "Available to Claim" to grab some deals</p>
                                <div class="back-button-container" style="margin-top:20px;">
                                    <a href="../index.php" class="back-button">
                                        <i class="fas fa-arrow-left"></i> Back to Home
                                    </a>
                                </div>
                            </div>`;
                        return;
                    }
                    allMyVouchers = res.data;
                    currentPageMy = 1;
                    renderMyVouchers();
                })
                .catch(() => {
                    container.innerHTML = `<div class="voucher-empty-state">Error connecting to server.</div>`;
                });
        }

        function loadAvailableVouchers() {
            const container = document.getElementById('available-vouchers-container');
            container.innerHTML = `<div class="voucher-empty-state"><i class="fas fa-spinner fa-spin"></i><p class="empty-title">Loading...</p></div>`;
            fetch('../api/user_voucher_api.php?action=get_available_vouchers')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        container.innerHTML = `<div class="voucher-empty-state">${res.message || 'Failed to load.'}</div>`;
                        return;
                    }
                    if (!res.data || res.data.length === 0) {
                        container.innerHTML = `
                            <div class="voucher-empty-state">
                                <i class="fas fa-gift"></i>
                                <p class="empty-title">No vouchers available</p>
                                <p class="empty-sub">Check back later for exclusive deals</p>
                                <div class="back-button-container" style="margin-top:20px;">
                                    <a href="../index.php" class="back-button">
                                        <i class="fas fa-arrow-left"></i> Back to Home
                                    </a>
                                </div>
                            </div>`;
                        return;
                    }
                    allAvailableVouchers = res.data;
                    currentPageAvailable = 1;
                    renderAvailableVouchers();
                })
                .catch(() => {
                    container.innerHTML = `<div class="voucher-empty-state">Error connecting to server.</div>`;
                });
        }

        function getClaimButtonElement(voucherId) {
            return document.querySelector(`.btn-claim-voucher[data-voucher-id="${voucherId}"]`);
        }

        function handleVoucherClaim(voucherId) {
            const btn = getClaimButtonElement(voucherId);
            claimVoucher(voucherId, btn);
        }
        window.handleVoucherClaim = handleVoucherClaim;

        function claimVoucher(voucherId, btn) {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }
            const formData = new FormData();
            formData.append('action', 'claim_voucher');
            formData.append('voucher_id', voucherId);
            fetch('../api/user_voucher_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        btn.outerHTML = `<span class="voucher-badge-status" style="background:#ecfdf5;color:#059669;"><span class="dot-indicator" style="background:#059669;"></span> Claimed</span>`;
                        Swal.fire({ icon: 'success', title: 'Claimed!', text: res.message, timer: 2000, showConfirmButton: false });
                        allMyVouchers = [];
                        loadMyVouchers();
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = 'Claim';
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = 'Claim';
                    Swal.fire('Error', 'Server error. Please try again.', 'error');
                });
        }
        window.claimVoucher = claimVoucher;

        document.addEventListener('DOMContentLoaded', () => {
            const guestMode = <?= $guestMode ? 'true' : 'false' ?>;
            if (guestMode) {
                switchVoucherTab('available');
                loadAvailableVouchers();
            } else {
                loadMyVouchers();
            }
        });
    })();
    </script>
</body>

</html>
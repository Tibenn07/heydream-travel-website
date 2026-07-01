<?php
require_once __DIR__ . '/../config/database.php';

requireLogin();

$user = $auth->getCurrentUser();
if (!$user) {
    $auth->logout();
    header('Location: login.php');
    exit;
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .page-container {
            padding: 120px 5% 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .page-header .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #003580;
            margin: 0;
        }

        .page-header .page-subtitle {
            color: #475569;
            font-size: 0.95rem;
            max-width: 720px;
            line-height: 1.6;
            margin: 10px 0 0;
        }

        .voucher-tabs {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .voucher-tab-button {
            border: 1px solid #cbd5e1;
            background: white;
            color: #475569;
            padding: 12px 20px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            min-width: 150px;
            text-align: center;
        }

        .voucher-tab-button.active {
            background: #003580;
            color: white;
            border-color: #003580;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 22px;
        }

        .voucher-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            min-height: 280px;
        }

        .voucher-banner {
            padding: 24px;
            color: white;
        }

        .voucher-discount {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1.05;
        }

        .voucher-discount-label {
            margin-top: 10px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .voucher-divider {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            background: #f8fafc;
        }

        .voucher-divider .dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #fff;
            border: 1px dashed #cbd5e1;
        }

        .voucher-divider .dashes {
            flex-grow: 1;
            border-bottom: 1px dashed #cbd5e1;
            margin: 0 6px;
        }

        .voucher-body {
            padding: 22px;
            flex-grow: 1;
        }

        .voucher-code-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 10px 14px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }

        .voucher-footer {
            padding: 18px 22px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border-top: 1px solid #e2e8f0;
        }

        .voucher-badge-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .btn-claim-voucher {
            border: none;
            background: #003580;
            color: white;
            padding: 12px 18px;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 700;
            transition: background 0.2s ease;
        }

        .btn-claim-voucher:hover {
            background: #0f4588;
        }

        .voucher-empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            border-radius: 24px;
            background: #f8fafc;
            color: #475569;
        }

        .voucher-empty-state i {
            display: block;
            font-size: 3rem;
            margin-bottom: 18px;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 100px 4% 50px;
            }

            .voucher-banner {
                padding: 20px;
            }

            .voucher-footer {
                flex-direction: column;
                align-items: flex-start;
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
        <div class="page-header">
            <div>
                <h1 class="page-title">Your Voucher Wallet</h1>
                <p class="page-subtitle">View vouchers you've already claimed, and find available vouchers you can claim or use on your next booking.</p>
            </div>
        </div>

        <div class="voucher-tabs" role="tablist" aria-label="Voucher tabs">
            <button id="vt-collected" class="voucher-tab-button active" onclick="switchVoucherTab('collected')">Collected</button>
            <button id="vt-available" class="voucher-tab-button" onclick="switchVoucherTab('available')">Available to Claim</button>
        </div>

        <div class="voucher-grid" id="voucher-content">
            <div id="voucher-tab-collected" class="voucher-tab-panel" style="display:block; width:100%;">
                <div class="voucher-grid" id="collected-vouchers-container"></div>
            </div>
            <div id="voucher-tab-available" class="voucher-tab-panel" style="display:none; width:100%;">
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

        function switchVoucherTab(tab) {
            voucherTabActive = tab;
            const collected = document.getElementById('voucher-tab-collected');
            const available = document.getElementById('voucher-tab-available');
            const btnCollected = document.getElementById('vt-collected');
            const btnAvailable = document.getElementById('vt-available');

            if (tab === 'collected') {
                collected.style.display = '';
                available.style.display = 'none';
                btnCollected.classList.add('active');
                btnAvailable.classList.remove('active');
                loadMyVouchers();
            } else {
                collected.style.display = 'none';
                available.style.display = '';
                btnAvailable.classList.add('active');
                btnCollected.classList.remove('active');
                loadAvailableVouchers();
            }
        }
        window.switchVoucherTab = switchVoucherTab;

        function buildVoucherCard(v, mode) {
            const color = v.color_theme || '#003580';
            const discountText = v.discount_type === 'percentage'
                ? `${parseFloat(v.discount_value)}% OFF`
                : `PHP ${parseFloat(v.discount_value).toLocaleString()} OFF`;
            const minSpend = parseFloat(v.minimum_spend) > 0
                ? `Min. spend: ₱${parseFloat(v.minimum_spend).toLocaleString()}`
                : 'No minimum spend';
            const startTime = v.start_date ? new Date(v.start_date) : null;
            const endTime = v.end_date ? new Date(v.end_date) : null;
            const now = new Date();
            const isExpired = endTime && endTime < now;
            const hasStarted = !startTime || startTime <= now;
            const timeLabel = !startTime || !hasStarted
                ? `Starts: ${startTime ? startTime.toLocaleString(undefined, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}) : 'Soon'}`
                : `Ends: ${endTime ? endTime.toLocaleString(undefined, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}) : 'Soon'}`;

            let footerHtml = '';
            if (mode === 'collected') {
                const statusBg = isExpired ? '#fee2e2' : (v.is_used ? '#f1f5f9' : '#d1fae5');
                const statusColor = isExpired ? '#dc2626' : (v.is_used ? '#64748b' : '#059669');
                const statusText = isExpired ? 'Expired' : (v.is_used ? 'Used' : 'Ready to Use');
                footerHtml = `
                    <span class="voucher-badge-status" style="background:${statusBg}; color:${statusColor};">
                        <span style="width:6px;height:6px;border-radius:50%;background:${statusColor};"></span> ${statusText}
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8;">${timeLabel}</span>
                `;
            } else {
                const alreadyClaimed = v.already_claimed;
                const isAutoApply = v.collection_method === 'auto_available';
                footerHtml = alreadyClaimed
                    ? `<span class="voucher-badge-status" style="background:#d1fae5;color:#059669;"><span style="width:6px;height:6px;border-radius:50%;background:#059669;"></span> Already Claimed</span>`
                    : isAutoApply
                    ? `<span class="voucher-badge-status" style="background:#e0f2fe;color:#0369a1;"><i class="fas fa-bolt" style="font-size:0.7rem;"></i> Auto-applies at checkout</span>`
                    : `<button class="btn-claim-voucher" onclick="claimVoucher(${v.id}, this)"><i class="fas fa-plus"></i> Claim</button>`;
                footerHtml += `<span style="font-size: 0.75rem; color: #94a3b8;">${timeLabel}</span>`;
            }

            const targets = (v.targets && v.targets.length > 0)
                ? v.targets.map(t => t.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase())).join(', ')
                : 'All Bookings';

            return `
                <div class="voucher-card">
                    <div class="voucher-banner" style="background: linear-gradient(135deg, ${color}, ${color}cc);">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                            <div>
                                <div class="voucher-discount">${discountText}</div>
                                <div class="voucher-discount-label">${v.voucher_name}</div>
                            </div>
                            <i class="fas fa-ticket-alt" style="font-size:2.5rem;opacity:0.25;position:relative;z-index:1;"></i>
                        </div>
                    </div>
                    <div class="voucher-divider">
                        <div class="dot" style="margin-left:-11px;"></div>
                        <div class="dashes"></div>
                        <div class="dot" style="margin-right:-11px;"></div>
                    </div>
                    <div class="voucher-body">
                        <div class="voucher-code-pill">${v.voucher_code}</div>
                        <div style="font-size:0.78rem;color:#475569;margin-bottom:6px;">${minSpend}</div>
                        ${v.description ? `<div style="font-size:0.76rem;color:#64748b;margin-top:6px;line-height:1.4;">${v.description}</div>` : ''}
                    </div>
                    <div class="voucher-footer">
                        ${footerHtml}
                    </div>
                </div>
            `;
        }

        function loadMyVouchers() {
            const container = document.getElementById('collected-vouchers-container');
            container.innerHTML = `<div class="voucher-empty-state"><i class="fas fa-spinner fa-spin"></i><p style="margin-top:12px;">Loading your vouchers...</p></div>`;

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
                                <p style="font-weight:700;font-size:1.05rem;margin:0;">No vouchers in your wallet yet.</p>
                                <p style="font-size:0.95rem;color:#64748b;margin-top:10px;">Switch to "Available to Claim" to grab some deals!</p>
                            </div>`;
                        return;
                    }
                    container.innerHTML = res.data.map(v => buildVoucherCard(v, 'collected')).join('');
                })
                .catch(() => {
                    container.innerHTML = `<div class="voucher-empty-state">Error connecting to server.</div>`;
                });
        }

        function loadAvailableVouchers() {
            const container = document.getElementById('available-vouchers-container');
            container.innerHTML = `<div class="voucher-empty-state"><i class="fas fa-spinner fa-spin"></i><p style="margin-top:12px;">Loading available vouchers...</p></div>`;

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
                                <p style="font-weight:700;font-size:1.05rem;margin:0;">No vouchers available right now.</p>
                                <p style="font-size:0.95rem;color:#64748b;margin-top:10px;">Check back later for exclusive deals!</p>
                            </div>`;
                        return;
                    }
                    container.innerHTML = res.data.map(v => buildVoucherCard(v, 'available')).join('');
                })
                .catch(() => {
                    container.innerHTML = `<div class="voucher-empty-state">Error connecting to server.</div>`;
                });
        }

        function claimVoucher(voucherId, btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const formData = new FormData();
            formData.append('action', 'claim_voucher');
            formData.append('voucher_id', voucherId);

            fetch('../api/user_voucher_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        btn.outerHTML = `<span class="voucher-badge-status" style="background:#d1fae5;color:#059669;"><span style="width:6px;height:6px;border-radius:50%;background:#059669;"></span> Claimed!</span>`;
                        Swal.fire({ icon: 'success', title: 'Voucher Claimed!', text: res.message, timer: 2500, showConfirmButton: false });
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-plus"></i> Claim';
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-plus"></i> Claim';
                    Swal.fire('Error', 'Server error. Please try again.', 'error');
                });
        }
        window.claimVoucher = claimVoucher;

        document.addEventListener('DOMContentLoaded', loadMyVouchers);
    })();
    </script>
</body>

</html>

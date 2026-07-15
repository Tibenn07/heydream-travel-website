<?php
// ========================================
// FILE: package-book.php
// DESCRIPTION: Dedicated checkout page for "Book This Deal" — reuses the
// same booking wizard (steps, payment, etc.) as the in-page modal, just
// rendered as a full page instead of an overlay.
// ========================================
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$identifier = isset($_GET['id']) ? $_GET['id'] : '';
$isNumeric = is_numeric($identifier);

$pkg = null;

try {
    if ($type === 'local') {
        $sql = "
            SELECT d.*, COALESCE(pr.business_display_name, p.company_name, d.partner_company) AS partner_company
            FROM destinations d
            LEFT JOIN partner_applications p ON d.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = d.partner_id
            WHERE d.type = 'local' AND ";
        if ($isNumeric) {
            $stmt = $pdo->prepare($sql . "d.id = :id");
            $stmt->execute(['id' => intval($identifier)]);
        } else {
            $lookupKey = preg_replace('/^local_/', '', $identifier);
            $stmt = $pdo->prepare($sql . "(d.name = :name OR REPLACE(LOWER(d.name), ' ', '_') = :name OR d.name LIKE :name_like)");
            $stmt->execute(['name' => $lookupKey, 'name_like' => '%' . $lookupKey . '%']);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pkg = normalizeBookPackage($row, 'local');
        }
    } elseif ($type === 'foreign') {
        $sql = "
            SELECT fd.*, COALESCE(pr.business_display_name, p.company_name, fd.partner_company) AS partner_company
            FROM foreign_destinations fd
            LEFT JOIN partner_applications p ON fd.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = fd.partner_id
            WHERE ";
        if ($isNumeric) {
            $stmt = $pdo->prepare($sql . "fd.id = :id");
            $stmt->execute(['id' => intval($identifier)]);
        } else {
            $stmt = $pdo->prepare($sql . "fd.dest_key = :key");
            $stmt->execute(['key' => $identifier]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $stmt = $pdo->prepare($sql . "(fd.name = :name OR REPLACE(LOWER(fd.name), ' ', '_') = :name OR fd.name LIKE :name_like)");
                $stmt->execute(['name' => $identifier, 'name_like' => '%' . $identifier . '%']);
            }
        }
        $row = $row ?? $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pkg = normalizeBookPackage($row, 'foreign');
        }
    } elseif ($type === 'flash') {
        $sql = "SELECT * FROM flash_deals WHERE ";
        if ($isNumeric) {
            $stmt = $pdo->prepare($sql . "id = :id");
            $stmt->execute(['id' => intval($identifier)]);
        } else {
            $stmt = $pdo->prepare($sql . "(title = :name OR REPLACE(LOWER(title), ' ', '_') = :name)");
            $stmt->execute(['name' => $identifier]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $stmt = $pdo->prepare($sql . "title LIKE :name_like");
                $stmt->execute(['name_like' => '%' . $identifier . '%']);
            }
        }
        $row = $row ?? $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pkg = normalizeBookPackage($row, 'flash');
        }
    }
} catch (PDOException $e) {
    $pkg = null;
}

// Same normalization as package-details.php, so the summary card renders
// identically no matter which of the three source tables the row came from.
function normalizeBookPackage($row, $type)
{
    $images = [];
    foreach (['image_path', 'image2_path', 'image3_path', 'image4_path'] as $col) {
        if (!empty($row[$col])) $images[] = $row[$col];
    }
    if (!empty($row['image_gallery'])) {
        $decoded = json_decode($row['image_gallery'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $extra) {
                if (!empty($extra) && !in_array($extra, $images, true)) $images[] = $extra;
            }
        }
    }

    return [
        'type' => $type,
        'id' => $row['id'],
        'identifier' => $type === 'foreign' ? (!empty($row['dest_key']) ? $row['dest_key'] : $row['id']) : $row['id'],
        'name' => $row['name'] ?? $row['title'] ?? 'Package',
        'location' => (!empty($row['location_name']) ? $row['location_name'] : null) ?? (!empty($row['location']) ? $row['location'] : null) ?? $row['city'] ?? $row['country'] ?? '',
        'price' => floatval($row['price'] ?? 0),
        'original_price' => floatval($row['original_price'] ?? 0),
        'discount_percent' => intval($row['discount_percent'] ?? 0),
        'currency' => $row['currency'] ?: '₱',
        'duration' => $row['duration'] ?: '3D/2N',
        'group_size' => $row['group_size'] ?: '2-15 pax',
        'best_season' => $row['best_season'] ?: 'Year Round',
        'images' => $images,
    ];
}

function resolveBookImgSrc($path)
{
    if (!$path) return '';
    if (preg_match('#^(https?:)?//#', $path) || strpos($path, 'data:') === 0) return $path;
    return $path;
}

$curr = $auth->getCurrentUser();
$pkgDetailsUrl = $pkg ? ('package-details.php?type=' . urlencode($pkg['type']) . '&id=' . urlencode($pkg['identifier'])) : 'index.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pkg ? 'Complete Your Booking - ' . htmlspecialchars($pkg['name']) . ' - HeyDream Travel' : 'Package Not Found - HeyDream Travel' ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { background: #f4f6f9; font-family: 'Poppins', sans-serif; }

        .pkgbook-wrap { max-width: 1100px; margin: 0 auto; padding: 24px 20px 60px; }

        /* ---- Top bar: cancel button ---- */
        .pkgbook-topbar {
            display: flex; align-items: center; flex-wrap: wrap;
            gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
            padding: 12px 18px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        }
        .pkgbook-cancel-btn {
            display: inline-flex; align-items: center; gap: 8px; background: #fff; color: #dc2626;
            border: 1px solid #fecaca; padding: 9px 18px; border-radius: 20px; font-weight: 700;
            font-size: 0.85rem; cursor: pointer; transition: background 0.2s, box-shadow 0.2s; flex-shrink: 0;
        }
        .pkgbook-cancel-btn:hover { background: #fef2f2; box-shadow: 0 2px 6px rgba(220,38,38,0.15); }

        /* ---- Two-column checkout layout ----
           Desktop: form left, summary sticky on the right. Mobile: booking
           process first, package summary below it (natural DOM order --
           the form is more urgent than the recap once you're mid-booking). */
        .pkgbook-layout { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
        @media (max-width: 960px) {
            .pkgbook-layout { grid-template-columns: 1fr; }
        }

        .pkgbook-summary { position: sticky; top: 20px; }
        @media (max-width: 960px) { .pkgbook-summary { position: static; margin-top: 20px; } }
        .pkgbook-summary-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; }
        .pkgbook-summary-img { width: 100%; height: 160px; object-fit: cover; display: block; background: #e2e8f0; }
        .pkgbook-summary-body { padding: 18px 20px 20px; }
        .pkgbook-summary-body h3 { margin: 0 0 6px; font-size: 1.05rem; font-weight: 800; color: #0f172a; }
        .pkgbook-summary-loc { color: #64748b; font-size: 0.85rem; margin-bottom: 14px; }
        .pkgbook-summary-loc i { color: #ff9800; margin-right: 4px; }

        /* Reused from package-details.php's price card so the summary looks consistent */
        .pkgdet-price-orig { color: #94a3b8; text-decoration: line-through; font-size: 0.95rem; margin-right: 8px; }
        .pkgdet-price-now { font-size: 1.7rem; font-weight: 900; color: #ff9800; }
        .pkgdet-price-per { color: #64748b; font-size: 0.85rem; margin-bottom: 4px; }
        .pkgdet-discount-badge { display: inline-block; background: #dc2626; color: #fff; font-weight: 800; font-size: 0.75rem; padding: 3px 10px; border-radius: 8px; margin-bottom: 8px; }
        .pkgdet-price-meta { display: flex; flex-direction: column; gap: 8px; margin-top: 14px; font-size: 0.85rem; color: #475569; }
        .pkgdet-price-meta div { display: flex; justify-content: space-between; }

        .pkgbook-notfound { text-align: center; min-height: 56vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; }
        .pkgbook-notfound .icon { width: 88px; height: 88px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .pkgbook-notfound .icon i { font-size: 2.2rem; color: #003580; }
        .pkgbook-notfound h2 { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .pkgbook-notfound p { color: #64748b; margin-bottom: 28px; }
        .pkgbook-notfound-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border-radius: 24px; font-weight: 700; font-size: 0.9rem; text-decoration: none; background: #003580; color: #fff; box-shadow: 0 4px 12px rgba(0,53,128,0.25); transition: transform 0.15s; }
        .pkgbook-notfound-btn:hover { transform: translateY(-2px); }

        /* ---- Un-modal-ify the shared booking wizard markup ----
           home-packages.js / foreign-packages.js / flash-deals.js build the
           booking wizard as a fixed, full-screen modal overlay everywhere
           else on the site. On this page it needs to render as normal,
           in-flow page content inside .pkgbook-form-col instead. */
        .home-package-modal, .package-modal, .flash-deal-modal {
            position: static !important;
            inset: auto !important;
            background: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            display: block !important;
            padding: 0 !important;
            z-index: auto !important;
        }
        .home-package-modal-content, .package-modal-content, .flash-deal-modal-content {
            position: static !important;
            width: 100% !important;
            max-width: 100% !important;
            max-height: none !important;
            margin: 0 !important;
            border-radius: 16px !important;
            box-shadow: 0 2px 12px rgba(15,23,42,0.06) !important;
        }
        /* The modal's own × close controls are replaced by the page-level
           Cancel button above, so hide them to avoid duplicate/confusing
           exits. */
        .close-modal, .close-modal-circle { display: none !important; }

        @media (max-width: 480px) {
            .pkgbook-topbar { flex-direction: column; align-items: stretch; }
            .pkgbook-cancel-btn { justify-content: center; }
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="logo" onclick="window.location.href='index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
        <div class="nav-container">
            <div class="hamburger-menu">
                <button class="hamburger-icon" id="menuToggle" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </header>
    <div class="panel-overlay" id="panelOverlay"></div>

    <div class="side-panel" id="sidePanel">
        <div class="sidebar-profile">
            <div class="sidebar-avatar" id="sidebarAvatar"><i class="fas fa-user"></i></div>
            <div class="sidebar-user-info" id="sidebarUserInfo">
                <div class="sidebar-user-role" id="sidebarUserRole">Guest</div>
                <div class="sidebar-user-name" id="sidebarUserName">Welcome!</div>
            </div>
        </div>
        <div class="sidebar-nav-body">
            <div class="sidebar-section-label">Main Menu</div>
            <a href="index.php" class="sidebar-nav-item" id="nav-home">
                <i class="fas fa-home sidebar-nav-icon"></i><span class="sidebar-nav-label">Home</span>
                <span class="sidebar-tooltip">Home</span>
            </a>
            <a href="local-destination.php" class="sidebar-nav-item" id="nav-local">
                <i class="fas fa-map-marker-alt sidebar-nav-icon"></i><span class="sidebar-nav-label">Local Tours</span>
                <span class="sidebar-tooltip">Local Tours</span>
            </a>
            <a href="foreign-destinations.php" class="sidebar-nav-item" id="nav-foreign">
                <i class="fas fa-plane sidebar-nav-icon"></i><span class="sidebar-nav-label">Foreign Tours</span>
                <span class="sidebar-tooltip">Foreign Tours</span>
            </a>
            <a href="flash-deals.php" class="sidebar-nav-item" id="nav-deals">
                <i class="fas fa-tag sidebar-nav-icon"></i><span class="sidebar-nav-label">Flash Deals</span>
                <span class="sidebar-tooltip">Flash Deals</span>
            </a>
            <button class="sidebar-nav-item" id="nav-my-booking" onclick="requireLogin('goToProfile')" style="border:none; text-align:left; background:#ffffff; width:100%;">
                <i class="fas fa-calendar-alt sidebar-nav-icon"></i><span class="sidebar-nav-label">My Booking</span>
                <span class="sidebar-tooltip">My Booking</span>
            </button>
            <button class="sidebar-nav-item" id="nav-account-toggle" onclick="toggleSidebarDropdown('accountDropdown', this)">
                <i class="fas fa-user sidebar-nav-icon"></i><span class="sidebar-nav-label">My Account</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i><span class="sidebar-tooltip">My Account</span>
            </button>
            <div class="sidebar-dropdown-content" id="accountDropdown">
                <a href="User Account/my-profile.php" class="sidebar-sub-item"><i class="fas fa-user-edit" style="color:#003580;font-size:0.8rem;"></i> My Profile</a>
                <button class="sidebar-sub-item" onclick="requireLogin('goToSaved')">
                    <i class="fas fa-star" style="color:#ff9800;font-size:0.8rem;"></i> Saved
                    <span style="background:#ff9800;color:white;padding:1px 7px;border-radius:20px;font-size:0.7rem;margin-left:6px;" id="savedCount">0</span>
                </button>
            </div>
            <div class="sidebar-divider"></div>
            <div class="sidebar-section-label">Settings</div>
            <a href="buttons/about.php" class="sidebar-sub-item"><i class="fas fa-info-circle" style="color:#003580;"></i> About Us</a>
            <a href="terms.php" class="sidebar-sub-item"><i class="fas fa-file-alt" style="color:#003580;"></i> Terms of Service</a>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-divider" style="margin:4px 0; opacity: 0.5;"></div>
            <a href="#" onclick="event.preventDefault(); showLogoutConfirmPopup();" class="sidebar-footer-item logout" id="sidebarLogoutBtn" style="display:none;">
                <i class="fas fa-sign-out-alt sidebar-nav-icon"></i><span class="sidebar-nav-label">Logout Account</span>
                <span class="sidebar-tooltip">Logout</span>
            </a>
            <a href="User Account/login.php" class="sidebar-footer-item" id="sidebarLoginBtn">
                <i class="fas fa-sign-in-alt sidebar-nav-icon" style="color:#003580;"></i><span class="sidebar-nav-label">Sign In</span>
                <span class="sidebar-tooltip">Sign In</span>
            </a>
        </div>
    </div><!-- /side-panel -->

    <section class="main-page-section">
        <div class="pkgbook-wrap">
            <?php if (!$pkg): ?>
                <div class="pkgbook-notfound">
                    <div class="icon"><i class="fas fa-map-signs"></i></div>
                    <h2>Package Not Found</h2>
                    <p>This package may have been removed or the link is incorrect.</p>
                    <a href="index.php" class="pkgbook-notfound-btn"><i class="fas fa-home"></i> Back to Home</a>
                </div>
            <?php else: ?>
                <div class="pkgbook-topbar">
                    <button class="pkgbook-cancel-btn" onclick="cancelPackageBooking()"><i class="fas fa-arrow-left"></i> Cancel</button>
                </div>

                <div class="pkgbook-layout">
                    <div class="pkgbook-form-col" id="pkgbookFormCol">
                        <div style="text-align:center;padding:60px 20px;color:#64748b;">
                            <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                            <p style="margin-top:12px;">Loading booking form...</p>
                        </div>
                    </div>

                    <aside class="pkgbook-summary">
                        <div class="pkgbook-summary-card">
                            <img class="pkgbook-summary-img" src="<?= htmlspecialchars(resolveBookImgSrc($pkg['images'][0] ?? '') ?: ('https://via.placeholder.com/400x200?text=' . urlencode($pkg['name']))) ?>" alt="<?= htmlspecialchars($pkg['name']) ?>" onerror="this.onerror=null;this.src='https://via.placeholder.com/400x200?text=<?= urlencode($pkg['name']) ?>'">
                            <div class="pkgbook-summary-body">
                                <h3><?= htmlspecialchars($pkg['name']) ?></h3>
                                <div class="pkgbook-summary-loc"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($pkg['location']) ?></div>

                                <?php if ($pkg['discount_percent'] > 0): ?><span class="pkgdet-discount-badge"><?= $pkg['discount_percent'] ?>% OFF</span><br><?php endif; ?>
                                <?php if ($pkg['original_price'] > $pkg['price'] && $pkg['original_price'] > 0): ?><span class="pkgdet-price-orig"><?= $pkg['currency'] ?><?= number_format($pkg['original_price']) ?></span><?php endif; ?>
                                <span class="pkgdet-price-now"><?= $pkg['currency'] ?><?= number_format($pkg['price']) ?></span>
                                <div class="pkgdet-price-per">per person</div>

                                <div class="pkgdet-price-meta">
                                    <div><span>Duration</span><strong><?= htmlspecialchars($pkg['duration']) ?></strong></div>
                                    <div><span>Group Size</span><strong><?= htmlspecialchars($pkg['group_size']) ?></strong></div>
                                    <div><span>Travel Validity</span><strong><?= htmlspecialchars($pkg['best_season']) ?></strong></div>
                                </div>

                            </div>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </section>

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
                        <li><i class="fas fa-map-marker-alt"></i> 3104 Tektite East Tower, Philippine Stock Exchange, Ortigas</li>
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
                    <a href="https://x.com/HeyDreamTravel?s=20" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <script>
        window.currentUserEmail = '<?= ($curr && isset($curr['email'])) ? htmlspecialchars($curr['email']) : '' ?>';
        window.currentFullName = '<?= ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : '' ?>';

        const PKG_TYPE = <?= json_encode($pkg['type'] ?? '') ?>;
        const PKG_IDENTIFIER = <?= json_encode($pkg['identifier'] ?? '') ?>;
        const PKG_DETAILS_URL = <?= json_encode($pkgDetailsUrl) ?>;

        function cancelPackageBooking() {
            window.location.href = PKG_DETAILS_URL;
        }

        // Draft auto-save: the traveler's date/name/phone/requests are kept
        // in sessionStorage as they type, so leaving and coming back to this
        // page (same tab session) restores what they already entered instead
        // of losing it. Payment fields are deliberately never saved.
        const PKG_DRAFT_KEY = 'pkgbook_draft_' + PKG_TYPE + '_' + PKG_IDENTIFIER;
        const PKG_DRAFT_EXCLUDE = /card|cvv|cvc|expiry|bank|gcash|paymaya|paymentref/i;

        function savePkgDraft() {
            const data = {};
            document.querySelectorAll('#pkgbookFormCol input, #pkgbookFormCol textarea').forEach(function (el) {
                if (!el.id || el.type === 'radio' || el.type === 'checkbox' || PKG_DRAFT_EXCLUDE.test(el.id)) return;
                data[el.id] = el.value;
            });
            try { sessionStorage.setItem(PKG_DRAFT_KEY, JSON.stringify(data)); } catch (e) { /* storage unavailable, skip */ }
        }

        function restorePkgDraft() {
            let raw;
            try { raw = sessionStorage.getItem(PKG_DRAFT_KEY); } catch (e) { return; }
            if (!raw) return;
            try {
                const data = JSON.parse(raw);
                Object.keys(data).forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el && !el.value) el.value = data[id];
                });
            } catch (e) { /* corrupt draft, ignore */ }
        }

        function clearPkgDraft() {
            try { sessionStorage.removeItem(PKG_DRAFT_KEY); } catch (e) { /* storage unavailable, skip */ }
        }

        document.addEventListener('DOMContentLoaded', async function () {
            // The modal's own × close controls act like the page's Cancel
            // button here.
            window.closeHomeDestinationModal = cancelPackageBooking;
            window.closeForeignPackageModal = cancelPackageBooking;
            window.closeFlashDealModal = cancelPackageBooking;

            const formCol = document.getElementById('pkgbookFormCol');
            if (!formCol) return;

            let draftSaveTimeout;
            formCol.addEventListener('input', function () {
                clearTimeout(draftSaveTimeout);
                draftSaveTimeout = setTimeout(savePkgDraft, 400);
            });

            // Clear the draft once the booking actually succeeds -- nothing
            // left to restore at that point.
            const successObserver = new MutationObserver(function () {
                if (document.querySelector('#pkgbookFormCol .success-message')) {
                    clearPkgDraft();
                    successObserver.disconnect();
                }
            });
            successObserver.observe(formCol, { childList: true, subtree: true });

            let modal = null;
            try {
                if (PKG_TYPE === 'local' && typeof resumeHomeBooking === 'function') {
                    await resumeHomeBooking(PKG_IDENTIFIER, 1);
                    modal = document.getElementById('homeDestinationModal');
                } else if (PKG_TYPE === 'foreign' && typeof resumeForeignBooking === 'function') {
                    await resumeForeignBooking(PKG_IDENTIFIER, 1);
                    modal = document.getElementById('foreignPackageModal');
                } else if (PKG_TYPE === 'flash' && typeof resumeFlashBooking === 'function') {
                    await resumeFlashBooking(PKG_IDENTIFIER, 1);
                    modal = document.getElementById('flashDealModal');
                }
            } catch (err) {
                console.error('Error loading booking form:', err);
            }

            if (modal) {
                formCol.innerHTML = '';
                formCol.appendChild(modal);
                modal.classList.add('active');
                restorePkgDraft();
            } else {
                formCol.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#dc2626;"><i class="fas fa-exclamation-circle" style="font-size:2rem;"></i><p style="margin-top:12px;">Could not load the booking form. Please go back and try again.</p></div>';
            }
        });
    </script>
    <script src="js/main.js?v=2"></script>
    <script src="js/menu.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/auth-menu.js?v=2"></script>
    <script src="js/voucher-checkout.js"></script>
    <script src="js/home-packages.js?v=4"></script>
    <script src="js/foreign-packages.js?v=4"></script>
    <script src="js/flash-deals.js?v=5"></script>
    <script src="js/saved.js"></script>
</body>

</html>

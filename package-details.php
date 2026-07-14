<?php
// ========================================
// FILE: package-details.php
// DESCRIPTION: Full-page package detail view (local / foreign / flash deal)
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
            // Homepage cards prefix the slug with "local_" (e.g. "local_boracay"),
            // while other entry points pass the bare slug — accept both.
            $lookupKey = preg_replace('/^local_/', '', $identifier);
            $stmt = $pdo->prepare($sql . "(d.name = :name OR REPLACE(LOWER(d.name), ' ', '_') = :name OR d.name LIKE :name_like)");
            $stmt->execute(['name' => $lookupKey, 'name_like' => '%' . $lookupKey . '%']);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pkg = normalizePackage($row, 'local');
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
            $pkg = normalizePackage($row, 'foreign');
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
            $pkg = normalizePackage($row, 'flash');
        }
    }
} catch (PDOException $e) {
    $pkg = null;
}

// Normalizes a raw DB row from any of the three source tables into one
// common shape the template below can render without type-branching.
function normalizePackage($row, $type)
{
    $decode = function ($val) {
        if (!$val) return [];
        if (is_array($val)) return $val;
        $decoded = json_decode($val, true);
        return is_array($decoded) ? $decoded : [];
    };

    $images = [];
    foreach (['image_path', 'image2_path', 'image3_path', 'image4_path'] as $col) {
        if (!empty($row[$col])) $images[] = $row[$col];
    }
    foreach ($decode($row['image_gallery'] ?? null) as $extra) {
        if (!empty($extra) && !in_array($extra, $images, true)) $images[] = $extra;
    }

    return [
        'type' => $type,
        'id' => $row['id'],
        'identifier' => $type === 'foreign' ? ($row['dest_key'] ?? $row['id']) : $row['id'],
        'name' => $row['name'] ?? $row['title'] ?? 'Package',
        'location' => (!empty($row['location_name']) ? $row['location_name'] : null) ?? (!empty($row['location']) ? $row['location'] : null) ?? $row['city'] ?? $row['country'] ?? '',
        'country' => $row['country'] ?? '',
        'city' => $row['city'] ?? '',
        'description' => $row['description'] ?? '',
        'short_description' => $row['short_description'] ?? '',
        'price' => floatval($row['price'] ?? 0),
        'original_price' => floatval($row['original_price'] ?? 0),
        'discount_percent' => intval($row['discount_percent'] ?? 0),
        'currency' => $row['currency'] ?: '₱',
        'duration' => $row['duration'] ?: '3D/2N',
        'group_size' => $row['group_size'] ?: '2-15 pax',
        'best_season' => $row['best_season'] ?: 'Year Round',
        'category' => $row['category'] ?? '',
        'badge' => $row['badge_text'] ?? '',
        'rating' => floatval($row['rating'] ?? 0),
        'reviews' => intval($row['reviews'] ?? 0),
        'activities_count' => intval($row['activities_count'] ?? 0),
        'booked_count' => trim($row['booked_count'] ?? ''),
        'images' => $images,
        'itinerary' => $decode($row['itinerary'] ?? null),
        'inclusions' => $decode($row['inclusions'] ?? null),
        'exclusions' => $decode($row['exclusions'] ?? null),
        'hotels' => $decode($row['hotels'] ?? null),
        'remarks' => $row['remarks'] ?? '',
        'promo_start' => $row['promo_start'] ?? null,
        'promo_end' => $row['promo_end'] ?? null,
        // blocked_months is a comma-joined list of month numbers (1-12);
        // blocked_dates is a comma-joined list of exact dates from a
        // flatpickr multi-date picker — neither is JSON, unlike itinerary/
        // inclusions/exclusions/hotels above.
        'blocked_months' => array_filter(array_map('trim', explode(',', $row['blocked_months'] ?? ''))),
        'blocked_dates' => array_filter(array_map('trim', explode(',', $row['blocked_dates'] ?? ''))),
        'partner_id' => $row['partner_id'] ?? null,
        'partner_company' => $row['partner_company'] ?? null,
    ];
}

const PKGDET_MONTH_NAMES = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];

function resolveImgSrc($path)
{
    if (!$path) return '';
    if (preg_match('#^(https?:)?//#', $path) || strpos($path, 'data:') === 0) return $path;
    return $path;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pkg ? htmlspecialchars($pkg['name']) . ' - HeyDream Travel' : 'Package Not Found - HeyDream Travel' ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { background: #f4f6f9; font-family: 'Poppins', sans-serif; }

        .pkgdet-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }

        .pkgdet-top-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }

        .pkgdet-breadcrumb { font-size: 0.85rem; color: #64748b; }
        .pkgdet-breadcrumb a { color: #003580; text-decoration: none; font-weight: 600; }
        .pkgdet-breadcrumb a:hover { text-decoration: underline; }

        .pkgdet-back-btn { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: #003580; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; text-decoration: none; box-shadow: 0 1px 3px rgba(15,23,42,0.06); cursor: pointer; transition: background 0.2s, box-shadow 0.2s; flex-shrink: 0; }
        .pkgdet-back-btn:hover { background: #f1f5f9; box-shadow: 0 2px 6px rgba(15,23,42,0.1); }

        .pkgdet-title-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
        .pkgdet-title-row h1 { font-size: 1.9rem; font-weight: 800; color: #0f172a; margin: 0 0 6px; }
        .pkgdet-location { color: #475569; font-size: 0.95rem; }
        .pkgdet-location i { color: #ff9800; margin-right: 4px; }
        .pkgdet-rating { display: inline-flex; align-items: center; gap: 6px; background: #003580; color: #fff; padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; }

        /* ---- Adaptive gallery ---- */
        .pkgdet-gallery { display: grid; gap: 8px; border-radius: 16px; overflow: hidden; margin-bottom: 28px; height: 440px; }
        .pkgdet-gallery.count-1 { grid-template-columns: 1fr; }
        .pkgdet-gallery.count-2 { grid-template-columns: 1fr 1fr; }
        .pkgdet-gallery.count-multi { grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; }
        .pkgdet-gallery.count-multi .pkgdet-gtile:first-child { grid-row: 1 / 3; }
        .pkgdet-gtile { position: relative; cursor: pointer; overflow: hidden; background: #e2e8f0; }
        .pkgdet-gtile img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.35s ease; }
        .pkgdet-gtile:hover img { transform: scale(1.05); }
        .pkgdet-gtile .pkgdet-more-overlay { position: absolute; inset: 0; background: rgba(15,23,42,0.6); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 800; }

        @media (max-width: 768px) {
            .pkgdet-gallery { height: 280px; }
            .pkgdet-gallery.count-multi { grid-template-columns: 1fr; grid-template-rows: none; height: auto; }
            .pkgdet-gallery.count-multi .pkgdet-gtile { height: 220px; }
            .pkgdet-gallery.count-multi .pkgdet-gtile:first-child { grid-row: auto; height: 260px; }
            .pkgdet-gallery.count-multi .pkgdet-gtile:not(:first-child):nth-child(n+4) { display: none; }
        }

        /* ---- Two-column body ---- */
        .pkgdet-body { display: grid; grid-template-columns: 1fr 340px; gap: 28px; align-items: start; }
        @media (max-width: 900px) {
            .pkgdet-body { grid-template-columns: 1fr; }
            /* Price/"Book This Deal" card is the second DOM child (after all
               the itinerary/inclusions content), which would otherwise land
               at the very bottom once the grid collapses to one column on
               mobile -- pull it back up near the top instead. */
            .pkgdet-sticky { order: -1; margin-bottom: 20px; position: static !important; }
        }

        .pkgdet-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .pkgdet-card h2 { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }
        .pkgdet-card h2 i { color: #ff9800; }

        .pkgdet-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .pkgdet-chip { background: #f0f7ff; border: 1px solid #cce5ff; color: #003580; padding: 8px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .pkgdet-chip i { color: #ff9800; }

        .pkgdet-desc { color: #334155; line-height: 1.7; font-size: 0.95rem; white-space: pre-line; }

        .pkgdet-day { border-left: 3px solid #ff9800; padding: 4px 0 16px 16px; margin-bottom: 8px; }
        .pkgdet-day:last-child { margin-bottom: 0; }
        .pkgdet-day h4 { margin: 0 0 6px; color: #003580; font-weight: 700; font-size: 0.95rem; }
        .pkgdet-activity-row { display: flex; align-items: flex-start; gap: 8px; color: #475569; font-size: 0.88rem; line-height: 1.5; margin-bottom: 6px; }
        .pkgdet-activity-row:last-child { margin-bottom: 0; }
        .pkgdet-activity-row i { color: #ff9800; font-size: 0.7rem; margin-top: 5px; flex-shrink: 0; }

        .pkgdet-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 600px) { .pkgdet-two-col { grid-template-columns: 1fr; } }
        .pkgdet-line-row { display: flex; align-items: flex-start; gap: 10px; font-size: 0.88rem; line-height: 1.5; margin-bottom: 10px; color: #334155; }
        .pkgdet-line-row:last-child { margin-bottom: 0; }
        .pkgdet-line-row .dot { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.68rem; flex-shrink: 0; margin-top: 1px; }
        .pkgdet-line-row.inc .dot { background: #dcfce7; color: #15803d; }
        .pkgdet-line-row.exc .dot { background: #fee2e2; color: #b91c1c; }

        .pkgdet-hotel-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; font-size: 0.88rem; }
        .pkgdet-hotel-row:last-child { margin-bottom: 0; }

        .pkgdet-pills { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 4px; }
        .pkgdet-pill { background: #fff7ed; border: 1px solid #fed7aa; color: #b45309; padding: 4px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 600; }

        .pkgdet-social-badge { display: inline-flex; align-items: center; gap: 6px; background: #fff7ed; color: #c2410c; padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; }

        .pkgdet-partner-box { display: flex; align-items: center; gap: 14px; }
        .pkgdet-partner-box .icon { width: 48px; height: 48px; border-radius: 50%; background: #fff7ed; color: #ff9800; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .pkgdet-partner-box a { color: #003580; font-weight: 700; text-decoration: none; }
        .pkgdet-partner-box a:hover { text-decoration: underline; }

        /* ---- Sticky price card ---- */
        .pkgdet-sticky { position: sticky; top: 20px; }
        .pkgdet-price-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; }
        .pkgdet-price-orig { color: #94a3b8; text-decoration: line-through; font-size: 0.95rem; margin-right: 8px; }
        .pkgdet-price-now { font-size: 1.9rem; font-weight: 900; color: #ff9800; }
        .pkgdet-price-per { color: #64748b; font-size: 0.85rem; }
        .pkgdet-discount-badge { display: inline-block; background: #dc2626; color: #fff; font-weight: 800; font-size: 0.75rem; padding: 3px 10px; border-radius: 8px; margin-bottom: 8px; }
        .pkgdet-book-btn { width: 100%; margin-top: 16px; background: linear-gradient(135deg, #ff9800, #f57c00); color: #fff; border: none; padding: 14px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; box-shadow: 0 6px 14px rgba(255,152,0,0.3); }
        .pkgdet-book-btn:hover { filter: brightness(1.05); }
        .pkgdet-price-meta { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; font-size: 0.85rem; color: #475569; }
        .pkgdet-price-meta div { display: flex; justify-content: space-between; }

        .pkgdet-notfound { text-align: center; min-height: 56vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; }
        .pkgdet-notfound .pkgdet-notfound-icon { width: 88px; height: 88px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .pkgdet-notfound .pkgdet-notfound-icon i { font-size: 2.2rem; color: #003580; }
        .pkgdet-notfound h2 { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .pkgdet-notfound p { color: #64748b; margin-bottom: 28px; }
        .pkgdet-notfound-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
        .pkgdet-notfound-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border-radius: 24px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: transform 0.15s, box-shadow 0.15s; }
        .pkgdet-notfound-btn.primary { background: #003580; color: #fff; box-shadow: 0 4px 12px rgba(0,53,128,0.25); }
        .pkgdet-notfound-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,53,128,0.32); }
        .pkgdet-notfound-btn.secondary { background: #fff; color: #003580; border: 1px solid #e2e8f0; }
        .pkgdet-notfound-btn.secondary:hover { background: #f1f5f9; transform: translateY(-2px); }

        /* ---- Lightbox ---- */
        .pkgdet-lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 5000; align-items: center; justify-content: center; }
        .pkgdet-lightbox.active { display: flex; }
        .pkgdet-lightbox img { max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: 8px; }
        .pkgdet-lightbox-close, .pkgdet-lightbox-prev, .pkgdet-lightbox-next { position: absolute; background: rgba(255,255,255,0.15); color: #fff; border: none; width: 46px; height: 46px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .pkgdet-lightbox-close { top: 20px; right: 20px; }
        .pkgdet-lightbox-prev { left: 20px; top: 50%; transform: translateY(-50%); }
        .pkgdet-lightbox-next { right: 20px; top: 50%; transform: translateY(-50%); }
        .pkgdet-lightbox-counter { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); color: #fff; font-size: 0.85rem; background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 20px; }
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
        <div class="pkgdet-wrap">
            <?php if (!$pkg): ?>
                <div class="pkgdet-notfound">
                    <div class="pkgdet-notfound-icon"><i class="fas fa-map-signs"></i></div>
                    <h2>Package Not Found</h2>
                    <p>This package may have been removed or the link is incorrect.</p>
                    <div class="pkgdet-notfound-actions">
                        <a href="javascript:void(0)" class="pkgdet-notfound-btn secondary" onclick="goBackFromPackageDetails('index.php')"><i class="fas fa-arrow-left"></i> Go Back</a>
                        <a href="index.php" class="pkgdet-notfound-btn primary"><i class="fas fa-home"></i> Back to Home</a>
                    </div>
                </div>
            <?php else: ?>
                <?php $pkgListingUrl = $pkg['type'] === 'local' ? 'local-destination.php' : ($pkg['type'] === 'foreign' ? 'foreign-destinations.php' : 'flash-deals.php'); ?>
                <div class="pkgdet-top-row">
                    <a href="index.php" class="pkgdet-back-btn"><i class="fas fa-home"></i> Back to Home</a>
                    <div class="pkgdet-breadcrumb">
                        <a href="index.php">Home</a> /
                        <a href="<?= $pkgListingUrl ?>"><?= $pkg['type'] === 'local' ? 'Local Tours' : ($pkg['type'] === 'foreign' ? 'Foreign Tours' : 'Flash Deals') ?></a> /
                        <?= htmlspecialchars($pkg['name']) ?>
                    </div>
                </div>

                <div class="pkgdet-title-row">
                    <div>
                        <h1><?= htmlspecialchars($pkg['name']) ?></h1>
                        <div class="pkgdet-location"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($pkg['location']) ?> &nbsp;·&nbsp; <i class="fas fa-clock"></i> <?= htmlspecialchars($pkg['duration']) ?></div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                        <?php if ($pkg['type'] === 'flash' && $pkg['rating'] > 0): ?>
                            <div class="pkgdet-rating"><i class="fas fa-star"></i> <?= number_format($pkg['rating'], 1) ?> <span style="opacity:0.8; font-weight:500;">(<?= $pkg['reviews'] ?> reviews)</span></div>
                        <?php endif; ?>
                        <?php if ($pkg['booked_count']): ?>
                            <div class="pkgdet-social-badge"><i class="fas fa-fire"></i> <?= htmlspecialchars($pkg['booked_count']) ?> booked</div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                $imgCount = count($pkg['images']);
                $galleryClass = $imgCount <= 1 ? 'count-1' : ($imgCount === 2 ? 'count-2' : 'count-multi');
                $visibleImages = $imgCount > 4 ? array_slice($pkg['images'], 0, 4) : $pkg['images'];
                ?>
                <div class="pkgdet-gallery <?= $galleryClass ?>" id="pkgdetGallery">
                    <?php if ($imgCount === 0): ?>
                        <div class="pkgdet-gtile" onclick="openLightbox(0)">
                            <img src="images/placeholder-dest.jpg" alt="<?= htmlspecialchars($pkg['name']) ?>" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27800%27 height=%27440%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23e2e8f0%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 font-family=%27sans-serif%27 font-size=%2724%27 fill=%27%2394a3b8%27 text-anchor=%27middle%27%3ENo Photos Available%3C/text%3E%3C/svg%3E'">
                        </div>
                    <?php else: foreach ($visibleImages as $i => $img): ?>
                        <div class="pkgdet-gtile" onclick="openLightbox(<?= $i ?>)">
                            <img src="<?= htmlspecialchars(resolveImgSrc($img)) ?>" alt="<?= htmlspecialchars($pkg['name']) ?> photo <?= $i + 1 ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                            <?php if ($i === 3 && $imgCount > 4): ?>
                                <div class="pkgdet-more-overlay">+<?= $imgCount - 4 ?> Photos</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="pkgdet-body">
                    <div class="pkgdet-main">
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-info-circle"></i> Overview</h2>
                            <div class="pkgdet-chips">
                                <div class="pkgdet-chip"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($pkg['duration']) ?></div>
                                <div class="pkgdet-chip"><i class="fas fa-users"></i> <?= htmlspecialchars($pkg['group_size']) ?></div>
                                <div class="pkgdet-chip"><i class="fas fa-sun"></i> <?= htmlspecialchars($pkg['best_season']) ?></div>
                                <?php if ($pkg['category']): ?><div class="pkgdet-chip"><i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($pkg['category'])) ?></div><?php endif; ?>
                                <?php if ($pkg['badge']): ?><div class="pkgdet-chip"><i class="fas fa-award"></i> <?= htmlspecialchars($pkg['badge']) ?></div><?php endif; ?>
                                <?php if ($pkg['activities_count'] > 0): ?><div class="pkgdet-chip"><i class="fas fa-hiking"></i> <?= $pkg['activities_count'] ?> Activities</div><?php endif; ?>
                            </div>
                            <p class="pkgdet-desc"><?= nl2br(htmlspecialchars($pkg['description'] ?: $pkg['short_description'] ?: 'Experience the beauty of this amazing destination.')) ?></p>
                        </div>

                        <?php if (!empty($pkg['itinerary'])): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-route"></i> Itinerary</h2>
                            <?php foreach ($pkg['itinerary'] as $day): ?>
                                <div class="pkgdet-day">
                                    <h4><?= htmlspecialchars(!empty($day['title']) ? $day['title'] : ('Day ' . ($day['day'] ?? ''))) ?></h4>
                                    <?php if (!empty($day['activities']) && is_array($day['activities'])): ?>
                                        <?php foreach ($day['activities'] as $act): ?><div class="pkgdet-activity-row"><i class="fas fa-circle"></i><span><?= htmlspecialchars($act) ?></span></div><?php endforeach; ?>
                                    <?php elseif (!empty($day['description'])): ?>
                                        <p style="margin:0; color:#475569; font-size:0.88rem;"><?= htmlspecialchars($day['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($pkg['inclusions']) || !empty($pkg['exclusions'])): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-clipboard-check"></i> Inclusions &amp; Exclusions</h2>
                            <div class="pkgdet-two-col">
                                <div>
                                    <h4 style="color:#15803d; font-size:0.9rem; margin:0 0 12px;">Included</h4>
                                    <?php if (empty($pkg['inclusions'])): ?><p style="color:#94a3b8; font-size:0.85rem;">Nothing listed.</p><?php endif; ?>
                                    <?php foreach ($pkg['inclusions'] as $i): ?><div class="pkgdet-line-row inc"><span class="dot"><i class="fas fa-check"></i></span><span><?= htmlspecialchars($i) ?></span></div><?php endforeach; ?>
                                </div>
                                <div>
                                    <h4 style="color:#b91c1c; font-size:0.9rem; margin:0 0 12px;">Not Included</h4>
                                    <?php if (empty($pkg['exclusions'])): ?><p style="color:#94a3b8; font-size:0.85rem;">Nothing listed.</p><?php endif; ?>
                                    <?php foreach ($pkg['exclusions'] as $e): ?><div class="pkgdet-line-row exc"><span class="dot"><i class="fas fa-times"></i></span><span><?= htmlspecialchars($e) ?></span></div><?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($pkg['hotels'])): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-hotel"></i> Hotel Options</h2>
                            <?php foreach ($pkg['hotels'] as $h): ?>
                                <div class="pkgdet-hotel-row">
                                    <span><?= htmlspecialchars($h['name'] ?? '') ?> <?= !empty($h['stars']) ? str_repeat('⭐', intval($h['stars'])) : '' ?></span>
                                    <span style="color:#16a34a; font-weight:700;"><?= (!empty($h['price']) && floatval($h['price']) > 0) ? '+' . $pkg['currency'] . number_format(floatval($h['price'])) : 'Included' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($pkg['partner_id']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-handshake"></i> Provided By</h2>
                            <div class="pkgdet-partner-box">
                                <div class="icon"><i class="fas fa-store"></i></div>
                                <div>
                                    <a href="view-partner-profile.php?id=<?= intval($pkg['partner_id']) ?>&from_type=<?= urlencode($type) ?>&from_id=<?= urlencode($identifier) ?>"><?= htmlspecialchars($pkg['partner_company'] ?: 'Partner Provider') ?></a>
                                    <p style="margin:2px 0 0; color:#64748b; font-size:0.82rem;">This package is offered by one of our trusted partners.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($pkg['remarks'] || $pkg['blocked_months'] || $pkg['blocked_dates'] || $pkg['promo_start'] || $pkg['promo_end']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-exclamation-circle"></i> Good to Know</h2>
                            <?php if ($pkg['remarks']): ?><p class="pkgdet-desc" style="margin-bottom:14px;"><?= nl2br(htmlspecialchars($pkg['remarks'])) ?></p><?php endif; ?>
                            <?php if ($pkg['promo_start'] || $pkg['promo_end']): ?>
                                <p style="color:#0f172a; font-size:0.85rem; margin:0 0 10px;"><i class="fas fa-calendar-check" style="color:#003580;"></i> Travel validity:
                                    <strong><?= $pkg['promo_start'] ? date('M j, Y', strtotime($pkg['promo_start'])) : 'Anytime' ?></strong> to
                                    <strong><?= $pkg['promo_end'] ? date('M j, Y', strtotime($pkg['promo_end'])) : 'Anytime' ?></strong>
                                </p>
                            <?php endif; ?>
                            <?php if ($pkg['blocked_months']): ?>
                                <p style="color:#b45309; font-size:0.85rem; margin:0 0 4px;"><i class="fas fa-calendar-times"></i> Unavailable months</p>
                                <div class="pkgdet-pills">
                                    <?php foreach ($pkg['blocked_months'] as $m): ?><span class="pkgdet-pill"><?= htmlspecialchars(PKGDET_MONTH_NAMES[intval($m)] ?? $m) ?></span><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($pkg['blocked_dates']): ?>
                                <p style="color:#b45309; font-size:0.85rem; margin:14px 0 4px;"><i class="fas fa-calendar-times"></i> Unavailable dates</p>
                                <div class="pkgdet-pills">
                                    <?php foreach ($pkg['blocked_dates'] as $d): $ts = strtotime($d); ?><span class="pkgdet-pill"><?= htmlspecialchars($ts !== false ? date('M j, Y', $ts) : $d) ?></span><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkgdet-sticky">
                        <div class="pkgdet-price-card">
                            <?php if ($pkg['discount_percent'] > 0): ?><span class="pkgdet-discount-badge"><?= $pkg['discount_percent'] ?>% OFF</span><br><?php endif; ?>
                            <?php if ($pkg['original_price'] > $pkg['price'] && $pkg['original_price'] > 0): ?><span class="pkgdet-price-orig"><?= $pkg['currency'] ?><?= number_format($pkg['original_price']) ?></span><?php endif; ?>
                            <span class="pkgdet-price-now"><?= $pkg['currency'] ?><?= number_format($pkg['price']) ?></span>
                            <div class="pkgdet-price-per">per person</div>
                            <button class="pkgdet-book-btn" onclick="startPackageBooking()"><i class="fas fa-bolt"></i> Book This Deal</button>
                            <div class="pkgdet-price-meta">
                                <div><span>Duration</span><strong><?= htmlspecialchars($pkg['duration']) ?></strong></div>
                                <div><span>Group Size</span><strong><?= htmlspecialchars($pkg['group_size']) ?></strong></div>
                                <div><span>Travel Validity</span><strong><?= htmlspecialchars($pkg['best_season']) ?></strong></div>
                            </div>
                        </div>
                    </div>
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

    <!-- Lightbox -->
    <div class="pkgdet-lightbox" id="pkgdetLightbox">
        <button class="pkgdet-lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
        <button class="pkgdet-lightbox-prev" onclick="shiftLightbox(-1)"><i class="fas fa-chevron-left"></i></button>
        <img id="pkgdetLightboxImg" src="" alt="Photo">
        <button class="pkgdet-lightbox-next" onclick="shiftLightbox(1)"><i class="fas fa-chevron-right"></i></button>
        <div class="pkgdet-lightbox-counter" id="pkgdetLightboxCounter"></div>
    </div>

    <script>
        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser(); echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';

        const PKG_IMAGES = <?= json_encode(array_map('resolveImgSrc', $pkg['images'] ?? [])) ?>;
        let pkgdetLightboxIndex = 0;

        function openLightbox(index) {
            if (!PKG_IMAGES.length) return;
            pkgdetLightboxIndex = index;
            renderLightbox();
            document.getElementById('pkgdetLightbox').classList.add('active');
        }
        function closeLightbox() {
            document.getElementById('pkgdetLightbox').classList.remove('active');
        }
        function shiftLightbox(dir) {
            pkgdetLightboxIndex = (pkgdetLightboxIndex + dir + PKG_IMAGES.length) % PKG_IMAGES.length;
            renderLightbox();
        }
        function renderLightbox() {
            document.getElementById('pkgdetLightboxImg').src = PKG_IMAGES[pkgdetLightboxIndex];
            document.getElementById('pkgdetLightboxCounter').innerText = (pkgdetLightboxIndex + 1) + ' / ' + PKG_IMAGES.length;
        }
        document.getElementById('pkgdetLightbox').addEventListener('click', (e) => {
            if (e.target.id === 'pkgdetLightbox') closeLightbox();
        });
        document.addEventListener('keydown', (e) => {
            if (!document.getElementById('pkgdetLightbox').classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') shiftLightbox(-1);
            if (e.key === 'ArrowRight') shiftLightbox(1);
        });

        // Package pages are linked from many places (home, listing pages, search,
        // saved items, partner profiles) -- prefer real browser history so "Back"
        // returns wherever the user actually came from, and only fall back to the
        // matching listing page when there's no same-site history to go back to
        // (e.g. the page was opened directly or in a new tab).
        function goBackFromPackageDetails(fallbackUrl) {
            const cameFromThisSite = document.referrer && document.referrer.indexOf(window.location.host) !== -1;
            if (cameFromThisSite && window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = fallbackUrl;
            }
        }

        // "Book This Deal" now goes to its own dedicated checkout page
        // instead of opening the booking modal in place.
        function startPackageBooking() {
            const type = <?= json_encode($pkg['type'] ?? '') ?>;
            const identifier = <?= json_encode($pkg['identifier'] ?? '') ?>;
            window.location.href = 'package-book.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(identifier);
        }
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

<?php
// ========================================
// FILE: index.php
// DESCRIPTION: Homepage with database integration for local destinations only
// Foreign destinations section now uses foreign-packages.js
// ========================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

// Self-healing: ensure partner_id column exists in destinations table
try {
    $cols = [];
    foreach ($pdo->query("SHOW COLUMNS FROM destinations") as $row) {
        $cols[$row['Field']] = true;
    }
    if (!isset($cols['partner_id'])) {
        $pdo->exec("ALTER TABLE destinations ADD COLUMN partner_id INT DEFAULT NULL");
    }
    if (!isset($cols['partner_company'])) {
        $pdo->exec("ALTER TABLE destinations ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    }
} catch (Throwable $e) {
    // Fail silently — table may not exist yet or already has the column
}

// Fetch local destinations from database for home page
$stmt = $pdo->prepare("
    SELECT 
        d.id, 
        d.name, 
        d.location_name, 
        d.city, 
        d.price, 
        d.currency,
        d.duration, 
        d.activities_count, 
        d.group_size, 
        d.best_season,
        d.itinerary, 
        d.inclusions, 
        d.exclusions,
        d.image_path,
        d.image2_path,
        d.image3_path,
        d.image4_path,
        d.description,
        d.collage_type,
        d.category,
        d.badge_text,
        d.hotels,
        d.remarks,
        d.promo_start,
        d.promo_end,
        d.blocked_months,
        d.highlight_duration,
        d.blocked_dates,
        d.partner_id,
        COALESCE(pr.business_display_name, p.company_name, d.partner_company) AS partner_company
    FROM destinations d
    LEFT JOIN partner_applications p ON d.partner_id = p.id
    LEFT JOIN partner_profiles pr ON pr.partner_id = d.partner_id
    WHERE d.type = 'local' AND d.is_active = 1 
    ORDER BY d.display_order, d.id ASC
");
$stmt->execute();
$home_local_destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process local destinations data for JavaScript
foreach ($home_local_destinations as &$dest) {
    $dest['location_name'] = $dest['location_name'] ?? $dest['city'] ?? 'Philippines';
    $dest['price'] = floatval($dest['price'] ?? 0);
    $dest['duration'] = $dest['duration'] ?? '3D/2N';
    $dest['activities_count'] = intval($dest['activities_count'] ?? 0);
    $dest['group_size'] = $dest['group_size'] ?? '2-15 pax';
    $dest['best_season'] = $dest['best_season'] ?? 'Year Round';
    $dest['description'] = $dest['description'] ?? 'Experience the beauty of this amazing destination.';
    $dest['remarks'] = $dest['remarks'] ?? '';

    // Make sure these fields are properly set for home-packages.js
    if ($dest['itinerary'] && is_string($dest['itinerary'])) {
        $dest['itinerary'] = json_decode($dest['itinerary'], true);
        if (!is_array($dest['itinerary']))
            $dest['itinerary'] = [];
    } else {
        $dest['itinerary'] = [];
    }

    if ($dest['inclusions'] && is_string($dest['inclusions'])) {
        $dest['inclusions'] = json_decode($dest['inclusions'], true);
        if (!is_array($dest['inclusions']))
            $dest['inclusions'] = [];
    } else {
        $dest['inclusions'] = [];
    }

    if ($dest['exclusions'] && is_string($dest['exclusions'])) {
        $dest['exclusions'] = json_decode($dest['exclusions'], true);
        if (!is_array($dest['exclusions']))
            $dest['exclusions'] = [];
    } else {
        $dest['exclusions'] = [];
    }

    if ($dest['hotels'] && is_string($dest['hotels'])) {
        $dest['hotels'] = json_decode($dest['hotels'], true);
        if (!is_array($dest['hotels']))
            $dest['hotels'] = [];
    } else {
        $dest['hotels'] = [];
    }

    if (!empty($dest['partner_id'])) {
        $dest['partner_source'] = 'partner_package';
        $dest['partner_package_name'] = $dest['name'];
    } else {
        $dest['partner_source'] = null;
        $dest['partner_package_name'] = null;
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream Travel & Tours - Home</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 for premium alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Additional styles for loading spinners */
        .loading-spinner {
            text-align: center;
            padding: 40px;
            grid-column: 1 / -1;
        }

        .loading-spinner i {
            font-size: 2rem;
            color: #003580;
            animation: spin 1s linear infinite;
        }

        .loading-spinner p {
            margin-top: 10px;
            color: #666;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Foreign destination card styles for homepage */
        .foreign-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            animation: fadeInUp 0.4s ease;
            display: flex;
            flex-direction: column;
            height: 430px;
            min-width: 280px;
            width: 280px;
        }

        .foreign-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.15);
        }

        .foreign-card-image {
            position: relative;
            height: 210px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .foreign-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .foreign-card:hover .foreign-card-image img {
            transform: scale(1.05);
        }

        /* Moved off the photo (was position:absolute over the image, blocking
           it) into the card's details section instead. */
        .foreign-card-badge-inline {
            align-self: flex-start;
            flex-shrink: 0;
            display: inline-block;
            background: #ff9800;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 8px;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .foreign-card-content {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .foreign-card-name,
        .home-card-name,
        .destination-name-local {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px 15px 15px;
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff !important;
            margin: 0;
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(to top, rgba(0, 53, 128, 0.95) 0%, rgba(0, 53, 128, 0.6) 60%, transparent 100%);
            z-index: 3;
            line-height: 1.2;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8), 0 1px 2px rgba(0, 0, 0, 0.5);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 70px;
            display: flex;
            align-items: flex-end;
        }

        .foreign-card-location,
        .home-card-location {
            color: #666;
            font-size: 0.7rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .foreign-card-location i,
        .home-card-location i {
            color: #ff9800;
            font-size: 0.65rem;
        }

        .foreign-card-desc {
            color: #555;
            font-size: 0.75rem;
            line-height: 1.4;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 50px;
        }

        .home-card-desc {
            color: #555;
            font-size: 0.75rem;
            line-height: 1.4;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 50px;
        }

        .foreign-card-price {
            font-size: 1rem;
            font-weight: 700;
            color: #ff9800;
            margin-bottom: 6px;
            margin-top: auto;
        }

        .foreign-card-price small {
            font-size: 0.65rem;
            color: #888;
            font-weight: normal;
        }

        .foreign-card-btn,
        .home-card-btn {
            background: #003580;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .foreign-card-btn:hover,
        .home-card-btn:hover {
            background: #ff9800;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.3);
            color: white;
        }

        /* =============================================
           Flash Deal Cards Styles (Version 5.0)
           ============================================= */
        .flash-deal-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            animation: fadeInUp 0.4s ease;
            display: flex;
            flex-direction: column;
            height: 430px;
            min-width: 280px;
            width: 280px;
        }

        .flash-deal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(255, 152, 0, 0.15);
        }

        .flash-deal-image {
            position: relative;
            height: 210px;
            overflow: hidden;
        }

        /* Image Collage Grid Styles */
        .image-collage {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .collage-three {
            display: flex;
            gap: 2px;
            height: 100%;
        }

        .collage-main {
            flex: 2;
            height: 100%;
        }

        .collage-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .collage-stack {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
            height: 100%;
        }

        .collage-stack img {
            width: 100%;
            height: calc(50% - 1px);
            object-fit: cover;
        }

        .collage-half {
            display: flex;
            gap: 2px;
            height: 100%;
        }

        .collage-half img {
            width: 50%;
            height: 100%;
            object-fit: cover;
        }

        .flash-deal-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ff9800;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .flash-deal-badge-red {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .flash-deal-content {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .flash-deal-category {
            color: #ff9800;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .flash-deal-title {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px 15px 15px;
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff !important;
            margin: 0;
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(to top, rgba(0, 53, 128, 0.95) 0%, rgba(0, 53, 128, 0.6) 60%, transparent 100%);
            z-index: 3;
            line-height: 1.2;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8), 0 1px 2px rgba(0, 0, 0, 0.5);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 70px;
            display: flex;
            align-items: flex-end;
        }

        .flash-deal-location {
            color: #666;
            font-size: 0.7rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .flash-deal-location i {
            color: #ff9800;
            font-size: 0.65rem;
        }

        .flash-deal-desc {
            color: #555;
            font-size: 0.75rem;
            line-height: 1.4;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 50px;
        }

        .flash-deal-price-section {
            margin-bottom: 8px;
            margin-top: auto;
        }

        .flash-deal-current-price {
            font-size: 1rem;
            font-weight: 700;
            color: #ff9800;
        }

        .flash-deal-current-price small {
            font-size: 0.65rem;
            color: #888;
            font-weight: normal;
        }

        .flash-deal-btn {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: #ffffff !important;
            border: none;
            padding: 12px 18px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .flash-deal-btn:hover {
            background: #003580;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 53, 128, 0.3);
            color: white;
        }

        .home-card-btn {
            background: #003580;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0;
        }

        .home-card-btn:hover {
            background: #ff9800;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.3);
            color: white;
        }

        /* Scrollable containers */
        .scrollable-container {
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: #ff9800 #e0e0e0;
        }

        .scrollable-container::-webkit-scrollbar {
            height: 8px;
        }

        .scrollable-container::-webkit-scrollbar-track {
            background: #e0e0e0;
            border-radius: 10px;
        }

        .scrollable-container::-webkit-scrollbar-thumb {
            background: #ff9800;
            border-radius: 10px;
        }

        .scrollable-container::-webkit-scrollbar-thumb:hover {
            background: #003580;
        }

        .foreign-destinations-grid,
        .popular-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 0;
            min-width: min-content;
        }

        /* ── Tablet (≤768px) ── */
        @media (max-width: 768px) {

            .foreign-destinations-grid,
            .popular-grid {
                gap: 15px;
            }

            /* All card types — same size */
            .foreign-card,
            .home-destination-card,
            .popular-card,
            .flash-deal-card {
                min-width: 260px !important;
                width: 260px !important;
                height: 460px !important;
            }

            /* Image areas scale proportionally */
            .foreign-card-image,
            .home-card-image,
            .card-image,
            .flash-deal-image {
                height: 190px !important;
            }
        }

        /* ── Mobile (≤480px) ── */
        @media (max-width: 480px) {

            .foreign-destinations-grid,
            .popular-grid {
                gap: 12px;
            }

            .foreign-card,
            .home-destination-card,
            .popular-card,
            .flash-deal-card {
                min-width: 240px !important;
                width: 240px !important;
                height: 440px !important;
            }

            .foreign-card-image,
            .home-card-image,
            .card-image,
            .flash-deal-image {
                height: 175px !important;
            }
        }

        /* ── Very Small Phones (≤360px) ── */
        @media (max-width: 360px) {

            .foreign-card,
            .home-destination-card,
            .popular-card,
            .flash-deal-card {
                min-width: 210px !important;
                width: 210px !important;
                height: 420px !important;
            }

            .foreign-card-image,
            .home-card-image,
            .card-image,
            .flash-deal-image {
                height: 160px !important;
            }
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

        .section-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title-popular {
            font-size: 1.5rem;
            font-weight: 700;
            color: #003580;
            margin: 0;
            position: relative;
            padding-left: 15px;
            border-left: 4px solid #ff9800;
        }

        .view-all-link {
            color: #ff9800;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .view-all-link:hover {
            color: #003580;
            transform: translateX(5px);
        }

        .popular-scroll-container {
            overflow-x: auto;
            overflow-y: visible;
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: #ff9800 #e0e0e0;
        }

        .popular-scroll-container::-webkit-scrollbar {
            height: 8px;
        }

        .popular-scroll-container::-webkit-scrollbar-track {
            background: #e0e0e0;
            border-radius: 10px;
        }

        .popular-scroll-container::-webkit-scrollbar-thumb {
            background: #ff9800;
            border-radius: 10px;
        }


        /* Popular card styles for local destinations */
        .popular-card,
        .home-destination-card,
        .foreign-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            animation: fadeInUp 0.4s ease;
            display: flex;
            flex-direction: column;
            height: 430px;
            min-width: 280px;
            width: 280px;
        }

        .popular-card:hover,
        .home-destination-card:hover,
        .foreign-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.15);
        }

        .card-image,
        .home-card-image,
        .foreign-card-image {
            position: relative;
            height: 210px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .card-image img,
        .home-card-image img,
        .foreign-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .popular-card:hover .card-image img,
        .home-destination-card:hover .home-card-image img,
        .foreign-card:hover .foreign-card-image img {
            transform: scale(1.05);
        }

        /* HORIZONTAL LAYOUT FOR DESKTOP (Consistent with Flash Deals) */
        @media (min-width: 992px) {

            .popular-card,
            .home-destination-card,
            .foreign-card {
                flex-direction: row !important;
                height: 230px !important;
                min-height: 230px !important;
                width: 450px !important;
                min-width: 450px !important;
                flex: 0 0 450px !important;
            }

            .card-image,
            .home-card-image,
            .foreign-card-image {
                width: 45%;
                height: 100% !important;
                border-radius: 16px 0 0 16px !important;
            }

            .home-card-name,
            .foreign-card-name,
            .destination-name-local {
                border-radius: 0 0 0 16px !important;
            }

            .card-content,
            .home-card-content,
            .foreign-card-content {
                width: 55%;
                padding: 15px;
            }
        }

        /* Moved off the photo (was position:absolute over the image, blocking
           it) into the card's details section instead -- same treatment as
           .foreign-card-badge-inline. */
        .card-badge {
            position: static;
            align-self: flex-start;
            flex-shrink: 0;
            display: inline-block;
            background: #ff9800;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 8px;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-content,
        .home-card-content,
        .foreign-card-content {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .home-card-name,
        .foreign-card-name,
        .flash-deal-title,
        .destination-name-local {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px 15px 15px;
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(to top, rgba(0, 53, 128, 0.95) 0%, rgba(0, 53, 128, 0.6) 60%, transparent 100%);
            z-index: 3;
            line-height: 1.2;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .destination-desc {
            color: #666;
            font-size: 0.7rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .package-info,
        .home-card-footer,
        .foreign-card-footer,
        .flash-deal-footer {
            margin-top: auto;
            display: flex;
            flex-direction: column;
        }

        .home-card-price,
        .foreign-card-price,
        .flash-deal-price-section {
            margin-bottom: 8px;
        }

        .package-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: #555;
        }

        .package-row .price {
            font-size: 1rem;
            font-weight: 700;
            color: #ff9800;
        }

        .view-package-btn {
            background: #003580;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-align: center;
        }

        .view-package-btn:hover {
            background: #ff9800;
            transform: scale(1.02);
        }

        /* Icon buttons styles moved to css/buttons.css */


        /* Search Results Message Styles */
        .search-results-message {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            padding: 12px 20px;
            border-radius: 40px;
            margin-bottom: 25px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            position: relative;
            z-index: 2000;
            pointer-events: auto;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
        }

        .search-results-message i {
            font-size: 1rem;
        }

        .clear-search-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }

        .clear-search-btn:hover {
            background: #ff9800;
            transform: scale(1.05);
        }

        .search-highlight {
            background: rgba(255, 152, 0, 0.3);
            border-radius: 4px;
            padding: 0 2px;
        }

        .no-results-message {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
            background: white;
            border-radius: 20px;
            margin: 20px 0;
        }

        .no-results-message i {
            font-size: 3rem;
            color: #ff9800;
            margin-bottom: 15px;
            opacity: 0.6;
        }

        .no-results-message h3 {
            color: #003580;
            margin-bottom: 10px;
        }

        .no-results-message p {
            color: #666;
        }

        /* CORRECT LAYERING:
           - .hero = NO z-index (does not create a stacking context)
           - .hero-overlay = z-index: 2 (within hero)
           - .hero-content = z-index: 3 (above overlay, creates its own stacking context)
           - icon-buttons = z-index: 200 (in root stacking context, above hero-content:3)
           - autocomplete = position:fixed z-index:99999 (escapes all stacking contexts)
        */
        .hero {
            position: relative;
            /* NO z-index intentionally — prevents hero from creating a stacking context
               that would trap the search bar below the buttons */
        }

        .hero-content {
            position: relative;
            z-index: 3;
            /* Above hero-overlay (z-index:2) so search bar is clickable */
            pointer-events: auto;
        }

        .transparent-search-wrapper {
            position: relative;
            z-index: 4;
        }

        /* ---- Hero search bar: destination + dates + travelers pill ---- */
        .hd-searchbar {
            display: flex;
            align-items: stretch;
            background: #fff;
            border-radius: 60px;
            box-shadow: 0 12px 34px rgba(0, 20, 60, 0.28);
            padding: 6px;
            max-width: 980px;
            margin: 0 auto;
            gap: 2px;
        }

        .hd-search-field {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            flex: 1 1 0;
            min-width: 0;
            border-radius: 40px;
            position: relative;
            transition: background 0.18s ease;
        }

        .hd-search-where { flex: 2 1 0; }

        .hd-search-field:hover { background: #f3f6fb; }

        .hd-search-field-icon {
            color: #003580;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .hd-search-field-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            text-align: left;
        }

        .hd-search-field-text label {
            font-size: 0.68rem;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 1px;
        }

        .hd-search-field-text input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.9rem;
            font-family: inherit;
            color: #1e293b;
            padding: 0;
            width: 100%;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .hd-search-field-text input::placeholder { color: #94a3b8; }

        .hd-search-field-text span {
            font-size: 0.9rem;
            color: #1e293b;
        }

        .hd-search-divider {
            width: 1px;
            background: #e2e8f0;
            margin: 10px 0;
            flex-shrink: 0;
        }

        .hd-search-submit {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: #fff;
            border: none;
            border-radius: 46px;
            padding: 0 22px;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(255, 152, 0, 0.35);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .hd-search-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.45);
        }

        /* Visible close ("x") button injected into the hero date pickers */
        .flatpickr-calendar .hd-fp-close {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .flatpickr-calendar .hd-fp-close:hover {
            background: #ff9800;
            color: #fff;
        }

        /* Below 900px the hd-searchbar drops the single-row pill layout for a
           stacked card (destination on its own row, dates side-by-side, full-
           width search button). That card is taller than the compact desktop
           pill, so the hero section -- which normally has a fixed height
           tuned for the single-row pill -- needs to grow with it instead of
           clipping it (it previously did, via `.hero { overflow:hidden }` /
           a fixed min-height in css/hero.css). These rules load after that
           stylesheet, so on an equal-specificity/!important tie they win. */
        @media (max-width: 900px) {
            .hero {
                height: auto !important;
                min-height: 360px !important;
                padding-bottom: 28px !important;
                overflow: visible !important;
            }

            .hd-searchbar {
                flex-wrap: wrap;
                border-radius: 20px;
                padding: 8px;
                gap: 0;
                box-shadow: 0 16px 36px rgba(0, 20, 60, 0.32);
            }
            .hd-search-where {
                flex: 1 1 100%;
                padding: 13px 14px;
                border-bottom: 1px solid #eef2f7;
                border-radius: 14px 14px 0 0;
            }
            .hd-search-date {
                flex: 1 1 50%;
                padding: 12px 14px;
            }
            #heroCheckInField { border-right: 1px solid #eef2f7; }
            .hd-search-divider { display: none; }
            .hd-search-submit {
                flex: 1 1 100%;
                border-radius: 14px;
                padding: 14px;
                margin-top: 8px;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .hero {
                min-height: 420px !important;
                padding-top: 58px !important;
            }
            .hd-search-field-text label { font-size: 0.64rem; }
            .hd-search-field-text input { font-size: 0.86rem; }
        }

        /* Make sure navbar is at the absolute front */
        .navbar {
            z-index: 999999 !important;
        }

        /* SweetAlert2's default z-index (1060) sits well below the navbar's
           999999, so toasts/alerts would render hidden behind it otherwise. */
        .swal2-container {
            z-index: 9999999 !important;
        }




        @media (max-width: 768px) {
            .autocomplete-dropdown {
                max-height: 350px;
            }

            .autocomplete-item {
                padding: 12px 15px;
            }

            .autocomplete-title {
                font-size: 0.85rem;
            }

            .autocomplete-icon {
                width: 38px;
                height: 38px;
            }

            .autocomplete-icon i {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .autocomplete-dropdown {
                max-height: 300px;
            }

            .autocomplete-item {
                padding: 10px 12px;
                gap: 10px;
            }

            .autocomplete-title {
                font-size: 0.8rem;
            }

            .autocomplete-section-header {
                padding: 8px 12px;
                font-size: 0.7rem;
            }
        }



        /* Search Results Popup Modal - HIGH Z-INDEX TO APPEAR ABOVE ALL */
        .search-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 9999999 !important;
            backdrop-filter: blur(10px);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .search-popup-overlay.active {
            display: flex;
        }

        .search-popup-container {
            background: white;
            border-radius: 28px;
            width: 90%;
            max-width: 950px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: modalSlideIn 0.3s ease;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 9999999;
        }

        .search-popup-header {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            padding: 20px 25px;
            border-radius: 28px 28px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .search-popup-header h2 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-popup-header h2 i {
            font-size: 1.2rem;
        }

        .close-search-popup {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            padding: 8px 20px;
            border-radius: 40px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .close-search-popup:hover {
            background: #ff9800;
            transform: scale(1.03);
        }

        .search-popup-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            max-height: calc(85vh - 80px);
        }

        .search-popup-body::-webkit-scrollbar {
            width: 8px;
        }

        .search-popup-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .search-popup-body::-webkit-scrollbar-thumb {
            background: #ff9800;
            border-radius: 10px;
        }

        /* Search Stats */
        .search-stats {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border: 1px solid #e0e0e0;
        }

        .search-term-badge {
            background: #003580;
            color: white;
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-term-badge i {
            font-size: 0.85rem;
        }

        .search-results-count {
            color: #666;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Search Result Sections */
        .search-result-section {
            margin-bottom: 30px;
        }

        .search-result-section h3 {
            color: #003580;
            margin-bottom: 15px;
            font-size: 1.1rem;
            padding-bottom: 10px;
            border-bottom: 3px solid #ff9800;
            display: inline-block;
            font-weight: 600;
        }

        .search-result-section h3 i {
            margin-right: 8px;
            color: #ff9800;
        }

        .search-result-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .search-result-item {
            display: flex;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .search-result-item:hover {
            transform: translateX(5px);
            border-color: #ff9800;
            box-shadow: 0 5px 20px rgba(255, 152, 0, 0.15);
            background: #fffdf9;
        }

        .search-result-image {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            background: #e0e0e0;
        }

        .search-result-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .search-result-info {
            flex: 1;
            padding: 12px 15px;
        }

        .search-result-title {
            font-size: 1rem;
            font-weight: 700;
            color: #003580;
            margin-bottom: 4px;
            font-family: 'Poppins', sans-serif;
        }

        .search-result-location {
            font-size: 0.7rem;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .search-result-location i {
            color: #ff9800;
            font-size: 0.65rem;
        }

        .search-result-desc {
            font-size: 0.7rem;
            color: #777;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .search-result-price {
            font-size: 0.85rem;
            font-weight: 700;
            color: #ff9800;
            margin-top: 4px;
        }

        /* Flash Deal specific styling */
        .search-result-item.flash-deal .search-result-title {
            color: #dc3545;
        }

        .flash-deal-badge-small {
            display: inline-block;
            background: #dc3545;
            color: white;
            font-size: 0.6rem;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            vertical-align: middle;
        }

        /* Empty State */
        .search-empty-state {
            text-align: center;
            padding: 50px 20px;
        }

        .search-empty-state i {
            font-size: 3rem;
            color: #ff9800;
            margin-bottom: 15px;
            opacity: 0.6;
        }

        .search-empty-state h3 {
            color: #003580;
            margin-bottom: 10px;
        }

        .search-empty-state p {
            color: #666;
        }

        /* No Results */
        .search-no-results {
            text-align: center;
            padding: 50px 30px;
            background: #f8f9fa;
            border-radius: 20px;
            margin: 20px 0;
        }

        .search-no-results i {
            font-size: 3rem;
            color: #ff9800;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .search-no-results h4 {
            color: #003580;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .search-no-results p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Loading State */
        .search-loading {
            text-align: center;
            padding: 60px;
        }

        .search-loading i {
            font-size: 2.5rem;
            color: #ff9800;
            animation: spin 1s linear infinite;
        }

        /* Search Suggestions */
        .search-suggestions {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .search-suggestions h4 {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 12px;
            font-weight: 500;
        }

        .suggestion-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .suggestion-tag {
            background: #f0f0f0;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #555;
            font-weight: 500;
        }

        .suggestion-tag:hover {
            background: #ff9800;
            color: white;
            transform: translateY(-2px);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 768px) {
            .search-popup-container {
                width: 95%;
                max-height: 90vh;
            }

            .search-popup-header {
                padding: 15px 20px;
            }

            .search-popup-header h2 {
                font-size: 1rem;
            }

            .close-search-popup {
                padding: 6px 15px;
                font-size: 0.85rem;
            }

            .search-result-image {
                width: 75px;
                height: 75px;
            }

            .search-result-title {
                font-size: 0.85rem;
            }

            .search-stats {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px 15px;
            }

            .search-term-badge {
                font-size: 0.8rem;
            }

            .suggestion-tag {
                padding: 4px 12px;
                font-size: 0.7rem;
            }
        }

        /* Auto-complete Dropdown */
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-top: 8px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 99999;
            padding: 10px 0;
        }

        .autocomplete-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: background 0.2s;
            text-align: left;
        }

        .autocomplete-item:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .autocomplete-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .autocomplete-details {
            flex: 1;
            overflow: hidden;
        }

        .autocomplete-title {
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .autocomplete-desc {
            color: #666;
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .autocomplete-section-header {
            padding: 10px 20px 6px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #ff9800;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @keyframes flashPulse {
            0% {
                transform: scale(1);
                box-shadow: 0 4px 15px rgba(255, 42, 42, 0.3);
            }

            50% {
                transform: scale(1.1);
                box-shadow: 0 8px 25px rgba(255, 42, 42, 0.6);
            }

            100% {
                transform: scale(1);
                box-shadow: 0 4px 15px rgba(255, 42, 42, 0.3);
            }
        }

        @keyframes textShimmer {
            0% {
                text-shadow: 1px 1px 0px rgba(255, 42, 42, 0.1);
            }

            50% {
                text-shadow: 0 0 15px rgba(255, 42, 42, 0.4);
            }

            100% {
                text-shadow: 1px 1px 0px rgba(255, 42, 42, 0.1);
            }
        }

        /* --- Localista Logo Styling --- */
        .company-logo-img {
            height: 80px;
            /* Desktop Size - You can edit this */
            object-fit: contain;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .company-logo-img:hover {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .company-logo-img {
                height: 45px;
                /* Mobile Size - You can edit this! */
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>

<body>

    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='index.php'">
            <div class="company-name">
                <img src="images/Localista (1).png" alt="HeyDream Travel and Tours"
                    class="company-logo-img"
                    onclick="window.location.href='index.php'">
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
                            <a href="User Account/my-profile.php"><i class="fas fa-user-edit"></i> My Profile</a>
                            <a href="saved.php"><i class="fas fa-heart"></i> Saved Items</a>
                            <a href="User Account/vouchers.php"><i class="fas fa-ticket-alt"></i> Vouchers</a>
                            <div class="dropdown-divider"></div>
                            <a href="#" onclick="event.preventDefault(); showLogoutConfirmPopup();" class="logout-link"><i
                                    class="fas fa-sign-out-alt"></i>
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

        <!-- Collapse Toggle Button -->

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
                <i class="fas fa-chevron-right sidebar-chevron"></i>
                <span class="sidebar-tooltip">Home</span>
            </a>

            <a href="local-destination.php" class="sidebar-nav-item" id="nav-local">
                <i class="fas fa-location-dot sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Local Tours</span>
                <i class="fas fa-chevron-right sidebar-chevron"></i>
                <span class="sidebar-tooltip">Local Tours</span>
            </a>

            <a href="foreign-destinations.php" class="sidebar-nav-item" id="nav-foreign">
                <i class="fas fa-plane sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Foreign Tours</span>
                <i class="fas fa-chevron-right sidebar-chevron"></i>
                <span class="sidebar-tooltip">Foreign Tours</span>
            </a>

            <a href="flash-deals.php" class="sidebar-nav-item" id="nav-deals">
                <i class="fas fa-tag sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Flash Deals</span>
                <i class="fas fa-chevron-right sidebar-chevron"></i>
                <span class="sidebar-tooltip">Flash Deals</span>
            </a>



            <!-- My Booking Link -->
            <button class="sidebar-nav-item" id="nav-my-booking" onclick="requireLogin('goToProfile')">
                <i class="fas fa-calendar-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">My Booking</span>
                <i class="fas fa-chevron-right sidebar-chevron"></i>
                <span class="sidebar-tooltip">My Booking</span>
            </button>

            <!-- My Account dropdown -->
            <button class="sidebar-nav-item" id="nav-account-toggle"
                onclick="toggleSidebarDropdown('accountDropdown', this)">
                <i class="fas fa-user sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">My Account</span>
                <i class="fas fa-chevron-right sidebar-chevron"></i>
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
                <i class="fas fa-chevron-right sidebar-chevron"></i>
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
                <i class="fas fa-chevron-right sidebar-chevron"></i>
                <span class="sidebar-tooltip">Help &amp; Support</span>
            </button>
            <div class="sidebar-dropdown-content" id="helpDropdown">
                <a href="help-support.php" class="sidebar-sub-item">
                    <i class="fas fa-question-circle" style="color:#003580;font-size:0.8rem;"></i> FAQs
                </a>
                <a href="#" onclick="openSupportChat(); return false;" class="sidebar-sub-item">
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
                <i class="fas fa-chevron-right sidebar-chevron"></i>
                <span class="sidebar-tooltip">Settings</span>
            </button>
            <div class="sidebar-dropdown-content" id="settingsDropdown">
                <a href="buttons/about.php" class="sidebar-sub-item">
                    <i class="fas fa-info-circle" style="color:#003580;"></i> About Us
                </a>
                <a href="terms.php" class="sidebar-sub-item">
                    <i class="fas fa-file-alt" style="color:#003580;"></i> Terms of Service
                </a> <a href="User Account/change-password.php" class="sidebar-sub-item" id="nav-change-password"
                    style="<?php echo $auth->isLoggedIn() ? 'display:block;' : 'display:none;'; ?>">
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


    <!-- HERO SECTION WITH SLIDING IMAGES -->
    <section class="hero" style="position: relative; overflow: visible;">
        <div class="sliding-images-container">
            <img src="images/siargao.jpg" alt="Siargao Island" class="active">
            <img src="images/boracay.jpg" alt="Boracay Beach">
            <img src="images/cebu.jpg" alt="Cebu">
            <img src="images/palawan.jpg" alt="Palawan">
            <img src="images/bohol.jpg" alt="Bohol">
            <img src="images/elnido.jpg" alt="El Nido">
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-content" style="position: relative; overflow: visible; z-index: 3; pointer-events: none;">

            <h1 class="hero-main-title" id="rotating-title" style="pointer-events: none;">Your journey begins here</h1>
            <div class="transparent-search-wrapper" style="position: relative; z-index: 4; pointer-events: auto;">
                <div class="search-container" style="position: relative; z-index: 5; pointer-events: auto;">
                    <div class="hd-searchbar" style="pointer-events: auto;">
                        <div class="hd-search-field hd-search-where">
                            <i class="fas fa-map-marker-alt hd-search-field-icon"></i>
                            <div class="hd-search-field-text">
                                <label for="globalSearchInput">Where to?</label>
                                <input type="text" id="globalSearchInput" placeholder="Search destinations, deals, visas..."
                                    autocomplete="off" style="pointer-events: auto; cursor: text;">
                            </div>
                        </div>

                        <div class="hd-search-divider"></div>

                        <div class="hd-search-field hd-search-date" id="heroCheckInField">
                            <i class="fas fa-calendar-alt hd-search-field-icon"></i>
                            <div class="hd-search-field-text">
                                <label for="heroCheckIn">Check-in</label>
                                <input type="text" id="heroCheckIn" placeholder="Add date" autocomplete="off" readonly style="pointer-events: auto; cursor: pointer;">
                            </div>
                        </div>

                        <div class="hd-search-divider"></div>

                        <div class="hd-search-field hd-search-date" id="heroCheckOutField">
                            <i class="fas fa-calendar-alt hd-search-field-icon"></i>
                            <div class="hd-search-field-text">
                                <label for="heroCheckOut">Check-out</label>
                                <input type="text" id="heroCheckOut" placeholder="Add date" autocomplete="off" readonly style="pointer-events: auto; cursor: pointer;">
                            </div>
                        </div>

                        <button class="hd-search-submit" id="globalSearchBtn" style="pointer-events: auto;" aria-label="Search">
                            <i class="fas fa-search"></i><span class="hd-search-submit-label">Search</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Autocomplete Dropdown - position:fixed outside hero so it is NOT trapped in hero-content's stacking context -->
    <div id="autocompleteDropdown" class="autocomplete-dropdown" style="display:none; position:fixed; z-index:99999;">
    </div>

    <!-- Search Results Message (hidden by default) -->
    <div id="searchResultsMessage" style="display: none; text-align: center; margin: 20px auto 0; max-width: 1200px;">
        <div class="search-results-message">
            <i class="fas fa-search"></i>
            <span id="searchResultsText">Searching...</span>
            <button class="clear-search-btn" id="clearSearchBtn">Clear Search <i class="fas fa-times"></i></button>
        </div>
    </div>


    <!-- SEARCH RESULTS POPUP MODAL - APPEARS ABOVE EVERYTHING -->
    <div id="searchPopupOverlay" class="search-popup-overlay">
        <div class="search-popup-container">
            <div class="search-popup-header">
                <h2 style="color: #ffffff;"><i class="fas fa-search"></i> Search Results</h2>
                <button class="close-search-popup" id="closeSearchPopup">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div class="search-popup-body" id="searchPopupBody">
                <div class="search-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p style="margin-top: 10px;">Searching for results...</p>
                </div>
            </div>
        </div>
    </div>

    <section class="icon-buttons-section">
        <div class="container">
            <div class="icon-buttons-grid">
                <div class="icon-button-item" onclick="window.location.href='buttons/flights.php'">
                    <div class="icon-circle"><i class="fas fa-plane"></i></div>
                    <span class="icon-label">Flights</span>
                </div>
                <div class="icon-button-item" onclick="window.location.href='buttons/hotel.php'">
                    <div class="icon-circle"><i class="fas fa-hotel"></i></div>
                    <span class="icon-label">Hotel</span>
                </div>
                <div class="icon-button-item" onclick="window.location.href='buttons/cruises.php'">
                    <div class="icon-circle"><i class="fas fa-ship"></i></div>
                    <span class="icon-label">Cruises</span>
                </div>
                <div class="icon-button-item" onclick="window.location.href='buttons/insurance.php'">
                    <div class="icon-circle"><i class="fas fa-shield-alt"></i></div>
                    <span class="icon-label">Insurance</span>
                </div>
                <div class="icon-button-item" onclick="window.location.href='buttons/visa.php'">
                    <div class="icon-circle"><i class="fas fa-passport"></i></div>
                    <span class="icon-label">Visa</span>
                </div>
                <div class="icon-button-item" onclick="window.location.href='buttons/experiences.php'">
                    <div class="icon-circle"><i class="fas fa-calendar-alt"></i></div>
                    <span class="icon-label">Experiences</span>
                </div>
                <div class="icon-button-item" onclick="window.location.href='buttons/about.php'">
                    <div class="icon-circle"><i class="fas fa-info-circle"></i></div>
                    <span class="icon-label">About Us</span>
                </div>
            </div>
        </div>
    </section>

    <style>
        @keyframes colorShimmer {
            0% { color: #003580; text-shadow: 0 0 0px rgba(0,53,128,0); transform: scale(1); }
            50% { color: #0055c8; text-shadow: 0 0 10px rgba(0,85,200,0.4); transform: scale(1.02); }
            100% { color: #003580; text-shadow: 0 0 0px rgba(0,53,128,0); transform: scale(1); }
        }
        .animated-title-glow {
            animation: colorShimmer 3s infinite alternate;
            display: inline-block;
        }

        @keyframes fadeInUpVoucher {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shineSweep {
            0% { left: -100%; }
            20% { left: 200%; }
            100% { left: 200%; }
        }

        .home-voucher-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.06);
            border: 1px solid #e2e8f0;
            display: flex;
            height: 120px;
            width: 340px;
            min-width: 340px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            animation: fadeInUpVoucher 0.6s ease-out forwards;
        }
        .home-voucher-card::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 50%; height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.6) 50%, rgba(255,255,255,0) 100%);
            transform: skewX(-25deg);
            animation: shineSweep 4s infinite;
            z-index: 10;
            pointer-events: none;
        }
        .home-voucher-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 53, 128, 0.12);
            border-color: #003580;
        }
        .home-voucher-left {
            padding: 15px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 120px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .home-voucher-left::after {
            content: '';
            position: absolute;
            right: -25px;
            top: -25px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        .home-voucher-val {
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1.1;
        }
        .home-voucher-type {
            font-size: 0.65rem;
            opacity: 0.9;
            font-weight: 600;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .home-voucher-mid {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            background: #f1f5f9;
            width: 14px;
            position: relative;
        }
        .home-voucher-mid .semi-circle-top {
            width: 14px;
            height: 7px;
            border-radius: 0 0 7px 7px;
            background: #f8fafc; /* Matches parent background */
            border-bottom: 1px solid #e2e8f0;
            position: absolute;
            top: 0;
            left: 0;
        }
        .home-voucher-mid .semi-circle-bottom {
            width: 14px;
            height: 7px;
            border-radius: 7px 7px 0 0;
            background: #f8fafc; /* Matches parent background */
            border-top: 1px solid #e2e8f0;
            position: absolute;
            bottom: 0;
            left: 0;
        }
        .home-voucher-mid .dashed-line {
            flex: 1;
            border-left: 2px dashed #cbd5e1;
            margin-left: 6px;
            margin-top: 7px;
            margin-bottom: 7px;
        }
        .home-voucher-right {
            flex: 1;
            padding: 12px 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: white;
        }
        .home-voucher-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
            margin: 0 0 3px 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .home-voucher-desc {
            font-size: 0.7rem;
            color: #64748b;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .home-voucher-limit {
            font-size: 0.65rem;
            color: #94a3b8;
            margin: 2px 0 0 0;
        }
        .home-voucher-action-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        .home-voucher-btn {
            background: #003580;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .home-voucher-btn:hover {
            background: #ff9800;
            transform: scale(1.05);
        }
        .home-voucher-btn:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }
    </style>

    <section class="favorites-section">
        <div class="container">
            <div class="destinations-grid-section">
                <div class="section-header-wrapper" id="flash-deals-header"
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 10px 0;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div
                            style="background: linear-gradient(135deg, #ff2a2a, #ff6b6b); color: white; border-radius: 12px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; animation: flashPulse 1.5s infinite ease-in-out;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h1 class="section-title-popular flash-title" id="flash-title"
                            style="color: #ff2a2a; font-style: italic; font-weight: 950; margin: 0; font-size: 2.2rem; text-transform: uppercase; border-left: none; padding-left: 0; letter-spacing: -1px; animation: textShimmer 2s infinite alternate ease-in-out;">
                            FLASH DEALS</h1>
                    </div>
                    <a href="flash-deals.php" class="view-all-link"
                        style="color: #ff9800; font-weight: 800; text-transform: uppercase; font-size: 0.9rem; text-decoration: none; border: 2px solid #ff9800; padding: 8px 20px; border-radius: 50px; transition: all 0.3s ease; background: rgba(255, 152, 0, 0.05);">VIEW
                        ALL DEALS <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="popular-scroll-container">
                    <div class="foreign-destinations-grid" id="flashDealsGridHome">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading flash deals...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- POPULAR DESTINATIONS SECTION -->
    <section class="popular-destinations-section">
        <div class="container">


            <!-- Foreign Destinations - Dynamic from foreign-packages.js -->
            <div class="destinations-grid-section">
                <div class="section-header-wrapper" id="foreign-header">
                    <h1 class="section-title-popular foreign-title" id="foreign-title">Foreign Destinations</h1>
                    <a href="foreign-destinations.php" class="view-all-link">See more <i
                            class="fas fa-arrow-right"></i></a>
                </div>

                <div class="popular-scroll-container">
                    <div class="foreign-destinations-grid" id="foreignDestinationsGridHome">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading foreign destinations...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAGINATION CONTAINER - ONLY ONE -->
            <div id="foreignHomePagination" class="pagination-controls"
                style="display: none; margin-top: 20px; justify-content: center;">
                <!-- Pagination buttons will be added here -->
            </div>

            <!-- Local Destinations - Dynamic from Database -->
            <div class="destinations-grid-section">
                <div class="section-header-wrapper" id="local-header">
                    <h1 class="section-title-popular local-title" id="local-title">Local Destinations</h1>
                    <a href="local-destination.php" class="view-all-link">See more <i
                            class="fas fa-arrow-right"></i></a>
                </div>

                <div class="popular-scroll-container">
                    <div class="popular-grid" id="localDestinationsGrid">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading local destinations...</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <?php if (false): // Temporarily hidden ?>
        <!-- DISCOVER THE BEST TIME TO BOOK YOUR FLIGHT SECTION -->
        <section class="booking-time-section">
            <div class="container">
                <h2 class="booking-time-title">Discover the best time to book your next flight ✈️</h2>

                <div class="destinations-row-container">
                    <div class="destinations-row" id="destinationList">
                        <div class="destination-item active" data-destination="baguio">Baguio</div>
                        <div class="destination-item" data-destination="fukuoka">Fukuoka</div>
                        <div class="destination-item" data-destination="cebu">Cebu City</div>
                        <div class="destination-item" data-destination="hongkong">Hong Kong</div>
                        <div class="destination-item" data-destination="makati">Makati</div>
                        <div class="destination-item" data-destination="manila">Manila</div>
                        <div class="destination-item" data-destination="iloilo">Iloilo City</div>
                        <div class="destination-item" data-destination="davao">Davao City</div>
                        <div class="destination-item" data-destination="singapore">Singapore</div>
                        <div class="destination-item" data-destination="tokyo">Tokyo</div>
                        <div class="destination-item" data-destination="bangkok">Bangkok</div>
                        <div class="destination-item" data-destination="seoul">Seoul</div>
                        <div class="destination-item" data-destination="taipei">Taipei</div>
                        <div class="destination-item" data-destination="osaka">Osaka</div>
                    </div>
                </div>

                <div class="pricing-scrollable-box">
                    <div class="pricing-header">
                        <span class="header-month">Month</span>
                        <span class="header-price" id="selectedDestination">Round Trip to Baguio</span>
                    </div>
                    <div class="pricing-items-container" id="flightPrices"></div>
                    <div class="pricing-footer">
                        <span class="scroll-indicator"><i class="fas fa-arrow-down"></i> Scroll for more months</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="separator-icon-mini">
            <div class="separator-line-left"></div>
            <div class="separator-icon-center">
                <i class="fas fa-plane"></i>
                <i class="fas fa-hotel"></i>
            </div>
            <div class="separator-line-right"></div>
        </div>

        <!-- DISCOVER THE BEST TIME TO BOOK YOUR HOTEL SECTION -->
        <section class="booking-time-section">
            <div class="container">
                <h2 class="booking-time-title">Discover the best time to book your next hotel 🏨</h2>

                <div class="destinations-row-container">
                    <div class="destinations-row" id="hotelDestinationList">
                        <div class="destination-item active" data-hotel-destination="baguio">Baguio</div>
                        <div class="destination-item" data-hotel-destination="cebu">Cebu City</div>
                        <div class="destination-item" data-hotel-destination="manila">Manila</div>
                        <div class="destination-item" data-hotel-destination="boracay">Boracay</div>
                        <div class="destination-item" data-hotel-destination="palawan">Palawan</div>
                        <div class="destination-item" data-hotel-destination="davao">Davao City</div>
                        <div class="destination-item" data-hotel-destination="iloilo">Iloilo City</div>
                        <div class="destination-item" data-hotel-destination="tagaytay">Tagaytay</div>
                        <div class="destination-item" data-hotel-destination="bohol">Bohol</div>
                        <div class="destination-item" data-hotel-destination="siargao">Siargao</div>
                    </div>
                </div>

                <div class="pricing-scrollable-box">
                    <div class="pricing-header">
                        <span class="header-month">Month</span>
                        <span class="header-price" id="selectedHotelDestination">Hotel Rates in Baguio</span>
                    </div>
                    <div class="pricing-items-container" id="hotelPrices"></div>
                    <div class="pricing-footer">
                        <span class="scroll-indicator"><i class="fas fa-arrow-down"></i> Scroll for more months</span>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="modal" id="signInModal">
        <div class="modal-content">
            <span class="modal-close" id="closeModal">&times;</span>
            <h2>Welcome Back</h2>
            <p class="modal-subtitle">Sign in to continue your travel journey</p>
            <div class="social-login">
                <button class="social-btn google"><span>🔴</span> Continue with Google</button>
                <button class="social-btn apple"><span>🍎</span> Continue with Apple</button>
                <button class="social-btn facebook"><span>📘</span> Continue with Facebook</button>
            </div>
            <div class="divider"><span>or</span></div>
            <form class="email-form" id="signInForm">
                <input type="email" placeholder="Email address" required>
                <input type="password" placeholder="Password" required>
                <button type="submit" class="signin-btn">Sign In</button>
            </form>
            <div class="create-account-link"><a href="#" id="createAccountLink">Don't have an account? Create one</a>
            </div>
            <p class="terms">By continuing, you agree to our Terms of Service and Privacy Policy</p>
        </div>
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
                    <a href="https://www.facebook.com/profile.php?id=61583752858443" target="_blank"><i
                            class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                        target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://x.com/HeyDreamTravel?s=20" target="_blank"><i
                            class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc"
                        target="_blank"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Pass data to JavaScript for local packages only
        window.homeLocalDestinationsData = <?= json_encode($home_local_destinations) ?>;

        console.log('=== DEBUG: Data being passed to JavaScript ===');
        console.log('Local destinations count:', window.homeLocalDestinationsData.length);
        console.log('Local destinations names:', window.homeLocalDestinationsData.map(d => d.name));

        // Load foreign destinations from database API - SIMPLE VERSION
        let allForeignDestinationsData = [];
        let currentForeignPageHome = 1;
        const foreignItemsPerPageHome = 4;

        // Load foreign destinations from database API - ALL DESTINATIONS IN SCROLLABLE ROW
        async function loadForeignDestinationsForHome() {
            const grid = document.getElementById('foreignDestinationsGridHome');
            if (!grid) return;

            // Show loading state
            grid.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading foreign destinations...</p></div>';

            try {
                // Load ALL foreign destinations (no limit)
                const response = await fetch('api/get-foreign-destinations.php');
                const data = await response.json();

                if (data.success && data.destinations && data.destinations.length > 0) {
                    renderForeignDestinationsHomeScrollable(data.destinations);
                } else {
                    grid.innerHTML = '<div class="loading-spinner"><i class="fas fa-info-circle"></i><p>No foreign destinations available.</p></div>';
                }
            } catch (error) {
                console.error('Error loading foreign destinations:', error);
                grid.innerHTML = '<div class="loading-spinner"><i class="fas fa-exclamation-circle"></i><p>Unable to load destinations. Please try again later.</p></div>';
            }
        }

        function renderForeignDestinationsHomeScrollable(destinations) {
            const grid = document.getElementById('foreignDestinationsGridHome');
            if (!grid) return;

            grid.innerHTML = '';

            destinations.forEach(dest => {
                const displayLocation = dest.location || (dest.city + ', ' + dest.country);
                const description = dest.description || dest.short_description || 'Discover this amazing destination.';
                const badge = dest.badge_text || '';
                const currency = dest.currency || '₱';
                const imagePath = dest.image_path ? dest.image_path : 'https://via.placeholder.com/400x200?text=' + dest.name;

                const badgeHtml = badge ? `<div class="foreign-card-badge-inline">${escapeHtmlForHome(badge)}</div>` : '';

                grid.innerHTML += `
            <div class="foreign-card" data-destination="${dest.dest_key}" onclick="showForeignPackagePopup('${dest.dest_key}')">
                <div class="foreign-card-image" style="position: relative;">
                    <img src="${imagePath}" alt="${escapeHtmlForHome(dest.name)}" onerror="this.src='https://via.placeholder.com/400x200?text=${dest.name}'">
                    <h3 class="foreign-card-name">${escapeHtmlForHome(dest.name)}</h3>
                </div>
                <div class="foreign-card-content">
                    ${badgeHtml}
                    <div class="foreign-card-location">
                        <i class="fas fa-map-marker-alt"></i> ${escapeHtmlForHome(displayLocation)}
                    </div>
                    <p class="foreign-card-desc">${escapeHtmlForHome(description)}</p>
                    ${dest.partner_id ? `<div style="font-size: 0.8rem; color: #64748b; margin-top: 5px; margin-bottom: 5px;">Provided by: <a href="view-partner-profile.php?id=${dest.partner_id}" style="color: #003580; font-weight: 500; text-decoration: none;" onclick="event.stopPropagation();">${escapeHtmlForHome(dest.partner_company || 'Partner')}</a></div>` : ''}
                    <div class="foreign-card-footer">
                        <div class="foreign-card-price">
                            From ${currency}${formatNumberHome(dest.price)}
                            <small>/ person</small>
                        </div>
                        <button class="foreign-card-btn" onclick="event.stopPropagation(); showForeignPackagePopup('${dest.dest_key}')">
                            View Tour Details →
                        </button>
                    </div>
                </div>
            </div>
        `;
            });
        }

        function formatNumberHome(num) {
            if (!num) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function escapeHtmlForHome(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Call the function on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadForeignDestinationsForHome();
        });
    </script>

    <!-- JavaScript Files -->
    <script>
        // Inject user info for booking forms
        <?php $__currentUser = isset($_SESSION['user_id']) ? $auth->getCurrentUser() : null; ?>
        window.currentUserEmail = '<?= $__currentUser ? htmlspecialchars($__currentUser['email']) : '' ?>';
        window.currentFullName = '<?= $__currentUser ? htmlspecialchars($__currentUser['full_name']) : '' ?>';
    </script>
    <script src="js/main.js?v=2"></script>
    <script src="js/slider.js?v=2"></script>
    <script src="js/menu.js?v=2"></script>
    <script src="js/booking.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/auth-menu.js?v=2"></script>
    <script src="js/voucher-checkout.js"></script>
    <script src="js/home-packages.js?v=5"></script>
    <script src="js/foreign-packages.js?v=4"></script>
    <script src="js/flash-deals.js?v=5"></script>

    <script>
        // ========================================
        // SEARCH POPUP FUNCTIONALITY - FULL SCREEN MODAL
        // ========================================

        let currentSearchTerm = '';
        let originalFlashDeals = [];
        let originalForeignDestinations = [];
        let originalLocalDestinations = [];

        // Format number with commas
        function formatSearchNumber(num) {
            if (!num) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Escape HTML
        function escapeSearchHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        const countryFlagMapGlobal = {
            'afghanistan': '🇦🇫', 'albania': '🇦🇱', 'algeria': '🇩🇿', 'andorra': '🇦🇩', 'angola': '🇦🇴',
            'argentina': '🇦🇷', 'armenia': '🇦🇲', 'australia': '🇦🇺', 'austria': '🇦🇹', 'azerbaijan': '🇦🇿',
            'bahrain': '🇧🇭', 'bangladesh': '🇧🇩', 'belarus': '🇧🇾', 'belgium': '🇧🇪', 'belize': '🇧🇿',
            'benin': '🇧🇯', 'bhutan': '🇧🇹', 'bolivia': '🇧🇴', 'bosnia': '🇧🇦', 'botswana': '🇧🇼',
            'brazil': '🇧🇷', 'brunei': '🇧🇳', 'bulgaria': '🇧🇬', 'burkina faso': '🇧🇫', 'burundi': '🇧🇮',
            'cambodia': '🇰🇭', 'cameroon': '🇨🇲', 'canada': '🇨🇦', 'chile': '🇨🇱', 'china': '🇨🇳',
            'colombia': '🇨🇴', 'costa rica': '🇨🇷', 'croatia': '🇭🇷', 'cuba': '🇨🇺', 'cyprus': '🇨🇾',
            'czech republic': '🇨🇿', 'czechia': '🇨🇿', 'denmark': '🇩🇰', 'djibouti': '🇩🇯',
            'dominican republic': '🇩🇴', 'ecuador': '🇪🇨', 'egypt': '🇪🇬', 'el salvador': '🇸🇻',
            'estonia': '🇪🇪', 'ethiopia': '🇪🇹', 'fiji': '🇫🇯', 'finland': '🇫🇮', 'france': '🇫🇷',
            'gabon': '🇬🇦', 'georgia': '🇬🇪', 'germany': '🇩🇪', 'ghana': '🇬🇭', 'greece': '🇬🇷',
            'guatemala': '🇬🇹', 'guinea': '🇬🇳', 'haiti': '🇭🇹', 'honduras': '🇭🇳', 'hong kong': '🇭🇰',
            'hungary': '🇭🇺', 'iceland': '🇮🇸', 'india': '🇮🇳', 'indonesia': '🇮🇩', 'iran': '🇮🇷',
            'iraq': '🇮🇶', 'ireland': '🇮🇪', 'israel': '🇮🇱', 'italy': '🇮🇹', 'jamaica': '🇯🇲',
            'japan': '🇯🇵', 'jp': '🇯🇵', 'jordan': '🇯🇴', 'kazakhstan': '🇰🇿', 'kenya': '🇰🇪', 'kuwait': '🇰🇼',
            'kyrgyzstan': '🇰🇬', 'laos': '🇱🇦', 'latvia': '🇱🇻', 'lebanon': '🇱🇧', 'libya': '🇱🇾',
            'liechtenstein': '🇱🇮', 'lithuania': '🇱🇹', 'luxembourg': '🇱🇺', 'macau': '🇲🇴',
            'madagascar': '🇲🇬', 'malaysia': '🇲🇾', 'maldives': '🇲🇻', 'mali': '🇲🇱', 'malta': '🇲🇹',
            'mauritius': '🇲🇺', 'mexico': '🇲🇽', 'moldova': '🇲🇩', 'monaco': '🇲🇨', 'mongolia': '🇲🇳',
            'montenegro': '🇲🇪', 'morocco': '🇲🇦', 'mozambique': '🇲🇿', 'myanmar': '🇲🇲', 'namibia': '🇳🇦',
            'nepal': '🇳🇵', 'netherlands': '🇳🇱', 'new zealand': '🇳🇿', 'nicaragua': '🇳🇮', 'niger': '🇳🇪',
            'nigeria': '🇳🇬', 'north korea': '🇰🇵', 'north macedonia': '🇲🇰', 'norway': '🇳🇴', 'oman': '🇴🇲',
            'pakistan': '🇵🇰', 'panama': '🇵🇦', 'papua new guinea': '🇵🇬', 'paraguay': '🇵🇾', 'peru': '🇵🇪',
            'philippines': '🇵🇭', 'ph': '🇵🇭', 'poland': '🇵🇱', 'portugal': '🇵🇹', 'qatar': '🇶🇦', 'romania': '🇷🇴',
            'russia': '🇷🇺', 'rwanda': '🇷🇼', 'saudi arabia': '🇸🇦', 'senegal': '🇸🇳', 'serbia': '🇷🇸',
            'singapore': '🇸🇬', 'sg': '🇸🇬', 'slovakia': '🇸🇰', 'slovenia': '🇸🇮', 'somalia': '🇸🇴', 'south africa': '🇿🇦',
            'south korea': '🇰🇷', 'kr': '🇰🇷', 'spain': '🇪🇸', 'sri lanka': '🇱🇰', 'sudan': '🇸🇩', 'sweden': '🇸🇪',
            'switzerland': '🇨🇭', 'syria': '🇸🇾', 'taiwan': '🇹🇼', 'tajikistan': '🇹🇯', 'tanzania': '🇹🇿',
            'thailand': '🇹🇭', 'timor-leste': '🇹🇱', 'togo': '🇹🇬', 'trinidad and tobago': '🇹🇹',
            'tunisia': '🇹🇳', 'turkey': '🇹🇷', 'turkmenistan': '🇹🇲', 'uganda': '🇺🇬', 'ukraine': '🇺🇦',
            'united arab emirates': '🇦🇪', 'uae': '🇦🇪', 'united kingdom': '🇬🇧', 'uk': '🇬🇧',
            'united states': '🇺🇸', 'usa': '🇺🇸', 'us': '🇺🇸', 'uruguay': '🇺🇾', 'uzbekistan': '🇺🇿',
            'venezuela': '🇻🇪', 'vietnam': '🇻🇳', 'yemen': '🇾🇪', 'zambia': '🇿🇲', 'zimbabwe': '🇿🇼',
            'malaysia': '🇲🇾', 'my': '🇲🇾', 'indonesia': '🇮🇩', 'id': '🇮🇩'
        };

        function getVisaFlagGlobal(name) {
            const key = name.toLowerCase().trim();
            for (const [country, flag] of Object.entries(countryFlagMapGlobal)) {
                if (key.includes(country)) return flag;
            }
            return '🛂'; // Custom passport emoji instead of generic globe
        }

        let searchDebounceTimeout = null;

        // Perform search and show popup using NEW UNIFIED API
        async function performGlobalSearch(searchTerm) {
            currentSearchTerm = searchTerm.trim();

            // Sync both inputs
            const mainInput = document.getElementById('globalSearchInput');
            const popupInput = document.getElementById('popupSearchInput');
            if (mainInput && mainInput.value !== currentSearchTerm) mainInput.value = currentSearchTerm;
            if (popupInput && popupInput.value !== currentSearchTerm) popupInput.value = currentSearchTerm;

            if (!currentSearchTerm || currentSearchTerm.length < 1) {
                if (!currentSearchTerm) clearSearch();
                return;
            }

            // Show popup
            const popupOverlay = document.getElementById('searchPopupOverlay');
            if (popupOverlay && !popupOverlay.classList.contains('active')) {
                popupOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                if (popupInput) {
                    popupInput.focus();
                }
            }

            // Show loading in popup if it's the first time or clearing
            const popupBody = document.getElementById('searchPopupBody');
            if (popupBody && (!popupBody.querySelector('.search-result-section') && !popupBody.querySelector('.search-no-results'))) {
                popupBody.innerHTML = `
                <div class="search-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p style="margin-top: 10px;">Searching for "${escapeSearchHtml(currentSearchTerm)}"...</p>
                </div>
            `;
            }



            try {
                // Fetch from UNIFIED SEARCH API
                const response = await fetch(`api/search-packages.php?q=${encodeURIComponent(currentSearchTerm)}`);
                const data = await response.json();

                if (!data.success) throw new Error(data.error || 'Search failed');

                const results = data.results || [];

                // Categorize results
                const flashMatches = results.filter(r => r.type === 'flash');
                const foreignMatches = results.filter(r => r.type === 'foreign');
                const localMatches = results.filter(r => r.type === 'local');
                const visaMatches = results.filter(r => r.type === 'visa');

                const totalMatches = results.length;

                let resultsHtml = `
                    <div class="search-stats">
                        <span class="search-term-badge"><i class="fas fa-search"></i> "${escapeSearchHtml(currentSearchTerm)}"</span>
                        <span class="search-results-count">Found ${totalMatches} total result${totalMatches !== 1 ? 's' : ''}</span>
                    </div>
                `;

                if (totalMatches === 0) {
                    resultsHtml += `
                        <div class="search-no-results">
                            <i class="fas fa-exclamation-circle"></i>
                            <h4>No results found</h4>
                            <p>We couldn't find any matches for "<strong>${escapeSearchHtml(currentSearchTerm)}</strong>".</p>
                            <p style="margin-top: 10px;">Try searching for a destination name, city, or specific place.</p>
                        </div>
                    `;
                } else {
                    // Flash Deals Section
                    if (flashMatches.length > 0) {
                        resultsHtml += `
                            <div class="search-result-section">
                                <h3><i class="fas fa-bolt"></i> Flash Deals (${flashMatches.length})</h3>
                                <div class="search-result-grid">
                        `;
                        flashMatches.forEach(item => {
                            const currency = item.currency || '₱';
                            const priceText = `From ${currency}${formatSearchNumber(item.price)}`;
                            resultsHtml += `
                                <div class="search-result-item flash-deal" onclick="handleSearchResultClick('flash', '${item.id}')">
                                    <div class="search-result-image">
                                        <img src="${item.image_path || 'images/default.jpg'}" alt="${escapeSearchHtml(item.name)}" onerror="this.src='https://via.placeholder.com/90x90?text=Deal'">
                                    </div>
                                    <div class="search-result-info">
                                        <div class="search-result-title">${escapeSearchHtml(item.name)}<span class="flash-deal-badge-small">⚡ DEAL</span></div>
                                        <div class="search-result-location"><i class="fas fa-map-marker-alt"></i> ${escapeSearchHtml(item.location || 'Special Offer')}</div>
                                        <div class="search-result-desc">${escapeSearchHtml(item.description || '').substring(0, 100)}...</div>
                                        <div class="search-result-price">${priceText}</div>
                                    </div>
                                </div>
                            `;
                        });
                        resultsHtml += `</div></div>`;
                    }

                    // Foreign Section
                    if (foreignMatches.length > 0) {
                        resultsHtml += `
                            <div class="search-result-section">
                                <h3><i class="fas fa-globe-asia"></i> Foreign Destinations (${foreignMatches.length})</h3>
                                <div class="search-result-grid">
                        `;
                        foreignMatches.forEach(item => {
                            const currency = item.currency || '₱';
                            const priceText = item.price > 0 ? `From ${currency}${formatSearchNumber(item.price)}` : 'Contact for Price';
                            resultsHtml += `
                                <div class="search-result-item" onclick="handleSearchResultClick('foreign', '${item.dest_key}')">
                                    <div class="search-result-image">
                                        <img src="${item.image_path || 'images/default.jpg'}" alt="${escapeSearchHtml(item.name)}" onerror="this.src='https://via.placeholder.com/90x90?text=Dest'">
                                    </div>
                                    <div class="search-result-info">
                                        <div class="search-result-title">${escapeSearchHtml(item.name)}</div>
                                        <div class="search-result-location"><i class="fas fa-map-marker-alt"></i> ${escapeSearchHtml(item.city || item.country || 'International')}</div>
                                        <div class="search-result-desc">${escapeSearchHtml(item.description || '').substring(0, 100)}...</div>
                                        <div class="search-result-price">${priceText}</div>
                                    </div>
                                </div>
                            `;
                        });
                        resultsHtml += `</div></div>`;
                    }

                    // Local Section
                    if (localMatches.length > 0) {
                        resultsHtml += `
                            <div class="search-result-section">
                                <h3><i class="fas fa-island-tropical"></i> Local Destinations (${localMatches.length})</h3>
                                <div class="search-result-grid">
                        `;
                        localMatches.forEach(item => {
                            const currency = item.currency || '₱';
                            const priceText = item.price > 0 ? `From ${currency}${formatSearchNumber(item.price)}` : 'Contact for Price';
                            resultsHtml += `
                                <div class="search-result-item" onclick="handleSearchResultClick('local', '${item.id}')">
                                    <div class="search-result-image">
                                        <img src="${item.image_path || 'images/default.jpg'}" alt="${escapeSearchHtml(item.name)}" onerror="this.src='https://via.placeholder.com/90x90?text=PH'">
                                    </div>
                                    <div class="search-result-info">
                                        <div class="search-result-title">${escapeSearchHtml(item.name)}</div>
                                        <div class="search-result-location"><i class="fas fa-map-marker-alt"></i> ${escapeSearchHtml(item.location || item.city || 'Philippines')}</div>
                                        <div class="search-result-desc">${escapeSearchHtml(item.description || '').substring(0, 100)}...</div>
                                        <div class="search-result-price">${priceText}</div>
                                    </div>
                                </div>
                            `;
                        });
                        resultsHtml += `</div></div>`;
                    }

                    // Visas Section
                    if (visaMatches.length > 0) {
                        resultsHtml += `
                            <div class="search-result-section">
                                <h3><i class="fas fa-passport"></i> Visa Services (${visaMatches.length})</h3>
                                <div class="search-result-grid">
                        `;
                        visaMatches.forEach(item => {
                            const currency = item.currency || '₱';
                            const priceText = `Service Fee: ${currency}${formatSearchNumber(item.price)}`;
                            resultsHtml += `
                                <div class="search-result-item visa-result" onclick="handleSearchResultClick('visa', '${item.id}')">
                                    <div class="search-result-image" style="background: #f0f2f5; display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">
                                        ${getVisaFlagGlobal(item.name)}
                                    </div>
                                    <div class="search-result-info">
                                        <div class="search-result-title">${escapeSearchHtml(item.name)} Visa Assistance</div>
                                        <div class="search-result-desc">${escapeSearchHtml(item.description || '').substring(0, 100)}...</div>
                                        <div class="search-result-price">${priceText}</div>
                                    </div>
                                </div>
                            `;
                        });
                        resultsHtml += `</div></div>`;
                    }
                }

                // Suggestions always visible at bottom or if no results
                resultsHtml += `
                    <div class="search-suggestions">
                        <h4><i class="fas fa-lightbulb"></i> Popular searches:</h4>
                        <div class="suggestion-tags">
                            <span class="suggestion-tag" onclick="searchSuggestion('beach')">🏖️ beach</span>
                            <span class="suggestion-tag" onclick="searchSuggestion('boracay')">🏝️ boracay</span>
                            <span class="suggestion-tag" onclick="searchSuggestion('japan')">🇯🇵 japan</span>
                            <span class="suggestion-tag" onclick="searchSuggestion('korea')">🇰🇷 korea</span>
                            <span class="suggestion-tag" onclick="searchSuggestion('hongkong')">🏙️ hongkong</span>
                            <span class="suggestion-tag" onclick="searchSuggestion('visa')">🛂 visa</span>
                        </div>
                    </div>
                `;

                if (popupBody) popupBody.innerHTML = resultsHtml;

            } catch (error) {
                console.error('Search error:', error);
                if (popupBody) {
                    popupBody.innerHTML = `
                        <div class="search-no-results">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h4>Search encountered an error</h4>
                            <p>${error.message}</p>
                            <button class="clear-search-btn" onclick="performGlobalSearch(currentSearchTerm)" style="margin-top:15px;">Retry</button>
                        </div>
                    `;
                }
            }
        }

        // Handle search result click
        function handleSearchResultClick(type, identifier) {
            // Close popup first
            closeSearchPopup();

            // Small delay to ensure popup closes before action
            setTimeout(() => {
                if (type === 'flash' && identifier) {
                    if (typeof showFlashDealPopup === 'function') showFlashDealPopup(parseInt(identifier));
                    else alert('Opening Flash Deal detail...');
                } else if (type === 'foreign' && identifier) {
                    if (typeof showForeignPackagePopup === 'function') showForeignPackagePopup(identifier);
                    else alert('Opening Foreign Package detail...');
                } else if (type === 'local' && identifier) {
                    // Check if identifier is ID or key
                    if (typeof showLocalPackagePopup === 'function') showLocalPackagePopup(identifier);
                    else alert('Opening Local Package detail...');
                } else if (type === 'visa') {
                    window.location.href = 'buttons/visa.php';
                }
            }, 150);
        }

        // Search suggestion
        function searchSuggestion(term) {
            const searchInput = document.getElementById('globalSearchInput');
            if (searchInput) {
                searchInput.value = term;
                performGlobalSearch(term);
            }
        }

        // Close search popup
        function closeSearchPopup() {
            const popupOverlay = document.getElementById('searchPopupOverlay');
            if (popupOverlay) {
                popupOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        // Clear search and close popup
        function clearSearch() {
            currentSearchTerm = '';

            // Close popup if open
            closeSearchPopup();

            // Show all Flash Deals
            const flashGrid = document.getElementById('flashDealsGridHome');
            if (flashGrid) {
                const flashCards = flashGrid.querySelectorAll('.flash-deal-card');
                flashCards.forEach(card => {
                    card.style.display = 'flex';
                    const highlighted = card.querySelectorAll('.search-highlight');
                    highlighted.forEach(el => {
                        const parent = el.parentNode;
                        parent.replaceChild(document.createTextNode(el.textContent), el);
                        parent.normalize();
                    });
                });
            }

            // Show all Foreign Destinations
            const foreignGrid = document.getElementById('foreignDestinationsGridHome');
            if (foreignGrid) {
                const foreignCards = foreignGrid.querySelectorAll('.foreign-card');
                foreignCards.forEach(card => {
                    card.style.display = 'flex';
                    const highlighted = card.querySelectorAll('.search-highlight');
                    highlighted.forEach(el => {
                        const parent = el.parentNode;
                        parent.replaceChild(document.createTextNode(el.textContent), el);
                        parent.normalize();
                    });
                });
            }

            // Show all Local Destinations
            const localGrid = document.getElementById('localDestinationsGrid');
            if (localGrid) {
                const localCards = localGrid.querySelectorAll('.home-destination-card, .popular-card');
                localCards.forEach(card => {
                    card.style.display = 'flex';
                    const highlighted = card.querySelectorAll('.search-highlight');
                    highlighted.forEach(el => {
                        const parent = el.parentNode;
                        parent.replaceChild(document.createTextNode(el.textContent), el);
                        parent.normalize();
                    });
                });
            }

            // Hide search message
            const messageContainer = document.getElementById('searchResultsMessage');
            if (messageContainer) {
                messageContainer.style.display = 'none';
            }

            // Clear search input
            const searchInput = document.getElementById('globalSearchInput');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        // Load flash deals for homepage and store original data
        async function loadFlashDealsForHomeWithStore() {
            const container = document.getElementById('flashDealsGridHome');
            if (!container) return;

            container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading flash deals...</p></div>';

            try {
                const response = await fetch('api/get-flash-deals.php?limit=4');
                const data = await response.json();

                if (data.success && data.deals && data.deals.length > 0) {
                    renderFlashDealsHome(data.deals);
                    setTimeout(() => {
                        const cards = container.querySelectorAll('.flash-deal-card');
                        if (cards.length > 0) {
                            originalFlashDeals = Array.from(cards).map(card => ({
                                title: card.querySelector('.flash-deal-title')?.textContent || '',
                                location: card.querySelector('.flash-deal-location')?.textContent || '',
                                description: card.querySelector('.flash-deal-desc')?.textContent || '',
                                category: card.querySelector('.flash-deal-category')?.textContent || ''
                            }));
                        }
                    }, 100);
                } else {
                    container.innerHTML = '<div class="loading-spinner"><i class="fas fa-info-circle"></i><p>No flash deals available. Check back soon!</p></div>';
                }
            } catch (error) {
                console.error('Error loading flash deals:', error);
                container.innerHTML = '<div class="loading-spinner"><i class="fas fa-exclamation-circle"></i><p>Unable to load deals. Please try again later.</p></div>';
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof loadFlashDealsForHome === 'function') {
                const originalLoad = loadFlashDealsForHome;
                window.loadFlashDealsForHome = function () {
                    originalLoad();
                    setTimeout(() => {
                        const container = document.getElementById('flashDealsGridHome');
                        if (container) {
                            const cards = container.querySelectorAll('.flash-deal-card');
                            if (cards.length > 0) {
                                originalFlashDeals = Array.from(cards).map(card => ({
                                    title: card.querySelector('.flash-deal-title')?.textContent || '',
                                    location: card.querySelector('.flash-deal-location')?.textContent || '',
                                    description: card.querySelector('.flash-deal-desc')?.textContent || '',
                                    category: card.querySelector('.flash-deal-category')?.textContent || ''
                                }));
                            }
                        }
                    }, 100);
                };
                window.loadFlashDealsForHome();
            } else {
                loadFlashDealsForHomeWithStore();
            }

            // Setup search button event listeners
            const searchBtn = document.getElementById('globalSearchBtn');
            const searchInput = document.getElementById('globalSearchInput');
            const clearSearchBtn = document.getElementById('clearSearchBtn');
            const closePopupBtn = document.getElementById('closeSearchPopup');
            const popupOverlay = document.getElementById('searchPopupOverlay');

            let searchDebounceTimeout = null;

            // Nudges the user to type a destination instead of silently doing
            // nothing when Search is clicked/Enter is pressed with an empty field.
            function notifyDestinationNeeded() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'warning',
                        title: 'Please enter a destination to search for',
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true
                    });
                } else {
                    alert('Please enter a destination to search for.');
                }
                searchInput?.focus();
            }

            if (searchBtn) {
                searchBtn.addEventListener('click', function () {
                    const searchTerm = searchInput?.value || '';
                    if (searchTerm.trim()) {
                        performGlobalSearch(searchTerm);
                    } else {
                        clearSearch();
                        notifyDestinationNeeded();
                    }
                });
            }

            // ── Hero search bar: check-in / check-out date pickers ──
            // Dates aren't used to filter results (no per-day inventory exists
            // for destinations/deals) -- they're captured so a chosen date can
            // prefill the travel date once the traveler reaches a package's own
            // booking flow, same as everywhere else on the site.
            if (typeof flatpickr === 'function') {
                const checkInEl = document.getElementById('heroCheckIn');
                const checkOutEl = document.getElementById('heroCheckOut');

                // Adds a visible "x" in the calendar's own corner so there's
                // always an obvious way to dismiss it besides clicking away.
                function addCalendarCloseButton(instance) {
                    if (instance.calendarContainer.querySelector('.hd-fp-close')) return;
                    const closeBtn = document.createElement('button');
                    closeBtn.type = 'button';
                    closeBtn.className = 'hd-fp-close';
                    closeBtn.setAttribute('aria-label', 'Close calendar');
                    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    closeBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        instance.close();
                    });
                    instance.calendarContainer.appendChild(closeBtn);
                }

                const checkOutPicker = flatpickr(checkOutEl, {
                    minDate: 'today',
                    dateFormat: 'M j, Y',
                    disableMobile: true,
                    onReady: (sd, ds, instance) => addCalendarCloseButton(instance)
                });

                const checkInPicker = flatpickr(checkInEl, {
                    minDate: 'today',
                    dateFormat: 'M j, Y',
                    disableMobile: true,
                    onReady: (sd, ds, instance) => addCalendarCloseButton(instance),
                    onChange: function (selectedDates) {
                        if (selectedDates[0]) {
                            const nextDay = new Date(selectedDates[0]);
                            nextDay.setDate(nextDay.getDate() + 1);
                            checkOutPicker.set('minDate', nextDay);
                            if (checkOutPicker.selectedDates[0] && checkOutPicker.selectedDates[0] <= selectedDates[0]) {
                                checkOutPicker.setDate(nextDay);
                            }
                        }
                    }
                });

                document.getElementById('heroCheckInField')?.addEventListener('click', () => checkInPicker.open());
                document.getElementById('heroCheckOutField')?.addEventListener('click', () => checkOutPicker.open());

                // Belt-and-suspenders close handling: flatpickr's own
                // click-outside detection wasn't reliably closing the calendar
                // here (readonly input + wrapper-div open trigger seems to
                // interfere with it), so close explicitly on outside click and
                // on Escape too.
                const heroPickers = [checkInPicker, checkOutPicker];
                document.addEventListener('click', function (e) {
                    heroPickers.forEach(picker => {
                        if (!picker.isOpen) return;
                        const fieldWrapper = picker.input.closest('.hd-search-field');
                        if (fieldWrapper && fieldWrapper.contains(e.target)) return;
                        if (picker.calendarContainer.contains(e.target)) return;
                        picker.close();
                    });
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    heroPickers.forEach(picker => { if (picker.isOpen) picker.close(); });
                });
            }

            // ── Helper: position the fixed autocomplete below the search bar ──
            function positionAutocomplete(dropdown) {
                const rect = searchInput.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 6) + 'px';
                if (window.innerWidth <= 768) {
                    // Mobile: wider than the input, centered on screen
                    const mobileWidth = Math.min(window.innerWidth * 0.98, 650);
                    dropdown.style.width = mobileWidth + 'px';
                    dropdown.style.left = Math.max(0, (window.innerWidth - mobileWidth) / 2) + 'px';
                    dropdown.style.right = 'auto';
                } else {
                    // The "Where to?" field itself is fairly narrow now that
                    // it shares the pill with date fields -- give the dropdown
                    // a wider minimum so destination names/images aren't crushed.
                    const desktopWidth = Math.max(rect.width, 360);
                    dropdown.style.width = desktopWidth + 'px';
                    dropdown.style.left = Math.min(rect.left, window.innerWidth - desktopWidth - 12) + 'px';
                }
            }

            // Shared item template so trending suggestions and live search
            // results render identically.
            function renderAutocompleteItemHtml(item) {
                let badge = '';
                if (item.type === 'flash') badge = ' <span style="color:#ff9800;font-size:0.7em;">⚡ DEAL</span>';
                if (item.type === 'foreign') badge = ' <span style="color:#17a2b8;font-size:0.7em;">🌍 INT</span>';
                if (item.type === 'local') badge = ' <span style="color:#28a745;font-size:0.7em;">🏝️ PH</span>';
                if (item.type === 'visa') badge = ' <span style="color:#6c757d;font-size:0.7em;">🛂 VISA</span>';

                const identifier = item.type === 'foreign' ? item.dest_key : item.id;
                const locText = item.type === 'visa' ? item.description : (item.location || item.city || item.country || '');
                const isVisa = item.type === 'visa';
                const visaFlag = isVisa ? getVisaFlagGlobal(item.name) : '';

                return `
                    <div class="autocomplete-item" onclick="handleSearchResultClick('${item.type}', '${identifier}'); document.getElementById('autocompleteDropdown').style.display='none';">
                        ${isVisa ?
                        `<div class="autocomplete-icon" style="background:#f0f2f5; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">${visaFlag}</div>` :
                        `<img src="${item.image_path || 'images/default.jpg'}" class="autocomplete-icon" onerror="this.src='https://via.placeholder.com/40'">`
                    }
                        <div class="autocomplete-details">
                            <div class="autocomplete-title">${escapeSearchHtml(item.name)}${badge}</div>
                            <div class="autocomplete-desc">${escapeSearchHtml(locText).substring(0, 50)}...</div>
                        </div>
                    </div>
                `;
            }

            // Trending destinations shown when the "Where to?" field is
            // focused before the traveler has typed anything -- fetched once
            // and cached for the rest of the page's life.
            let trendingDestinationsCache = null;
            async function loadTrendingDestinations() {
                if (trendingDestinationsCache) return trendingDestinationsCache;
                try {
                    const response = await fetch('api/search-packages.php?trending=1');
                    const data = await response.json();
                    trendingDestinationsCache = (data.success && data.places) ? data.places : [];
                } catch (error) {
                    console.error('Trending destinations error:', error);
                    trendingDestinationsCache = [];
                }
                return trendingDestinationsCache;
            }

            // Clicking a suggested place fills the field and runs a real
            // search for it (results popup), rather than jumping to one
            // specific package.
            function selectTrendingPlace(city) {
                searchInput.value = city;
                const autoDropdown = document.getElementById('autocompleteDropdown');
                if (autoDropdown) autoDropdown.style.display = 'none';
                performGlobalSearch(city);
            }
            // Called from an inline onclick in the trending-place dropdown
            // markup, which resolves in global scope -- this function is
            // otherwise scoped inside the DOMContentLoaded callback.
            window.selectTrendingPlace = selectTrendingPlace;

            function renderTrendingPlaceHtml(place) {
                const subtitle = place.count > 1 ? `${place.count} packages available` : (place.country || '');
                return `
                    <div class="autocomplete-item" onclick="selectTrendingPlace('${escapeSearchHtml(place.city).replace(/'/g, "\\'")}')">
                        <img src="${place.image_path || 'images/default.jpg'}" class="autocomplete-icon" onerror="this.src='https://via.placeholder.com/40'">
                        <div class="autocomplete-details">
                            <div class="autocomplete-title"><i class="fas fa-map-marker-alt" style="color:#ff9800;font-size:0.8em;"></i> ${escapeSearchHtml(place.city)}</div>
                            <div class="autocomplete-desc">${escapeSearchHtml(subtitle)}</div>
                        </div>
                    </div>
                `;
            }

            async function showTrendingDestinations() {
                const autoDropdown = document.getElementById('autocompleteDropdown');
                if (!autoDropdown) return;

                autoDropdown.innerHTML = `<div style="padding:15px;color:#666;text-align:center;font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Loading popular destinations...</div>`;
                autoDropdown.style.display = 'block';
                positionAutocomplete(autoDropdown);

                const places = await loadTrendingDestinations();
                if (searchInput.value.trim().length > 0) return; // user started typing while this was loading

                if (places.length === 0) {
                    autoDropdown.style.display = 'none';
                    return;
                }

                autoDropdown.innerHTML = `<div class="autocomplete-section-header"><i class="fas fa-fire"></i> Popular Destinations</div>`
                    + places.map(renderTrendingPlaceHtml).join('');
                autoDropdown.style.display = 'block';
                positionAutocomplete(autoDropdown);
            }

            if (searchInput) {
                searchInput.addEventListener('input', function (e) {
                    const term = e.target.value.trim().toLowerCase();
                    const autoDropdown = document.getElementById('autocompleteDropdown');

                    if (!term) {
                        showTrendingDestinations();
                        return;
                    }

                    // Show searching state in dropdown
                    if (autoDropdown) {
                        autoDropdown.innerHTML = `<div style="padding:15px;color:#666;text-align:center;font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>`;
                        autoDropdown.style.display = 'block';
                        positionAutocomplete(autoDropdown);
                    }

                    clearTimeout(searchDebounceTimeout);

                    searchDebounceTimeout = setTimeout(async () => {
                        try {
                            const response = await fetch(`api/search-packages.php?q=${encodeURIComponent(term)}`);
                            const data = await response.json();

                            if (!data.success) throw new Error(data.error);

                            const results = data.results || [];
                            let matchesHtml = '';

                            if (results.length === 0) {
                                matchesHtml = `<div style="padding:15px;color:#666;text-align:center;font-size:0.85rem;">No matches found for "${term}"</div>`;
                            } else {
                                // Limit to top 8 results for autocomplete
                                matchesHtml = results.slice(0, 8).map(renderAutocompleteItemHtml).join('');
                            }

                            if (autoDropdown) {
                                autoDropdown.innerHTML = matchesHtml;
                                autoDropdown.style.display = 'block';
                                positionAutocomplete(autoDropdown);
                            }
                        } catch (error) {
                            console.error('Autocomplete search error:', error);
                            if (autoDropdown) {
                                autoDropdown.innerHTML = `<div style="padding:15px;color:#dc3545;text-align:center;font-size:0.85rem;">Search error</div>`;
                            }
                        }
                    }, 300); // 300ms debounce
                });

                searchInput.addEventListener('focus', function () {
                    const autoDropdown = document.getElementById('autocompleteDropdown');
                    if (this.value.trim().length > 0) {
                        if (autoDropdown && autoDropdown.innerHTML !== '') {
                            autoDropdown.style.display = 'block';
                            positionAutocomplete(autoDropdown);
                        }
                    } else {
                        showTrendingDestinations();
                    }
                });

                document.addEventListener('click', function (e) {
                    const autoDropdown = document.getElementById('autocompleteDropdown');
                    if (autoDropdown && searchInput && !searchInput.contains(e.target) && !autoDropdown.contains(e.target)) {
                        autoDropdown.style.display = 'none';
                    }
                    if (searchBtn && searchBtn.contains(e.target)) {
                        autoDropdown.style.display = 'none';
                    }
                });

                // Reposition autocomplete dropdown on scroll so it follows the search bar
                window.addEventListener('scroll', function () {
                    const autoDropdown = document.getElementById('autocompleteDropdown');
                    if (!autoDropdown || autoDropdown.style.display === 'none') return;
                    requestAnimationFrame(function () {
                        const rect = searchInput.getBoundingClientRect();
                        // Hide if search bar has scrolled out of the viewport
                        if (rect.bottom < 0 || rect.top > window.innerHeight) {
                            autoDropdown.style.display = 'none';
                            return;
                        }
                        positionAutocomplete(autoDropdown);
                    });
                }, { passive: true });

                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const searchTerm = searchInput.value || '';
                        if (searchTerm.trim()) {
                            performGlobalSearch(searchTerm);
                            const autoDropdown = document.getElementById('autocompleteDropdown');
                            if (autoDropdown) autoDropdown.style.display = 'none';
                        } else {
                            clearSearch();
                            notifyDestinationNeeded();
                        }
                    }
                });
            }

            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function () {
                    clearSearch();
                });
            }

            if (closePopupBtn) {
                closePopupBtn.addEventListener('click', function () {
                    closeSearchPopup();
                });
            }

            if (popupOverlay) {
                popupOverlay.addEventListener('click', function (e) {
                    if (e.target === popupOverlay) {
                        closeSearchPopup();
                    }
                });
            }

            setTimeout(() => {
                if (window.homeLocalDestinationsData) {
                    originalLocalDestinations = [...window.homeLocalDestinationsData];
                }

                fetch('api/get-foreign-destinations.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.destinations) {
                            originalForeignDestinations = data.destinations;
                        }
                    })
                    .catch(err => console.error('Error:', err));
            }, 1000);
        });






    </script>

    <!-- Chatbot injected inline below -->
    <script src="js/saved.js"></script>


    <script>
        // Check for URL parameters to trigger modals automatically
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.get('action') === 'logout') {
                // Remove the parameter from the URL to avoid re-triggering on refresh
                urlParams.delete('action');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);

                // Show the popup after a small delay for smoother entrance
                setTimeout(() => {
                    if (typeof showLogoutConfirmPopup === 'function') {
                        showLogoutConfirmPopup();
                    }
                }, 300);
            }

            // Handle successful logout toast
            if (urlParams.get('logout') === 'success') {
                // Remove the parameter from the URL
                urlParams.delete('logout');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);

                // Create a beautiful toast notification
                const toast = document.createElement('div');
                toast.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; background: #e8f5e9; width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;"><i class="fas fa-check" style="color: #2e7d32; font-size: 1rem;"></i></div> <div style="display: flex; flex-direction: column;"><span style="color: #1a1a1a; font-weight: 700; font-size: 0.95rem; font-family: \'Poppins\', sans-serif;">Signed Out Successfully</span><span style="color: #666; font-size: 0.8rem;">Your session has been securely ended.</span></div>';
                toast.style.position = 'fixed';
                toast.style.bottom = '30px';
                toast.style.left = '50%';
                toast.style.transform = 'translateX(-50%) translateY(100px)';
                toast.style.background = 'white';
                toast.style.padding = '12px 20px 12px 12px';
                toast.style.borderRadius = '16px';
                toast.style.boxShadow = '0 10px 40px rgba(0, 53, 128, 0.12), 0 2px 10px rgba(0,0,0,0.04)';
                toast.style.display = 'flex';
                toast.style.alignItems = 'center';
                toast.style.gap = '14px';
                toast.style.zIndex = '9999';
                toast.style.transition = 'transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.4s ease';
                toast.style.opacity = '0';

                document.body.appendChild(toast);

                // Animate in
                setTimeout(() => {
                    toast.style.transform = 'translateX(-50%) translateY(0)';
                    toast.style.opacity = '1';
                }, 100);

                // Animate out and remove after 3.5 seconds
                setTimeout(() => {
                    toast.style.transform = 'translateX(-50%) translateY(50px)';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 400);
                }, 3500);
            }
        });
    </script>



    <!-- ===== HEYDREAM AI CHATBOT WIDGET (same design as inquire.php) ===== -->
    <?php include_once 'chatbot_widget.php'; ?>
</body>

</html>
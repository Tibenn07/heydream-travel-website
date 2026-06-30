<?php
// local-destination.php - Database-driven version with modern design

require_once __DIR__ . '/config/database.php';

// Get destination from URL parameter
$selected_dest_name = isset($_GET['dest']) ? trim($_GET['dest']) : '';

// Fetch all local destinations from database with ALL fields from destinations table
$stmt = $pdo->prepare("
    SELECT 
        id,
        name,
        location_name,
        city,
        description,
        price,
        currency,
        duration,
        activities_count,
        group_size,
        best_season,
        itinerary,
        inclusions,
        exclusions,
        hotels,
        image_path,
        image2_path,
        image3_path,
        image4_path,
        collage_type,
        category,
        badge_text,
        is_active,
        promo_start,
        promo_end,
        blocked_months,
        highlight_duration,
        blocked_dates,
        remarks
    FROM destinations 
    WHERE type = 'local' 
    AND is_active = 1 
    ORDER BY display_order, id ASC
");
$stmt->execute();
$db_destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare destinations data for JavaScript - SAME STRUCTURE as home-packages.js
$allDestinations = [];
foreach ($db_destinations as $dest) {
    // Get images - store the full path as it comes from database (already has uploads/)
    $images = [];
    if ($dest['image_path'])
        $images[] = $dest['image_path'];
    if ($dest['image2_path'])
        $images[] = $dest['image2_path'];
    if ($dest['image3_path'])
        $images[] = $dest['image3_path'];
    if ($dest['image4_path'])
        $images[] = $dest['image4_path'];

    // Parse itinerary (already JSON from content-manager)
    $itinerary = [];
    if ($dest['itinerary'] && is_string($dest['itinerary'])) {
        $itinerary = json_decode($dest['itinerary'], true);
        if (!is_array($itinerary))
            $itinerary = [];
    }

    // Parse inclusions
    $inclusions = [];
    if ($dest['inclusions'] && is_string($dest['inclusions'])) {
        $inclusions = json_decode($dest['inclusions'], true);
        if (!is_array($inclusions))
            $inclusions = [];
    }

    // Parse exclusions
    $exclusions = [];
    if (isset($dest['exclusions']) && $dest['exclusions'] && is_string($dest['exclusions'])) {
        $exclusions = json_decode($dest['exclusions'], true);
        if (!is_array($exclusions))
            $exclusions = [];
    }

    // Parse hotels
    $hotels = [];
    if (isset($dest['hotels']) && $dest['hotels'] && is_string($dest['hotels'])) {
        $hotels = json_decode($dest['hotels'], true);
        if (!is_array($hotels))
            $hotels = [];
    }

    $allDestinations[] = [
        'id' => $dest['id'],
        'key' => strtolower(str_replace(' ', '_', $dest['name'])),
        'name' => $dest['name'],
        'location' => $dest['location_name'] ?? $dest['city'] ?? 'Philippines',
        'description' => $dest['description'] ?? 'Experience the beauty of this amazing destination.',
        'price' => floatval($dest['price'] ?? 0),
        'currency' => $dest['currency'] ?? '₱',
        'duration' => $dest['duration'] ?? '3D/2N',
        'activities' => intval($dest['activities_count'] ?? 0),
        'groupSize' => $dest['group_size'] ?? '2-15 pax',
        'bestSeason' => $dest['best_season'] ?? 'Year Round',
        'images' => $images,
        'collageType' => $dest['collage_type'] ?? 'three',
        'category' => $dest['category'] ?? 'beach',
        'badge' => $dest['badge_text'] ?? ($dest['activities_count'] . ' activities'),
        'itinerary' => $itinerary,
        'inclusions' => $inclusions,
        'exclusions' => $exclusions,
        'hotels' => $hotels,
        'promo_start' => $dest['promo_start'],
        'promo_end' => $dest['promo_end'],
        'blocked_months' => $dest['blocked_months'],
        'blocked_dates' => $dest['blocked_dates'],
        'highlight_duration' => intval($dest['highlight_duration'] ?? 1),
        'remarks' => $dest['remarks']
    ];
}

// Find selected destination if specified
$selected_destination = null;
if ($selected_dest_name) {
    foreach ($allDestinations as $dest) {
        if (strtolower($dest['name']) == strtolower($selected_dest_name)) {
            $selected_destination = $dest;
            break;
        }
    }
}

// Booking is handled by JavaScript calling api/save-local-booking.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Local Destinations</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ========================================
           LOCAL DESTINATIONS PAGE STYLES (Matches Foreign Destinations)
           ======================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }

        /* Hero Section - Compact */
        .local-destination-hero {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            color: white;
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease;
        }

        .local-destination-hero h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .local-destination-hero p {
            font-size: 0.9rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Stats Bar - Horizontal Row */
        .stats-bar {
            display: flex;
            justify-content: space-around;
            align-items: center;
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            flex: 1;
            min-width: 0;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #003580;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            white-space: nowrap;
        }

        /* Filter Chips */
        .destinations-filter {
            display: flex;
            gap: 12px;
            flex-wrap: nowrap;
            margin-bottom: 40px;
            justify-content: flex-start;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 10px;
            scrollbar-width: none;
        }

        .destinations-filter::-webkit-scrollbar {
            display: none;
        }

        .filter-chip {
            flex-shrink: 0;
            white-space: nowrap;
            padding: 8px 20px;
            background: white;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #333;
            border: 1px solid #e0e0e0;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
        }

        .filter-chip:hover {
            background: #003580;
            color: white;
            border-color: #003580;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 53, 128, 0.2);
        }

        .filter-chip.active {
            background: #003580;
            color: white;
            border-color: #003580;
        }

        /* Destinations Grid */
        .destinations-grid-local {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
            grid-template-columns: repeat(4, 1fr);
        }

        @media (max-width: 1200px) {
            .destinations-grid-local {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .destinations-grid-local {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Mobile Adjustments */
        @media (max-width: 767px) {
            .destinations-grid-local {
                gap: 15px;
            }

            .destination-card-local {
                height: 460px !important;
            }

            .destination-image {
                height: 190px !important;
            }
        }

        /* Small Mobile Adjustments */
        @media (max-width: 480px) {
            .destinations-grid-local {
                gap: 12px;
            }

            .destination-card-local {
                height: 440px !important;
            }

            .destination-image {
                height: 175px !important;
            }
        }

        /* Destination Card */
        .destination-card-local {
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
        }

        .destination-card-local:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.15);
        }

        .destination-image {
            position: relative;
            height: 210px;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* Image Collage Styles */
        .image-collage {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        /* 2-Image Collage (Half & Half - Left/Right) */
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

        /* 3-Image Collage (1 large + 2 small stacked) */
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

        /* 4-Image Collage (Grid) */
        .collage-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 2px;
            height: 100%;
        }

        .collage-grid img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .destination-card-local:hover .image-collage img {
            transform: scale(1.05);
            transition: transform 0.4s ease;
        }


        .destination-info {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

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

        .destination-location {
            color: #666;
            font-size: 0.7rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .destination-location i {
            color: #ff9800;
            font-size: 0.65rem;
        }

        .destination-description {
            color: #555;
            font-size: 0.75rem;
            line-height: 1.4;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 50px;
        }

        .destination-price {
            font-size: 1rem;
            font-weight: 700;
            color: #ff9800;
            margin-bottom: 6px;
            margin-top: 10px;
        }

        .destination-price small {
            font-size: 0.65rem;
            color: #888;
            font-weight: normal;
        }

        .destination-packages {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: auto;
        }

        .view-details-btn {
            background: #003580;
            color: #ffffff;
            border: none;
            padding: 9px 14px;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .view-details-btn:hover {
            background: #ff9800;
            transform: scale(1.05);
        }

        /* Pagination Controls */
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 30px 0 20px;
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
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #003580;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            background: #ff9800;
            transform: translateY(-3px);
        }

        /* Loading Spinner */
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

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* ===== IMPROVED PAYMENT METHOD STYLES ===== */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 2px solid #e8e8e8;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .payment-method:hover {
            border-color: #ff9800;
            background: #fffaf2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.1);
        }

        .payment-method.selected {
            border-color: #ff9800;
            background: #fffaf2;
            box-shadow: 0 3px 10px rgba(255, 152, 0, 0.15);
        }

        .payment-method input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #ff9800;
            margin: 0;
            flex-shrink: 0;
        }

        .payment-icon {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .payment-icon i {
            font-size: 1.6rem;
            color: #ff9800;
        }

        .payment-info {
            flex: 1;
        }

        .payment-name {
            font-weight: 700;
            font-size: 1rem;
            color: #003580;
            margin-bottom: 4px;
        }

        .payment-desc {
            font-size: 0.7rem;
            color: #888;
        }

        .payment-details-box {
            display: none;
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 16px;
            animation: fadeIn 0.3s ease;
        }

        .payment-details-box.show {
            display: block;
        }

        .payment-instructions {
            background: white;
            border-radius: 14px;
            padding: 20px;
        }

        .instruction-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #ff9800;
        }

        .instruction-header i {
            font-size: 1.6rem;
            color: #ff9800;
        }

        .instruction-header h4 {
            color: #003580;
            margin: 0;
            font-size: 1.1rem;
        }

        .qr-code {
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .qr-placeholder {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, #f5f7fa, #e9eef5);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            gap: 10px;
        }

        .qr-placeholder i {
            font-size: 3rem;
            color: #ff9800;
        }

        .qr-placeholder p {
            font-size: 0.7rem;
            color: #666;
            margin: 0;
        }

        .account-details {
            background: #fff9e6;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .account-number {
            font-weight: bold;
            color: #003580;
            background: #e8f0fe;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 1rem;
        }

        .copy-btn {
            background: #e0e0e0;
            border: none;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            cursor: pointer;
            margin-left: 8px;
            transition: all 0.2s ease;
        }

        .copy-btn:hover {
            background: #ff9800;
            color: white;
        }

        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
            margin-top: 15px;
        }

        .file-upload:hover {
            border-color: #ff9800;
            background: #fff9e6;
        }

        .file-upload i {
            font-size: 1.5rem;
            color: #ff9800;
            margin-bottom: 5px;
        }

        .file-upload p {
            font-size: 0.7rem;
            color: #666;
            margin: 0;
        }

        .file-upload .file-name {
            font-size: 0.65rem;
            color: #003580;
            margin-top: 5px;
            font-weight: 500;
        }

        .upload-preview {
            margin-top: 10px;
            text-align: center;
        }

        .upload-preview img {
            max-width: 100%;
            max-height: 100px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .instruction-note {
            background: #e8f0fe;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.7rem;
            color: #003580;
            margin-top: 10px;
            text-align: center;
        }

        .card-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .booking-steps {
            display: flex;
            margin: 20px 0 25px;
            position: relative;
            padding: 0 10px;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 35px;
            height: 35px;
            background: #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: bold;
            font-size: 0.9rem;
            color: #666;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: #ff9800;
            color: white;
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.3);
        }

        .step.completed .step-number {
            background: #28a745;
            color: white;
        }

        .step-label {
            font-size: 0.7rem;
            color: #666;
            font-weight: 500;
        }

        .step.active .step-label {
            color: #ff9800;
            font-weight: 600;
        }

        .step.completed .step-label {
            color: #28a745;
        }

        .step-line {
            position: absolute;
            top: 17px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }

        .step:last-child .step-line {
            display: none;
        }

        .step-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .step-content.active {
            display: block;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-prev {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-prev:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-next {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-next:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }

        .review-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .review-section {
            margin-bottom: 15px;
        }

        .review-section h4 {
            color: #003580;
            margin-bottom: 10px;
            font-size: 0.85rem;
            border-left: 3px solid #ff9800;
            padding-left: 10px;
        }

        .review-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.8rem;
        }

        .review-label {
            width: 120px;
            font-weight: 600;
            color: #666;
        }

        .review-value {
            flex: 1;
            color: #333;
        }

        .error-message {
            background: #fff5f5;
            border-left: 3px solid #ff4444;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.75rem;
            color: #ff4444;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .payment-status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.8rem;
            margin-top: 10px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Destination Modal Styles (Matches Foreign Modal) */
        .destination-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .destination-modal.active {
            display: flex;
        }

        .destination-modal-content {
            background: white;
            border-radius: 24px;
            max-width: 900px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        .destination-modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .destination-modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .destination-modal-content::-webkit-scrollbar-thumb {
            background: #003580;
            border-radius: 10px;
        }

        .destination-modal-header {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            padding: 20px 25px;
            border-radius: 24px 24px 0 0;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }

        .close-modal:hover {
            transform: rotate(90deg);
            color: #ff9800;
        }

        .destination-modal-header h2 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .destination-modal-header p {
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }

        .destination-modal-body {
            padding: 20px;
        }

        .package-details-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 16px;
        }

        .detail-item {
            text-align: center;
        }

        .detail-item i {
            font-size: 1.3rem;
            color: #ff9800;
            margin-bottom: 6px;
            display: block;
        }

        .detail-label {
            font-size: 0.7rem;
            color: #666;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: #003580;
        }

        .itinerary-section {
            margin-bottom: 20px;
        }

        .itinerary-section h3 {
            color: #003580;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .itinerary-day {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid #ff9800;
        }

        .itinerary-day h4 {
            color: #003580;
            margin-bottom: 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .day-badge {
            background: #ff9800;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
        }

        .itinerary-day ul {
            list-style: none;
            padding-left: 0;
        }

        .itinerary-day li {
            padding: 4px 0;
            padding-left: 20px;
            position: relative;
            color: #555;
            font-size: 0.75rem;
        }

        .itinerary-day li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #ff9800;
        }

        .inclusions-section {
            background: #e8f0fe;
            padding: 15px;
            border-radius: 16px;
            margin-bottom: 15px;
            border-left: 4px solid #003580;
        }

        .inclusions-section h3 {
            color: #003580;
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .inclusions-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            list-style: none;
        }

        .inclusions-list li {
            padding-left: 22px;
            position: relative;
            color: #555;
            font-size: 0.8rem;
        }

        .inclusions-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
        }

        .exclusions-section {
            background: #fff3e0;
            padding: 15px;
            border-radius: 16px;
            margin-bottom: 20px;
            border-left: 4px solid #ff9800;
        }

        .exclusions-section h3 {
            color: #ff9800;
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .exclusions-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            list-style: none;
        }

        .exclusions-list li {
            padding-left: 22px;
            position: relative;
            color: #666;
            font-size: 0.8rem;
        }

        .exclusions-list li:before {
            content: "✗";
            position: absolute;
            left: 0;
            color: #dc3545;
        }

        .package-price-card {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            border: 2px solid #ff9800;
        }

        .package-price-card .price {
            font-size: 1.8rem;
            font-weight: 800;
            color: #ff9800;
        }

        .book-now-btn {
            background: #ff9800;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .book-now-btn:hover:not(:disabled) {
            background: #f57c00;
            transform: translateY(-2px);
        }

        .book-now-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .booking-form-modal {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .booking-form-modal h3 {
            color: #003580;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .booking-form-modal .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .booking-form-modal input,
        .booking-form-modal textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.8rem;
            font-family: inherit;
        }

        .booking-form-modal input:focus,
        .booking-form-modal textarea:focus {
            outline: none;
            border-color: #003580;
            box-shadow: 0 0 0 3px rgba(0, 53, 128, 0.1);
        }

        .booking-form-modal label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 0.75rem;
        }

        .booking-summary {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .booking-summary h4 {
            color: #003580;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dashed #e0e0e0;
            font-size: 0.75rem;
        }

        .summary-item.total {
            font-weight: bold;
            color: #ff9800;
            font-size: 0.85rem;
        }

        .success-message {
            text-align: center;
            padding: 30px;
        }

        .success-message i {
            font-size: 2.5rem;
            color: #28a745;
            margin-bottom: 12px;
        }

        .booking-number {
            background: #e8f0fe;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin: 10px 0;
            display: inline-block;
        }

        .details-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin: 15px 0;
            text-align: left;
        }

        /* Tab Pane Styles */
        .local-pane {
            display: none;
            animation: fadeIn 0.3s ease;
            background: white;
            padding: 5px 0;
        }

        .local-tab {
            transition: all 0.3s ease;
        }

        .local-tab.active {
            color: #003580 !important;
            border-bottom: 3px solid #ff9800 !important;
        }

        .home-saved-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 2200;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .home-saved-notification.show {
            transform: translateX(0);
        }

        .home-saved-notification.success {
            background: #28a745;
            color: white;
        }

        .home-saved-notification.error {
            background: #dc3545;
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .local-destination-hero {
                padding: 20px 15px;
            }

            .local-destination-hero h1 {
                font-size: 1.4rem;
            }

            .stats-bar {
                padding: 15px 20px;
            }

            .stat-number {
                font-size: 1.3rem;
            }

            .stat-label {
                font-size: 0.7rem;
                white-space: normal;
            }

            .filter-chip {
                padding: 5px 12px;
                font-size: 0.7rem;
            }

            .package-details-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-form-modal .form-row {
                grid-template-columns: 1fr;
                gap: 12px;
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

            .payment-methods {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .card-row {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .review-row {
                flex-direction: column;
            }

            .review-label {
                width: 100%;
                margin-bottom: 3px;
            }
        }

        @media (max-width: 480px) {
            .local-destination-hero h1 {
                font-size: 1.2rem;
            }

            .local-destination-hero p {
                font-size: 0.7rem;
            }

            .stats-bar {
                padding: 12px 15px;
            }

            .stat-number {
                font-size: 1.1rem;
            }

            .stat-label {
                font-size: 0.6rem;
            }

            .step-number {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }

            .step-label {
                font-size: 0.6rem;
            }

            .step-line {
                top: 15px;
            }
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

            <a href="index.php" class="sidebar-nav-item" id="nav-home">
                <i class="fas fa-home sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Home</span>
                <span class="sidebar-tooltip">Home</span>
            </a>

            <a href="local-destination.php" class="sidebar-nav-item active" id="nav-local">
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
                    style="<?php echo $auth->isLoggedIn() ? 'display:block;' : 'display:none;'; ?>">
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

    <section class="main-page-section">
        <div class="container" style="max-width: 1400px; margin: 0 auto;">
            <!-- Hero Section - Compact -->
            <div class="local-destination-hero">
                <h1>Discover the Philippines</h1>
                <p>Explore beautiful islands, beaches, and adventures our country has to offer.</p>
            </div>

            <!-- Stats Bar - All in One Row -->
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-number"><?= count(array_unique(array_column($allDestinations, 'location'))) ?>+
                    </div>
                    <div class="stat-label">Beautiful Destinations</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= count($allDestinations) ?>+</div>
                    <div class="stat-label">Tour Packages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Happy Travelers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">4.8</div>
                    <div class="stat-label">Customer Rating</div>
                </div>
            </div>

            <!-- Filter Chips -->
            <div class="destinations-filter" id="locationFilterContainer">
                <div class="filter-chip active" data-filter="all">All Destinations</div>
            </div>

            <!-- Destinations Grid -->
            <div class="destinations-grid-local" id="destinationsGrid"></div>

            <!-- Pagination Controls -->
            <div class="pagination-controls" id="paginationControls">
                <button class="pagination-btn" id="prevPageBtn" onclick="changePage(-1)">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="page-numbers" id="pageNumbers"></div>
                <button class="pagination-btn" id="nextPageBtn" onclick="changePage(1)">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Destination Detail Modal (Tour Itinerary & Booking) -->
    <div id="destinationModal" class="destination-modal">
        <div class="destination-modal-content">
            <div class="destination-modal-header"
                style="background: #003580; padding: 20px; border-radius: 24px 24px 0 0; position: relative;">
                <span class="close-modal" onclick="closeDestinationModal()"
                    style="color: white; position: absolute; top: 15px; right: 20px; font-size: 1.8rem; cursor: pointer;">&times;</span>
                <h2 id="modalDestName" style="color: white; margin: 0; font-size: 1.5rem;">Loading...</h2>
                <p id="modalDestLocation"
                    style="color: rgba(255,255,255,0.8); margin: 5px 0 0; display: flex; align-items: center; gap: 8px; font-size: 0.85rem;">
                    <i class="fas fa-map-marker-alt"></i> Loading...
                </p>
            </div>
            <div class="destination-modal-body" id="destinationModalBody">
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                    <p>Loading tour details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <div class="back-to-top" id="backToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Back Button -->
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

    <script src="js/main.js?v=2"></script>
    <script src="js/menu.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/auth-menu.js?v=2"></script>
    <script src="js/voucher-checkout.js"></script>

    <script>
        // Inject user info for booking forms
        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser();
        echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php $curr = $auth->getCurrentUser();
        echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';
    </script>
    <script>
        // ========================================
        // LOCAL DESTINATIONS DATA (From Database)
        // ========================================

        const allDestinations = <?= json_encode($allDestinations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Check if there's a specific destination to show
        <?php if ($selected_destination): ?>
            document.addEventListener('DOMContentLoaded', function () {
                showDestinationDetails('<?= $selected_destination['key'] ?>');
            });
        <?php endif; ?>

        <?php if (isset($booking_success) && $booking_success && isset($booking_data) && $booking_data): ?>
            document.addEventListener('DOMContentLoaded', function () {
                showBookingSuccess(<?= json_encode($booking_data) ?>);
            });
        <?php endif; ?>

        let currentPage = 1;
        const itemsPerPage = 4;
        let filteredDestinations = [...allDestinations];

        function formatNumber(num) {
            if (num === null || num === undefined) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function renderDestinations() {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageDestinations = filteredDestinations.slice(start, end);

            const grid = document.getElementById('destinationsGrid');
            if (!grid) return;

            if (pageDestinations.length === 0 && filteredDestinations.length === 0) {
                grid.innerHTML = '<div class="no-results" style="text-align:center; padding:60px 20px; grid-column: 1 / -1;"><i class="fas fa-search" style="font-size:3rem; color:#ccc; margin-bottom:15px; display:block;"></i><h3 style="color:#555;">No destinations found</h3><p style="color:#888;">We couldn\'t find any destinations matching your criteria. Please try another category!</p></div>';
                return;
            }

            grid.innerHTML = '';

            pageDestinations.forEach(dest => {
                const images = dest.images || [];
                const collageType = dest.collageType || 'three';
                let collageHtml = '';

                if (collageType === 'three' && images.length >= 3) {
                    collageHtml = `
                        <div class="collage-three">
                            <div class="collage-main">
                                <img src="${images[0] || ''}" alt="${escapeHtml(dest.name)}" onerror="this.src='https://via.placeholder.com/250x180?text=${escapeHtml(dest.name)}'">
                            </div>
                            <div class="collage-stack">
                                <img src="${images[1] || ''}" alt="${escapeHtml(dest.name)}" onerror="this.src='https://via.placeholder.com/150x90?text=${escapeHtml(dest.name)}'">
                                <img src="${images[2] || ''}" alt="${escapeHtml(dest.name)}" onerror="this.src='https://via.placeholder.com/150x90?text=${escapeHtml(dest.name)}'">
                            </div>
                        </div>
                    `;
                } else if (collageType === 'half' && images.length >= 2) {
                    collageHtml = `
                        <div class="collage-half">
                            <img src="${images[0] || ''}" alt="${escapeHtml(dest.name)}" onerror="this.src='https://via.placeholder.com/200x180?text=${escapeHtml(dest.name)}'">
                            <img src="${images[1] || ''}" alt="${escapeHtml(dest.name)}" onerror="this.src='https://via.placeholder.com/200x180?text=${escapeHtml(dest.name)}'">
                        </div>
                    `;
                } else {
                    const imgSrc = images[0] || 'https://via.placeholder.com/400x250?text=' + encodeURIComponent(dest.name);
                    collageHtml = `<img src="${imgSrc}" alt="${escapeHtml(dest.name)}" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='https://via.placeholder.com/400x250?text=${encodeURIComponent(dest.name)}'">`;
                }

                grid.innerHTML += `
                    <div class="destination-card-local" data-category="${dest.category}" onclick="showDestinationDetails('${dest.key}')">
                        <div class="destination-image">
                            <div class="image-collage">${collageHtml}</div>
                            <h3 class="destination-name-local">${escapeHtml(dest.name)}</h3>
                            ${dest.badge ? `<div class="home-card-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; z-index: 2; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(dest.badge)}</div>` : ''}
                        </div>
                        <div class="destination-info">
                            <div class="destination-location">
                                <i class="fas fa-map-marker-alt"></i> ${escapeHtml(dest.location)}
                            </div>
                            <p class="destination-description">${escapeHtml(dest.description)}</p>
                            <div class="destination-price">
                                From ${dest.currency || '₱'}${formatNumber(dest.price)}
                                <small>/ person</small>
                            </div>
                            <div class="destination-packages">
                                <button class="view-details-btn" onclick="event.stopPropagation(); showDestinationDetails('${dest.key}')">
                                    View Tour Details →
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            updatePaginationControls();
        }

        function updatePaginationControls() {
            const totalPages = Math.ceil(filteredDestinations.length / itemsPerPage);
            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            const pageNumbersDiv = document.getElementById('pageNumbers');

            if (!prevBtn || !nextBtn || !pageNumbersDiv) return;

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages || totalPages === 0;

            let pageHtml = '';
            const maxVisible = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);

            if (endPage - startPage + 1 < maxVisible) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            if (startPage > 1) {
                pageHtml += `<div class="page-number" onclick="goToPage(1)">1</div>`;
                if (startPage > 2) pageHtml += `<div class="page-dots">...</div>`;
            }

            for (let i = startPage; i <= endPage; i++) {
                pageHtml += `<div class="page-number ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</div>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) pageHtml += `<div class="page-dots">...</div>`;
                pageHtml += `<div class="page-number" onclick="goToPage(${totalPages})">${totalPages}</div>`;
            }

            pageNumbersDiv.innerHTML = pageHtml;
        }

        function goToPage(page) {
            if (page < 1) return;
            const totalPages = Math.ceil(filteredDestinations.length / itemsPerPage);
            if (page > totalPages) return;

            currentPage = page;
            renderDestinations();
            window.scrollTo({
                top: 300,
                behavior: 'smooth'
            });
        }

        function changePage(delta) {
            const totalPages = Math.ceil(filteredDestinations.length / itemsPerPage);
            const newPage = currentPage + delta;
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                renderDestinations();
                window.scrollTo({
                    top: 300,
                    behavior: 'smooth'
                });
            }
        }

        function filterDestinations(category) {
            if (category === 'all') {
                filteredDestinations = [...allDestinations];
            } else {
                filteredDestinations = allDestinations.filter(dest => {
                    if (!dest.location) return false;
                    const baseLoc = dest.location.split(',')[0].trim();
                    return baseLoc === category;
                });
            }
            currentPage = 1;
            renderDestinations();

            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.remove('active');
                if (chip.dataset.filter === category) {
                    chip.classList.add('active');
                }
            });
        }

        window.switchLocalTab = function (event, tabId) {
            const tabContainer = event.target.parentElement;
            const tabs = tabContainer.querySelectorAll('.local-tab');
            tabs.forEach(t => {
                t.classList.remove('active');
                t.style.color = '#666';
                t.style.borderBottomColor = 'transparent';
            });
            event.target.classList.add('active');
            event.target.style.color = '#003580';
            event.target.style.borderBottomColor = '#ff9800';

            const modalBody = document.getElementById('destinationModalBody');
            if (modalBody) {
                modalBody.querySelectorAll('.local-pane').forEach(p => p.style.display = 'none');
                const targetPane = modalBody.querySelector('#local-pane-' + tabId);
                if (targetPane) targetPane.style.display = 'block';
            }
        };

        // ========== UPDATED showDestinationDetails WITH PAYMENT METHODS ==========
        function showDestinationDetails(destKey) {
            const destination = allDestinations.find(d => d.key === destKey);
            if (!destination) return;

            const modal = document.getElementById('destinationModal');
            document.getElementById('modalDestName').textContent = destination.name;
            document.getElementById('modalDestLocation').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${destination.location}, Philippines`;

            // Hide the old modal header to show the new hero collage
            const oldHeader = modal.querySelector('.destination-modal-header');
            if (oldHeader) {
                oldHeader.style.display = 'none';
            }

            window.currentLocalDestCurrency = destination.currency || '₱';
            window.localSelectedHotelSurcharge = 0; // Initialize surcharge
            modal.classList.add('active');

            // Build itinerary HTML
            let itineraryHtml = `<div class="itinerary-section"><h3><i class="fas fa-list-ol"></i> Tour Itinerary</h3>`;

            if (destination.itinerary && destination.itinerary.length > 0) {
                destination.itinerary.forEach(day => {
                    const dayTitle = day.title || `Day ${day.day}`;
                    itineraryHtml += `
                    <div class="itinerary-day">
                        <h4><span class="day-badge">${escapeHtml(dayTitle.split(':')[0])}</span> ${escapeHtml(dayTitle.split(':')[1] || dayTitle)}</h4>
                        <ul>
                `;
                    if (day.activities && day.activities.length > 0) {
                        day.activities.forEach(activity => itineraryHtml += `<li>${escapeHtml(activity)}</li>`);
                    } else {
                        itineraryHtml += `<li>No activities listed</li>`;
                    }
                    itineraryHtml += `</ul></div>`;
                });
            } else {
                itineraryHtml += `<p>Itinerary coming soon...</p>`;
            }
            itineraryHtml += `</div>`;

            // Build remarks HTML
            let remarksHtml = '';
            if (destination.remarks && destination.remarks.trim().length > 0) {
                remarksHtml = `
                    <div class="remarks-section" style="margin-top: 20px; margin-bottom: 20px; padding: 15px; background: #fffde7; border-left: 4px solid #fbc02d; border-radius: 8px;">
                        <h3 style="margin-top: 0; margin-bottom: 10px; color: #f57f17; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle"></i> Remarks and Note
                        </h3>
                        <div style="white-space: pre-wrap; color: #5d4037; font-size: 0.95rem; line-height: 1.5;">${escapeHtml(destination.remarks)}</div>
                    </div>
                `;
            }

            // Build inclusions HTML
            let inclusionsHtml = `<div class="inclusions-section"><h3><i class="fas fa-check-circle"></i> Package Inclusions</h3><ul class="inclusions-list">`;
            if (destination.inclusions && destination.inclusions.length > 0) {
                destination.inclusions.forEach(item => inclusionsHtml += `<li>${escapeHtml(item)}</li>`);
            } else {
                inclusionsHtml += `<li>Inclusions coming soon...</li>`;
            }
            inclusionsHtml += `</ul></div>`;

            // Build exclusions HTML
            let exclusionsHtml = `<div class="exclusions-section"><h3><i class="fas fa-times-circle"></i> Package Exclusions</h3><ul class="exclusions-list">`;
            if (destination.exclusions && destination.exclusions.length > 0) {
                destination.exclusions.forEach(item => exclusionsHtml += `<li>${escapeHtml(item)}</li>`);
            } else {
                exclusionsHtml += `<li>Exclusions coming soon...</li>`;
            }
            exclusionsHtml += `</ul></div>`;

            // Store current destination for booking data
            window.currentLocalDest = destination;

            let html = `
            <span class="close-modal" onclick="closeDestinationModal()" style="position:absolute; top:15px; right:20px; color:white; font-size:1.8rem; cursor:pointer; z-index:10; text-shadow:0 2px 4px rgba(0,0,0,0.5);">&times;</span>
            
            <div id="localDetailsView">
                <div style="position:relative; height:250px; margin:-20px -20px 20px -20px; border-radius:24px 24px 0 0; overflow:hidden; background:#f0f0f0;">
                    ${(() => {
                    const images = destination.images || [];

                    if (images.length >= 3) {
                        return `
                                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:2px; height:100%;">
                                    <div style="height:100%;">
                                        <img src="${escapeHtml(images[0])}" alt="${escapeHtml(destination.name)}" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                    <div style="display:grid; grid-template-rows: 1fr 1fr; gap:2px; height:100%;">
                                        <img src="${escapeHtml(images[1])}" alt="${escapeHtml(destination.name)}" style="width:100%; height:100%; object-fit:cover;">
                                        <img src="${escapeHtml(images[2])}" alt="${escapeHtml(destination.name)}" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                </div>
                            `;
                    } else if (images.length === 2) {
                        return `
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2px; height:100%;">
                                    <img src="${escapeHtml(images[0])}" alt="${escapeHtml(destination.name)}" style="width:100%; height:100%; object-fit:cover;">
                                    <img src="${escapeHtml(images[1])}" alt="${escapeHtml(destination.name)}" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            `;
                    } else {
                        return `<img src="${escapeHtml(images[0] || 'images/placeholder-dest.jpg')}" alt="${escapeHtml(destination.name)}" style="width:100%; height:100%; object-fit:cover;">`;
                    }
                })()}
                    <div style="position:absolute; bottom:0; left:0; right:0; padding:40px 20px 15px; background:linear-gradient(to top, rgba(0,0,0,0.9), transparent); color:white;">
                        <h2 style="margin:0; font-size:1.6rem; text-shadow:0 2px 4px rgba(0,0,0,0.5);">${escapeHtml(destination.name)}</h2>
                        <p style="margin:5px 0 0; font-size:0.85rem; text-shadow:0 1px 2px rgba(0,0,0,0.5);"><i class="fas fa-map-marker-alt" style="color:#ff9800;"></i> ${escapeHtml(destination.location)}, Philippines | <i class="fas fa-clock" style="color:#ff9800;"></i> ${escapeHtml(destination.duration)}</p>
                    </div>
                </div>
                
                <div style="display:flex; overflow-x:auto; border-bottom:1px solid #ddd; margin-bottom:20px;">
                    <div class="local-tab active" onclick="switchLocalTab(event, 'info')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#003580; border-bottom:3px solid #ff9800; white-space:nowrap;">Overview</div>
                    <div class="local-tab" onclick="switchLocalTab(event, 'itinerary')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Itinerary</div>
                    <div class="local-tab" onclick="switchLocalTab(event, 'inclusions')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Inclusions</div>
                </div>

                <div id="local-pane-info" class="local-pane" style="display:block;">
                    <div class="package-details-grid" style="margin-bottom:15px;">
                        <div class="detail-item"><i class="fas fa-clock"></i><div class="detail-label">TRAVEL VALIDITY</div><div class="detail-value">${escapeHtml(destination.bestSeason)}</div></div>
                        <div class="detail-item"><i class="fas fa-users"></i><div class="detail-label">GROUP SIZE</div><div class="detail-value">${escapeHtml(destination.groupSize)}</div></div>
                        <div class="detail-item"><i class="fas fa-calendar-alt"></i><div class="detail-label">DURATION</div><div class="detail-value">${escapeHtml(destination.duration)}</div></div>
                        <div class="detail-item hotel-selection-item" onclick="toggleLocalHotelSelection()" style="cursor:pointer;">
                            <i class="fas fa-hotel"></i>
                            <div class="detail-label">HOTEL</div>
                            <div class="detail-value" id="localSelectedHotelName" style="color:#ff9800; font-weight:bold;">${destination.hotels && destination.hotels.length > 0 ? escapeHtml(destination.hotels[0].name) + (destination.hotels[0].stars ? '⭐'.repeat(destination.hotels[0].stars) : '') : 'Change Hotel'} <i class="fas fa-chevron-down" style="font-size:0.7rem; margin-left:5px;"></i></div>
                        </div>
                    </div>
                    <div id="localHotelDropdown" class="hotel-dropdown" style="display:none; margin: 10px 0; background: white; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        ${(destination.hotels || []).map((h, i) => `
                            <div class="hotel-option ${i === 0 ? 'active' : ''}" onclick="selectLocalHotel(${i}, '${escapeHtml(h.name).replace(/'/g, "\\'")}', ${h.stars || 0}, ${h.price})" style="padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:500;">${escapeHtml(h.name)} <span style="font-size:0.8rem;">${h.stars ? '⭐'.repeat(h.stars) : ''}</span></span>
                                    <span style="color:#4caf50; font-size:0.9rem;">${h.price > 0 ? `+${destination.currency || '₱'}${formatNumber(h.price)}` : 'Included'}</span>
                                </div>
                            </div>
                        `).join('')}
                        ${(!destination.hotels || destination.hotels.length === 0) ? '<div style="padding:15px; text-align:center; color:#888;">No other hotels available</div>' : ''}
                    </div>
                </div>

                <div id="local-pane-itinerary" class="local-pane" style="display:none;">
                    ${itineraryHtml}
                    ${remarksHtml}
                </div>
                
                <div id="local-pane-inclusions" class="local-pane" style="display:none;">
                    ${inclusionsHtml}
                    ${exclusionsHtml}
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                    <div>
                        <span style="font-size:0.8rem; color:#666;">Price starting from</span><br>
                        <span style="font-size:1.4rem; font-weight:800; color:#ff9800;">${destination.currency || '₱'}${formatNumber(destination.price)}</span>
                    </div>
                    <button onclick="document.getElementById('localDetailsView').style.display='none'; document.getElementById('localBookingView').style.display='block';" style="background:linear-gradient(135deg, #ff9800, #f57c00); color:white; border:none; padding:10px 25px; border-radius:30px; font-weight:bold; cursor:pointer;">
                        Book This Deal <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div id="localBookingView" style="display:none;">
                <div style="margin-bottom:15px;">
                    <button onclick="document.getElementById('localBookingView').style.display='none'; document.getElementById('localDetailsView').style.display='block';" style="background:none; border:none; color:#003580; font-weight:600; cursor:pointer;"><i class="fas fa-arrow-left"></i> Back to Details</button>
                </div>
                <div class="package-price-card">
                    <div class="price">${destination.currency || '₱'}${formatNumber(destination.price)}</div>
                    <small>/ person</small>
                    <div style="margin-top: 8px;">${escapeHtml(destination.duration)} tour package</div>
                </div>
            
            <!-- Booking Steps -->
            <div class="booking-steps">
                <div class="step active" id="localStep1">
                    <div class="step-number">1</div>
                    <div class="step-label">Date</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="localStep2">
                    <div class="step-number">2</div>
                    <div class="step-label">Info</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="localStep3">
                    <div class="step-number">3</div>
                    <div class="step-label">Review</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="localStep4">
                    <div class="step-number">4</div>
                    <div class="step-label">Payment</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="localStep5">
                    <div class="step-number">5</div>
                    <div class="step-label">Confirm</div>
                </div>
            </div>
            
            <!-- Step 1: Travel Date -->
            <div id="localStep1Content" class="step-content active">
                <div class="booking-form-modal">
                    <h3><i class="fas fa-calendar-alt"></i> Select Travel Date</h3>
                    <p style="color: #666; margin-bottom: 20px;">Please pick your preferred travel date to get started.</p>
                    
                    <div class="form-group">
                        <label>Travel Date *</label>
                        <input type="text" id="localStepDate" placeholder="Select your travel date" readonly style="cursor:pointer; background:#fff;">
                        <div id="localDateRangeInfo" style="display:none; margin-top:8px; padding:10px 14px; background:linear-gradient(135deg,#e3f2fd,#e8f5e9); border-left:4px solid #2196F3; border-radius:6px; font-size:0.9em; color:#0d47a1; position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <i class="fas fa-info-circle"></i> <span id="localDateRangeText"></span>
                                    <div id="localPromoEndingWarning" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                        <i class="fas fa-exclamation-triangle"></i> <span id="localPromoEndingWarningText"></span>
                                    </div>
                                    <div id="localBlockedDateConflict" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                        <i class="fas fa-ban"></i> <span id="localBlockedDateConflictText"></span>
                                    </div>
                                </div>
                                <button type="button" id="homeClearDateBtn" onclick="clearLocalDate()" style="background: rgba(0,0,0,0.05); border: none; border-radius: 4px; padding: 2px 6px; cursor: pointer; color: #0d47a1; font-size: 0.8rem; font-weight: 600; margin-left: 10px; white-space: nowrap;" title="Clear Selection">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="localStep1Errors" class="error-message" style="display: none;"></div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn-next" onclick="validateLocalStep1()">Next: Passenger Info <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Step 2: Passenger Information -->
            <div id="localStep2Content" class="step-content">
                <div class="booking-form-modal">
                    <h3><i class="fas fa-user"></i> Passenger Information</h3>
                    <div class="booking-summary" style="margin-bottom: 20px; background: #f9f9f9;">
                        <div class="summary-item"><span>Price per Person:</span><span>${destination.currency || '₱'}${formatNumber(destination.price)}</span></div>
                        <div class="summary-item"><span>Travelers:</span><span id="localStepSummaryTravelers">1</span></div>
                        <div class="summary-item total"><span>Total:</span><span id="localStepSummaryTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</span></div>
                    </div>

                    <!-- Voucher Section (Step 2) -->
                    <div id="localStep2VoucherArea" style="margin-bottom:18px;"></div>

                    <div class="form-row">
                        <div class="form-group"><label>Full Name *</label><input type="text" id="localStepFullName" placeholder="Enter your full name" value="${window.currentFullName || ''}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Phone *</label><input type="tel" id="localStepPhone" placeholder="+63 912 345 6789"></div>
                        <div class="form-group"><label>Travelers *</label><input type="number" id="localStepTravelers" min="1" value="1" onchange="updateLocalStepTotal(${destination.price})"></div>
                    </div>
                    <div class="form-group"><label>Special Requests</label><textarea id="localStepRequests" rows="2" placeholder="Any special requirements, dietary restrictions, etc."></textarea></div>
                    
                    <div id="localStep2Errors" class="error-message" style="display: none;"></div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn-prev" onclick="goToLocalStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn-next" onclick="validateLocalStep2()">Review Booking <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Step 3: Review -->
            <div id="localStep3Content" class="step-content">
                <div class="review-details">
                    <div class="review-section"><h4>Passenger Information</h4>
                        <div class="review-row"><div class="review-label">Full Name:</div><div class="review-value" id="localReviewName">-</div></div>
                        <div class="review-row"><div class="review-label">Email:</div><div class="review-value" id="localReviewEmail">-</div></div>
                        <div class="review-row"><div class="review-label">Phone:</div><div class="review-value" id="localReviewPhone">-</div></div>
                    </div>
                    <div class="review-section"><h4>Travel Details</h4>
                        <div class="review-row"><div class="review-label">Destination:</div><div class="review-value">${escapeHtml(destination.name)}</div></div>
                        <div class="review-row"><div class="review-label">Duration:</div><div class="review-value">${escapeHtml(destination.duration)}</div></div>
                        <div class="review-row"><div class="review-label">Travel Date:</div><div class="review-value" id="localReviewDate">-</div></div>
                        <div class="review-row"><div class="review-label">Travelers:</div><div class="review-value" id="localReviewTravelers">-</div></div>
                        <div class="review-row"><div class="review-label">Special Requests:</div><div class="review-value" id="localReviewRequests">-</div></div>
                    </div>
                    <div class="review-section"><h4>Price Summary</h4>
                        <div class="review-row"><div class="review-label">Price per Person:</div><div class="review-value">${destination.currency || '₱'}${formatNumber(destination.price)}</div></div>
                        <div class="review-row total"><div class="review-label">Total:</div><div class="review-value" id="localReviewTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</div></div>
                    </div>
                </div>
                <div class="action-buttons">
                    <button type="button" class="btn-prev" onclick="goToLocalStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn-next" onclick="goToLocalStep(4)">Proceed to Payment <i class="fas fa-credit-card"></i></button>
                </div>
            </div>
            
            <!-- Step 4: Payment Methods -->
            <div id="localStep4Content" class="step-content">
                <div class="booking-form-modal">
                    <h3><i class="fas fa-credit-card"></i> Select Payment Method</h3>
                    <div class="payment-methods">
                        <div class="payment-method" onclick="selectLocalPaymentMethod('gcash', event)">
                            <input type="radio" name="local_payment" value="gcash" id="localGcashRadio">
                            <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                            <div class="payment-info">
                                <div class="payment-name">GCash</div>
                                <div class="payment-desc">Scan QR code to pay</div>
                            </div>
                        </div>
                        <div class="payment-method" onclick="selectLocalPaymentMethod('paymaya', event)">
                            <input type="radio" name="local_payment" value="paymaya" id="localPaymayaRadio">
                            <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                            <div class="payment-info">
                                <div class="payment-name">PayMaya</div>
                                <div class="payment-desc">Scan QR code to pay</div>
                            </div>
                        </div>
                        <div class="payment-method" onclick="selectLocalPaymentMethod('card', event)">
                            <input type="radio" name="local_payment" value="card" id="localCardRadio">
                            <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                            <div class="payment-info">
                                <div class="payment-name">Credit / Debit Card</div>
                                <div class="payment-desc">Visa, Mastercard, JCB</div>
                            </div>
                        </div>
                        <div class="payment-method disabled" onclick="alert('Bank Transfer is currently unavailable. Please use GCash or PayMaya for now.')" style="opacity: 0.6; cursor: not-allowed; position: relative;">
                            <input type="radio" name="local_payment" value="bank" id="localBankRadio" disabled>
                            <div class="payment-icon"><i class="fas fa-university"></i></div>
                            <div class="payment-info">
                                <div class="payment-name">Bank Transfer <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div>
                                <div class="payment-desc">Coming Soon</div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="localGcashDetails" class="payment-details-box">
                        <!-- ... (same code) ... -->
                    </div>
                    <!-- (other payment details boxes) -->
                    
                    <div id="localStep4Errors" class="error-message" style="display: none;"></div>
                    <div class="action-buttons">
                        <button type="button" class="btn-prev" onclick="goToLocalStep(3)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn-next" onclick="validateLocalPayment()">Complete Payment <i class="fas fa-check-circle"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Step 5: Confirmation -->
            <div id="localStep5Content" class="step-content">
                <div class="success-message">
                    <i class="fas fa-clock"></i>
                    <h2>⏳ Booking Received!</h2>
                    <p>Your booking has been submitted and is pending payment verification.</p>
                    <div class="booking-number" id="localBookingNumber">Booking: Processing...</div>
                    <div class="details-card">
                        <h4>📋 Booking Details:</h4>
                        <p><strong>Destination:</strong> ${escapeHtml(destination.name)}</p>
                        <p><strong>Duration:</strong> ${escapeHtml(destination.duration)}</p>
                        <p><strong>Travel Date:</strong> <span id="localConfirmDate">-</span></p>
                        <p><strong>Travelers:</strong> <span id="localConfirmTravelers">-</span></p>
                        <p><strong>Total Amount:</strong> <span style="color:#ff9800;" id="localConfirmTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</span></p>
                        <p><strong>Payment Method:</strong> <span id="localConfirmPayment">-</span></p>
                        <p><strong>Payment Status:</strong> <span style="color:#ff9800;">Pending Verification</span></p>
                        <p><strong>Booked By:</strong> <span id="localConfirmName">-</span></p>
                    </div>
                    <div class="payment-status-pending"><i class="fas fa-info-circle"></i> Your payment is pending verification. Our team will contact you shortly.</div>
                    <div class="action-buttons">
                        <button class="book-now-btn" onclick="closeDestinationModal()" style="background: #ff9800; width: auto;">Close</button>
                    </div>
                </div>
            </div>
        `;

            document.getElementById('destinationModalBody').innerHTML = html;

            // ── Flatpickr calendar for travel date ──────────────────────────────────
            const blockedDates = (destination.blocked_dates || '')
                .split(',')
                .map(d => d.trim())
                .filter(Boolean);

            // Parse blocked months (CSV string "1,2,3" -> array of numbers [0,1,2] for JS)
            const blockedMonths = (destination.blocked_months || '')
                .split(',')
                .map(m => parseInt(m.trim()) - 1)
                .filter(m => !isNaN(m));

            const highlightDuration = parseInt(destination.highlight_duration || 1);

            flatpickr('#localStepDate', {
                minDate: destination.promo_start && new Date(destination.promo_start) > new Date() ? destination.promo_start : 'today',
                maxDate: destination.promo_end || null,
                disableMobile: true,
                disable: [
                    ...blockedDates,
                    function (date) {
                        // Disable based on blocked months
                        return blockedMonths.includes(date.getMonth());
                    }
                ],
                onDayCreate: function (dObj, dStr, fp, dayElem) {
                    const date = dayElem.dateObj;
                    const selectedDate = fp.selectedDates[0];

                    if (selectedDate) {
                        const startTime = new Date(selectedDate).setHours(0, 0, 0, 0);
                        const endTime = new Date(selectedDate);
                        endTime.setDate(endTime.getDate() + highlightDuration - 1);
                        const endTimeTime = endTime.setHours(0, 0, 0, 0);
                        const currentTime = date.setHours(0, 0, 0, 0);

                        if (currentTime >= startTime && currentTime <= endTimeTime) {
                            dayElem.classList.add('promo-range-red');
                        } else {
                            dayElem.classList.add('promo-pale');
                        }
                    }
                },
                onChange: function (selectedDates, dateStr, instance) {
                    if (!selectedDates.length) return;

                    const start = selectedDates[0];
                    const end = new Date(start);
                    end.setDate(end.getDate() + highlightDuration - 1);

                    // Redraw to apply classes
                    instance.redraw();

                    // Show range info banner
                    const rangeInfo = document.getElementById('localDateRangeInfo');
                    const rangeText = document.getElementById('localDateRangeText');
                    const warningBox = document.getElementById('localPromoEndingWarning');
                    const warningText = document.getElementById('localPromoEndingWarningText');

                    if (rangeInfo && rangeText) {
                        const opts = { month: 'short', day: 'numeric', year: 'numeric' };
                        const durText = highlightDuration > 1 ? `${highlightDuration} Days` : '1 Day';
                        rangeText.textContent = `Your trip: ${start.toLocaleDateString(undefined, opts)} → ${end.toLocaleDateString(undefined, opts)} (${durText})`;
                        rangeInfo.style.display = 'block';

                        // CHECK PROMO END VALIDATION
                        let promoExpired = false;
                        if (destination.promo_end) {
                            const promoEnd = new Date(destination.promo_end);
                            promoEnd.setHours(23, 59, 59, 999);

                            if (end > promoEnd) {
                                promoExpired = true;
                                warningText.textContent = `Trip Unavailable: Your ${durText} trip extends beyond the promo period (ends ${promoEnd.toLocaleDateString(undefined, opts)}).`;
                                warningBox.style.display = 'block';
                            } else {
                                warningBox.style.display = 'none';
                            }
                        }

                        // CHECK BLOCKED DATE CONFLICTS (Range Check)
                        const conflictBox = document.getElementById('localBlockedDateConflict');
                        const conflictText = document.getElementById('localBlockedDateConflictText');
                        const blockedDatesArr = (destination.blocked_dates || '').split(',').map(d => d.trim()).filter(Boolean);

                        let foundConflict = null;
                        const checkDate = new Date(start);

                        for (let i = 0; i < highlightDuration; i++) {
                            const yyyy = checkDate.getFullYear();
                            const mm = String(checkDate.getMonth() + 1).padStart(2, '0');
                            const dd = String(checkDate.getDate()).padStart(2, '0');
                            const dateStr = `${yyyy}-${mm}-${dd}`;
                            const month = checkDate.getMonth();

                            if (blockedDatesArr.includes(dateStr)) {
                                foundConflict = `${checkDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} is a blocked date.`;
                                break;
                            }
                            if (blockedMonths.includes(month)) {
                                foundConflict = `${checkDate.toLocaleDateString(undefined, { month: 'long' })} is a blocked month.`;
                                break;
                            }
                            checkDate.setDate(checkDate.getDate() + 1);
                        }

                        const clearBtn = document.getElementById('homeClearDateBtn');

                        if (foundConflict || promoExpired) {
                            window.localDateConflict = true;
                            if (foundConflict) {
                                conflictText.textContent = `Trip Unavailable: ${foundConflict} Please choose another start date.`;
                                conflictBox.style.display = 'block';
                            } else {
                                conflictBox.style.display = 'none';
                            }

                            // Set Red Style (Error)
                            rangeInfo.style.background = 'linear-gradient(135deg,#ffebee,#ffcdd2)';
                            rangeInfo.style.borderLeft = '4px solid #f44336';
                            rangeInfo.style.color = '#b71c1c';
                            if (clearBtn) clearBtn.style.color = '#b71c1c';
                        } else {
                            window.localDateConflict = false;
                            conflictBox.style.display = 'none';

                            // Set Blue Style (Normal)
                            rangeInfo.style.background = 'linear-gradient(135deg,#e3f2fd,#e8f5e9)';
                            rangeInfo.style.borderLeft = '4px solid #2196F3';
                            rangeInfo.style.color = '#0d47a1';
                            if (clearBtn) clearBtn.style.color = '#0d47a1';
                        }
                    }
                }
            });

            window.localDateConflict = false; // Reset state

            window.clearLocalDate = function () {
                const fp = document.querySelector('#localStepDate')._flatpickr;
                if (fp) {
                    fp.clear();
                    fp.redraw();
                }
                const rangeInfo = document.getElementById('localDateRangeInfo');
                if (rangeInfo) rangeInfo.style.display = 'none';
                document.getElementById('localStepDate').value = '';
            };
            // ──────────────────────────────────────────────────────────────────────────

            // Initialize total update
            document.getElementById('localStepTravelers').addEventListener('change', function () {
                updateLocalStepTotal(destination.price);
            });
        }

        // Helper functions for payment methods
        let localSelectedPayment = null;
        let localBookingData = null;

        function copyToClipboardLocal(text) {
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.style.background = '#28a745';
                btn.style.color = 'white';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '#e0e0e0';
                    btn.style.color = '';
                }, 1500);
            });
        }

        function formatNumber(num) {
            if (num === null || num === undefined) return '0';
            return new Intl.NumberFormat('en-US').format(num);
        }

        function updateLocalStepTotal(price) {
            const travelers = parseInt(document.getElementById('localStepTravelers').value) || 1;
            const hotelSurcharge = window.localSelectedHotelSurcharge || 0;
            const total = (travelers * price) + hotelSurcharge;
            document.getElementById('localStepSummaryTravelers').textContent = travelers;
            document.getElementById('localStepSummaryTotal').textContent = (window.currentLocalDestCurrency || '₱') + formatNumber(total);
            if (document.getElementById('localGcashAmount')) document.getElementById('localGcashAmount').textContent = formatNumber(total);
            if (document.getElementById('localPaymayaAmount')) document.getElementById('localPaymayaAmount').textContent = formatNumber(total);
            if (document.getElementById('localBankAmount')) document.getElementById('localBankAmount').textContent = formatNumber(total);

            if (typeof updateVoucherTotalInline === 'function') {
                updateVoucherTotalInline('local', total);
            }
        }

        // ========== HOTEL SELECTION LOGIC (GLOBAL) ==========
        window.localSelectedHotelSurcharge = 0;
        window.toggleLocalHotelSelection = function () {
            const dropdown = document.getElementById('localHotelDropdown');
            if (dropdown) dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        };
        window.selectLocalHotel = function (index, name, stars, price) {
            const basePrice = window.currentLocalDest ? window.currentLocalDest.price : 0;
            const nameEl = document.getElementById('localSelectedHotelName');
            const starHtml = stars ? ` <span style="font-size:0.8rem;">${'⭐'.repeat(stars)}</span>` : '';
            if (nameEl) nameEl.innerHTML = name + starHtml + ' <i class="fas fa-chevron-down" style="font-size:0.7rem; margin-left:5px;"></i>';
            window.localSelectedHotelSurcharge = price;
            updateLocalStepTotal(basePrice);
            const dropdown = document.getElementById('localHotelDropdown');
            if (dropdown) dropdown.style.display = 'none';
            document.querySelectorAll('#localHotelDropdown .hotel-option').forEach((el, i) => {
                el.style.background = i === index ? '#e3f2fd' : 'white';
            });
        };

        function goToLocalStep(step) {
            for (let i = 1; i <= 5; i++) {
                const stepDiv = document.getElementById(`localStep${i}`);
                const contentDiv = document.getElementById(`localStep${i}Content`);
                if (stepDiv) {
                    if (i < step) {
                        stepDiv.classList.add('completed');
                        stepDiv.classList.remove('active');
                    } else if (i === step) {
                        stepDiv.classList.add('active');
                        stepDiv.classList.remove('completed');
                    } else {
                        stepDiv.classList.remove('active', 'completed');
                    }
                }
                if (contentDiv) {
                    if (i === step) {
                        contentDiv.classList.add('active');
                    } else {
                        contentDiv.classList.remove('active');
                    }
                }
            }
            // Init voucher widget on Step 2 (Passenger Info)
            if (step === 2 && typeof initVoucherCheckoutInline === 'function') {
                const price = window.currentLocalDest ? window.currentLocalDest.price : 0;
                const travelers = parseInt(document.getElementById('localStepTravelers')?.value) || 1;
                const hotelSurcharge = window.localSelectedHotelSurcharge || 0;
                const total = (price * travelers) + hotelSurcharge;
                initVoucherCheckoutInline(
                    'local',
                    total,
                    'local_destinations',
                    'localStep2VoucherArea',
                    'localStepSummaryTotal',
                    () => window.currentLocalDestCurrency || '₱',
                    window.currentLocalDest ? window.currentLocalDest.id : 0
                );
            }
            // Init voucher widget when Payment step (4) is shown
            if (step === 4 && localBookingData) {
                const total = localBookingData.totalAmount;
                if (document.getElementById('localGcashAmount')) document.getElementById('localGcashAmount').textContent = formatNumber(total);
                if (document.getElementById('localPaymayaAmount')) document.getElementById('localPaymayaAmount').textContent = formatNumber(total);
                if (document.getElementById('localBankAmount')) document.getElementById('localBankAmount').textContent = formatNumber(total);
            }
        }

        function validateLocalStep1() {
            const errors = [];
            const travelDate = document.getElementById('localStepDate').value;

            if (!travelDate) errors.push('Travel Date is required');
            if (window.localDateConflict) errors.push('Selected range includes blocked dates. Please pick another date.');

            if (errors.length > 0) {
                const errorDiv = document.getElementById('localStep1Errors');
                errorDiv.style.display = 'flex';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
                errorDiv.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }

            // Require login before proceeding to passenger info
            requireLogin('resumeLocalBooking', window.currentLocalDest.key, 2);
        }

        window.resumeLocalBooking = function (key, step) {
            const modal = document.getElementById('destinationModal');
            if (modal && modal.classList.contains('active')) {
                // Modal is already open, just go to the next step
                goToLocalStep(step);
            } else {
                // Modal not open (likely after login redirect), load it first
                showDestinationDetails(key);
                setTimeout(() => {
                    // Pre-fill user info if available
                    if (window.currentFullName) {
                        const nameField = document.getElementById('localStepFullName');
                        if (nameField) nameField.value = window.currentFullName;
                    }
                    goToLocalStep(step);
                }, 500); // Small delay to allow modal to render
            }
        };

        function validateLocalStep2() {
            const errors = [];
            const fullName = document.getElementById('localStepFullName').value.trim();
            const phone = document.getElementById('localStepPhone').value.trim();
            const travelers = document.getElementById('localStepTravelers').value;

            if (!fullName) errors.push('Full Name is required');

            // Use auto-detected email from session
            const email = window.currentUserEmail || '';
            if (!email) errors.push('Error: User session not found. Please log in again.');

            if (!phone) errors.push('Phone number is required');
            if (!travelers || travelers < 1) errors.push('At least 1 traveler is required');

            if (errors.length > 0) {
                const errorDiv = document.getElementById('localStep2Errors');
                errorDiv.style.display = 'flex';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
                errorDiv.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }

            const travelDate = document.getElementById('localStepDate').value;

            localBookingData = {
                fullName,
                email,
                phone,
                travelDate,
                travelers: parseInt(travelers),
                specialRequests: document.getElementById('localStepRequests').value.trim() || ''
            };

            document.getElementById('localReviewName').textContent = fullName;
            document.getElementById('localReviewEmail').textContent = email;
            document.getElementById('localReviewPhone').textContent = phone;
            document.getElementById('localReviewDate').textContent = new Date(travelDate).toLocaleDateString();
            document.getElementById('localReviewTravelers').textContent = travelers;
            document.getElementById('localReviewRequests').textContent = localBookingData.specialRequests || 'None';

            const price = window.currentLocalDest ? window.currentLocalDest.price : 0;
            const hotelSurcharge = window.localSelectedHotelSurcharge || 0;
            const rawTotal = (travelers * price) + hotelSurcharge;
            const appliedVoucher = window._appliedVoucher && window._appliedVoucher['local'];
            const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : rawTotal;

            localBookingData.totalAmount = finalAmount;

            const reviewTotalEl = document.getElementById('localReviewTotal');
            if (reviewTotalEl) {
                if (appliedVoucher) {
                    reviewTotalEl.innerHTML = `${window.currentLocalDestCurrency || '₱'}${formatNumber(finalAmount)} <span style="text-decoration:line-through;color:#94a3b8;font-size:0.8em;">${window.currentLocalDestCurrency || '₱'}${formatNumber(rawTotal)}</span>`;
                } else {
                    reviewTotalEl.textContent = (window.currentLocalDestCurrency || '₱') + formatNumber(rawTotal);
                }
            }

            goToLocalStep(3);
        }

        // Select payment method - FIXED (added event parameter)
        function selectLocalPaymentMethod(method, event) {
            localSelectedPayment = method;
            const targetElement = event.currentTarget;
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            targetElement.classList.add('selected');
            document.querySelectorAll('input[name="local_payment"]').forEach(radio => radio.checked = false);
            const radio = document.getElementById(`local${method.charAt(0).toUpperCase() + method.slice(1)}Radio`);
            if (radio) radio.checked = true;

            // Hide all payment details boxes
            document.querySelectorAll('.payment-details-box').forEach(box => {
                box.classList.remove('show');
            });

            // Show selected payment details
            const detailsBox = document.getElementById(`local${method.charAt(0).toUpperCase() + method.slice(1)}Details`);
            if (detailsBox) detailsBox.classList.add('show');
        }

        function handleLocalFileUpload(event, paymentMethod) {
            const file = event.target.files[0];
            if (file) {
                if (!file.type.match('image.*')) {
                    alert('Please upload an image file (PNG, JPG, JPEG)');
                    event.target.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    event.target.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (e) {
                    const previewDiv = document.getElementById(`local-preview-${paymentMethod}`);
                    if (previewDiv) {
                        previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof" style="max-width:100%; max-height:100px; border-radius:8px;">`;
                    }
                };
                reader.readAsDataURL(file);
                const fileNameSpan = document.getElementById(`local-file-name-${paymentMethod}`);
                if (fileNameSpan) {
                    fileNameSpan.textContent = file.name;
                }
            }
        }

        function validateLocalPayment() {
            const errors = [];
            const price = window.currentLocalDest ? window.currentLocalDest.price : 0;
            const travelers = localBookingData ? localBookingData.travelers : 1;
            const hotelSurcharge = window.localSelectedHotelSurcharge || 0;
            const totalAmount = (price * travelers) + hotelSurcharge;

            if (!localSelectedPayment) errors.push('Please select a payment method');

            if (localSelectedPayment === 'gcash') {
                const ref = document.getElementById('localPaymentRefGcash')?.value.trim();
                if (!ref) errors.push('Please enter the GCash reference number');
            }
            if (localSelectedPayment === 'paymaya') {
                const ref = document.getElementById('localPaymentRefPaymaya')?.value.trim();
                if (!ref) errors.push('Please enter the PayMaya reference number');
            }
            if (localSelectedPayment === 'card') {
                if (!document.getElementById('localCardNumber')?.value.trim()) errors.push('Card Number is required');
                if (!document.getElementById('localExpiryDate')?.value.trim()) errors.push('Expiry Date is required');
                if (!document.getElementById('localCvv')?.value.trim()) errors.push('CVV is required');
                if (!document.getElementById('localCardName')?.value.trim()) errors.push('Cardholder Name is required');
            }
            if (localSelectedPayment === 'bank') {
                const ref = document.getElementById('localBankRef')?.value.trim();
                if (!ref) errors.push('Reference Number is required');
            }

            if (errors.length > 0) {
                const errorDiv = document.getElementById('localStep4Errors');
                errorDiv.style.display = 'flex';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
                errorDiv.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return false;
            }

            let paymentMethodName = '';
            let paymentRef = '';
            if (localSelectedPayment === 'gcash') { paymentMethodName = 'GCash'; paymentRef = document.getElementById('localPaymentRefGcash')?.value.trim(); }
            else if (localSelectedPayment === 'paymaya') { paymentMethodName = 'PayMaya'; paymentRef = document.getElementById('localPaymentRefPaymaya')?.value.trim(); }
            else if (localSelectedPayment === 'card') { paymentMethodName = 'Credit/Debit Card'; paymentRef = 'Card ending in ' + document.getElementById('localCardNumber')?.value.slice(-4); }
            else if (localSelectedPayment === 'bank') { paymentMethodName = 'Bank Transfer'; paymentRef = document.getElementById('localBankRef')?.value.trim(); }

            const btn = document.querySelector('#localStep4Content .btn-next');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            // Apply voucher discount if one was applied
            const appliedVoucher = window._appliedVoucher && window._appliedVoucher['local'];
            const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : totalAmount;

            fetch('api/save-local-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    destination_id: window.currentLocalDest.id,
                    destination_name: window.currentLocalDest.name,
                    package_duration: window.currentLocalDest.duration,
                    price_per_person: price,
                    full_name: localBookingData.fullName,
                    email: localBookingData.email,
                    phone: localBookingData.phone,
                    travel_date: localBookingData.travelDate,
                    number_of_travelers: localBookingData.travelers,
                    special_requests: localBookingData.specialRequests,
                    total_amount: finalAmount,
                    payment_method: localSelectedPayment,
                    payment_reference: paymentRef,
                    voucher_id: appliedVoucher ? appliedVoucher.id : null,
                    voucher_discount: appliedVoucher ? appliedVoucher.discountAmount : null,
                    currency: window.currentLocalDestCurrency || '₱'
                })
            })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;

                    if (data.success) {
                        document.getElementById('localBookingNumber').innerHTML = 'Booking: ' + data.booking_number;
                        document.getElementById('localConfirmDate').textContent = new Date(localBookingData.travelDate).toLocaleDateString();
                        document.getElementById('localConfirmTravelers').textContent = localBookingData.travelers;
                        document.getElementById('localConfirmTotal').innerHTML = (window.currentLocalDestCurrency || '₱') + formatNumber(finalAmount);
                        document.getElementById('localConfirmPayment').textContent = paymentMethodName;
                        document.getElementById('localConfirmName').textContent = localBookingData.fullName;

                        goToLocalStep(5);
                    } else {
                        alert(data.message || 'Error saving booking');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    alert('Connection error. Please try again.');
                });

            return true;
        }

        // Keep existing functions unchanged
        function updateModalTotal(price) {
            const travelers = parseInt(document.getElementById('modalTravelersCount').value) || 1;
            const total = travelers * price;
            document.getElementById('modalSummaryTravelers').textContent = travelers;
            document.getElementById('modalSummaryTotal').textContent = (window.currentLocalDestCurrency || '₱') + formatNumber(total);
        }

        function submitBooking(event, destinationId, price, destinationName, duration) {
            event.preventDefault();
            const form = event.target;
            const fullName = form.querySelector('[name="full_name"]').value.trim();
            const email = form.querySelector('[name="email"]').value.trim();
            const phone = form.querySelector('[name="phone"]').value.trim();
            const travelers = parseInt(form.querySelector('[name="travelers"]').value) || 1;
            const travelDate = form.querySelector('[name="travel_date"]').value;
            const specialRequests = form.querySelector('[name="special_requests"]').value.trim();
            const totalAmount = price * travelers;

            if (!fullName) {
                showNotification('Please enter your full name', 'error');
                return false;
            }
            if (!email) {
                showNotification('Please enter your email address', 'error');
                return false;
            }
            if (!email.match(/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/)) {
                showNotification('Please enter a valid email address', 'error');
                return false;
            }
            if (!phone) {
                showNotification('Please enter your phone number', 'error');
                return false;
            }
            if (!travelDate) {
                showNotification('Please select a travel date', 'error');
                return false;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            fetch('api/save-local-booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    destination_id: destinationId,
                    destination_name: destinationName,
                    package_duration: duration,
                    price_per_person: price,
                    full_name: fullName,
                    email: email,
                    phone: phone,
                    travel_date: travelDate,
                    number_of_travelers: travelers,
                    special_requests: specialRequests,
                    total_amount: totalAmount
                })
            })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;

                    if (data.success) {
                        const bookingNumber = data.booking_number || 'LOC-' + Math.random().toString(36).substr(2, 8).toUpperCase();
                        const modalBody = document.getElementById('destinationModalBody');
                        modalBody.innerHTML = `
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h2>🎉 Booking Confirmed!</h2>
                        <p>Your booking for <strong>${escapeHtml(destinationName)}</strong> has been submitted successfully.</p>
                        <div class="booking-number">Booking: ${bookingNumber}</div>
                        <div class="details-card">
                            <p><strong>📋 Booking Details:</strong></p>
                            <p><strong>📍 Destination:</strong> ${escapeHtml(destinationName)}</p>
                            <p><strong>📅 Duration:</strong> ${escapeHtml(duration)}</p>
                            <p><strong>📅 Travel Date:</strong> ${new Date(travelDate).toLocaleDateString()}</p>
                            <p><strong>👥 Travelers:</strong> ${travelers} person(s)</p>
                            <p><strong>💰 Total Amount:</strong> <span style="color: #ff9800; font-size: 1.1rem;">${window.currentLocalDestCurrency || '₱'}${formatNumber(totalAmount)}</span></p>
                            <p><strong>👤 Booked By:</strong> ${escapeHtml(fullName)}</p>
                            <p><strong>📧 Email:</strong> ${escapeHtml(email)}</p>
                            <p><strong>📞 Phone:</strong> ${escapeHtml(phone)}</p>
                            ${specialRequests ? `<p><strong>📝 Special Requests:</strong> ${escapeHtml(specialRequests)}</p>` : ''}
                        </div>
                        <p>A confirmation has been recorded in our system. Our team will contact you shortly.</p>
                        <button class="book-now-btn" onclick="closeDestinationModal()" style="width: auto; margin-top: 20px;">Close</button>
                    </div>`;
                        document.dispatchEvent(new CustomEvent('bookingUpdated'));
                    } else {
                        showNotification(data.message || 'Booking failed. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    showNotification('Network error. Please check your connection and try again.', 'error');
                });

            return false;
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `home-saved-notification ${type}`;
            notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        function showBookingSuccess(bookingData) {
            const modal = document.getElementById('destinationModal');
            const modalBody = document.getElementById('destinationModalBody');

            document.getElementById('modalDestName').textContent = 'Booking Confirmed!';
            document.getElementById('modalDestLocation').innerHTML = '<i class="fas fa-check-circle"></i> Your booking is complete';

            modalBody.innerHTML = `
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <h2>🎉 Booking Confirmed!</h2>
                <p>Your booking for <strong>${escapeHtml(bookingData.destination_name)}</strong> has been submitted successfully.</p>
                <div class="booking-number">Booking: ${bookingData.booking_number}</div>
                <div class="details-card">
                    <p><strong>📋 Booking Details:</strong></p>
                    <p><strong>📍 Destination:</strong> ${escapeHtml(bookingData.destination_name)}</p>
                    <p><strong>📅 Travel Date:</strong> ${new Date(bookingData.travel_date).toLocaleDateString()}</p>
                    <p><strong>👥 Travelers:</strong> ${bookingData.travelers} person(s)</p>
                    <p><strong>💰 Total Amount:</strong> <span style="color: #ff9800; font-size: 1.1rem;">${bookingData.currency || '₱'}${formatNumber(bookingData.total_amount)}</span></p>
                    <p><strong>👤 Booked By:</strong> ${escapeHtml(bookingData.full_name)}</p>
                    <p><strong>📧 Email:</strong> ${escapeHtml(bookingData.email)}</p>
                    <p><strong>📞 Phone:</strong> ${escapeHtml(bookingData.phone)}</p>
                    ${bookingData.special_requests ? `<p><strong>📝 Special Requests:</strong> ${escapeHtml(bookingData.special_requests)}</p>` : ''}
                </div>
                <p>A confirmation has been recorded. Our team will contact you shortly.</p>
                <button class="book-now-btn" onclick="closeDestinationModal()" style="width: auto; margin-top: 20px;">Close</button>
            </div>`;

            modal.classList.add('active');
        }

        function closeDestinationModal() {
            document.getElementById('destinationModal').classList.remove('active');
            localSelectedPayment = null;
            localBookingData = null;
        }

        // Alias for the close button in the dynamic HTML
        window.closeModal = closeDestinationModal;

        document.addEventListener('DOMContentLoaded', function () {
            renderDestinations();

            // Generate location chips dynamically based on allDestinations
            const filterContainer = document.querySelector('.destinations-filter');
            if (filterContainer) {
                const locationCounts = {};
                allDestinations.forEach(dest => {
                    if (!dest.location) return;
                    const baseLoc = dest.location.split(',')[0].trim();
                    locationCounts[baseLoc] = (locationCounts[baseLoc] || 0) + 1;
                });

                Object.keys(locationCounts).sort().forEach(loc => {
                    // Show chip if there's at least 1 package for the location
                    if (locationCounts[loc] >= 1) {
                        const chip = document.createElement('div');
                        chip.className = 'filter-chip';
                        chip.dataset.filter = loc;
                        chip.textContent = loc;
                        chip.addEventListener('click', function () {
                            filterDestinations(loc);
                        });
                        filterContainer.appendChild(chip);
                    }
                });

                const allChip = filterContainer.querySelector('[data-filter="all"]');
                if (allChip) {
                    allChip.addEventListener('click', function () {
                        filterDestinations('all');
                    });
                }
            }

            document.querySelectorAll('.destination-modal').forEach(modal => {
                modal.addEventListener('click', function (e) {
                    if (e.target === this) {
                        this.classList.remove('active');
                    }
                });
            });

            window.addEventListener('scroll', function () {
                const btn = document.getElementById('backToTop');
                if (window.scrollY > 300) btn.classList.add('show');
                else btn.classList.remove('show');
            });

            const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
            document.querySelectorAll('#savedCount').forEach(el => {
                if (el) el.textContent = savedItems.length;
            });
        });
    </script>
    <script src="js/saved.js"></script>
</body>

</html>

</html>
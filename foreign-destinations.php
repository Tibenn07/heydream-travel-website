<?php
// ========================================
// FILE: foreign-destinations.php
// DESCRIPTION: Foreign Destinations Page - Loads from Database
// ========================================
require_once __DIR__ . '/config/database.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch all active foreign destinations from database
$destinations = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, dest_key, name, country, city, location,
            description, short_description,
            price, currency, duration, activities_count, group_size, best_season,
            itinerary, inclusions, exclusions,
            image_path, image2_path, image3_path,
            collage_type, category, badge_text,
            is_active, display_order, blocked_dates, promo_start, promo_end,
            blocked_months, highlight_duration, remarks, hotels
        FROM foreign_destinations 
        WHERE is_active = 1 
        ORDER BY display_order, id ASC
    ");
    $stmt->execute();
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each destination for frontend display
    foreach ($destinations as &$dest) {
        // Parse JSON fields
        if ($dest['itinerary']) {
            $dest['itinerary'] = json_decode($dest['itinerary'], true);
            if (!is_array($dest['itinerary']))
                $dest['itinerary'] = [];
        } else {
            $dest['itinerary'] = [];
        }

        if ($dest['inclusions']) {
            $dest['inclusions'] = json_decode($dest['inclusions'], true);
            if (!is_array($dest['inclusions']))
                $dest['inclusions'] = [];
        } else {
            $dest['inclusions'] = [];
        }

        if ($dest['exclusions']) {
            $dest['exclusions'] = json_decode($dest['exclusions'], true);
            if (!is_array($dest['exclusions']))
                $dest['exclusions'] = [];
        } else {
            $dest['exclusions'] = [];
        }

        if ($dest['hotels']) {
            $dest['hotels'] = json_decode($dest['hotels'], true);
            if (!is_array($dest['hotels']))
                $dest['hotels'] = [];
        } else {
            $dest['hotels'] = [];
        }

        // Set default values
        $dest['duration'] = $dest['duration'] ?? '4D/3N';
        $dest['group_size'] = $dest['group_size'] ?? '2-15 pax';
        $dest['best_season'] = $dest['best_season'] ?? 'Year Round';
        $dest['activities_count'] = $dest['activities_count'] ?? 0;
        $dest['collage_type'] = $dest['collage_type'] ?? 'three';
        $dest['category'] = $dest['category'] ?? 'asia';
        $dest['badge_text'] = $dest['badge_text'] ?? '';
        $dest['currency'] = $dest['currency'] ?? '₱';

        // Build image paths array with full paths
        $dest['images'] = [];
        if ($dest['image_path'])
            $dest['images'][] = $dest['image_path'];
        if ($dest['image2_path'])
            $dest['images'][] = $dest['image2_path'];
        if ($dest['image3_path'])
            $dest['images'][] = $dest['image3_path'];

        // If no images, use placeholder
        if (empty($dest['images'])) {
            $dest['images'][] = 'https://via.placeholder.com/400x250?text=' . urlencode($dest['name']);
        }
    }
} catch (PDOException $e) {
    // If table doesn't exist yet, use empty array
    $destinations = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Foreign Destinations</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ========================================
           FOREIGN DESTINATIONS PAGE STYLES
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

        .foreign-destination-hero {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            color: white;
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease;
        }

        .foreign-destination-hero h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .foreign-destination-hero p {
            font-size: 0.9rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.5;
        }

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

        .destinations-grid-foreign {
            display: grid;
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (min-width: 1200px) {
            .destinations-grid-foreign {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (min-width: 992px) and (max-width: 1199px) {
            .destinations-grid-foreign {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .destinations-grid-foreign {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        @media (max-width: 767px) {
            .destinations-grid-foreign {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .destinations-grid-foreign {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        .destination-card-foreign {
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
            height: 100%;
        }

        .destination-card-foreign:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.15);
        }

        .destination-image {
            position: relative;
            height: 180px;
            overflow: hidden;
        }

        .image-collage {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
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

        .destination-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            z-index: 2;
        }

        .destination-info {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .destination-name-foreign {
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
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 34px;
        }

        .destination-price {
            font-size: 1rem;
            font-weight: 700;
            color: #ff9800;
            margin-bottom: 8px;
        }

        .destination-price small {
            font-size: 0.65rem;
            color: #888;
            font-weight: normal;
        }

        .destination-packages {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 8px;
            border-top: 1px solid #e9ecef;
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

        .upload-preview {
            margin-top: 10px;
            text-align: center;
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

        .booking-form-modal label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 0.75rem;
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
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 6px;
            list-style: none;
            padding-left: 0;
        }

        .exclusions-list li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 8px;
            color: #555;
            font-size: 0.75rem;
        }

        .exclusions-list li:before {
            content: "✗";
            position: absolute;
            left: 0;
            color: #dc3545;
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

        @media (max-width: 768px) {
            .foreign-destination-hero {
                padding: 20px 15px;
            }

            .foreign-destination-hero h1 {
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

            .payment-methods {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .package-details-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-form-modal .form-row {
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
            .foreign-destination-hero h1 {
                font-size: 1.2rem;
            }

            .foreign-destination-hero p {
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

            <a href="local-destination.php" class="sidebar-nav-item" id="nav-local">
                <i class="fas fa-map-marker-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Local Tours</span>
                <span class="sidebar-tooltip">Local Tours</span>
            </a>

            <a href="foreign-destinations.php" class="sidebar-nav-item active" id="nav-foreign">
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
            <div class="foreign-destination-hero">
                <h1>Explore the World</h1>
                <p>Discover amazing destinations across Asia and beyond. Book your dream international vacation today!
                </p>
            </div>

            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-number" id="statCountries">
                        <?= count(array_unique(array_column($destinations, 'country'))) ?>+
                    </div>
                    <div class="stat-label">Countries</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="statPackages"><?= count($destinations) ?>+</div>
                    <div class="stat-label">Tour Packages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">2,000+</div>
                    <div class="stat-label">Happy Travelers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">4.7</div>
                    <div class="stat-label">Customer Rating</div>
                </div>
            </div>

            <div class="destinations-filter" id="locationFilterContainer">
                <div class="filter-chip active" data-filter="all">All Destinations</div>
            </div>

            <div class="destinations-grid-foreign" id="destinationsGrid">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading destinations...</p>
                </div>
            </div>

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

    <!-- Destination Detail Modal -->
    <div id="foreignPackageModal" class="package-modal">
        <div class="package-modal-content">
            <div class="package-modal-header">
                <span class="close-modal" onclick="closeForeignPackageModal()">&times;</span>
                <h2 id="foreignModalDestName">Loading...</h2>
                <p id="foreignModalDestLocation"><i class="fas fa-map-marker-alt"></i> Loading...</p>
            </div>
            <div class="package-modal-body" id="foreignPackageModalBody">
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                    <p>Loading tour details...</p>
                </div>
            </div>
        </div>
    </div>

    <div class="back-to-top" id="backToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <i class="fas fa-arrow-up"></i>
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

    <script>
        // Inject user info for booking forms
        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser();
        echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php $curr = $auth->getCurrentUser();
        echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';
    </script>
    <script src="js/main.js?v=2"></script>
    <script src="js/menu.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/auth-menu.js?v=2"></script>
    <script src="js/voucher-checkout.js"></script>
    <script src="js/foreign-packages.js?v=4"></script>

    <script>
        // Pagination functionality
        let currentPage = 1;
        const itemsPerPage = 4;
        let allDestinations = <?= json_encode($destinations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let filteredDestinations = [...allDestinations];

        function formatNumber(num) {
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
                                <img src="${images[0] || ''}" alt="${dest.name}" onerror="this.src='https://via.placeholder.com/250x180?text=${dest.name}'">
                            </div>
                            <div class="collage-stack">
                                <img src="${images[1] || ''}" alt="${dest.name}" onerror="this.src='https://via.placeholder.com/150x90?text=${dest.name}'">
                                <img src="${images[2] || ''}" alt="${dest.name}" onerror="this.src='https://via.placeholder.com/150x90?text=${dest.name}'">
                            </div>
                        </div>
                    `;
                } else if (collageType === 'half' && images.length >= 2) {
                    collageHtml = `
                        <div class="collage-half">
                            <img src="${images[0] || ''}" alt="${dest.name}" onerror="this.src='https://via.placeholder.com/200x180?text=${dest.name}'">
                            <img src="${images[1] || ''}" alt="${dest.name}" onerror="this.src='https://via.placeholder.com/200x180?text=${dest.name}'">
                        </div>
                    `;
                } else {
                    const imgSrc = images[0] || 'https://via.placeholder.com/400x250?text=' + dest.name;
                    collageHtml = `<img src="${imgSrc}" alt="${dest.name}" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='https://via.placeholder.com/400x250?text=${dest.name}'">`;
                }

                grid.innerHTML += `
                    <div class="destination-card-foreign" data-category="${dest.category}" onclick="showForeignPackagePopup('${dest.dest_key}')">
                        <div class="destination-image">
                            <div class="image-collage">${collageHtml}</div>
                            <h3 class="destination-name-foreign">${escapeHtml(dest.name)}</h3>
                            ${dest.badge_text ? `<div class="destination-badge">${escapeHtml(dest.badge_text)}</div>` : ''}
                        </div>
                        <div class="destination-info">
                            <div class="destination-location">
                                <i class="fas fa-map-marker-alt"></i> ${escapeHtml(dest.location || (dest.city + ', ' + dest.country))}
                            </div>
                            <p class="destination-description">${escapeHtml(dest.short_description || dest.description || 'Discover this amazing destination.')}</p>
                            <div class="destination-price">
                                From ${dest.currency || '₱'}${formatNumber(dest.price)}
                                <small>/ person</small>
                            </div>
                            <div class="destination-packages">
                                <button class="view-details-btn" onclick="event.stopPropagation(); showForeignPackagePopup('${dest.dest_key}')">
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
            window.scrollTo({ top: 300, behavior: 'smooth' });
        }

        function changePage(delta) {
            const totalPages = Math.ceil(filteredDestinations.length / itemsPerPage);
            const newPage = currentPage + delta;
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                renderDestinations();
                window.scrollTo({ top: 300, behavior: 'smooth' });
            }
        }

        function filterDestinations(category) {
            if (category === 'all') {
                filteredDestinations = [...allDestinations];
            } else {
                filteredDestinations = allDestinations.filter(dest => {
                    if (!dest.location && !dest.country) return false;
                    // For foreign destinations, it might make sense to group by country or the location name
                    const baseLoc = dest.country || dest.location;
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

        document.addEventListener('DOMContentLoaded', function () {
            renderDestinations();

            // Generate location chips dynamically based on allDestinations
            const filterContainer = document.querySelector('.destinations-filter');
            if (filterContainer) {
                const locationCounts = {};
                allDestinations.forEach(dest => {
                    // Use country from DB or fallback
                    let baseLoc = dest.country || dest.location;
                    if (!baseLoc) return;

                    locationCounts[baseLoc] = (locationCounts[baseLoc] || 0) + 1;
                });

                Object.keys(locationCounts).sort().forEach(loc => {
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

            window.addEventListener('scroll', function () {
                const btn = document.getElementById('backToTop');
                if (window.scrollY > 300) btn.classList.add('show');
                else btn.classList.remove('show');
            });

            const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
            document.querySelectorAll('#savedCount').forEach(el => { if (el) el.textContent = savedItems.length; });
        });




        function validateFlashPayment() {
            // First check if user is logged in
            fetch('api/check-auth.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) {
                        showLoginRequiredPopup();
                        return;
                    }

                    // Continue with existing payment validation
                    const errors = [];
                    // ... rest of your validation code ...
                });
        }



    </script>
    <script src="js/saved.js"></script>

    <!-- ===== HEYDREAM AI CHATBOT WIDGET ===== -->
    <?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>

</html>

</html>
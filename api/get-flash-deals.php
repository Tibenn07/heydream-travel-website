<?php
// api/get-flash-deals.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get parameters
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
    $identifier = isset($_GET['id']) ? $_GET['id'] : null;

    try {
        // Strip common prefixes added by frontend JS
        if ($identifier && !is_numeric($identifier)) {
            if (strpos($identifier, 'flash_') === 0) $identifier = substr($identifier, 6);
            if (strpos($identifier, 'deal_') === 0) $identifier = substr($identifier, 5);
        }

        $id = is_numeric($identifier) ? intval($identifier) : 0;
        $titleSlug = !is_numeric($identifier) ? $identifier : '';

    if ($identifier) {
        // Fetch single flash deal
        if ($id > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM flash_deals
                WHERE id = ?
            ");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM flash_deals
                WHERE (title = ? OR REPLACE(LOWER(title), ' ', '_') = ?)
                LIMIT 1
            ");
            $stmt->execute([$titleSlug, $titleSlug]);
        }
        $deal = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback: no exact/slug title match — try a fuzzy LIKE match
        // (handles callers that only have a partial or slightly different
        // display name, e.g. admin previews resolving a historical booking).
        if (!$deal && $titleSlug !== '') {
            $fallbackStmt = $pdo->prepare("
                SELECT * FROM flash_deals
                WHERE title LIKE ? OR ? LIKE CONCAT('%', title, '%')
                LIMIT 1
            ");
            $fallbackStmt->execute(['%' . $titleSlug . '%', $titleSlug]);
            $deal = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($deal) {
            // Parse JSON fields
            $deal['itinerary'] = $deal['itinerary'] ? json_decode($deal['itinerary'], true) : [];
            $deal['inclusions'] = $deal['inclusions'] ? json_decode($deal['inclusions'], true) : [];
            $deal['exclusions'] = $deal['exclusions'] ? json_decode($deal['exclusions'], true) : [];
            $deal['hotels'] = $deal['hotels'] ? json_decode($deal['hotels'], true) : [];
            
            // Set default values
            $deal['duration'] = $deal['duration'] ?? '3D/2N';
            $deal['group_size'] = $deal['group_size'] ?? '2-15 pax';
            $deal['best_season'] = $deal['best_season'] ?? 'Year Round';
            
            echo json_encode(['success' => true, 'deal' => $deal]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Flash deal not found', 'deleted' => true]);
        }
    } else {
        // Fetch all flash deals
        $sql = "
            SELECT 
                id, title, short_description, category, location,
                price, currency, original_price, discount_percent, duration, group_size, best_season,
                rating, reviews, booked_count, badge_text,
                itinerary, inclusions, exclusions, hotels,
                image_path, image2_path, image3_path,
                collage_type, is_active, description, remarks, blocked_dates,
                promo_start, promo_end, blocked_months, highlight_duration,
                partner_id, partner_company
            FROM flash_deals 
            WHERE is_active = 1 
            AND (promo_end IS NULL OR promo_end = '' OR promo_end >= CURDATE())
            ORDER BY display_order, id ASC
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . $limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process each deal
        foreach ($deals as &$deal) {
            // Parse JSON fields
            $deal['itinerary'] = $deal['itinerary'] ? json_decode($deal['itinerary'], true) : [];
            $deal['inclusions'] = $deal['inclusions'] ? json_decode($deal['inclusions'], true) : [];
            $deal['exclusions'] = $deal['exclusions'] ? json_decode($deal['exclusions'], true) : [];
            $deal['hotels'] = $deal['hotels'] ? json_decode($deal['hotels'], true) : [];
            
            // Set default values
            $deal['duration'] = $deal['duration'] ?? '3D/2N';
            $deal['group_size'] = $deal['group_size'] ?? '2-15 pax';
            $deal['best_season'] = $deal['best_season'] ?? 'Year Round';
            $deal['collage_type'] = $deal['collage_type'] ?? 'three';
            
            // Calculate discount percent if not set
            if (!$deal['discount_percent'] && $deal['original_price'] > 0 && $deal['price'] > 0) {
                $deal['discount_percent'] = round((($deal['original_price'] - $deal['price']) / $deal['original_price']) * 100);
            }
        }
        
        echo json_encode(['success' => true, 'deals' => $deals]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

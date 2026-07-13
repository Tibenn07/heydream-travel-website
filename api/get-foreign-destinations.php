<?php
// File: api/get-foreign-destinations.php
// API endpoint to fetch foreign destinations (single or multiple)

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$key = isset($_GET['key']) ? $_GET['key'] : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
$category = isset($_GET['category']) ? $_GET['category'] : null;

try {
    // If a specific key is provided, fetch a single destination (for popup)
    if ($key) {
        // Strip common prefixes added by frontend JS
        if (strpos($key, 'foreign_') === 0) $key = substr($key, 8);
        if (strpos($key, 'popular_') === 0) $key = substr($key, 8);
        
        $isNumeric = is_numeric($key);
        $sql = "
            SELECT 
                fd.*, 
                COALESCE(pr.business_display_name, p.company_name, fd.partner_company) AS partner_company
            FROM foreign_destinations fd
            LEFT JOIN partner_applications p ON fd.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = fd.partner_id
            WHERE " . ($isNumeric ? "fd.id = ?" : "fd.dest_key = ?") . "
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key]);
        $destination = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback: no exact dest_key match — try matching by name (handles
        // callers that only have a display name/title, e.g. admin previews
        // resolving a historical booking's package name).
        if (!$destination && !$isNumeric) {
            $fallbackStmt = $pdo->prepare("
                SELECT
                    fd.*,
                    COALESCE(pr.business_display_name, p.company_name, fd.partner_company) AS partner_company
                FROM foreign_destinations fd
                LEFT JOIN partner_applications p ON fd.partner_id = p.id
                LEFT JOIN partner_profiles pr ON pr.partner_id = fd.partner_id
                WHERE (fd.name = :name OR REPLACE(LOWER(fd.name), ' ', '_') = :name OR fd.name LIKE :name_like)
                LIMIT 1
            ");
            $fallbackStmt->execute(['name' => $key, 'name_like' => '%' . $key . '%']);
            $destination = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($destination) {
            // Parse JSON fields
            $destination['itinerary'] = $destination['itinerary'] ? json_decode($destination['itinerary'], true) : [];
            $destination['inclusions'] = $destination['inclusions'] ? json_decode($destination['inclusions'], true) : [];
            $destination['exclusions'] = $destination['exclusions'] ? json_decode($destination['exclusions'], true) : [];
            $destination['hotels'] = $destination['hotels'] ? json_decode($destination['hotels'], true) : [];
            
            // Set default values
            $destination['duration'] = $destination['duration'] ?? '4D/3N';
            $destination['group_size'] = $destination['group_size'] ?? '2-15 pax';
            $destination['best_season'] = $destination['best_season'] ?? 'Year Round';
            $destination['activities_count'] = $destination['activities_count'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'destination' => $destination
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Destination not found',
                'deleted' => true
            ]);
        }
    } 
    // Otherwise, fetch multiple destinations (for homepage grid)
    else {
        $sql = "
            SELECT 
                fd.dest_key, fd.name, fd.country, fd.city, fd.location,
                fd.description, fd.short_description,
                fd.price, fd.currency, fd.duration, fd.badge_text,
                fd.image_path, fd.image2_path, fd.image3_path,
                fd.remarks, fd.blocked_dates,
                fd.promo_start, fd.promo_end, fd.blocked_months, fd.highlight_duration,
                fd.partner_id,
                COALESCE(pr.business_display_name, p.company_name, fd.partner_company) AS partner_company
            FROM foreign_destinations fd
            LEFT JOIN partner_applications p ON fd.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = fd.partner_id
            WHERE fd.is_active = 1 
            ORDER BY fd.display_order, fd.id ASC
        ";
        
        $params = [];
        
        // Apply category filter if provided
        if ($category && $category !== 'all') {
            $sql = str_replace("WHERE is_active = 1", "WHERE is_active = 1 AND category LIKE :category", $sql);
            $params['category'] = '%' . $category . '%';
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Apply limit if specified (for homepage)
        if ($limit > 0 && count($destinations) > $limit) {
            $destinations = array_slice($destinations, 0, $limit);
        }
        
        echo json_encode([
            'success' => true,
            'destinations' => $destinations,
            'count' => count($destinations)
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

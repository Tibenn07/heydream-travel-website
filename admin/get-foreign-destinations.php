<?php
// ========================================
// FILE: api/get-foreign-destinations.php
// DESCRIPTION: API endpoint to fetch foreign destinations
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
$destKey = isset($_GET['key']) ? $_GET['key'] : null;

try {
    if ($destKey) {
        // Fetch single destination by key
        $stmt = $pdo->prepare("
            SELECT 
                id, dest_key, name, country, city, location,
                description, short_description,
                price, duration, activities_count, group_size, best_season,
                itinerary, inclusions, exclusions,
                image_path, image2_path, image3_path,
                collage_type, category, badge_text
            FROM foreign_destinations 
            WHERE dest_key = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$destKey]);
        $destination = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($destination) {
            // Parse JSON fields
            $destination['itinerary'] = json_decode($destination['itinerary'], true) ?: [];
            $destination['inclusions'] = json_decode($destination['inclusions'], true) ?: [];
            $destination['exclusions'] = json_decode($destination['exclusions'], true) ?: [];
            
            echo json_encode([
                'success' => true,
                'destination' => $destination
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Destination not found'
            ]);
        }
    } else {
        // Fetch multiple destinations
        $sql = "
            SELECT 
                dest_key, name, country, city, location,
                description, short_description,
                price, duration, badge_text,
                image_path
            FROM foreign_destinations 
            WHERE is_active = 1 
            ORDER BY display_order, id ASC
        ";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'destinations' => $destinations,
            'count' => count($destinations)
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

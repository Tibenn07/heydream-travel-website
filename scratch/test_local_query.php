<?php
require 'config/database.php';
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, name, location_name, city, description, price, currency, duration, 
            activities_count, group_size, best_season, itinerary, inclusions, 
            exclusions, hotels, image_path, image2_path, image3_path, image4_path, 
            collage_type, category, badge_text, is_active, promo_start, promo_end, 
            blocked_months, highlight_duration, blocked_dates, remarks
        FROM destinations 
        WHERE type = 'local' 
        AND is_active = 1 
        ORDER BY id ASC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "SUCCESS: Fetched " . count($results) . " local destinations.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>

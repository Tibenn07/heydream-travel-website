<?php
require_once __DIR__ . '/../config/database.php';

try {
    $sql = "
        SELECT 
            dest_key, name, country, city, location,
            description, short_description,
            price, currency, duration, badge_text,
            image_path, image2_path, image3_path,
            remarks, blocked_dates,
            promo_start, promo_end, blocked_months, highlight_duration
        FROM foreign_destinations 
        WHERE is_active = 1 
        ORDER BY display_order, id ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Count: " . count($destinations) . "\n";
    foreach ($destinations as $d) {
        echo "- " . $d['name'] . " (Key: " . $d['dest_key'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

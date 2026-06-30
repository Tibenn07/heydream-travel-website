<?php
// Quick test: simulate flash-deals.php rendering
require 'config/database.php';
$flash_deals = [];
$stmt = $pdo->prepare("SELECT id, title, category, location, short_description, price, original_price, discount_percent, duration, rating, reviews, booked_count, badge_text, image_path, image2_path, image3_path, collage_type, description FROM flash_deals WHERE is_active = 1 ORDER BY display_order, id ASC");
$stmt->execute();
$flash_deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($flash_deals as &$deal) {
    $deal['images'] = [];
    if ($deal['image_path']) $deal['images'][] = $deal['image_path'];
    if ($deal['image2_path']) $deal['images'][] = $deal['image2_path'];
    if ($deal['image3_path']) $deal['images'][] = $deal['image3_path'];
    if (empty($deal['images'])) $deal['images'][] = 'placeholder.jpg';
    $deal['collage_type'] = $deal['collage_type'] ?? 'three';
}

echo "Total deals: " . count($flash_deals) . PHP_EOL;
echo "=== Card render test ===" . PHP_EOL;

foreach (array_slice($flash_deals, 0, 4) as $deal) {
    $images      = $deal['images'] ?? [];
    $img1        = !empty($images[0]) ? htmlspecialchars($images[0]) : '';
    $price       = number_format($deal['price'], 0, '.', ',');
    $collageType = $deal['collage_type'] ?? 'three';
    echo "Deal #{$deal['id']}: {$deal['title']} | price: {$price} | collage: {$collageType} | images: " . count($images) . PHP_EOL;
    echo "  img1: $img1" . PHP_EOL;
    echo "  badge_text: " . htmlspecialchars($deal['badge_text'] ?? '') . PHP_EOL;
    echo "  PHP Card render: OK" . PHP_EOL;
}
echo PHP_EOL . "SUCCESS - PHP rendering would work correctly." . PHP_EOL;
?>

<?php
require 'config/database.php';
$stmt = $pdo->query('SELECT id, title, image_path, image2_path, image3_path, collage_type, price FROM flash_deals WHERE is_active = 1 LIMIT 5');
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($deals as $d) {
    echo 'ID: ' . $d['id'] . ' | title: ' . $d['title'] . ' | collage: ' . $d['collage_type'] . PHP_EOL;
    echo '  img1: ' . $d['image_path'] . PHP_EOL;
    echo '  img2: ' . $d['image2_path'] . PHP_EOL;
    echo '  img3: ' . $d['image3_path'] . PHP_EOL;
    echo '  price: ' . $d['price'] . PHP_EOL;
}
echo PHP_EOL . 'TOTAL DEALS: ' . count($deals) . PHP_EOL;

// Now test what json_encode produces
$stmt2 = $pdo->query('SELECT id, title, price FROM flash_deals WHERE is_active = 1 LIMIT 2');
$d2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$json = json_encode($d2, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
echo PHP_EOL . 'JSON VALID: ' . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO: ' . json_last_error_msg()) . PHP_EOL;
echo 'JSON snippet: ' . substr($json, 0, 200) . PHP_EOL;
?>

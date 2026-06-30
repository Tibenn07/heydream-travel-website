<?php
// File: api/search-packages.php
// Unified search API for all tour packages and visas

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 1) {
    echo json_encode([
        'success' => true,
        'results' => [],
        'count' => 0,
        'message' => 'Query too short'
    ]);
    exit;
}

$searchTerm = '%' . $query . '%';
$results = [];

try {
    // 1. Search Flash Deals
    $stmt = $pdo->prepare("
        SELECT id, title as name, location, description, price, currency, image_path, 'flash' as type
        FROM flash_deals
        WHERE is_active = 1 AND (
            title LIKE :q OR 
            location LIKE :q OR 
            description LIKE :q OR 
            category LIKE :q OR
            itinerary LIKE :q OR
            hotels LIKE :q
        )
        LIMIT 10
    ");
    $stmt->execute(['q' => $searchTerm]);
    $flashDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($flashDeals as $item) $results[] = $item;

    // 2. Search Foreign Destinations
    $stmt = $pdo->prepare("
        SELECT id, name, dest_key, city, country, location, description, price, currency, image_path, 'foreign' as type
        FROM foreign_destinations
        WHERE is_active = 1 AND (
            name LIKE :q OR 
            city LIKE :q OR 
            country LIKE :q OR 
            location LIKE :q OR 
            description LIKE :q OR
            itinerary LIKE :q OR
            hotels LIKE :q
        )
        LIMIT 10
    ");
    $stmt->execute(['q' => $searchTerm]);
    $foreign = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($foreign as $item) $results[] = $item;

    // 3. Search Local Destinations
    $stmt = $pdo->prepare("
        SELECT id, name, city, country, location_name as location, description, price, currency, image_path, 'local' as type
        FROM destinations
        WHERE type = 'local' AND is_active = 1 AND (
            name LIKE :q OR 
            city LIKE :q OR 
            country LIKE :q OR 
            location_name LIKE :q OR 
            description LIKE :q OR
            itinerary LIKE :q OR
            hotels LIKE :q
        )
        LIMIT 10
    ");
    $stmt->execute(['q' => $searchTerm]);
    $local = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($local as $item) $results[] = $item;

    // 4. Search Visas
    $stmt = $pdo->prepare("
        SELECT id, title as name, description, price, currency, icon_value as image_path, 'visa' as type
        FROM visas
        WHERE is_active = 1 AND (
            title LIKE :q OR 
            description LIKE :q
        )
        LIMIT 5
    ");
    $stmt->execute(['q' => $searchTerm]);
    $visas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($visas as $item) $results[] = $item;

    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

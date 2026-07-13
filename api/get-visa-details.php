<?php
// File: api/get-visa-details.php
// Get a single visa service by ID or title (visas have no numeric id sent
// on booking, since buttons/visa.php only sends the title as package_name).

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$identifier = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['title']) ? $_GET['title'] : '');
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$isNumeric = is_numeric($identifier);
$id = $isNumeric ? intval($identifier) : 0;
$name = !$isNumeric ? trim((string) $identifier) : '';

if (!$id && $name === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid ID or Title']);
    exit;
}

try {
    $categoryClause = $category !== '' ? ' AND category = :category' : '';

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM visas WHERE id = :id" . $categoryClause);
        $params = ['id' => $id];
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM visas
            WHERE (title = :name OR REPLACE(LOWER(title), ' ', '_') = :name OR title LIKE :name_like)
            {$categoryClause}
            LIMIT 1
        ");
        $params = ['name' => $name, 'name_like' => '%' . $name . '%'];
    }
    if ($category !== '') { $params['category'] = $category; }
    $stmt->execute($params);
    $visa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visa) {
        echo json_encode(['success' => false, 'error' => 'Visa not found', 'deleted' => true]);
        exit;
    }

    $visa['price'] = floatval($visa['price']);

    echo json_encode(['success' => true, 'data' => $visa]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

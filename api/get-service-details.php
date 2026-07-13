<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$identifier = isset($_GET['id']) ? $_GET['id'] : '';
$isNumeric = is_numeric($identifier);
$id = $isNumeric ? intval($identifier) : 0;
$name = !$isNumeric ? trim((string) $identifier) : '';
$serviceType = isset($_GET['type']) ? trim((string) $_GET['type']) : '';

if (!$id && $name === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $typeClause = $serviceType !== '' ? ' AND service_type = :service_type' : '';

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM site_services WHERE id = :id" . $typeClause);
        $params = ['id' => $id];
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM site_services
            WHERE (title = :name OR REPLACE(LOWER(title), ' ', '_') = :name OR title LIKE :name_like)
            {$typeClause}
            LIMIT 1
        ");
        $params = ['name' => $name, 'name_like' => '%' . $name . '%'];
    }
    if ($serviceType !== '') { $params['service_type'] = $serviceType; }
    $stmt->execute($params);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found', 'deleted' => true]);
        exit;
    }

    $id = (int) $service['id'];

    // Fetch itinerary (Wrap in secondary try-catch to avoid crashing if table is missing)
    try {
        $it_stmt = $pdo->prepare("SELECT * FROM service_itinerary WHERE service_id = ? ORDER BY day_number");
        $it_stmt->execute([$id]);
        $service['itinerary'] = $it_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $service['itinerary'] = []; // Fallback to empty if table doesn't exist
    }

    echo json_encode(['success' => true, 'data' => $service]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

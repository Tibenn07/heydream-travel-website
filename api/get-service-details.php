<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM site_services WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit;
    }

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

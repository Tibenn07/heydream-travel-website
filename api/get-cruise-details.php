<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM cruises WHERE id = ? AND is_published = 1");
    $stmt->execute([$id]);
    $cruise = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cruise) {
        echo json_encode(['success' => false, 'message' => 'Cruise not found']);
        exit;
    }

    // Fetch itinerary
    $it_stmt = $pdo->prepare("SELECT * FROM cruise_itinerary WHERE cruise_id = ? ORDER BY day_number");
    $it_stmt->execute([$id]);
    $cruise['itinerary'] = $it_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $cruise]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

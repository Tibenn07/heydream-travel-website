<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$identifier = isset($_GET['id']) ? $_GET['id'] : '';
$isNumeric = is_numeric($identifier);
$id = $isNumeric ? intval($identifier) : 0;
$name = !$isNumeric ? trim((string) $identifier) : '';

if (!$id && $name === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM cruises WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM cruises
            WHERE (title = :name OR REPLACE(LOWER(title), ' ', '_') = :name OR title LIKE :name_like)
            LIMIT 1
        ");
        $stmt->execute(['name' => $name, 'name_like' => '%' . $name . '%']);
    }
    $cruise = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cruise) {
        echo json_encode(['success' => false, 'message' => 'Cruise not found', 'deleted' => true]);
        exit;
    }

    $id = (int) $cruise['id'];

    // Fetch itinerary
    $it_stmt = $pdo->prepare("SELECT * FROM cruise_itinerary WHERE cruise_id = ? ORDER BY day_number");
    $it_stmt->execute([$id]);
    $cruise['itinerary'] = $it_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $cruise]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

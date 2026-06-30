<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetchColumn();
    
    echo json_encode(['count' => (int)$count]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>

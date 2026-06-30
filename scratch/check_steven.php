<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=heydream_travel', 'root', '');
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE full_name LIKE ?');
    $stmt->execute(['%Steven Rebancos%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

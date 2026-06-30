<?php
require_once __DIR__ . '/../config/database.php';
echo "Destinations (Local): ";
$stmt = $pdo->query("SELECT DISTINCT category FROM destinations WHERE type = 'local'");
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

echo "Destinations (Foreign): ";
$stmt = $pdo->query("SELECT DISTINCT category FROM destinations WHERE type = 'foreign'");
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
?>

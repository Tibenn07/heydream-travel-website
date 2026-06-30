<?php
require_once __DIR__ . '/../config/database.php';
echo "Flash Deals: ";
$stmt = $pdo->query("SELECT DISTINCT category FROM flash_deals");
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

echo "Foreign: ";
$stmt = $pdo->query("SELECT DISTINCT category FROM foreign_destinations");
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

echo "Local: ";
$stmt = $pdo->query("SELECT DISTINCT category FROM local_destinations");
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
?>

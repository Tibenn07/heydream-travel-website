<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT DISTINCT category FROM flash_deals");
$cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($cats);
?>

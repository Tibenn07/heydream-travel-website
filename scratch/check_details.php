<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT title, category, location FROM flash_deals WHERE is_active = 1");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

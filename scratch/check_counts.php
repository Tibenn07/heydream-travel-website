<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT category, is_active, count(*) as count FROM flash_deals GROUP BY category, is_active");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

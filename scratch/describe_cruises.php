<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("DESCRIBE cruises");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

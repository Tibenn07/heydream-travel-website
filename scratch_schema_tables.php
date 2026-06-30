<?php
require 'config/database.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
?>

<?php
require 'config/database.php';
$stmt = $pdo->query("DESCRIBE foreign_destinations");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("DESCRIBE destinations_enhanced");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
?>

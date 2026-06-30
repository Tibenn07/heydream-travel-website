<?php
require 'config/database.php';
$stmt = $pdo->query("DESCRIBE destinations");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

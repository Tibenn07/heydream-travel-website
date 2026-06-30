<?php
require 'config/database.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "TABLE: $table\n";
    $stmt2 = $pdo->query("DESCRIBE $table");
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
    echo "\n";
}
?>

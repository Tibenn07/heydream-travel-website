<?php
require 'config/database.php';
$tables = ['flash_deals', 'local_packages', 'foreign_packages'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "TABLE: $table\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "\n";
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}
?>

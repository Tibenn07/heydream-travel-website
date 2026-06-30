<?php
require 'config/database.php';
try {
    foreach (['flash_deals', 'foreign_destinations'] as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "COLUMNS IN $table:\n";
        foreach ($columns as $col) {
            echo $col['Field'] . "\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

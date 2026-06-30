<?php
require 'config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE destinations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "COLUMNS IN destinations:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

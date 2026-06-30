<?php
require_once 'config/database.php';
try {
    $stmt = $pdo->query('DESCRIBE flash_deals');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

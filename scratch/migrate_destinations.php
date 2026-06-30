<?php
require 'config/database.php';
try {
    $sql = "ALTER TABLE destinations 
            ADD COLUMN remarks TEXT NULL,
            ADD COLUMN promo_start DATE NULL,
            ADD COLUMN promo_end DATE NULL,
            ADD COLUMN blocked_months TEXT NULL,
            ADD COLUMN highlight_duration INT DEFAULT 1,
            ADD COLUMN blocked_dates TEXT NULL";
    
    $pdo->exec($sql);
    echo "Successfully added missing columns to destinations table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "One or more columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>

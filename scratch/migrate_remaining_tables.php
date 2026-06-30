<?php
require 'config/database.php';
try {
    // Migrate flash_deals
    $sql_flash = "ALTER TABLE flash_deals 
            ADD COLUMN remarks TEXT NULL,
            ADD COLUMN promo_start DATE NULL,
            ADD COLUMN promo_end DATE NULL,
            ADD COLUMN blocked_months TEXT NULL,
            ADD COLUMN highlight_duration INT DEFAULT 1,
            ADD COLUMN blocked_dates TEXT NULL";
    
    try {
        $pdo->exec($sql_flash);
        echo "Successfully added missing columns to flash_deals table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Flash deals columns already exist.\n";
        } else {
            throw $e;
        }
    }

    // Migrate foreign_destinations
    $sql_foreign = "ALTER TABLE foreign_destinations 
            ADD COLUMN promo_start DATE NULL,
            ADD COLUMN promo_end DATE NULL,
            ADD COLUMN blocked_months TEXT NULL,
            ADD COLUMN highlight_duration INT DEFAULT 1,
            ADD COLUMN blocked_dates TEXT NULL,
            ADD COLUMN remarks TEXT NULL"; 
            
    try {
        $pdo->exec($sql_foreign);
        echo "Successfully added missing columns to foreign_destinations table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Foreign destinations columns already exist.\n";
        } else {
            throw $e;
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

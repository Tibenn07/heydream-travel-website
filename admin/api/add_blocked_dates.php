<?php
require 'config/database.php';

$tables = ['flash_deals', 'foreign_destinations', 'destinations'];
foreach ($tables as $table) {
    try {
        $sql = "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `blocked_dates` TEXT DEFAULT NULL";
        $pdo->exec($sql);
        echo "Successfully added blocked_dates to $table\n";
    } catch (PDOException $e) {
        echo "Error altering $table: " . $e->getMessage() . "\n";
    }
}
?>

<?php
require_once __DIR__ . '/../config/database.php';

$tables = ['flash_deals', 'foreign_destinations', 'destinations'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $hasColumn = false;
        while ($row = $stmt->fetch()) {
            if ($row['Field'] === 'blocked_dates') {
                $hasColumn = true;
                break;
            }
        }
        
        if (!$hasColumn) {
            echo "Adding blocked_dates to $table...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN blocked_dates TEXT");
        } else {
            echo "$table already has blocked_dates.\n";
        }
    } catch (Exception $e) {
        echo "Error on table $table: " . $e->getMessage() . "\n";
    }
}
?>

<?php
require_once __DIR__ . '/../config/database.php';

$tables = ['flash_deals', 'foreign_destinations', 'destinations'];
foreach ($tables as $table) {
    echo "Processing $table...\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $existingColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['Field'];
        }

        if (!in_array('promo_start', $existingColumns)) {
            echo "  Adding promo_start...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN promo_start DATE NULL");
        }
        if (!in_array('promo_end', $existingColumns)) {
            echo "  Adding promo_end...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN promo_end DATE NULL");
        }
        if (!in_array('blocked_months', $existingColumns)) {
            echo "  Adding blocked_months...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN blocked_months TEXT NULL");
        }
        if (!in_array('highlight_duration', $existingColumns)) {
            echo "  Adding highlight_duration...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN highlight_duration INT DEFAULT 1");
        }

        echo "  Done with $table.\n";
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
?>

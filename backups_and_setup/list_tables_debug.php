<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config/database.php';

$tables = ['packages', 'destinations', 'foreign_destinations', 'cruises', 'site_services', 'visas', 'flash_deals'];
foreach ($tables as $table) {
    echo "\n=== Columns for: $table ===\n";
    try {
        $cols = $pdo->query("DESCRIBE $table")->fetchAll();
        foreach ($cols as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "  Total Rows: $count\n";
        if ($count > 0) {
            $sample = $pdo->query("SELECT * FROM $table LIMIT 1")->fetch();
            echo "  Sample title/name: " . ($sample['title'] ?? $sample['name'] ?? $sample['destination_name'] ?? $sample['package_name'] ?? json_encode(array_slice($sample, 0, 2))) . "\n";
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}

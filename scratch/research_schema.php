<?php
require_once __DIR__ . '/../config/database.php';
foreach(['flash_deals', 'foreign_destinations', 'destinations'] as $table) {
    echo "--- $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} | {$row['Type']}\n";
    }
}
?>

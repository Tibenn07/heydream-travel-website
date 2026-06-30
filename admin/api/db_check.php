<?php
require_once __DIR__ . '/../../config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM marketing_campaigns");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $cols);

if (!in_array('open_count', $cols)) {
    $pdo->exec("ALTER TABLE marketing_campaigns ADD COLUMN open_count INT DEFAULT 0");
    echo "\nAdded open_count column";
}
?>

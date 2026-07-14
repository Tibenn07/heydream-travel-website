<?php
require 'config/database.php';

echo "=== Foreign Destinations (Last 15) ===\n";
$stmt = $pdo->query('SELECT id, dest_key, name, partner_id, partner_company FROM foreign_destinations ORDER BY id DESC LIMIT 15');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row) {
    $pk = $row['partner_id'] !== null ? $row['partner_id'] : 'NULL';
    $pc = $row['partner_company'] ?? 'NULL';
    $dk = $row['dest_key'] ?? 'NULL';
    echo "ID: {$row['id']}, dest_key: {$dk}, name: {$row['name']}, partner_id: {$pk}, partner_company: {$pc}\n";
}
?>

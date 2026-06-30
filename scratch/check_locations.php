<?php
require_once 'config/database.php';
echo "LOCATIONS:\n";
$stmt = $pdo->query("SELECT DISTINCT location FROM flash_deals WHERE is_active = 1");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
echo "\nCATEGORIES:\n";
$stmt = $pdo->query("SELECT DISTINCT category FROM flash_deals WHERE is_active = 1");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

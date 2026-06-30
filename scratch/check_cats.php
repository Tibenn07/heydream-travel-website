<?php
require_once 'config/database.php';
$cats = $pdo->query('SELECT DISTINCT category FROM flash_deals')->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($cats);

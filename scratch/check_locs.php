<?php
require_once 'config/database.php';
$locs = $pdo->query('SELECT DISTINCT location FROM flash_deals')->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($locs);

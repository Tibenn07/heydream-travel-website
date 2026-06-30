<?php
require_once dirname(__DIR__) . '/config/database.php';
$stmt = $pdo->prepare("SELECT id, title, location, is_active FROM flash_deals");
$stmt->execute();
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($deals, JSON_PRETTY_PRINT);

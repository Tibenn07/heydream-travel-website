<?php
require_once __DIR__ . '/config/database.php';
$stmt = $pdo->query('SHOW COLUMNS FROM bookings');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

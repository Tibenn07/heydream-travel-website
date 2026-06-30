<?php
require 'config/database.php';
$stmt = $pdo->query('SELECT id, travel_date, reminder_sent FROM bookings WHERE id IN (20, 22)');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

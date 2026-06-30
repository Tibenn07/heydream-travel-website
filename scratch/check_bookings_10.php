<?php
require 'config/database.php';
$stmt = $pdo->prepare("SELECT id, email, travel_date, booking_status, reminder_sent FROM bookings ORDER BY id DESC LIMIT 10");
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($bookings);

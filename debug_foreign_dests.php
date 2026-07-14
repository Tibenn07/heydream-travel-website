<?php
require 'config/database.php';

echo "=== Foreign Destinations (Last 10) ===\n";
$stmt = $pdo->query('SELECT id, name, partner_id, partner_company FROM foreign_destinations ORDER BY id DESC LIMIT 10');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pid = $row['partner_id'] !== null ? $row['partner_id'] : 'NULL';
    $pco = $row['partner_company'] ?? 'NULL';
    echo "ID: {$row['id']}, Name: {$row['name']}, Partner ID: {$pid}, Partner Company: {$pco}\n";
}

echo "\n=== Bookings for Foreign Destinations (Last 10) ===\n";
$bookings = $pdo->query('SELECT id, booking_number, destination_name, partner_id, partner_package_id, partner_source FROM bookings WHERE booking_number LIKE "FOR-%" OR booking_number LIKE "FO-%" ORDER BY id DESC LIMIT 10');
foreach ($bookings->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pid = $row['partner_id'] !== null ? $row['partner_id'] : 'NULL';
    $ppid = $row['partner_package_id'] !== null ? $row['partner_package_id'] : 'NULL';
    $ps = $row['partner_source'] ?? 'NULL';
    echo "ID: {$row['id']}, Booking: {$row['booking_number']}, Dest: {$row['destination_name']}, Partner ID: {$pid}, Partner Package ID: {$ppid}, Partner Source: {$ps}\n";
}
?>

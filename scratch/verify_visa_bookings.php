<?php
require_once __DIR__ . '/config/database.php';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE destination_name = 'Visa Assistance'");
$stmt->execute();
echo "Visa Assistance bookings count: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE destination_name = 'Visa Assistance' LIMIT 1");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>

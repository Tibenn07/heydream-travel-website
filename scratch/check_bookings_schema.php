<?php
require 'config/database.php';
echo "--- BOOKINGS TABLE ---\n";
$stmt = $pdo->query("DESCRIBE bookings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

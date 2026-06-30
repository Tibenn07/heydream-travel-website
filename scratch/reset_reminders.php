<?php
require 'config/database.php';
$pdo->exec("UPDATE bookings SET reminder_sent = 0 WHERE travel_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
echo "Reset done";

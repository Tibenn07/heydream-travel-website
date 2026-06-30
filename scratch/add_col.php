<?php
require 'config/database.php';
try {
    $pdo->exec('ALTER TABLE bookings ADD COLUMN reminder_sent TINYINT(1) DEFAULT 0');
    echo 'Column added successfully';
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}

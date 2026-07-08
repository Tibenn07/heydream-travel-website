<?php
$_SERVER['HTTP_HOST']='localhost';
require 'c:\xampp\htdocs\HeyDream Website 13.0\config\database.php';
$stmt=$pdo->query("SHOW CREATE TABLE bookings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

<?php
require 'c:/xampp/htdocs/HeyDream Website - anti gravity 12.3-20260525T035054Z-3-001/HeyDream Website - anti gravity 12.3/config/database.php';
echo "<h2>Admin Users</h2>";
$stmt = $pdo->query('SELECT id, username, email, is_active, approved FROM admin_users');
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";

echo "<h2>Registration Requests</h2>";
$stmt = $pdo->query('SELECT id, username, email, status FROM admin_registration_requests');
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";

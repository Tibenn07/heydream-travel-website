<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Database Test</h2>";

try {
    $stmt = $pdo->query("SELECT * FROM destinations");
    $destinations = $stmt->fetchAll();
    
    echo "<h3>Destinations found:</h3>";
    foreach ($destinations as $dest) {
        echo "- " . $dest['name'] . "<br>";
    }
    
    echo "<h3>Bookings table check:</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Bookings table exists<br>";
    } else {
        echo "❌ Bookings table does NOT exist<br>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

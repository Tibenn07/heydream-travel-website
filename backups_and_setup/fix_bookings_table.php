<?php
// File: fix_bookings_table.php
// Run this script to fix the bookings table structure

require_once __DIR__ . '/config/database.php';

echo "<h2>Fixing Bookings Table Structure</h2>";

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
    if ($stmt->rowCount() == 0) {
        // Create bookings table
        $sql = "CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            booking_number VARCHAR(50) NOT NULL UNIQUE,
            destination_name VARCHAR(100),
            package_name VARCHAR(100),
            package_duration VARCHAR(50),
            price_per_person DECIMAL(10,2),
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            travel_date DATE NOT NULL,
            number_of_travelers INT NOT NULL DEFAULT 1,
            special_requests TEXT,
            total_amount DECIMAL(12,2) NOT NULL,
            booking_status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
            payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
            payment_method VARCHAR(50),
            payment_reference VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_booking_number (booking_number),
            INDEX idx_email (email),
            INDEX idx_user_id (user_id)
        )";
        $pdo->exec($sql);
        echo "✅ Bookings table created!<br>";
    } else {
        echo "✅ Bookings table exists.<br>";
        
        // Add missing columns if any
        $stmt = $pdo->query("SHOW COLUMNS FROM bookings");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $columns_to_add = [
            'special_requests' => "ALTER TABLE bookings ADD COLUMN special_requests TEXT AFTER number_of_travelers",
            'package_duration' => "ALTER TABLE bookings ADD COLUMN package_duration VARCHAR(50) AFTER package_name",
            'price_per_person' => "ALTER TABLE bookings ADD COLUMN price_per_person DECIMAL(10,2) AFTER package_duration",
            'payment_reference' => "ALTER TABLE bookings ADD COLUMN payment_reference VARCHAR(100) AFTER payment_method"
        ];
        
        foreach ($columns_to_add as $col_name => $sql) {
            if (!in_array($col_name, $columns)) {
                $pdo->exec($sql);
                echo "✅ Added column: $col_name<br>";
            }
        }
    }
    
    // Show table structure
    echo "<h3>Current Bookings Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count existing bookings
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
    $count = $stmt->fetchColumn();
    echo "<p><strong>Total bookings: $count</strong></p>";
    
    echo "<br><a href='local-destination.php'>Go to Local Destinations</a> | ";
    echo "<a href='foreign-destinations.php'>Go to Foreign Destinations</a> | ";
    echo "<a href='flash-deals.php'>Go to Flash Deals</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

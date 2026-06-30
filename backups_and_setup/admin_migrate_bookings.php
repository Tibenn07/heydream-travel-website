<?php
// File: migrate_bookings.php
// Run this script once to ensure bookings table has all required columns

require_once __DIR__ . '/config/database.php';

echo "<h2>Migrating Bookings Table</h2>";

try {
    // Check if table exists, if not create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bookings (
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
        )
    ");
    echo "✅ Bookings table created/verified!<br>";
    
    // Add missing columns if any
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $columns_to_add = [
        'payment_reference' => "ALTER TABLE bookings ADD COLUMN payment_reference VARCHAR(100) AFTER payment_method",
        'special_requests' => "ALTER TABLE bookings ADD COLUMN special_requests TEXT AFTER number_of_travelers",
        'package_duration' => "ALTER TABLE bookings ADD COLUMN package_duration VARCHAR(50) AFTER package_name",
        'price_per_person' => "ALTER TABLE bookings ADD COLUMN price_per_person DECIMAL(10,2) AFTER package_duration"
    ];
    
    foreach ($columns_to_add as $col_name => $sql) {
        if (!in_array($col_name, $columns)) {
            $pdo->exec($sql);
            echo "✅ Added column: $col_name<br>";
        }
    }
    
    echo "<br><strong style='color: green;'>Migration complete! Bookings table is ready.</strong>";
    echo "<br><br><a href='HeyDream.php'>Back to Homepage</a>";
    
} catch (PDOException $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?>

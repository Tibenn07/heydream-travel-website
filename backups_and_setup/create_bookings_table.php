<?php
// File: create_bookings_table.php
// Run this to create the bookings table

require_once __DIR__ . '/config/database.php';

echo "<h2>Creating Bookings Table</h2>";

try {
    $sql = "
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
    )";
    
    $pdo->exec($sql);
    echo "✅ Bookings table created successfully!<br>";
    
    // Show table structure
    $stmt = $pdo->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll();
    echo "<h3>Table Structure:</h3>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li><strong>{$col['Field']}</strong> - {$col['Type']}</li>";
    }
    echo "</ul>";
    
    echo "<br><a href='debug_booking.php'>Go back to debug</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

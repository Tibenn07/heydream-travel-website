<?php
require_once __DIR__ . '/config/database.php';

try {
    // Create bookings table
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_booking_number (booking_number),
            INDEX idx_email (email),
            INDEX idx_user_id (user_id)
        )
    ");
    echo "✅ 'bookings' table created successfully!<br>";

    // Create destinations table if it doesn't exist (also required by the dashboard)
    try {
        $pdo->query("SELECT 1 FROM destinations LIMIT 1");
        echo "✅ 'destinations' table already exists!<br>";
    } catch (PDOException $e) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS destinations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(150) NOT NULL,
                description TEXT,
                slug VARCHAR(150),
                image_url VARCHAR(255),
                price DECIMAL(10,2),
                location VARCHAR(150),
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✅ 'destinations' table created successfully!<br>";
    }

    echo "<br><strong>All missing tables have been created! You can now access your dashboard.</strong>";
    echo "<br><br><a href='admin/dashboard.php'>Go to Dashboard</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

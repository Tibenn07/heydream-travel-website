<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cruises (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cruise_code VARCHAR(50) UNIQUE,
            title VARCHAR(200) NOT NULL,
            short_description TEXT,
            full_description TEXT,
            duration VARCHAR(100),
            featured_image VARCHAR(500),
            gallery TEXT,
            departure_port VARCHAR(200),
            destinations TEXT,
            route TEXT,
            ship_name VARCHAR(200),
            cruise_line VARCHAR(200),
            room_types TEXT,
            amenities TEXT,
            ship_description TEXT,
            base_price DECIMAL(15,2) DEFAULT 0,
            price_per_person DECIMAL(15,2) DEFAULT 0,
            promo_price DECIMAL(15,2) DEFAULT 0,
            inclusions TEXT,
            exclusions TEXT,
            departure_date DATE,
            return_date DATE,
            booking_deadline DATE,
            available_slots INT DEFAULT 0,
            status ENUM('Available', 'Full', 'Cancelled') DEFAULT 'Available',
            required_documents TEXT,
            travel_requirements TEXT,
            health_requirements TEXT,
            cancellation_policy TEXT,
            refund_policy TEXT,
            terms_conditions TEXT,
            category VARCHAR(100),
            destination_type VARCHAR(100),
            tags TEXT,
            highlights TEXT,
            promo_text TEXT,
            rating DECIMAL(2,1) DEFAULT 0,
            feedback_count INT DEFAULT 0,
            is_published TINYINT DEFAULT 1,
            is_featured TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cruise_itinerary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cruise_id INT NOT NULL,
            day_number INT NOT NULL,
            title VARCHAR(200),
            description TEXT,
            FOREIGN KEY (cruise_id) REFERENCES cruises(id) ON DELETE CASCADE
        )
    ");

    echo "Tables created successfully!";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}

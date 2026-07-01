<?php

// Force localhost configuration for script execution
$_SERVER['HTTP_HOST'] = 'localhost';

require_once __DIR__ . '/../config/database.php';

try {
    // Table: vouchers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vouchers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_name VARCHAR(255) NOT NULL,
            voucher_code VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            minimum_spend DECIMAL(12,2) DEFAULT 0.00,
            maximum_discount DECIMAL(12,2) NULL,
            max_discounted_travelers INT DEFAULT 0,
            max_total_redemptions INT DEFAULT 0,
            max_redemptions_per_user INT DEFAULT 1,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            priority INT DEFAULT 0,
            display_order INT DEFAULT 0,
            banner_image_url VARCHAR(255) NULL,
            color_theme VARCHAR(50) NULL,
            audience ENUM('everyone', 'logged_in_only', 'first_time_customers', 'returning_customers', 'selected_users', 'vip_members') DEFAULT 'everyone',
            collection_method ENUM('auto_available', 'user_collect', 'admin_assign', 'welcome_voucher', 'birthday_voucher') DEFAULT 'auto_available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 'vouchers' table created successfully!<br>";

    // Table: voucher_targets (to link vouchers to website sections)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_targets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_id INT NOT NULL,
            target_type ENUM('flash_deals', 'local_destinations', 'foreign_destinations', 'flights', 'flight_packages', 'cruises', 'experiences', 'premium_services', 'visa_services') NOT NULL,
            FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
            UNIQUE(voucher_id, target_type)
        )
    ");
    echo "✅ 'voucher_targets' table created successfully!<br>";

    // Table: voucher_packages (to link vouchers to specific packages within sections)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_id INT NOT NULL,
            target_type ENUM('flash_deals', 'local_destinations', 'foreign_destinations', 'flights', 'flight_packages', 'cruises', 'experiences', 'premium_services', 'visa_services') NOT NULL,
            package_id INT NOT NULL,
            FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
            UNIQUE(voucher_id, target_type, package_id)
        )
    ");
    echo "✅ 'voucher_packages' table created successfully!<br>";

    // Table: user_vouchers (to track which users have collected/been assigned which vouchers)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_vouchers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            voucher_id INT NOT NULL,
            is_collected BOOLEAN DEFAULT TRUE,
            is_used BOOLEAN DEFAULT FALSE,
            collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- Assuming a 'users' table exists
            FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
            UNIQUE(user_id, voucher_id)
        )
    ");
    echo "✅ 'user_vouchers' table created successfully!<br>";

    // Ensure 'bookings' table exists with a proper primary key before creating foreign key
    // This is duplicated from setup-tables.php to ensure consistency for this script
    try {
        $pdo->query("SELECT 1 FROM bookings LIMIT 1");
        echo "✅ 'bookings' table already exists!<br>";
    } catch (PDOException $e) {
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
    }

    // Table: voucher_redemptions (to track individual redemptions for usage limits)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_redemptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_id INT NOT NULL,
            user_id INT NULL, -- NULL if audience is 'everyone' and not logged in
            booking_id INT NOT NULL, -- Link to the booking where the voucher was used
            redemption_amount DECIMAL(12,2) NOT NULL,
            redemption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo "✅ 'voucher_redemptions' table created successfully!<br>";

    echo "<br><strong>All voucher-related tables have been created or already exist!</strong><br>";

} catch (PDOException $e) {
    echo "Error creating voucher tables: " . $e->getMessage();
}
?>
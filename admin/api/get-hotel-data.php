<?php
// File: admin/api/get-hotel-data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

// Check if table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM hotel_booking_settings LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it with default data
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hotel_booking_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            destination_key VARCHAR(50) NOT NULL UNIQUE,
            destination_name VARCHAR(100) NOT NULL,
            month_january_low INT DEFAULT 0,
            month_january_high INT DEFAULT 0,
            month_january_hotel VARCHAR(100),
            month_february_low INT DEFAULT 0,
            month_february_high INT DEFAULT 0,
            month_february_hotel VARCHAR(100),
            month_march_low INT DEFAULT 0,
            month_march_high INT DEFAULT 0,
            month_march_hotel VARCHAR(100),
            month_april_low INT DEFAULT 0,
            month_april_high INT DEFAULT 0,
            month_april_hotel VARCHAR(100),
            month_may_low INT DEFAULT 0,
            month_may_high INT DEFAULT 0,
            month_may_hotel VARCHAR(100),
            month_june_low INT DEFAULT 0,
            month_june_high INT DEFAULT 0,
            month_june_hotel VARCHAR(100),
            month_july_low INT DEFAULT 0,
            month_july_high INT DEFAULT 0,
            month_july_hotel VARCHAR(100),
            month_august_low INT DEFAULT 0,
            month_august_high INT DEFAULT 0,
            month_august_hotel VARCHAR(100),
            month_september_low INT DEFAULT 0,
            month_september_high INT DEFAULT 0,
            month_september_hotel VARCHAR(100),
            month_october_low INT DEFAULT 0,
            month_october_high INT DEFAULT 0,
            month_october_hotel VARCHAR(100),
            month_november_low INT DEFAULT 0,
            month_november_high INT DEFAULT 0,
            month_november_hotel VARCHAR(100),
            month_december_low INT DEFAULT 0,
            month_december_high INT DEFAULT 0,
            month_december_hotel VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default data for Baguio
    $pdo->exec("
        INSERT INTO hotel_booking_settings (destination_key, destination_name,
            month_january_low, month_january_high, month_january_hotel,
            month_february_low, month_february_high, month_february_hotel,
            month_march_low, month_march_high, month_march_hotel,
            month_april_low, month_april_high, month_april_hotel,
            month_may_low, month_may_high, month_may_hotel,
            month_june_low, month_june_high, month_june_hotel,
            month_july_low, month_july_high, month_july_hotel,
            month_august_low, month_august_high, month_august_hotel,
            month_september_low, month_september_high, month_september_hotel,
            month_october_low, month_october_high, month_october_hotel,
            month_november_low, month_november_high, month_november_hotel,
            month_december_low, month_december_high, month_december_hotel)
        VALUES ('baguio', 'Baguio',
            3800, 8500, 'Peak Season',
            3000, 6800, 'Flower Festival',
            2500, 5800, '3-star, 4-star',
            3200, 7500, '4-star, Resort',
            2800, 6200, '3-star, Boutique',
            2200, 4800, 'Budget, 3-star',
            2000, 4500, 'Budget Friendly',
            2100, 4700, 'Budget, 3-star',
            2300, 5000, '3-star',
            2600, 5500, '3-star, 4-star',
            2800, 6000, '4-star',
            4500, 12000, 'Peak Season')
    ");
}

// Fetch all hotel data
$stmt = $pdo->prepare("SELECT * FROM hotel_booking_settings WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$results = $stmt->fetchAll();

$hotelData = [];
foreach ($results as $row) {
    $destinationKey = $row['destination_key'];
    $hotelData[$destinationKey] = [
        'name' => $row['destination_name'],
        'month_january' => [
            'low' => (int)$row['month_january_low'],
            'high' => (int)$row['month_january_high'],
            'hotel' => $row['month_january_hotel'] ?? ''
        ],
        'month_february' => [
            'low' => (int)$row['month_february_low'],
            'high' => (int)$row['month_february_high'],
            'hotel' => $row['month_february_hotel'] ?? ''
        ],
        'month_march' => [
            'low' => (int)$row['month_march_low'],
            'high' => (int)$row['month_march_high'],
            'hotel' => $row['month_march_hotel'] ?? ''
        ],
        'month_april' => [
            'low' => (int)$row['month_april_low'],
            'high' => (int)$row['month_april_high'],
            'hotel' => $row['month_april_hotel'] ?? ''
        ],
        'month_may' => [
            'low' => (int)$row['month_may_low'],
            'high' => (int)$row['month_may_high'],
            'hotel' => $row['month_may_hotel'] ?? ''
        ],
        'month_june' => [
            'low' => (int)$row['month_june_low'],
            'high' => (int)$row['month_june_high'],
            'hotel' => $row['month_june_hotel'] ?? ''
        ],
        'month_july' => [
            'low' => (int)$row['month_july_low'],
            'high' => (int)$row['month_july_high'],
            'hotel' => $row['month_july_hotel'] ?? ''
        ],
        'month_august' => [
            'low' => (int)$row['month_august_low'],
            'high' => (int)$row['month_august_high'],
            'hotel' => $row['month_august_hotel'] ?? ''
        ],
        'month_september' => [
            'low' => (int)$row['month_september_low'],
            'high' => (int)$row['month_september_high'],
            'hotel' => $row['month_september_hotel'] ?? ''
        ],
        'month_october' => [
            'low' => (int)$row['month_october_low'],
            'high' => (int)$row['month_october_high'],
            'hotel' => $row['month_october_hotel'] ?? ''
        ],
        'month_november' => [
            'low' => (int)$row['month_november_low'],
            'high' => (int)$row['month_november_high'],
            'hotel' => $row['month_november_hotel'] ?? ''
        ],
        'month_december' => [
            'low' => (int)$row['month_december_low'],
            'high' => (int)$row['month_december_high'],
            'hotel' => $row['month_december_hotel'] ?? ''
        ]
    ];
}

echo json_encode(['success' => true, 'data' => $hotelData]);
?>

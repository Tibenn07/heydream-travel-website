<?php
// File: admin/api/get-flight-data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

// Check if table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM flight_booking_settings LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it with default data
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS flight_booking_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            destination_key VARCHAR(50) NOT NULL UNIQUE,
            destination_name VARCHAR(100) NOT NULL,
            month_january_low INT DEFAULT 0,
            month_january_high INT DEFAULT 0,
            month_january_airline VARCHAR(100),
            month_february_low INT DEFAULT 0,
            month_february_high INT DEFAULT 0,
            month_february_airline VARCHAR(100),
            month_march_low INT DEFAULT 0,
            month_march_high INT DEFAULT 0,
            month_march_airline VARCHAR(100),
            month_april_low INT DEFAULT 0,
            month_april_high INT DEFAULT 0,
            month_april_airline VARCHAR(100),
            month_may_low INT DEFAULT 0,
            month_may_high INT DEFAULT 0,
            month_may_airline VARCHAR(100),
            month_june_low INT DEFAULT 0,
            month_june_high INT DEFAULT 0,
            month_june_airline VARCHAR(100),
            month_july_low INT DEFAULT 0,
            month_july_high INT DEFAULT 0,
            month_july_airline VARCHAR(100),
            month_august_low INT DEFAULT 0,
            month_august_high INT DEFAULT 0,
            month_august_airline VARCHAR(100),
            month_september_low INT DEFAULT 0,
            month_september_high INT DEFAULT 0,
            month_september_airline VARCHAR(100),
            month_october_low INT DEFAULT 0,
            month_october_high INT DEFAULT 0,
            month_october_airline VARCHAR(100),
            month_november_low INT DEFAULT 0,
            month_november_high INT DEFAULT 0,
            month_november_airline VARCHAR(100),
            month_december_low INT DEFAULT 0,
            month_december_high INT DEFAULT 0,
            month_december_airline VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default data for Baguio
    $pdo->exec("
        INSERT INTO flight_booking_settings (destination_key, destination_name,
            month_january_low, month_january_high, month_january_airline,
            month_february_low, month_february_high, month_february_airline,
            month_march_low, month_march_high, month_march_airline,
            month_april_low, month_april_high, month_april_airline,
            month_may_low, month_may_high, month_may_airline,
            month_june_low, month_june_high, month_june_airline,
            month_july_low, month_july_high, month_july_airline,
            month_august_low, month_august_high, month_august_airline,
            month_september_low, month_september_high, month_september_airline,
            month_october_low, month_october_high, month_october_airline,
            month_november_low, month_november_high, month_november_airline,
            month_december_low, month_december_high, month_december_airline)
        VALUES ('baguio', 'Baguio',
            5120, 11450, 'PAL, Cebu Pac',
            4890, 10230, 'AirAsia',
            4220, 8980, 'PAL, Cebu Pac',
            5460, 12500, 'PAL',
            4820, 10610, 'Cebu Pac, AirAsia',
            6535, 14970, 'PAL, JAL',
            8680, 18455, 'Peak Season',
            7685, 16055, 'Multiple',
            5555, 11730, 'Cebu Pac',
            4990, 10450, 'AirAsia, PAL',
            4780, 9890, 'Cebu Pac',
            9250, 22800, 'Holiday Peak')
    ");
    
    // Insert default data for Cebu
    $pdo->exec("
        INSERT INTO flight_booking_settings (destination_key, destination_name,
            month_january_low, month_january_high, month_january_airline,
            month_february_low, month_february_high, month_february_airline,
            month_march_low, month_march_high, month_march_airline,
            month_april_low, month_april_high, month_april_airline,
            month_may_low, month_may_high, month_may_airline,
            month_june_low, month_june_high, month_june_airline,
            month_july_low, month_july_high, month_july_airline,
            month_august_low, month_august_high, month_august_airline,
            month_september_low, month_september_high, month_september_airline,
            month_october_low, month_october_high, month_october_airline,
            month_november_low, month_november_high, month_november_airline,
            month_december_low, month_december_high, month_december_airline)
        VALUES ('cebu', 'Cebu City',
            4120, 9450, 'PAL',
            3890, 8230, 'Cebu Pac',
            3220, 6980, 'Cebu Pac',
            4460, 9500, 'PAL',
            3820, 8610, 'AirAsia',
            4535, 9970, 'Cebu Pac',
            5680, 12455, 'Peak',
            5285, 11055, 'Multiple',
            4155, 8730, 'PAL',
            3990, 8450, 'Cebu Pac',
            3780, 7890, 'AirAsia',
            7250, 16800, 'Holiday')
    ");
    
    // Insert default data for Manila
    $pdo->exec("
        INSERT INTO flight_booking_settings (destination_key, destination_name,
            month_january_low, month_january_high, month_january_airline,
            month_february_low, month_february_high, month_february_airline,
            month_march_low, month_march_high, month_march_airline,
            month_april_low, month_april_high, month_april_airline,
            month_may_low, month_may_high, month_may_airline,
            month_june_low, month_june_high, month_june_airline,
            month_july_low, month_july_high, month_july_airline,
            month_august_low, month_august_high, month_august_airline,
            month_september_low, month_september_high, month_september_airline,
            month_october_low, month_october_high, month_october_airline,
            month_november_low, month_november_high, month_november_airline,
            month_december_low, month_december_high, month_december_airline)
        VALUES ('manila', 'Manila',
            4120, 9450, 'PAL',
            3890, 8230, 'Cebu Pac',
            3220, 6980, 'Cebu Pac',
            4460, 9500, 'PAL',
            3820, 8610, 'AirAsia',
            4535, 9970, 'Cebu Pac',
            5680, 12455, 'Peak',
            5285, 11055, 'Multiple',
            4155, 8730, 'PAL',
            3990, 8450, 'Cebu Pac',
            3780, 7890, 'AirAsia',
            7250, 16800, 'Holiday')
    ");
    
    // Insert default data for Singapore
    $pdo->exec("
        INSERT INTO flight_booking_settings (destination_key, destination_name,
            month_january_low, month_january_high, month_january_airline,
            month_february_low, month_february_high, month_february_airline,
            month_march_low, month_march_high, month_march_airline,
            month_april_low, month_april_high, month_april_airline,
            month_may_low, month_may_high, month_may_airline,
            month_june_low, month_june_high, month_june_airline,
            month_july_low, month_july_high, month_july_airline,
            month_august_low, month_august_high, month_august_airline,
            month_september_low, month_september_high, month_september_airline,
            month_october_low, month_october_high, month_october_airline,
            month_november_low, month_november_high, month_november_airline,
            month_december_low, month_december_high, month_december_airline)
        VALUES ('singapore', 'Singapore',
            8120, 17450, 'Singapore Air',
            7590, 16230, 'PAL',
            7240, 14480, 'Singapore Air',
            8460, 17700, 'Singapore Air',
            7920, 16610, 'PAL',
            9535, 18970, 'Cebu Pac',
            11880, 23455, 'Peak',
            10985, 22055, 'Multiple',
            8555, 17730, 'Singapore Air',
            7790, 16450, 'PAL',
            7280, 14890, 'Cebu Pac',
            13250, 27800, 'Holiday')
    ");
    
    // Insert default data for Tokyo
    $pdo->exec("
        INSERT INTO flight_booking_settings (destination_key, destination_name,
            month_january_low, month_january_high, month_january_airline,
            month_february_low, month_february_high, month_february_airline,
            month_march_low, month_march_high, month_march_airline,
            month_april_low, month_april_high, month_april_airline,
            month_may_low, month_may_high, month_may_airline,
            month_june_low, month_june_high, month_june_airline,
            month_july_low, month_july_high, month_july_airline,
            month_august_low, month_august_high, month_august_airline,
            month_september_low, month_september_high, month_september_airline,
            month_october_low, month_october_high, month_october_airline,
            month_november_low, month_november_high, month_november_airline,
            month_december_low, month_december_high, month_december_airline)
        VALUES ('tokyo', 'Tokyo',
            10120, 21450, 'JAL',
            9590, 20230, 'ANA',
            9240, 18480, 'JAL, ANA',
            11460, 22700, 'JAL',
            9920, 19610, 'ANA',
            11535, 22970, 'PAL',
            14880, 28455, 'Peak',
            13985, 27055, 'Multiple',
            10555, 21730, 'JAL',
            9790, 20450, 'ANA',
            9280, 18890, 'PAL',
            16250, 32800, 'Holiday')
    ");
    
    // Insert default data for Bangkok
    $pdo->exec("
        INSERT INTO flight_booking_settings (destination_key, destination_name,
            month_january_low, month_january_high, month_january_airline,
            month_february_low, month_february_high, month_february_airline,
            month_march_low, month_march_high, month_march_airline,
            month_april_low, month_april_high, month_april_airline,
            month_may_low, month_may_high, month_may_airline,
            month_june_low, month_june_high, month_june_airline,
            month_july_low, month_july_high, month_july_airline,
            month_august_low, month_august_high, month_august_airline,
            month_september_low, month_september_high, month_september_airline,
            month_october_low, month_october_high, month_october_airline,
            month_november_low, month_november_high, month_november_airline,
            month_december_low, month_december_high, month_december_airline)
        VALUES ('bangkok', 'Bangkok',
            7120, 15450, 'Thai Air',
            6590, 14230, 'PAL',
            6240, 12480, 'Thai Air',
            7460, 15700, 'Thai Air',
            6920, 14610, 'Cebu Pac',
            8535, 16970, 'PAL',
            10880, 21455, 'Peak',
            9985, 20055, 'Multiple',
            7555, 15730, 'Thai Air',
            6790, 14450, 'PAL',
            6280, 12890, 'Cebu Pac',
            12250, 25800, 'Holiday')
    ");
}

// Fetch all flight data
$stmt = $pdo->prepare("SELECT * FROM flight_booking_settings WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$results = $stmt->fetchAll();

$flightData = [];
foreach ($results as $row) {
    $destinationKey = $row['destination_key'];
    $flightData[$destinationKey] = [
        'name' => $row['destination_name'],
        'month_january' => [
            'low' => (int)$row['month_january_low'],
            'high' => (int)$row['month_january_high'],
            'airline' => $row['month_january_airline'] ?? ''
        ],
        'month_february' => [
            'low' => (int)$row['month_february_low'],
            'high' => (int)$row['month_february_high'],
            'airline' => $row['month_february_airline'] ?? ''
        ],
        'month_march' => [
            'low' => (int)$row['month_march_low'],
            'high' => (int)$row['month_march_high'],
            'airline' => $row['month_march_airline'] ?? ''
        ],
        'month_april' => [
            'low' => (int)$row['month_april_low'],
            'high' => (int)$row['month_april_high'],
            'airline' => $row['month_april_airline'] ?? ''
        ],
        'month_may' => [
            'low' => (int)$row['month_may_low'],
            'high' => (int)$row['month_may_high'],
            'airline' => $row['month_may_airline'] ?? ''
        ],
        'month_june' => [
            'low' => (int)$row['month_june_low'],
            'high' => (int)$row['month_june_high'],
            'airline' => $row['month_june_airline'] ?? ''
        ],
        'month_july' => [
            'low' => (int)$row['month_july_low'],
            'high' => (int)$row['month_july_high'],
            'airline' => $row['month_july_airline'] ?? ''
        ],
        'month_august' => [
            'low' => (int)$row['month_august_low'],
            'high' => (int)$row['month_august_high'],
            'airline' => $row['month_august_airline'] ?? ''
        ],
        'month_september' => [
            'low' => (int)$row['month_september_low'],
            'high' => (int)$row['month_september_high'],
            'airline' => $row['month_september_airline'] ?? ''
        ],
        'month_october' => [
            'low' => (int)$row['month_october_low'],
            'high' => (int)$row['month_october_high'],
            'airline' => $row['month_october_airline'] ?? ''
        ],
        'month_november' => [
            'low' => (int)$row['month_november_low'],
            'high' => (int)$row['month_november_high'],
            'airline' => $row['month_november_airline'] ?? ''
        ],
        'month_december' => [
            'low' => (int)$row['month_december_low'],
            'high' => (int)$row['month_december_high'],
            'airline' => $row['month_december_airline'] ?? ''
        ]
    ];
}

echo json_encode(['success' => true, 'data' => $flightData]);
?>

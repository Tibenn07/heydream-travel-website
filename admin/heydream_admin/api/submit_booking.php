<?php
// api/submit_booking.php - Save booking submissions from the mobile app
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function returnError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    require_once '../db_config.php';
} catch (Exception $e) {
    returnError('Database config error: ' . $e->getMessage());
}

if (!file_exists('../db_config.php')) {
    returnError('Database configuration file not found');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnError('Invalid request method. Use POST.');
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    returnError('Database connection failed: ' . $e->getMessage());
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}

$bookingId = isset($data['bookingId']) ? trim($data['bookingId']) : '';
$title = isset($data['title']) ? trim($data['title']) : '';
$destination = isset($data['destination']) ? trim($data['destination']) : '';
$dates = isset($data['dates']) ? trim($data['dates']) : '';
$nights = isset($data['nights']) ? (int) $data['nights'] : 0;
$travelers = isset($data['travelers']) ? (int) $data['travelers'] : 1;
$status = isset($data['status']) ? trim($data['status']) : 'Confirmed';
$price = isset($data['price']) ? trim($data['price']) : '';
$leadName = isset($data['travelerLead']['name']) ? trim($data['travelerLead']['name']) : '';
$leadEmail = isset($data['travelerLead']['email']) ? trim($data['travelerLead']['email']) : '';
$leadPhone = isset($data['travelerLead']['phone']) ? trim($data['travelerLead']['phone']) : '';
$paymentMethod = isset($data['paymentMethod']) ? trim($data['paymentMethod']) : '';
$specialRequests = isset($data['specialRequests']) ? trim($data['specialRequests']) : '';
$packageType = isset($data['packageType']) ? trim($data['packageType']) : '';
$selectedTier = isset($data['selectedTier']) ? trim($data['selectedTier']) : '';
$submittedAt = isset($data['submittedAt']) ? trim($data['submittedAt']) : date('Y-m-d H:i:s');

if (empty($bookingId) || empty($leadName) || empty($leadEmail)) {
    returnError('Booking details are incomplete.');
}

if ($conn->query("SHOW TABLES LIKE 'bookings'")->num_rows === 0) {
    $createTable = "
        CREATE TABLE bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id VARCHAR(100) NOT NULL UNIQUE,
            package_title VARCHAR(255) NOT NULL,
            destination VARCHAR(255) DEFAULT NULL,
            travel_dates VARCHAR(255) DEFAULT NULL,
            nights INT DEFAULT 0,
            travelers INT DEFAULT 1,
            status VARCHAR(50) DEFAULT 'Confirmed',
            price VARCHAR(100) DEFAULT NULL,
            lead_name VARCHAR(255) DEFAULT NULL,
            lead_email VARCHAR(255) DEFAULT NULL,
            lead_phone VARCHAR(100) DEFAULT NULL,
            payment_method VARCHAR(100) DEFAULT NULL,
            special_requests TEXT DEFAULT NULL,
            package_type VARCHAR(255) DEFAULT NULL,
            selected_tier VARCHAR(255) DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking_status (status),
            INDEX idx_submitted_at (submitted_at)
        )
    ";
    if (!$conn->query($createTable)) {
        returnError('Failed to create bookings table: ' . $conn->error);
    }
}

$stmt = $conn->prepare(
    "INSERT INTO bookings (
        booking_id, package_title, destination, travel_dates, nights, travelers, status, price,
        lead_name, lead_email, lead_phone, payment_method, special_requests, package_type,
        selected_tier, submitted_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    returnError('Database prepare error: ' . $conn->error);
}

$stmt->bind_param(
    "ssssiissssssssss",
    $bookingId,
    $title,
    $destination,
    $dates,
    $nights,
    $travelers,
    $status,
    $price,
    $leadName,
    $leadEmail,
    $leadPhone,
    $paymentMethod,
    $specialRequests,
    $packageType,
    $selectedTier,
    $submittedAt
);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    returnError('Database error: ' . $stmt->error);
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Booking submitted to admin successfully.',
    'data' => ['booking_id' => $bookingId]
]);

<?php
// api/get_bookings.php - Fetch bookings for mobile app
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once '../db_config.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database config error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get filters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

$whereClauses = [];
$params = [];
$types = "";

if ($status && $status !== 'all') {
    $whereClauses[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($email) {
    $whereClauses[] = "lead_email = ?";
    $params[] = $email;
    $types .= "s";
}

$whereSQL = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

// Check if table exists
if ($conn->query("SHOW TABLES LIKE 'bookings'")->num_rows === 0) {
    echo json_encode(['success' => true, 'data' => [], 'count' => 0]);
    exit;
}

// Get total count
$countSQL = "SELECT COUNT(*) as total FROM bookings" . $whereSQL;
$countStmt = $conn->prepare($countSQL);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalCount = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Get bookings
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$bookingsSQL = "SELECT * FROM bookings" . $whereSQL . " ORDER BY submitted_at DESC LIMIT ? OFFSET ?";
$bookingsStmt = $conn->prepare($bookingsSQL);
if (!empty($params)) {
    $bookingsStmt->bind_param($types, ...$params);
}
$bookingsStmt->execute();
$result = $bookingsStmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = [
        'id' => $row['id'],
        'bookingId' => $row['booking_id'],
        'title' => $row['package_title'],
        'destination' => $row['destination'],
        'dates' => $row['travel_dates'],
        'nights' => (int)$row['nights'],
        'travelers' => (int)$row['travelers'],
        'status' => $row['status'],
        'price' => $row['price'],
        'leadName' => $row['lead_name'],
        'leadEmail' => $row['lead_email'],
        'leadPhone' => $row['lead_phone'],
        'paymentMethod' => $row['payment_method'],
        'packageType' => $row['package_type'],
        'selectedTier' => $row['selected_tier'],
        'submittedAt' => $row['submitted_at'],
    ];
}

$bookingsStmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'data' => $bookings,
    'count' => $totalCount,
]);

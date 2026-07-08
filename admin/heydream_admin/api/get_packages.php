<?php
// api/get_packages.php - Return approved packages for the mobile app

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_config.php';

$response = ['success' => false, 'message' => '', 'data' => []];

$partner_id = $_GET['partner_id'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    $conn = getDBConnection();

    // If flight_packages table does not exist, return empty
    $tableCheck = $conn->query("SHOW TABLES LIKE 'flight_packages'");
    if ($tableCheck->num_rows == 0) {
        $response['success'] = true;
        $response['data'] = [];
        $response['total'] = 0;
        $response['message'] = 'No packages available yet';
        echo json_encode($response);
        exit;
    }

    $sql = "SELECT * FROM flight_packages WHERE approved = 1";
    $params = [];
    $types = "";

    if ($partner_id) {
        $sql .= " AND partner_id = ?";
        $params[] = $partner_id;
        $types .= "s";
    }

    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $countSql = "SELECT COUNT(*) as total FROM flight_packages WHERE approved = 1";
    $countParams = [];
    $countTypes = "";
    if ($partner_id) {
        $countSql .= " AND partner_id = ?";
        $countParams[] = $partner_id;
        $countTypes .= "s";
    }
    $countStmt = $conn->prepare($countSql);
    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalCount = $totalResult->fetch_assoc()['total'] ?? 0;
    $countStmt->close();

    $packages = [];
    while ($row = $result->fetch_assoc()) {
        $row['gallery'] = json_decode($row['gallery'], true) ?: [];
        $row['inclusions'] = json_decode($row['inclusions'], true) ?: [];
        $row['exclusions'] = json_decode($row['exclusions'], true) ?: [];
        $row['itinerary'] = json_decode($row['itinerary'], true) ?: [];
        $row['pricing_tiers'] = json_decode($row['pricing_tiers'], true) ?: [];

        $package = [
            'id' => $row['id'],
            'title' => $row['title'],
            'partner_id' => $row['partner_id'],
            'partner_name' => $row['partner_name'],
            'destination' => $row['destination'],
            'address' => $row['address'],
            'duration' => $row['duration'],
            'price' => $row['price'],
            'description' => $row['description'],
            'image' => $row['image'],
            'gallery' => $row['gallery'],
            'inclusions' => $row['inclusions'],
            'exclusions' => $row['exclusions'],
            'itinerary' => $row['itinerary'],
            'pricing_tiers' => $row['pricing_tiers'],
            'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
            'package_type' => $row['package_type'],
            'category' => $row['category'],
            'approved' => (int)$row['approved'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'vehicle_info' => json_decode($row['vehicle_info'] ?? '{}', true) ?: [],
            'rental_info' => json_decode($row['rental_info'] ?? '{}', true) ?: [],
            'vehicle_requirements' => json_decode($row['vehicle_requirements'] ?? '[]', true) ?: [],
            'service_info' => json_decode($row['service_info'] ?? '{}', true) ?: [],
            'cruise_info' => json_decode($row['cruise_info'] ?? '{}', true) ?: [],
            'schedule_text' => $row['schedule_text'] ?? 'Morning • Afternoon • Evening',
        ];

        $packages[] = $package;
    }

    $response['success'] = true;
    $response['data'] = $packages;
    $response['total'] = $totalCount;
    $response['limit'] = $limit;
    $response['offset'] = $offset;

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log("get_packages.php error: " . $e->getMessage());
}

echo json_encode($response);

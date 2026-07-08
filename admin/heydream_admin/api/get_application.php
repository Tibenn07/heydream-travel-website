<?php
// api/get_application.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../db_config.php';

$response = ['success' => false, 'message' => '', 'data' => null];

$id = $_GET['id'] ?? '';

if (empty($id)) {
    $response['message'] = 'Application ID is required';
    echo json_encode($response);
    exit;
}

$conn = getDBConnection();

// Get all columns from the table
$columns = [];
$columnsResult = $conn->query("SHOW COLUMNS FROM partner_applications");
if ($columnsResult) {
    while ($col = $columnsResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
}

// Build the select query with all columns
$selectFields = implode(", ", $columns);
$stmt = $conn->prepare("SELECT $selectFields FROM partner_applications WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($app = $result->fetch_assoc()) {
    // Add face_verification_filename if it exists in the table
    if (!isset($app['face_verification_filename'])) {
        $app['face_verification_filename'] = null;
    }
    if (!isset($app['latitude'])) {
        $app['latitude'] = null;
    }
    if (!isset($app['longitude'])) {
        $app['longitude'] = null;
    }
    $response['success'] = true;
    $response['data'] = $app;
} else {
    $response['message'] = 'Application not found';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
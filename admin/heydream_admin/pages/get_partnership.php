<?php
// api/get_partnership.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db_config.php';

$response = ['success' => false, 'message' => '', 'data' => null];

$id = $_GET['id'] ?? '';

if (empty($id)) {
    $response['message'] = 'Report ID is required';
    echo json_encode($response);
    exit;
}

$conn = getDBConnection();

// Get all columns from the table
$columns = [];
$columnsResult = $conn->query("SHOW COLUMNS FROM partnership_reports");
if ($columnsResult) {
    while ($col = $columnsResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
}

// Build the select query with all columns
$selectFields = implode(", ", $columns);
$stmt = $conn->prepare("SELECT $selectFields FROM partnership_reports WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($report = $result->fetch_assoc()) {
    $response['success'] = true;
    $response['data'] = $report;
} else {
    $response['message'] = 'Report not found';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
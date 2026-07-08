<?php
// api/update_application_status.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../db_config.php';

$response = ['success' => false, 'message' => ''];

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $response['message'] = 'Invalid input';
    echo json_encode($response);
    exit;
}

$id = $input['id'] ?? '';
$status = $input['status'] ?? '';
$rejection_reason = $input['rejection_reason'] ?? '';

if (empty($id) || empty($status)) {
    $response['message'] = 'ID and status are required';
    echo json_encode($response);
    exit;
}

$conn = getDBConnection();

$sql = "UPDATE partner_applications SET status = ?";
$params = [$status];
$types = "s";

if ($status === 'rejected' && $rejection_reason) {
    $sql .= ", rejection_reason = ?";
    $params[] = $rejection_reason;
    $types .= "s";
}

$sql .= ", reviewed_at = NOW() WHERE id = ?";
$params[] = $id;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Status updated successfully';
} else {
    $response['message'] = 'Failed to update status: ' . $conn->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
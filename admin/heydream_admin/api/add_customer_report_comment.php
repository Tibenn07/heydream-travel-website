<?php
// api/add_customer_report_comment.php
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

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $response['message'] = 'Unauthorized. Please login first.';
    echo json_encode($response);
    exit;
}

// Get POST JSON data
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$report_id = $input['report_id'] ?? '';
$comment = $input['comment'] ?? '';

if (empty($report_id) || !is_numeric($report_id)) {
    $response['message'] = 'Valid Report ID is required';
    echo json_encode($response);
    exit;
}

if (empty(trim($comment))) {
    $response['message'] = 'Comment cannot be empty';
    echo json_encode($response);
    exit;
}

$conn = getDBConnection();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

$stmt = $conn->prepare("INSERT INTO customer_report_comments (report_id, admin_id, admin_name, comment) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $report_id, $admin_id, $admin_name, $comment);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Comment added successfully';
} else {
    $response['message'] = 'Error adding comment: ' . $stmt->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>

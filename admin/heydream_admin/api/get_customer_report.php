<?php
// api/get_customer_report.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../db_config.php';

$response = ['success' => false, 'message' => '', 'data' => null];

$id = $_GET['id'] ?? '';

if (empty($id) || !is_numeric($id)) {
    $response['message'] = 'Valid Report ID is required';
    echo json_encode($response);
    exit;
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT * FROM customer_reports WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($report = $result->fetch_assoc()) {
    // Check if customer_report_comments table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'customer_report_comments'");
    $comments = [];
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $cStmt = $conn->prepare("SELECT c.*, u.role FROM customer_report_comments c LEFT JOIN admin_users u ON c.admin_id = u.id WHERE c.report_id = ? ORDER BY c.created_at ASC");
        $cStmt->bind_param("i", $id);
        $cStmt->execute();
        $cResult = $cStmt->get_result();
        while ($comment = $cResult->fetch_assoc()) {
            $comments[] = $comment;
        }
        $cStmt->close();
    }
    $report['comments'] = $comments;

    $response['success'] = true;
    $response['data'] = $report;
} else {
    $response['message'] = 'Report not found';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
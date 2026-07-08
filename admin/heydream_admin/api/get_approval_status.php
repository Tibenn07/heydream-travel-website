<?php
// api/get_approval_status.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../db_config.php';

$response = ['success' => false, 'message' => ''];

$id = $_GET['id'] ?? '';

if (empty($id) || !is_numeric($id)) {
    $response['message'] = 'Valid approval ID is required';
    echo json_encode($response);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT id, status, rejection_reason, reviewed_at, submitted_at
        FROM approvals 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response['success'] = true;
        $response['data'] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'rejection_reason' => $row['rejection_reason'],
            'reviewed_at' => $row['reviewed_at'],
            'submitted_at' => $row['submitted_at']
        ];
    } else {
        $response['message'] = 'Approval not found';
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
?>
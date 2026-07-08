<?php
// api/get_partner_approvals.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../db_config.php';

$response = ['success' => false, 'message' => '', 'data' => []];

$partner_id = $_GET['partner_id'] ?? '';

if (empty($partner_id)) {
    $response['message'] = 'Partner ID is required';
    echo json_encode($response);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT id, type, title, status, submitted_at, reviewed_at, rejection_reason
        FROM approvals 
        WHERE partner_id = ? AND type = 'package'
        ORDER BY submitted_at DESC
    ");
    $stmt->bind_param("s", $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $approvals = [];
    while ($row = $result->fetch_assoc()) {
        $approvals[] = $row;
    }
    
    $response['success'] = true;
    $response['data'] = $approvals;
    $response['total'] = count($approvals);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
?>
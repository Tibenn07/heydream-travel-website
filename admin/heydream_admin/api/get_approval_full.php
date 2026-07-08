<?php
// api/get_approval_full.php
// FIXED - Handles missing columns gracefully

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
    $response['message'] = 'Valid ID is required';
    echo json_encode($response);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check which columns exist
    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM approvals");
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
    
    // Build select fields based on existing columns
    $selectFields = "id, type, partner_id, partner_name, title, content, status, submitted_at, reviewed_at, rejection_reason";
    
    if (in_array('description', $columns)) {
        $selectFields .= ", description";
    }
    if (in_array('metadata', $columns)) {
        $selectFields .= ", metadata";
    }
    
    $stmt = $conn->prepare("SELECT $selectFields FROM approvals WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Decode JSON fields
        if (!empty($row['content'])) {
            $row['content'] = json_decode($row['content'], true);
        }
        if (isset($row['metadata']) && !empty($row['metadata'])) {
            $row['metadata'] = json_decode($row['metadata'], true);
        } else {
            $row['metadata'] = $row['content'] ?? [];
        }
        
        $response['success'] = true;
        $response['data'] = $row;
    } else {
        $response['message'] = 'Not found';
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
?>
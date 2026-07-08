<?php
// api/submit_package_approval.php
// Receives package approval submissions from partner app

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../db_config.php';

$response = ['success' => false, 'message' => ''];

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $response['message'] = 'Invalid input data';
    echo json_encode($response);
    exit;
}

if (empty($input['title']) || empty($input['content'])) {
    $response['message'] = 'Title and content are required';
    echo json_encode($response);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check which columns exist in approvals table
    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM approvals");
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
    
    $type = 'package';
    $partner_id = $input['partner_id'] ?? 'partner_1';
    $partner_name = $input['partner_name'] ?? 'Unknown Partner';
    $title = $input['title'];
    $description = $input['description'] ?? $title;
    $content = $input['content'];
    $metadata = json_encode($input['metadata'] ?? $input);
    
    // Build query based on existing columns
    if (in_array('description', $columns) && in_array('metadata', $columns)) {
        $stmt = $conn->prepare("
            INSERT INTO approvals (
                type, partner_id, partner_name, title, description, content, 
                metadata, status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param(
            "sssssss",
            $type,
            $partner_id,
            $partner_name,
            $title,
            $description,
            $content,
            $metadata
        );
    } elseif (in_array('metadata', $columns)) {
        $stmt = $conn->prepare("
            INSERT INTO approvals (
                type, partner_id, partner_name, title, content, 
                metadata, status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param(
            "ssssss",
            $type,
            $partner_id,
            $partner_name,
            $title,
            $content,
            $metadata
        );
    } else {
        $stmt = $conn->prepare("
            INSERT INTO approvals (
                type, partner_id, partner_name, title, content, 
                status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param(
            "sssss",
            $type,
            $partner_id,
            $partner_name,
            $title,
            $content
        );
    }
    
    if ($stmt->execute()) {
        $approvalId = $stmt->insert_id;
        
        $response['success'] = true;
        $response['message'] = 'Package submitted for approval';
        $response['data'] = [
            'id' => $approvalId,
            'status' => 'pending'
        ];
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
?>
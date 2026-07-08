<?php
// api/test_debug.php - Test file to check if API is working
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log everything
error_log("=== TEST DEBUG ===");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

$response = [
    'success' => true,
    'message' => 'Test API is working!',
    'post_data' => $_POST,
    'files' => $_FILES,
    'server' => $_SERVER
];

echo json_encode($response);
?>
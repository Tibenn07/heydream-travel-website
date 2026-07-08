<?php
// api/test.php - Test API connection
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = [
    'success' => true,
    'message' => 'API is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'],
    'php_version' => phpversion(),
    'method' => $_SERVER['REQUEST_METHOD'],
    'api_base' => dirname($_SERVER['SCRIPT_NAME'])
];

echo json_encode($response);
?>
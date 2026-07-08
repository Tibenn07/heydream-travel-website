<?php
// api/submit_report.php - Submit customer report from mobile app
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Always return JSON, even on errors
function returnError($message) {
    $response = ['success' => false, 'message' => $message];
    echo json_encode($response);
    exit;
}

try {
    require_once '../db_config.php';
} catch (Exception $e) {
    returnError('Database config error: ' . $e->getMessage());
}

// Check if db_config exists
if (!file_exists('../db_config.php')) {
    returnError('Database configuration file not found');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnError('Invalid request method. Use POST.');
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    returnError('Database connection failed: ' . $e->getMessage());
}

// Get form data
$report_type = isset($_POST['report_type']) ? trim($_POST['report_type']) : '';
$partner_name = isset($_POST['partner_name']) ? trim($_POST['partner_name']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$reported_by = isset($_POST['reported_by']) ? trim($_POST['reported_by']) : '';
$reported_by_email = isset($_POST['reported_by_email']) ? trim($_POST['reported_by_email']) : '';
$priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'medium';

// Validate
$errors = [];
if (empty($report_type)) $errors[] = 'Report type is required';
if (empty($subject)) $errors[] = 'Subject is required';
if (empty($description)) $errors[] = 'Description is required';
if (empty($reported_by)) $errors[] = 'Your name is required';
if (empty($reported_by_email)) $errors[] = 'Your email is required';

if (!empty($errors)) {
    returnError(implode(', ', $errors));
}

// Create upload directory
$uploadDir = __DIR__ . '/../uploads/reports/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        returnError('Failed to create upload directory');
    }
}

// Handle screenshot upload
$screenshot_path = '';
if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
    $screenshot_path = saveUploadedFile($_FILES['screenshot'], $uploadDir, 'report');
}

// Check if customer_reports table exists, if not create it
$tableCheck = $conn->query("SHOW TABLES LIKE 'customer_reports'");
if ($tableCheck->num_rows == 0) {
    $createTable = "
        CREATE TABLE customer_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_type VARCHAR(50) NOT NULL DEFAULT 'general',
            partner_name VARCHAR(255) DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            screenshot_path VARCHAR(500) DEFAULT NULL,
            reported_by VARCHAR(255) NOT NULL,
            reported_by_email VARCHAR(255) NOT NULL,
            priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
            status ENUM('open', 'in_review', 'resolved') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_report_type (report_type),
            INDEX idx_status (status)
        )
    ";
    if (!$conn->query($createTable)) {
        returnError('Failed to create table: ' . $conn->error);
    }
}

// Check and fix table structure - remove report_id if it exists
$columnsResult = $conn->query("SHOW COLUMNS FROM customer_reports");
$columns = [];
if ($columnsResult) {
    while ($col = $columnsResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
}

// If report_id exists, drop it (it's causing the duplicate entry error)
if (in_array('report_id', $columns)) {
    $conn->query("ALTER TABLE customer_reports DROP COLUMN report_id");
    // Re-fetch columns after drop
    $columnsResult = $conn->query("SHOW COLUMNS FROM customer_reports");
    $columns = [];
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
}

// Add missing columns if needed
if (!in_array('report_type', $columns)) {
    $conn->query("ALTER TABLE customer_reports ADD COLUMN report_type VARCHAR(50) NOT NULL DEFAULT 'general'");
}
if (!in_array('partner_name', $columns)) {
    $conn->query("ALTER TABLE customer_reports ADD COLUMN partner_name VARCHAR(255) DEFAULT NULL");
}
if (!in_array('screenshot_path', $columns)) {
    $conn->query("ALTER TABLE customer_reports ADD COLUMN screenshot_path VARCHAR(500) DEFAULT NULL");
}
if (!in_array('priority', $columns)) {
    $conn->query("ALTER TABLE customer_reports ADD COLUMN priority ENUM('high', 'medium', 'low') DEFAULT 'medium'");
}
if (!in_array('status', $columns)) {
    $conn->query("ALTER TABLE customer_reports ADD COLUMN status ENUM('open', 'in_review', 'resolved') DEFAULT 'open'");
}

// Insert report - using id as auto_increment, no report_id
$sql = "INSERT INTO customer_reports (
    report_type, partner_name, subject, description, screenshot_path,
    reported_by, reported_by_email, priority, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    returnError('Database prepare error: ' . $conn->error);
}

$stmt->bind_param(
    "ssssssss",
    $report_type,
    $partner_name,
    $subject,
    $description,
    $screenshot_path,
    $reported_by,
    $reported_by_email,
    $priority
);

if ($stmt->execute()) {
    $response = [
        'success' => true,
        'message' => 'Report submitted successfully!',
        'report_id' => $stmt->insert_id
    ];
    echo json_encode($response);
} else {
    returnError('Database error: ' . $stmt->error);
}

$stmt->close();
$conn->close();

function saveUploadedFile($file, $uploadDir, $prefix) {
    try {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $prefix . '_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filename;
        }
        return '';
    } catch (Exception $e) {
        return '';
    }
}
?>
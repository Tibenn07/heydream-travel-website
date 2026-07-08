<?php
// api/submit_application.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => ''];

// Log the request for debugging
error_log("=== SUBMIT APPLICATION REQUEST ===");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

try {
    require_once '../db_config.php';
} catch (Exception $e) {
    $response['message'] = 'Database config error: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// Check if db_config exists
if (!file_exists('../db_config.php')) {
    $response['message'] = 'db_config.php not found';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method. Use POST.';
    echo json_encode($response);
    exit;
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    $response['message'] = 'Database connection failed: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// Get form data
$business_name = $_POST['business_name'] ?? '';
$person_name = $_POST['person_name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$business_type = $_POST['business_type'] ?? '';
$address = $_POST['address'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';
$message = $_POST['message'] ?? '';

// Validate
if (empty($business_name) || empty($email) || empty($phone)) {
    $response['message'] = 'Please fill in all required fields';
    echo json_encode($response);
    $conn->close();
    exit;
}

$application_id = generateApplicationId();

// Create upload directory
$uploadDir = __DIR__ . '/../uploads/applications/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    error_log("Created upload directory: " . $uploadDir);
}

error_log("Upload directory: " . $uploadDir);

// Handle file uploads
$business_permit_file = '';
$dti_file = '';
$sec_file = '';
$dot_file = '';
$business_id_file = '';
$face_verification_file = '';

// Process uploaded files
if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] === UPLOAD_ERR_OK) {
    $business_permit_file = saveUploadedFile($_FILES['business_permit'], $uploadDir, 'business_permit');
}

if (isset($_FILES['dti_registration']) && $_FILES['dti_registration']['error'] === UPLOAD_ERR_OK) {
    $dti_file = saveUploadedFile($_FILES['dti_registration'], $uploadDir, 'dti');
}

if (isset($_FILES['sec_registration']) && $_FILES['sec_registration']['error'] === UPLOAD_ERR_OK) {
    $sec_file = saveUploadedFile($_FILES['sec_registration'], $uploadDir, 'sec');
}

if (isset($_FILES['dot_accreditation']) && $_FILES['dot_accreditation']['error'] === UPLOAD_ERR_OK) {
    $dot_file = saveUploadedFile($_FILES['dot_accreditation'], $uploadDir, 'dot');
}

if (isset($_FILES['business_id']) && $_FILES['business_id']['error'] === UPLOAD_ERR_OK) {
    $business_id_file = saveUploadedFile($_FILES['business_id'], $uploadDir, 'business_id');
}

// Process face verification photo
if (isset($_FILES['face_verification']) && $_FILES['face_verification']['error'] === UPLOAD_ERR_OK) {
    $face_verification_file = saveUploadedFile($_FILES['face_verification'], $uploadDir, 'face_verification');
}

// Check if person_name column exists
$personNameExists = false;
$businessIdExists = false;
$faceVerificationExists = false;
$latitudeExists = false;
$longitudeExists = false;

$columnsResult = $conn->query("SHOW COLUMNS FROM partner_applications");
if ($columnsResult) {
    while ($col = $columnsResult->fetch_assoc()) {
        if ($col['Field'] === 'person_name') $personNameExists = true;
        if ($col['Field'] === 'business_id_filename') $businessIdExists = true;
        if ($col['Field'] === 'face_verification_filename') $faceVerificationExists = true;
        if ($col['Field'] === 'latitude') $latitudeExists = true;
        if ($col['Field'] === 'longitude') $longitudeExists = true;
    }
}

// Build the insert query dynamically based on existing columns
$fields = "application_id, business_name, email, phone, business_type, address, message, 
           business_permit_filename, dti_filename, sec_filename, dot_filename, status";
$values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'";
$params = [
    $application_id, $business_name, $email, $phone, $business_type, $address, $message,
    $business_permit_file, $dti_file, $sec_file, $dot_file
];
$types = "sssssssssss";

// Add person_name if column exists
if ($personNameExists) {
    $fields .= ", person_name";
    $values .= ", ?";
    $params[] = $person_name;
    $types .= "s";
}

// Add business_id_filename if column exists
if ($businessIdExists) {
    $fields .= ", business_id_filename";
    $values .= ", ?";
    $params[] = $business_id_file;
    $types .= "s";
}

// Add face_verification_filename if column exists
if ($faceVerificationExists) {
    $fields .= ", face_verification_filename";
    $values .= ", ?";
    $params[] = $face_verification_file;
    $types .= "s";
}

// Add latitude if column exists
if ($latitudeExists) {
    $fields .= ", latitude";
    $values .= ", ?";
    $params[] = $latitude;
    $types .= "s";
}

// Add longitude if column exists
if ($longitudeExists) {
    $fields .= ", longitude";
    $values .= ", ?";
    $params[] = $longitude;
    $types .= "s";
}

$sql = "INSERT INTO partner_applications ($fields) VALUES ($values)";
error_log("SQL: " . $sql);

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Application submitted successfully!';
    $response['data'] = ['application_id' => $application_id];
} else {
    $response['message'] = 'Database error: ' . $conn->error;
    error_log("DB Error: " . $conn->error);
}

$stmt->close();
$conn->close();

echo json_encode($response);

function saveUploadedFile($file, $uploadDir, $prefix) {
    try {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $prefix . '_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log("File saved: " . $filepath);
            return $filename;
        }
        error_log("Failed to move file: " . $file['tmp_name'] . " to " . $filepath);
        return '';
    } catch (Exception $e) {
        error_log("saveUploadedFile error: " . $e->getMessage());
        return '';
    }
}
?>
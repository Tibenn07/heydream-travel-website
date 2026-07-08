<?php
// api_save_application.php - Receives partner application from React Native app
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Try regular POST if JSON fails
        $input = $_POST;
    }
    
    // Validate required fields
    if (empty($input['email'])) {
        $response['message'] = 'Email is required';
        echo json_encode($response);
        exit;
    }
    
    $uploadDir = 'uploads/applications/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Handle file uploads (base64 from React Native)
    $business_permit_filename = null;
    $business_permit_path = null;
    $dti_filename = null;
    $dti_path = null;
    $sec_filename = null;
    $sec_path = null;
    $dot_filename = null;
    $dot_path = null;
    $business_id_filename = null;
    $business_id_path = null;
    
    // Process business permit
    if (!empty($input['businessPermit_data'])) {
        $business_permit_filename = saveBase64File($input['businessPermit_data'], $uploadDir, 'business_permit');
        $business_permit_path = $uploadDir . $business_permit_filename;
    }
    
    // Process DTI registration
    if (!empty($input['dtiRegistration_data'])) {
        $dti_filename = saveBase64File($input['dtiRegistration_data'], $uploadDir, 'dti');
        $dti_path = $uploadDir . $dti_filename;
    }
    
    // Process SEC registration
    if (!empty($input['secRegistration_data'])) {
        $sec_filename = saveBase64File($input['secRegistration_data'], $uploadDir, 'sec');
        $sec_path = $uploadDir . $sec_filename;
    }
    
    // Process DOT accreditation
    if (!empty($input['dotAccreditation_data'])) {
        $dot_filename = saveBase64File($input['dotAccreditation_data'], $uploadDir, 'dot');
        $dot_path = $uploadDir . $dot_filename;
    }
    
    // Process Business ID
    if (!empty($input['businessId_data'])) {
        $business_id_filename = saveBase64File($input['businessId_data'], $uploadDir, 'business_id');
        $business_id_path = $uploadDir . $business_id_filename;
    }
    
    $conn = getDBConnection();
    
    $application_id = generateApplicationId();
    $business_name = sanitizeInput($input['business_name'] ?? '');
    $person_name = sanitizeInput($input['person_name'] ?? '');
    $email = sanitizeInput($input['email']);
    $phone = sanitizeInput($input['phone'] ?? '');
    $business_type = sanitizeInput($input['business_type'] ?? '');
    $address = sanitizeInput($input['address'] ?? '');
    $message = sanitizeInput($input['message'] ?? '');
    
    // Check if person_name column exists
    $personNameExists = false;
    $businessIdExists = false;
    $columnsResult = $conn->query("SHOW COLUMNS FROM partner_applications");
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            if ($col['Field'] === 'person_name') $personNameExists = true;
            if ($col['Field'] === 'business_id_filename') $businessIdExists = true;
        }
    }
    
    // Build the insert query dynamically based on existing columns
    $fields = "application_id, business_name, email, phone, business_type, address, message, 
               business_permit_filename, business_permit_path, dti_filename, dti_path, 
               sec_filename, sec_path, dot_filename, dot_path, status";
    $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'";
    $params = [
        $application_id, $business_name, $email, $phone, $business_type, $address, $message,
        $business_permit_filename, $business_permit_path, $dti_filename, $dti_path,
        $sec_filename, $sec_path, $dot_filename, $dot_path
    ];
    $types = "sssssssssssssss";
    
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
        $params[] = $business_id_filename;
        $types .= "s";
    }
    
    $sql = "INSERT INTO partner_applications ($fields) VALUES ($values)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Application submitted successfully!';
        $response['application_id'] = $application_id;
    } else {
        $response['message'] = 'Failed to save application: ' . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);

function saveBase64File($base64Data, $uploadDir, $prefix) {
    // Extract base64 data
    if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $matches)) {
        $fileData = base64_decode($matches[2]);
        $mimeType = $matches[1];
        
        $extension = '';
        if (strpos($mimeType, 'pdf') !== false) $extension = 'pdf';
        elseif (strpos($mimeType, 'jpeg') !== false) $extension = 'jpg';
        elseif (strpos($mimeType, 'png') !== false) $extension = 'png';
        else $extension = 'bin';
        
        $filename = $prefix . '_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        file_put_contents($filepath, $fileData);
        return $filename;
    }
    return null;
}
?>
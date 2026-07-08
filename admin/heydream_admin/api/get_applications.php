<?php
// api/get_applications.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../db_config.php';

$response = ['success' => false, 'message' => '', 'data' => []];

$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$conn = getDBConnection();

// Build query
$where = [];
$params = [];
$types = "";

if ($status && $status !== 'all') {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($search) {
    $where[] = "(business_name LIKE ? OR email LIKE ? OR person_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM partner_applications $whereSQL");
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

// Get data
$sql = "SELECT * FROM partner_applications $whereSQL ORDER BY submitted_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}

$response['success'] = true;
$response['data'] = $applications;
$response['total'] = $total;

$stmt->close();
$conn->close();

echo json_encode($response);
?>
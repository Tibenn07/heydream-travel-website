<?php
// File: User Account/api/upload-api.php
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// Check if user or admin is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Auto-create booking_documents table if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS booking_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_number VARCHAR(50) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_booking_number (booking_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* silently continue */ }

// Auto-create upload directory
$uploadDir = __DIR__ . '/../../uploads/booking_docs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'upload':
            if (!isset($_FILES['document']) || !isset($_POST['booking_number'])) {
                echo json_encode(['success' => false, 'message' => 'Missing file or booking number']);
                break;
            }

            $bookingNumber = $_POST['booking_number'];
            $file = $_FILES['document'];

            // Validate file type
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF and Images (JPG, PNG, WEBP) are allowed.']);
                break;
            }

            // 10MB limit
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File size too large. Max 10MB allowed.']);
                break;
            }

            // Create unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = $bookingNumber . '_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_path = $uploadDir . $newFileName;
            $db_path = 'uploads/booking_docs/' . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Save to database
                $stmt = $pdo->prepare("INSERT INTO booking_documents (booking_number, file_path, file_name) VALUES (?, ?, ?)");
                $stmt->execute([$bookingNumber, $db_path, $file['name']]);
                $newId = $pdo->lastInsertId();

                // Update travel_documents flag in bookings if not already set
                $stmt = $pdo->prepare("UPDATE bookings SET travel_documents = 1 WHERE booking_number = ? AND travel_documents = 0");
                $stmt->execute([$bookingNumber]);

                echo json_encode([
                    'success'  => true,
                    'message'  => 'File uploaded successfully',
                    'file'     => [
                        'id'          => $newId,
                        'name'        => $file['name'],
                        'path'        => $db_path,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save file on server']);
            }
            break;

        case 'delete':
            // Only the document owner (user) or admin can delete
            $docId = intval($_POST['id'] ?? 0);
            $bookingNumber = trim($_POST['booking_number'] ?? '');

            if ($docId <= 0 || empty($bookingNumber)) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                break;
            }

            // Fetch the document record
            $stmt = $pdo->prepare("SELECT * FROM booking_documents WHERE id = ? AND booking_number = ?");
            $stmt->execute([$docId, $bookingNumber]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                echo json_encode(['success' => false, 'message' => 'Document not found']);
                break;
            }

            // If user is logged in (not admin), verify this booking belongs to them
            if (isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
                $checkStmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_number = ? AND user_id = ?");
                $checkStmt->execute([$bookingNumber, $_SESSION['user_id']]);
                if (!$checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Permission denied']);
                    break;
                }
            }

            // Delete file from disk
            $filePath = __DIR__ . '/../../' . $doc['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM booking_documents WHERE id = ?");
            $stmt->execute([$docId]);

            // Check if there are remaining documents; if none, reset travel_documents flag
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM booking_documents WHERE booking_number = ?");
            $countStmt->execute([$bookingNumber]);
            $remaining = $countStmt->fetchColumn();
            if ($remaining == 0) {
                $pdo->prepare("UPDATE bookings SET travel_documents = 0 WHERE booking_number = ?")->execute([$bookingNumber]);
            }

            echo json_encode(['success' => true, 'message' => 'Document deleted', 'remaining' => intval($remaining)]);
            break;

        case 'list':
            $bookingNumber = $_GET['booking_number'] ?? '';
            if (empty($bookingNumber)) {
                echo json_encode(['success' => false, 'message' => 'Missing booking number']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM booking_documents WHERE booking_number = ? ORDER BY uploaded_at DESC");
            $stmt->execute([$bookingNumber]);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'documents' => $docs]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

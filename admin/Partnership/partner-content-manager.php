<?php
// File: admin/Partnership/partner-content-manager.php
// Partner-Specific Content Manager - Manages ONLY packages uploaded by partners
// Packages are stored in partner_package_uploads table
// Packages appear on partners.php and via partner-packages.js

// Suppress errors from appearing in output (especially important for JSON responses)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header for all POST responses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
}

// Allow only partner users to access this partner-specific content manager
$is_partner = false;
if (isset($_SESSION['partner_id']) && !empty($_SESSION['partner_id'])) {
    $is_partner = true;
    $partnerId = (int)$_SESSION['partner_id'];
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sendJson(['success' => false, 'message' => 'Your session has expired. Please log in again.'], 401);
    }

    header('Location: partner-login.php');
    exit;
}

$page = $_GET['page'] ?? 'foreign-destinations';
$partner_profile = null;

// Partners can access foreign and local destinations
$allowedPartnerPages = ['foreign-destinations', 'local-destinations'];
$requestedPage = trim($_GET['page'] ?? '');
if ($requestedPage !== '' && in_array($requestedPage, $allowedPartnerPages, true)) {
    $page = $requestedPage;
}

// Get partner profile info
$partnerProfileStmt = $pdo->prepare("SELECT company_name, contact_person, email FROM partner_applications WHERE id = ? LIMIT 1");
$partnerProfileStmt->execute([$partnerId]);
$partner_profile = $partnerProfileStmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function sendJson($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle image upload
function uploadImage($file, $old_image = null)
{
    global $upload_dir;

    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $file_type = $file['type'];

        if (!in_array($file_type, $allowed_types)) {
            return ['success' => false, 'message' => 'Only JPG, PNG, GIF, and WEBP images are allowed'];
        }

        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            return ['success' => false, 'message' => 'Image size must be less than 5MB'];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            if ($old_image && file_exists(__DIR__ . '/../' . $old_image)) {
                unlink(__DIR__ . '/../' . $old_image);
            }
            return ['success' => true, 'path' => 'uploads/' . $filename];
        }
    }
    return ['success' => true, 'path' => $old_image];
}

// ==================== ENSURE TABLE EXISTS ====================
try {
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS partner_package_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            partner_company VARCHAR(255) NOT NULL,
            uploaded_by_name VARCHAR(255) NOT NULL,
            uploaded_by_email VARCHAR(255) NOT NULL,
            package_name VARCHAR(255) NOT NULL,
            destination_name VARCHAR(255) DEFAULT '',
            destination_type ENUM('foreign', 'local') DEFAULT 'foreign',
            duration VARCHAR(80) DEFAULT '',
            price DECIMAL(10,2) DEFAULT 0,
            description TEXT,
            image_path VARCHAR(500),
            image2_path VARCHAR(500),
            image3_path VARCHAR(500),
            image_gallery TEXT,
            inclusions TEXT,
            exclusions TEXT,
            itinerary TEXT,
            hotels TEXT,
            remarks TEXT,
            blocked_dates TEXT,
            promo_start DATE,
            promo_end DATE,
            blocked_months TEXT,
            highlight_duration INT DEFAULT 1,
            currency VARCHAR(10) DEFAULT '₱',
            group_size VARCHAR(50) DEFAULT '2-15 pax',
            best_season VARCHAR(100) DEFAULT 'Year Round',
            activities_count INT DEFAULT 0,
            badge_text VARCHAR(100),
            display_order INT DEFAULT 0,
            upload_status VARCHAR(30) DEFAULT 'pending',
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_partner_uploads_partner (partner_id),
            INDEX idx_partner_uploads_status (upload_status),
            INDEX idx_destination_type (destination_type)
        )
SQL);
} catch (Throwable $e) {
    error_log("Table creation error: " . $e->getMessage());
}

try {
    $existingColumnsStmt = $pdo->query('SHOW COLUMNS FROM partner_package_uploads');
    $existingColumns = [];
    foreach ($existingColumnsStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $existingColumns[] = $column['Field'];
    }

    $columnUpdates = [
        'destination_type' => "ALTER TABLE partner_package_uploads ADD COLUMN destination_type ENUM('foreign','local') DEFAULT 'foreign'",
        'image_path' => "ALTER TABLE partner_package_uploads ADD COLUMN image_path VARCHAR(500)",
        'image2_path' => "ALTER TABLE partner_package_uploads ADD COLUMN image2_path VARCHAR(500)",
        'image3_path' => "ALTER TABLE partner_package_uploads ADD COLUMN image3_path VARCHAR(500)",
        'image_gallery' => "ALTER TABLE partner_package_uploads ADD COLUMN image_gallery TEXT",
        'inclusions' => "ALTER TABLE partner_package_uploads ADD COLUMN inclusions TEXT",
        'exclusions' => "ALTER TABLE partner_package_uploads ADD COLUMN exclusions TEXT",
        'itinerary' => "ALTER TABLE partner_package_uploads ADD COLUMN itinerary TEXT",
        'hotels' => "ALTER TABLE partner_package_uploads ADD COLUMN hotels TEXT",
        'remarks' => "ALTER TABLE partner_package_uploads ADD COLUMN remarks TEXT",
        'blocked_dates' => "ALTER TABLE partner_package_uploads ADD COLUMN blocked_dates TEXT",
        'promo_start' => "ALTER TABLE partner_package_uploads ADD COLUMN promo_start DATE",
        'promo_end' => "ALTER TABLE partner_package_uploads ADD COLUMN promo_end DATE",
        'blocked_months' => "ALTER TABLE partner_package_uploads ADD COLUMN blocked_months TEXT",
        'highlight_duration' => "ALTER TABLE partner_package_uploads ADD COLUMN highlight_duration INT DEFAULT 1",
        'currency' => "ALTER TABLE partner_package_uploads ADD COLUMN currency VARCHAR(10) DEFAULT '₱'",
        'group_size' => "ALTER TABLE partner_package_uploads ADD COLUMN group_size VARCHAR(50) DEFAULT '2-15 pax'",
        'best_season' => "ALTER TABLE partner_package_uploads ADD COLUMN best_season VARCHAR(100) DEFAULT 'Year Round'",
        'activities_count' => "ALTER TABLE partner_package_uploads ADD COLUMN activities_count INT DEFAULT 0",
        'badge_text' => "ALTER TABLE partner_package_uploads ADD COLUMN badge_text VARCHAR(100)",
        'display_order' => "ALTER TABLE partner_package_uploads ADD COLUMN display_order INT DEFAULT 0",
        'upload_status' => "ALTER TABLE partner_package_uploads ADD COLUMN upload_status VARCHAR(30) DEFAULT 'pending'",
        'is_active' => "ALTER TABLE partner_package_uploads ADD COLUMN is_active TINYINT DEFAULT 1"
    ];

    foreach ($columnUpdates as $column => $sql) {
        if (!in_array($column, $existingColumns)) {
            $pdo->exec($sql);
        }
    }
} catch (Throwable $e) {
    error_log('Partner package schema update error: ' . $e->getMessage());
}

// ==================== FORM HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

    // Save Partner Package (Foreign or Local)
    if ($action === 'save_partner_package') {
        $id = intval($_POST['id'] ?? 0);
        $package_name = trim($_POST['package_name'] ?? '');
        $destination_name = trim($_POST['destination_name'] ?? '');
        $destination_type = trim($_POST['destination_type'] ?? 'foreign');
        $duration = trim($_POST['duration'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $currency = trim($_POST['currency'] ?? '₱');
        $group_size = trim($_POST['group_size'] ?? '');
        $best_season = trim($_POST['best_season'] ?? '');
        $activities_count = intval($_POST['activities_count'] ?? 0);
        $badge_text = trim($_POST['badge_text'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $blocked_dates = trim($_POST['blocked_dates'] ?? '');
        $promo_start = !empty($_POST['promo_start']) ? $_POST['promo_start'] : null;
        $promo_end = !empty($_POST['promo_end']) ? $_POST['promo_end'] : null;
        $highlight_duration = intval($_POST['highlight_duration'] ?? 1);
        $blocked_months = isset($_POST['blocked_months']) ? implode(',', $_POST['blocked_months']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Handle items from form
        $itinerary_json = $_POST['itinerary'] ?? '[]';
        $hotels_json = $_POST['hotels'] ?? '[]';

        // Handle inclusions
        $inclusions = $_POST['inclusions'] ?? '';
        if (is_string($inclusions) && strpos($inclusions, "\n") !== false) {
            $inclusions = explode("\n", $inclusions);
            $inclusions = array_filter(array_map('trim', $inclusions));
        }
        $inclusions_json = is_array($inclusions) ? json_encode($inclusions) : '[]';

        // Handle exclusions
        $exclusions = $_POST['exclusions'] ?? '';
        if (is_string($exclusions) && strpos($exclusions, "\n") !== false) {
            $exclusions = explode("\n", $exclusions);
            $exclusions = array_filter(array_map('trim', $exclusions));
        }
        $exclusions_json = is_array($exclusions) ? json_encode($exclusions) : '[]';

        // Handle images
        $image = isset($_FILES['image']) ? uploadImage($_FILES['image'], $_POST['old_image'] ?? null) : ['success' => true, 'path' => $_POST['old_image'] ?? null];
        $image2 = isset($_FILES['image2']) ? uploadImage($_FILES['image2'], $_POST['old_image2'] ?? null) : ['success' => true, 'path' => $_POST['old_image2'] ?? null];
        $image3 = isset($_FILES['image3']) ? uploadImage($_FILES['image3'], $_POST['old_image3'] ?? null) : ['success' => true, 'path' => $_POST['old_image3'] ?? null];

        // Handle Gallery Images
        $gallery = [];
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT image_gallery FROM partner_package_uploads WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetchColumn();
            $gallery = $existing ? json_decode($existing, true) : [];
        }

        if (isset($_POST['remove_gallery_images']) && !empty($_POST['remove_gallery_images'])) {
            $to_remove = explode(',', $_POST['remove_gallery_images']);
            $gallery = array_values(array_filter($gallery, function ($img) use ($to_remove) {
                return !in_array($img, $to_remove);
            }));
        }

        if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
            foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery']['error'][$key] === 0) {
                    $ext = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'partner_g_' . time() . '_' . $key . '.' . $ext;
                    if (move_uploaded_file($tmp_name, __DIR__ . '/../uploads/' . $filename)) {
                        $gallery[] = 'uploads/' . $filename;
                    }
                }
            }
        }
        $image_gallery_json = json_encode($gallery);

        if ($package_name === '') {
            echo json_encode(['success' => false, 'message' => 'Please enter a package name.']);
            exit;
        }

        $partner_company = $partner_profile['company_name'] ?? 'Partner';
        $uploaded_by_name = $partner_profile['contact_person'] ?? 'Partner';
        $uploaded_by_email = $partner_profile['email'] ?? '';

        if ($id > 0) {
            // Verify ownership
            $stmt = $pdo->prepare('SELECT partner_id FROM partner_package_uploads WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Package not found.']);
                exit;
            }

            if ($existing['partner_id'] !== $partnerId) {
                echo json_encode(['success' => false, 'message' => 'You can only edit your own packages.']);
                exit;
            }

            // Update the package
            $update = $pdo->prepare('UPDATE partner_package_uploads SET 
                package_name = ?, 
                destination_name = ?, 
                destination_type = ?,
                duration = ?, 
                price = ?, 
                description = ?,
                currency = ?,
                group_size = ?,
                best_season = ?,
                activities_count = ?,
                badge_text = ?,
                display_order = ?,
                remarks = ?,
                blocked_dates = ?,
                promo_start = ?,
                promo_end = ?,
                blocked_months = ?,
                highlight_duration = ?,
                is_active = ?,
                image_path = ?,
                image2_path = ?,
                image3_path = ?,
                image_gallery = ?,
                inclusions = ?,
                exclusions = ?,
                itinerary = ?,
                hotels = ?,
                upload_status = "pending",
                updated_at = NOW() 
                WHERE id = ?');
            $update->execute([
                $package_name,
                $destination_name,
                $destination_type,
                $duration,
                $price,
                $description,
                $currency,
                $group_size,
                $best_season,
                $activities_count,
                $badge_text,
                $display_order,
                $remarks,
                $blocked_dates,
                $promo_start,
                $promo_end,
                $blocked_months,
                $highlight_duration,
                $is_active,
                $image['path'],
                $image2['path'],
                $image3['path'],
                $image_gallery_json,
                $inclusions_json,
                $exclusions_json,
                $itinerary_json,
                $hotels_json,
                $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Package updated successfully and is pending review.']);
            exit;
        } else {
            // Creating new package
            $insert = $pdo->prepare('INSERT INTO partner_package_uploads 
                (partner_id, partner_company, uploaded_by_name, uploaded_by_email, 
                 package_name, destination_name, destination_type, duration, price, description,
                 currency, group_size, best_season, activities_count, badge_text, display_order,
                 remarks, blocked_dates, promo_start, promo_end, blocked_months, highlight_duration,
                 is_active, image_path, image2_path, image3_path, image_gallery,
                 inclusions, exclusions, itinerary, hotels, upload_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $partnerId,
                $partner_company,
                $uploaded_by_name,
                $uploaded_by_email,
                $package_name,
                $destination_name,
                $destination_type,
                $duration,
                $price,
                $description,
                $currency,
                $group_size,
                $best_season,
                $activities_count,
                $badge_text,
                $display_order,
                $remarks,
                $blocked_dates,
                $promo_start,
                $promo_end,
                $blocked_months,
                $highlight_duration,
                $is_active,
                $image['path'],
                $image2['path'],
                $image3['path'],
                $image_gallery_json,
                $inclusions_json,
                $exclusions_json,
                $itinerary_json,
                $hotels_json,
                'pending'
            ]);

            echo json_encode(['success' => true, 'message' => 'Package uploaded successfully and is awaiting approval.']);
            exit;
        }
    }

    // Delete Partner Package
    elseif ($action === 'delete_partner_package') {
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid package selected.']);
            exit;
        }

        // Verify ownership
        $stmt = $pdo->prepare('SELECT partner_id, image_path, image2_path, image3_path, image_gallery FROM partner_package_uploads WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Package not found.']);
            exit;
        }

        if ($existing['partner_id'] !== $partnerId) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }

        // Delete images
        foreach (['image_path', 'image2_path', 'image3_path'] as $img_field) {
            if (!empty($existing[$img_field]) && file_exists(__DIR__ . '/../' . $existing[$img_field])) {
                unlink(__DIR__ . '/../' . $existing[$img_field]);
            }
        }

        // Delete gallery images
        if (!empty($existing['image_gallery'])) {
            $gallery = json_decode($existing['image_gallery'], true);
            if (is_array($gallery)) {
                foreach ($gallery as $img) {
                    if (file_exists(__DIR__ . '/../' . $img)) {
                        unlink(__DIR__ . '/../' . $img);
                    }
                }
            }
        }

        // Delete the package
        $pdo->prepare('DELETE FROM partner_package_uploads WHERE id = ?')->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Package deleted successfully.']);
        exit;
    }
    } catch (Throwable $e) {
        error_log('Partner content manager POST error: ' . $e->getMessage());
        sendJson(['success' => false, 'message' => 'Something went wrong while saving the package. Please refresh and try again.'], 500);
    }
}

// ==================== FETCH PARTNER PACKAGES ====================
try {
    // Get packages for this partner
    $stmt = $pdo->prepare("SELECT * FROM partner_package_uploads WHERE partner_id = ? ORDER BY created_at DESC");
    $stmt->execute([$partnerId]);
    $partner_packages = $stmt->fetchAll();
} catch (PDOException $e) {
    $partner_packages = [];
    error_log("Error fetching partner packages: " . $e->getMessage());
}

// Count packages by type
$foreign_count = 0;
$local_count = 0;
$pending_count = 0;
$approved_count = 0;

foreach ($partner_packages as $pkg) {
    if ($pkg['destination_type'] === 'foreign') $foreign_count++;
    else $local_count++;
    
    $status = strtolower($pkg['upload_status'] ?? 'pending');
    if ($status === 'approved') $approved_count++;
    else $pending_count++;
}

// Helper function to get status badge HTML
function getStatusBadge($status) {
    $status = strtolower($status ?? 'pending');
    switch ($status) {
        case 'approved':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Approved</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        default:
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
    }
}

// Helper function to get package type label
function getPackageTypeLabel($type) {
    return $type === 'foreign' ? '🌍 Foreign' : '🏖️ Local';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Packages - HeyDream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Blocked Months Grid Styling */
        .month-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 5px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .month-checkbox {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .month-checkbox input {
            cursor: pointer;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 2000;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header i {
            font-size: 2.5rem;
            color: #ffd700;
        }

        .sidebar-header h2 {
            font-size: 1.2rem;
            margin-top: 10px;
        }

        .sidebar-header p {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255, 152, 0, 0.2);
            color: #ffd700;
            border-left: 3px solid #ffd700;
        }

        .nav-item i {
            width: 24px;
        }

        .admin-main {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
        }

        .top-bar {
            background: white;
            border-radius: 12px;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 15px;
            z-index: 1000;
            border: 1px solid #f1f5f9;
        }

        .top-bar .left-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-toggle {
            display: none;
            background: #f5f7fa;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            color: #003580;
            font-size: 1.1rem;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: #e9ecef;
        }

        .top-bar h1 {
            font-size: 1.5rem;
            color: #003580;
            margin: 0;
        }

        .admin-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .back-to-dashboard {
            background: #003580;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 10px rgba(0, 53, 128, 0.15);
        }

        .back-to-dashboard:hover {
            background: #00265d;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 53, 128, 0.25);
        }

        .content-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            color: #003580;
            font-size: 1.3rem;
        }

        .section-header h2 i {
            margin-right: 10px;
            color: #ffd700;
        }

        .section-header .subtitle {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: normal;
        }

        .add-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .add-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #f8fafc;
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.2s;
        }

        .stat-card:hover {
            border-color: #003580;
            box-shadow: 0 4px 12px rgba(0, 53, 128, 0.08);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .stat-icon.total { background: rgba(0, 53, 128, 0.1); color: #003580; }
        .stat-icon.foreign { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
        .stat-icon.local { background: rgba(16, 185, 129, 0.12); color: #10b981; }
        .stat-icon.pending { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .stat-icon.approved { background: rgba(16, 185, 129, 0.15); color: #10b981; }

        .stat-info h4 {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin: 0 0 2px 0;
        }
        .stat-info p {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
            color: #64748b;
        }

        .filter-tab:hover {
            border-color: #003580;
            color: #003580;
        }

        .filter-tab.active {
            background: #003580;
            color: white;
            border-color: #003580;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }

        .content-card {
            background: #f8f9fa;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }

        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .card-preview {
            height: 150px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .card-preview .badge {
            position: absolute;
            top: 10px;
            left: 10px;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            z-index: 10;
            pointer-events: none;
        }

        .card-preview .badge.pending { background: #d97706; }
        .card-preview .badge.approved { background: #10b981; }
        .card-preview .badge.rejected { background: #dc2626; }
        .card-preview .badge.type-foreign { background: #3b82f6; }
        .card-preview .badge.type-local { background: #10b981; }

        .card-preview .package-icon {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 2.5rem;
            color: rgba(255, 255, 255, 0.3);
        }

        .card-content {
            padding: 15px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .card-meta {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 6px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .card-description {
            font-size: 0.85rem;
            color: #64748b;
            margin: 8px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-badge.status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge.status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
        }

        .edit-card-btn,
        .delete-card-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        .edit-card-btn {
            background: #ffd700;
            color: white;
        }

        .edit-card-btn:hover {
            background: #f57c00;
        }

        .delete-card-btn {
            background: #dc3545;
            color: white;
        }

        .delete-card-btn:hover {
            background: #c82333;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99999;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 900px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            animation: fadeInUp 0.3s ease;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 24px 24px 0 0;
        }

        .modal-header h3 {
            color: #003580;
            margin: 0;
        }

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            transition: 0.2s;
        }

        .close-modal:hover {
            color: #dc2626;
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
            transition: border 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #003580;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 53, 128, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .image-preview {
            width: 100%;
            height: 120px;
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            margin-top: 5px;
            border: 2px dashed #ddd;
            background-color: #f0f0f0;
        }

        .save-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            font-size: 1rem;
            transition: 0.3s;
        }

        .save-btn:hover {
            background: #218838;
        }

        .cancel-btn {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: 0.3s;
            font-size: 0.95rem;
        }

        .cancel-btn:hover {
            background: #e2e8f0;
        }

        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .message.info {
            background: #e8f0fe;
            color: #003580;
            border-left: 4px solid #ffd700;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 3.5rem;
            color: #cbd5e1;
            margin-bottom: 15px;
            display: block;
        }

        .empty-state h3 {
            color: #0f172a;
            margin-bottom: 8px;
        }

        .empty-state p {
            max-width: 400px;
            margin: 0 auto;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 25px;
            gap: 20px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .tab-btn {
            padding: 10px 5px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            position: relative;
            white-space: nowrap;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .tab-btn.active {
            color: #003580;
            font-weight: 700;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #003580;
        }

        .tab-content {
            display: none;
            padding: 5px 0;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Itinerary Builder */
        .itinerary-builder {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .itinerary-day-item {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e0e0e0;
            position: relative;
        }

        .remove-day-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .add-day-btn {
            background: #ffd700;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }

        /* SweetAlert2 Overrides */
        .swal2-container {
            z-index: 99999 !important;
            backdrop-filter: blur(4px);
        }

        .swal2-popup {
            border-radius: 24px !important;
            font-family: 'Poppins', sans-serif !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3) !important;
        }

        .swal2-title {
            font-size: 1.4rem !important;
            font-weight: 700 !important;
            color: #0f172a !important;
        }

        .swal2-styled.swal2-confirm {
            background-color: #dc2626 !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            padding: 12px 28px !important;
        }

        .swal2-styled.swal2-cancel {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            padding: 12px 28px !important;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            z-index: 1900;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Mobile Responsiveness */
        @media (max-width: 992px) {
            .admin-sidebar {
                left: -280px;
                box-shadow: 5px 0 25px rgba(0, 0, 0, 0.15);
            }

            .admin-sidebar.active {
                left: 0;
            }

            .admin-main {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .menu-toggle {
                display: flex;
            }

            .top-bar {
                padding: 12px 15px;
                border-radius: 12px;
                margin-bottom: 20px;
            }

            .top-bar h1 {
                font-size: 1.1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .add-btn {
                width: 100%;
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                max-height: 90vh;
            }

            .month-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 576px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }

            .month-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .top-bar {
                flex-direction: row;
                justify-content: space-between;
            }

            .back-to-dashboard span {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <div class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <i class="fas fa-edit"></i>
                <h2>Partner Content</h2>
                <p><?= htmlspecialchars($partner_profile['company_name'] ?? 'Partner') ?></p>
            </div>
            <div class="sidebar-nav">
                <a href="?page=foreign-destinations" class="nav-item <?= $page === 'foreign-destinations' ? 'active' : '' ?>">
                    <i class="fas fa-globe-asia"></i>
                    <span>Foreign Destinations</span>
                </a>
                <a href="?page=local-destinations" class="nav-item <?= $page === 'local-destinations' ? 'active' : '' ?>">
                    <i class="fas fa-umbrella-beach"></i>
                    <span>Local Destinations</span>
                </a>
            </div>
        </div>

        <div class="admin-main">
            <div class="top-bar">
                <div class="left-section">
                    <button class="menu-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><i class="fas fa-box"></i> My Packages</h1>
                </div>
                <div class="admin-actions">
                    <a href="partner-dashboard.php" class="back-to-dashboard">
                        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="content-section">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon total"><i class="fas fa-box"></i></div>
                        <div class="stat-info">
                            <h4>Total Packages</h4>
                            <p><?= count($partner_packages) ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon foreign"><i class="fas fa-globe-asia"></i></div>
                        <div class="stat-info">
                            <h4>Foreign</h4>
                            <p><?= $foreign_count ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon local"><i class="fas fa-umbrella-beach"></i></div>
                        <div class="stat-info">
                            <h4>Local</h4>
                            <p><?= $local_count ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                        <div class="stat-info">
                            <h4>Pending</h4>
                            <p><?= $pending_count ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info">
                            <h4>Approved</h4>
                            <p><?= $approved_count ?></p>
                        </div>
                    </div>
                </div>

                <div class="section-header">
                    <div>
                        <h2><i class="fas fa-handshake"></i> My Uploaded Packages</h2>
                        <span class="subtitle">Manage your packages. All uploads require admin approval before going live on the partners page.</span>
                    </div>
                    <button class="add-btn" onclick="openPartnerPackageModal()">
                        <i class="fas fa-plus"></i> Add New Package
                    </button>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all" onclick="filterPackages('all')">All</button>
                    <button class="filter-tab" data-filter="foreign" onclick="filterPackages('foreign')">🌍 Foreign</button>
                    <button class="filter-tab" data-filter="local" onclick="filterPackages('local')">🏖️ Local</button>
                </div>

                <div class="cards-grid" id="packagesGrid">
                    <?php if (empty($partner_packages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>No Packages Yet</h3>
                            <p>You haven't uploaded any packages yet. Click "Add New Package" to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($partner_packages as $pkg): 
                            $statusClass = strtolower($pkg['upload_status'] ?? 'pending');
                            $typeClass = $pkg['destination_type'] ?? 'foreign';
                            $preview_image = !empty($pkg['image_path']) ? '../' . $pkg['image_path'] : 'https://via.placeholder.com/300x150?text=No+Image';
                        ?>
                            <div class="content-card" data-type="<?= $typeClass ?>">
                                <div class="card-preview" style="background-image: url('<?= $preview_image ?>');">
                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($statusClass) ?></span>
                                    <span class="badge type-<?= $typeClass ?>" style="left: auto; right: 10px; background: <?= $typeClass === 'foreign' ? '#3b82f6' : '#10b981' ?>;">
                                        <?= $typeClass === 'foreign' ? '🌍 Foreign' : '🏖️ Local' ?>
                                    </span>
                                    <i class="fas fa-box package-icon"></i>
                                </div>
                                <div class="card-content">
                                    <h3 class="card-title"><?= htmlspecialchars($pkg['package_name']) ?></h3>
                                    <div class="card-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pkg['destination_name'] ?: 'Destination not specified') ?></span>
                                    </div>
                                    <div class="card-meta">
                                        <span><i class="fas fa-clock"></i> <?= htmlspecialchars($pkg['duration'] ?: 'Flexible') ?></span>
                                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($pkg['currency'] ?? '₱') ?><?= number_format((float)$pkg['price'], 2) ?></span>
                                    </div>
                                    <?php if (!empty($pkg['description'])): ?>
                                        <div class="card-description"><?= htmlspecialchars($pkg['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="card-meta">
                                        <span><i class="far fa-calendar-alt"></i> Uploaded: <?= date('M d, Y', strtotime($pkg['created_at'])) ?></span>
                                        <?= getStatusBadge($pkg['upload_status']) ?>
                                    </div>
                                    <div class="card-actions">
                                        <button class="edit-card-btn" onclick='openPartnerPackageModal(<?= json_encode($pkg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="delete-card-btn" onclick='deletePackage(<?= (int)$pkg['id'] ?>, <?= json_encode($pkg['package_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Partner Package Modal -->
    <div id="partnerPackageModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 id="partnerPackageModalTitle">Add New Package</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab(event, 'tab-general')">General Info</button>
                    <button class="tab-btn" onclick="switchTab(event, 'tab-pricing')">Pricing & Schedule</button>
                    <button class="tab-btn" onclick="switchTab(event, 'tab-details')">Itinerary & Details</button>
                    <button class="tab-btn" onclick="switchTab(event, 'tab-gallery')">Gallery</button>
                </div>

                <form method="POST" enctype="multipart/form-data" id="partnerPackageForm">
                    <input type="hidden" name="action" value="save_partner_package">
                    <input type="hidden" name="id" id="package_id" value="0">
                    <input type="hidden" name="old_image" id="old_image">
                    <input type="hidden" name="old_image2" id="old_image2">
                    <input type="hidden" name="old_image3" id="old_image3">
                    <input type="hidden" name="remove_gallery_images" id="remove_gallery_images" value="">

                    <!-- General Info Tab -->
                    <div id="tab-general" class="tab-content active">
                        <div class="form-group">
                            <label>Package Name *</label>
                            <input type="text" name="package_name" id="package_name" required placeholder="e.g., Boracay Beach Getaway">
                        </div>

                        <div class="form-group">
                            <label>Destination Name</label>
                            <input type="text" name="destination_name" id="destination_name" placeholder="e.g., Boracay, Philippines">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Destination Type *</label>
                                <select name="destination_type" id="destination_type" required>
                                    <option value="foreign">🌍 Foreign Destination</option>
                                    <option value="local">🏖️ Local Destination</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Duration</label>
                                <input type="text" name="duration" id="duration" placeholder="e.g., 4D/3N">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="description" rows="4" placeholder="Describe what this package includes..."></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Badge Text</label>
                                <input type="text" name="badge_text" id="badge_text" placeholder="e.g., ✨ Best Seller">
                            </div>
                            <div class="form-group">
                                <label>Display Order</label>
                                <input type="number" name="display_order" id="display_order" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Schedule Tab -->
                    <div id="tab-pricing" class="tab-content">
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label>Currency</label>
                                <select name="currency" id="currency" required>
                                    <option value="₱">₱ PHP</option>
                                    <option value="$">$ USD</option>
                                    <option value="€">€ EUR</option>
                                    <option value="£">£ GBP</option>
                                    <option value="¥">¥ JPY/CNY</option>
                                    <option value="₩">₩ KRW</option>
                                    <option value="SG$">SG$ SGD</option>
                                    <option value="RM">RM MYR</option>
                                    <option value="฿">฿ THB</option>
                                    <option value="A$">A$ AUD</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Price *</label>
                                <input type="number" name="price" id="price" step="0.01" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Group Size</label>
                                <input type="text" name="group_size" id="group_size" placeholder="2-15 pax">
                            </div>
                            <div class="form-group">
                                <label>Activities Count</label>
                                <input type="number" name="activities_count" id="activities_count" value="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Best Season / Validity</label>
                                <input type="text" name="best_season" id="best_season" placeholder="Year Round">
                            </div>
                            <div class="form-group">
                                <label>Blocked Dates</label>
                                <input type="text" name="blocked_dates" id="blocked_dates" class="blocked-dates-picker" placeholder="Select dates...">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Promo Start Date</label>
                                <input type="date" name="promo_start" id="promo_start">
                            </div>
                            <div class="form-group">
                                <label>Promo End Date</label>
                                <input type="date" name="promo_end" id="promo_end">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Highlight Duration (Days)</label>
                            <input type="number" name="highlight_duration" id="highlight_duration" value="1" min="1">
                        </div>

                        <div class="form-group">
                            <label>Blocked Months (Unclickable)</label>
                            <div class="month-grid">
                                <?php $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; ?>
                                <?php foreach ($months as $i => $m): ?>
                                    <label class="month-checkbox">
                                        <input type="checkbox" name="blocked_months[]" value="<?= $i + 1 ?>" class="month-check">
                                        <?= $m ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <label>
                                    <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                                    Active (show on website)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Itinerary & Details Tab -->
                    <div id="tab-details" class="tab-content">
                        <!-- Inclusions Section -->
                        <div class="form-group">
                            <label>Package Inclusions (one per line)</label>
                            <textarea name="inclusions" id="inclusions" rows="4" 
                                placeholder="Hotel accommodation&#10;Daily breakfast&#10;Tours included"></textarea>
                        </div>

                        <!-- Hotel Management -->
                        <div class="form-group">
                            <label>Hotel Options (Surcharges)</label>
                            <div id="hotelBuilderContainer"></div>
                            <button type="button" class="add-day-btn" style="background: #4caf50;" onclick="addHotel()">
                                <i class="fas fa-plus"></i> Add Hotel Option
                            </button>
                            <input type="hidden" name="hotels" id="hotels_json" value="[]">
                            <small>Add hotels users can choose from. Use price 0 for the default hotel.</small>
                        </div>

                        <!-- Exclusions Section -->
                        <div class="form-group">
                            <label>Package Exclusions (one per line)</label>
                            <textarea name="exclusions" id="exclusions" rows="3" 
                                placeholder="Airfare&#10;Personal expenses"></textarea>
                        </div>

                        <!-- Itinerary Builder -->
                        <div class="form-group">
                            <label>Tour Itinerary</label>
                            <div id="itineraryBuilder"></div>
                            <button type="button" class="add-day-btn" onclick="addItineraryDay()">
                                <i class="fas fa-plus"></i> Add Day
                            </button>
                            <input type="hidden" name="itinerary" id="itinerary_data" value="[]">
                        </div>

                        <div class="form-group">
                            <label>Remarks and Note</label>
                            <textarea name="remarks" id="remarks" rows="4" 
                                placeholder="Important notes about the itinerary, requirements, or special conditions..."></textarea>
                        </div>
                    </div>

                    <!-- Gallery Tab -->
                    <div id="tab-gallery" class="tab-content">
                        <div class="form-group">
                            <label>Main Images (Cover Slides)</label>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                <div>
                                    <label style="font-size: 0.8rem;">Image 1 (Main)</label>
                                    <input type="file" name="image" onchange="previewImage(this, 'preview_1')">
                                    <div id="preview_1" class="image-preview"></div>
                                </div>
                                <div>
                                    <label style="font-size: 0.8rem;">Image 2 (Optional)</label>
                                    <input type="file" name="image2" onchange="previewImage(this, 'preview_2')">
                                    <div id="preview_2" class="image-preview"></div>
                                </div>
                                <div>
                                    <label style="font-size: 0.8rem;">Image 3 (Optional)</label>
                                    <input type="file" name="image3" onchange="previewImage(this, 'preview_3')">
                                    <div id="preview_3" class="image-preview"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 25px;">
                            <label>Full Photo Gallery (Multi-upload)</label>
                            <input type="file" name="gallery[]" id="gallery_input" multiple accept="image/*" onchange="previewGallery(this)">
                            <div id="gallery_preview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px;"></div>
                        </div>
                    </div>

                    <div style="position: sticky; bottom: -20px; background: white; padding: 20px 0; border-top: 1px solid #eee; margin-top: 20px; z-index: 10;">
                        <div class="form-group" style="background: #f8fafc; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px;">
                            <p style="font-size: 0.8rem; color: #64748b; margin: 0;">
                                <i class="fas fa-info-circle"></i> 
                                Your package will be submitted for admin review. Once approved, it will appear on the public partners page.
                            </p>
                        </div>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Package</button>
                        <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ========== MODAL FUNCTIONS ==========
        function openPartnerPackageModal(pkg = null) {
            const isEdit = !!(pkg && pkg.id);
            document.getElementById('partnerPackageModalTitle').innerText = isEdit ? 'Edit Package' : 'Add New Package';
            document.getElementById('package_id').value = pkg?.id || 0;
            document.getElementById('package_name').value = pkg?.package_name || '';
            document.getElementById('destination_name').value = pkg?.destination_name || '';
            document.getElementById('destination_type').value = pkg?.destination_type || 'foreign';
            document.getElementById('duration').value = pkg?.duration || '';
            document.getElementById('price').value = pkg?.price || '';
            document.getElementById('description').value = pkg?.description || '';
            document.getElementById('currency').value = pkg?.currency || '₱';
            document.getElementById('group_size').value = pkg?.group_size || '';
            document.getElementById('best_season').value = pkg?.best_season || '';
            document.getElementById('activities_count').value = pkg?.activities_count || 0;
            document.getElementById('badge_text').value = pkg?.badge_text || '';
            document.getElementById('display_order').value = pkg?.display_order || 0;
            document.getElementById('remarks').value = pkg?.remarks || '';
            document.getElementById('blocked_dates').value = pkg?.blocked_dates || '';
            document.getElementById('promo_start').value = pkg?.promo_start || '';
            document.getElementById('promo_end').value = pkg?.promo_end || '';
            document.getElementById('highlight_duration').value = pkg?.highlight_duration || 1;
            document.getElementById('is_active').checked = pkg?.is_active == 1;

            // Set blocked months
            if (pkg?.blocked_months) {
                const months = pkg.blocked_months.split(',');
                document.querySelectorAll('.month-check').forEach(cb => {
                    cb.checked = months.includes(cb.value);
                });
            } else {
                document.querySelectorAll('.month-check').forEach(cb => cb.checked = false);
            }

            // Set inclusions
            let inclusionsText = '';
            if (pkg?.inclusions) {
                try {
                    const inclusions = JSON.parse(pkg.inclusions);
                    inclusionsText = Array.isArray(inclusions) ? inclusions.join('\n') : pkg.inclusions;
                } catch (e) {
                    inclusionsText = pkg.inclusions;
                }
            }
            document.getElementById('inclusions').value = inclusionsText;

            // Set exclusions
            let exclusionsText = '';
            if (pkg?.exclusions) {
                try {
                    const exclusions = JSON.parse(pkg.exclusions);
                    exclusionsText = Array.isArray(exclusions) ? exclusions.join('\n') : pkg.exclusions;
                } catch (e) {
                    exclusionsText = pkg.exclusions;
                }
            }
            document.getElementById('exclusions').value = exclusionsText;

            // Set itinerary
            itineraryDays = [];
            if (pkg?.itinerary) {
                try {
                    itineraryDays = typeof pkg.itinerary === 'string' ? JSON.parse(pkg.itinerary) : pkg.itinerary;
                } catch (e) { console.error("Error parsing itinerary", e); }
            }
            renderItineraryBuilder();

            // Set hotels
            hotels = [];
            if (pkg?.hotels) {
                try {
                    hotels = typeof pkg.hotels === 'string' ? JSON.parse(pkg.hotels) : pkg.hotels;
                } catch (e) { console.error("Error parsing hotels", e); }
            }
            renderHotelBuilder();

            // Set images
            if (pkg?.image_path) {
                document.getElementById('preview_1').style.backgroundImage = `url('../${pkg.image_path}')`;
                document.getElementById('old_image').value = pkg.image_path;
            }
            if (pkg?.image2_path) {
                document.getElementById('preview_2').style.backgroundImage = `url('../${pkg.image2_path}')`;
                document.getElementById('old_image2').value = pkg.image2_path;
            }
            if (pkg?.image3_path) {
                document.getElementById('preview_3').style.backgroundImage = `url('../${pkg.image3_path}')`;
                document.getElementById('old_image3').value = pkg.image3_path;
            }

            // Load Gallery
            galleryFiles = [];
            savedGallery = [];
            document.getElementById('remove_gallery_images').value = '';
            if (pkg?.image_gallery) {
                try {
                    savedGallery = JSON.parse(pkg.image_gallery) || [];
                } catch (e) { console.error("Error parsing gallery", e); }
            }
            renderGalleryPreview();

            // Reset tabs
            const firstTab = document.querySelector('#partnerPackageModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('partnerPackageModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('partnerPackageModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal on overlay click
        document.getElementById('partnerPackageModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ========== TAB SWITCHING ==========
        function switchTab(evt, tabId) {
            const contents = document.querySelectorAll('#partnerPackageModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#partnerPackageModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        // ========== ITINERARY BUILDER ==========
        let itineraryDays = [];

        function renderItineraryBuilder() {
            const container = document.getElementById('itineraryBuilder');
            if (!container) return;

            if (itineraryDays.length === 0) {
                container.innerHTML = '<div class="message info" style="background: #f8f9fa;">No itinerary days added. Click "Add Day" to create your itinerary.</div>';
                return;
            }

            let html = '<div class="itinerary-builder">';
            itineraryDays.forEach((day, index) => {
                const activitiesText = Array.isArray(day.activities) ? day.activities.join('\n') : (day.activities || '');
                html += `
                    <div class="itinerary-day-item" data-index="${index}">
                        <button type="button" class="remove-day-btn" onclick="removeItineraryDay(${index})">&times;</button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Day Number</label>
                                <input type="number" value="${day.day || index + 1}" onchange="updateItineraryDay(${index}, 'day', this.value)">
                            </div>
                            <div class="form-group">
                                <label>Day Title</label>
                                <input type="text" value="${escapeHtml(day.title || `Day ${index + 1}`)}" onchange="updateItineraryDay(${index}, 'title', this.value)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Activities (one per line)</label>
                            <textarea rows="4" onchange="updateItineraryDay(${index}, 'activities', this.value)">${escapeHtml(activitiesText)}</textarea>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function addItineraryDay() {
            const newDayNumber = itineraryDays.length + 1;
            itineraryDays.push({
                day: newDayNumber,
                title: `Day ${newDayNumber}`,
                activities: []
            });
            renderItineraryBuilder();
        }

        function removeItineraryDay(index) {
            itineraryDays.splice(index, 1);
            itineraryDays.forEach((day, i) => {
                day.day = i + 1;
                day.title = `Day ${i + 1}`;
            });
            renderItineraryBuilder();
        }

        function updateItineraryDay(index, field, value) {
            if (field === 'day') {
                itineraryDays[index].day = parseInt(value);
            } else if (field === 'title') {
                itineraryDays[index].title = value;
            } else if (field === 'activities') {
                const activities = value.split('\n').filter(a => a.trim());
                itineraryDays[index].activities = activities;
            }
        }

        // ========== HOTEL BUILDER ==========
        let hotels = [];

        function renderHotelBuilder() {
            const container = document.getElementById('hotelBuilderContainer');
            if (!container) return;

            document.getElementById('hotels_json').value = JSON.stringify(hotels);

            let html = '<div class="itinerary-days-list">';
            hotels.forEach((hotel, index) => {
                html += `
                    <div class="itinerary-day-item" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 15px;">
                        <div class="day-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight:700; color:#003580; display: flex; align-items: center; gap: 8px; font-size: 0.95rem;">
                                <i class="fas fa-hotel" style="color: #64748b;"></i> Hotel Option #${index + 1}
                            </span>
                            <button type="button" class="btn-remove-day" onclick="removeHotel(${index})" style="background: #fee2e2; color: #dc2626; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 700; transition: all 0.2s; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-trash-alt"></i> REMOVE
                            </button>
                        </div>
                        <div class="day-content" style="padding: 15px; background: #fff;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; font-size: 0.85rem;">Hotel Name/Type</label>
                                    <input type="text" value="${escapeHtml(hotel.name)}" 
                                           onchange="updateHotel(${index}, 'name', this.value)" 
                                           placeholder="e.g. Standard 3-Star Hotel"
                                           style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
                                </div>
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; font-size: 0.85rem;">Price Surcharge (Add to base price)</label>
                                    <input type="number" value="${hotel.price || 0}" 
                                           onchange="updateHotel(${index}, 'price', this.value)" 
                                           placeholder="0 if this is the base hotel"
                                           style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            if (hotels.length === 0) {
                html = '<div style="padding:20px; text-align:center; color:#888; background:#f9f9f9; border-radius:8px; border:1px dashed #ddd; margin-bottom:15px;">No hotel options added yet. Click "Add Hotel Option" to begin.</div>';
            }

            container.innerHTML = html;
        }

        function addHotel() {
            hotels.push({ name: '', price: 0 });
            renderHotelBuilder();
        }

        function removeHotel(index) {
            hotels.splice(index, 1);
            renderHotelBuilder();
        }

        function updateHotel(index, field, value) {
            if (field === 'price') {
                hotels[index][field] = parseFloat(value) || 0;
            } else {
                hotels[index][field] = value;
            }
            document.getElementById('hotels_json').value = JSON.stringify(hotels);
        }

        // ========== GALLERY FUNCTIONS ==========
        let galleryFiles = [];
        let savedGallery = [];

        function renderGalleryPreview() {
            const preview = document.getElementById('gallery_preview');
            if (!preview) return;
            preview.innerHTML = '';

            savedGallery.forEach(img => {
                const div = document.createElement('div');
                div.style.height = '100px';
                div.style.borderRadius = '8px';
                div.style.backgroundImage = `url('../${img}')`;
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.style.position = 'relative';
                div.style.border = '1px solid #ddd';
                div.innerHTML = `<button type="button" onclick="removeGalleryImage('${img}', this)" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });

            galleryFiles.forEach((file, index) => {
                const reader = new FileReader();
                const div = document.createElement('div');
                div.style.height = '100px';
                div.style.borderRadius = '8px';
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.style.position = 'relative';
                div.style.border = '2px dashed #3b82f6';

                reader.onload = function(e) {
                    div.style.backgroundImage = `url(${e.target.result})`;
                };
                reader.readAsDataURL(file);

                div.innerHTML = `<button type="button" onclick="removeNewGalleryFile(${index})" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });
        }

        function removeNewGalleryFile(index) {
            galleryFiles.splice(index, 1);
            renderGalleryPreview();
        }

        function previewGallery(input) {
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    galleryFiles.push(file);
                });
                input.value = '';
            }
            renderGalleryPreview();
        }

        function removeGalleryImage(path, btn) {
            Swal.fire({
                title: 'Remove Image?',
                text: 'Are you sure you want to remove this image from the gallery?',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const removeInput = document.getElementById('remove_gallery_images');
                    let current = removeInput.value ? removeInput.value.split(',') : [];
                    current.push(path);
                    removeInput.value = current.join(',');
                    savedGallery = savedGallery.filter(img => img !== path);
                    renderGalleryPreview();
                }
            });
        }

        // ========== UTILITY FUNCTIONS ==========
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById(previewId);
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                    preview.style.backgroundSize = 'cover';
                    preview.style.backgroundPosition = 'center';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ========== DELETE FUNCTION ==========
        function deletePackage(id, name) {
            Swal.fire({
                title: 'Delete Package?',
                text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_partner_package');
                    formData.append('id', id);

                    Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                    fetch('partner-content-manager.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(json => {
                            Swal.close();
                            if (json.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: json.message || 'Package deleted successfully.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire('Error', json.message || 'Unable to delete package.', 'error');
                            }
                        })
                        .catch(err => {
                            Swal.close();
                            Swal.fire('Error', 'Network error: ' + err.message, 'error');
                        });
                }
            });
        }

        // ========== FILTER PACKAGES ==========
        function filterPackages(type) {
            const cards = document.querySelectorAll('.content-card');
            const tabs = document.querySelectorAll('.filter-tab');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            document.querySelector(`.filter-tab[data-filter="${type}"]`)?.classList.add('active');

            cards.forEach(card => {
                if (type === 'all') {
                    card.style.display = '';
                } else {
                    card.style.display = card.dataset.type === type ? '' : 'none';
                }
            });
        }

        // ========== FORM SUBMIT HANDLER ==========
        document.getElementById('partnerPackageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();

            document.getElementById('itinerary_data').value = JSON.stringify(itineraryDays);
            document.getElementById('hotels_json').value = JSON.stringify(hotels);

            const form = this;
            const formData = new FormData(form);

            formData.delete('gallery[]');
            galleryFiles.forEach(file => {
                formData.append('gallery[]', file);
            });

            const isEdit = formData.get('id') && parseInt(formData.get('id')) > 0;

            Swal.fire({ 
                title: isEdit ? 'Updating...' : 'Uploading...', 
                allowOutsideClick: false, 
                didOpen: () => { Swal.showLoading(); } 
            });

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(json => {
                    Swal.close();
                    if (json.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: json.message || 'Package saved successfully.',
                            timer: 2500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', json.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
        });

        // ========== SIDEBAR TOGGLE ==========
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const adminSidebar = document.getElementById('adminSidebar');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                adminSidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                adminSidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }

        // Initialize Flatpickr for blocked dates
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".blocked-dates-picker", {
                mode: "multiple",
                dateFormat: "Y-m-d",
                minDate: "today"
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body>

</html>
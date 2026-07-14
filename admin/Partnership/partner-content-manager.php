<?php
// File: admin/content-manager.php
// User-Friendly Content Manager with Full Package Management
// Now supports auto-save draft functionality
// Foreign destinations now fully editable from database

// Suppress errors from appearing in output (especially important for JSON responses)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$database_path = dirname(__DIR__, 2) . '/config/database.php';
if (!file_exists($database_path)) {
    $database_path = __DIR__ . '/../config/database.php';
}
require_once $database_path;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header for all responses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
}

// Restrict this manager to approved partnership accounts only.
if (!isset($_SESSION['partner_id']) || empty($_SESSION['partner_id'])) {
    header('Location: partner-login.php');
    exit;
}

$partnerId = (int)$_SESSION['partner_id'];
$partnerCompany = trim($_SESSION['partner_company'] ?? '');

$stmt = $pdo->prepare("SELECT id, company_name, status FROM partner_applications WHERE id = ? LIMIT 1");
$stmt->execute([$partnerId]);
$partnerAccount = $stmt->fetch();

if (!$partnerAccount || $partnerAccount['status'] !== 'approved') {
    header('Location: partner-login.php');
    exit;
}

if ($partnerCompany === '') {
    $partnerCompany = $partnerAccount['company_name'] ?? '';
}

$page = $_GET['page'] ?? 'flash-deals';
$message = '';
$error = '';

// Create uploads directory if it doesn't exist
$upload_dir = dirname(__DIR__, 2) . '/uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
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
            if ($old_image && file_exists(dirname(__DIR__, 2) . '/' . $old_image)) {
                unlink(dirname(__DIR__, 2) . '/' . $old_image);
            }
            return ['success' => true, 'path' => 'uploads/' . $filename];
        }
    }
    return ['success' => true, 'path' => $old_image];
}

// Shared validation for raw upload blocks (gallery loops, etc.) that don't go through uploadImage()
function isAllowedUploadImage($fileType, $fileSize)
{
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    return in_array($fileType, $allowed_types) && $fileSize <= $max_size;
}

function assetUrl($path)
{
    if (!$path) {
        return '';
    }

    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '//') === 0) {
        return $path;
    }

    if (strpos($path, '../') === 0 || strpos($path, './') === 0) {
        return $path;
    }

    return '../../' . ltrim($path, '/');
}

function ensurePartnerOwnership($pdo, $table, $id, $partnerId)
{
    if ($id <= 0) {
        return true;
    }

    $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ? AND partner_id = ? LIMIT 1");
    $stmt->execute([$id, $partnerId]);
    return (bool) $stmt->fetchColumn();
}

// Self-healing schema check for the `cruises` table: save_advanced_cruise writes to
// every column below, so any environment whose `cruises` table predates one of these
// fields (e.g. a production database that was only ever seeded from an older version of
// this codebase) would throw an uncaught "Unknown column" PDOException -- which, with
// display_errors off in production, comes back to the browser as a completely empty
// response ("Unexpected end of JSON input"), instead of a real error message. Checking
// each column independently (rather than one ALTER TABLE per statement in a shared
// try/catch) also avoids the previous bug where one column already existing would abort
// the ALTER for the next column in the same try block.
function ensureCruiseColumns($pdo)
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $columns = [
        'partner_id' => 'INT DEFAULT NULL',
        'partner_company' => 'VARCHAR(255) DEFAULT NULL',
        'cruise_code' => 'VARCHAR(100) DEFAULT NULL',
        'title' => 'VARCHAR(255) DEFAULT NULL',
        'short_description' => 'TEXT',
        'full_description' => 'TEXT',
        'duration' => 'VARCHAR(100) DEFAULT NULL',
        'departure_port' => 'VARCHAR(255) DEFAULT NULL',
        'destinations' => 'TEXT',
        'route' => 'TEXT',
        'ship_name' => 'VARCHAR(255) DEFAULT NULL',
        'cruise_line' => 'VARCHAR(255) DEFAULT NULL',
        'room_types' => 'TEXT',
        'amenities' => 'TEXT',
        'ship_description' => 'TEXT',
        'base_price' => 'DECIMAL(10,2) DEFAULT 0',
        'price_per_person' => 'DECIMAL(10,2) DEFAULT 0',
        'promo_price' => 'DECIMAL(10,2) DEFAULT 0',
        'inclusions' => 'TEXT',
        'exclusions' => 'TEXT',
        'departure_date' => 'DATE DEFAULT NULL',
        'return_date' => 'DATE DEFAULT NULL',
        'booking_deadline' => 'DATE DEFAULT NULL',
        'available_slots' => 'INT DEFAULT 0',
        'status' => "VARCHAR(50) DEFAULT 'Available'",
        'required_documents' => 'TEXT',
        'travel_requirements' => 'TEXT',
        'health_requirements' => 'TEXT',
        'cancellation_policy' => 'TEXT',
        'refund_policy' => 'TEXT',
        'terms_conditions' => 'TEXT',
        'category' => 'VARCHAR(100) DEFAULT NULL',
        'destination_type' => 'VARCHAR(100) DEFAULT NULL',
        'tags' => 'VARCHAR(255) DEFAULT NULL',
        'highlights' => 'TEXT',
        'promo_text' => 'VARCHAR(255) DEFAULT NULL',
        'is_published' => 'TINYINT(1) DEFAULT 0',
        'is_featured' => 'TINYINT(1) DEFAULT 0',
        'featured_image' => 'VARCHAR(500) DEFAULT NULL',
        'gallery' => 'TEXT',
        'rating' => "DECIMAL(2,1) DEFAULT 0.0",
        'feedback_count' => 'INT DEFAULT 0',
    ];

    try {
        $existing = [];
        foreach ($pdo->query("SHOW COLUMNS FROM cruises") as $row) {
            $existing[$row['Field']] = true;
        }
        foreach ($columns as $column => $definition) {
            if (!isset($existing[$column])) {
                try {
                    $pdo->exec("ALTER TABLE cruises ADD COLUMN `$column` $definition");
                } catch (PDOException $e) {
                    // Leave it -- the save handler's own try/catch will surface a real
                    // error message instead of a blank response if this column is
                    // actually needed and still missing.
                }
            }
        }
        $checked = true;
    } catch (PDOException $e) {
        // cruises table itself doesn't exist yet; nothing to heal.
    }
}

// ==================== PACKAGE TABLES SETUP ====================
try {
    // Create foreign_destinations table with all fields needed for foreign-packages.js
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS foreign_destinations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dest_key VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            country VARCHAR(100),
            city VARCHAR(100),
            location VARCHAR(200),
            description TEXT,
            short_description VARCHAR(255),
            price DECIMAL(10,2) DEFAULT 0,
            currency VARCHAR(10) DEFAULT '₱',
            duration VARCHAR(50) DEFAULT '4D/3N',
            activities_count INT DEFAULT 0,
            group_size VARCHAR(50) DEFAULT '2-15 pax',
            best_season VARCHAR(100) DEFAULT 'Year Round',
            itinerary TEXT,
            inclusions TEXT,
            exclusions TEXT,
            hotels TEXT,
            remarks TEXT,
            blocked_dates TEXT,
            promo_start DATE,
            promo_end DATE,
            blocked_months TEXT,
            highlight_duration INT DEFAULT 1,
            image_path VARCHAR(500),
            image2_path VARCHAR(500),
            image3_path VARCHAR(500),
            image_gallery TEXT,
            collage_type VARCHAR(20) DEFAULT 'three',
            category VARCHAR(100) DEFAULT 'asia',
            badge_text VARCHAR(100),
            display_order INT DEFAULT 0,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (dest_key),
            INDEX idx_name (name),
            INDEX idx_is_active (is_active)
        )
    ");

    // Create site_services table for Cruises, Flight Packages, Premium Services, and Experiences
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_type ENUM('cruise', 'flight', 'premium', 'experience') NOT NULL,
            title VARCHAR(200) NOT NULL,
            service_code VARCHAR(50),
            category VARCHAR(100),
            tags VARCHAR(255),
            badge_text VARCHAR(100),
            description TEXT,
            full_description TEXT,
            highlights TEXT,
            inclusions TEXT,
            exclusions TEXT,
            required_documents TEXT,
            travel_requirements TEXT,
            cancellation_policy TEXT,
            terms_conditions TEXT,
            icon_class VARCHAR(100) DEFAULT 'fas fa-ship',
            featured_image VARCHAR(500),
            image_gallery TEXT,
            price DECIMAL(10,2) DEFAULT 0,
            currency VARCHAR(10) DEFAULT '₱',
            duration VARCHAR(100),
            available_slots INT DEFAULT 0,
            booking_deadline DATE,
            departure_date DATE,
            return_date DATE,
            status_text VARCHAR(50) DEFAULT 'Available',
            is_active TINYINT DEFAULT 1,
            is_featured TINYINT DEFAULT 0,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_service_type (service_type),
            INDEX idx_is_active (is_active)
        )
    ");

    // Create service_itinerary table for step-by-step details
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_itinerary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            day_number INT NOT NULL,
            title VARCHAR(200),
            description TEXT,
            FOREIGN KEY (service_id) REFERENCES site_services(id) ON DELETE CASCADE
        )
    ");

    // Ensure site_services has all columns and correct enum
    $ss_stmt = $pdo->query("SHOW COLUMNS FROM site_services");
    $ss_columns = $ss_stmt->fetchAll(PDO::FETCH_ASSOC);
    $existing_cols = array_column($ss_columns, 'Field');

    // Add missing columns
    $ss_cols_to_add = [
        'full_description' => "ALTER TABLE site_services ADD COLUMN full_description TEXT AFTER description",
        'highlights' => "ALTER TABLE site_services ADD COLUMN highlights TEXT AFTER full_description",
        'inclusions' => "ALTER TABLE site_services ADD COLUMN inclusions TEXT AFTER highlights",
        'exclusions' => "ALTER TABLE site_services ADD COLUMN exclusions TEXT AFTER inclusions",
        'featured_image' => "ALTER TABLE site_services ADD COLUMN featured_image VARCHAR(500) AFTER icon_class",
        'image_gallery' => "ALTER TABLE site_services ADD COLUMN image_gallery TEXT AFTER featured_image",
        'amenities' => "ALTER TABLE site_services ADD COLUMN amenities TEXT AFTER image_gallery",
        'service_code' => "ALTER TABLE site_services ADD COLUMN service_code VARCHAR(50) AFTER title",
        'category' => "ALTER TABLE site_services ADD COLUMN category VARCHAR(100) AFTER service_code",
        'tags' => "ALTER TABLE site_services ADD COLUMN tags VARCHAR(255) AFTER category",
        'available_slots' => "ALTER TABLE site_services ADD COLUMN available_slots INT DEFAULT 0 AFTER duration",
        'booking_deadline' => "ALTER TABLE site_services ADD COLUMN booking_deadline DATE AFTER available_slots",
        'departure_date' => "ALTER TABLE site_services ADD COLUMN departure_date DATE AFTER booking_deadline",
        'return_date' => "ALTER TABLE site_services ADD COLUMN return_date DATE AFTER departure_date",
        'status_text' => "ALTER TABLE site_services ADD COLUMN status_text VARCHAR(50) DEFAULT 'Available' AFTER return_date",
        'required_documents' => "ALTER TABLE site_services ADD COLUMN required_documents TEXT AFTER exclusions",
        'travel_requirements' => "ALTER TABLE site_services ADD COLUMN travel_requirements TEXT AFTER required_documents",
        'cancellation_policy' => "ALTER TABLE site_services ADD COLUMN cancellation_policy TEXT AFTER travel_requirements",
        'terms_conditions' => "ALTER TABLE site_services ADD COLUMN terms_conditions TEXT AFTER cancellation_policy",
        'is_featured' => "ALTER TABLE site_services ADD COLUMN is_featured TINYINT DEFAULT 0 AFTER is_active"
    ];
    foreach ($ss_cols_to_add as $col => $sql) {
        if (!in_array($col, $existing_cols)) {
            $pdo->exec($sql);
        }
    }

    try {
        $pdo->exec("ALTER TABLE site_services ADD COLUMN partner_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE site_services ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
    }

    // Update ENUM if 'experience' or 'premium' is missing
    $type_col = array_filter($ss_columns, function ($c) {
        return $c['Field'] === 'service_type';
    });
    $type_col = array_shift($type_col);
    if ($type_col && (strpos($type_col['Type'], "'experience'") === false || strpos($type_col['Type'], "'premium'") === false)) {
        $pdo->exec("ALTER TABLE site_services MODIFY COLUMN service_type ENUM('cruise', 'flight', 'premium', 'experience') NOT NULL");
    }

    // Add short_description column to site_services if missing
    if (!in_array('short_description', $existing_cols)) {
        $pdo->exec("ALTER TABLE site_services ADD COLUMN short_description VARCHAR(255) AFTER title");
    }

    // Check if foreign_destinations table has all required columns and add missing ones
    $foreign_stmt = $pdo->query("SHOW COLUMNS FROM foreign_destinations");
    $foreign_columns = $foreign_stmt->fetchAll(PDO::FETCH_COLUMN);

    $foreign_columns_to_add = [
        'dest_key' => "ALTER TABLE foreign_destinations ADD COLUMN dest_key VARCHAR(50) NOT NULL DEFAULT ''",
        'image2_path' => "ALTER TABLE foreign_destinations ADD COLUMN image2_path VARCHAR(500)",
        'image3_path' => "ALTER TABLE foreign_destinations ADD COLUMN image3_path VARCHAR(500)",
        'image_gallery' => "ALTER TABLE foreign_destinations ADD COLUMN image_gallery TEXT",
        'collage_type' => "ALTER TABLE foreign_destinations ADD COLUMN collage_type VARCHAR(20) DEFAULT 'three'",
        'location' => "ALTER TABLE foreign_destinations ADD COLUMN location VARCHAR(200)",
        'currency' => "ALTER TABLE foreign_destinations ADD COLUMN currency VARCHAR(10) DEFAULT '₱'",
        'hotels' => "ALTER TABLE foreign_destinations ADD COLUMN hotels TEXT",
        'remarks' => "ALTER TABLE foreign_destinations ADD COLUMN remarks TEXT",
        'blocked_dates' => "ALTER TABLE foreign_destinations ADD COLUMN blocked_dates TEXT",
        'promo_start' => "ALTER TABLE foreign_destinations ADD COLUMN promo_start DATE",
        'promo_end' => "ALTER TABLE foreign_destinations ADD COLUMN promo_end DATE",
        'blocked_months' => "ALTER TABLE foreign_destinations ADD COLUMN blocked_months TEXT",
        'highlight_duration' => "ALTER TABLE foreign_destinations ADD COLUMN highlight_duration INT DEFAULT 1"
    ];

    foreach ($foreign_columns_to_add as $col_name => $sql) {
        if (!in_array($col_name, $foreign_columns)) {
            $pdo->exec($sql);
        }
    }

    try {
        $pdo->exec("ALTER TABLE foreign_destinations ADD COLUMN partner_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE foreign_destinations ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
    }

    // Ensure packages table exists for package save/edit flows in the content manager.
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        destination_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        duration VARCHAR(50) DEFAULT '',
        price DECIMAL(10,2) DEFAULT 0,
        activities_count INT DEFAULT 0,
        uploaded_by INT DEFAULT NULL,
        uploaded_by_name VARCHAR(255) DEFAULT NULL,
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_packages_destination (destination_id),
        INDEX idx_packages_active (is_active),
        INDEX idx_packages_uploader (uploaded_by)
    )");

    // Also ensure destinations table exists for local destinations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS destinations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(20) DEFAULT 'local',
            country VARCHAR(100),
            city VARCHAR(100),
            location_name VARCHAR(100),
            description TEXT,
            short_description TEXT,
            activities_count INT DEFAULT 0,
            package_price DECIMAL(10,2) DEFAULT 0,
            package_duration VARCHAR(50),
            duration VARCHAR(50),
            price DECIMAL(10,2) DEFAULT 0,
            currency VARCHAR(10) DEFAULT '₱',
            group_size VARCHAR(50),
            best_season VARCHAR(100),
            category VARCHAR(100),
            image_path VARCHAR(500),
            image_gallery TEXT,
            is_active TINYINT DEFAULT 1,
            display_order INT DEFAULT 0,
            badge_text VARCHAR(100),
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    try {
        $pdo->exec("ALTER TABLE destinations ADD COLUMN partner_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
    }

    // Add missing currency column to local destinations if missing
    try {
        $pdo->exec("ALTER TABLE destinations ADD COLUMN currency VARCHAR(10) DEFAULT '₱'");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN image_gallery TEXT");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN hotels TEXT");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN remarks TEXT");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN blocked_dates TEXT");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN promo_start DATE");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN promo_end DATE");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN blocked_months TEXT");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN highlight_duration INT DEFAULT 1");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN image2_path VARCHAR(500)");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN image3_path VARCHAR(500)");
        $pdo->exec("ALTER TABLE destinations ADD COLUMN booked_count VARCHAR(50)");
    } catch (PDOException $e) { /* Columns might already exist */
    }

    // Update flash_deals table with new columns
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS flash_deals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            short_description VARCHAR(255),
            category VARCHAR(100),
            location VARCHAR(200),
            description TEXT,
            price DECIMAL(10,2) DEFAULT 0,
            original_price DECIMAL(10,2) DEFAULT 0,
            discount_percent INT DEFAULT 0,
            duration VARCHAR(50) DEFAULT '3D/2N',
            group_size VARCHAR(50) DEFAULT '2-15 pax',
            best_season VARCHAR(100) DEFAULT 'Year Round',
            rating DECIMAL(3,1) DEFAULT 0,
            reviews INT DEFAULT 0,
            booked_count VARCHAR(50),
            badge_text VARCHAR(100),
            itinerary TEXT,
            inclusions TEXT,
            exclusions TEXT,
            hotels TEXT,
            remarks TEXT,
            blocked_dates TEXT,
            promo_start DATE,
            promo_end DATE,
            blocked_months TEXT,
            highlight_duration INT DEFAULT 1,
            image_path VARCHAR(500),
            image2_path VARCHAR(500),
            image3_path VARCHAR(500),
            image_gallery TEXT,
            collage_type VARCHAR(20) DEFAULT 'three',
            is_active TINYINT DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    try {
        $pdo->exec("ALTER TABLE flash_deals ADD COLUMN partner_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE flash_deals ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
    }

    // Add missing columns to flash_deals if they don't exist
    $flash_stmt = $pdo->query("SHOW COLUMNS FROM flash_deals");
    $flash_columns = $flash_stmt->fetchAll(PDO::FETCH_COLUMN);

    $flash_columns_to_add = [
        'short_description' => "ALTER TABLE flash_deals ADD COLUMN short_description VARCHAR(255) AFTER title",
        'duration' => "ALTER TABLE flash_deals ADD COLUMN duration VARCHAR(50) DEFAULT '3D/2N' AFTER discount_percent",
        'group_size' => "ALTER TABLE flash_deals ADD COLUMN group_size VARCHAR(50) DEFAULT '2-15 pax' AFTER duration",
        'best_season' => "ALTER TABLE flash_deals ADD COLUMN best_season VARCHAR(100) DEFAULT 'Year Round' AFTER group_size",
        'itinerary' => "ALTER TABLE flash_deals ADD COLUMN itinerary TEXT AFTER badge_text",
        'description' => "ALTER TABLE flash_deals ADD COLUMN description TEXT AFTER location",
        'inclusions' => "ALTER TABLE flash_deals ADD COLUMN inclusions TEXT AFTER itinerary",
        'exclusions' => "ALTER TABLE flash_deals ADD COLUMN exclusions TEXT AFTER inclusions",
        'image_gallery' => "ALTER TABLE flash_deals ADD COLUMN image_gallery TEXT",
        'collage_type' => "ALTER TABLE flash_deals ADD COLUMN collage_type VARCHAR(20) DEFAULT 'three' AFTER image3_path",
        'currency' => "ALTER TABLE flash_deals ADD COLUMN currency VARCHAR(10) DEFAULT '₱'",
        'hotels' => "ALTER TABLE flash_deals ADD COLUMN hotels TEXT",
        'remarks' => "ALTER TABLE flash_deals ADD COLUMN remarks TEXT",
        'blocked_dates' => "ALTER TABLE flash_deals ADD COLUMN blocked_dates TEXT",
        'promo_start' => "ALTER TABLE flash_deals ADD COLUMN promo_start DATE",
        'promo_end' => "ALTER TABLE flash_deals ADD COLUMN promo_end DATE",
        'blocked_months' => "ALTER TABLE flash_deals ADD COLUMN blocked_months TEXT",
        'highlight_duration' => "ALTER TABLE flash_deals ADD COLUMN highlight_duration INT DEFAULT 1"
    ];

    foreach ($flash_columns_to_add as $col_name => $sql) {
        if (!in_array($col_name, $flash_columns)) {
            $pdo->exec($sql);
        }
    }

    // Create visas table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(100) NOT NULL,
            category VARCHAR(50) DEFAULT 'international',
            description TEXT,
            price DECIMAL(10,2) DEFAULT 0,
            currency VARCHAR(10) DEFAULT '₱',
            processing_time VARCHAR(50),
            requirements TEXT,
            disclaimer TEXT,
            icon_type VARCHAR(50) DEFAULT 'image',
            icon_value VARCHAR(255),
            is_active TINYINT DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    try {
        $pdo->exec("ALTER TABLE visas ADD COLUMN disclaimer TEXT");
    } catch (PDOException $e) { /* Column might already exist */
    }
    // Create global_settings table for flags and generic config
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS global_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT
        )
    ");

    try {
        $pdo->exec("ALTER TABLE visas ADD COLUMN partner_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE visas ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
    }

    try {
        $pdo->exec("ALTER TABLE flight_booking_settings ADD COLUMN partner_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE flight_booking_settings ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
    }

    try {
        $pdo->exec("ALTER TABLE hotel_booking_settings ADD COLUMN partner_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE hotel_booking_settings ADD COLUMN partner_company VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
    }

    ensureCruiseColumns($pdo);

    // Initialize default visa settings if they don't exist
    $pdo->exec("
        INSERT IGNORE INTO global_settings (setting_key, setting_value) VALUES 
        ('visa_disclaimer', 'Completing the application does not provide a 100% guarantee of approval.'),
        ('visa_checklist', '[\"Valid Passport (6 months validity)\",\"Completed Application Form\",\"2x2 Photos\",\"Flight Itinerary\",\"Hotel Booking\",\"Bank Statement\"]')
    ");

} catch (PDOException $e) {
    // Log error but continue
    error_log("Database setup error: " . $e->getMessage());
}

// ==================== FORM HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save Flash Deal - Updated with new fields
    if ($action === 'save_flash_deal') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && !ensurePartnerOwnership($pdo, 'flash_deals', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own packages.']);
            exit;
        }
        $title = trim($_POST['title']);
        $short_description = trim($_POST['short_description'] ?? '');
        $category = trim($_POST['category']);
        $location = trim($_POST['location']);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price']);
        $currency = trim($_POST['currency'] ?? '₱');
        $original_price = floatval($_POST['original_price'] ?? 0);
        $discount_percent = intval($_POST['discount_percent'] ?? 0);
        $duration = trim($_POST['duration'] ?? '');
        $group_size = trim($_POST['group_size'] ?? '');
        $best_season = trim($_POST['best_season'] ?? '');
        $rating = floatval($_POST['rating'] ?? 0);
        $reviews = intval($_POST['reviews'] ?? 0);
        $booked_count = trim($_POST['booked_count'] ?? '');
        $badge_text = trim($_POST['badge_text'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = intval($_POST['display_order'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $blocked_dates = trim($_POST['blocked_dates'] ?? '');
        $promo_start = !empty($_POST['promo_start']) ? $_POST['promo_start'] : null;
        $promo_end = !empty($_POST['promo_end']) ? $_POST['promo_end'] : null;
        $highlight_duration = intval($_POST['highlight_duration'] ?? 1);
        $blocked_months = isset($_POST['blocked_months']) ? implode(',', $_POST['blocked_months']) : '';

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

        $image1 = isset($_FILES['image_1']) ? uploadImage($_FILES['image_1'], $_POST['old_image_1'] ?? null) : ['success' => true, 'path' => $_POST['old_image_1'] ?? null];
        $image2 = isset($_FILES['image_2']) ? uploadImage($_FILES['image_2'], $_POST['old_image_2'] ?? null) : ['success' => true, 'path' => $_POST['old_image_2'] ?? null];
        $image3 = isset($_FILES['image_3']) ? uploadImage($_FILES['image_3'], $_POST['old_image_3'] ?? null) : ['success' => true, 'path' => $_POST['old_image_3'] ?? null];

        // Handle Gallery Images
        $gallery = [];
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT image_gallery FROM flash_deals WHERE id = ?");
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
                if ($_FILES['gallery']['error'][$key] === 0 && isAllowedUploadImage($_FILES['gallery']['type'][$key], $_FILES['gallery']['size'][$key])) {
                    $ext = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'flash_g_' . time() . '_' . $key . '.' . $ext;
                    if (move_uploaded_file($tmp_name, dirname(__DIR__, 2) . '/uploads/' . $filename)) {
                        $gallery[] = 'uploads/' . $filename;
                    }
                }
            }
        }
        $image_gallery_json = json_encode($gallery);

        if ($image1['success'] && $image2['success'] && $image3['success']) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE flash_deals SET 
                        title=?, short_description=?, category=?, location=?, description=?, 
                        price=?, currency=?, original_price=?, discount_percent=?, duration=?, group_size=?, best_season=?,
                        rating=?, reviews=?, booked_count=?, badge_text=?, 
                        itinerary=?, inclusions=?, exclusions=?, hotels=?,
                        image_path=?, image2_path=?, image3_path=?, image_gallery=?,
                        is_active=?, display_order=?, remarks=?, blocked_dates=?,
                        promo_start=?, promo_end=?, blocked_months=?, highlight_duration=?, partner_id=?, partner_company=? WHERE id=?");
                    $stmt->execute([
                        $title,
                        $short_description,
                        $category,
                        $location,
                        $description,
                        $price,
                        $currency,
                        $original_price,
                        $discount_percent,
                        $duration,
                        $group_size,
                        $best_season,
                        $rating,
                        $reviews,
                        $booked_count,
                        $badge_text,
                        $itinerary_json,
                        $inclusions_json,
                        $exclusions_json,
                        $hotels_json,
                        $image1['path'],
                        $image2['path'],
                        $image3['path'],
                        $image_gallery_json,
                        $is_active,
                        $display_order,
                        $remarks,
                        $blocked_dates,
                        $promo_start,
                        $promo_end,
                        $blocked_months,
                        $highlight_duration,
                        $partnerId,
                        $partnerCompany,
                        $id
                    ]);
                    $stmt = $pdo->prepare("SELECT * FROM flash_deals WHERE id = ?");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'message' => 'Flash deal updated successfully!', 'data' => $row]);
                    exit;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO flash_deals (
                        title, short_description, category, location, description, 
                        price, currency, original_price, discount_percent, duration, group_size, best_season,
                        rating, reviews, booked_count, badge_text,
                        itinerary, inclusions, exclusions, hotels,
                        image_path, image2_path, image3_path, image_gallery,
                        is_active, display_order, remarks, blocked_dates,
                        promo_start, promo_end, blocked_months, highlight_duration, partner_id, partner_company
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $title,
                        $short_description,
                        $category,
                        $location,
                        $description,
                        $price,
                        $currency,
                        $original_price,
                        $discount_percent,
                        $duration,
                        $group_size,
                        $best_season,
                        $rating,
                        $reviews,
                        $booked_count,
                        $badge_text,
                        $itinerary_json,
                        $inclusions_json,
                        $exclusions_json,
                        $hotels_json,
                        $image1['path'],
                        $image2['path'],
                        $image3['path'],
                        $image_gallery_json,
                        $is_active,
                        $display_order,
                        $remarks,
                        $blocked_dates,
                        $promo_start,
                        $promo_end,
                        $blocked_months,
                        $highlight_duration,
                        $partnerId,
                        $partnerCompany
                    ]);
                    $newId = intval($pdo->lastInsertId());
                    $stmt = $pdo->prepare("SELECT * FROM flash_deals WHERE id = ?");
                    $stmt->execute([$newId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'message' => 'Flash deal added successfully!', 'data' => $row, 'id' => $newId]);
                    exit;
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Image upload failed: ' . ($image1['message'] ?? $image2['message'] ?? $image3['message'])]);
            exit;
        }
    }

    // Save Destination (Local)
    elseif ($action === 'save_destination') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && !ensurePartnerOwnership($pdo, 'destinations', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own packages.']);
            exit;
        }
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $country = trim($_POST['country']);
        $city = trim($_POST['city']);
        $location_name = trim($_POST['location_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $activities_count = intval($_POST['activities_count'] ?? 0);
        $package_price = floatval($_POST['package_price'] ?? 0);
        $package_duration = trim($_POST['package_duration'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $currency = trim($_POST['currency'] ?? '₱');
        $group_size = trim($_POST['group_size'] ?? '');
        $best_season = trim($_POST['best_season'] ?? '');
        $category = trim($_POST['category'] ?? 'beach');
        $booked_count = trim($_POST['booked_count'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = intval($_POST['display_order'] ?? 0);
        $badge_text = trim($_POST['badge_text'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $blocked_dates = trim($_POST['blocked_dates'] ?? '');
        $promo_start = !empty($_POST['promo_start']) ? $_POST['promo_start'] : null;
        $promo_end = !empty($_POST['promo_end']) ? $_POST['promo_end'] : null;
        $highlight_duration = intval($_POST['highlight_duration'] ?? 1);
        $blocked_months = isset($_POST['blocked_months']) ? implode(',', $_POST['blocked_months']) : '';

        // Handle items from form
        $itinerary_json = $_POST['itinerary'] ?? '[]';
        $hotels_json = $_POST['hotels'] ?? '[]';

        // Handle inclusions
        $inclusions = $_POST['inclusions'] ?? '';
        if (is_string($inclusions) && strpos($inclusions, "\n") !== false) {
            $inclusions = explode("\n", $inclusions);
            $inclusions = array_filter(array_map('trim', $inclusions));
        }
        $inclusions_json = is_array($inclusions) ? json_encode($inclusions) : $inclusions;

        // Handle exclusions
        $exclusions = $_POST['exclusions'] ?? '';
        if (is_string($exclusions) && strpos($exclusions, "\n") !== false) {
            $exclusions = explode("\n", $exclusions);
            $exclusions = array_filter(array_map('trim', $exclusions));
        }
        $exclusions_json = is_array($exclusions) ? json_encode($exclusions) : $exclusions;

        $image = isset($_FILES['image']) ? uploadImage($_FILES['image'], $_POST['old_image'] ?? null) : ['success' => true, 'path' => $_POST['old_image'] ?? null];
        $image2 = isset($_FILES['image_2']) ? uploadImage($_FILES['image_2'], $_POST['old_image_2'] ?? null) : ['success' => true, 'path' => $_POST['old_image_2'] ?? null];
        $image3 = isset($_FILES['image_3']) ? uploadImage($_FILES['image_3'], $_POST['old_image_3'] ?? null) : ['success' => true, 'path' => $_POST['old_image_3'] ?? null];

        // Handle Gallery Images
        $gallery = [];
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT image_gallery FROM destinations WHERE id = ?");
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
                if ($_FILES['gallery']['error'][$key] === 0 && isAllowedUploadImage($_FILES['gallery']['type'][$key], $_FILES['gallery']['size'][$key])) {
                    $ext = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'local_g_' . time() . '_' . $key . '.' . $ext;
                    if (move_uploaded_file($tmp_name, dirname(__DIR__, 2) . '/uploads/' . $filename)) {
                        $gallery[] = 'uploads/' . $filename;
                    }
                }
            }
        }
        $image_gallery_json = json_encode($gallery);

        if ($image['success'] && $image2['success'] && $image3['success']) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE destinations SET 
                        name=?, type=?, country=?, city=?, location_name=?, description=?, short_description=?, 
                        activities_count=?, package_price=?, package_duration=?, duration=?, price=?, currency=?, 
                        group_size=?, best_season=?, category=?, image_path=?, image2_path=?, image3_path=?, image_gallery=?,
                        is_active=?, display_order=?, badge_text=?, booked_count=?, inclusions=?, exclusions=?, itinerary=?, hotels=?, remarks=?, blocked_dates=?,
                        promo_start=?, promo_end=?, blocked_months=?, highlight_duration=?, partner_id=?, partner_company=?
                        WHERE id=?");
                    $stmt->execute([
                        $name,
                        $type,
                        $country,
                        $city,
                        $location_name,
                        $description,
                        $short_description,
                        $activities_count,
                        $package_price,
                        $package_duration,
                        $duration,
                        $price,
                        $currency,
                        $group_size,
                        $best_season,
                        $category,
                        $image['path'],
                        $image2['path'],
                        $image3['path'],
                        $image_gallery_json,
                        $is_active,
                        $display_order,
                        $badge_text,
                        $booked_count,
                        $inclusions_json,
                        $exclusions_json,
                        $itinerary_json,
                        $hotels_json,
                        $remarks,
                        $blocked_dates,
                        $promo_start,
                        $promo_end,
                        $blocked_months,
                        $highlight_duration,
                        $partnerId,
                        $partnerCompany,
                        $id
                    ]);
                    $stmt = $pdo->prepare("SELECT * FROM destinations WHERE id = ?");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'message' => 'Destination updated successfully!', 'data' => $row]);
                    exit;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO destinations (
                        name, type, country, city, location_name, description, short_description, 
                        activities_count, package_price, package_duration, duration, price, currency, 
                        group_size, best_season, category, image_path, image2_path, image3_path, image_gallery,
                        is_active, display_order, badge_text, booked_count, inclusions, exclusions, itinerary, hotels, remarks, blocked_dates,
                        promo_start, promo_end, blocked_months, highlight_duration, partner_id, partner_company
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $name,
                        $type,
                        $country,
                        $city,
                        $location_name,
                        $description,
                        $short_description,
                        $activities_count,
                        $package_price,
                        $package_duration,
                        $duration,
                        $price,
                        $currency,
                        $group_size,
                        $best_season,
                        $category,
                        $image['path'],
                        $image2['path'],
                        $image3['path'],
                        $image_gallery_json,
                        $is_active,
                        $display_order,
                        $badge_text,
                        $booked_count,
                        $inclusions_json,
                        $exclusions_json,
                        $itinerary_json,
                        $hotels_json,
                        $remarks,
                        $blocked_dates,
                        $promo_start,
                        $promo_end,
                        $blocked_months,
                        $highlight_duration,
                        $partnerId,
                        $partnerCompany
                    ]);
                    $newId = intval($pdo->lastInsertId());
                    $stmt = $pdo->prepare("SELECT * FROM destinations WHERE id = ?");
                    $stmt->execute([$newId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'message' => 'Destination added successfully!', 'data' => $row, 'id' => $newId]);
                    exit;
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Image upload failed: ' . ($image['message'] ?? $image2['message'] ?? $image3['message'])]);
            exit;
        }
    }

    // Save Foreign Destination
    elseif ($action === 'save_foreign_destination') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && !ensurePartnerOwnership($pdo, 'foreign_destinations', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own packages.']);
            exit;
        }
        $name = trim($_POST['name']);

        $dest_key = trim($_POST['dest_key'] ?? '');
        if (empty($dest_key) && !empty($name)) {
            $dest_key = strtolower(str_replace([' ', '/', '\\', '&', '?'], '-', $name));
        }

        $country = trim($_POST['country']);
        $city = trim($_POST['city']);
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $activities_count = intval($_POST['activities_count'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $currency = trim($_POST['currency'] ?? '₱');
        $duration = trim($_POST['duration'] ?? '');
        $group_size = trim($_POST['group_size'] ?? '');
        $best_season = trim($_POST['best_season'] ?? '');
        $category = trim($_POST['category'] ?? 'asia');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = intval($_POST['display_order'] ?? 0);
        $badge_text = trim($_POST['badge_text'] ?? '');
        $collage_type = trim($_POST['collage_type'] ?? 'three');
        $remarks = trim($_POST['remarks'] ?? '');
        $blocked_dates = trim($_POST['blocked_dates'] ?? '');
        $promo_start = !empty($_POST['promo_start']) ? $_POST['promo_start'] : null;
        $promo_end = !empty($_POST['promo_end']) ? $_POST['promo_end'] : null;
        $highlight_duration = intval($_POST['highlight_duration'] ?? 1);
        $blocked_months = isset($_POST['blocked_months']) ? implode(',', $_POST['blocked_months']) : '';

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
            $stmt = $pdo->prepare("SELECT image_gallery FROM foreign_destinations WHERE id = ?");
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
                if ($_FILES['gallery']['error'][$key] === 0 && isAllowedUploadImage($_FILES['gallery']['type'][$key], $_FILES['gallery']['size'][$key])) {
                    $ext = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'foreign_g_' . time() . '_' . $key . '.' . $ext;
                    if (move_uploaded_file($tmp_name, dirname(__DIR__, 2) . '/uploads/' . $filename)) {
                        $gallery[] = 'uploads/' . $filename;
                    }
                }
            }
        }
        $image_gallery_json = json_encode($gallery);

        if ($image['success'] && $image2['success'] && $image3['success']) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE foreign_destinations SET 
                        dest_key=?, name=?, country=?, city=?, location=?, description=?, short_description=?, 
                        activities_count=?, price=?, currency=?, duration=?, group_size=?, best_season=?, 
                        category=?, image_path=?, image2_path=?, image3_path=?, image_gallery=?, collage_type=?,
                        is_active=?, display_order=?, badge_text=?, inclusions=?, exclusions=?, itinerary=?, hotels=?, remarks=?, blocked_dates=?,
                        promo_start=?, promo_end=?, blocked_months=?, highlight_duration=?, partner_id=?, partner_company=?
                        WHERE id=?");
                    $stmt->execute([
                        $dest_key,
                        $name,
                        $country,
                        $city,
                        $location,
                        $description,
                        $short_description,
                        $activities_count,
                        $price,
                        $currency,
                        $duration,
                        $group_size,
                        $best_season,
                        $category,
                        $image['path'],
                        $image2['path'],
                        $image3['path'],
                        $image_gallery_json,
                        $collage_type,
                        $is_active,
                        $display_order,
                        $badge_text,
                        $inclusions_json,
                        $exclusions_json,
                        $itinerary_json,
                        $hotels_json,
                        $remarks,
                        $blocked_dates,
                        $promo_start,
                        $promo_end,
                        $blocked_months,
                        $highlight_duration,
                        $partnerId,
                        $partnerCompany,
                        $id
                    ]);
                    $stmt = $pdo->prepare("SELECT * FROM foreign_destinations WHERE id = ?");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'message' => 'Foreign destination updated successfully!', 'data' => $row]);
                    exit;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO foreign_destinations (
                        dest_key, name, country, city, location, description, short_description, 
                        activities_count, price, currency, duration, group_size, best_season, 
                        category, image_path, image2_path, image3_path, image_gallery, collage_type,
                        is_active, display_order, badge_text, inclusions, exclusions, itinerary, hotels, remarks, blocked_dates,
                        promo_start, promo_end, blocked_months, highlight_duration, partner_id, partner_company
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $dest_key,
                        $name,
                        $country,
                        $city,
                        $location,
                        $description,
                        $short_description,
                        $activities_count,
                        $price,
                        $currency,
                        $duration,
                        $group_size,
                        $best_season,
                        $category,
                        $image['path'],
                        $image2['path'],
                        $image3['path'],
                        $image_gallery_json,
                        $collage_type,
                        $is_active,
                        $display_order,
                        $badge_text,
                        $inclusions_json,
                        $exclusions_json,
                        $itinerary_json,
                        $hotels_json,
                        $remarks,
                        $blocked_dates,
                        $promo_start,
                        $promo_end,
                        $blocked_months,
                        $highlight_duration,
                        $partnerId,
                        $partnerCompany
                    ]);
                    $newId = intval($pdo->lastInsertId());
                    $stmt = $pdo->prepare("SELECT * FROM foreign_destinations WHERE id = ?");
                    $stmt->execute([$newId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'message' => 'Foreign destination added successfully!', 'data' => $row, 'id' => $newId]);
                    exit;
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Image upload failed: ' . ($image['message'] ?? $image2['message'] ?? $image3['message'])]);
            exit;
        }
    }

    // Delete Destination
    elseif ($action === 'delete_destination') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'destinations', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT image_path FROM destinations WHERE id = ?");
        $stmt->execute([$id]);
        $dest = $stmt->fetch();
        if ($dest && $dest['image_path'] && file_exists(dirname(__DIR__, 2) . '/' . $dest['image_path'])) {
            unlink(dirname(__DIR__, 2) . '/' . $dest['image_path']);
        }
        $stmt = $pdo->prepare("DELETE FROM destinations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Destination deleted successfully!']);
        exit;
    }

    // Delete Foreign Destination
    elseif ($action === 'delete_foreign_destination') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'foreign_destinations', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT image_path, image2_path, image3_path FROM foreign_destinations WHERE id = ?");
        $stmt->execute([$id]);
        $dest = $stmt->fetch();
        if ($dest) {
            foreach (['image_path', 'image2_path', 'image3_path'] as $img) {
                if ($dest[$img] && file_exists(dirname(__DIR__, 2) . '/' . $dest[$img])) {
                    unlink(dirname(__DIR__, 2) . '/' . $dest[$img]);
                }
            }
        }
        $stmt = $pdo->prepare("DELETE FROM foreign_destinations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Foreign destination deleted successfully!']);
        exit;
    }

    // Delete Flash Deal
    elseif ($action === 'delete_flash_deal') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'flash_deals', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT image_path, image2_path, image3_path FROM flash_deals WHERE id = ?");
        $stmt->execute([$id]);
        $deal = $stmt->fetch();
        if ($deal) {
            foreach (['image_path', 'image2_path', 'image3_path'] as $img_field) {
                if ($deal[$img_field] && file_exists(dirname(__DIR__, 2) . '/' . $deal[$img_field])) {
                    unlink(dirname(__DIR__, 2) . '/' . $deal[$img_field]);
                }
            }
        }
        $stmt = $pdo->prepare("DELETE FROM flash_deals WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Flash deal deleted successfully!']);
        exit;
    }

    // Save Visa
    elseif ($action === 'save_visa') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && !ensurePartnerOwnership($pdo, 'visas', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own packages.']);
            exit;
        }
        $title = trim($_POST['title']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $currency = trim($_POST['currency'] ?? '₱');
        $processing_time = trim($_POST['processing_time'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = intval($_POST['display_order'] ?? 0);
        $icon_type = trim($_POST['icon_type'] ?? 'image');

        // Handle requirements (line-separated to JSON array)
        $requirements_raw = $_POST['requirements'] ?? '';
        $requirements_array = explode("\n", $requirements_raw);
        $requirements_array = array_filter(array_map('trim', $requirements_array));
        $requirements_json = json_encode(array_values($requirements_array));

        $disclaimer = trim($_POST['disclaimer'] ?? '');
        $important_notes = trim($_POST['important_notes'] ?? '');
        $visa_status = in_array($_POST['visa_status'] ?? '', ['required', 'free']) ? $_POST['visa_status'] : 'required';

        // Handle icon value
        $icon_value = $_POST['icon_value'] ?? '';
        if ($icon_type === 'upload' && isset($_FILES['icon_upload'])) {
            $upload = uploadImage($_FILES['icon_upload'], $_POST['old_icon_value'] ?? null);
            if ($upload['success']) {
                $icon_value = $upload['path'];
            } else {
                $icon_value = $_POST['old_icon_value'] ?? '';
                if (!empty($_FILES['icon_upload']['name'])) {
                    $error = "Icon upload failed: " . $upload['message'];
                }
            }
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE visas SET title=?, category=?, description=?, price=?, currency=?, processing_time=?, visa_status=?, requirements=?, disclaimer=?, important_notes=?, icon_type=?, icon_value=?, is_active=?, display_order=?, partner_id=?, partner_company=? WHERE id=?");
                $stmt->execute([$title, $category, $description, $price, $currency, $processing_time, $visa_status, $requirements_json, $disclaimer, $important_notes, $icon_type, $icon_value, $is_active, $display_order, $partnerId, $partnerCompany, $id]);
                echo json_encode(['success' => true, 'message' => 'Visa updated successfully!']);
                exit;
            } else {
                $stmt = $pdo->prepare("INSERT INTO visas (title, category, description, price, currency, processing_time, visa_status, requirements, disclaimer, important_notes, icon_type, icon_value, is_active, display_order, partner_id, partner_company) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $category, $description, $price, $currency, $processing_time, $visa_status, $requirements_json, $disclaimer, $important_notes, $icon_type, $icon_value, $is_active, $display_order, $partnerId, $partnerCompany]);
                echo json_encode(['success' => true, 'message' => 'Visa added successfully!']);
                exit;
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // Delete Visa
    elseif ($action === 'delete_visa') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'visas', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT icon_type, icon_value FROM visas WHERE id = ?");
        $stmt->execute([$id]);
        $visa = $stmt->fetch();
        if ($visa && $visa['icon_type'] === 'upload' && $visa['icon_value'] && file_exists(dirname(__DIR__, 2) . '/' . $visa['icon_value'])) {
            unlink(dirname(__DIR__, 2) . '/' . $visa['icon_value']);
        }
        $stmt = $pdo->prepare("DELETE FROM visas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Visa deleted successfully!']);
        exit;
    }

    // Save Visa Settings
    elseif ($action === 'save_visa_settings') {
        $disclaimer = trim($_POST['visa_disclaimer'] ?? '');
        $checklist_raw = $_POST['visa_checklist'] ?? '';
        $checklist_array = explode("\n", $checklist_raw);
        $checklist_array = array_filter(array_map('trim', $checklist_array));
        $checklist_json = json_encode(array_values($checklist_array));

        $stmt = $pdo->prepare("UPDATE global_settings SET setting_value = ? WHERE setting_key = 'visa_disclaimer'");
        $stmt->execute([$disclaimer]);

        $stmt = $pdo->prepare("UPDATE global_settings SET setting_value = ? WHERE setting_key = 'visa_checklist'");
        $stmt->execute([$checklist_json]);

        echo json_encode(['success' => true, 'message' => 'Visa settings updated successfully!']);
        exit;
    }

    // Save Flight Data
    elseif ($action === 'save_flight_data') {
        $id = intval($_POST['id'] ?? 0);
        $destination_key = trim($_POST['destination_key']);
        $destination_name = trim($_POST['destination_name']);

        // Create flight_booking_settings table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS flight_booking_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                destination_key VARCHAR(50) NOT NULL,
                destination_name VARCHAR(100) NOT NULL,
                month_january_low INT DEFAULT 0,
                month_january_high INT DEFAULT 0,
                month_january_airline VARCHAR(100),
                month_february_low INT DEFAULT 0,
                month_february_high INT DEFAULT 0,
                month_february_airline VARCHAR(100),
                month_march_low INT DEFAULT 0,
                month_march_high INT DEFAULT 0,
                month_march_airline VARCHAR(100),
                month_april_low INT DEFAULT 0,
                month_april_high INT DEFAULT 0,
                month_april_airline VARCHAR(100),
                month_may_low INT DEFAULT 0,
                month_may_high INT DEFAULT 0,
                month_may_airline VARCHAR(100),
                month_june_low INT DEFAULT 0,
                month_june_high INT DEFAULT 0,
                month_june_airline VARCHAR(100),
                month_july_low INT DEFAULT 0,
                month_july_high INT DEFAULT 0,
                month_july_airline VARCHAR(100),
                month_august_low INT DEFAULT 0,
                month_august_high INT DEFAULT 0,
                month_august_airline VARCHAR(100),
                month_september_low INT DEFAULT 0,
                month_september_high INT DEFAULT 0,
                month_september_airline VARCHAR(100),
                month_october_low INT DEFAULT 0,
                month_october_high INT DEFAULT 0,
                month_october_airline VARCHAR(100),
                month_november_low INT DEFAULT 0,
                month_november_high INT DEFAULT 0,
                month_november_airline VARCHAR(100),
                month_december_low INT DEFAULT 0,
                month_december_high INT DEFAULT 0,
                month_december_airline VARCHAR(100),
                is_active TINYINT DEFAULT 1,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        $fields = [];
        $values = [];
        $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

        foreach ($months as $month) {
            $low = intval($_POST["{$month}_low"] ?? 0);
            $high = intval($_POST["{$month}_high"] ?? 0);
            $airline = trim($_POST["{$month}_airline"] ?? '');
            $fields[] = "month_{$month}_low = ?";
            $fields[] = "month_{$month}_high = ?";
            $fields[] = "month_{$month}_airline = ?";
            $values[] = $low;
            $values[] = $high;
            $values[] = $airline;
        }
        if ($id > 0) {
            $sql = "UPDATE flight_booking_settings SET " . implode(', ', $fields) . ", destination_key = ?, destination_name = ? WHERE id = ?";
            $values[] = $destination_key;
            $values[] = $destination_name;
            $values[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = "Flight data updated successfully!";
        } else {
            $sql = "INSERT INTO flight_booking_settings (destination_key, destination_name, " . implode(', ', array_map(function ($f) {
                return str_replace(' = ?', '', $f);
            }, $fields)) . ") VALUES (?, ?, " . implode(', ', array_fill(0, count($fields), '?')) . ")";
            array_unshift($values, $destination_key, $destination_name);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = "Flight data added successfully!";
        }
    }

    // Save Hotel Data
    elseif ($action === 'save_hotel_data') {
        try {
            $id = intval($_POST['id'] ?? 0);
            $destination_key = trim($_POST['destination_key']);
            $destination_name = trim($_POST['destination_name']);

            // Create hotel_booking_settings table if it doesn't exist
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS hotel_booking_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                destination_key VARCHAR(50) NOT NULL,
                destination_name VARCHAR(100) NOT NULL,
                month_january_low INT DEFAULT 0,
                month_january_high INT DEFAULT 0,
                month_january_hotel VARCHAR(200),
                month_february_low INT DEFAULT 0,
                month_february_high INT DEFAULT 0,
                month_february_hotel VARCHAR(200),
                month_march_low INT DEFAULT 0,
                month_march_high INT DEFAULT 0,
                month_march_hotel VARCHAR(200),
                month_april_low INT DEFAULT 0,
                month_april_high INT DEFAULT 0,
                month_april_hotel VARCHAR(200),
                month_may_low INT DEFAULT 0,
                month_may_high INT DEFAULT 0,
                month_may_hotel VARCHAR(200),
                month_june_low INT DEFAULT 0,
                month_june_high INT DEFAULT 0,
                month_june_hotel VARCHAR(200),
                month_july_low INT DEFAULT 0,
                month_july_high INT DEFAULT 0,
                month_july_hotel VARCHAR(200),
                month_august_low INT DEFAULT 0,
                month_august_high INT DEFAULT 0,
                month_august_hotel VARCHAR(200),
                month_september_low INT DEFAULT 0,
                month_september_high INT DEFAULT 0,
                month_september_hotel VARCHAR(200),
                month_october_low INT DEFAULT 0,
                month_october_high INT DEFAULT 0,
                month_october_hotel VARCHAR(200),
                month_november_low INT DEFAULT 0,
                month_november_high INT DEFAULT 0,
                month_november_hotel VARCHAR(200),
                month_december_low INT DEFAULT 0,
                month_december_high INT DEFAULT 0,
                month_december_hotel VARCHAR(200),
                is_active TINYINT DEFAULT 1,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

            $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

            $fields = [];
            $values = [];

            foreach ($months as $month) {
                $low = intval($_POST["{$month}_low"] ?? 0);
                $high = intval($_POST["{$month}_high"] ?? 0);
                $hotel = trim($_POST["{$month}_hotel"] ?? '');
                $fields[] = "month_{$month}_low = ?";
                $fields[] = "month_{$month}_high = ?";
                $fields[] = "month_{$month}_hotel = ?";
                $values[] = $low;
                $values[] = $high;
                $values[] = $hotel;
            }
            if ($id > 0) {
                $sql = "UPDATE hotel_booking_settings SET " . implode(', ', $fields) . ", destination_key = ?, destination_name = ? WHERE id = ?";
                $values[] = $destination_key;
                $values[] = $destination_name;
                $values[] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $message = "Hotel data updated successfully!";
            } else {
                $sql = "INSERT INTO hotel_booking_settings (destination_key, destination_name, " . implode(', ', array_map(function ($f) {
                    return str_replace(' = ?', '', $f);
                }, $fields)) . ") VALUES (?, ?, " . implode(', ', array_fill(0, count($fields), '?')) . ")";
                array_unshift($values, $destination_key, $destination_name);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $message = "Hotel data added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Failed to save hotel data: " . $e->getMessage();
        }
    }

    // Delete Flight Data
    elseif ($action === 'delete_flight_data') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'flight_booking_settings', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM flight_booking_settings WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Flight data deleted successfully!']);
        exit;
    }

    // Delete Hotel Data
    elseif ($action === 'delete_hotel_data') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'hotel_booking_settings', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM hotel_booking_settings WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Hotel data deleted successfully!']);
        exit;
    }

    // Save Site Service (Flights, Premium, Experiences)
    elseif ($action === 'save_site_service') {
        try {
            $service_id = intval($_POST['id'] ?? 0);
            if ($service_id > 0 && !ensurePartnerOwnership($pdo, 'site_services', $service_id, $partnerId)) {
                echo json_encode(['success' => false, 'message' => 'You can only edit your own packages.']);
                exit;
            }
            $service_type = $_POST['service_type'];
            $title = trim($_POST['title'] ?? '');
            $service_code = trim($_POST['service_code'] ?? '');
            $badge_text = trim($_POST['badge_text'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $full_description = trim($_POST['full_description'] ?? '');
            $highlights = trim($_POST['highlights'] ?? '');
            $inclusions = trim($_POST['inclusions'] ?? '');
            $exclusions = trim($_POST['exclusions'] ?? '');
            $required_documents = trim($_POST['required_documents'] ?? '');
            $travel_requirements = trim($_POST['travel_requirements'] ?? '');
            $cancellation_policy = trim($_POST['cancellation_policy'] ?? '');
            $terms_conditions = trim($_POST['terms_conditions'] ?? '');
            $amenities = trim($_POST['amenities'] ?? '');
            $icon_class = trim($_POST['icon_class'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $currency = trim($_POST['currency'] ?? '₱');
            $duration = trim($_POST['duration'] ?? '');
            $available_slots = intval($_POST['available_slots'] ?? 0);
            $booking_deadline = !empty($_POST['booking_deadline']) ? $_POST['booking_deadline'] : null;
            $departure_date = !empty($_POST['departure_date']) ? $_POST['departure_date'] : null;
            $return_date = !empty($_POST['return_date']) ? $_POST['return_date'] : null;
            $status_text = trim($_POST['status_text'] ?? 'Available');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $display_order = intval($_POST['display_order'] ?? 0);

            // Handle Featured Image
            $featured_image = $_POST['old_featured_image'] ?? '';
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0 && isAllowedUploadImage($_FILES['featured_image']['type'], $_FILES['featured_image']['size'])) {
                $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                $filename = 'service_f_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], dirname(__DIR__, 2) . '/uploads/' . $filename)) {
                    $featured_image = 'uploads/' . $filename;
                }
            }

            // Handle Gallery Images
            $gallery = [];
            if ($service_id > 0) {
                $stmt = $pdo->prepare("SELECT image_gallery FROM site_services WHERE id = ?");
                $stmt->execute([$service_id]);
                $existing = $stmt->fetchColumn();
                $gallery = $existing ? json_decode($existing, true) : [];
            }

            if (isset($_POST['remove_gallery_images']) && !empty($_POST['remove_gallery_images'])) {
                $to_remove = explode(',', $_POST['remove_gallery_images']);
                $gallery = array_values(array_filter($gallery, function ($img) use ($to_remove) {
                    return !in_array($img, $to_remove);
                }));
            }

            if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
                foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
                    // Skip empty or errored entries (e.g. empty file input slot)
                    if ($_FILES['gallery']['error'][$key] !== UPLOAD_ERR_OK) continue;
                    if (empty($_FILES['gallery']['name'][$key])) continue;
                    if (!isAllowedUploadImage($_FILES['gallery']['type'][$key], $_FILES['gallery']['size'][$key])) continue;
                    $ext = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'service_g_' . time() . '_' . $key . '.' . $ext;
                    if (move_uploaded_file($tmp_name, dirname(__DIR__, 2) . '/uploads/' . $filename)) {
                        $gallery[] = 'uploads/' . $filename;
                    }
                }
            }
            $image_gallery_json = json_encode($gallery);
            if ($service_id > 0) {
                $stmt = $pdo->prepare("UPDATE site_services SET 
                service_type=?, title=?, service_code=?, badge_text=?, category=?, tags=?, 
                description=?, full_description=?, highlights=?, inclusions=?, exclusions=?, 
                required_documents=?, travel_requirements=?, cancellation_policy=?, terms_conditions=?,
                icon_class=?, featured_image=?, image_gallery=?, amenities=?,
                price=?, currency=?, duration=?, available_slots=?, booking_deadline=?, 
                departure_date=?, return_date=?, status_text=?, is_active=?, is_featured=?, display_order=?, partner_id=?, partner_company=? 
                WHERE id=?");
                $stmt->execute([
                    $service_type,
                    $title,
                    $service_code,
                    $badge_text,
                    $category,
                    $tags,
                    $description,
                    $full_description,
                    $highlights,
                    $inclusions,
                    $exclusions,
                    $required_documents,
                    $travel_requirements,
                    $cancellation_policy,
                    $terms_conditions,
                    $icon_class,
                    $featured_image,
                    $image_gallery_json,
                    $amenities,
                    $price,
                    $currency,
                    $duration,
                    $available_slots,
                    $booking_deadline,
                    $departure_date,
                    $return_date,
                    $status_text,
                    $is_active,
                    $is_featured,
                    $display_order,
                    $partnerId,
                    $partnerCompany,
                    $service_id
                ]);
                $message = ucfirst($service_type) . " service updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO site_services (
                service_type, title, service_code, badge_text, category, tags, 
                description, full_description, highlights, inclusions, exclusions, 
                required_documents, travel_requirements, cancellation_policy, terms_conditions,
                icon_class, featured_image, image_gallery, amenities,
                price, currency, duration, available_slots, booking_deadline, 
                departure_date, return_date, status_text, is_active, is_featured, display_order, partner_id, partner_company
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $service_type,
                    $title,
                    $service_code,
                    $badge_text,
                    $category,
                    $tags,
                    $description,
                    $full_description,
                    $highlights,
                    $inclusions,
                    $exclusions,
                    $required_documents,
                    $travel_requirements,
                    $cancellation_policy,
                    $terms_conditions,
                    $icon_class,
                    $featured_image,
                    $image_gallery_json,
                    $amenities,
                    $price,
                    $currency,
                    $duration,
                    $available_slots,
                    $booking_deadline,
                    $departure_date,
                    $return_date,
                    $status_text,
                    $is_active,
                    $is_featured,
                    $display_order,
                    $partnerId,
                    $partnerCompany
                ]);
                $service_id = $pdo->lastInsertId();
                $message = ucfirst($service_type) . " service added successfully!";
            }

            // Handle Itinerary
            $pdo->prepare("DELETE FROM service_itinerary WHERE service_id = ?")->execute([$service_id]);
            if (isset($_POST['itinerary']) && is_array($_POST['itinerary'])) {
                $it_stmt = $pdo->prepare("INSERT INTO service_itinerary (service_id, day_number, title, description) VALUES (?, ?, ?, ?)");
                foreach ($_POST['itinerary'] as $day) {
                    $it_stmt->execute([$service_id, $day['day_number'], $day['title'], $day['description']]);
                }
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to save service: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'delete_site_service') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'site_services', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM site_services WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Service deleted successfully!']);
        exit;
    }

    // Advanced Cruise Handlers
    elseif ($action === 'save_advanced_cruise') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && !ensurePartnerOwnership($pdo, 'cruises', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own packages.']);
            exit;
        }
        $data = [
            'cruise_code' => trim($_POST['cruise_code'] ?? ''),
            'title' => trim($_POST['title'] ?? ''),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'full_description' => trim($_POST['full_description'] ?? ''),
            'duration' => trim($_POST['duration'] ?? ''),
            'departure_port' => trim($_POST['departure_port'] ?? ''),
            'destinations' => trim($_POST['destinations'] ?? ''),
            'route' => trim($_POST['route'] ?? ''),
            'ship_name' => trim($_POST['ship_name'] ?? ''),
            'cruise_line' => trim($_POST['cruise_line'] ?? ''),
            'room_types' => $_POST['room_types'] ?? '[]',
            'amenities' => $_POST['amenities'] ?? '[]',
            'ship_description' => trim($_POST['ship_description'] ?? ''),
            'base_price' => floatval($_POST['base_price'] ?? 0),
            'price_per_person' => floatval($_POST['price_per_person'] ?? 0),
            'promo_price' => floatval($_POST['promo_price'] ?? 0),
            'inclusions' => trim($_POST['inclusions'] ?? ''),
            'exclusions' => trim($_POST['exclusions'] ?? ''),
            'departure_date' => ($_POST['departure_date'] ?? '') ?: null,
            'return_date' => ($_POST['return_date'] ?? '') ?: null,
            'booking_deadline' => ($_POST['booking_deadline'] ?? '') ?: null,
            'available_slots' => intval($_POST['available_slots'] ?? 0),
            'status' => $_POST['status'] ?? 'Available',
            'required_documents' => trim($_POST['required_documents'] ?? ''),
            'travel_requirements' => trim($_POST['travel_requirements'] ?? ''),
            'health_requirements' => trim($_POST['health_requirements'] ?? ''),
            'cancellation_policy' => trim($_POST['cancellation_policy'] ?? ''),
            'refund_policy' => trim($_POST['refund_policy'] ?? ''),
            'terms_conditions' => trim($_POST['terms_conditions'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'destination_type' => trim($_POST['destination_type'] ?? ''),
            'tags' => trim($_POST['tags'] ?? ''),
            'highlights' => trim($_POST['highlights'] ?? ''),
            'promo_text' => trim($_POST['promo_text'] ?? ''),
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0
        ];

        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0 && isAllowedUploadImage($_FILES['featured_image']['type'], $_FILES['featured_image']['size'])) {
            $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $filename = 'cruise_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], dirname(__DIR__, 2) . '/uploads/' . $filename)) {
                $data['featured_image'] = 'uploads/' . $filename;
            }
        } else {
            $data['featured_image'] = $_POST['old_featured_image'] ?? '';
        }

        // Handle Cruise Image Gallery
        $gallery = [];
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT gallery FROM cruises WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetchColumn();
            $gallery = $existing ? json_decode($existing, true) : [];
        }

        if (isset($_POST['remove_gallery_images']) && !empty($_POST['remove_gallery_images'])) {
            $to_remove = explode(',', $_POST['remove_gallery_images']);
            $gallery = array_values(array_filter($gallery, function ($img) use ($to_remove) {
                return !in_array($img, $to_remove);
            }));
            // Delete from disk if necessary
            foreach ($to_remove as $img) {
                $filepath = dirname(__DIR__, 2) . '/' . $img;
                if (file_exists($filepath) && strpos($img, 'uploads/') === 0) {
                    @unlink($filepath);
                }
            }
        }

        if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
            foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery']['error'][$key] !== UPLOAD_ERR_OK) continue;
                if (empty($_FILES['gallery']['name'][$key])) continue;
                if (!isAllowedUploadImage($_FILES['gallery']['type'][$key], $_FILES['gallery']['size'][$key])) continue;
                $ext = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                $filename = 'cruise_gallery_' . time() . '_' . $key . '.' . $ext;
                if (move_uploaded_file($tmp_name, dirname(__DIR__, 2) . '/uploads/' . $filename)) {
                    $gallery[] = 'uploads/' . $filename;
                }
            }
        }
        $data['gallery'] = json_encode($gallery);

        $data['partner_id'] = $partnerId;
        $data['partner_company'] = $partnerCompany;

        try {
            if ($id > 0) {
                $sql = "UPDATE cruises SET ";
                $updates = [];
                foreach ($data as $key => $val) {
                    $updates[] = "$key = :$key";
                }
                $sql .= implode(', ', $updates) . " WHERE id = :id";
                $data['id'] = $id;
                $pdo->prepare($sql)->execute($data);
                $cruise_id = $id;
            } else {
                $fields = array_keys($data);
                $placeholders = array_map(function ($f) {
                    return ":$f";
                }, $fields);
                $sql = "INSERT INTO cruises (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $pdo->prepare($sql)->execute($data);
                $cruise_id = $pdo->lastInsertId();
            }

            // Itinerary
            $pdo->prepare("DELETE FROM cruise_itinerary WHERE cruise_id = ?")->execute([$cruise_id]);
            if (isset($_POST['itinerary']) && is_array($_POST['itinerary'])) {
                $it_stmt = $pdo->prepare("INSERT INTO cruise_itinerary (cruise_id, day_number, title, description) VALUES (?, ?, ?, ?)");
                foreach ($_POST['itinerary'] as $day) {
                    $it_stmt->execute([$cruise_id, $day['day_number'], $day['title'], $day['description']]);
                }
            }
        } catch (PDOException $e) {
            error_log('save_advanced_cruise failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to save cruise: ' . $e->getMessage()]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM cruises WHERE id = ?");
        $stmt->execute([$cruise_id]);
        $savedCruise = $stmt->fetch(PDO::FETCH_ASSOC);
        $it_stmt = $pdo->prepare("SELECT * FROM cruise_itinerary WHERE cruise_id = ? ORDER BY day_number");
        $it_stmt->execute([$cruise_id]);
        $savedCruise['itinerary'] = $it_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'message' => 'Cruise saved successfully!', 'data' => $savedCruise]);
        exit;
    } elseif ($action === 'delete_advanced_cruise') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'cruises', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own packages.']);
            exit;
        }
        $pdo->prepare("DELETE FROM cruises WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'get_advanced_cruise') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'cruises', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only access your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM cruises WHERE id = ?");
        $stmt->execute([$id]);
        $cruise = $stmt->fetch(PDO::FETCH_ASSOC);
        $it_stmt = $pdo->prepare("SELECT * FROM cruise_itinerary WHERE cruise_id = ? ORDER BY day_number");
        $it_stmt->execute([$id]);
        $cruise['itinerary'] = $it_stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $cruise]);
        exit;
    } elseif ($action === 'get_site_service') {
        $id = intval($_POST['id']);
        if (!ensurePartnerOwnership($pdo, 'site_services', $id, $partnerId)) {
            echo json_encode(['success' => false, 'message' => 'You can only access your own packages.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM site_services WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        $it_stmt = $pdo->prepare("SELECT * FROM service_itinerary WHERE service_id = ? ORDER BY day_number");
        $it_stmt->execute([$id]);
        $service['itinerary'] = $it_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $service]);
        exit;
    }
}

// Fetch data for display
try {
    $stmt = $pdo->prepare("SELECT * FROM flash_deals WHERE partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $flash_deals = $stmt->fetchAll();
} catch (PDOException $e) {
    $flash_deals = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM foreign_destinations WHERE partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $foreign_destinations = $stmt->fetchAll();
} catch (PDOException $e) {
    $foreign_destinations = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM destinations WHERE type = 'local' AND partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $local_destinations = $stmt->fetchAll();
} catch (PDOException $e) {
    $local_destinations = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM flight_booking_settings WHERE partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $flight_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $flight_data = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM hotel_booking_settings WHERE partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $hotel_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $hotel_data = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM visas WHERE partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $visas = $stmt->fetchAll();
} catch (PDOException $e) {
    $visas = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM cruises WHERE partner_id = ? ORDER BY created_at DESC");
    $stmt->execute([$partnerId]);
    $cruises = $stmt->fetchAll();
} catch (PDOException $e) {
    $cruises = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM site_services WHERE service_type = 'flight' AND partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $flight_packages = $stmt->fetchAll();
} catch (PDOException $e) {
    $flight_packages = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM site_services WHERE service_type = 'premium' AND partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $premium_services = $stmt->fetchAll();
} catch (PDOException $e) {
    $premium_services = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM site_services WHERE service_type = 'experience' AND partner_id = ? ORDER BY display_order, id DESC");
    $stmt->execute([$partnerId]);
    $experience_services = $stmt->fetchAll();
} catch (PDOException $e) {
    $experience_services = [];
}

// Fetch global settings
try {
    $settings_query = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
    $global_settings_raw = $settings_query->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $global_settings_raw = [];
}

$visa_disclaimer = $global_settings_raw['visa_disclaimer'] ?? 'Completing the application does not provide a 100% guarantee of approval.';
$visa_checklist_json = $global_settings_raw['visa_checklist'] ?? '[]';
$visa_checklist_array = json_decode($visa_checklist_json, true) ?: [];
$visa_checklist_text = implode("\n", $visa_checklist_array);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Manager - HeyDream Admin</title>
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

        /* Custom Highlighting for Flatpickr in User Booking Modal */
        /* These will be used in the frontend JS */
        .promo-range-red {
            background: #ff4444 !important;
            color: #fff !important;
            border-radius: 50% !important;
            border: none !important;
        }

        .promo-pale {
            opacity: 0.4 !important;
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
            background: linear-gradient(180deg, #07233f 0%, #0f4c81 100%);
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
            color: #4da3ff;
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
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            border-left: 3px solid #4da3ff;
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
        }

        .section-header h2 {
            color: #003580;
            font-size: 1.3rem;
        }

        .section-header h2 i {
            margin-right: 10px;
            color: #ffd700;
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
        }

        .add-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
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
            background: #ffd700;
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
            margin-bottom: 10px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 10px;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        /* SweetAlert2 Premium Redesign & Layering */
        .swal2-container {
            z-index: 99999 !important;
            /* Forces notification to the very front */
            backdrop-filter: blur(4px);
            background: rgba(15, 23, 42, 0.3) !important;
        }

        .swal2-popup {
            border-radius: 24px !important;
            padding: 2.5rem 2rem !important;
            font-family: 'Poppins', sans-serif !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        .swal2-title {
            font-size: 1.6rem !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            margin-bottom: 0.5rem !important;
        }

        .swal2-html-container {
            font-size: 1rem !important;
            color: #64748b !important;
            line-height: 1.6 !important;
        }

        .swal2-icon {
            border-width: 3px !important;
            margin-top: 0 !important;
        }

        .swal2-styled.swal2-confirm {
            background-color: #dc2626 !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            padding: 12px 28px !important;
            font-size: 0.95rem !important;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25) !important;
            transition: all 0.2s !important;
        }

        .swal2-styled.swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.35) !important;
        }

        .swal2-styled.swal2-cancel {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            padding: 12px 28px !important;
            font-size: 0.95rem !important;
            transition: all 0.2s !important;
        }

        .swal2-styled.swal2-cancel:hover {
            background-color: #e2e8f0 !important;
            color: #475569 !important;
            transform: translateY(-2px) !important;
        }

        /* Flashing Red Icon Animation for Deletions */
        .swal2-icon.swal2-error {
            border-color: #dc2626 !important;
            animation: pulse-red-icon 1.5s infinite !important;
        }

        .swal2-icon.swal2-error [class^='swal2-x-mark-line'] {
            background-color: #dc2626 !important;
        }

        @keyframes pulse-red-icon {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4);
            }

            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 15px rgba(220, 38, 38, 0);
            }

            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
            }
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
            background: white;
            border-radius: 24px 24px 0 0;
        }

        .modal-header h3 {
            color: #003580;
        }

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            padding: 25px;
        }

        /* Country Picker Styling */
        /* Improved Country Picker Styling */
        .picker-container {
            background: linear-gradient(145deg, #ffffff, #f0f2f5);
            border: 1px solid #e0e6ed;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .picker-search {
            position: relative;
            margin-bottom: 15px;
        }

        .picker-search i.fa-search {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #003580;
            opacity: 0.6;
            z-index: 5;
        }

        .picker-search input {
            padding-left: 40px !important;
            padding-right: 40px !important;
            border: 2px solid #e0e0e0 !important;
            border-radius: 12px !important;
            height: 45px;
            background: white !important;
            transition: all 0.3s ease;
        }

        .picker-search input:focus {
            border-color: #003580 !important;
            box-shadow: 0 0 0 4px rgba(0, 53, 128, 0.1) !important;
        }

        .clear-picker-search {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            background: #eee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            font-size: 0.7rem;
            transition: all 0.2s ease;
            z-index: 5;
            border: none;
        }

        .clear-picker-search:hover {
            background: #ff4444;
            color: white;
        }

        .picker-results {
            max-height: 240px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 12px;
            padding: 8px 4px;
            scrollbar-width: thin;
            scrollbar-color: #003580 #f0f2f5;
        }

        .picker-results::-webkit-scrollbar {
            width: 6px;
        }

        .picker-results::-webkit-scrollbar-track {
            background: #f0f2f5;
            border-radius: 10px;
        }

        .picker-results::-webkit-scrollbar-thumb {
            background: #003580;
            border-radius: 10px;
        }

        .picker-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .picker-item::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #003580;
            transform: scaleY(0);
            transition: transform 0.25s ease;
        }

        .picker-item:hover {
            border-color: #003580;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 53, 128, 0.08);
            background: #f8fbff;
        }

        .picker-item:hover::after {
            transform: scaleY(1);
        }

        .picker-item img {
            width: 28px;
            height: 20px;
            object-fit: cover;
            border-radius: 3px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #f0f0f0;
        }

        .picker-item span {
            font-size: 0.8rem;
            color: #2d3748;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
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
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .image-upload-group {
            border: 2px dashed #ddd;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .image-upload-group:hover {
            border-color: #ffd700;
            background: #fff8f0;
        }

        .image-preview {
            width: 100%;
            height: 120px;
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            margin-bottom: 10px;
            background-color: #f0f0f0;
        }

        .image-upload-label {
            display: inline-block;
            padding: 8px 16px;
            background: #003580;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
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
        }

        .save-btn:hover {
            background: #218838;
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

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .toggle-switch-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 26px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-switch-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #cbd5e1;
            border-radius: 999px;
            transition: 0.2s;
        }

        .toggle-switch-slider::before {
            content: "";
            position: absolute;
            height: 20px;
            width: 20px;
            left: 3px;
            top: 3px;
            background-color: #fff;
            border-radius: 50%;
            transition: 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .toggle-switch input:checked+.toggle-switch-slider {
            background-color: #10b981;
        }

        .toggle-switch input:checked+.toggle-switch-slider::before {
            transform: translateX(20px);
        }

        .toggle-switch input:focus-visible+.toggle-switch-slider {
            outline: 2px solid #003580;
            outline-offset: 2px;
        }

        .toggle-switch-label {
            font-weight: 600;
            color: #0f172a;
            cursor: pointer;
            user-select: none;
        }

        input[type="file"] {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 9px 12px;
            border: 1.5px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 0.82rem;
            color: #64748b;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }

        input[type="file"]:hover {
            border-color: #003580;
            background: #eff6ff;
        }

        input[type="file"]::file-selector-button {
            padding: 8px 16px;
            margin-right: 12px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #003580, #0057d9);
            color: #fff;
            font-weight: 600;
            font-size: 0.78rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        input[type="file"]::file-selector-button:hover {
            opacity: 0.88;
        }

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

        .month-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .month-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .month-card h4 {
            color: #003580;
            margin-bottom: 10px;
            text-align: center;
        }

        .auto-save-indicator {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            z-index: 2100;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .auto-save-indicator.show {
            opacity: 1;
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

        /* Mobile Responsiveness - Harmonized Overrides */
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

            .sidebar-overlay.active {
                display: block;
            }

            .top-bar {
                padding: 12px 10px 12px 5px !important;
                /* Aggressive left move */
                border-radius: 12px;
                margin-bottom: 20px;
                position: relative;
                top: 0;
            }

            .top-bar h1 {
                font-size: 1.1rem;
            }

            .back-to-dashboard span {
                display: inline;
                margin-left: 5px;
            }

            .back-to-dashboard {
                padding: 6px 12px;
                font-size: 0.85rem;
            }

            .month-grid {
                grid-template-columns: repeat(2, 1fr);
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
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }

            .month-grid {
                grid-template-columns: 1fr;
            }

            .card-meta {
                flex-direction: column;
                gap: 5px;
            }

            .top-bar {
                flex-direction: row;
                /* Keep in row to keep button and title together */
                justify-content: space-between;
                align-items: center;
                text-align: left;
            }

            .top-bar .left-section {
                width: auto;
                justify-content: flex-start;
            }
        }

        /* Advanced Cruise Styles */
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

        .tab-btn.tab-saved {
            color: #166534;
            font-weight: 700;
        }

        .tab-btn.tab-saved::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #22c55e;
        }

        .tab-content {
            display: none;
            padding: 5px 0;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .itinerary-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }

        .remove-day {
            position: absolute;
            top: 15px;
            right: 15px;
            color: #dc2626;
            cursor: pointer;
            transition: 0.3s;
        }

        .remove-day:hover {
            color: #991b1b;
            transform: scale(1.1);
        }

        .image-preview-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .img-preview {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            background-size: cover;
            background-position: center;
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

        /* Standardized Premium Gallery Grid */
        .gallery-top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .featured-upload-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .featured-preview-frame {
            width: 100%;
            height: 240px;
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            transition: all 0.3s ease;
        }

        .featured-preview-frame:hover {
            border-color: #003580;
            background: #f0f7ff;
        }

        .gallery-upload-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .upload-badge-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .upload-counter-badge {
            background: #003580;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0, 53, 128, 0.2);
        }

        .preview-grid-layout {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-auto-rows: 110px;
            gap: 10px;
            width: 100%;
            min-height: 110px;
        }

        .grid-preview-item {
            border-radius: 12px;
            background: #f1f5f9;
            background-size: cover;
            background-position: center;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .grid-preview-item:hover {
            transform: scale(1.02);
            z-index: 2;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .remove-img-overlay {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 38, 38, 0.9);
            color: white;
            border: none;
            width: 22px;
            height: 22px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .grid-preview-item:hover .remove-img-overlay {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .gallery-top-grid {
                grid-template-columns: 1fr;
            }

            .featured-preview-frame {
                height: 180px;
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
                <h2>Content Manager</h2>
                <p>Manage Website Content</p>
            </div>
            <div class="sidebar-nav">
                <a href="?page=flash-deals" class="nav-item <?= $page === 'flash-deals' ? 'active' : '' ?>">
                    <i class="fas fa-bolt"></i>
                    <span>Flash Deals</span>
                </a>
                <a href="?page=foreign-destinations"
                    class="nav-item <?= $page === 'foreign-destinations' ? 'active' : '' ?>">
                    <i class="fas fa-globe-asia"></i>
                    <span>Foreign Destinations</span>
                </a>
                <a href="?page=local-destinations"
                    class="nav-item <?= $page === 'local-destinations' ? 'active' : '' ?>">
                    <i class="fas fa-umbrella-beach"></i>
                    <span>Local Destinations</span>
                </a>
                <!-- Removed Flight Prices and Hotel Rates from sidebar -->
                <a href="?page=visa-assistance" class="nav-item <?= $page === 'visa-assistance' ? 'active' : '' ?>">
                    <i class="fas fa-passport"></i>
                    <span>Visa Assistance</span>
                </a>
                <a href="?page=cruises" class="nav-item <?= $page === 'cruises' ? 'active' : '' ?>">
                    <i class="fas fa-ship"></i>
                    <span>Cruises</span>
                </a>
                <a href="?page=flight-bookings" class="nav-item <?= $page === 'flight-bookings' ? 'active' : '' ?>">
                    <i class="fas fa-plane-departure"></i>
                    <span>Flight Bookings</span>
                </a>
                <a href="?page=premium-services" class="nav-item <?= $page === 'premium-services' ? 'active' : '' ?>">
                    <i class="fas fa-hotel"></i>
                    <span>Hotel Services</span>
                </a>
                <a href="?page=experiences" class="nav-item <?= $page === 'experiences' ? 'active' : '' ?>">
                    <i class="fas fa-mountain"></i>
                    <span>Experiences</span>
                </a>
            </div>
        </div>

        <div class="admin-main">
            <div class="top-bar">
                <div class="left-section">
                    <button class="menu-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><i class="fas fa-edit"></i>
                        <?= $page === 'premium-services' ? 'Hotel Services' : ucfirst(str_replace('-', ' ', $page)) ?>
                    </h1>
                </div>
                <div class="admin-actions">
                    <a href="partner-dashboard.php" class="back-to-dashboard">
                        <i class="fas fa-tachometer-alt"></i> <span>Back to Dashboard</span>
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message success"><i class="fas fa-check-circle"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="auto-save-indicator" id="autoSaveIndicator">
                <i class="fas fa-save"></i> Draft saved
            </div>

            <!-- Visa Assistance Section -->
            <?php if ($page === 'visa-assistance'): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-passport"></i> Visa Assistance Services</h2>
                        <button class="add-btn" onclick="openVisaModal()">
                            <i class="fas fa-plus"></i> Add New Visa Service
                        </button>
                    </div>

                    <div class="cards-grid">
                        <?php if (empty($visas)): ?>
                            <div class="message info">No visa assistance services yet. Click "Add New Visa Service" to create your first one.</div>
                        <?php else: ?>
                        <?php foreach ($visas as $visa): ?>
                            <div class="content-card">
                                <div class="card-preview"
                                    style="background-image: url('<?= $visa['icon_type'] === 'image' || $visa['icon_type'] === 'upload' ? (!empty($visa['icon_value']) ? (strpos($visa['icon_value'], 'http') === 0 ? $visa['icon_value'] : assetUrl($visa['icon_value'])) : assetUrl('assets/img/placeholder.jpg')) : '' ?>'); background-color: #f8f9fa;">
                                    <?php if ($visa['icon_type'] === 'icon'): ?>
                                        <div
                                            style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 3rem; color: #003580;">
                                            <i class="fas <?= $visa['icon_value'] ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="badge">
                                        <?= htmlspecialchars($visa['category']) ?>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <div class="card-title">
                                        <?= htmlspecialchars($visa['title']) ?>
                                    </div>
                                    <div class="card-meta">
                                        <span><i class="fas fa-tag"></i>
                                            <?= $visa['currency'] ?>
                                            <?= number_format($visa['price'], 2) ?>
                                        </span>
                                        <span><i class="fas fa-clock"></i>
                                            <?= htmlspecialchars($visa['processing_time']) ?>
                                        </span>
                                    </div>
                                    <div class="status-badge <?= $visa['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $visa['is_active'] ? 'Active' : 'Inactive' ?>
                                    </div>
                                    <div class="card-actions">
                                        <button class="edit-card-btn"
                                            onclick="editVisa(<?= htmlspecialchars(json_encode($visa)) ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="delete-card-btn"
                                            onclick="deleteItem('visa', <?= $visa['id'] ?>, '<?= addslashes($visa['title']) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cruises Section (Advanced) -->
            <?php if ($page === 'cruises'): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-ship"></i> Cruise Inventory</h2>
                        <button class="add-btn" onclick="openAdvancedCruiseModal()">
                            <i class="fas fa-plus"></i> Add New Cruise
                        </button>
                    </div>

                    <div class="stats-grid"
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(0,53,128,0.1); display: flex; align-items: center; justify-content: center; color: #003580; font-size: 1.5rem;">
                                <i class="fas fa-ship"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Total</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count($cruises) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(22,163,74,0.1); display: flex; align-items: center; justify-content: center; color: #16a34a; font-size: 1.5rem;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Active</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($cruises, function ($c) {
                                        return $c['is_published'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(217,119,6,0.1); display: flex; align-items: center; justify-content: center; color: #d97706; font-size: 1.5rem;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Featured</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($cruises, function ($c) {
                                        return $c['is_featured'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card"
                        style="background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #f1f5f9;">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Image</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Cruise Details</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Schedule</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Price</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Status</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cruiseTableBody">
                                    <?php if (empty($cruises)): ?>
                                        <tr>
                                            <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">No cruise
                                                packages found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cruises as $cruise): ?>
                                            <tr data-item-id="<?= $cruise['id'] ?>" style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 15px 20px;">
                                                    <?php if (!empty($cruise['featured_image'])): ?>
                                                        <img src="<?= assetUrl($cruise['featured_image']) ?>"
                                                            style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0;">
                                                    <?php else: ?>
                                                        <div style="width: 60px; height: 60px; border-radius: 8px; background: #e0f2fe; display: flex; align-items: center; justify-content: center; color: #0369a1;">
                                                            <i class="fas fa-ship"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #0f172a;">
                                                        <?= htmlspecialchars($cruise['title']) ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: #64748b;">Code:
                                                        <?= htmlspecialchars($cruise['cruise_code']) ?> |
                                                        <?= htmlspecialchars($cruise['ship_name']) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-size: 0.85rem; color: #0f172a;"><i
                                                            class="far fa-calendar-alt"></i>
                                                        <?= $cruise['departure_date'] ?: 'N/A' ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: #64748b;">
                                                        <?= htmlspecialchars($cruise['duration']) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #003580;">
                                                        ₱
                                                        <?= number_format($cruise['base_price'], 2) ?>
                                                    </div>
                                                    <?php if ($cruise['promo_price'] > 0): ?>
                                                        <div style="font-size: 0.75rem; color: #dc2626; font-weight: 600;">Promo:
                                                            ₱
                                                            <?= number_format($cruise['promo_price'], 2) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <span class="status-badge"
                                                        style="padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; 
                                                    <?= $cruise['status'] === 'Available' ? 'background: #dcfce7; color: #16a34a;' : ($cruise['status'] === 'Full' ? 'background: #fee2e2; color: #dc2626;' : 'background: #f1f5f9; color: #64748b;') ?>">
                                                        <?= $cruise['status'] ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="display: flex; gap: 8px;">
                                                        <button class="edit-btn" onclick="editAdvancedCruise(<?= $cruise['id'] ?>)"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fef3c7; color: #d97706; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s;"><i
                                                                class="fas fa-edit"></i></button>
                                                        <button class="delete-btn"
                                                            onclick="deleteAdvancedCruise(<?= $cruise['id'] ?>, '<?= addslashes($cruise['title']) ?>')"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fee2e2; color: #dc2626; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s;"><i
                                                                class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Flight Bookings Section -->
            <?php if ($page === 'flight-bookings'): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-plane-departure"></i> Flight Inventory</h2>
                        <button class="add-btn" onclick="openServiceModal('flight')">
                            <i class="fas fa-plus"></i> Add New Flight Booking
                        </button>
                    </div>

                    <div class="stats-grid"
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(46,125,50,0.1); display: flex; align-items: center; justify-content: center; color: #2e7d32; font-size: 1.5rem;">
                                <i class="fas fa-plane"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Total</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count($flight_packages) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(0,53,128,0.1); display: flex; align-items: center; justify-content: center; color: #003580; font-size: 1.5rem;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Active</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($flight_packages, function ($f) {
                                        return $f['is_active'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(217,119,6,0.1); display: flex; align-items: center; justify-content: center; color: #d97706; font-size: 1.5rem;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Featured</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($flight_packages, function ($f) {
                                        return $f['is_featured'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card"
                        style="background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #f1f5f9;">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Image</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Flight Details</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Schedule</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Price</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Status</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($flight_packages)): ?>
                                        <tr>
                                            <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">No flight
                                                packages found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($flight_packages as $service): ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 15px 20px;">
                                                    <?php if ($service['featured_image']): ?>
                                                        <img src="<?= assetUrl($service['featured_image']) ?>"
                                                            style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div
                                                            style="width: 50px; height: 50px; border-radius: 8px; background: #e8f5e9; display: flex; align-items: center; justify-content: center; color: #2e7d32;">
                                                            <i class="<?= htmlspecialchars($service['icon_class']) ?>"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #0f172a;">
                                                        <?= htmlspecialchars($service['title']) ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: #64748b;">
                                                        <?= htmlspecialchars($service['badge_text']) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-size: 0.85rem; color: #0f172a;"><i class="far fa-clock"></i>
                                                        <?= htmlspecialchars($service['duration']) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #003580;">
                                                        <?= $service['currency'] ?>
                                                        <?= number_format($service['price'], 2) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <span class="status-badge"
                                                        style="padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; <?= $service['is_active'] ? 'background: #dcfce7; color: #16a34a;' : 'background: #fee2e2; color: #dc2626;' ?>">
                                                        <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="display: flex; gap: 8px;">
                                                        <button class="edit-btn"
                                                            onclick="editService(<?= htmlspecialchars(json_encode($service)) ?>)"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fef3c7; color: #d97706; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i
                                                                class="fas fa-edit"></i></button>
                                                        <button class="delete-btn"
                                                            onclick="deleteItem('site_service', <?= $service['id'] ?>, '<?= addslashes($service['title']) ?>')"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fee2e2; color: #dc2626; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i
                                                                class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Premium Services Section -->
            <?php if ($page === 'premium-services'): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-hotel"></i> Hotel Inventory</h2>
                        <button class="add-btn" onclick="openServiceModal('premium')">
                            <i class="fas fa-plus"></i> Add New Hotel Service
                        </button>
                    </div>

                    <div class="stats-grid"
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(249,168,37,0.1); display: flex; align-items: center; justify-content: center; color: #f9a825; font-size: 1.5rem;">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Total</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count($premium_services) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(0,53,128,0.1); display: flex; align-items: center; justify-content: center; color: #003580; font-size: 1.5rem;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Active</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($premium_services, function ($p) {
                                        return $p['is_active'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(217,119,6,0.1); display: flex; align-items: center; justify-content: center; color: #d97706; font-size: 1.5rem;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Featured</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($premium_services, function ($p) {
                                        return $p['is_featured'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card"
                        style="background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #f1f5f9;">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Image</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Service Details</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Duration</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Price</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Status</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($premium_services)): ?>
                                        <tr>
                                            <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">No
                                                premium services found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($premium_services as $service): ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 15px 20px;">
                                                    <?php if ($service['featured_image']): ?>
                                                        <img src="<?= assetUrl($service['featured_image']) ?>"
                                                            style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div
                                                            style="width: 50px; height: 50px; border-radius: 8px; background: #fff9c4; display: flex; align-items: center; justify-content: center; color: #f9a825;">
                                                            <i class="<?= htmlspecialchars($service['icon_class']) ?>"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #0f172a;">
                                                        <?= htmlspecialchars($service['title']) ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: #64748b;">
                                                        <?= htmlspecialchars($service['badge_text']) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-size: 0.85rem; color: #0f172a;"><i class="far fa-clock"></i>
                                                        <?= htmlspecialchars($service['duration']) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #003580;">
                                                        <?= $service['currency'] ?>
                                                        <?= number_format($service['price'], 2) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <span class="status-badge"
                                                        style="padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; <?= $service['is_active'] ? 'background: #dcfce7; color: #16a34a;' : 'background: #fee2e2; color: #dc2626;' ?>">
                                                        <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="display: flex; gap: 8px;">
                                                        <button class="edit-btn"
                                                            onclick="editService(<?= htmlspecialchars(json_encode($service)) ?>)"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fef3c7; color: #d97706; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i
                                                                class="fas fa-edit"></i></button>
                                                        <button class="delete-btn"
                                                            onclick="deleteItem('site_service', <?= $service['id'] ?>, '<?= addslashes($service['title']) ?>')"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fee2e2; color: #dc2626; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i
                                                                class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Experiences Section -->
            <?php if ($page === 'experiences'): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-mountain"></i> Experience Inventory</h2>
                        <div style="display: flex; gap: 10px;">
                            <button class="add-btn" onclick="openServiceModal('experience')">
                                <i class="fas fa-plus"></i> Add New Experience
                            </button>
                        </div>
                    </div>

                    <div class="stats-grid"
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(20, 196, 146, 0.1); display: flex; align-items: center; justify-content: center; color: #14c492; font-size: 1.5rem;">
                                <i class="fas fa-mountain"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Total</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count($experience_services) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(20, 196, 146, 0.1); display: flex; align-items: center; justify-content: center; color: #14c492; font-size: 1.5rem;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Active</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($experience_services, function ($e) {
                                        return $e['is_active'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                        <div class="stat-card"
                            style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;">
                            <div class="stat-icon"
                                style="width: 50px; height: 50px; border-radius: 10px; background: rgba(217,119,6,0.1); display: flex; align-items: center; justify-content: center; color: #d97706; font-size: 1.5rem;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 0.8rem; color: #64748b; text-transform: uppercase;">Featured</h3>
                                <p style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">
                                    <?= count(array_filter($experience_services, function ($e) {
                                        return $e['is_featured'];
                                    })) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card"
                        style="background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #f1f5f9;">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Image</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Experience Details</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Schedule</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Price</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Status</th>
                                        <th
                                            style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($experience_services)): ?>
                                        <tr>
                                            <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">No
                                                experiences found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($experience_services as $service): ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 15px 20px;">
                                                    <?php if ($service['featured_image']): ?>
                                                        <img src="<?= assetUrl($service['featured_image']) ?>"
                                                            style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div
                                                            style="width: 50px; height: 50px; border-radius: 8px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; color: #14c492;">
                                                            <i
                                                                class="<?= htmlspecialchars($service['icon_class'] ?: 'fas fa-mountain') ?>"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #0f172a;">
                                                        <?= htmlspecialchars($service['title']) ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: #64748b;">
                                                        <?= htmlspecialchars($service['badge_text'] ?: 'Experience') ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-size: 0.85rem; color: #0f172a;"><i class="far fa-clock"></i>
                                                        <?= htmlspecialchars($service['duration']) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="font-weight: 700; color: #14c492;">
                                                        <?= $service['currency'] ?>
                                                        <?= number_format($service['price'], 2) ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <span class="status-badge"
                                                        style="padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; <?= $service['is_active'] ? 'background: #dcfce7; color: #16a34a;' : 'background: #fee2e2; color: #dc2626;' ?>">
                                                        <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 15px 20px;">
                                                    <div style="display: flex; gap: 8px;">
                                                        <button class="edit-btn"
                                                            onclick="editService(<?= htmlspecialchars(json_encode($service)) ?>)"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fef3c7; color: #d97706; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i
                                                                class="fas fa-edit"></i></button>
                                                        <button class="delete-btn"
                                                            onclick="deleteItem('site_service', <?= $service['id'] ?>, '<?= addslashes($service['title']) ?>')"
                                                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #fee2e2; color: #dc2626; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i
                                                                class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Site Service Modal (Flights, Premium, Experiences) -->
            <div id="serviceModal" class="modal">
                <div class="modal-content" style="max-width: 900px;">
                    <div class="modal-header">
                        <h3 id="serviceModalTitle">Add Service</h3>
                        <span class="close-modal" onclick="closeModal('serviceModal')">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="tabs">
                            <button class="tab-btn active" onclick="switchServiceTab(event, 's-tab-general')">General
                                Info</button>
                            <button class="tab-btn" onclick="switchServiceTab(event, 's-tab-details')">Details &
                                Highlights</button>
                            <button class="tab-btn"
                                onclick="switchServiceTab(event, 's-tab-itinerary')">Itinerary</button>
                            <button class="tab-btn" onclick="switchServiceTab(event, 's-tab-pricing')">Pricing &
                                Slots</button>
                            <button class="tab-btn"
                                onclick="switchServiceTab(event, 's-tab-schedule')">Schedule</button>
                            <button class="tab-btn"
                                onclick="switchServiceTab(event, 's-tab-policies')">Policies</button>
                            <button class="tab-btn" onclick="switchServiceTab(event, 's-tab-gallery')">Gallery</button>
                        </div>

                        <form method="POST" id="serviceForm" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="action" value="save_site_service">
                            <input type="hidden" name="id" id="service_id" value="0">
                            <input type="hidden" name="service_type" id="service_type">
                            <input type="hidden" name="old_featured_image" id="service_old_featured_image">
                            <input type="hidden" name="remove_gallery_images" id="service_remove_gallery_images"
                                value="">

                            <!-- General Info Tab -->
                            <div id="s-tab-general" class="tab-content active">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Service Title *</label>
                                        <input type="text" name="title" id="service_title" required
                                            placeholder="e.g. Luxury Japan Experience">
                                    </div>
                                    <div class="form-group">
                                        <label>Service Code / ID *</label>
                                        <input type="text" name="service_code" id="service_service_code" required
                                            placeholder="e.g. JP-LX-001">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Short Description (Card View)</label>
                                    <textarea name="description" id="service_description" rows="2"
                                        placeholder="Brief summary for listing cards..."></textarea>
                                </div>

                                <input type="hidden" name="full_description" id="service_full_description" value="">

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <input type="text" name="category" id="service_category"
                                            placeholder="e.g. Adventure, Luxury, Family">
                                    </div>
                                    <div class="form-group">
                                        <label>Tags (comma separated)</label>
                                        <input type="text" name="tags" id="service_tags"
                                            placeholder="e.g. Best Seller, Summer, Promo">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Badge Text (e.g. VIP ACCESS)</label>
                                        <input type="text" name="badge_text" id="service_badge_text">
                                    </div>
                                    <div class="form-group">
                                        <label>Icon Class (FontAwesome)</label>
                                        <input type="text" name="icon_class" id="service_icon_class"
                                            placeholder="fas fa-plane">
                                    </div>
                                </div>

                                <div class="form-row"
                                    style="background: #f8fbff; padding: 15px; border-radius: 10px; margin-top: 10px; border: 1px solid #eef2f7;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #003580;">Status Controls</label>
                                        <div style="display: flex; gap: 24px; margin-top: 8px;">
                                            <div class="toggle-switch-group">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="is_active" id="service_is_active" checked>
                                                    <span class="toggle-switch-slider"></span>
                                                </label>
                                                <label for="service_is_active" class="toggle-switch-label">Published</label>
                                            </div>
                                            <div class="toggle-switch-group">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="is_featured" id="service_is_featured">
                                                    <span class="toggle-switch-slider"></span>
                                                </label>
                                                <label for="service_is_featured" class="toggle-switch-label">Featured</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Display Order</label>
                                        <input type="number" name="display_order" id="service_display_order" value="0">
                                    </div>
                                </div>
                            </div>

                            <!-- Details & Highlights Tab -->
                            <div id="s-tab-details" class="tab-content">
                                <div class="form-group">
                                    <label>Highlights (One per line)</label>
                                    <textarea name="highlights" id="service_highlights" rows="4"
                                        placeholder="Visit famous landmarks&#10;Expert local guide&#10;All entrance fees included"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Amenities (One per line - for Hotels)</label>
                                    <textarea name="amenities" id="service_amenities" rows="3"
                                        placeholder="Free Wi-Fi&#10;Swimming Pool&#10;Spa & Wellness&#10;Fitness Center"></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Inclusions (One per line)</label>
                                        <textarea name="inclusions" id="service_inclusions" rows="6"
                                            placeholder="Roundtrip Airfare&#10;Hotel Accommodations&#10;Daily Breakfast"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Exclusions (One per line)</label>
                                        <textarea name="exclusions" id="service_exclusions" rows="6"
                                            placeholder="Personal Expenses&#10;Optional Tours&#10;Travel Insurance"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Itinerary Tab -->
                            <div id="s-tab-itinerary" class="tab-content">
                                <div id="service-itinerary-list">
                                    <!-- Days added here -->
                                </div>
                                <button type="button" class="add-day-btn"
                                    style="margin-top: 10px; width: 100%; border: 1px dashed #003580; background: #f8fbff; color: #003580;"
                                    onclick="addServiceItineraryDay()">
                                    <i class="fas fa-plus"></i> Add Itinerary Day
                                </button>
                            </div>

                            <!-- Pricing & Slots Tab -->
                            <div id="s-tab-pricing" class="tab-content">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Currency</label>
                                        <select name="currency" id="service_currency">
                                            <option value="₱">₱ PHP</option>
                                            <option value="$">$ USD</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Price *</label>
                                        <input type="number" name="price" id="service_price" step="0.01" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Available Slots / Capacity</label>
                                        <input type="number" name="available_slots" id="service_available_slots"
                                            value="0">
                                    </div>
                                    <div class="form-group">
                                        <label>Availability Status</label>
                                        <select name="status_text" id="service_status_text">
                                            <option value="Available">Available</option>
                                            <option value="Limited Slots">Limited Slots</option>
                                            <option value="Sold Out">Sold Out</option>
                                            <option value="On Request">On Request</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Tab -->
                            <div id="s-tab-schedule" class="tab-content">
                                <div class="form-group">
                                    <label>Duration Summary</label>
                                    <input type="text" name="duration" id="service_duration"
                                        placeholder="e.g. 7 Days / 6 Nights">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Booking Deadline</label>
                                        <input type="date" name="booking_deadline" id="service_booking_deadline">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Departure Date</label>
                                        <input type="date" name="departure_date" id="service_departure_date">
                                    </div>
                                    <div class="form-group">
                                        <label>Return Date</label>
                                        <input type="date" name="return_date" id="service_return_date">
                                    </div>
                                </div>
                            </div>

                            <!-- Policies Tab -->
                            <div id="s-tab-policies" class="tab-content">
                                <div class="form-group">
                                    <label>Required Documents</label>
                                    <textarea name="required_documents" id="service_required_documents" rows="3"
                                        placeholder="e.g. Passport, Visa, Vaccination Card"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Travel Requirements</label>
                                    <textarea name="travel_requirements" id="service_travel_requirements" rows="3"
                                        placeholder="e.g. RT-PCR Test, Quarantine protocols"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Cancellation Policy</label>
                                    <textarea name="cancellation_policy" id="service_cancellation_policy" rows="3"
                                        placeholder="e.g. Non-refundable, 50% refund before 30 days"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Terms & Conditions</label>
                                    <textarea name="terms_conditions" id="service_terms_conditions" rows="3"></textarea>
                                </div>
                            </div>

                            <!-- Gallery Tab -->
                            <div id="s-tab-gallery" class="tab-content">
                                <div class="gallery-top-grid">
                                    <div class="featured-upload-section">
                                        <div class="upload-badge-container">
                                            <label style="font-weight: 700; color: #0f172a; margin: 0;">Featured
                                                Photo</label>
                                            <span class="upload-counter-badge" style="background: #14c492;">Main
                                                Cover</span>
                                        </div>
                                        <div id="service_featured_preview" class="featured-preview-frame"
                                            onclick="document.getElementById('service_featured_input').click()">
                                            <div style="text-align: center; color: #64748b;">
                                                <i class="fas fa-cloud-upload-alt"
                                                    style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                                <span style="font-size: 0.85rem; font-weight: 600;">Click to Upload
                                                    Cover</span>
                                            </div>
                                        </div>
                                        <input type="file" name="featured_image" id="service_featured_input"
                                            style="display: none;" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                            onchange="previewImage(this, 'service_featured_preview')">
                                    </div>

                                    <div class="gallery-upload-section">
                                        <div class="upload-badge-container">
                                            <label style="font-weight: 700; color: #0f172a; margin: 0;">Additional
                                                Photos</label>
                                            <span class="upload-counter-badge" id="service_gallery_count">0 / 5
                                                Photos</span>
                                        </div>
                                        <div class="preview-grid-layout" id="service_gallery_preview">
                                            <div class="grid-preview-item"
                                                style="grid-column: span 2; grid-row: span 2; display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 2px dashed #cbd5e1; cursor: pointer;"
                                                onclick="document.getElementById('service_gallery_input').click()">
                                                <div style="text-align: center; color: #64748b;">
                                                    <i class="fas fa-plus-circle"
                                                        style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                                                    <div style="font-size: 0.75rem; font-weight: 600;">Add Gallery</div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="file" name="gallery[]" id="service_gallery_input" multiple
                                            style="display: none;" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                            onchange="previewServiceGallery(this)">
                                    </div>
                                </div>
                            </div>

                            <div
                                style="position: sticky; bottom: -20px; background: white; padding: 20px 0; border-top: 1px solid #eee; margin-top: 20px; z-index: 10;">
                                <button type="submit" class="save-btn"
                                    style="width: 100%; background: #003580; height: 50px; font-size: 1rem;">
                                    <i class="fas fa-save"></i> Save All Service Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>


            <!-- Advanced Cruise Modal -->
            <div id="advancedCruiseModal" class="modal">
                <div class="modal-content" style="max-width: 900px;">
                    <div class="modal-header">
                        <h3 id="advancedCruiseModalTitle">Manage Cruise Package</h3>
                        <span class="close-modal" onclick="closeModal('advancedCruiseModal')">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="tabs">
                            <button class="tab-btn active" onclick="switchTab(event, 'tab-general')">General Info</button>
                            <button class="tab-btn" onclick="switchTab(event, 'tab-ship')">Ship & Route</button>
                            <button class="tab-btn" onclick="switchTab(event, 'tab-itinerary')">Itinerary</button>
                            <button class="tab-btn" onclick="switchTab(event, 'tab-pricing')">Pricing & Slots</button>
                            <button class="tab-btn" onclick="switchTab(event, 'tab-schedule')">Schedule</button>
                            <button class="tab-btn" onclick="switchTab(event, 'tab-policies')">Policies</button>
                            <button class="tab-btn" onclick="switchTab(event, 'tab-gallery')">Gallery</button>
                        </div>

                        <form id="advancedCruiseForm" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="action" value="save_advanced_cruise">
                            <input type="hidden" name="id" id="advanced_cruise_id" value="0">
                            <input type="hidden" name="old_featured_image" id="old_featured_image">

                            <!-- General Tab -->
                            <div id="tab-general" class="tab-content active">
                                <div class="form-grid"
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label>Cruise Title *</label>
                                        <input type="text" name="title" id="cruise_title" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Cruise Code / ID *</label>
                                        <input type="text" name="cruise_code" id="cruise_code" required>
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Short Description</label>
                                        <textarea name="short_description" id="cruise_short_description"
                                            rows="2"></textarea>
                                    </div>
                                    <input type="hidden" name="full_description" id="cruise_full_description" value="">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <input type="text" name="category" id="cruise_category"
                                            placeholder="e.g. Luxury, Family, Expedition">
                                    </div>
                                    <div class="form-group">
                                        <label>Tags (comma separated)</label>
                                        <input type="text" name="tags" id="cruise_tags"
                                            placeholder="e.g. Best Seller, Summer, Promo">
                                    </div>

                                    <div class="form-group">
                                        <label>Status Controls</label>
                                        <div style="display: flex; gap: 24px; align-items: center; margin-top: 10px;">
                                            <div class="toggle-switch-group">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="is_published" id="cruise_is_published" value="1" checked>
                                                    <span class="toggle-switch-slider"></span>
                                                </label>
                                                <label for="cruise_is_published" class="toggle-switch-label">Published</label>
                                            </div>
                                            <div class="toggle-switch-group">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="is_featured" id="cruise_is_featured" value="1">
                                                    <span class="toggle-switch-slider"></span>
                                                </label>
                                                <label for="cruise_is_featured" class="toggle-switch-label">Featured</label>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Ship Tab -->
                            <div id="tab-ship" class="tab-content">
                                <div class="form-grid"
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label>Ship Name</label>
                                        <input type="text" name="ship_name" id="cruise_ship_name">
                                    </div>
                                    <div class="form-group">
                                        <label>Cruise Line</label>
                                        <input type="text" name="cruise_line" id="cruise_cruise_line">
                                    </div>
                                    <div class="form-group">
                                        <label>Departure Port</label>
                                        <input type="text" name="departure_port" id="cruise_departure_port">
                                    </div>
                                    <div class="form-group">
                                        <label>Destination Type</label>
                                        <select name="destination_type" id="cruise_destination_type">
                                            <option value="International">International</option>
                                            <option value="Local">Local / Domestic</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Destinations / Route</label>
                                        <input type="text" name="destinations" id="cruise_destinations"
                                            placeholder="e.g. Singapore, Penang, Phuket">
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Ship Description</label>
                                        <textarea name="ship_description" id="cruise_ship_description"
                                            rows="3"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Room Types (Inside, Balcony, etc.)</label>
                                        <input type="text" name="room_types" id="cruise_room_types"
                                            placeholder="e.g. Inside, Oceanview, Balcony, Suite">
                                    </div>
                                    <div class="form-group">
                                        <label>Amenities (Pool, Spa, etc.)</label>
                                        <input type="text" name="amenities" id="cruise_amenities"
                                            placeholder="e.g. Pool, Spa, Casino, Gym">
                                    </div>
                                </div>
                            </div>

                            <!-- Itinerary Tab -->
                            <div id="tab-itinerary" class="tab-content">
                                <div id="itinerary-list">
                                    <!-- Days added here -->
                                </div>
                                <button type="button" class="add-day-btn"
                                    style="margin-top: 10px; width: 100%; border: 1px dashed #003580; background: #f8fbff; color: #003580;"
                                    onclick="addItineraryDay()">
                                    <i class="fas fa-plus"></i> Add Itinerary Day
                                </button>
                            </div>

                            <!-- Pricing Tab -->
                            <div id="tab-pricing" class="tab-content">
                                <div class="form-grid"
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label>Base Price (₱) *</label>
                                        <input type="number" name="base_price" id="cruise_base_price" step="0.01"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label>Promo Price (₱)</label>
                                        <input type="number" name="promo_price" id="cruise_promo_price" step="0.01">
                                    </div>
                                    <div class="form-group">
                                        <label>Available Slots</label>
                                        <input type="number" name="available_slots" id="cruise_available_slots">
                                    </div>
                                    <div class="form-group">
                                        <label>Booking Status</label>
                                        <select name="status" id="cruise_status">
                                            <option value="Available">Available</option>
                                            <option value="Full">Full / Sold Out</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Inclusions (One per line)</label>
                                        <textarea name="inclusions" id="cruise_inclusions" rows="4"></textarea>
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Exclusions (One per line)</label>
                                        <textarea name="exclusions" id="cruise_exclusions" rows="4"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Tab -->
                            <div id="tab-schedule" class="tab-content">
                                <div class="form-grid"
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label>Duration (e.g. 5D/4N)</label>
                                        <input type="text" name="duration" id="cruise_duration_val">
                                    </div>
                                    <div class="form-group">
                                        <label>Departure Date</label>
                                        <input type="date" name="departure_date" id="cruise_departure_date">
                                    </div>
                                    <div class="form-group">
                                        <label>Return Date</label>
                                        <input type="date" name="return_date" id="cruise_return_date">
                                    </div>
                                    <div class="form-group">
                                        <label>Booking Deadline</label>
                                        <input type="date" name="booking_deadline" id="cruise_booking_deadline">
                                    </div>
                                </div>
                            </div>

                            <!-- Policies Tab -->
                            <div id="tab-policies" class="tab-content">
                                <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Travel Requirements</label>
                                        <textarea name="travel_requirements" id="cruise_travel_requirements"
                                            rows="3"></textarea>
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Cancellation Policy</label>
                                        <textarea name="cancellation_policy" id="cruise_cancellation_policy"
                                            rows="3"></textarea>
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 20px;">
                                        <label>Terms & Conditions</label>
                                        <textarea name="terms_conditions" id="cruise_terms_conditions"
                                            rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Gallery Tab -->
                            <div id="tab-gallery" class="tab-content">
                                <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                                    <div class="form-group" style="margin-bottom: 25px;">
                                        <label style="font-weight: 700; color: #003580; margin-bottom: 5px; display: block; font-size: 1.1rem;">Featured Photo / Cover Image</label>
                                        <span style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 10px;">This is the main image that will be shown on cards, search results, and at the top of detail pages.</span>
                                        <input type="file" name="featured_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                            onchange="previewImage(this, 'featured_preview_box')">
                                        <div id="featured_preview_box" class="image-preview"
                                            style="height: 180px; margin-top:10px; border-radius: 12px; border: 1px solid #cbd5e1; background-size: cover; background-position: center;"></div>
                                    </div>
                                    
                                    <div class="gallery-upload-section" style="border-top: 1px solid #e2e8f0; padding-top: 25px;">
                                        <div class="upload-badge-container" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                            <label style="font-weight: 700; color: #003580; margin: 0; font-size: 1.1rem;">Cruise Image Gallery</label>
                                            <span class="upload-counter-badge" id="cruise_gallery_count">0 Photos</span>
                                        </div>
                                        <span style="font-size: 0.85rem; color: #64748b; display: block; margin-top: -10px; margin-bottom: 20px;">Add supplementary photos to showcase ship facilities, cabin classes, destinations, and activities.</span>
                                        
                                        <div class="preview-grid-layout" id="cruise_gallery_preview">
                                            <div class="grid-preview-item"
                                                style="grid-column: span 2; grid-row: span 2; display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 2px dashed #cbd5e1; cursor: pointer;"
                                                onclick="document.getElementById('cruise_gallery_input').click()">
                                                <div style="text-align: center; color: #64748b;">
                                                    <i class="fas fa-plus-circle"
                                                        style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                                                    <div style="font-size: 0.75rem; font-weight: 600;">Add Gallery</div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="file" name="gallery[]" id="cruise_gallery_input" multiple
                                            style="display: none;" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                            onchange="previewCruiseGallery(this)">
                                        <input type="hidden" name="remove_gallery_images" id="cruise_remove_gallery_images" value="">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="save-btn"
                                style="margin-top: 30px; font-size: 1rem; padding: 15px; background: #003580;">
                                <i class="fas fa-save"></i> Save Cruise Package
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Flash Deals Section -->
            <?php if ($page === 'flash-deals'): ?>
                <div id="flashDealsSection" class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bolt"></i> Flash Deals</h2>
                        <button class="add-btn" onclick="openFlashDealModal()">
                            <i class="fas fa-plus"></i> Add Flash Deal
                        </button>
                    </div>
                    <div class="cards-grid">
                        <?php if (empty($flash_deals)): ?>
                            <div class="message info">No flash deals yet. Click "Add Flash Deal" to create your first one.</div>
                        <?php else: ?>
                            <?php foreach ($flash_deals as $deal):
                                $preview_image = !empty($deal['image_path']) ? assetUrl($deal['image_path']) : 'https://via.placeholder.com/300x150?text=No+Image';
                                $discount_text = $deal['discount_percent'] ? "⚡ {$deal['discount_percent']}% off" : ($deal['badge_text'] ?? 'Flash Deal');
                                $is_expired = !empty($deal['promo_end']) && strtotime($deal['promo_end'] . ' 23:59:59') < time();
                                ?>
                                <div class="content-card" data-item-type="flash_deal" data-item-id="<?= $deal['id'] ?>" <?= $is_expired ? 'style="opacity: 0.7; filter: grayscale(80%); border-color: #ccc;"' : '' ?> >
                                    <div class="card-preview" style="background-image: url('<?= $preview_image ?>');">
                                        <span class="badge" <?= $is_expired ? 'style="background: #6c757d;"' : '' ?>>
                                            <?= $is_expired ? 'Expired' : htmlspecialchars($discount_text) ?>
                                        </span>
                                    </div>
                                    <div class="card-content">
                                        <h3 class="card-title">
                                            <?= htmlspecialchars($deal['title']) ?>
                                        </h3>
                                        <div class="card-meta">
                                            <span><i class="fas fa-tag"></i>
                                                <?= htmlspecialchars($deal['category'] ?? 'General') ?>
                                            </span>
                                            <span><i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($deal['location'] ?? 'Various') ?>
                                            </span>
                                        </div>
                                        <div class="card-meta">
                                            <span><i class="fas fa-tag"></i>
                                                <?= htmlspecialchars($deal['currency'] ?? '₱') ?>
                                                <?= number_format($deal['price'], 2) ?>
                                            </span>
                                            <?php if ($deal['original_price']): ?>
                                                <span><i class="fas fa-tag"></i>
                                                    <s>
                                                        <?= htmlspecialchars($deal['currency'] ?? '₱') ?>
                                                        <?= number_format($deal['original_price'], 2) ?>
                                                    </s></span>
                                            <?php endif; ?>
                                            <?php if ($deal['discount_percent']): ?>
                                                <span><i class="fas fa-percent"></i> Save
                                                    <?= $deal['discount_percent'] ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-meta">
                                            <?php if ($deal['duration']): ?>
                                                <span><i class="fas fa-clock"></i>
                                                    <?= htmlspecialchars($deal['duration']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-meta">
                                            <span
                                                class="status-badge <?= $deal['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $deal['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="card-actions">
                                            <button class="edit-card-btn"
                                                onclick="editFlashDeal(<?= htmlspecialchars(json_encode($deal)) ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="delete-card-btn"
                                                onclick="deleteItem('flash_deal', <?= $deal['id'] ?>, '<?= htmlspecialchars($deal['title']) ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="flashDealModal" class="modal">
                    <div class="modal-content" style="max-width: 900px;">
                        <div class="modal-header">
                            <h3 id="flashDealModalTitle">Add Flash Deal</h3>
                            <span class="close-modal" onclick="closeModal('flashDealModal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="tabs">
                                <button class="tab-btn active" onclick="switchFlashDealTab(event, 'fd-tab-general')">General
                                    Info</button>
                                <button class="tab-btn" onclick="switchFlashDealTab(event, 'fd-tab-pricing')">Pricing &
                                    Schedule</button>
                                <button class="tab-btn" onclick="switchFlashDealTab(event, 'fd-tab-details')">Itinerary &
                                    Details</button>
                                <button class="tab-btn"
                                    onclick="switchFlashDealTab(event, 'fd-tab-gallery')">Gallery</button>
                            </div>

                            <form method="POST" enctype="multipart/form-data" id="flashDealForm" novalidate>
                                <input type="hidden" name="action" value="save_flash_deal">
                                <input type="hidden" name="id" id="deal_id" value="0">
                                <input type="hidden" name="old_image_1" id="old_image_1">
                                <input type="hidden" name="old_image_2" id="old_image_2">
                                <input type="hidden" name="old_image_3" id="old_image_3">
                                <input type="hidden" name="remove_gallery_images" id="flash_remove_gallery_images" value="">

                                <!-- General Info Tab -->
                                <div id="fd-tab-general" class="tab-content active">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Title *</label>
                                            <input type="text" name="title" id="deal_title" required>
                                        </div>
                                        <div class="form-group" style="display:none;">
                                            <label>Category *</label>
                                            <select name="category" id="deal_category">
                                                <option value="Theme Park">🎢 Theme Parks</option>
                                                <option value="Zoos & aquariums">🦒 Zoos & Aquariums</option>
                                                <option value="Water activities">💧 Water Activities</option>
                                                <option value="Massages">💆 Massages & Spas</option>
                                                <option value="Beach activities">🏖️ Beach Activities</option>
                                                <option value="Cultural tours">🏛️ Cultural Tours</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Location</label>
                                            <input type="text" name="location" id="deal_location"
                                                placeholder="e.g., Santa Rosa, Laguna">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Short Description</label>
                                        <textarea name="description" id="deal_description" rows="4"
                                            placeholder="Brief description that will appear on the package card."></textarea>
                                    </div>
                                </div>

                                <!-- Pricing & Schedule Tab -->
                                <div id="fd-tab-pricing" class="tab-content">
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1;">
                                            <label>Currency</label>
                                            <select name="currency" id="deal_currency" required>
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
                                            <input type="number" name="price" id="deal_price" step="0.01" required>
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Original Price</label>
                                            <input type="number" name="original_price" id="deal_original_price" step="0.01">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label>Discount %</label>
                                            <input type="number" name="discount_percent" id="deal_discount_percent" step="1"
                                                min="0" max="100" placeholder="Auto">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group" style="flex: 2;">
                                            <label>Duration (e.g. 3D/2N)</label>
                                            <input type="text" name="duration" id="deal_duration" placeholder="3D/2N">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Blocked Dates</label>
                                            <input type="text" name="blocked_dates" id="deal_blocked_dates"
                                                class="blocked-dates-picker" placeholder="Select dates...">
                                        </div>
                                    </div>

                                    <!-- Promo Constraints -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Travel Validity Start</label>
                                            <input type="date" name="promo_start" id="deal_promo_start">
                                        </div>
                                        <div class="form-group">
                                            <label>Travel Validity End</label>
                                            <input type="date" name="promo_end" id="deal_promo_end">
                                        </div>
                                        <div class="form-group">
                                            <label>Highlight (Days)</label>
                                            <input type="number" name="highlight_duration" id="deal_highlight_duration"
                                                value="1" min="1">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Blocked Months (Unclickable)</label>
                                        <div class="month-grid">
                                            <?php $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; ?>
                                            <?php foreach ($months as $i => $m): ?>
                                                <label class="month-checkbox">
                                                    <input type="checkbox" name="blocked_months[]" value="<?= $i + 1 ?>"
                                                        class="deal-month-check">
                                                    <?= $m ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Group Size</label>
                                            <input type="text" name="group_size" id="deal_group_size"
                                                placeholder="2-15 pax">
                                        </div>
                                        <div class="form-group" style="display:none;">
                                            <label>Rating (0-5)</label>
                                            <input type="number" name="rating" id="deal_rating" step="0.1" min="0" max="5">
                                        </div>
                                        <div class="form-group" style="display:none;">
                                            <label>Reviews Count</label>
                                            <input type="number" name="reviews" id="deal_reviews">
                                        </div>
                                    </div>
                                </div>

                                <!-- Itinerary & Details Tab -->
                                <div id="fd-tab-details" class="tab-content">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Validity (Optional / Fallback Info)</label>
                                            <input type="text" name="best_season" id="deal_best_season"
                                                placeholder="Year Round">
                                        </div>
                                        <div class="form-group">
                                            <label>Badge Text</label>
                                            <input type="text" name="badge_text" id="deal_badge_text"
                                                placeholder="⚡ Flash Deal">
                                        </div>
                                        <div class="form-group">
                                            <label>Booked Count</label>
                                            <input type="text" name="booked_count" id="deal_booked_count"
                                                placeholder="e.g., 10K+ booked">
                                        </div>
                                    </div>

                                    <!-- Inclusions Section -->
                                    <div class="form-group">
                                        <label>Package Inclusions (one per line)</label>
                                        <textarea name="inclusions" id="deal_inclusions" rows="4"
                                            placeholder="VIP access included&#10;Travel Insurance&#10;English speaking guide&#10;Hotel transfers"></textarea>
                                    </div>

                                    <!-- Hotel Management -->
                                    <div class="form-group">
                                        <label>Hotel Options (Surcharges)</label>
                                        <div id="flashHotelBuilderContainer"></div>
                                        <button type="button" class="add-day-btn" style="background: #4caf50;"
                                            onclick="addHotel('flash')">
                                            <i class="fas fa-plus"></i> Add Hotel Option
                                        </button>
                                        <input type="hidden" name="hotels" id="flash_hotels_json" value="[]">
                                        <small>Add hotels users can choose from. Use price 0 for the default hotel.</small>
                                    </div>

                                    <!-- Exclusions Section -->
                                    <div class="form-group">
                                        <label>Package Exclusions (one per line)</label>
                                        <textarea name="exclusions" id="deal_exclusions" rows="3"
                                            placeholder="Airfare&#10;Personal expenses&#10;Lunch & dinner"></textarea>
                                    </div>

                                    <!-- Itinerary Builder -->
                                    <div class="form-group">
                                        <label>Tour Itinerary</label>
                                        <div id="flashDealItineraryBuilder"></div>
                                        <button type="button" class="add-day-btn" onclick="addFlashDealItineraryDay()">
                                            <i class="fas fa-plus"></i> Add Day
                                        </button>
                                        <input type="hidden" name="itinerary" id="flash_deal_itinerary_data" value="[]">
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Display Order</label>
                                            <input type="number" name="display_order" id="deal_display_order" value="0">
                                        </div>
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="toggle-switch-group">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="is_active" id="deal_is_active" value="1" checked>
                                                    <span class="toggle-switch-slider"></span>
                                                </label>
                                                <label for="deal_is_active" class="toggle-switch-label">Active (show on website)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Remarks and Note</label>
                                        <textarea name="remarks" id="deal_remarks" rows="4"
                                            placeholder="Important notes about the itinerary, requirements, or special conditions..."></textarea>
                                        <small>This will appear after the Tour Itinerary in the booking popup.</small>
                                    </div>
                                </div>

                                <!-- Gallery Tab -->
                                <div id="fd-tab-gallery" class="tab-content">
                                    <div class="form-group">
                                        <label>Main Images (Cover Slides)</label>
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 1 (Main)</label>
                                                <input type="file" name="image_1" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'fd_preview_1')">
                                                <div id="fd_preview_1" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 2 (Optional)</label>
                                                <input type="file" name="image_2" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'fd_preview_2')">
                                                <div id="fd_preview_2" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 3 (Optional)</label>
                                                <input type="file" name="image_3" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'fd_preview_3')">
                                                <div id="fd_preview_3" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-top: 25px;">
                                        <label>Full Photo Gallery (Multi-upload)</label>
                                        <input type="file" name="gallery[]" id="flash_gallery_input" multiple accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                            onchange="previewFlashGallery(this)">
                                        <div id="flash_gallery_preview"
                                            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px;">
                                        </div>
                                    </div>
                                </div>

                                <div
                                    style="position: sticky; bottom: -20px; background: white; padding: 20px 0; border-top: 1px solid #eee; margin-top: 20px; z-index: 10;">
                                    <div class="form-actions">
                                        <button type="submit" class="save-btn"
                                            style="width: 100%; justify-content: center; height: 50px; font-size: 1.1rem;"><i
                                                class="fas fa-save"></i> Save Flash Deal</button>
                                        <button type="button" class="cancel-btn" onclick="closeModal('flashDealModal')"
                                            style="width: 100%; justify-content: center; height: 45px; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; margin-top: 10px; transition: all 0.2s;">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Foreign Destinations Section -->
            <?php if ($page === 'foreign-destinations'): ?>
                <div id="foreignDestinationsSection" class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-globe-asia"></i> Foreign Destinations</h2>
                        <button class="add-btn" onclick="openForeignDestinationModal()">
                            <i class="fas fa-plus"></i> Add Foreign Destination
                        </button>
                    </div>
                    <div class="cards-grid">
                        <?php if (empty($foreign_destinations)): ?>
                            <div class="message info">No foreign destinations yet. Click "Add Foreign Destination" to create
                                your first one.</div>
                        <?php else: ?>
                            <?php foreach ($foreign_destinations as $dest):
                                $preview_image = !empty($dest['image_path']) ? assetUrl($dest['image_path']) : 'https://via.placeholder.com/300x150?text=No+Image';
                                $badge_text = $dest['badge_text'] ?? ($dest['activities_count'] . ' activities');
                                ?>
                                <div class="content-card" data-item-type="foreign_destination" data-item-id="<?= $dest['id'] ?>">
                                    <div class="card-preview" style="background-image: url('<?= $preview_image ?>');">
                                        <?php if (!empty($dest['badge_text'])): ?>
                                            <span class="badge">
                                                <?= htmlspecialchars($dest['badge_text']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-content">
                                        <h3 class="card-title">
                                            <?= htmlspecialchars($dest['name']) ?>
                                        </h3>
                                        <div class="card-meta">
                                            <span><i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars(($dest['city'] ?? '') . ', ' . ($dest['country'] ?? '')) ?>
                                            </span>
                                        </div>
                                        <div class="card-meta">
                                            <span><i class="fas fa-tasks"></i>
                                                <?= $dest['activities_count'] ?? 0 ?>
                                                activities
                                            </span>
                                            <span
                                                class="status-badge <?= $dest['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $dest['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="card-meta">
                                            <span><i class="fas fa-tag"></i>
                                                <?= htmlspecialchars($dest['currency'] ?? '₱') ?>
                                                <?= number_format($dest['price'], 2) ?>
                                            </span>
                                            <span><i class="fas fa-clock"></i>
                                                <?= htmlspecialchars($dest['duration']) ?>
                                            </span>
                                        </div>
                                        <div class="card-actions">
                                            <button class="edit-card-btn"
                                                onclick="editForeignDestination(<?= htmlspecialchars(json_encode($dest)) ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="delete-card-btn"
                                                onclick="deleteItem('foreign_destination', <?= $dest['id'] ?>, '<?= htmlspecialchars($dest['name']) ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="foreignDestinationModal" class="modal">
                    <div class="modal-content" style="max-width: 900px;">
                        <div class="modal-header">
                            <h3 id="foreignDestModalTitle">Add Foreign Destination</h3>
                            <span class="close-modal" onclick="closeModal('foreignDestinationModal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="tabs">
                                <button class="tab-btn active" onclick="switchForeignTab(event, 'fr-tab-general')">General
                                    Info</button>
                                <button class="tab-btn" onclick="switchForeignTab(event, 'fr-tab-pricing')">Pricing &
                                    Schedule</button>
                                <button class="tab-btn" onclick="switchForeignTab(event, 'fr-tab-details')">Itinerary &
                                    Details</button>
                                <button class="tab-btn" onclick="switchForeignTab(event, 'fr-tab-gallery')">Gallery</button>
                            </div>

                            <form method="POST" enctype="multipart/form-data" id="foreignDestinationForm" novalidate>
                                <input type="hidden" name="action" value="save_foreign_destination">
                                <input type="hidden" name="id" id="foreign_dest_id" value="0">
                                <input type="hidden" name="old_image" id="foreign_old_image">
                                <input type="hidden" name="old_image2" id="foreign_old_image2">
                                <input type="hidden" name="old_image3" id="foreign_old_image3">
                                <input type="hidden" name="remove_gallery_images" id="foreign_remove_gallery_images"
                                    value="">

                                <!-- General Info Tab -->
                                <div id="fr-tab-general" class="tab-content active">

                                    <div class="form-group">
                                        <label>Title *</label>
                                        <input type="text" name="name" id="foreign_dest_name" required>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Country</label>
                                            <input type="text" name="country" id="foreign_dest_country">
                                        </div>
                                        <div class="form-group">
                                            <label>City</label>
                                            <input type="text" name="city" id="foreign_dest_city">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Location Display (e.g., Seoul, South Korea)</label>
                                        <input type="text" name="location" id="foreign_dest_location"
                                            placeholder="Seoul, South Korea">
                                    </div>

                                    <div class="form-group">
                                        <label>Short Description</label>
                                        <textarea name="description" id="foreign_dest_description" rows="3"
                                            placeholder="Brief description that will appear on the package card."></textarea>
                                    </div>
                                </div>

                                <!-- Pricing & Schedule Tab -->
                                <div id="fr-tab-pricing" class="tab-content">

                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1;">
                                            <label>Currency</label>
                                            <select name="currency" id="foreign_dest_currency" required>
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
                                            <input type="number" name="price" id="foreign_dest_price" step="0.01" required
                                                value="0">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Duration (e.g. 3D/2N)</label>
                                            <input type="text" name="duration" id="foreign_dest_duration"
                                                placeholder="(e.g., 07 April 2026 - 10 June 2026)">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Blocked Dates</label>
                                            <input type="text" name="blocked_dates" id="foreign_dest_blocked_dates"
                                                class="blocked-dates-picker" placeholder="Select dates...">
                                        </div>
                                    </div>

                                    <!-- New Promo Constraints -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Travel Validity Start Date</label>
                                            <input type="date" name="promo_start" id="foreign_promo_start">
                                        </div>
                                        <div class="form-group">
                                            <label>Travel Validity End Date</label>
                                            <input type="date" name="promo_end" id="foreign_promo_end">
                                        </div>
                                        <div class="form-group">
                                            <label>Highlight Duration (Days)</label>
                                            <input type="number" name="highlight_duration" id="foreign_highlight_duration"
                                                value="1" min="1">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Blocked Months (Unclickable)</label>
                                        <div class="month-grid">
                                            <?php
                                            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                            foreach ($months as $i => $m): ?>
                                                <label class="month-checkbox">
                                                    <input type="checkbox" name="blocked_months[]" value="<?= $i + 1 ?>"
                                                        class="foreign-month-check">
                                                    <?= $m ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Activities Count</label>
                                            <input type="number" name="activities_count" id="foreign_dest_activities_count"
                                                value="0">
                                        </div>
                                        <div class="form-group">
                                            <label>Group Size</label>
                                            <input type="text" name="group_size" id="foreign_dest_group_size"
                                                placeholder="2-15 pax">
                                        </div>
                                    </div>
                                </div>

                                <!-- Itinerary & Details Tab -->
                                <div id="fr-tab-details" class="tab-content">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Validity (Optional / Fallback Info)</label>
                                            <input type="text" name="best_season" id="foreign_dest_best_season"
                                                placeholder="March - May, September - November">
                                        </div>
                                        <div class="form-group" style="display:none;">
                                            <label>Category</label>
                                            <select name="category" id="foreign_dest_category">
                                                <option value="asia">🌏 Asia</option>
                                                <option value="city">🏙️ City Tours</option>
                                                <option value="cultural">🏛️ Cultural</option>
                                                <option value="nature">🌿 Nature</option>
                                                <option value="adventure">🏄 Adventure</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Badge Text</label>
                                            <input type="text" name="badge_text" id="foreign_dest_badge_text"
                                                placeholder="e.g. ✨ Featured or 15% OFF">
                                        </div>
                                    </div>

                                    <input type="hidden" name="collage_type" id="foreign_dest_collage_type" value="three">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Display Order</label>
                                            <input type="number" name="display_order" id="foreign_dest_display_order"
                                                value="0">
                                        </div>
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="toggle-switch-group">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="is_active" id="foreign_dest_is_active" value="1" checked>
                                                    <span class="toggle-switch-slider"></span>
                                                </label>
                                                <label for="foreign_dest_is_active" class="toggle-switch-label">Active (show on website)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Inclusions Section -->
                                    <div class="form-group">
                                        <label>Package Inclusions (one per line)</label>
                                        <textarea name="inclusions" id="foreign_dest_inclusions" rows="4"
                                            placeholder="4-star hotel accommodation&#10;Daily breakfast&#10;Airport transfers&#10;English speaking guide&#10;Travel insurance"></textarea>
                                        <small>These will appear in the booking modal popup.</small>
                                    </div>

                                    <!-- Hotel Management -->
                                    <div class="form-group">
                                        <label>Hotel Options (Surcharges)</label>
                                        <div id="foreignHotelBuilderContainer"></div>
                                        <button type="button" class="add-day-btn" style="background: #4caf50;"
                                            onclick="addHotel('foreign')">
                                            <i class="fas fa-plus"></i> Add Hotel Option
                                        </button>
                                        <input type="hidden" name="hotels" id="foreign_hotels_json" value="[]">
                                        <small>Add hotels users can choose from. Use price 0 for the default hotel.</small>
                                    </div>

                                    <!-- Exclusions Section -->
                                    <div class="form-group">
                                        <label>Package Exclusions (one per line)</label>
                                        <textarea name="exclusions" id="foreign_dest_exclusions" rows="3"
                                            placeholder="Airfare&#10;Lunch & dinner&#10;Personal expenses&#10;Visa fees"></textarea>
                                        <small>These will appear in the booking modal popup.</small>
                                    </div>



                                    <!-- Itinerary Builder -->
                                    <div class="form-group">
                                        <label>Tour Itinerary</label>
                                        <div id="foreignItineraryBuilder"></div>
                                        <button type="button" class="add-day-btn" onclick="addForeignItineraryDay()">
                                            <i class="fas fa-plus"></i> Add Day
                                        </button>
                                        <input type="hidden" name="itinerary" id="foreign_itinerary_data" value="[]">
                                    </div>

                                    <div class="form-group">
                                        <label>Remarks and Note</label>
                                        <textarea name="remarks" id="foreign_dest_remarks" rows="4"
                                            placeholder="Important notes about the itinerary, requirements, or special conditions..."></textarea>
                                        <small>This will appear after the Tour Itinerary in the booking popup.</small>
                                    </div>
                                </div>

                                <!-- Gallery Tab -->
                                <div id="fr-tab-gallery" class="tab-content">
                                    <div class="form-group">
                                        <label>Main Images (Cover Slides)</label>
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 1 (Main)</label>
                                                <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'foreign_preview_1')">
                                                <div id="foreign_preview_1" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 2 (Optional)</label>
                                                <input type="file" name="image2" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'foreign_preview_2')">
                                                <div id="foreign_preview_2" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 3 (Optional)</label>
                                                <input type="file" name="image3" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'foreign_preview_3')">
                                                <div id="foreign_preview_3" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-top: 25px;">
                                        <label>Full Photo Gallery (Multi-upload)</label>
                                        <input type="file" name="gallery[]" id="foreign_gallery_input" multiple accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                            onchange="previewForeignGallery(this)">
                                        <div id="foreign_gallery_preview"
                                            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px;">
                                        </div>
                                    </div>
                                </div>

                                <div
                                    style="position: sticky; bottom: -20px; background: white; padding: 20px 0; border-top: 1px solid #eee; margin-top: 20px; z-index: 10;">
                                    <div class="form-actions">
                                        <button type="submit" class="save-btn"
                                            style="width: 100%; justify-content: center; height: 50px; font-size: 1.1rem;"><i
                                                class="fas fa-save"></i> Save Foreign Destination</button>
                                        <button type="button" class="cancel-btn"
                                            onclick="closeModal('foreignDestinationModal')"
                                            style="width: 100%; justify-content: center; height: 45px; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; margin-top: 10px; transition: all 0.2s;">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Local Destinations Section -->
            <?php if ($page === 'local-destinations'): ?>
                <div id="localDestinationsSection" class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-umbrella-beach"></i> Local Destinations</h2>
                        <button class="add-btn" onclick="openLocalDestinationModal()">
                            <i class="fas fa-plus"></i> Add Local Destination
                        </button>
                    </div>
                    <div class="cards-grid">
                        <?php if (empty($local_destinations)): ?>
                            <div class="message info">No local destinations yet. Click "Add Local Destination" to create your
                                first one.</div>
                        <?php else: ?>
                            <?php foreach ($local_destinations as $dest):
                                $preview_image = !empty($dest['image_path']) ? assetUrl($dest['image_path']) : 'https://via.placeholder.com/300x150?text=No+Image';
                                $badge_text = $dest['badge_text'] ?? ($dest['activities_count'] . ' activities');
                                ?>
                                <div class="content-card" data-item-type="destination" data-item-id="<?= $dest['id'] ?>">
                                    <div class="card-preview" style="background-image: url('<?= $preview_image ?>');">
                                        <?php if (!empty($dest['badge_text'])): ?>
                                            <span class="badge">
                                                <?= htmlspecialchars($dest['badge_text']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-content">
                                        <h3 class="card-title">
                                            <?= htmlspecialchars($dest['name']) ?>
                                        </h3>
                                        <div class="card-meta">
                                            <span><i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($dest['city'] . ', ' . $dest['country']) ?>
                                            </span>
                                        </div>
                                        <div class="card-meta">
                                            <span><i class="fas fa-tasks"></i>
                                                <?= $dest['activities_count'] ?> activities
                                            </span>
                                            <span
                                                class="status-badge <?= $dest['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $dest['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="card-meta">
                                            <span><i class="fas fa-tag"></i>
                                                <?= htmlspecialchars($dest['currency'] ?? '₱') ?>
                                                <?= number_format($dest['price'] ?? 0, 2) ?>
                                            </span>
                                            <span><i class="fas fa-clock"></i>
                                                <?= htmlspecialchars($dest['duration'] ?? '') ?>
                                            </span>
                                        </div>
                                        <div class="card-actions">
                                            <button class="edit-card-btn"
                                                onclick="editLocalDestination(<?= htmlspecialchars(json_encode($dest)) ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="delete-card-btn"
                                                onclick="deleteItem('destination', <?= $dest['id'] ?>, '<?= htmlspecialchars($dest['name']) ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="localDestinationModal" class="modal">
                    <div class="modal-content" style="max-width: 900px;">
                        <div class="modal-header">
                            <h3 id="localDestModalTitle">Add Local Destination</h3>
                            <span class="close-modal" onclick="closeModal('localDestinationModal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="tabs">
                                <button class="tab-btn active" onclick="switchLocalTab(event, 'lc-tab-general')">General
                                    Info</button>
                                <button class="tab-btn" onclick="switchLocalTab(event, 'lc-tab-pricing')">Pricing &
                                    Schedule</button>
                                <button class="tab-btn" onclick="switchLocalTab(event, 'lc-tab-details')">Itinerary &
                                    Details</button>
                                <button class="tab-btn" onclick="switchLocalTab(event, 'lc-tab-gallery')">Gallery</button>
                            </div>

                            <form method="POST" enctype="multipart/form-data" id="localDestinationForm" novalidate>
                                <input type="hidden" name="action" value="save_destination">
                                <input type="hidden" name="id" id="local_dest_id" value="0">
                                <input type="hidden" name="type" value="local">
                                <input type="hidden" name="old_image" id="local_old_image">
                                <input type="hidden" name="old_image_2" id="local_old_image_2">
                                <input type="hidden" name="old_image_3" id="local_old_image_3">
                                <input type="hidden" name="remove_gallery_images" id="local_remove_gallery_images" value="">

                                <!-- General Info Tab -->
                                <div id="lc-tab-general" class="tab-content active">
                                    <div class="form-group">
                                        <label>Title *</label>
                                        <input type="text" name="name" id="local_dest_name" required>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Country *</label>
                                            <input type="text" name="country" id="local_dest_country" required
                                                value="Philippines">
                                        </div>
                                        <div class="form-group">
                                            <label>City *</label>
                                            <input type="text" name="city" id="local_dest_city" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Location Display (e.g., Palawan Island)</label>
                                        <input type="text" name="location_name" id="local_dest_location_name"
                                            placeholder="e.g., Palawan Island">
                                    </div>

                                    <div class="form-group">
                                        <label>Short Description</label>
                                        <textarea name="description" id="local_dest_description" rows="4"
                                            placeholder="Brief description that will appear on the package card."></textarea>
                                    </div>
                                </div>

                                <!-- Pricing & Schedule Tab -->
                                <div id="lc-tab-pricing" class="tab-content">
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1;">
                                            <label>Currency</label>
                                            <select name="currency" id="local_dest_currency" required>
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
                                            <input type="number" name="price" id="local_dest_price" step="0.01" value="0"
                                                required>
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Tour Duration (e.g. 5D/4N)</label>
                                            <input type="text" name="duration" id="local_dest_duration" placeholder="3D/2N">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Blocked Dates</label>
                                            <input type="text" name="blocked_dates" id="local_dest_blocked_dates"
                                                class="blocked-dates-picker" placeholder="Select dates...">
                                        </div>
                                    </div>

                                    <!-- Promo Constraints -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Travel Validity Start</label>
                                            <input type="date" name="promo_start" id="local_promo_start">
                                        </div>
                                        <div class="form-group">
                                            <label>Travel Validity End</label>
                                            <input type="date" name="promo_end" id="local_promo_end">
                                        </div>
                                        <div class="form-group">
                                            <label>Highlight (Days)</label>
                                            <input type="number" name="highlight_duration" id="local_highlight_duration"
                                                value="1" min="1">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Blocked Months (Unclickable)</label>
                                        <div class="month-grid">
                                            <?php
                                            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                            foreach ($months as $i => $m): ?>
                                                <label class="month-checkbox">
                                                    <input type="checkbox" name="blocked_months[]" value="<?= $i + 1 ?>"
                                                        class="local-month-check">
                                                    <?= $m ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Activities Count</label>
                                            <input type="number" name="activities_count" id="local_dest_activities_count"
                                                value="0">
                                        </div>
                                        <div class="form-group">
                                            <label>Group Size</label>
                                            <input type="text" name="group_size" id="local_dest_group_size"
                                                placeholder="2-15 pax">
                                        </div>
                                    </div>
                                </div>

                                <!-- Itinerary & Details Tab -->
                                <div id="lc-tab-details" class="tab-content">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Validity (Optional / Fallback Info)</label>
                                            <input type="text" name="best_season" id="local_dest_best_season"
                                                placeholder="March - May">
                                        </div>
                                        <div class="form-group">
                                            <label>Badge Text</label>
                                            <input type="text" name="badge_text" id="local_dest_badge_text"
                                                placeholder="e.g. ✨ Featured or Sale">
                                        </div>
                                        <div class="form-group">
                                            <label>Booked Count</label>
                                            <input type="text" name="booked_count" id="local_dest_booked_count"
                                                placeholder="e.g., 5K+ booked">
                                        </div>
                                    </div>

                                    <!-- Inclusions -->
                                    <div class="form-group">
                                        <label>Package Inclusions (one per line)</label>
                                        <textarea name="inclusions" id="local_dest_inclusions" rows="4"
                                            placeholder="Hotel accommodation&#10;Daily breakfast&#10;Tours included"></textarea>
                                    </div>

                                    <!-- Hotel Management -->
                                    <div class="form-group">
                                        <label>Hotel Options (Surcharges)</label>
                                        <div id="localHotelBuilderContainer"></div>
                                        <button type="button" class="add-day-btn" style="background: #4caf50;"
                                            onclick="addHotel('local')">
                                            <i class="fas fa-plus"></i> Add Hotel Option
                                        </button>
                                        <input type="hidden" name="hotels" id="local_hotels_json" value="[]">
                                        <small>Add hotels users can choose from. Use price 0 for the default hotel.</small>
                                    </div>

                                    <!-- Exclusions -->
                                    <div class="form-group">
                                        <label>Package Exclusions (one per line)</label>
                                        <textarea name="exclusions" id="local_dest_exclusions" rows="3"
                                            placeholder="Airfare&#10;Personal expenses"></textarea>
                                    </div>

                                    <!-- Itinerary -->
                                    <div class="form-group">
                                        <label>Tour Itinerary</label>
                                        <div id="localItineraryBuilder"></div>
                                        <button type="button" class="add-day-btn" onclick="addLocalItineraryDay()">
                                            <i class="fas fa-plus"></i> Add Day
                                        </button>
                                        <input type="hidden" name="itinerary" id="local_itinerary_data" value="[]">
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Display Order</label>
                                            <input type="number" name="display_order" id="local_dest_display_order"
                                                value="0">
                                        </div>
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="toggle-switch-group">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="is_active" id="local_dest_is_active" value="1" checked>
                                                    <span class="toggle-switch-slider"></span>
                                                </label>
                                                <label for="local_dest_is_active" class="toggle-switch-label">Active (show on website)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Remarks and Note</label>
                                        <textarea name="remarks" id="local_dest_remarks" rows="4"
                                            placeholder="Important notes about the itinerary, requirements, or special conditions..."></textarea>
                                        <small>This will appear after the Tour Itinerary in the booking popup.</small>
                                    </div>
                                </div>

                                <!-- Gallery Tab -->
                                <div id="lc-tab-gallery" class="tab-content">
                                    <div class="form-group">
                                        <label>Main Images (Cover Slides)</label>
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 1 (Main)</label>
                                                <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'local_preview_1')">
                                                <div id="local_preview_1" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 2 (Optional)</label>
                                                <input type="file" name="image_2" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'local_preview_2')">
                                                <div id="local_preview_2" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                            <div>
                                                <label style="font-size: 0.8rem;">Image 3 (Optional)</label>
                                                <input type="file" name="image_3" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                                    onchange="previewImage(this, 'local_preview_3')">
                                                <div id="local_preview_3" class="image-preview"
                                                    style="height: 120px; margin-top: 5px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-top: 25px;">
                                        <label>Full Photo Gallery (Multi-upload)</label>
                                        <input type="file" name="gallery[]" id="local_gallery_input" multiple accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                            onchange="previewLocalGallery(this)">
                                        <div id="local_gallery_preview"
                                            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px;">
                                        </div>
                                    </div>
                                </div>

                                <div
                                    style="position: sticky; bottom: -20px; background: white; padding: 20px 0; border-top: 1px solid #eee; margin-top: 20px; z-index: 10;">
                                    <div class="form-actions">
                                        <button type="submit" class="save-btn"
                                            style="width: 100%; justify-content: center; height: 50px; font-size: 1.1rem;"><i
                                                class="fas fa-save"></i> Save Local Destination</button>
                                        <button type="button" class="cancel-btn"
                                            onclick="closeModal('localDestinationModal')"
                                            style="width: 100%; justify-content: center; height: 45px; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; margin-top: 10px; transition: all 0.2s;">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Flight Booking Section -->
            <?php if ($page === 'flight-booking'): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-plane"></i> Flight Price Data</h2>
                        <button class="add-btn" onclick="openFlightModal()">
                            <i class="fas fa-plus"></i> Add Destination
                        </button>
                    </div>
                    <div class="cards-grid">
                        <?php foreach ($flight_data as $flight): ?>
                            <div class="content-card">
                                <div class="card-content">
                                    <h3 class="card-title">
                                        <?= htmlspecialchars($flight['destination_name']) ?>
                                    </h3>
                                    <div class="card-meta">
                                        <span><i class="fas fa-key"></i>
                                            <?= htmlspecialchars($flight['destination_key']) ?>
                                        </span>
                                    </div>
                                    <div class="card-actions">
                                        <button class="edit-card-btn"
                                            onclick="editFlightData(<?= htmlspecialchars(json_encode($flight)) ?>)">
                                            <i class="fas fa-edit"></i> Edit Prices
                                        </button>
                                        <button class="delete-card-btn"
                                            onclick="deleteItem('flight_data', <?= $flight['id'] ?>, '<?= htmlspecialchars($flight['destination_name']) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="flightModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="flightModalTitle">Edit Flight Prices</h3>
                            <span class="close-modal" onclick="closeModal('flightModal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form method="POST" id="flightForm">
                                <input type="hidden" name="action" value="save_flight_data">
                                <input type="hidden" name="id" id="flight_id" value="0">

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Destination Key *</label>
                                        <input type="text" name="destination_key" id="flight_key" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Destination Name *</label>
                                        <input type="text" name="destination_name" id="flight_name" required>
                                    </div>
                                </div>

                                <div class="month-grid" id="flightMonthsGrid">
                                    <?php
                                    $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
                                    foreach ($months as $month):
                                        ?>
                                        <div class="month-card">
                                            <h4>
                                                <?= ucfirst($month) ?>
                                            </h4>
                                            <div class="form-group">
                                                <label>Lowest Price (₱)</label>
                                                <input type="number" name="<?= $month ?>_low" id="flight_<?= $month ?>_low"
                                                    class="flight-month-input" value="0">
                                            </div>
                                            <div class="form-group">
                                                <label>Highest Price (₱)</label>
                                                <input type="number" name="<?= $month ?>_high" id="flight_<?= $month ?>_high"
                                                    class="flight-month-input" value="0">
                                            </div>
                                            <div class="form-group">
                                                <label>Airlines</label>
                                                <input type="text" name="<?= $month ?>_airline"
                                                    id="flight_<?= $month ?>_airline" class="flight-month-input"
                                                    placeholder="e.g., PAL, Cebu Pac">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" class="save-btn">Save Flight Prices</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hotel Booking Section -->
            <?php if ($page === 'hotel-booking'): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-hotel"></i> Hotel Rate Data</h2>
                        <button class="add-btn" onclick="openHotelModal()">
                            <i class="fas fa-plus"></i> Add Destination
                        </button>
                    </div>
                    <div class="cards-grid">
                        <?php foreach ($hotel_data as $hotel): ?>
                            <div class="content-card">
                                <div class="card-content">
                                    <h3 class="card-title">
                                        <?= htmlspecialchars($hotel['destination_name']) ?>
                                    </h3>
                                    <div class="card-meta">
                                        <span><i class="fas fa-key"></i>
                                            <?= htmlspecialchars($hotel['destination_key']) ?>
                                        </span>
                                    </div>
                                    <div class="card-actions">
                                        <button class="edit-card-btn"
                                            onclick="editHotelData(<?= htmlspecialchars(json_encode($hotel)) ?>)">
                                            <i class="fas fa-edit"></i> Edit Rates
                                        </button>
                                        <button class="delete-card-btn"
                                            onclick="deleteItem('hotel_data', <?= $hotel['id'] ?>, '<?= htmlspecialchars($hotel['destination_name']) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="hotelModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="hotelModalTitle">Edit Hotel Rates</h3>
                            <span class="close-modal" onclick="closeModal('hotelModal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form method="POST" id="hotelForm">
                                <input type="hidden" name="action" value="save_hotel_data">
                                <input type="hidden" name="id" id="hotel_id" value="0">

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Destination Key *</label>
                                        <input type="text" name="destination_key" id="hotel_key" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Destination Name *</label>
                                        <input type="text" name="destination_name" id="hotel_name" required>
                                    </div>
                                </div>

                                <div class="month-grid" id="hotelMonthsGrid">
                                    <?php foreach ($months as $month): ?>
                                        <div class="month-card">
                                            <h4>
                                                <?= ucfirst($month) ?>
                                            </h4>
                                            <div class="form-group">
                                                <label>Lowest Price (₱)</label>
                                                <input type="number" name="<?= $month ?>_low" id="hotel_<?= $month ?>_low"
                                                    class="hotel-month-input" value="0">
                                            </div>
                                            <div class="form-group">
                                                <label>Highest Price (₱)</label>
                                                <input type="number" name="<?= $month ?>_high" id="hotel_<?= $month ?>_high"
                                                    class="hotel-month-input" value="0">
                                            </div>
                                            <div class="form-group">
                                                <label>Hotel Type/Description</label>
                                                <input type="text" name="<?= $month ?>_hotel" id="hotel_<?= $month ?>_hotel"
                                                    class="hotel-month-input" placeholder="e.g., Peak Season">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" class="save-btn">Save Hotel Rates</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Visa Management Modal -->
            <div id="visaModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="visaModalTitle">Add Visa Service</h3>
                        <span class="close-modal" onclick="closeModal('visaModal')">&times;</span>
                    </div>
                    <div class="modal-body">
                        <!-- Quick Country Finder -->
                        <div class="picker-container">
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 10px; color: #003580; font-size: 0.9rem;">
                                <i class="fas fa-search-location"></i> Quick Country & Flag Finder
                            </label>
                            <div class="picker-search">
                                <i class="fas fa-search fa-search-icon"></i>
                                <input type="text" id="visaCountrySearch"
                                    placeholder="Search for a country (e.g. Japan, France, South Korea)..."
                                    onkeyup="filterVisaCountries()">
                                <button type="button" class="clear-picker-search" onclick="clearVisaCountrySearch()"
                                    title="Clear search">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="picker-results" id="visaCountryResults">
                                <!-- Results will be populated by JS -->
                            </div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" id="visaForm" novalidate>
                            <input type="hidden" name="action" value="save_visa">
                            <input type="hidden" name="id" id="visa_id" value="0">
                            <input type="hidden" name="old_icon_value" id="visa_old_icon_value">

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Visa Title *</label>
                                    <input type="text" name="title" id="visa_title" required
                                        placeholder="e.g. Singapore">
                                </div>
                                <div class="form-group">
                                    <label>Category *</label>
                                    <select name="category" id="visa_category" required>
                                        <option value="Asia">Asia</option>
                                        <option value="Africa">Africa</option>
                                        <option value="North America">North America</option>
                                        <option value="South America">South America</option>
                                        <option value="Antarctica">Antarctica</option>
                                        <option value="Europe">Europe</option>
                                        <option value="Australia">Australia</option>
                                        <option value="International">International</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Visa Status *</label>
                                <div style="display: flex; gap: 15px; margin-top: 5px; flex-wrap: wrap;">
                                    <label
                                        style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: normal;">
                                        <input type="radio" name="visa_status" id="visa_status_required"
                                            value="required" checked style="width: auto; accent-color: #e74c3c;">
                                        <span
                                            style="background: #fdecea; color: #c0392b; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">🔴
                                            Visa Required</span>
                                    </label>
                                    <label
                                        style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: normal;">
                                        <input type="radio" name="visa_status" id="visa_status_free" value="free"
                                            style="width: auto; accent-color: #27ae60;">
                                        <span
                                            style="background: #eafaf1; color: #1e8449; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">🟢
                                            Visa-Free</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" id="visa_description" rows="2"
                                    placeholder="Brief description of the service."></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select name="currency" id="visa_currency">
                                        <option value="₱">₱ PHP</option>
                                        <option value="$">$ USD</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Price *</label>
                                    <input type="number" name="price" id="visa_price" step="0.01" required value="0">
                                </div>
                                <div class="form-group">
                                    <label>Processing Time</label>
                                    <input type="text" name="processing_time" id="visa_processing_time"
                                        placeholder="e.g. Visa-Free or 3-5 Working Days">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Requirements (one per line)</label>
                                <textarea name="requirements" id="visa_requirements" rows="4"
                                    placeholder="Passport valid for 6 months&#10;2x2 Photo&#10;Bank Certificate"></textarea>
                            </div>

                            <div class="form-group">
                                <label>Disclaimer Notice <small>(Optional warning shown before
                                        application)</small></label>
                                <textarea name="disclaimer" id="visa_disclaimer" rows="2"
                                    placeholder="e.g. Completing the application does not provide a 100% guarantee of approval."></textarea>
                            </div>

                            <div class="form-group">
                                <label>Important Notes <small>(Optional notes displayed in the confirmation
                                        prompt)</small></label>
                                <textarea name="important_notes" id="visa_important_notes" rows="3"
                                    placeholder="e.g. Processing may take longer during peak season.&#10;Additional fees may apply for rush processing."></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Icon Type</label>
                                    <select name="icon_type" id="visa_icon_type" onchange="toggleVisaIconFields()">
                                        <option value="image">Flag URL (External)</option>
                                        <option value="upload">Upload Icon/Flag</option>
                                        <option value="icon">FontAwesome Icon</option>
                                    </select>
                                </div>
                                <div class="form-group" id="visa_icon_value_group">
                                    <label id="visa_icon_label">Flag URL</label>
                                    <input type="text" name="icon_value" id="visa_icon_value"
                                        placeholder="https://flagcdn.com/w80/sg.png">
                                </div>
                                <div class="form-group" id="visa_icon_upload_group" style="display:none;">
                                    <label>Upload Icon</label>
                                    <input type="file" name="icon_upload" id="visa_icon_upload" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Display Order</label>
                                    <input type="number" name="display_order" id="visa_display_order" value="0">
                                </div>
                                <div class="form-group" style="padding-top: 25px;">
                                    <div class="toggle-switch-group">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="is_active" id="visa_is_active" value="1" checked>
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                        <label for="visa_is_active" class="toggle-switch-label">Active (Show on website)</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="save-btn">Save Visa Service</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <script>
        // ========== PERSISTENCE ENGINE ==========
        const PersistenceEngine = {
            checkDraft: function(formId) {
                try {
                    const draftKey = 'draft_' + formId;
                    const draftData = localStorage.getItem(draftKey);
                    if (!draftData) return;

                    const parsed = JSON.parse(draftData);
                    const currentIdElement = this.getIdElement(formId);
                    const currentId = currentIdElement ? currentIdElement.value : '0';

                    if (parsed.editingId !== currentId) {
                        return;
                    }

                    Swal.fire({
                        title: 'Unsaved Draft Found',
                        text: 'You have unsaved changes for this item. Would you like to restore them?',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Restore',
                        cancelButtonText: 'No, Discard',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        scrollbarPadding: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.restoreDraft(formId, parsed);
                            Swal.fire({
                                icon: 'success',
                                title: 'Draft Restored!',
                                showConfirmButton: false,
                                timer: 1000
                            });
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            this.clearDraft(formId);
                        }
                    });
                } catch (e) {
                    console.error("Error checking draft", e);
                }
            },

            normalizeAssetPath: function(path) {
                if (!path || typeof path !== 'string') {
                    return '';
                }
                if (/^(https?:|\/\/)/i.test(path)) {
                    return path;
                }
                if (path.startsWith('../') || path.startsWith('./') || path.startsWith('/')) {
                    return path;
                }
                return '../../' + path.replace(/^\/+/, '');
            },

            saveDraft: function(formId) {
                try {
                    const form = document.getElementById(formId);
                    if (!form) return;

                    // Only save if the modal is currently open and active
                    const modal = form.closest('.modal');
                    if (modal && !modal.classList.contains('active')) return;

                    const formData = {};
                    const inputs = form.querySelectorAll('input, select, textarea');
                    inputs.forEach(input => {
                        if (!input.name || input.type === 'file') return;
                        if (input.type === 'checkbox') {
                            if (input.name.endsWith('[]')) {
                                if (!formData[input.name]) {
                                    formData[input.name] = [];
                                }
                                if (input.checked) {
                                    formData[input.name].push(input.value);
                                }
                            } else {
                                formData[input.name] = input.checked;
                            }
                        } else if (input.type === 'radio') {
                            if (input.checked) {
                                formData[input.name] = input.value;
                            }
                        } else {
                            formData[input.name] = input.value;
                        }
                    });

                    // Get editing ID
                    const idElement = this.getIdElement(formId);
                    formData.editingId = idElement ? idElement.value : '0';
                    formData.timestamp = new Date().getTime();

                    // Form-specific extra structures
                    if (formId === 'flashDealForm') {
                        formData._extra = {
                            itinerary: typeof flashDealItineraryDays !== 'undefined' ? flashDealItineraryDays : [],
                            hotels: typeof flashHotels !== 'undefined' ? flashHotels : [],
                            savedGallery: typeof _flashSavedGallery !== 'undefined' ? _flashSavedGallery : []
                        };
                    } else if (formId === 'foreignDestinationForm') {
                        formData._extra = {
                            itinerary: typeof foreignItineraryDays !== 'undefined' ? foreignItineraryDays : [],
                            hotels: typeof foreignHotels !== 'undefined' ? foreignHotels : [],
                            savedGallery: typeof _foreignSavedGallery !== 'undefined' ? _foreignSavedGallery : []
                        };
                    } else if (formId === 'localDestinationForm') {
                        formData._extra = {
                            itinerary: typeof localItineraryDays !== 'undefined' ? localItineraryDays : [],
                            hotels: typeof localHotels !== 'undefined' ? localHotels : [],
                            savedGallery: typeof _localSavedGallery !== 'undefined' ? _localSavedGallery : []
                        };
                    } else if (formId === 'advancedCruiseForm') {
                        const itinerary = [];
                        const list = document.getElementById('itinerary-list');
                        if (list) {
                            Array.from(list.children).forEach(item => {
                                const titleInput = item.querySelector('input[name*="[title]"]');
                                const descTextarea = item.querySelector('textarea[name*="[description]"]');
                                itinerary.push({
                                    title: titleInput ? titleInput.value : '',
                                    description: descTextarea ? descTextarea.value : ''
                                });
                            });
                        }
                        formData._extra = { itinerary: itinerary };
                    } else if (formId === 'serviceForm') {
                        const itinerary = [];
                        const list = document.getElementById('service-itinerary-list');
                        if (list) {
                            Array.from(list.children).forEach(item => {
                                const titleInput = item.querySelector('input[name*="[title]"]');
                                const descTextarea = item.querySelector('textarea[name*="[description]"]');
                                itinerary.push({
                                    title: titleInput ? titleInput.value : '',
                                    description: descTextarea ? descTextarea.value : ''
                                });
                            });
                        }
                        formData._extra = { itinerary: itinerary };
                    }

                    localStorage.setItem('draft_' + formId, JSON.stringify(formData));
                    
                    // Show a subtle visual cue that draft is saved
                    this.showDraftSavedIndicator(formId);
                } catch (e) {
                    console.error("Error saving draft", e);
                }
            },

            restoreDraft: function(formId, parsed) {
                try {
                    const form = document.getElementById(formId);
                    if (!form) return;

                    Object.keys(parsed).forEach(key => {
                        if (key === 'editingId' || key === 'timestamp' || key === '_extra') return;

                        if (key.endsWith('[]')) {
                            const checkboxes = form.querySelectorAll(`[name="${key}"]`);
                            checkboxes.forEach(cb => {
                                cb.checked = parsed[key].includes(cb.value);
                            });
                        } else {
                            const input = form.querySelector(`[name="${key}"]`);
                            if (!input) return;

                            if (input.type === 'checkbox') {
                                input.checked = parsed[key];
                            } else if (input.type === 'radio') {
                                const radio = form.querySelector(`[name="${key}"][value="${parsed[key]}"]`);
                                if (radio) radio.checked = true;
                            } else {
                                input.value = parsed[key];
                            }
                        }
                    });

                    // Restore form-specific extra structures
                    if (formId === 'flashDealForm' && parsed._extra) {
                        if (parsed._extra.itinerary) {
                            flashDealItineraryDays = parsed._extra.itinerary;
                            renderFlashDealItineraryBuilder();
                        }
                        if (parsed._extra.hotels) {
                            flashHotels = parsed._extra.hotels;
                            renderHotelBuilder('flash');
                        }
                        if (parsed._extra.savedGallery) {
                            _flashSavedGallery = parsed._extra.savedGallery;
                            _renderFlashGalleryPreview();
                        }
                    } else if (formId === 'foreignDestinationForm' && parsed._extra) {
                        if (parsed._extra.itinerary) {
                            foreignItineraryDays = parsed._extra.itinerary;
                            renderForeignItineraryBuilder();
                        }
                        if (parsed._extra.hotels) {
                            foreignHotels = parsed._extra.hotels;
                            renderHotelBuilder('foreign');
                        }
                        if (parsed._extra.savedGallery) {
                            _foreignSavedGallery = parsed._extra.savedGallery;
                            _renderForeignGalleryPreview();
                        }
                    } else if (formId === 'localDestinationForm' && parsed._extra) {
                        if (parsed._extra.itinerary) {
                            localItineraryDays = parsed._extra.itinerary;
                            renderLocalItineraryBuilder();
                        }
                        if (parsed._extra.hotels) {
                            localHotels = parsed._extra.hotels;
                            renderHotelBuilder('local');
                        }
                        if (parsed._extra.savedGallery) {
                            _localSavedGallery = parsed._extra.savedGallery;
                            _renderLocalGalleryPreview();
                        }
                    } else if (formId === 'advancedCruiseForm' && parsed._extra && parsed._extra.itinerary) {
                        const list = document.getElementById('itinerary-list');
                        if (list) {
                            list.innerHTML = '';
                            parsed._extra.itinerary.forEach(it => {
                                addItineraryDay(it.title, it.description);
                            });
                        }
                    } else if (formId === 'serviceForm' && parsed._extra && parsed._extra.itinerary) {
                        const list = document.getElementById('service-itinerary-list');
                        if (list) {
                            list.innerHTML = '';
                            parsed._extra.itinerary.forEach(it => {
                                addServiceItineraryDay(it.title, it.description);
                            });
                        }
                    }

                    // Restore Flatpickr dates if present
                    const blockedDatesInput = form.querySelector('[id*="blocked_dates"]');
                    if (blockedDatesInput && blockedDatesInput._flatpickr && parsed[blockedDatesInput.name]) {
                        blockedDatesInput._flatpickr.setDate(parsed[blockedDatesInput.name].split(','));
                    }

                    // Trigger change events
                    form.querySelectorAll('input, select, textarea').forEach(input => {
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    // Restore image previews if old image values are present
                    const updatePreview = (oldInputId, previewId) => {
                        const input = document.getElementById(oldInputId);
                        const preview = document.getElementById(previewId);
                        if (input && preview) {
                            if (input.value) {
                                preview.style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(input.value)}')`;
                                preview.style.backgroundSize = 'cover';
                                preview.style.backgroundPosition = 'center';
                                if (preview.classList.contains('featured-preview-frame')) {
                                    preview.innerHTML = '';
                                }
                            } else {
                                preview.style.backgroundImage = '';
                            }
                        }
                    };

                    if (formId === 'flashDealForm') {
                        updatePreview('old_image_1', 'fd_preview_1');
                        updatePreview('old_image_2', 'fd_preview_2');
                        updatePreview('old_image_3', 'fd_preview_3');
                    } else if (formId === 'foreignDestinationForm') {
                        updatePreview('foreign_old_image', 'foreign_preview_1');
                        updatePreview('foreign_old_image2', 'foreign_preview_2');
                        updatePreview('foreign_old_image3', 'foreign_preview_3');
                    } else if (formId === 'localDestinationForm') {
                        updatePreview('local_old_image', 'local_preview_1');
                        updatePreview('local_old_image_2', 'local_preview_2');
                        updatePreview('local_old_image_3', 'local_preview_3');
                    } else if (formId === 'serviceForm') {
                        updatePreview('service_old_featured_image', 'service_featured_preview');
                    } else if (formId === 'advancedCruiseForm') {
                        updatePreview('old_featured_image', 'featured_preview_box');
                    }
                } catch (e) {
                    console.error("Error restoring draft", e);
                }
            },

            clearDraft: function(formId) {
                try {
                    localStorage.removeItem('draft_' + formId);
                } catch (e) {
                    console.error("Error clearing draft", e);
                }
            },

            getIdElement: function(formId) {
                if (formId === 'flashDealForm') return document.getElementById('deal_id');
                if (formId === 'foreignDestinationForm') return document.getElementById('foreign_dest_id');
                if (formId === 'localDestinationForm') return document.getElementById('local_dest_id');
                if (formId === 'serviceForm') return document.getElementById('service_id');
                if (formId === 'flightForm') return document.getElementById('flight_id');
                if (formId === 'hotelForm') return document.getElementById('hotel_id');
                if (formId === 'visaForm') return document.getElementById('visa_id');
                if (formId === 'advancedCruiseForm') return document.getElementById('advanced_cruise_id');
                return null;
            },

            getSavedTabsStorageKey: function(formId) {
                return 'savedTabs_' + formId;
            },

            getSavedTabs: function(formId) {
                try {
                    const saved = localStorage.getItem(this.getSavedTabsStorageKey(formId));
                    return saved ? JSON.parse(saved) : null;
                } catch (e) {
                    console.error('Error reading saved tabs', e);
                    return null;
                }
            },

            setSavedTabState: function(formId, tabId) {
                try {
                    const idElement = this.getIdElement(formId);
                    const currentId = idElement ? idElement.value : '0';
                    if (!currentId || currentId === '0') return;

                    let saved = this.getSavedTabs(formId);
                    if (!saved || saved.editingId !== currentId) {
                        saved = { editingId: currentId, tabs: {} };
                    }
                    if (!saved.tabs) saved.tabs = {};
                    saved.tabs[tabId] = true;
                    localStorage.setItem(this.getSavedTabsStorageKey(formId), JSON.stringify(saved));
                } catch (e) {
                    console.error('Error saving tab state', e);
                }
            },

            clearSavedTabState: function(formId) {
                try {
                    localStorage.removeItem(this.getSavedTabsStorageKey(formId));
                } catch (e) {
                    console.error('Error clearing saved tabs', e);
                }
            },

            applySavedTabState: function(formId, modalSelector) {
                const saved = this.getSavedTabs(formId);
                const idElement = this.getIdElement(formId);
                const currentId = idElement ? idElement.value : '0';
                document.querySelectorAll(modalSelector + ' .tab-btn').forEach(btn => {
                    btn.classList.remove('tab-saved');
                });
                if (!saved || saved.editingId !== currentId || !saved.tabs) return;

                document.querySelectorAll(modalSelector + ' .tab-btn').forEach(btn => {
                    const targetMatch = btn.getAttribute('onclick')?.match(/'([^']+)'/);
                    const target = targetMatch ? targetMatch[1] : btn.dataset.target;
                    if (!target) return;
                    btn.classList.toggle('tab-saved', !!saved.tabs[target]);
                });
            },

            showDraftSavedIndicator: function(formId) {
                const form = document.getElementById(formId);
                if (!form) return;
                let indicator = form.querySelector('.draft-saved-indicator');
                if (!indicator) {
                    indicator = document.createElement('span');
                    indicator.className = 'draft-saved-indicator';
                    indicator.innerHTML = '<i class="fas fa-save"></i> Draft Saved';
                    indicator.style.cssText = 'font-size: 12px; color: #10b981; margin-left: 10px; display: inline-flex; align-items: center; gap: 5px; opacity: 0; transition: opacity 0.3s;';
                    const saveBtn = form.querySelector('button[type="submit"], .save-btn');
                    if (saveBtn) {
                        saveBtn.parentNode.insertBefore(indicator, saveBtn.nextSibling);
                    }
                }
                indicator.style.opacity = '1';
                setTimeout(() => {
                    indicator.style.opacity = '0';
                }, 2000);
            },

            initAutosave: function() {
                const formIds = [
                    'flashDealForm',
                    'foreignDestinationForm',
                    'localDestinationForm',
                    'serviceForm',
                    'flightForm',
                    'hotelForm',
                    'visaForm',
                    'advancedCruiseForm'
                ];
                
                formIds.forEach(formId => {
                    const form = document.getElementById(formId);
                    if (!form) return;
                    
                    form.addEventListener('input', () => {
                        this.saveDraft(formId);
                    });
                    form.addEventListener('change', () => {
                        this.saveDraft(formId);
                    });
                    if (['flightForm', 'hotelForm', 'visaForm'].includes(formId)) {
                        form.addEventListener('submit', () => {
                            this.clearDraft(formId);
                        });
                    }
                });
            },

            getSavedTabsStorageKey: function(formId) {
                return 'savedTabs_' + formId;
            },

            getSavedTabs: function(formId) {
                try {
                    const saved = localStorage.getItem(this.getSavedTabsStorageKey(formId));
                    return saved ? JSON.parse(saved) : null;
                } catch (e) {
                    console.error('Error reading saved tabs', e);
                    return null;
                }
            },

            setSavedTabState: function(formId, tabId) {
                try {
                    const idElement = this.getIdElement(formId);
                    const currentId = idElement ? idElement.value : '0';
                    if (!currentId || currentId === '0') return;

                    let saved = this.getSavedTabs(formId);
                    if (!saved || saved.editingId !== currentId) {
                        saved = { editingId: currentId, tabs: {} };
                    }
                    if (!saved.tabs) saved.tabs = {};
                    saved.tabs[tabId] = true;
                    localStorage.setItem(this.getSavedTabsStorageKey(formId), JSON.stringify(saved));
                } catch (e) {
                    console.error('Error saving tab state', e);
                }
            },

            clearSavedTabState: function(formId) {
                try {
                    localStorage.removeItem(this.getSavedTabsStorageKey(formId));
                } catch (e) {
                    console.error('Error clearing saved tabs', e);
                }
            },

            applySavedTabState: function(formId, modalSelector) {
                const saved = this.getSavedTabs(formId);
                const idElement = this.getIdElement(formId);
                const currentId = idElement ? idElement.value : '0';
                if (!saved || saved.editingId !== currentId || !saved.tabs) return;

                document.querySelectorAll(modalSelector + ' .tab-btn').forEach(btn => {
                    const targetMatch = btn.getAttribute('onclick')?.match(/'([^']+)'/);
                    const target = targetMatch ? targetMatch[1] : btn.dataset.target;
                    if (!target) return;
                    btn.classList.toggle('tab-saved', !!saved.tabs[target]);
                });
            }
        };

        // ========== FOREIGN DESTINATION JAVASCRIPT ==========
        let foreignItineraryDays = [];

        function openForeignDestinationModal() {
            document.getElementById('foreign_dest_id').value = '0';
            document.getElementById('foreign_dest_name').value = '';
            document.getElementById('foreign_dest_country').value = '';
            document.getElementById('foreign_dest_city').value = '';
            document.getElementById('foreign_dest_location').value = '';
            document.getElementById('foreign_dest_description').value = '';
            document.getElementById('foreign_dest_price').value = '0';
            document.getElementById('foreign_dest_currency').value = '₱';
            document.getElementById('foreign_dest_duration').value = '';

            const picker = document.getElementById('foreign_dest_blocked_dates')._flatpickr;
            if (picker) picker.clear();

            document.getElementById('foreign_dest_activities_count').value = '0';
            document.getElementById('foreign_dest_group_size').value = '';
            document.getElementById('foreign_dest_best_season').value = '';
            document.getElementById('foreign_dest_category').value = 'asia';
            document.getElementById('foreign_dest_badge_text').value = '';
            document.getElementById('foreign_dest_collage_type').value = 'three';
            document.getElementById('foreign_dest_display_order').value = '0';
            document.getElementById('foreign_dest_inclusions').value = '';
            document.getElementById('foreign_dest_exclusions').value = '';
            document.getElementById('foreign_dest_remarks').value = '';
            document.getElementById('foreign_dest_is_active').checked = true;

            // Reset images
            document.getElementById('foreign_preview_1').style.backgroundImage = '';
            document.getElementById('foreign_preview_2').style.backgroundImage = '';
            document.getElementById('foreign_preview_3').style.backgroundImage = '';
            document.getElementById('foreign_old_image').value = '';
            document.getElementById('foreign_old_image2').value = '';
            document.getElementById('foreign_old_image3').value = '';
            _foreignGalleryFiles = [];
            _foreignSavedGallery = [];
            document.getElementById('foreign_remove_gallery_images').value = '';
            _renderForeignGalleryPreview();

            // Reset itinerary
            foreignItineraryDays = [];
            renderForeignItineraryBuilder();

            // Reset hotels
            foreignHotels = [];
            renderHotelBuilder('foreign');

            // Reset tabs
            const firstTab = document.querySelector('#foreignDestinationModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('foreignDestinationModal').classList.add('active');
            PersistenceEngine.checkDraft('foreignDestinationForm');
            PersistenceEngine.applySavedTabState('foreignDestinationForm', '#foreignDestinationModal');
        }

        let _foreignGalleryFiles = []; // new files
        let _foreignSavedGallery  = []; // saved images paths

        function _renderForeignGalleryPreview() {
            const preview = document.getElementById('foreign_gallery_preview');
            if (!preview) return;
            preview.innerHTML = '';

            // 1. Render saved photos
            _foreignSavedGallery.forEach(img => {
                const div = document.createElement('div');
                div.style.height = '100px';
                div.style.borderRadius = '8px';
                div.style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(img)}')`;
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.style.position = 'relative';
                div.style.border = '1px solid #ddd';
                div.innerHTML = `<button type="button" onclick="removeForeignGalleryImage('${img}', this)" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });

            // 2. Render newly selected photos
            _foreignGalleryFiles.forEach((file, index) => {
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

                div.innerHTML = `<button type="button" onclick="_removeNewForeignGalleryFile(${index})" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });
        }

        function _removeNewForeignGalleryFile(index) {
            _foreignGalleryFiles.splice(index, 1);
            _renderForeignGalleryPreview();
        }

        function previewForeignGallery(input) {
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    _foreignGalleryFiles.push(file);
                });
                input.value = ''; // Clear file input so the same files can be re-selected
            }
            _renderForeignGalleryPreview();
        }

        function removeForeignGalleryImage(path, btn) {
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
                    const removeInput = document.getElementById('foreign_remove_gallery_images');
                    let current = removeInput.value ? removeInput.value.split(',') : [];
                    current.push(path);
                    removeInput.value = current.join(',');
                    
                    _foreignSavedGallery = _foreignSavedGallery.filter(img => img !== path);
                    _renderForeignGalleryPreview();
                }
            });
        }

        function editForeignDestination(dest) {
            document.getElementById('foreign_dest_id').value = dest.id || '0';
            document.getElementById('foreign_dest_name').value = dest.name || '';
            document.getElementById('foreign_dest_country').value = dest.country || '';
            document.getElementById('foreign_dest_city').value = dest.city || '';
            document.getElementById('foreign_dest_location').value = dest.location || '';
            document.getElementById('foreign_dest_description').value = dest.description || '';
            document.getElementById('foreign_dest_price').value = dest.price || '0';
            document.getElementById('foreign_dest_currency').value = dest.currency || '₱';
            document.getElementById('foreign_dest_duration').value = dest.duration || '';

            const picker = document.getElementById('foreign_dest_blocked_dates')._flatpickr;
            if (picker) {
                picker.clear();
                if (dest.blocked_dates) picker.setDate(dest.blocked_dates.split(','));
            }

            document.getElementById('foreign_dest_activities_count').value = dest.activities_count || '0';
            document.getElementById('foreign_dest_group_size').value = dest.group_size || '';
            document.getElementById('foreign_dest_best_season').value = dest.best_season || '';
            document.getElementById('foreign_dest_category').value = dest.category || 'asia';

            // Populate new Promo fields
            document.getElementById('foreign_promo_start').value = dest.promo_start || '';
            document.getElementById('foreign_promo_end').value = dest.promo_end || '';
            document.getElementById('foreign_highlight_duration').value = dest.highlight_duration || 1;
            setBlockedMonths(dest.blocked_months, 'foreign-month-check');
            document.getElementById('foreign_dest_badge_text').value = dest.badge_text || '';
            document.getElementById('foreign_dest_collage_type').value = dest.collage_type || 'three';
            document.getElementById('foreign_dest_display_order').value = dest.display_order || 0;
            document.getElementById('foreign_dest_inclusions').value = dest.inclusions || '';
            document.getElementById('foreign_dest_exclusions').value = dest.exclusions || '';
            document.getElementById('foreign_dest_remarks').value = dest.remarks || '';
            document.getElementById('foreign_dest_is_active').checked = dest.is_active == 1;

            // Load itinerary
            foreignItineraryDays = [];
            if (dest.itinerary) {
                try {
                    foreignItineraryDays = typeof dest.itinerary === 'string' ? JSON.parse(dest.itinerary) : dest.itinerary;
                } catch (e) { console.error("Error parsing itinerary", e); }
            }
            renderForeignItineraryBuilder();

            // Load hotels
            foreignHotels = [];
            if (dest.hotels) {
                try {
                    foreignHotels = typeof dest.hotels === 'string' ? JSON.parse(dest.hotels) : dest.hotels;
                } catch (e) { console.error("Error parsing hotels", e); }
            }
            renderHotelBuilder('foreign');

            // Set images
            if (dest.image_path) {
                document.getElementById('foreign_preview_1').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(dest.image_path)}')`;
                document.getElementById('foreign_old_image').value = dest.image_path;
            }
            if (dest.image2_path) {
                document.getElementById('foreign_preview_2').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(dest.image2_path)}')`;
                document.getElementById('foreign_old_image2').value = dest.image2_path;
            }
            if (dest.image3_path) {
                document.getElementById('foreign_preview_3').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(dest.image3_path)}')`;
                document.getElementById('foreign_old_image3').value = dest.image3_path;
            }

            // Load Gallery
            _foreignGalleryFiles = [];
            _foreignSavedGallery = [];
            document.getElementById('foreign_remove_gallery_images').value = '';
            if (dest.image_gallery) {
                try {
                    _foreignSavedGallery = JSON.parse(dest.image_gallery) || [];
                } catch (e) { console.error("Error parsing gallery", e); }
            }
            _renderForeignGalleryPreview();

            // Reset tabs
            const firstTab = document.querySelector('#foreignDestinationModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('foreignDestinationModal').classList.add('active');
            PersistenceEngine.checkDraft('foreignDestinationForm');
            PersistenceEngine.applySavedTabState('foreignDestinationForm', '#foreignDestinationModal');
        }

        function renderForeignItineraryBuilder() {
            const container = document.getElementById('foreignItineraryBuilder');
            if (!container) return;

            if (foreignItineraryDays.length === 0) {
                container.innerHTML = '<div class="message info" style="background: #f8f9fa;">No itinerary days added. Click "Add Day" to create your itinerary.</div>';
                if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                    PersistenceEngine.saveDraft('foreignDestinationForm');
                }
                return;
            }

            let html = '<div class="itinerary-builder">';
            foreignItineraryDays.forEach((day, index) => {
                const activitiesText = Array.isArray(day.activities) ? day.activities.join('\n') : (day.activities || '');
                html += `
                    <div class="itinerary-day-item" data-index="${index}">
                        <button type="button" class="remove-day-btn" onclick="removeForeignItineraryDay(${index})">&times;</button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Day Number</label>
                                <input type="number" value="${day.day || index + 1}" onchange="updateForeignItineraryDay(${index}, 'day', this.value)">
                            </div>
                            <div class="form-group">
                                <label>Day Title</label>
                                <input type="text" value="${escapeHtml(day.title || `Day ${index + 1}`)}" onchange="updateForeignItineraryDay(${index}, 'title', this.value)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Activities (one per line)</label>
                            <textarea rows="4" onchange="updateForeignItineraryDay(${index}, 'activities', this.value)">${escapeHtml(activitiesText)}</textarea>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                PersistenceEngine.saveDraft('foreignDestinationForm');
            }
        }

        function addForeignItineraryDay() {
            const newDayNumber = foreignItineraryDays.length + 1;
            foreignItineraryDays.push({
                day: newDayNumber,
                title: `Day ${newDayNumber}`,
                activities: []
            });
            renderForeignItineraryBuilder();
        }

        function removeForeignItineraryDay(index) {
            foreignItineraryDays.splice(index, 1);
            foreignItineraryDays.forEach((day, i) => {
                day.day = i + 1;
                day.title = `Day ${i + 1}`;
            });
            renderForeignItineraryBuilder();
        }

        function updateForeignItineraryDay(index, field, value) {
            if (field === 'day') {
                foreignItineraryDays[index].day = parseInt(value);
            } else if (field === 'title') {
                foreignItineraryDays[index].title = value;
            } else if (field === 'activities') {
                const activities = value.split('\n').filter(a => a.trim());
                foreignItineraryDays[index].activities = activities;
            }
        }

        // ========== LOCAL DESTINATION JAVASCRIPT ==========
        let localItineraryDays = [];
        let localHotels = [];
        let foreignHotels = [];

        function openLocalDestinationModal() {
            document.getElementById('localDestModalTitle').innerText = 'Add Local Destination';
            document.getElementById('local_dest_id').value = '0';
            document.getElementById('local_dest_name').value = '';
            document.getElementById('local_dest_country').value = 'Philippines';
            document.getElementById('local_dest_city').value = '';
            document.getElementById('local_dest_location_name').value = '';
            document.getElementById('local_dest_description').value = '';
            document.getElementById('local_dest_price').value = '0';
            document.getElementById('local_dest_currency').value = '₱';
            document.getElementById('local_dest_duration').value = '';

            const picker = document.getElementById('local_dest_blocked_dates')._flatpickr;
            if (picker) picker.clear();

            document.getElementById('local_dest_activities_count').value = '0';
            document.getElementById('local_dest_group_size').value = '';
            document.getElementById('local_dest_best_season').value = '';
            document.getElementById('local_dest_badge_text').value = '';
            document.getElementById('local_dest_booked_count').value = '';
            document.getElementById('local_dest_display_order').value = '0';
            document.getElementById('local_dest_inclusions').value = '';
            document.getElementById('local_dest_exclusions').value = '';
            document.getElementById('local_dest_remarks').value = '';
            document.getElementById('local_dest_is_active').checked = true;

            // Reset images
            document.getElementById('local_preview_1').style.backgroundImage = '';
            document.getElementById('local_preview_2').style.backgroundImage = '';
            document.getElementById('local_preview_3').style.backgroundImage = '';
            document.getElementById('local_old_image').value = '';
            document.getElementById('local_old_image_2').value = '';
            document.getElementById('local_old_image_3').value = '';

            _localGalleryFiles = [];
            _localSavedGallery = [];
            document.getElementById('local_remove_gallery_images').value = '';
            _renderLocalGalleryPreview();

            // Reset itinerary
            localItineraryDays = [];
            renderLocalItineraryBuilder();

            // Reset hotels
            localHotels = [];
            renderHotelBuilder('local');

            // Reset tabs
            const firstTab = document.querySelector('#localDestinationModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('localDestinationModal').classList.add('active');
            PersistenceEngine.checkDraft('localDestinationForm');
            PersistenceEngine.applySavedTabState('localDestinationForm', '#localDestinationModal');
        }

        let _localGalleryFiles = []; // new files
        let _localSavedGallery  = []; // saved images paths

        function _renderLocalGalleryPreview() {
            const preview = document.getElementById('local_gallery_preview');
            if (!preview) return;
            preview.innerHTML = '';

            // 1. Render saved photos
            _localSavedGallery.forEach(img => {
                const div = document.createElement('div');
                div.style.height = '100px';
                div.style.borderRadius = '8px';
                div.style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(img)}')`;
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.style.position = 'relative';
                div.style.border = '1px solid #ddd';
                div.innerHTML = `<button type="button" onclick="removeLocalGalleryImage('${img}', this)" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });

            // 2. Render newly selected photos
            _localGalleryFiles.forEach((file, index) => {
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

                div.innerHTML = `<button type="button" onclick="_removeNewLocalGalleryFile(${index})" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });
        }

        function _removeNewLocalGalleryFile(index) {
            _localGalleryFiles.splice(index, 1);
            _renderLocalGalleryPreview();
        }

        function previewLocalGallery(input) {
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    _localGalleryFiles.push(file);
                });
                input.value = ''; // Clear file input so the same files can be re-selected
            }
            _renderLocalGalleryPreview();
        }

        function removeLocalGalleryImage(path, btn) {
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
                    const removeInput = document.getElementById('local_remove_gallery_images');
                    let current = removeInput.value ? removeInput.value.split(',') : [];
                    current.push(path);
                    removeInput.value = current.join(',');
                    
                    _localSavedGallery = _localSavedGallery.filter(img => img !== path);
                    _renderLocalGalleryPreview();
                }
            });
        }

        function editLocalDestination(destData) {
            const dest = typeof destData === 'string' ? JSON.parse(destData) : destData;
            document.getElementById('localDestModalTitle').innerText = 'Edit Local Destination';
            document.getElementById('local_dest_id').value = dest.id || '0';
            document.getElementById('local_dest_name').value = dest.name || '';
            document.getElementById('local_dest_country').value = dest.country || 'Philippines';
            document.getElementById('local_dest_city').value = dest.city || '';
            document.getElementById('local_dest_location_name').value = dest.location_name || '';
            document.getElementById('local_dest_description').value = dest.description || '';
            document.getElementById('local_dest_price').value = dest.price || '0';
            document.getElementById('local_dest_currency').value = dest.currency || '₱';
            document.getElementById('local_dest_duration').value = dest.duration || '';

            const picker = document.getElementById('local_dest_blocked_dates')._flatpickr;
            if (picker) {
                picker.clear();
                if (dest.blocked_dates) picker.setDate(dest.blocked_dates.split(','));
            }

            document.getElementById('local_dest_activities_count').value = dest.activities_count || '0';
            document.getElementById('local_dest_group_size').value = dest.group_size || '';

            // Populate new Promo fields
            document.getElementById('local_promo_start').value = dest.promo_start || '';
            document.getElementById('local_promo_end').value = dest.promo_end || '';
            document.getElementById('local_highlight_duration').value = dest.highlight_duration || 1;
            setBlockedMonths(dest.blocked_months, 'local-month-check');
            document.getElementById('local_dest_best_season').value = dest.best_season || '';
            document.getElementById('local_dest_badge_text').value = dest.badge_text || '';
            document.getElementById('local_dest_booked_count').value = dest.booked_count || '';
            document.getElementById('local_dest_display_order').value = dest.display_order || '0';
            document.getElementById('local_dest_inclusions').value = dest.inclusions || '';
            document.getElementById('local_dest_exclusions').value = dest.exclusions || '';
            document.getElementById('local_dest_remarks').value = dest.remarks || '';
            document.getElementById('local_dest_is_active').checked = dest.is_active == 1;

            // Load itinerary
            localItineraryDays = [];
            if (dest.itinerary) {
                try {
                    localItineraryDays = typeof dest.itinerary === 'string' ? JSON.parse(dest.itinerary) : dest.itinerary;
                } catch (e) { console.error("Error parsing itinerary", e); }
            }
            renderLocalItineraryBuilder();

            // Load hotels
            localHotels = [];
            if (dest.hotels) {
                try {
                    localHotels = typeof dest.hotels === 'string' ? JSON.parse(dest.hotels) : dest.hotels;
                } catch (e) { console.error("Error parsing hotels", e); }
            }
            renderHotelBuilder('local');

            // Set images
            if (dest.image_path) {
                document.getElementById('local_preview_1').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(dest.image_path)}')`;
                document.getElementById('local_old_image').value = dest.image_path;
            } else {
                document.getElementById('local_preview_1').style.backgroundImage = '';
                document.getElementById('local_old_image').value = '';
            }

            if (dest.image2_path) {
                document.getElementById('local_preview_2').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(dest.image2_path)}')`;
                document.getElementById('local_old_image_2').value = dest.image2_path;
            } else {
                document.getElementById('local_preview_2').style.backgroundImage = '';
                document.getElementById('local_old_image_2').value = '';
            }

            if (dest.image3_path) {
                document.getElementById('local_preview_3').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(dest.image3_path)}')`;
                document.getElementById('local_old_image_3').value = dest.image3_path;
            } else {
                document.getElementById('local_preview_3').style.backgroundImage = '';
                document.getElementById('local_old_image_3').value = '';
            }

            // Load Gallery
            _localGalleryFiles = [];
            _localSavedGallery = [];
            document.getElementById('local_remove_gallery_images').value = '';
            if (dest.image_gallery) {
                try {
                    _localSavedGallery = JSON.parse(dest.image_gallery) || [];
                } catch (e) { console.error("Error parsing gallery", e); }
            }
            _renderLocalGalleryPreview();

            // Reset tabs
            const firstTab = document.querySelector('#localDestinationModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('localDestinationModal').classList.add('active');
            PersistenceEngine.checkDraft('localDestinationForm');
            PersistenceEngine.applySavedTabState('localDestinationForm', '#localDestinationModal');
        }

        function renderLocalItineraryBuilder() {
            const container = document.getElementById('localItineraryBuilder');
            if (!container) return;

            if (localItineraryDays.length === 0) {
                container.innerHTML = '<div class="message info" style="background: #f8f9fa;">No itinerary days added. Click "Add Day" to create your itinerary.</div>';
                if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                    PersistenceEngine.saveDraft('localDestinationForm');
                }
                return;
            }

            let html = '<div class="itinerary-builder">';
            localItineraryDays.forEach((day, index) => {
                const activitiesText = Array.isArray(day.activities) ? day.activities.join('\n') : (day.activities || '');
                html += `
                    <div class="itinerary-day-item" data-index="${index}">
                        <button type="button" class="remove-day-btn" onclick="removeLocalItineraryDay(${index})">&times;</button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Day Number</label>
                                <input type="number" value="${day.day || index + 1}" onchange="updateLocalItineraryDay(${index}, 'day', this.value)">
                            </div>
                            <div class="form-group">
                                <label>Day Title</label>
                                <input type="text" value="${escapeHtml(day.title || `Day ${index + 1}`)}" onchange="updateLocalItineraryDay(${index}, 'title', this.value)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Activities (one per line)</label>
                            <textarea rows="4" onchange="updateLocalItineraryDay(${index}, 'activities', this.value)">${escapeHtml(activitiesText)}</textarea>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                PersistenceEngine.saveDraft('localDestinationForm');
            }
        }

        function addLocalItineraryDay() {
            const newDayNumber = localItineraryDays.length + 1;
            localItineraryDays.push({
                day: newDayNumber,
                title: `Day ${newDayNumber}`,
                activities: []
            });
            renderLocalItineraryBuilder();
        }

        function removeLocalItineraryDay(index) {
            localItineraryDays.splice(index, 1);
            localItineraryDays.forEach((day, i) => {
                day.day = i + 1;
                day.title = `Day ${i + 1}`;
            });
            renderLocalItineraryBuilder();
        }

        function updateLocalItineraryDay(index, field, value) {
            if (field === 'day') {
                localItineraryDays[index].day = parseInt(value);
            } else if (field === 'title') {
                localItineraryDays[index].title = value;
            } else if (field === 'activities') {
                const activities = value.split('\n').filter(a => a.trim());
                localItineraryDays[index].activities = activities;
            }
        }

        // Form submit handlers
        document.getElementById('foreignDestinationForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateRequiredFields(this)) return;

            // Set itinerary and hotels fields before constructing FormData
            document.getElementById('foreign_itinerary_data').value = JSON.stringify(foreignItineraryDays);
            document.getElementById('foreign_hotels_json').value = JSON.stringify(foreignHotels);

            const form = this;
            const formData = new FormData(form);

            // Remove the empty file inputs that came from the form gallery select
            formData.delete('gallery[]');

            // Append all accumulated gallery files
            _foreignGalleryFiles.forEach(file => {
                formData.append('gallery[]', file);
            });

            const activeTabBtn = document.querySelector('#foreignDestinationModal .tab-btn.active');
            const activeTab = activeTabBtn ? activeTabBtn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] : null;
            if (activeTab) {
                formData.set('active_tab', activeTab);
                PersistenceEngine.setSavedTabState('foreignDestinationForm', activeTab);
            }

            Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(json => {
                    Swal.close();
                    if (json.success) {
                        PersistenceEngine.clearDraft('foreignDestinationForm');
                        if (activeTabBtn) activeTabBtn.classList.add('tab-saved');
                        refreshPartnerItemCard('foreign_destination', json.data);
                        Swal.fire('Saved!', json.message || 'Destination saved successfully.', 'success');
                    } else {
                        Swal.fire('Error', json.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
        });

        document.getElementById('localDestinationForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateRequiredFields(this)) return;

            // Set itinerary and hotels fields before constructing FormData
            document.getElementById('local_itinerary_data').value = JSON.stringify(localItineraryDays);
            document.getElementById('local_hotels_json').value = JSON.stringify(localHotels);

            const form = this;
            const formData = new FormData(form);

            // Remove empty gallery
            formData.delete('gallery[]');

            // Append all accumulated gallery files
            _localGalleryFiles.forEach(file => {
                formData.append('gallery[]', file);
            });

            const activeTabBtn = document.querySelector('#localDestinationModal .tab-btn.active');
            const activeTab = activeTabBtn ? activeTabBtn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] : null;
            if (activeTab) {
                formData.set('active_tab', activeTab);
                PersistenceEngine.setSavedTabState('localDestinationForm', activeTab);
            }

            Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(json => {
                    Swal.close();
                    if (json.success) {
                        PersistenceEngine.clearDraft('localDestinationForm');
                        if (activeTabBtn) activeTabBtn.classList.add('tab-saved');
                        refreshPartnerItemCard('destination', json.data);
                        Swal.fire('Saved!', json.message || 'Destination saved successfully.', 'success');
                    } else {
                        Swal.fire('Error', json.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
        });

        document.getElementById('flashDealForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateRequiredFields(this)) return;

            // Set itinerary and hotels fields
            document.getElementById('flash_deal_itinerary_data').value = JSON.stringify(flashDealItineraryDays);
            document.getElementById('flash_hotels_json').value = JSON.stringify(flashHotels);

            const form = this;
            const formData = new FormData(form);

            // Remove empty gallery
            formData.delete('gallery[]');

            // Append all accumulated gallery files
            _flashGalleryFiles.forEach(file => {
                formData.append('gallery[]', file);
            });

            const activeTabBtn = document.querySelector('#flashDealModal .tab-btn.active');
            const activeTab = activeTabBtn ? activeTabBtn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] : null;
            if (activeTab) {
                formData.set('active_tab', activeTab);
                PersistenceEngine.setSavedTabState('flashDealForm', activeTab);
            }

            Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(json => {
                    Swal.close();
                    if (json.success) {
                        PersistenceEngine.clearDraft('flashDealForm');
                        if (activeTabBtn) activeTabBtn.classList.add('tab-saved');
                        refreshPartnerItemCard('flash_deal', json.data);
                        Swal.fire('Saved!', json.message || 'Flash Deal saved successfully.', 'success');
                    } else {
                        Swal.fire('Error', json.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
        });




        // ========== FLASH DEAL ITINERARY BUILDER ==========
        let flashDealItineraryDays = [];

        function renderFlashDealItineraryBuilder() {
            const container = document.getElementById('flashDealItineraryBuilder');
            if (!container) return;

            if (flashDealItineraryDays.length === 0) {
                container.innerHTML = '<div class="message info" style="background: #f8f9fa;">No itinerary days added. Click "Add Day" to create your itinerary.</div>';
                if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                    PersistenceEngine.saveDraft('flashDealForm');
                }
                return;
            }

            let html = '<div class="itinerary-builder">';
            flashDealItineraryDays.forEach((day, index) => {
                const activitiesText = Array.isArray(day.activities) ? day.activities.join('\n') : (day.activities || '');
                html += `
                    <div class="itinerary-day-item" data-index="${index}">
                        <button type="button" class="remove-day-btn" onclick="removeFlashDealItineraryDay(${index})">&times;</button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Day Number</label>
                                <input type="number" value="${day.day || index + 1}" onchange="updateFlashDealItineraryDay(${index}, 'day', this.value)">
                            </div>
                            <div class="form-group">
                                <label>Day Title</label>
                                <input type="text" value="${escapeHtml(day.title || `Day ${index + 1}`)}" onchange="updateFlashDealItineraryDay(${index}, 'title', this.value)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Activities (one per line)</label>
                            <textarea rows="4" onchange="updateFlashDealItineraryDay(${index}, 'activities', this.value)">${escapeHtml(activitiesText)}</textarea>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                PersistenceEngine.saveDraft('flashDealForm');
            }
        }

        let flashHotels = [];
        // flashDealHotels is an alias for flashHotels for backwards compatibility
        Object.defineProperty(window, 'flashDealHotels', {
            get() { return flashHotels; },
            set(v) { flashHotels = v; }
        });

        function renderHotelBuilder(type) {
            let hotelData = [];
            let containerId = '';
            let hiddenInputId = '';

            if (type === 'flash') {
                hotelData = flashHotels;
                containerId = 'flashHotelBuilderContainer';
                hiddenInputId = 'flash_hotels_json';
            } else if (type === 'local') {
                hotelData = localHotels;
                containerId = 'localHotelBuilderContainer';
                hiddenInputId = 'local_hotels_json';
            } else if (type === 'foreign') {
                hotelData = foreignHotels;
                containerId = 'foreignHotelBuilderContainer';
                hiddenInputId = 'foreign_hotels_json';
            }

            const container = document.getElementById(containerId);
            if (!container) return;

            // Sync with hidden input
            const hiddenInput = document.getElementById(hiddenInputId);
            if (hiddenInput) hiddenInput.value = JSON.stringify(hotelData);

            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                const formIdMap = {
                    'flash': 'flashDealForm',
                    'local': 'localDestinationForm',
                    'foreign': 'foreignDestinationForm'
                };
                PersistenceEngine.saveDraft(formIdMap[type]);
            }

            let html = '<div class="itinerary-days-list">';
            hotelData.forEach((hotel, index) => {
                html += `
                    <div class="itinerary-day-item" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <div class="day-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight:700; color:#003580; display: flex; align-items: center; gap: 8px; font-size: 0.95rem;">
                                <i class="fas fa-hotel" style="color: #64748b;"></i> Hotel Option #${index + 1}
                            </span>
                            <button type="button" class="btn-remove-day" onclick="removeHotel('${type}', ${index})" style="background: #fee2e2; color: #dc2626; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 700; transition: all 0.2s; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-trash-alt"></i> REMOVE
                            </button>
                        </div>
                        <div class="day-content" style="padding: 15px; background: #fff;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; font-size: 0.85rem;">Hotel Name/Type</label>
                                    <input type="text" value="${escapeHtml(hotel.name)}" 
                                           onchange="updateHotel('${type}', ${index}, 'name', this.value)" 
                                           placeholder="e.g. Standard 3-Star Hotel"
                                           style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
                                </div>
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; font-size: 0.85rem;">Price Surcharge (Add to base price)</label>
                                    <input type="number" value="${hotel.price || 0}" 
                                           onchange="updateHotel('${type}', ${index}, 'price', this.value)" 
                                           placeholder="0 if this is the base hotel"
                                           style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            if (hotelData.length === 0) {
                html = '<div style="padding:20px; text-align:center; color:#888; background:#f9f9f9; border-radius:8px; border:1px dashed #ddd; margin-bottom:15px;">No hotel options added yet. Click "Add Hotel Option" to begin.</div>';
            }

            container.innerHTML = html;
        }

        function addHotel(type) {
            if (type === 'flash') flashHotels.push({ name: '', price: 0 });
            else if (type === 'local') localHotels.push({ name: '', price: 0 });
            else if (type === 'foreign') foreignHotels.push({ name: '', price: 0 });
            renderHotelBuilder(type);
        }

        function removeHotel(type, index) {
            if (type === 'flash') flashHotels.splice(index, 1);
            else if (type === 'local') localHotels.splice(index, 1);
            else if (type === 'foreign') foreignHotels.splice(index, 1);
            renderHotelBuilder(type);
        }

        function updateHotel(type, index, field, value) {
            let hotelData = [];
            if (type === 'flash') hotelData = flashHotels;
            else if (type === 'local') hotelData = localHotels;
            else if (type === 'foreign') hotelData = foreignHotels;

            if (field === 'price') {
                hotelData[index][field] = parseFloat(value) || 0;
            } else {
                hotelData[index][field] = value;
            }

            const hiddenInputId = type === 'flash' ? 'flash_hotels_json' : (type === 'local' ? 'local_hotels_json' : 'foreign_hotels_json');
            const hiddenInput = document.getElementById(hiddenInputId);
            if (hiddenInput) hiddenInput.value = JSON.stringify(hotelData);
        }

        function addFlashDealItineraryDay() {
            const newDayNumber = flashDealItineraryDays.length + 1;
            flashDealItineraryDays.push({
                day: newDayNumber,
                title: `Day ${newDayNumber}`,
                activities: []
            });
            renderFlashDealItineraryBuilder();
        }

        function removeFlashDealItineraryDay(index) {
            flashDealItineraryDays.splice(index, 1);
            flashDealItineraryDays.forEach((day, i) => {
                day.day = i + 1;
                day.title = `Day ${i + 1}`;
            });
            renderFlashDealItineraryBuilder();
        }

        function updateFlashDealItineraryDay(index, field, value) {
            if (field === 'day') {
                flashDealItineraryDays[index].day = parseInt(value);
            } else if (field === 'title') {
                flashDealItineraryDays[index].title = value;
            } else if (field === 'activities') {
                const activities = value.split('\n').filter(a => a.trim());
                flashDealItineraryDays[index].activities = activities;
            }
        }

        // ========== FLASH DEAL FUNCTIONS (Updated) ==========
        function openFlashDealModal() {
            document.getElementById('flashDealModalTitle').innerText = 'Add Flash Deal';
            document.getElementById('deal_id').value = '0';
            document.getElementById('deal_title').value = '';
            document.getElementById('deal_category').value = '';
            document.getElementById('deal_location').value = '';
            document.getElementById('deal_description').value = '';
            document.getElementById('deal_price').value = '';
            document.getElementById('deal_currency').value = '₱';
            document.getElementById('deal_original_price').value = '';
            document.getElementById('deal_discount_percent').value = '';
            document.getElementById('deal_duration').value = '';

            const picker = document.getElementById('deal_blocked_dates')._flatpickr;
            if (picker) picker.clear();

            document.getElementById('deal_group_size').value = '';
            document.getElementById('deal_best_season').value = '';
            document.getElementById('deal_badge_text').value = '';
            document.getElementById('deal_rating').value = '';
            document.getElementById('deal_reviews').value = '';
            document.getElementById('deal_booked_count').value = '';
            document.getElementById('deal_display_order').value = '0';
            document.getElementById('deal_inclusions').value = '';
            document.getElementById('deal_exclusions').value = '';
            document.getElementById('deal_remarks').value = '';
            document.getElementById('old_image_1').value = '';
            document.getElementById('old_image_2').value = '';
            document.getElementById('old_image_3').value = '';
            document.getElementById('fd_preview_1').style.backgroundImage = '';
            document.getElementById('fd_preview_2').style.backgroundImage = '';
            document.getElementById('fd_preview_3').style.backgroundImage = '';
            document.getElementById('deal_is_active').checked = true;
            _flashGalleryFiles = [];
            _flashSavedGallery = [];
            document.getElementById('flash_remove_gallery_images').value = '';
            _renderFlashGalleryPreview();

            // Reset itinerary
            flashDealItineraryDays = [];
            renderFlashDealItineraryBuilder();

            // Reset hotels
            flashHotels = [];
            renderHotelBuilder('flash');

            // Reset tabs
            const firstTab = document.querySelector('#flashDealModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('flashDealModal').classList.add('active');
            PersistenceEngine.checkDraft('flashDealForm');
            PersistenceEngine.applySavedTabState('flashDealForm', '#flashDealModal');
        }

        let _flashGalleryFiles = []; // new files
        let _flashSavedGallery  = []; // saved images paths

        function _renderFlashGalleryPreview() {
            const preview = document.getElementById('flash_gallery_preview');
            if (!preview) return;
            preview.innerHTML = '';

            // 1. Render saved photos
            _flashSavedGallery.forEach(img => {
                const div = document.createElement('div');
                div.style.height = '100px';
                div.style.borderRadius = '8px';
                div.style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(img)}')`;
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.style.position = 'relative';
                div.style.border = '1px solid #ddd';
                div.innerHTML = `<button type="button" onclick="removeFlashGalleryImage('${img}', this)" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });

            // 2. Render newly selected photos
            _flashGalleryFiles.forEach((file, index) => {
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

                div.innerHTML = `<button type="button" onclick="_removeNewFlashGalleryFile(${index})" style="position:absolute; top:5px; right:5px; background:rgba(220,53,69,0.8); color:white; border:none; border-radius:4px; width:20px; height:20px; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            });
        }

        function _removeNewFlashGalleryFile(index) {
            _flashGalleryFiles.splice(index, 1);
            _renderFlashGalleryPreview();
        }

        function previewFlashGallery(input) {
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    _flashGalleryFiles.push(file);
                });
                input.value = ''; // Clear file input so the same files can be re-selected
            }
            _renderFlashGalleryPreview();
        }

        function removeFlashGalleryImage(path, btn) {
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
                    const removeInput = document.getElementById('flash_remove_gallery_images');
                    let current = removeInput.value ? removeInput.value.split(',') : [];
                    current.push(path);
                    removeInput.value = current.join(',');
                    
                    _flashSavedGallery = _flashSavedGallery.filter(img => img !== path);
                    _renderFlashGalleryPreview();
                }
            });
        }

        function editFlashDeal(deal) {
            document.getElementById('flashDealModalTitle').innerText = 'Edit Flash Deal';
            document.getElementById('deal_id').value = deal.id;
            document.getElementById('deal_title').value = deal.title || '';
            document.getElementById('deal_category').value = deal.category || '';
            document.getElementById('deal_location').value = deal.location || '';
            document.getElementById('deal_description').value = deal.description || '';
            document.getElementById('deal_price').value = deal.price || 0;
            document.getElementById('deal_currency').value = deal.currency || '₱';
            document.getElementById('deal_original_price').value = deal.original_price || 0;
            document.getElementById('deal_discount_percent').value = deal.discount_percent || 0;
            document.getElementById('deal_duration').value = deal.duration || '';

            const picker = document.getElementById('deal_blocked_dates')._flatpickr;
            if (picker) {
                picker.clear();
                if (deal.blocked_dates) picker.setDate(deal.blocked_dates.split(','));
            }

            document.getElementById('deal_group_size').value = deal.group_size || '';
            document.getElementById('deal_best_season').value = deal.best_season || '';

            // Populate new Promo fields
            document.getElementById('deal_promo_start').value = deal.promo_start || '';
            document.getElementById('deal_promo_end').value = deal.promo_end || '';
            document.getElementById('deal_highlight_duration').value = deal.highlight_duration || 1;
            setBlockedMonths(deal.blocked_months, 'deal-month-check');
            document.getElementById('deal_badge_text').value = deal.badge_text || '';
            document.getElementById('deal_rating').value = deal.rating || 0;
            document.getElementById('deal_reviews').value = deal.reviews || 0;
            document.getElementById('deal_booked_count').value = deal.booked_count || '';
            document.getElementById('deal_display_order').value = deal.display_order || 0;
            document.getElementById('deal_remarks').value = deal.remarks || '';
            document.getElementById('deal_is_active').checked = deal.is_active == 1;

            // Load itinerary
            flashDealItineraryDays = [];
            if (deal.itinerary) {
                try {
                    flashDealItineraryDays = typeof deal.itinerary === 'string' ? JSON.parse(deal.itinerary) : deal.itinerary;
                } catch (e) { console.error("Error parsing itinerary", e); }
            }
            renderFlashDealItineraryBuilder();

            // Load hotels
            flashHotels = [];
            if (deal.hotels) {
                try {
                    flashHotels = typeof deal.hotels === 'string' ? JSON.parse(deal.hotels) : deal.hotels;
                } catch (e) { console.error("Error parsing hotels", e); }
            }
            renderHotelBuilder('flash');

            // Parse inclusions
            if (deal.inclusions) {
                try {
                    const inclusions = JSON.parse(deal.inclusions);
                    if (Array.isArray(inclusions)) {
                        document.getElementById('deal_inclusions').value = inclusions.join('\n');
                    } else {
                        document.getElementById('deal_inclusions').value = deal.inclusions;
                    }
                } catch (e) {
                    document.getElementById('deal_inclusions').value = deal.inclusions;
                }
            }

            // Parse exclusions
            if (deal.exclusions) {
                try {
                    const exclusions = JSON.parse(deal.exclusions);
                    if (Array.isArray(exclusions)) {
                        document.getElementById('deal_exclusions').value = exclusions.join('\n');
                    } else {
                        document.getElementById('deal_exclusions').value = deal.exclusions;
                    }
                } catch (e) {
                    document.getElementById('deal_exclusions').value = deal.exclusions;
                }
            }

            if (deal.image_path) {
                document.getElementById('fd_preview_1').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(deal.image_path)}')`;
                document.getElementById('old_image_1').value = deal.image_path;
            }
            if (deal.image2_path) {
                document.getElementById('fd_preview_2').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(deal.image2_path)}')`;
                document.getElementById('old_image_2').value = deal.image2_path;
            }
            if (deal.image3_path) {
                document.getElementById('fd_preview_3').style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(deal.image3_path)}')`;
                document.getElementById('old_image_3').value = deal.image3_path;
            }

            // Load Gallery
            _flashGalleryFiles = [];
            _flashSavedGallery = [];
            document.getElementById('flash_remove_gallery_images').value = '';
            if (deal.image_gallery) {
                try {
                    _flashSavedGallery = JSON.parse(deal.image_gallery) || [];
                } catch (e) { console.error("Error parsing gallery", e); }
            }
            _renderFlashGalleryPreview();

            // Reset tabs
            const firstTab = document.querySelector('#flashDealModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('flashDealModal').classList.add('active');
            PersistenceEngine.checkDraft('flashDealForm');
            PersistenceEngine.applySavedTabState('flashDealForm', '#flashDealModal');
        }



        // ========== FLIGHT DATA FUNCTIONS ==========
        function openFlightModal() {
            document.getElementById('flightModalTitle').innerText = 'Add Flight Prices';
            document.getElementById('flight_id').value = '0';
            document.getElementById('flight_key').value = '';
            document.getElementById('flight_name').value = '';
            document.querySelectorAll('.flight-month-input').forEach(input => input.value = '');
            document.getElementById('flightModal').classList.add('active');
            PersistenceEngine.checkDraft('flightForm');
        }

        function editFlightData(flight) {
            document.getElementById('flightModalTitle').innerText = 'Edit Flight Prices';
            document.getElementById('flight_id').value = flight.id;
            document.getElementById('flight_key').value = flight.destination_key;
            document.getElementById('flight_name').value = flight.destination_name;

            const months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
            months.forEach(month => {
                const lowInput = document.getElementById(`flight_${month}_low`);
                const highInput = document.getElementById(`flight_${month}_high`);
                const airlineInput = document.getElementById(`flight_${month}_airline`);

                if (lowInput) lowInput.value = flight[`month_${month}_low`] || 0;
                if (highInput) highInput.value = flight[`month_${month}_high`] || 0;
                if (airlineInput) airlineInput.value = flight[`month_${month}_airline`] || '';
            });

            document.getElementById('flightModal').classList.add('active');
            PersistenceEngine.checkDraft('flightForm');
        }

        // ========== HOTEL DATA FUNCTIONS ==========
        function openHotelModal() {
            document.getElementById('hotelModalTitle').innerText = 'Add Hotel Rates';
            document.getElementById('hotel_id').value = '0';
            document.getElementById('hotel_key').value = '';
            document.getElementById('hotel_name').value = '';
            document.querySelectorAll('.hotel-month-input').forEach(input => input.value = '');
            document.getElementById('hotelModal').classList.add('active');
            PersistenceEngine.checkDraft('hotelForm');
        }

        function editHotelData(hotel) {
            document.getElementById('hotelModalTitle').innerText = 'Edit Hotel Rates';
            document.getElementById('hotel_id').value = hotel.id;
            document.getElementById('hotel_key').value = hotel.destination_key;
            document.getElementById('hotel_name').value = hotel.destination_name;

            const months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
            months.forEach(month => {
                const lowInput = document.getElementById(`hotel_${month}_low`);
                const highInput = document.getElementById(`hotel_${month}_high`);
                const hotelInput = document.getElementById(`hotel_${month}_hotel`);

                if (lowInput) lowInput.value = hotel[`month_${month}_low`] || 0;
                if (highInput) highInput.value = hotel[`month_${month}_high`] || 0;
                if (hotelInput) hotelInput.value = hotel[`month_${month}_hotel`] || '';
            });

            document.getElementById('hotelModal').classList.add('active');
            PersistenceEngine.checkDraft('hotelForm');
        }

        // ========== VISA ASSISTANCE FUNCTIONS ==========
        const visaCountries = {
            "af": "Afghanistan", "ax": "Aland Islands", "al": "Albania", "dz": "Algeria", "as": "American Samoa", "ad": "Andorra", "ao": "Angola", "ai": "Anguilla", "ag": "Antigua and Barbuda", "ar": "Argentina", "am": "Armenia", "aw": "Aruba", "au": "Australia", "at": "Austria", "az": "Azerbaijan", "bs": "Bahamas", "bh": "Bahrain", "bd": "Bangladesh", "bb": "Barbados", "by": "Belarus", "be": "Belgium", "bz": "Belize", "bj": "Benin", "bm": "Bermuda", "bt": "Bhutan", "bo": "Bolivia", "ba": "Bosnia and Herzegovina", "bw": "Botswana", "br": "Brazil", "io": "British Indian Ocean Territory", "bn": "Brunei Darussalam", "bg": "Bulgaria", "bf": "Burkina Faso", "bi": "Burundi", "kh": "Cambodia", "cm": "Cameroon", "ca": "Canada", "cv": "Cape Verde", "ky": "Cayman Islands", "cf": "Central African Republic", "td": "Chad", "cl": "Chile", "cn": "China", "cx": "Christmas Island", "cc": "Cocos (Keeling) Islands", "co": "Colombia", "km": "Comoros", "cg": "Congo", "cd": "Congo, Democratic Republic of the", "ck": "Cook Islands", "cr": "Costa Rica", "ci": "Cote d'Ivoire", "hr": "Croatia", "cu": "Cuba", "cw": "Curacao", "cy": "Cyprus", "cz": "Czech Republic", "dk": "Denmark", "dj": "Djibouti", "dm": "Dominica", "do": "Dominican Republic", "ec": "Ecuador", "eg": "Egypt", "sv": "El Salvador", "gq": "Equatorial Guinea", "er": "Eritrea", "ee": "Estonia", "et": "Ethiopia", "fk": "Falkland Islands", "fo": "Faroe Islands", "fj": "Fiji", "fi": "Finland", "fr": "France", "gf": "French Guiana", "pf": "French Polynesia", "tf": "French Southern Territories", "ga": "Gabon", "gm": "Gambia", "ge": "Georgia", "de": "Germany", "gh": "Ghana", "gi": "Gibraltar", "gr": "Greece", "gl": "Greenland", "gd": "Grenada", "gp": "Guadeloupe", "gu": "Guam", "gt": "Guatemala", "gg": "Guernsey", "gn": "Guinea", "gw": "Guinea-Bissau", "gy": "Guyana", "ht": "Haiti", "va": "Holy See (Vatican City)", "hn": "Honduras", "hk": "Hong Kong", "hu": "Hungary", "is": "Iceland", "in": "India", "id": "Indonesia", "ir": "Iran", "iq": "Iraq", "ie": "Ireland", "im": "Isle of Man", "il": "Israel", "it": "Italy", "jm": "Jamaica", "jp": "Japan", "je": "Jersey", "jo": "Jordan", "kz": "Kazakhstan", "ke": "Kenya", "ki": "Kiribati", "kp": "Korea, North", "kr": "Korea, South", "kw": "Kuwait", "kg": "Kyrgyzstan", "la": "Laos", "lv": "Latvia", "lb": "Lebanon", "ls": "Lesotho", "lr": "Liberia", "ly": "Libya", "li": "Liechtenstein", "lt": "Lithuania", "lu": "Luxembourg", "mo": "Macao", "mg": "Madagascar", "mw": "Malawi", "my": "Malaysia", "mv": "Maldives", "ml": "Mali", "mt": "Malta", "mh": "Marshall Islands", "mq": "Martinique", "mr": "Mauritania", "mu": "Mauritius", "yt": "Mayotte", "mx": "Mexico", "fm": "Micronesia", "md": "Moldova", "mc": "Monaco", "mn": "Mongolia", "me": "Montenegro", "ms": "Montserrat", "ma": "Morocco", "mz": "Mozambique", "mm": "Myanmar", "na": "Namibia", "nr": "Nauru", "np": "Nepal", "nl": "Netherlands", "nc": "New Caledonia", "nz": "New Zealand", "ni": "Nicaragua", "ne": "Niger", "ng": "Nigeria", "nu": "Niue", "nf": "Norfolk Island", "mp": "Northern Mariana Islands", "no": "Norway", "om": "Oman", "pk": "Pakistan", "pw": "Palau", "ps": "Palestine", "pa": "Panama", "pg": "Papua New Guinea", "py": "Paraguay", "pe": "Peru", "ph": "Philippines", "pl": "Poland", "pt": "Portugal", "pr": "Puerto Rico", "qa": "Qatar", "re": "Reunion", "ro": "Romania", "ru": "Russia", "rw": "Rwanda", "sh": "Saint Helena", "kn": "Saint Kitts and Nevis", "lc": "Saint Lucia", "pm": "Saint Pierre and Miquelon", "vc": "Saint Vincent and the Grenadines", "ws": "Samoa", "sm": "San Marino", "st": "Sao Tome and Principe", "sa": "Saudi Arabia", "sn": "Senegal", "rs": "Serbia", "sc": "Seychelles", "sl": "Sierra Leone", "sg": "Singapore", "sx": "Sint Maarten", "sk": "Slovakia", "si": "Slovenia", "sb": "Solomon Islands", "so": "Somalia", "za": "South Africa", "gs": "South Georgia", "ss": "South Sudan", "es": "Spain", "lk": "Sri Lanka", "sd": "Sudan", "sr": "Suriname", "sj": "Svalbard and Jan Mayen", "sz": "Swaziland", "se": "Sweden", "ch": "Switzerland", "sy": "Syria", "tw": "Taiwan", "tj": "Tajikistan", "tz": "Tanzania", "th": "Thailand", "tl": "Timor-Leste", "tg": "Togo", "tk": "Tokelau", "to": "Tonga", "tt": "Trinidad and Tobago", "tn": "Tunisia", "tr": "Turkey", "tm": "Turkmenistan", "tc": "Turks and Caicos Islands", "tv": "Tuvalu", "ug": "Uganda", "ua": "Ukraine", "ae": "United Arab Emirates", "gb": "United Kingdom", "us": "United States", "uy": "Uruguay", "uz": "Uzbekistan", "vu": "Vanuatu", "ve": "Venezuela", "vn": "Vietnam", "vg": "British Virgin Islands", "vi": "US Virgin Islands", "wf": "Wallis and Futuna", "eh": "Western Sahara", "ye": "Yemen", "zm": "Zambia", "zw": "Zimbabwe"
        };

        function renderVisaCountries(query = '') {
            const results = document.getElementById('visaCountryResults');
            if (!results) return;

            results.innerHTML = '';
            const lowerQuery = query.toLowerCase();

            Object.entries(visaCountries).forEach(([code, name]) => {
                if (name.toLowerCase().includes(lowerQuery)) {
                    const item = document.createElement('div');
                    item.className = 'picker-item';
                    item.onclick = () => selectVisaCountry(name, code);
                    item.innerHTML = `
                        <img src="https://flagcdn.com/w40/${code}.png" alt="${name}" onerror="this.onerror=null;this.src='https://via.placeholder.com/40x30?text=Flag'">
                        <span>${name}</span>
                    `;
                    results.appendChild(item);
                }
            });

            if (results.innerHTML === '') {
                results.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #666; font-size: 0.8rem;">No countries found.</div>';
            }
        }

        function filterVisaCountries() {
            const query = document.getElementById('visaCountrySearch').value;
            const clearBtn = document.querySelector('.clear-picker-search');
            if (clearBtn) {
                clearBtn.style.display = query ? 'flex' : 'none';
            }
            renderVisaCountries(query);
        }

        function clearVisaCountrySearch() {
            const searchInput = document.getElementById('visaCountrySearch');
            if (searchInput) {
                searchInput.value = '';
                filterVisaCountries();
                searchInput.focus();
            }
        }

        function selectVisaCountry(name, code) {
            // Update the form fields
            document.getElementById('visa_title').value = name;
            document.getElementById('visa_icon_type').value = 'image';
            document.getElementById('visa_icon_value').value = `https://flagcdn.com/w80/${code}.png`;

            // Auto-assign category based on continent
            const asia = ['af', 'am', 'az', 'bh', 'bd', 'bt', 'bn', 'kh', 'cn', 'cy', 'ge', 'in', 'id', 'ir', 'iq', 'il', 'jp', 'jo', 'kz', 'kw', 'kg', 'la', 'lb', 'my', 'mv', 'mn', 'mm', 'np', 'kp', 'om', 'pk', 'ps', 'ph', 'qa', 'sa', 'sg', 'kr', 'lk', 'sy', 'tw', 'tj', 'th', 'tl', 'tr', 'tm', 'ae', 'uz', 'vn', 'ye'];
            const africa = ['dz', 'ao', 'bj', 'bw', 'bf', 'bi', 'cv', 'cm', 'cf', 'td', 'km', 'cg', 'cd', 'ci', 'dj', 'eg', 'gq', 'er', 'sz', 'et', 'ga', 'gm', 'gh', 'gn', 'gw', 'ke', 'ls', 'lr', 'ly', 'mg', 'mw', 'ml', 'mr', 'mu', 'yt', 'ma', 'mz', 'na', 'ne', 'ng', 'rw', 'st', 'sn', 'sc', 'sl', 'so', 'za', 'ss', 'sd', 'tz', 'tg', 'tn', 'ug', 'zm', 'zw'];
            const northAmerica = ['ai', 'ag', 'aw', 'bs', 'bb', 'bz', 'bm', 'vg', 'ca', 'ky', 'cr', 'cu', 'cw', 'dm', 'do', 'sv', 'gl', 'gd', 'gp', 'gt', 'ht', 'hn', 'jm', 'mq', 'mx', 'ms', 'ni', 'pa', 'pr', 'bl', 'kn', 'lc', 'mf', 'pm', 'vc', 'sx', 'tt', 'tc', 'us', 'vi'];
            const southAmerica = ['ar', 'bo', 'br', 'cl', 'co', 'ec', 'fk', 'gf', 'gy', 'py', 'pe', 'sr', 'uy', 've'];
            const europe = ['ax', 'al', 'ad', 'at', 'by', 'be', 'ba', 'bg', 'hr', 'cz', 'dk', 'ee', 'fo', 'fi', 'fr', 'de', 'gi', 'gr', 'gg', 'va', 'hu', 'is', 'ie', 'im', 'it', 'je', 'lv', 'li', 'lt', 'lu', 'mk', 'mt', 'md', 'mc', 'me', 'nl', 'no', 'pl', 'pt', 'ro', 'ru', 'sm', 'rs', 'sk', 'si', 'es', 'sj', 'se', 'ch', 'ua', 'gb'];
            const australia = ['au', 'fj', 'ki', 'mh', 'fm', 'nr', 'nz', 'pw', 'pg', 'ws', 'sb', 'to', 'tv', 'vu'];
            const antarctica = ['aq'];

            let assignedCategory = 'International'; // default fallback
            if (asia.includes(code)) {
                assignedCategory = 'Asia';
            } else if (africa.includes(code)) {
                assignedCategory = 'Africa';
            } else if (northAmerica.includes(code)) {
                assignedCategory = 'North America';
            } else if (southAmerica.includes(code)) {
                assignedCategory = 'South America';
            } else if (europe.includes(code)) {
                assignedCategory = 'Europe';
            } else if (australia.includes(code)) {
                assignedCategory = 'Australia';
            } else if (antarctica.includes(code)) {
                assignedCategory = 'Antarctica';
            }

            document.getElementById('visa_category').value = assignedCategory;

            // Re-run the toggle logic to show the correct field
            toggleVisaIconFields();

            // Highlight the selected state or visual feedback
            const searchInput = document.getElementById('visaCountrySearch');
            searchInput.value = ''; // Reset search
            renderVisaCountries(); // Reset list

            // Optional: Scroll to the Title field to show it was updated
            document.getElementById('visa_title').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        function toggleVisaIconFields() {
            const type = document.getElementById('visa_icon_type').value;
            const valueGroup = document.getElementById('visa_icon_value_group');
            const uploadGroup = document.getElementById('visa_icon_upload_group');
            const label = document.getElementById('visa_icon_label');

            if (type === 'upload') {
                valueGroup.style.display = 'none';
                uploadGroup.style.display = 'block';
            } else {
                valueGroup.style.display = 'block';
                uploadGroup.style.display = 'none';
                label.innerText = type === 'icon' ? 'FontAwesome Icon Class' : 'Flag URL';
            }
        }

        function openVisaModal() {
            document.getElementById('visaModalTitle').innerText = 'Add Visa Service';
            document.getElementById('visaForm').reset();
            document.getElementById('visa_id').value = '0';
            document.getElementById('visa_old_icon_value').value = '';
            document.getElementById('visa_disclaimer').value = '';
            document.getElementById('visa_important_notes').value = '';
            document.getElementById('visa_status_required').checked = true;

            // Reset country picker
            const searchInput = document.getElementById('visaCountrySearch');
            if (searchInput) searchInput.value = '';
            renderVisaCountries();

            toggleVisaIconFields();
            document.getElementById('visaModal').classList.add('active');
            PersistenceEngine.checkDraft('visaForm');
            PersistenceEngine.applySavedTabState('visaForm', '#visaModal');
        }

        function editVisa(visa) {
            document.getElementById('visaModalTitle').innerText = 'Edit Visa Service';
            document.getElementById('visa_id').value = visa.id;
            document.getElementById('visa_title').value = visa.title;
            document.getElementById('visa_category').value = visa.category;
            document.getElementById('visa_description').value = visa.description || '';
            document.getElementById('visa_price').value = visa.price;
            document.getElementById('visa_currency').value = visa.currency || '₱';
            document.getElementById('visa_processing_time').value = visa.processing_time || '';
            document.getElementById('visa_display_order').value = visa.display_order || 0;
            document.getElementById('visa_is_active').checked = visa.is_active == 1;
            document.getElementById('visa_icon_type').value = visa.icon_type;
            document.getElementById('visa_icon_value').value = (visa.icon_type !== 'upload') ? (visa.icon_value || '') : '';
            document.getElementById('visa_old_icon_value').value = visa.icon_value || '';

            // Handle requirements (JSON to line-separated)
            let reqs = '';
            if (visa.requirements) {
                try {
                    const reqArray = JSON.parse(visa.requirements);
                    reqs = reqArray.join('\n');
                } catch (e) { reqs = visa.requirements; }
            }
            document.getElementById('visa_requirements').value = reqs;
            document.getElementById('visa_disclaimer').value = visa.disclaimer || '';
            document.getElementById('visa_important_notes').value = visa.important_notes || '';
            const visaStatus = visa.visa_status || 'required';
            document.querySelector(`input[name="visa_status"][value="${visaStatus}"]`).checked = true;

            toggleVisaIconFields();
            document.getElementById('visaModal').classList.add('active');
            PersistenceEngine.checkDraft('visaForm');
            PersistenceEngine.applySavedTabState('visaForm', '#visaModal');
        }

        document.getElementById('visaForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateRequiredFields(this)) return;

            const form = this;
            const formData = new FormData(form);

            const activeTabBtn = document.querySelector('#visaModal .tab-btn.active');
            const activeTab = activeTabBtn ? activeTabBtn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] : null;
            if (activeTab) {
                formData.set('active_tab', activeTab);
                PersistenceEngine.setSavedTabState('visaForm', activeTab);
            }

            Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(json => {
                    Swal.close();
                    if (json.success) {
                        PersistenceEngine.clearDraft('visaForm');
                        if (activeTabBtn) activeTabBtn.classList.add('tab-saved');
                        Swal.fire('Saved!', json.message || 'Visa service saved successfully.', 'success');
                    } else {
                        Swal.fire('Error', json.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
        });

        // flightForm and hotelForm still submit as normal (non-AJAX) forms; just gate
        // them on the same required-field check so missing info is never silent.
        document.getElementById('flightForm')?.addEventListener('submit', function (e) {
            if (!validateRequiredFields(this)) e.preventDefault();
        });
        document.getElementById('hotelForm')?.addEventListener('submit', function (e) {
            if (!validateRequiredFields(this)) e.preventDefault();
        });

        // ========== COMMON FUNCTIONS ==========

        // Checks every [required] field in a form and reports back what's missing,
        // including fields hidden inside an inactive tab (which the browser's native
        // validation cannot report on, since it refuses to focus a hidden control).
        function getFieldLabel(field) {
            if (field.dataset.label) return field.dataset.label;
            const group = field.closest('.form-group') || field.closest('div');
            if (group) {
                const label = group.querySelector('label');
                if (label) return label.textContent.replace('*', '').trim();
            }
            return field.name || 'This field';
        }

        function validateRequiredFields(form) {
            const activeTabContent = form.querySelector('.tab-content.active');
            const requiredFields = activeTabContent ? activeTabContent.querySelectorAll('[required]') : form.querySelectorAll('[required]');
            const missing = [];
            let firstInvalid = null;

            requiredFields.forEach(field => {
                if (field.type === 'radio') {
                    const group = form.querySelectorAll(`input[name="${field.name}"]`);
                    const anyChecked = Array.from(group).some(r => r.checked);
                    if (!anyChecked && !missing.includes(getFieldLabel(field))) {
                        missing.push(getFieldLabel(field));
                        if (!firstInvalid) firstInvalid = field;
                    }
                    return;
                }
                const val = (field.value || '').trim();
                if (!val) {
                    missing.push(getFieldLabel(field));
                    if (!firstInvalid) firstInvalid = field;
                }
            });

            if (missing.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Required Information',
                    html: 'Please fill in the following before saving:<br><br><strong>' + missing.join('<br>') + '</strong>'
                });

                if (firstInvalid) {
                    const tabContent = firstInvalid.closest('.tab-content');
                    if (tabContent && !tabContent.classList.contains('active')) {
                        const tabBtn = document.querySelector(`[onclick*="'${tabContent.id}'"]`);
                        if (tabBtn) tabBtn.click();
                    }
                    setTimeout(() => firstInvalid.focus(), 100);
                }
                return false;
            }
            return true;
        }

        function deleteItem(type, id, name) {
            Swal.fire({
                title: 'Delete Item?',
                text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let action = '';
                    if (type === 'flash_deal') action = 'delete_flash_deal';
                    else if (type === 'foreign_destination') action = 'delete_foreign_destination';
                    else if (type === 'destination') action = 'delete_destination';
                    else if (type === 'flight_data') action = 'delete_flight_data';
                    else if (type === 'hotel_data') action = 'delete_hotel_data';
                    else if (type === 'site_service') action = 'delete_site_service';
                    else if (type === 'visa') action = 'delete_visa';

                    Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                    const formData = new FormData();
                    formData.append('action', action);
                    formData.append('id', id);

                    fetch('partner-content-manager.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(json => {
                            Swal.close();
                            if (json.success) {
                                Swal.fire('Deleted!', json.message || 'Item deleted successfully.', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Error', json.message || 'Something went wrong.', 'error');
                            }
                        })
                        .catch(err => {
                            Swal.close();
                            Swal.fire('Error', 'Network error: ' + err.message, 'error');
                        });
                }
            });
        }

        function switchServiceTab(evt, tabId) {
            const contents = document.querySelectorAll('#serviceModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#serviceModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        function getSavedServiceTabs(s) {
            const tabs = { general: false, details: false, itinerary: false, pricing: false, schedule: false, policies: false, gallery: false };
            if (s.title || s.service_code || s.category || s.description) tabs.general = true;
            if (s.highlights || s.amenities || (s.inclusions && s.inclusions !== '[]') || (s.exclusions && s.exclusions !== '[]')) tabs.details = true;
            if (s.itinerary && s.itinerary.length > 0) tabs.itinerary = true;
            if (parseFloat(s.price) > 0 || (s.available_slots && parseInt(s.available_slots) > 0) || (s.currency && String(s.currency).trim() !== '')) tabs.pricing = true;
            if (s.duration || s.booking_deadline || s.departure_date || s.return_date) tabs.schedule = true;
            if (s.required_documents || s.travel_requirements || s.cancellation_policy || s.terms_conditions) tabs.policies = true;
            const galleryPaths = [];
            if (s.featured_image) galleryPaths.push(s.featured_image);
            if (s.image_gallery) {
                try {
                    const parsed = typeof s.image_gallery === 'string' ? JSON.parse(s.image_gallery) : s.image_gallery;
                    if (Array.isArray(parsed)) galleryPaths.push(...parsed.filter(Boolean));
                } catch (e) {}
            }
            if (galleryPaths.length > 0) tabs.gallery = true;
            return tabs;
        }

        function setServiceTabSavedState(tabs) {
            document.querySelectorAll('#serviceModal .tab-btn').forEach(btn => {
                const target = btn.getAttribute('onclick')?.match(/'([^']+)'/);
                const t = target ? target[1] : null;
                if (!t) return;
                btn.classList.toggle('tab-saved',
                    (t === 's-tab-general' && tabs.general) ||
                    (t === 's-tab-details' && tabs.details) ||
                    (t === 's-tab-itinerary' && tabs.itinerary) ||
                    (t === 's-tab-pricing' && tabs.pricing) ||
                    (t === 's-tab-schedule' && tabs.schedule) ||
                    (t === 's-tab-policies' && tabs.policies) ||
                    (t === 's-tab-gallery' && tabs.gallery)
                );
            });
        }

        function switchFlashDealTab(evt, tabId) {
            const contents = document.querySelectorAll('#flashDealModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#flashDealModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        function switchLocalTab(evt, tabId) {
            const contents = document.querySelectorAll('#localDestinationModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#localDestinationModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        function switchForeignTab(evt, tabId) {
            const contents = document.querySelectorAll('#foreignDestinationModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#foreignDestinationModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        function addServiceItineraryDay(title = '', desc = '') {
            const list = document.getElementById('service-itinerary-list');
            const dayNum = list.children.length + 1;
            const item = document.createElement('div');
            item.className = 'itinerary-item';
            item.innerHTML = `
                <div class="remove-day" onclick="this.parentElement.remove(); updateServiceDayNumbers();"><i class="fas fa-trash"></i></div>
                <div style="font-weight: 700; color: #003580; margin-bottom: 15px;">Day <span class="day-num">${dayNum}</span></div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="itinerary[${dayNum}][title]" value="${title}" placeholder="e.g. Arrival & Check-in">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Description</label>
                    <textarea name="itinerary[${dayNum}][description]" rows="2">${desc}</textarea>
                </div>
                <input type="hidden" name="itinerary[${dayNum}][day_number]" value="${dayNum}" class="day-hidden-num">
            `;
            list.appendChild(item);
            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                PersistenceEngine.saveDraft('serviceForm');
            }
        }

        function updateServiceDayNumbers() {
            const list = document.getElementById('service-itinerary-list');
            Array.from(list.children).forEach((item, index) => {
                const dayNum = index + 1;
                item.querySelector('.day-num').innerText = dayNum;
                item.querySelector('.day-hidden-num').value = dayNum;
                item.querySelector('input[name*="[title]"]').name = `itinerary[${dayNum}][title]`;
                item.querySelector('textarea[name*="[description]"]').name = `itinerary[${dayNum}][description]`;
            });
            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                PersistenceEngine.saveDraft('serviceForm');
            }
        }

        // Accumulated gallery files for cruise form
        let _cruiseGalleryFiles = [];  // new files to be uploaded
        let _cruiseSavedGallery = []; // already-saved paths from DB

        function previewCruiseGallery(input) {
            const preview = document.getElementById('cruise_gallery_preview');
            const countBadge = document.getElementById('cruise_gallery_count');

            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    _cruiseGalleryFiles.push(file);
                });
                input.value = '';
            }

            _renderCruiseGalleryPreview(preview, countBadge);
        }

        function _renderCruiseGalleryPreview(preview, countBadge) {
            preview.innerHTML = '';

            const savedCount = _cruiseSavedGallery.length;
            const newCount   = _cruiseGalleryFiles.length;
            const total      = savedCount + newCount;

            if (countBadge) {
                countBadge.innerText = `${total} Photo${total !== 1 ? 's' : ''}${savedCount > 0 && newCount > 0 ? ' (' + savedCount + ' saved + ' + newCount + ' new)' : ''}`;
                countBadge.style.background = '#003580';
            }

            // 1. Render saved (already-uploaded) photos first
            _cruiseSavedGallery.forEach((img) => {
                const div = document.createElement('div');
                div.className = 'grid-preview-item';
                div.style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(img)}')`;
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.innerHTML = `<button type="button" class="remove-img-overlay"
                    onclick="removeCruiseGalleryImage('${img}', this)"
                    title="Remove saved photo">
                    <i class="fas fa-times"></i>
                </button>`;
                preview.appendChild(div);
            });

            // 2. Render newly selected (not yet uploaded) photos
            _cruiseGalleryFiles.forEach((file, index) => {
                const reader = new FileReader();
                const div = document.createElement('div');
                div.className = 'grid-preview-item';
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.innerHTML = `<button type="button" class="remove-img-overlay"
                    onclick="_removeNewCruiseGalleryFile(${index})"
                    title="Remove new photo">
                    <i class="fas fa-times"></i>
                </button>`;
                reader.onload = function (e) {
                    div.style.backgroundImage = `url(${e.target.result})`;
                };
                reader.readAsDataURL(file);
                preview.appendChild(div);
            });

            // 3. "Add More" button always last
            const addBtn = document.createElement('div');
            addBtn.className = 'grid-preview-item';
            addBtn.style.display = 'flex';
            addBtn.style.alignItems = 'center';
            addBtn.style.justifyContent = 'center';
            addBtn.style.background = '#f8fafc';
            addBtn.style.border = '2px dashed #003580';
            addBtn.style.cursor = 'pointer';
            addBtn.style.transition = 'background 0.2s';
            addBtn.onclick = () => document.getElementById('cruise_gallery_input').click();
            addBtn.innerHTML = `
                <div style="text-align: center; color: #003580;">
                    <i class="fas fa-plus-circle" style="font-size: 1.5rem; margin-bottom: 4px; display:block;"></i>
                    <div style="font-size: 0.7rem; font-weight: 700;">Add Photos</div>
                </div>
            `;
            preview.appendChild(addBtn);
        }

        function _removeNewCruiseGalleryFile(index) {
            _cruiseGalleryFiles.splice(index, 1);
            const preview    = document.getElementById('cruise_gallery_preview');
            const countBadge = document.getElementById('cruise_gallery_count');
            _renderCruiseGalleryPreview(preview, countBadge);
        }

        function removeCruiseGalleryImage(path, btn) {
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
                    const removeInput = document.getElementById('cruise_remove_gallery_images');
                    let current = removeInput.value ? removeInput.value.split(',') : [];
                    current.push(path);
                    removeInput.value = current.join(',');
                    btn.parentElement.remove();
                    _cruiseSavedGallery = _cruiseSavedGallery.filter(img => img !== path);
                    const preview    = document.getElementById('cruise_gallery_preview');
                    const countBadge = document.getElementById('cruise_gallery_count');
                    _renderCruiseGalleryPreview(preview, countBadge);
                }
            });
        }

        // Accumulated gallery files for service form
        let _serviceGalleryFiles = [];  // new files to be uploaded
        let _serviceSavedGallery = []; // already-saved paths from DB

        function previewServiceGallery(input) {
            const preview = document.getElementById('service_gallery_preview');
            const countBadge = document.getElementById('service_gallery_count');

            // Merge newly selected files into accumulated list
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    _serviceGalleryFiles.push(file);
                });
                // Reset the input so the same files can be re-selected if needed
                input.value = '';
            }

            _renderServiceGalleryPreview(preview, countBadge);
        }

        function _renderServiceGalleryPreview(preview, countBadge) {
            preview.innerHTML = '';

            const savedCount = _serviceSavedGallery.length;
            const newCount   = _serviceGalleryFiles.length;
            const total      = savedCount + newCount;

            if (countBadge) {
                countBadge.innerText = `${total} Photo${total !== 1 ? 's' : ''}${savedCount > 0 && newCount > 0 ? ' (' + savedCount + ' saved + ' + newCount + ' new)' : ''}`;
                countBadge.style.background = '#003580';
            }

            // 1. Render saved (already-uploaded) photos first
            _serviceSavedGallery.forEach((img) => {
                const div = document.createElement('div');
                div.className = 'grid-preview-item';
                div.style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(img)}')`;
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.innerHTML = `<button type="button" class="remove-img-overlay"
                    onclick="removeServiceGalleryImage('${img}', this)"
                    title="Remove saved photo">
                    <i class="fas fa-times"></i>
                </button>`;
                preview.appendChild(div);
            });

            // 2. Render newly selected (not yet uploaded) photos
            _serviceGalleryFiles.forEach((file, index) => {
                const reader = new FileReader();
                const div = document.createElement('div');
                div.className = 'grid-preview-item';
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.innerHTML = `<button type="button" class="remove-img-overlay"
                    onclick="_removeNewServiceGalleryFile(${index})"
                    title="Remove new photo">
                    <i class="fas fa-times"></i>
                </button>`;
                reader.onload = function (e) {
                    div.style.backgroundImage = `url(${e.target.result})`;
                };
                reader.readAsDataURL(file);
                preview.appendChild(div);
            });

            // 3. "Add More" button always last
            const addBtn = document.createElement('div');
            addBtn.className = 'grid-preview-item';
            addBtn.style.display = 'flex';
            addBtn.style.alignItems = 'center';
            addBtn.style.justifyContent = 'center';
            addBtn.style.background = '#f8fafc';
            addBtn.style.border = '2px dashed #003580';
            addBtn.style.cursor = 'pointer';
            addBtn.style.transition = 'background 0.2s';
            addBtn.onclick = () => document.getElementById('service_gallery_input').click();
            addBtn.innerHTML = `
                <div style="text-align: center; color: #003580;">
                    <i class="fas fa-plus-circle" style="font-size: 1.5rem; margin-bottom: 4px; display:block;"></i>
                    <div style="font-size: 0.7rem; font-weight: 700;">Add Photos</div>
                </div>
            `;
            preview.appendChild(addBtn);
        }

        function _removeNewServiceGalleryFile(index) {
            _serviceGalleryFiles.splice(index, 1);
            const preview    = document.getElementById('service_gallery_preview');
            const countBadge = document.getElementById('service_gallery_count');
            _renderServiceGalleryPreview(preview, countBadge);
        }

        function removeServiceGalleryImage(path, btn) {
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
                    const removeInput = document.getElementById('service_remove_gallery_images');
                    let current = removeInput.value ? removeInput.value.split(',') : [];
                    current.push(path);
                    removeInput.value = current.join(',');
                    btn.parentElement.remove();
                }
            });
        }

        function getServiceTypeLabel(type) {
            if (type === 'premium') return 'Hotel';
            return type.charAt(0).toUpperCase() + type.slice(1);
        }

        function openServiceModal(type) {
            document.querySelectorAll('#serviceModal .tab-btn').forEach(btn => btn.classList.remove('tab-saved'));
            document.getElementById('serviceModalTitle').innerText = 'Add ' + getServiceTypeLabel(type);
            document.getElementById('serviceForm').reset();
            document.getElementById('service_id').value = '0';
            document.getElementById('service_type').value = type;
            document.getElementById('service_old_featured_image').value = '';
            document.getElementById('service_remove_gallery_images').value = '';
            _serviceGalleryFiles = []; // Reset accumulated gallery files
            _serviceSavedGallery  = []; // Reset saved gallery

            // Reset Featured Preview
            const featPreview = document.getElementById('service_featured_preview');
            featPreview.style.backgroundImage = '';
            featPreview.innerHTML = `
                <div style="text-align: center; color: #64748b;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    <span style="font-size: 0.85rem; font-weight: 600;">Click to Upload Cover</span>
                </div>
            `;

            // Reset Gallery Preview
            document.getElementById('service_gallery_preview').innerHTML = `
                <div class="grid-preview-item" style="grid-column: span 2; grid-row: span 2; display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 2px dashed #cbd5e1; cursor: pointer;"
                     onclick="document.getElementById('service_gallery_input').click()">
                    <div style="text-align: center; color: #64748b;">
                        <i class="fas fa-plus-circle" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        <div style="font-size: 0.75rem; font-weight: 600;">Add Gallery</div>
                    </div>
                </div>
            `;
            document.getElementById('service_gallery_count').innerText = '0 / 5 Photos';
            document.getElementById('service_gallery_count').style.background = '#003580';

            document.getElementById('service-itinerary-list').innerHTML = '';

            document.getElementById('service_icon_class').value = type === 'cruise' ? 'fas fa-ship' : (type === 'flight' ? 'fas fa-plane' : (type === 'experience' ? 'fas fa-mountain' : 'fas fa-crown'));
            document.getElementById('service_currency').value = '₱';
            document.getElementById('service_display_order').value = '0';
            document.getElementById('service_is_active').checked = true;
            document.getElementById('service_is_featured').checked = false;
            document.getElementById('service_status_text').value = 'Available';
            document.getElementById('service_available_slots').value = '0';

            // Reset tabs
            const firstTab = document.querySelector('#serviceModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('serviceModal').classList.add('active');
            PersistenceEngine.checkDraft('serviceForm');
            PersistenceEngine.applySavedTabState('serviceForm', '#serviceModal');
        }

        function editService(serviceData) {
            const id = typeof serviceData === 'object' ? serviceData.id : serviceData;

            _serviceGalleryFiles = []; // Reset accumulated gallery files

            const formData = new FormData();
            formData.append('action', 'get_site_service');
            formData.append('id', id);

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const s = res.data;
                        document.getElementById('serviceModalTitle').innerText = 'Edit ' + getServiceTypeLabel(s.service_type);
                        document.getElementById('serviceForm').reset();
                        document.getElementById('service_id').value = s.id;
                        document.getElementById('service_type').value = s.service_type;
                        document.getElementById('service_title').value = s.title || '';
                        document.getElementById('service_service_code').value = s.service_code || '';
                        document.getElementById('service_badge_text').value = s.badge_text || '';
                        document.getElementById('service_category').value = s.category || '';
                        document.getElementById('service_tags').value = s.tags || '';
                        document.getElementById('service_description').value = s.description || '';
                        document.getElementById('service_full_description').value = s.full_description || '';
                        document.getElementById('service_highlights').value = s.highlights || '';
                        document.getElementById('service_inclusions').value = s.inclusions || '';
                        document.getElementById('service_exclusions').value = s.exclusions || '';
                        document.getElementById('service_amenities').value = s.amenities || '';
                        document.getElementById('service_required_documents').value = s.required_documents || '';
                        document.getElementById('service_travel_requirements').value = s.travel_requirements || '';
                        document.getElementById('service_cancellation_policy').value = s.cancellation_policy || '';
                        document.getElementById('service_terms_conditions').value = s.terms_conditions || '';
                        document.getElementById('service_icon_class').value = s.icon_class || '';
                        document.getElementById('service_price').value = s.price || 0;
                        document.getElementById('service_currency').value = s.currency || '₱';
                        document.getElementById('service_duration').value = s.duration || '';
                        document.getElementById('service_available_slots').value = s.available_slots || 0;
                        document.getElementById('service_booking_deadline').value = s.booking_deadline || '';
                        document.getElementById('service_departure_date').value = s.departure_date || '';
                        document.getElementById('service_return_date').value = s.return_date || '';
                        document.getElementById('service_status_text').value = s.status_text || 'Available';
                        document.getElementById('service_display_order').value = s.display_order || 0;
                        document.getElementById('service_is_active').checked = s.is_active == 1;
                        document.getElementById('service_is_featured').checked = s.is_featured == 1;

                        document.getElementById('service_old_featured_image').value = s.featured_image || '';
                        const featPreview = document.getElementById('service_featured_preview');
                        if (s.featured_image) {
                            featPreview.style.backgroundImage = `url('${PersistenceEngine.normalizeAssetPath(s.featured_image)}')`;
                            featPreview.innerHTML = '';
                        } else {
                            featPreview.style.backgroundImage = '';
                            featPreview.innerHTML = `
                                <div style="text-align: center; color: #64748b;">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                    <span style="font-size: 0.85rem; font-weight: 600;">Click to Upload Cover</span>
                                </div>
                            `;
                        }

                        document.getElementById('service_remove_gallery_images').value = '';

                        // Load saved gallery into the tracker so it persists while editing
                        _serviceSavedGallery = [];
                        if (s.image_gallery) {
                            try { _serviceSavedGallery = JSON.parse(s.image_gallery); } catch(e) {}
                        }

                        // Render the full gallery preview (saved + any new) via shared helper
                        const galleryPreview = document.getElementById('service_gallery_preview');
                        const countBadge     = document.getElementById('service_gallery_count');
                        _renderServiceGalleryPreview(galleryPreview, countBadge);

                        // Itinerary
                        const itList = document.getElementById('service-itinerary-list');
                        itList.innerHTML = '';
                        if (s.itinerary && s.itinerary.length > 0) {
                            s.itinerary.forEach(it => addServiceItineraryDay(it.title, it.description));
                        }

                        // Reset tabs
                        const firstTab = document.querySelector('#serviceModal .tab-btn');
                        if (firstTab) firstTab.click();

                        document.getElementById('serviceModal').classList.add('active');
                        PersistenceEngine.checkDraft('serviceForm');
                        PersistenceEngine.applySavedTabState('serviceForm', '#serviceModal');
                    }
                });
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                const form = modal.querySelector('form');
                if (form) {
                    PersistenceEngine.saveDraft(form.id);
                    Swal.fire({
                        icon: 'info',
                        title: 'Draft Saved',
                        text: 'Your progress has been automatically saved as a draft.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        backdrop: false
                    });
                }
                modal.classList.remove('active');
            }
        }

        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById(previewId);
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                    preview.style.backgroundSize = 'cover';
                    preview.style.backgroundPosition = 'center';
                    // Clear placeholder text if it's a premium preview frame
                    if (preview.classList.contains('featured-preview-frame')) {
                        preview.innerHTML = '';
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getCardsGridForItemType(itemType) {
            if (itemType === 'flash_deal') {
                return document.querySelector('#flashDealsSection .cards-grid');
            }
            if (itemType === 'foreign_destination') {
                return document.querySelector('#foreignDestinationsSection .cards-grid');
            }
            if (itemType === 'destination') {
                return document.querySelector('#localDestinationsSection .cards-grid');
            }
            return null;
        }

        function buildPartnerCard(itemType, itemData) {
            const previewImage = itemData.image_path
                ? PersistenceEngine.normalizeAssetPath(itemData.image_path)
                : 'https://via.placeholder.com/300x150?text=No+Image';
            const card = document.createElement('div');
            card.className = 'content-card';
            card.dataset.itemType = itemType;
            card.dataset.itemId = itemData.id;

            let isExpired = false;
            if (itemType === 'flash_deal' && itemData.promo_end) {
                const endDate = new Date(itemData.promo_end + 'T23:59:59');
                isExpired = !isNaN(endDate.getTime()) && endDate < new Date();
            }
            if (isExpired) {
                card.style.opacity = '0.7';
                card.style.filter = 'grayscale(80%)';
                card.style.borderColor = '#ccc';
            }

            const titleText = itemData.title || itemData.name || 'Untitled';
            const locationText = itemType === 'flash_deal'
                ? itemData.location || 'Various'
                : [itemData.city, itemData.country].filter(Boolean).join(', ') || 'Location unknown';
            const badgeText = itemType === 'flash_deal'
                ? (itemData.discount_percent ? `⚡ ${itemData.discount_percent}% off` : (itemData.badge_text || 'Flash Deal'))
                : (itemData.badge_text || '');
            const priceText = `${itemData.currency || '₱'} ${Number(itemData.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const durationText = itemData.duration ? escapeHtml(itemData.duration) : '';
            const statusLabel = itemData.is_active == 1 ? 'Active' : 'Inactive';
            const statusClass = itemData.is_active == 1 ? 'status-active' : 'status-inactive';

            const preview = document.createElement('div');
            preview.className = 'card-preview';
            preview.style.backgroundImage = `url('${previewImage}')`;
            if (badgeText) {
                const badge = document.createElement('span');
                badge.className = 'badge';
                if (isExpired) badge.style.background = '#6c757d';
                badge.textContent = badgeText;
                preview.appendChild(badge);
            }

            const content = document.createElement('div');
            content.className = 'card-content';

            const title = document.createElement('h3');
            title.className = 'card-title';
            title.textContent = titleText;
            content.appendChild(title);

            const meta1 = document.createElement('div');
            meta1.className = 'card-meta';
            const locationSpan = document.createElement('span');
            locationSpan.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${escapeHtml(locationText)}`;
            meta1.appendChild(locationSpan);
            content.appendChild(meta1);

            if (itemType !== 'flash_deal') {
                const meta2 = document.createElement('div');
                meta2.className = 'card-meta';
                const activitiesSpan = document.createElement('span');
                activitiesSpan.innerHTML = `<i class="fas fa-tasks"></i> ${escapeHtml(String(itemData.activities_count || 0))} activities`;
                const statusSpan = document.createElement('span');
                statusSpan.className = `status-badge ${statusClass}`;
                statusSpan.textContent = statusLabel;
                meta2.appendChild(activitiesSpan);
                meta2.appendChild(statusSpan);
                content.appendChild(meta2);
            }

            const meta3 = document.createElement('div');
            meta3.className = 'card-meta';
            const priceSpan = document.createElement('span');
            priceSpan.innerHTML = `<i class="fas fa-tag"></i> ${escapeHtml(priceText)}`;
            meta3.appendChild(priceSpan);
            if (durationText) {
                const durationSpan = document.createElement('span');
                durationSpan.innerHTML = `<i class="fas fa-clock"></i> ${durationText}`;
                meta3.appendChild(durationSpan);
            }
            content.appendChild(meta3);

            const meta4 = document.createElement('div');
            meta4.className = 'card-meta';
            const statusSpan = document.createElement('span');
            statusSpan.className = `status-badge ${statusClass}`;
            statusSpan.textContent = statusLabel;
            meta4.appendChild(statusSpan);
            content.appendChild(meta4);

            const actions = document.createElement('div');
            actions.className = 'card-actions';
            const editButton = document.createElement('button');
            editButton.className = 'edit-card-btn';
            editButton.type = 'button';
            editButton.innerHTML = '<i class="fas fa-edit"></i> Edit';
            editButton.addEventListener('click', () => {
                if (itemType === 'flash_deal') editFlashDeal(itemData);
                else if (itemType === 'foreign_destination') editForeignDestination(itemData);
                else if (itemType === 'destination') editLocalDestination(itemData);
            });
            const deleteButton = document.createElement('button');
            deleteButton.className = 'delete-card-btn';
            deleteButton.type = 'button';
            deleteButton.innerHTML = '<i class="fas fa-trash"></i> Delete';
            deleteButton.addEventListener('click', () => {
                const deleteType = itemType === 'flash_deal' ? 'flash_deal' : (itemType === 'foreign_destination' ? 'foreign_destination' : 'destination');
                deleteItem(deleteType, itemData.id, itemData.name || itemData.title || 'Item');
            });
            actions.appendChild(editButton);
            actions.appendChild(deleteButton);
            content.appendChild(actions);

            card.appendChild(preview);
            card.appendChild(content);
            return card;
        }

        function refreshPartnerItemCard(itemType, itemData) {
            if (!itemData || !itemData.id) return;
            const grid = getCardsGridForItemType(itemType);
            if (!grid) return;
            const existing = grid.querySelector(`.content-card[data-item-type="${itemType}"][data-item-id="${itemData.id}"]`);
            const card = buildPartnerCard(itemType, itemData);
            if (existing) {
                existing.replaceWith(card);
            } else {
                const emptyMessage = grid.querySelector('.message.info');
                if (emptyMessage && grid.children.length === 1) {
                    grid.innerHTML = '';
                }
                grid.prepend(card);
            }
        }

        // ========== TAB SWITCHING FUNCTIONS ==========
        function switchForeignTab(evt, tabId) {
            const contents = document.querySelectorAll('#foreignDestinationModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#foreignDestinationModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }
        function switchLocalTab(evt, tabId) {
            const contents = document.querySelectorAll('#localDestinationModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#localDestinationModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }
        function switchFlashDealTab(evt, tabId) {
            const contents = document.querySelectorAll('#flashDealModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#flashDealModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }


        // ========== ADVANCED CRUISE FUNCTIONS ==========
        function switchTab(evt, tabId) {
            const contents = document.querySelectorAll('#advancedCruiseModal .tab-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('#advancedCruiseModal .tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        function getSavedAdvancedCruiseTabs(d) {
            const tabs = { general: false, ship: false, itinerary: false, pricing: false, schedule: false, policies: false, gallery: false };
            if (d.title || d.cruise_code || d.short_description || d.category) tabs.general = true;
            if (d.ship_name || d.cruise_line || d.departure_port || d.destination_type || d.destinations || d.ship_description || d.room_types || d.amenities) tabs.ship = true;
            if (d.itinerary && d.itinerary.length > 0) tabs.itinerary = true;
            if (parseFloat(d.base_price) > 0 || parseFloat(d.promo_price) > 0) tabs.pricing = true;
            if (d.departure_date || d.return_date || d.duration) tabs.schedule = true;
            if (d.travel_requirements || d.cancellation_policy || d.terms_conditions) tabs.policies = true;
            const gallery = [];
            if (d.featured_image) gallery.push(d.featured_image);
            if (d.gallery) {
                try {
                    const parsed = typeof d.gallery === 'string' ? JSON.parse(d.gallery) : d.gallery;
                    if (Array.isArray(parsed)) gallery.push(...parsed.filter(Boolean));
                } catch (e) {}
            }
            if (gallery.length > 0) tabs.gallery = true;
            return tabs;
        }

        function setAdvancedCruiseTabSavedState(tabs) {
            document.querySelectorAll('#advancedCruiseModal .tab-btn').forEach(btn => {
                const target = btn.getAttribute('onclick')?.match(/'([^']+)'/);
                const t = target ? target[1] : null;
                if (!t) return;
                btn.classList.toggle('tab-saved',
                    (t === 'tab-general' && tabs.general) ||
                    (t === 'tab-ship' && tabs.ship) ||
                    (t === 'tab-itinerary' && tabs.itinerary) ||
                    (t === 'tab-pricing' && tabs.pricing) ||
                    (t === 'tab-schedule' && tabs.schedule) ||
                    (t === 'tab-policies' && tabs.policies) ||
                    (t === 'tab-gallery' && tabs.gallery)
                );
            });
        }

        function addItineraryDay(title = '', desc = '') {
            const list = document.getElementById('itinerary-list');
            const dayNum = list.children.length + 1;
            const item = document.createElement('div');
            item.className = 'itinerary-item';
            item.innerHTML = `
                <div class="remove-day" onclick="this.parentElement.remove(); updateDayNumbers();"><i class="fas fa-trash"></i></div>
                <div style="font-weight: 700; color: #003580; margin-bottom: 15px;">Day <span class="day-num">${dayNum}</span></div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="itinerary[${dayNum}][title]" value="${title}" placeholder="e.g. Arrival & Check-in">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Description</label>
                    <textarea name="itinerary[${dayNum}][description]" rows="2">${desc}</textarea>
                </div>
                <input type="hidden" name="itinerary[${dayNum}][day_number]" value="${dayNum}" class="day-hidden-num">
            `;
            list.appendChild(item);
            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                PersistenceEngine.saveDraft('advancedCruiseForm');
            }
        }

        function updateDayNumbers() {
            const list = document.getElementById('itinerary-list');
            Array.from(list.children).forEach((item, index) => {
                const dayNum = index + 1;
                item.querySelector('.day-num').innerText = dayNum;
                item.querySelector('.day-hidden-num').value = dayNum;
                item.querySelector('input[name*="[title]"]').name = `itinerary[${dayNum}][title]`;
                item.querySelector('textarea[name*="[description]"]').name = `itinerary[${dayNum}][description]`;
            });
            if (typeof PersistenceEngine !== 'undefined' && PersistenceEngine.saveDraft) {
                PersistenceEngine.saveDraft('advancedCruiseForm');
            }
        }

        function openAdvancedCruiseModal() {
            document.querySelectorAll('#advancedCruiseModal .tab-btn').forEach(btn => btn.classList.remove('tab-saved'));
            document.getElementById('advancedCruiseModalTitle').innerText = 'Add Cruise Inventory';
            document.getElementById('advancedCruiseForm').reset();
            document.getElementById('advanced_cruise_id').value = '0';
            document.getElementById('old_featured_image').value = '';
            document.getElementById('featured_preview_box').style.backgroundImage = '';
            document.getElementById('itinerary-list').innerHTML = '';

            _cruiseGalleryFiles = [];
            _cruiseSavedGallery = [];
            document.getElementById('cruise_remove_gallery_images').value = '';
            _renderCruiseGalleryPreview(document.getElementById('cruise_gallery_preview'), document.getElementById('cruise_gallery_count'));

            // Default first day
            addItineraryDay('Departure', 'Embarkation and welcome on board.');

            // Reset tabs
            const firstTab = document.querySelector('#advancedCruiseModal .tab-btn');
            if (firstTab) firstTab.click();

            document.getElementById('advancedCruiseModal').classList.add('active');
            PersistenceEngine.checkDraft('advancedCruiseForm');
            PersistenceEngine.applySavedTabState('advancedCruiseForm', '#advancedCruiseModal');
        }

        function editAdvancedCruise(id) {


            const formData = new FormData();
            formData.append('action', 'get_advanced_cruise');
            formData.append('id', id);

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {

                    if (res.success) {
                        const d = res.data;
                        document.getElementById('advancedCruiseModalTitle').innerText = 'Edit Cruise';
                        document.getElementById('advanced_cruise_id').value = d.id;
                        document.getElementById('cruise_title').value = d.title;
                        document.getElementById('cruise_code').value = d.cruise_code;
                        document.getElementById('cruise_short_description').value = d.short_description || '';
                        document.getElementById('cruise_full_description').value = d.full_description || '';
                        document.getElementById('cruise_category').value = d.category || '';
                        document.getElementById('cruise_tags').value = d.tags || '';
                        document.getElementById('old_featured_image').value = d.featured_image || '';
                        document.getElementById('featured_preview_box').style.backgroundImage = d.featured_image ? `url('${PersistenceEngine.normalizeAssetPath(d.featured_image)}')` : '';
                        document.getElementById('cruise_is_published').checked = d.is_published == 1;
                        document.getElementById('cruise_is_featured').checked = d.is_featured == 1;

                        document.getElementById('cruise_ship_name').value = d.ship_name || '';
                        document.getElementById('cruise_cruise_line').value = d.cruise_line || '';
                        document.getElementById('cruise_departure_port').value = d.departure_port || '';
                        document.getElementById('cruise_destination_type').value = d.destination_type || 'International';
                        document.getElementById('cruise_destinations').value = d.destinations || '';
                        document.getElementById('cruise_ship_description').value = d.ship_description || '';
                        document.getElementById('cruise_room_types').value = d.room_types || '';
                        document.getElementById('cruise_amenities').value = d.amenities || '';

                        document.getElementById('cruise_base_price').value = d.base_price;
                        document.getElementById('cruise_promo_price').value = d.promo_price || '';
                        document.getElementById('cruise_available_slots').value = d.available_slots || 0;
                        document.getElementById('cruise_status').value = d.status || 'Available';
                        document.getElementById('cruise_inclusions').value = d.inclusions || '';
                        document.getElementById('cruise_exclusions').value = d.exclusions || '';

                        document.getElementById('cruise_duration_val').value = d.duration || '';
                        document.getElementById('cruise_departure_date').value = d.departure_date || '';
                        document.getElementById('cruise_return_date').value = d.return_date || '';
                        document.getElementById('cruise_booking_deadline').value = d.booking_deadline || '';

                        document.getElementById('cruise_travel_requirements').value = d.travel_requirements || '';
                        document.getElementById('cruise_cancellation_policy').value = d.cancellation_policy || '';
                        document.getElementById('cruise_terms_conditions').value = d.terms_conditions || '';

                        // Load image gallery
                        _cruiseGalleryFiles = [];
                        document.getElementById('cruise_remove_gallery_images').value = '';
                        try {
                            _cruiseSavedGallery = d.gallery ? JSON.parse(d.gallery) : [];
                        } catch(e) {
                            _cruiseSavedGallery = [];
                        }
                        _renderCruiseGalleryPreview(document.getElementById('cruise_gallery_preview'), document.getElementById('cruise_gallery_count'));

                        // Itinerary
                        const list = document.getElementById('itinerary-list');
                        list.innerHTML = '';
                        if (d.itinerary && d.itinerary.length > 0) {
                            d.itinerary.forEach(it => addItineraryDay(it.title, it.description));
                        }

                        setAdvancedCruiseTabSavedState(getSavedAdvancedCruiseTabs(d));
                        document.getElementById('advancedCruiseModal').classList.add('active');
                        PersistenceEngine.checkDraft('advancedCruiseForm');
                        PersistenceEngine.applySavedTabState('advancedCruiseForm', '#advancedCruiseModal');
                    }
                });
        }

        function deleteAdvancedCruise(id, name) {
            Swal.fire({
                title: 'Delete Cruise?',
                text: `Are you sure you want to delete "${name}"?`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_advanced_cruise');
                    formData.append('id', id);
                    fetch('partner-content-manager.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                Swal.fire('Deleted!', 'Cruise has been removed.', 'success').then(() => location.reload());
                            }
                        });
                }
            });
        }

        document.getElementById('advancedCruiseForm').addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateRequiredFields(this)) return;

            const form = this;
            const formData = new FormData(form);

            // Remove the empty gallery[] file input
            formData.delete('gallery[]');

            // Append all accumulated new files
            _cruiseGalleryFiles.forEach(file => {
                formData.append('gallery[]', file);
            });

            const activeCruiseTabBtn = document.querySelector('#advancedCruiseModal .tab-btn.active');
            const activeCruiseTab = activeCruiseTabBtn ? activeCruiseTabBtn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] : null;
            if (activeCruiseTab) {
                formData.set('active_tab', activeCruiseTab);
                PersistenceEngine.setSavedTabState('advancedCruiseForm', activeCruiseTab);
            }

            Swal.fire({ title: 'Saving...', didOpen: () => { Swal.showLoading(); } });

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.text().then(text => ({ status: r.status, text })))
                .then(({ status, text }) => {
                    Swal.close();
                    let res;
                    try {
                        res = JSON.parse(text);
                    } catch (parseErr) {
                        Swal.fire('Error', 'The server returned an unexpected response (HTTP ' + status + '). ' +
                            (text ? text.slice(0, 300) : 'Empty response -- check the server error log for a PHP error.'), 'error');
                        return;
                    }
                    if (res.success) {
                        if (res.data && res.data.id) {
                            document.getElementById('advanced_cruise_id').value = res.data.id;
                        }
                        if (activeCruiseTab) {
                            PersistenceEngine.setSavedTabState('advancedCruiseForm', activeCruiseTab);
                        }
                        PersistenceEngine.clearDraft('advancedCruiseForm');
                        PersistenceEngine.applySavedTabState('advancedCruiseForm', '#advancedCruiseModal');
                        refreshCruiseTableRow(res.data);
                        Swal.fire('Success!', res.message, 'success');
                    } else {
                        Swal.fire('Error', res.message || 'Something went wrong', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
        });

        // ========== SERVICE FORM AJAX SUBMIT ==========
        document.getElementById('serviceForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateRequiredFields(this)) return;

            const form = this;
            const formData = new FormData(form);

            // Remove the (empty) gallery[] file input that came from the form
            // so we don't send a blank entry that confuses PHP's $_FILES index check
            formData.delete('gallery[]');

            // Attach accumulated new gallery files
            _serviceGalleryFiles.forEach(function(file) {
                formData.append('gallery[]', file);
            });

            const activeServiceTabBtn = document.querySelector('#serviceModal .tab-btn.active');
            const activeServiceTab = activeServiceTabBtn ? activeServiceTabBtn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] : null;
            if (activeServiceTab) {
                formData.set('active_tab', activeServiceTab);
            }

            Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('partner-content-manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(json => {
                    Swal.close();
                    if (json.success) {
                        if (json.data && json.data.id) {
                            document.getElementById('service_id').value = json.data.id;
                        }
                        if (activeServiceTab) {
                            PersistenceEngine.setSavedTabState('serviceForm', activeServiceTab);
                        }
                        PersistenceEngine.clearDraft('serviceForm');
                        if (activeServiceTabBtn) activeServiceTabBtn.classList.add('tab-saved');
                        refreshServiceTableRow(json.data);
                        Swal.fire('Saved!', json.message || 'Service saved successfully.', 'success');
                    } else {
                        Swal.fire('Error', json.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                });
        });

        // Clear accumulated gallery files when service modal is closed
        document.getElementById('serviceModal')?.addEventListener('click', function(e) {
            // Only reset when the overlay (modal backdrop) is clicked – handled by the
            // generic modal click-to-close listener below. We just piggy-back here.
        });
        // Reset gallery accumulator when modal opens (handled in openServiceModal / editService)

        // Close modals on overlay click
        // Close modals on overlay click (with mousedown/mouseup checks to prevent swipe closes)
        let mousedownTarget = null;
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('mousedown', function (e) {
                mousedownTarget = e.target;
            });
            modal.addEventListener('mouseup', function (e) {
                if (e.target === this && mousedownTarget === this) {
                    const form = this.querySelector('form');
                    if (form) {
                        PersistenceEngine.saveDraft(form.id);
                        Swal.fire({
                            icon: 'info',
                            title: 'Draft Saved',
                            text: 'Your progress has been automatically saved as a draft.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            backdrop: false
                        });
                    }
                    this.classList.remove('active');
                }
            });
        });
        // Mobile Sidebar Toggle
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
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Copied!',
                        text: 'Content copied to clipboard',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                });
            }

            // Helper to set Blocked Months checkboxes
            function setBlockedMonths(monthsStr, className) {
                const checkboxes = document.querySelectorAll('.' + className);
                checkboxes.forEach(cb => cb.checked = false);
                if (!monthsStr) return;
                const months = monthsStr.split(',');
                checkboxes.forEach(cb => {
                    if (months.includes(cb.value)) cb.checked = true;
                });
            }
            // Helper to auto-extract highlight duration from duration text (e.g. "3D/2N" -> 3)
            function setupDurationExtractor(textInputId, numberInputId) {
                const textInput = document.getElementById(textInputId);
                const numberInput = document.getElementById(numberInputId);
                if (textInput && numberInput) {
                    textInput.addEventListener('input', function () {
                        const parsed = parseInt(this.value);
                        if (!isNaN(parsed) && parsed > 0) {
                            numberInput.value = parsed;
                        }
                    });
                }
            }
            setupDurationExtractor('deal_duration', 'deal_highlight_duration');
            setupDurationExtractor('foreign_dest_duration', 'foreign_highlight_duration');
            setupDurationExtractor('local_dest_duration', 'local_highlight_duration');

            sidebarOverlay.addEventListener('click', () => {
                adminSidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }
        PersistenceEngine.initAutosave();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize Flatpickr for blocked dates
        flatpickr(".blocked-dates-picker", {
            mode: "multiple",
            dateFormat: "Y-m-d",
            minDate: "today"
        });
    </script>
</body>

</html>
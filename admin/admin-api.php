<?php
// Start output buffering immediately to prevent any stray output from corrupting JSON responses
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Create a debug log file in the same directory
$debugLogFile = __DIR__ . '/admin_api_debug.log';

function debugLog($message, $data = null)
{
    global $debugLogFile;
    $logEntry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logEntry .= " - Data: " . print_r($data, true);
    }
    file_put_contents($debugLogFile, $logEntry . "\n", FILE_APPEND);
    error_log($logEntry);
}

debugLog("=== Admin API Request Started ===");
debugLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
debugLog("POST Data: " . print_r($_POST, true));
debugLog("GET Data: " . print_r($_GET, true));

session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check if admin is logged in
if (!in_array($action, ['reset_password', 'request_reset']) && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    debugLog("Unauthorized access attempt - session: " . print_r($_SESSION, true));
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

debugLog("Admin logged in: " . ($_SESSION['admin_username'] ?? 'Unknown'));

// Check if database.php exists
$databasePath = __DIR__ . '/../config/database.php';
if (!file_exists($databasePath)) {
    debugLog("Database config file not found at: " . $databasePath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database configuration file not found']);
    exit;
}

require_once $databasePath;

// Verify database connection
if (!isset($pdo) || !$pdo) {
    debugLog("Database connection failed - PDO not set or invalid");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

debugLog("Database connection successful");

// Migration for visa_status
try {
    $stmtMigrate = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'visa_status'");
    if (!$stmtMigrate->fetch()) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN visa_status VARCHAR(50) DEFAULT 'PENDING'");
        debugLog("Migration: Added visa_status column to bookings table");
    }
} catch (Exception $e) {
    debugLog("Migration Error: " . $e->getMessage());
}

// Migration for payment_proof
try {
    $stmtMigrate2 = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'payment_proof'");
    if (!$stmtMigrate2->fetch()) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN payment_proof VARCHAR(500) DEFAULT NULL AFTER payment_reference");
        debugLog("Migration: Added payment_proof column to bookings table");
    }
} catch (Exception $e) {
    debugLog("Migration Error (payment_proof): " . $e->getMessage());
}

// Ensure package table exists for package add/edit/delete flows.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        destination_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        duration VARCHAR(50) DEFAULT '',
        price DECIMAL(10,2) DEFAULT 0,
        activities_count INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_packages_destination (destination_id),
        INDEX idx_packages_active (is_active)
    )");
    debugLog("Migration: Ensured packages table exists");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(255) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(50) NOT NULL,
        website VARCHAR(255) DEFAULT NULL,
        business_type VARCHAR(100) NOT NULL,
        message TEXT,
        password VARCHAR(255) NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        rejection_reason TEXT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    )");
    debugLog("Migration: Ensured partner_applications table exists");
} catch (Exception $e) {
    debugLog("Migration Error (packages table): " . $e->getMessage());
}

$admin_role = $_SESSION['admin_role'] ?? 'admin';

debugLog("Action: " . $action);
debugLog("Admin Role: " . $admin_role);

header('Content-Type: application/json');

// Helper function to check if user has permission
function hasPermission($allowed_roles, $current_role)
{
    return in_array($current_role, $allowed_roles);
}

try {
    switch ($action) {

        case 'request_reset':
            debugLog("Processing request_reset");
            require_once __DIR__ . '/../config/email_config.php';

            $email = trim($_POST['email'] ?? '');

            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Email is required']);
                break;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1 AND approved = 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if (!$admin) {
                echo json_encode(['success' => false, 'message' => 'No active admin found with that email address']);
                break;
            }

            $token = bin2hex(random_bytes(50));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("UPDATE admin_users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
            if ($stmt->execute([$token, $expiresAt, $email])) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'];
                $resetLink = $protocol . $host . dirname($_SERVER['REQUEST_URI']) . '/reset-password.php?token=' . $token;

                $result = sendPasswordResetEmail($email, $admin['full_name'], $resetLink);
                if (isset($result['success']) && $result['success']) {
                    echo json_encode(['success' => true, 'message' => "Password reset link sent to $email"]);
                } else {
                    echo json_encode(['success' => false, 'message' => "Error sending email: " . ($result['message'] ?? 'Unknown error')]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to generate reset token']);
            }
            break;

        case 'reset_password':
            debugLog("Processing reset_password with token");

            $token = trim($_POST['token'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($token) || empty($new_password) || empty($confirm_password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                break;
            }
            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
                break;
            }
            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE reset_token = ? AND is_active = 1");
            $stmt->execute([$token]);
            $admin = $stmt->fetch();

            if (!$admin) {
                echo json_encode(['success' => false, 'message' => 'Invalid reset token']);
                break;
            }

            if (strtotime($admin['reset_token_expires']) < time()) {
                echo json_encode(['success' => false, 'message' => 'Reset token has expired']);
                break;
            }

            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, reset_token = NULL, reset_token_expires = NULL, last_password_reset = NOW() WHERE id = ?");
            $success = $stmt->execute([$hashedPassword, $admin['id']]);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully! You can now login.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update database']);
            }
            break;


        case 'update_email_settings':
            debugLog("Processing update_email_settings");
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $host = trim($_POST['host'] ?? '');
            $port = intval($_POST['port'] ?? 587);
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $from_email = trim($_POST['from_email'] ?? '');
            $from_name = trim($_POST['from_name'] ?? '');

            if (empty($host) || empty($username) || empty($password) || empty($from_email) || empty($from_name)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                break;
            }

            $configContent = "<?php\n// File: config/email_config.php\n\n// Email configuration\n\$emailConfig = [\n";
            $configContent .= "    'host' => '" . addslashes($host) . "',\n";
            $configContent .= "    'username' => '" . addslashes($username) . "',\n";
            $configContent .= "    'password' => '" . addslashes($password) . "',\n";
            $configContent .= "    'port' => " . $port . ",\n";
            $configContent .= "    'from_email' => '" . addslashes($from_email) . "',\n";
            $configContent .= "    'from_name' => '" . addslashes($from_name) . "'\n];\n\n";
            $configContent .= "// Include PHPMailer files\n";
            $configContent .= "require_once __DIR__ . '/../PHPMailer/Exception.php';\n";
            $configContent .= "require_once __DIR__ . '/../PHPMailer/PHPMailer.php';\n";
            $configContent .= "require_once __DIR__ . '/../PHPMailer/SMTP.php';\n\n";
            $configContent .= "use PHPMailer\\PHPMailer\\PHPMailer;\nuse PHPMailer\\PHPMailer\\SMTP;\nuse PHPMailer\\PHPMailer\\Exception;\n\n";
            $configContent .= <<<'EOF'
function sendPasswordResetEmail($toEmail, $toName, $resetLink) {
    global $emailConfig;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $emailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['username'];
        $mail->Password   = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $emailConfig['port'];
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($emailConfig['username'], $emailConfig['from_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your HeyDream Admin Password';
        
        $currentYear = date('Y');
        $safeName = htmlspecialchars($toName);
        
        $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <meta name='color-scheme' content='light dark'>
                <meta name='supported-color-schemes' content='light dark'>
                <title>Password Reset - HeyDream</title>
                <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap' rel='stylesheet'>
                <style>
                    /* Base styles */
                    body {
                        font-family: 'Inter', Arial, sans-serif;
                        background-color: #f4f7f6;
                        margin: 0;
                        padding: 0;
                        color: #333333;
                    }
                    .wrapper {
                        width: 100%;
                        table-layout: fixed;
                        background-color: #f4f7f6;
                        padding: 40px 0;
                    }
                    .main-container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 12px;
                        overflow: hidden;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
                    }
                    .header {
                        background-color: #0077B6;
                        padding: 40px 30px;
                        text-align: center;
                    }
                    .header h1 {
                        margin: 0;
                        color: #ffffff;
                        font-size: 28px;
                        font-weight: 800;
                        letter-spacing: -0.5px;
                    }
                    .header p {
                        margin: 5px 0 0;
                        color: #FFF3B0;
                        font-size: 16px;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    }
                    .content {
                        padding: 40px 30px;
                        background-color: #ffffff;
                    }
                    .content h2 {
                        margin: 0 0 20px;
                        color: #1a1a1a;
                        font-size: 20px;
                    }
                    .content p {
                        margin: 0 0 20px;
                        color: #4a4a4a;
                        line-height: 1.6;
                        font-size: 16px;
                    }
                    .button-wrapper {
                        text-align: center;
                        margin: 35px 0;
                    }
                    .button {
                        display: inline-block;
                        background-color: #0077B6;
                        color: #ffffff !important;
                        padding: 14px 32px;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 16px;
                        transition: background-color 0.3s;
                        border: 2px solid #0077B6;
                    }
                    .button:hover {
                        background-color: #005f92;
                        border-color: #005f92;
                    }
                    .divider {
                        border-top: 1px solid #eeeeee;
                        margin: 30px 0;
                    }
                    .sub-text {
                        font-size: 13px;
                        color: #888888;
                        margin-bottom: 0;
                    }
                    .footer {
                        background-color: #FFF3B0;
                        padding: 30px;
                        text-align: center;
                        border-top: 1px solid #f0e4a0;
                    }
                    .footer p {
                        margin: 5px 0;
                        color: #0077B6;
                        font-size: 14px;
                        font-weight: 600;
                    }
                    .footer .contact {
                        color: #555555;
                        font-size: 13px;
                        font-weight: 500;
                        margin-top: 15px;
                    }
                    .footer a {
                        color: #0077B6;
                        text-decoration: none;
                        font-weight: 600;
                    }
            
                    /* Dark mode overrides */
                    @media (prefers-color-scheme: dark) {
                        body, .wrapper {
                            background-color: #121212 !important;
                        }
                        .main-container {
                            background-color: #1e1e1e !important;
                            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
                        }
                        .content {
                            background-color: #1e1e1e !important;
                        }
                        .content h2 {
                            color: #ffffff !important;
                        }
                        .content p {
                            color: #cccccc !important;
                        }
                        .divider {
                            border-top-color: #333333 !important;
                        }
                        .sub-text {
                            color: #999999 !important;
                        }
                        .footer {
                            background-color: #2a2818 !important;
                            border-top-color: #3d3a24 !important;
                        }
                        .footer p, .footer a {
                            color: #66b3ff !important;
                        }
                        .header p {
                            color: #FFF3B0 !important;
                        }
                        .footer .contact {
                             color: #dddddd !important;
                        }
                    }
                </style>
            </head>
            <body>
                <div class='wrapper'>
                    <div class='main-container'>
                        <div class='header'>
                            <h1>HeyDream Travel</h1>
                            <p>Admin Portal</p>
                        </div>
                        <div class='content'>
                            <h2>Hello {$safeName},</h2>
                            <p>We received a request to reset the password for your administrator account.</p>
                            <div class='button-wrapper'>
                                <a href='{$resetLink}' class='button'>Reset Password</a>
                            </div>
                            <p>This secure link will expire in 1 hour.</p>
                            <p>If you did not request a password reset, please ignore this email.</p>
                            <div class='divider'></div>
                            <p class='sub-text'>For security purposes, this password reset link can only be used once.</p>
                        </div>
                        <div class='footer'>
                            <p>© {$currentYear} HeyDream Travel & Tours.</p>
                            <p>All rights reserved.</p>
                            <div class='contact'>
                                📞 0945 776 4140 &nbsp;|&nbsp; ✉️ <a href='mailto:heydreamtravelandtours@gmail.com'>heydreamtravelandtours@gmail.com</a>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Hello {$safeName},\n\nWe received a request to reset your admin account password.\n\nClick this link to reset your password: {$resetLink}\n\nThis link will expire in 1 hour.\n\nIf you didn't request this, please ignore this email.\n\n© {$currentYear} HeyDream Travel & Tours";
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>
EOF;

            $configFile = __DIR__ . '/../config/email_config.php';
            if (file_put_contents($configFile, $configContent)) {
                echo json_encode(['success' => true, 'message' => 'Email settings updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to write config file. Check permissions.']);
            }
            break;

        case 'export_users':
            debugLog("Processing export_users");

            // Only Super Admin can export users
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for export_users - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, full_name, email, phone, provider, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            $users = $stmt->fetchAll();

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'Provider', 'Registered Date']);

            foreach ($users as $user) {
                fputcsv($output, [
                    $user['id'],
                    $user['full_name'],
                    $user['email'],
                    $user['phone'] ?? 'N/A',
                    $user['provider'],
                    $user['created_at']
                ]);
            }
            fclose($output);
            exit;
            break;

        case 'get_user_history':
            $email = $_GET['email'] ?? '';
            debugLog("Processing get_user_history for Email: " . $email);
            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Missing email']);
                break;
            }

            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE email = ? AND booking_status IN ('completed', 'confirmed') ORDER BY created_at DESC");
            $stmt->execute([$email]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $history]);
            break;

        case 'get_booking':
            debugLog("Processing get_booking for ID: " . ($_GET['id'] ?? 'null'));

            // Super Admin, Admin, and Sales can view bookings
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                debugLog("Permission denied for get_booking - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            if (!isset($_GET['id']) || $_GET['id'] === '') {
                debugLog("Invalid booking ID");
                echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
                break;
            }
            $id = intval($_GET['id']);
            $booking_number_param = trim($_GET['booking_number'] ?? '');

            // For id=0 records, look up by booking_number instead
            if ($id === 0 && $booking_number_param !== '') {
                $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_number = ?");
                $stmt->execute([$booking_number_param]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
                $stmt->execute([$id]);
            }
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($booking) {
                debugLog("Booking found for ID: " . $id);
                echo json_encode(['success' => true, 'data' => $booking]);
            } else {
                debugLog("Booking not found for ID: " . $id);
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
            }
            break;

        case 'toggle_marketing_consent':
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $id = intval($_POST['id'] ?? 0);
            $consent = intval($_POST['marketing_consent'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE bookings SET marketing_consent = ? WHERE id = ?");
            if ($stmt->execute([$consent, $id])) {
                echo json_encode(['success' => true, 'message' => 'Marketing consent updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update consent']);
            }
            break;

        case 'get_booking_documents':
            debugLog("Processing get_booking_documents for Booking Number: " . ($_GET['booking_number'] ?? 'null'));
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
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

        case 'get_package_info':
            $packageName = $_GET['package_name'] ?? '';
            debugLog("Processing get_package_info for Package: " . $packageName);
            if (empty($packageName)) {
                echo json_encode(['success' => false, 'message' => 'Missing package name']);
                break;
            }

            // Try searching in destinations (Local/Foreign)
            $stmt = $pdo->prepare("SELECT inclusions, exclusions, itinerary, description, location_name as location FROM destinations WHERE name = ? LIMIT 1");
            $stmt->execute([$packageName]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$info) {
                // Try searching in flash_deals
                $stmt = $pdo->prepare("SELECT inclusions, exclusions, itinerary, description, location FROM flash_deals WHERE title = ? LIMIT 1");
                $stmt->execute([$packageName]);
                $info = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$info) {
                // Try searching in foreign_destinations if table exists
                try {
                    $stmt = $pdo->prepare("SELECT inclusions, exclusions, itinerary, description, location_name as location FROM foreign_destinations WHERE name = ? LIMIT 1");
                    $stmt->execute([$packageName]);
                    $info = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                }
            }

            if ($info) {
                echo json_encode(['success' => true, 'data' => $info]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No package details found']);
            }
            break;

        case 'update_booking':
            debugLog("Processing update_booking");
            debugLog("POST Data: " . print_r($_POST, true));

            // Super Admin, Admin, and Sales can update bookings
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                debugLog("Permission denied for update_booking - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only Admins can update bookings.']);
                break;
            }

            if (!isset($_POST['id']) || $_POST['id'] === '') {
                debugLog("Invalid booking ID for update");
                echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
                break;
            }
            $id = intval($_POST['id']);
            $booking_number_key = trim($_POST['booking_number'] ?? '');

            $booking_status = trim($_POST['booking_status'] ?? 'pending');
            $payment_status = trim($_POST['payment_status'] ?? 'unpaid');
            $admin_notes = trim($_POST['admin_notes'] ?? '');
            $flight_details = trim($_POST['flight_details'] ?? '');
            $visa_status = trim($_POST['visa_status'] ?? 'PENDING');
            $travel_documents = isset($_POST['travel_documents']) ? intval($_POST['travel_documents']) : 0;
            $ready_for_travel = isset($_POST['ready_for_travel']) ? intval($_POST['ready_for_travel']) : 0;
            $number_of_travelers = isset($_POST['number_of_travelers']) ? intval($_POST['number_of_travelers']) : 1;
            $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
            $price_per_person = isset($_POST['price_per_person']) ? floatval($_POST['price_per_person']) : 0;

            // Validate status values
            $valid_booking_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
            $valid_payment_statuses = ['unpaid', 'paid', 'refunded'];

            if (!in_array($booking_status, $valid_booking_statuses)) {
                debugLog("Invalid booking status: " . $booking_status);
                echo json_encode(['success' => false, 'message' => 'Invalid booking status']);
                break;
            }

            if (!in_array($payment_status, $valid_payment_statuses)) {
                debugLog("Invalid payment status: " . $payment_status);
                echo json_encode(['success' => false, 'message' => 'Invalid payment status']);
                break;
            }

            // Get old status and booking details before update
            // For id=0 records, look up by booking_number
            if ($id === 0 && $booking_number_key !== '') {
                $stmt = $pdo->prepare("SELECT booking_status, payment_status, email, full_name, booking_number, destination_name, travel_date, number_of_travelers, total_amount, travel_documents, ready_for_travel, package_name FROM bookings WHERE booking_number = ?");
                $stmt->execute([$booking_number_key]);
            } else {
                $stmt = $pdo->prepare("SELECT booking_status, payment_status, email, full_name, booking_number, destination_name, travel_date, number_of_travelers, total_amount, travel_documents, ready_for_travel, package_name FROM bookings WHERE id = ?");
                $stmt->execute([$id]);
            }
            $old_booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_booking) {
                debugLog("Booking not found for ID: " . $id);
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
                break;
            }

            $old_status = $old_booking['booking_status'];

            // Auto-complete logic based on user request
            $isVisaRelated = ($old_booking['destination_name'] === 'Visa Assistance' ||
                stripos($old_booking['package_name'] ?? '', 'Visa') !== false ||
                (isset($old_booking['booking_number']) && strpos($old_booking['booking_number'], 'VI-') === 0));
            $vStatus = strtoupper($visa_status);
            $isVisaMatch = ($vStatus === 'APPROVED' || $vStatus === 'N/A' || !$isVisaRelated);
            if ($travel_documents === 1 && $payment_status === 'paid' && $isVisaMatch) {
                $booking_status = 'completed';
            }

            // Update the booking
            // For id=0 records, update by booking_number
            if ($id === 0 && $booking_number_key !== '') {
                $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ?, payment_status = ?, admin_notes = ?, flight_details = ?, visa_status = ?, travel_documents = ?, ready_for_travel = ?, number_of_travelers = ?, total_amount = ?, price_per_person = ? WHERE booking_number = ?");
                $success = $stmt->execute([$booking_status, $payment_status, $admin_notes, $flight_details, $visa_status, $travel_documents, $ready_for_travel, $number_of_travelers, $total_amount, $price_per_person, $booking_number_key]);
            } else {
                $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ?, payment_status = ?, admin_notes = ?, flight_details = ?, visa_status = ?, travel_documents = ?, ready_for_travel = ?, number_of_travelers = ?, total_amount = ?, price_per_person = ? WHERE id = ?");
                $success = $stmt->execute([$booking_status, $payment_status, $admin_notes, $flight_details, $visa_status, $travel_documents, $ready_for_travel, $number_of_travelers, $total_amount, $price_per_person, $id]);
            }

            if ($success) {

                debugLog("Booking update successful for ID: " . $id);

                // Include email functions if file exists
                $emailSent = false;
                $email_functions_path = __DIR__ . '/../config/email_functions.php';

                if (file_exists($email_functions_path)) {
                    debugLog("Email functions file found, attempting to send email");
                    require_once $email_functions_path;

                    // Prepare booking data for email
                    $booking_data = [
                        'id' => $id,
                        'booking_number' => $old_booking['booking_number'],
                        'full_name' => $old_booking['full_name'],
                        'email' => $old_booking['email'],
                        'destination_name' => $old_booking['destination_name'],
                        'travel_date' => $old_booking['travel_date'],
                        'number_of_travelers' => $old_booking['number_of_travelers'],
                        'total_amount' => $old_booking['total_amount'],
                        'booking_status' => $booking_status,
                        'payment_status' => $payment_status,
                        'admin_notes' => $admin_notes,
                        'flight_details' => $flight_details
                    ];

                    // 1. Send status update email if status changed (except completed, which gets the ready_for_travel email)
                    if ($old_status != $booking_status && $booking_status !== 'completed' && function_exists('sendBookingStatusEmail')) {
                        $emailSent = sendBookingStatusEmail($id, $booking_data);
                        debugLog("Status update email sent: " . ($emailSent ? 'Success' : 'Failed'));
                    }

                    // 2. Send visa payment confirmation email
                    if ($old_booking['payment_status'] != 'paid' && $payment_status == 'paid' && $old_booking['destination_name'] === 'Visa Assistance' && function_exists('sendVisaPaymentConfirmationEmail')) {
                        $emailSent = sendVisaPaymentConfirmationEmail($id, $booking_data);
                        debugLog("Visa payment confirmation email sent: " . ($emailSent ? 'Success' : 'Failed'));
                    }

                    // 3. Send travel documents prepared email (only if not fully completed yet to avoid double emailing)
                    if ($old_booking['travel_documents'] == 0 && $travel_documents == 1 && !$isNowFullyCompleted && function_exists('sendTrackingUpdateEmail')) {
                        $emailSent = sendTrackingUpdateEmail($id, 'travel_documents', $booking_data);
                        debugLog("Travel documents update email sent: " . ($emailSent ? 'Success' : 'Failed'));
                    }

                    // 4. Send ready for travel email (if now fully completed)
                    if ($old_booking['ready_for_travel'] == 0 && $ready_for_travel == 1 && function_exists('sendTrackingUpdateEmail')) {
                        $emailSent = sendTrackingUpdateEmail($id, 'ready_for_travel', $booking_data);
                        debugLog("Ready for travel update email sent: " . ($emailSent ? 'Success' : 'Failed'));
                    }
                } else {
                    debugLog("Email functions file not found at: " . $email_functions_path);
                }

                echo json_encode([
                    'success' => true,
                    'email_sent' => $emailSent,
                    'message' => 'Booking updated successfully' . ($emailSent ? ' and email notification sent.' : '.')
                ]);
            } else {
                debugLog("Booking update failed for ID: " . $id);
                echo json_encode(['success' => false, 'message' => 'Failed to update booking']);
            }
            break;

        case 'send_custom_email':
            debugLog("Processing send_custom_email");
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $id = intval($_POST['id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            if ($id <= 0 || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID or empty message']);
                break;
            }

            $email_functions_path = __DIR__ . '/../config/email_functions.php';
            if (file_exists($email_functions_path)) {
                require_once $email_functions_path;
                if (function_exists('sendCustomCustomerEmail')) {
                    $success = sendCustomCustomerEmail($id, $message);
                    if ($success) {
                        echo json_encode(['success' => true, 'message' => 'Email sent successfully directly to the customer!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please check email server configuration.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Email function not available']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Email configuration missing']);
            }
            break;

        case 'mark_contacted':
        case 'update_inquiry_status':
            debugLog("Processing update_inquiry_status for ID: " . ($_POST['id'] ?? 'null'));
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                break;
            }
            $new_status = trim($_POST['status'] ?? $_POST['new_status'] ?? 'contacted');
            $allowed_statuses = ['contacted', 'confirmed', 'cancelled', 'pending'];
            if (!in_array($new_status, $allowed_statuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                break;
            }
            $timestamp = date('Y-m-d H:i:s');
            $admin_user = $_SESSION['admin_username'] ?? 'Admin';
            $status_note = "\n[System: Status changed to '" . $new_status . "' on " . $timestamp . " by " . $admin_user . "]";
            $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ?, admin_notes = CONCAT(COALESCE(admin_notes, ''), ?) WHERE id = ?");
            if ($stmt->execute([$new_status, $status_note, $id])) {
                // Send email based on status
                $emailSent = false;
                $email_functions_path = __DIR__ . '/../config/email_functions.php';

                if (file_exists($email_functions_path)) {
                    require_once $email_functions_path;

                    if ($new_status === 'confirmed' && function_exists('sendInquiryContactedEmail')) {
                        $emailSent = sendInquiryContactedEmail($id);
                        debugLog("Inquiry confirmation email sent: " . ($emailSent ? 'Success' : 'Failed'));
                    } else if ($new_status === 'cancelled' && function_exists('sendInquiryCancelledEmail')) {
                        $emailSent = sendInquiryCancelledEmail($id);
                        debugLog("Inquiry cancellation email sent: " . ($emailSent ? 'Success' : 'Failed'));
                    }
                }

                $displayStatus = ($new_status === 'confirmed') ? 'Reviewed' : ucfirst($new_status);
                echo json_encode([
                    'success' => true,
                    'email_sent' => $emailSent,
                    'message' => 'Inquiry status updated to ' . $displayStatus . $emailMsg
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update record']);
            }
            break;

        case 'get_notifications':
            error_reporting(0);
            if (ob_get_length())
                ob_clean();
            header('Content-Type: application/json');
            debugLog("Processing get_notifications");

            // 1. Query upcoming travel dates for admin dashboard (next 3 days)
            $stmt = $pdo->prepare("
                SELECT id, full_name, destination_name, travel_date, booking_number, booking_status, 
                       payment_status, travel_documents, ready_for_travel, visa_status, package_name
                FROM bookings 
                WHERE LOWER(booking_status) != 'cancelled'
                ORDER BY travel_date ASC
            ");
            $stmt->execute();
            $all_confirmed = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $upcoming = [];
            $today = new DateTime('today');
            $limit = clone $today;
            $limit->modify('+3 days')->setTime(23, 59, 59);

            foreach ($all_confirmed as $booking) {
                if (empty($booking['travel_date']))
                    continue;

                // Truly Finished Logic (Consistency with Dashboard)
                $isVisaRelated = ($booking['destination_name'] === 'Visa Assistance' ||
                    stripos($booking['package_name'] ?? '', 'Visa') !== false ||
                    (isset($booking['booking_number']) && strpos($booking['booking_number'], 'VI-') === 0));

                $vStatus = strtoupper($booking['visa_status'] ?? 'PENDING');
                $isVisaMatch = ($vStatus === 'APPROVED' || $vStatus === 'N/A' || !$isVisaRelated);

                $isFinished = (strtolower($booking['booking_status'] ?? '') === 'completed' &&
                    strtolower($booking['payment_status'] ?? '') === 'paid' &&
                    intval($booking['travel_documents'] ?? 0) === 1 &&
                    $isVisaMatch);

                if (!$isFinished)
                    continue; // Only fully completed bookings should pop up on upcoming

                try {
                    $tDate = new DateTime($booking['travel_date']);
                    if ($tDate >= $today && $tDate <= $limit) {
                        $upcoming[] = $booking;
                    }
                } catch (Exception $e) {
                    continue; // Skip invalid dates
                }
            }

            // 2. Identify bookings requiring customer reminders (1-2 days away)
            $to_remind = [];
            try {
                $remind_stmt = $pdo->prepare("
                    SELECT id, full_name, email, destination_name, travel_date, booking_number, package_name
                    FROM bookings 
                    WHERE travel_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                    AND booking_status IN ('confirmed', 'completed')
                    AND reminder_sent = 0
                ");
                $remind_stmt->execute();
                $to_remind = $remind_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                debugLog("Reminder query failed (likely missing reminder_sent column): " . $e->getMessage());
            }

            if (!empty($to_remind)) {
                try {
                    require_once __DIR__ . '/../config/email_functions.php';
                    foreach ($to_remind as $booking) {
                        if (function_exists('sendFlightReminderEmail')) {
                            sendFlightReminderEmail($booking['id'], $booking);
                        }
                    }
                    debugLog("Customer reminder emails sent for " . count($to_remind) . " bookings");
                } catch (Exception $e) {
                    debugLog("Error sending customer reminders: " . $e->getMessage());
                } catch (Error $e) {
                    debugLog("Fatal error in customer reminders: " . $e->getMessage());
                }
            }

            // Admin travel alert email removed as requested

            echo json_encode([
                'success' => true,
                'count' => count($upcoming),
                'travels' => $upcoming,
                'reminders_sent' => count($to_remind)
            ]);
            break;

        case 'delete_booking':
            $idInput = $_POST['id'] ?? $_GET['id'] ?? 0;
            $bookingNumberInput = trim($_POST['booking_number'] ?? $_GET['booking_number'] ?? '');
            debugLog("Processing delete_booking for ID: " . (is_scalar($idInput) ? $idInput : 'null') . " / booking number: " . $bookingNumberInput);

            // Only Super Admin can delete bookings
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for delete_booking - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($idInput);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
                $success = $stmt->execute([$id]);
                $deleted = $success && $stmt->rowCount() > 0;
                debugLog("Booking delete " . ($deleted ? "successful" : "failed") . " for ID: " . $id);
                echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Booking deleted successfully' : 'Booking not found']);
            } elseif ($bookingNumberInput !== '') {
                $stmt = $pdo->prepare("DELETE FROM bookings WHERE booking_number = ?");
                $success = $stmt->execute([$bookingNumberInput]);
                $deleted = $success && $stmt->rowCount() > 0;
                debugLog("Booking delete " . ($deleted ? "successful" : "failed") . " for booking number: " . $bookingNumberInput);
                echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Booking deleted successfully' : 'Booking not found']);
            } else {
                debugLog("Invalid booking ID for delete: " . ($id ?? 'null'));
                echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
            }
            break;

        case 'get_destination':
            debugLog("Processing get_destination for ID: " . ($_GET['id'] ?? 'null'));

            // All admin roles can view destinations (including Sales)
            if (!hasPermission(['super_admin', 'admin', 'editor', 'sales'], $admin_role)) {
                debugLog("Permission denied for get_destination - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                debugLog("Invalid destination ID: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid destination ID']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM destinations WHERE id = ?");
            $stmt->execute([$id]);
            $destination = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($destination) {
                debugLog("Destination found for ID: " . $id);
                echo json_encode(['success' => true, 'data' => $destination]);
            } else {
                debugLog("Destination not found for ID: " . $id);
                echo json_encode(['success' => false, 'message' => 'Destination not found']);
            }
            break;

        case 'update_destination':
            debugLog("Processing update_destination");

            // Super Admin and Admin can update destinations, Editor can also update, Sales cannot
            if (!hasPermission(['super_admin', 'admin', 'editor'], $admin_role)) {
                debugLog("Permission denied for update_destination - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied. Sales role cannot edit destinations.']);
                break;
            }

            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                debugLog("Invalid destination ID for update: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid destination ID']);
                break;
            }

            $name = trim($_POST['name'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $type = trim($_POST['type'] ?? 'foreign');
            $description = trim($_POST['description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            $remarks = trim($_POST['remarks'] ?? '');
            $promo_start = !empty($_POST['promo_start']) ? $_POST['promo_start'] : null;
            $promo_end = !empty($_POST['promo_end']) ? $_POST['promo_end'] : null;
            $blocked_months = trim($_POST['blocked_months'] ?? '');
            $highlight_duration = intval($_POST['highlight_duration'] ?? 1);
            $blocked_dates = trim($_POST['blocked_dates'] ?? '');

            if (empty($name)) {
                debugLog("Destination name is required for update");
                echo json_encode(['success' => false, 'message' => 'Destination name is required']);
                break;
            }

            debugLog("Updating destination ID: $id, Name: $name");

            $stmt = $pdo->prepare("UPDATE destinations SET name = ?, country = ?, city = ?, type = ?, description = ?, is_active = ?, remarks = ?, promo_start = ?, promo_end = ?, blocked_months = ?, highlight_duration = ?, blocked_dates = ? WHERE id = ?");
            $success = $stmt->execute([$name, $country, $city, $type, $description, $is_active, $remarks, $promo_start, $promo_end, $blocked_months, $highlight_duration, $blocked_dates, $id]);
            debugLog("Destination update " . ($success ? "successful" : "failed"));
            echo json_encode(['success' => $success]);
            break;

        case 'add_destination':
            debugLog("Processing add_destination");

            // Super Admin and Admin can add destinations, Editor can also add, Sales cannot
            if (!hasPermission(['super_admin', 'admin', 'editor'], $admin_role)) {
                debugLog("Permission denied for add_destination - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied. Sales role cannot add destinations.']);
                break;
            }

            $name = trim($_POST['name'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $type = trim($_POST['type'] ?? 'foreign');
            $description = trim($_POST['description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            $remarks = trim($_POST['remarks'] ?? '');
            $promo_start = !empty($_POST['promo_start']) ? $_POST['promo_start'] : null;
            $promo_end = !empty($_POST['promo_end']) ? $_POST['promo_end'] : null;
            $blocked_months = trim($_POST['blocked_months'] ?? '');
            $highlight_duration = intval($_POST['highlight_duration'] ?? 1);
            $blocked_dates = trim($_POST['blocked_dates'] ?? '');

            if (empty($name)) {
                debugLog("Destination name is required for add");
                echo json_encode(['success' => false, 'message' => 'Destination name is required']);
                break;
            }

            debugLog("Adding new destination: $name");

            $stmt = $pdo->prepare("INSERT INTO destinations (name, country, city, type, description, is_active, remarks, promo_start, promo_end, blocked_months, highlight_duration, blocked_dates) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$name, $country, $city, $type, $description, $is_active, $remarks, $promo_start, $promo_end, $blocked_months, $highlight_duration, $blocked_dates]);
            $newId = $pdo->lastInsertId();
            debugLog("Destination added with ID: $newId, Success: " . ($success ? "Yes" : "No"));
            echo json_encode(['success' => $success, 'id' => $newId]);
            break;

        case 'delete_destination':
            debugLog("Processing delete_destination for ID: " . ($_POST['id'] ?? 'null'));

            // Only Super Admin can delete destinations
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for delete_destination - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                debugLog("Invalid destination ID for delete: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid destination ID']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM destinations WHERE id = ?");
            $success = $stmt->execute([$id]);
            debugLog("Destination delete " . ($success ? "successful" : "failed") . " for ID: " . $id);
            echo json_encode(['success' => $success]);
            break;

        case 'get_package':
            debugLog("Processing get_package for ID: " . ($_GET['id'] ?? 'null'));

            // All admin roles can view packages (including Sales)
            if (!hasPermission(['super_admin', 'admin', 'editor', 'sales'], $admin_role)) {
                debugLog("Permission denied for get_package - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                debugLog("Invalid package ID: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
            $stmt->execute([$id]);
            $package = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($package) {
                debugLog("Package found for ID: " . $id);
                echo json_encode(['success' => true, 'data' => $package]);
            } else {
                debugLog("Package not found for ID: " . $id);
                echo json_encode(['success' => false, 'message' => 'Package not found']);
            }
            break;

        case 'update_package':
            debugLog("Processing update_package");

            // Super Admin and Admin can update packages, Editor can also update, Sales cannot
            if (!hasPermission(['super_admin', 'admin', 'editor'], $admin_role)) {
                debugLog("Permission denied for update_package - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied. Sales role cannot update packages.']);
                break;
            }

            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                debugLog("Invalid package ID for update: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                break;
            }

            $destination_id = intval($_POST['destination_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $activities_count = intval($_POST['activities_count'] ?? 0);
            $is_active = intval($_POST['is_active'] ?? 1);

            if (empty($name)) {
                debugLog("Package name is required for update");
                echo json_encode(['success' => false, 'message' => 'Package name is required']);
                break;
            }

            debugLog("Updating package ID: $id, Name: $name");

            $stmt = $pdo->prepare("UPDATE packages SET destination_id = ?, name = ?, duration = ?, price = ?, activities_count = ?, is_active = ? WHERE id = ?");
            $success = $stmt->execute([$destination_id, $name, $duration, $price, $activities_count, $is_active, $id]);
            debugLog("Package update " . ($success ? "successful" : "failed"));
            echo json_encode(['success' => $success]);
            break;

        case 'add_package':
            debugLog("Processing add_package");

            // Super Admin and Admin can add packages, Editor can also add, Sales cannot
            if (!hasPermission(['super_admin', 'admin', 'editor'], $admin_role)) {
                debugLog("Permission denied for add_package - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied. Sales role cannot add packages.']);
                break;
            }

            $destination_id = intval($_POST['destination_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $activities_count = intval($_POST['activities_count'] ?? 0);
            $is_active = intval($_POST['is_active'] ?? 1);

            if (empty($name)) {
                debugLog("Package name is required for add");
                echo json_encode(['success' => false, 'message' => 'Package name is required']);
                break;
            }

            debugLog("Adding new package: $name for destination ID: $destination_id");

            $stmt = $pdo->prepare("INSERT INTO packages (destination_id, name, duration, price, activities_count, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$destination_id, $name, $duration, $price, $activities_count, $is_active]);
            $newId = $pdo->lastInsertId();
            debugLog("Package added with ID: $newId, Success: " . ($success ? "Yes" : "No"));
            echo json_encode(['success' => $success, 'id' => $newId]);
            break;

        case 'delete_package':
            debugLog("Processing delete_package for ID: " . ($_POST['id'] ?? 'null'));

            // Only Super Admin can delete packages
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for delete_package - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                debugLog("Invalid package ID for delete: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
            $success = $stmt->execute([$id]);
            debugLog("Package delete " . ($success ? "successful" : "failed") . " for ID: " . $id);
            echo json_encode(['success' => $success]);
            break;

        case 'get_destinations_list':
            debugLog("Processing get_destinations_list");

            // All admin roles can view destinations list (including Sales)
            if (!hasPermission(['super_admin', 'admin', 'editor', 'sales'], $admin_role)) {
                debugLog("Permission denied for get_destinations_list - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, name FROM destinations WHERE is_active = 1 ORDER BY name");
            $stmt->execute();
            $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            debugLog("Found " . count($destinations) . " destinations");
            echo json_encode(['success' => true, 'data' => $destinations]);
            break;

        case 'export_bookings':
            debugLog("Processing export_bookings");

            // Only Super Admin and Admin can export bookings (Sales cannot export)
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                debugLog("Permission denied for export_bookings - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied. Sales role cannot export bookings.']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM bookings ORDER BY created_at DESC");
            $stmt->execute();
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="bookings_export_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Booking Number', 'Destination', 'Package', 'Customer Name', 'Email', 'Phone', 'Travel Date', 'Travelers', 'Total Amount', 'Booking Status', 'Payment Status', 'Created At']);

            foreach ($bookings as $booking) {
                fputcsv($output, [
                    $booking['id'],
                    $booking['booking_number'],
                    $booking['destination_name'],
                    $booking['package_name'],
                    $booking['full_name'],
                    $booking['email'],
                    $booking['phone'],
                    $booking['travel_date'],
                    $booking['number_of_travelers'],
                    $booking['total_amount'],
                    $booking['booking_status'],
                    $booking['payment_status'],
                    $booking['created_at']
                ]);
            }
            fclose($output);
            exit;
            break;

        // ========== ADMIN MANAGEMENT ENDPOINTS ==========

        case 'get_admin_users':
            debugLog("Processing get_admin_users");

            // Only Super Admin can view admin users
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for get_admin_users - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, last_login, is_active, created_at FROM admin_users ORDER BY created_at DESC");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            debugLog("Found " . count($admins) . " admin users");
            echo json_encode(['success' => true, 'data' => $admins]);
            break;

        case 'get_all_users':
            debugLog("Processing get_all_users");

            // Only Super Admin can view all users (without passwords)
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for get_all_users - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, full_name, email, phone, provider, is_active, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            debugLog("Found " . count($users) . " users");
            echo json_encode(['success' => true, 'data' => $users]);
            break;

        case 'update_admin_status':
            debugLog("Processing update_admin_status");

            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for update_admin_status - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($_POST['id'] ?? 0);
            $is_active = intval($_POST['is_active'] ?? 1);

            if ($id <= 0) {
                debugLog("Invalid admin ID for status update: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE admin_users SET is_active = ? WHERE id = ?");
            $success = $stmt->execute([$is_active, $id]);
            debugLog("Admin status update " . ($success ? "successful" : "failed") . " for ID: $id");
            echo json_encode(['success' => $success]);
            break;

        case 'get_pending_requests':
            debugLog("Processing get_pending_requests");

            // Only super admin can view pending requests
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for get_pending_requests - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, requested_at FROM admin_registration_requests WHERE status = 'pending' ORDER BY requested_at DESC");
            $stmt->execute();
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            debugLog("Found " . count($requests) . " pending requests");
            echo json_encode(['success' => true, 'data' => $requests]);
            break;

        case 'approve_admin_request':
            debugLog("Processing approve_admin_request for ID: " . ($_POST['request_id'] ?? 'null'));

            // Only super admin can approve requests
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for approve_admin_request - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $request_id = intval($_POST['request_id'] ?? 0);
            if ($request_id <= 0) {
                debugLog("Invalid request ID for approval: " . $request_id);
                echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
                break;
            }

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM admin_registration_requests WHERE id = ? AND status = 'pending'");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$request) {
                    debugLog("Request not found or already processed for ID: " . $request_id);
                    echo json_encode(['success' => false, 'message' => 'Request not found or already processed']);
                    $pdo->rollBack();
                    break;
                }

                // Remove any soft-deleted users with the same username/email to allow re-registration
                $stmt = $pdo->prepare("DELETE FROM admin_users WHERE (username = ? OR email = ?) AND (is_active = 0 OR is_active IS NULL)");
                $stmt->execute([$request['username'], $request['email']]);

                $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
                $stmt->execute([$request['username'], $request['email']]);
                if ($stmt->fetch()) {
                    debugLog("Username or email already exists: " . $request['username'] . " / " . $request['email']);
                    echo json_encode(['success' => false, 'message' => 'Username or email already exists on an active account']);
                    $pdo->rollBack();
                    break;
                }

                // Store hashed password (already hashed from request)
                $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password, full_name, role, is_active, approved) VALUES (?, ?, ?, ?, ?, 1, 1)");
                $stmt->execute([
                    $request['username'],
                    $request['email'],
                    $request['password'],  // Already hashed from registration
                    $request['full_name'],
                    $request['role']
                ]);

                $stmt = $pdo->prepare("UPDATE admin_registration_requests SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id'], $request_id]);

                $pdo->commit();

                debugLog("Admin request approved successfully for ID: " . $request_id);
                echo json_encode(['success' => true, 'message' => 'Admin account approved successfully']);

            } catch (PDOException $e) {
                $pdo->rollBack();
                debugLog("Admin approval error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'get_partner_packages_for_approval':
            debugLog("Processing get_partner_packages_for_approval");

            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                debugLog("Permission denied for get_partner_packages_for_approval - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, partner_company, uploaded_by_name, uploaded_by_email, package_name, destination_name, duration, price, upload_status, created_at FROM partner_package_uploads ORDER BY created_at DESC");
            $stmt->execute();
            $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $packages]);
            break;

        case 'approve_partner_package_submission':
            debugLog("Processing approve_partner_package_submission for ID: " . ($_POST['package_id'] ?? 'null'));

            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $package_id = intval($_POST['package_id'] ?? 0);
            if ($package_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE partner_package_uploads SET upload_status = 'approved', is_active = 1, updated_at = NOW() WHERE id = ?" );
            if ($stmt->execute([$package_id]) && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Package approved and published.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unable to approve package.']);
            }
            break;

        case 'reject_partner_package_submission':
            debugLog("Processing reject_partner_package_submission for ID: " . ($_POST['package_id'] ?? 'null'));

            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $package_id = intval($_POST['package_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($package_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                break;
            }

            if (empty($reason)) {
                $reason = 'No reason provided';
            }

            $stmt = $pdo->prepare("UPDATE partner_package_uploads SET upload_status = 'rejected', is_active = 0, remarks = COALESCE(CONCAT(remarks, '\n', ?), ?) , updated_at = NOW() WHERE id = ?" );
            if ($stmt->execute([$reason, $reason, $package_id]) && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Package rejected.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unable to reject package.']);
            }
            break;

        case 'get_partner_applications':
            debugLog("Processing get_partner_applications");

            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                debugLog("Permission denied for get_partner_applications - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, company_name, contact_person, email, phone, business_type, website, status, rejection_reason, approved_at, created_at FROM partner_applications ORDER BY created_at DESC");
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $applications]);
            break;

        case 'get_approved_partners':
            debugLog("Processing get_approved_partners");

            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                debugLog("Permission denied for get_approved_partners - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, company_name, contact_person, email, phone, business_type, website, approved_at, created_at, is_banned, ban_until FROM partner_applications WHERE status = 'approved' ORDER BY approved_at DESC");
            $stmt->execute();
            $approvedPartners = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $approvedPartners]);
            break;

        case 'approve_partner_application':
            debugLog("Processing approve_partner_application for ID: " . ($_POST['request_id'] ?? 'null'));
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                debugLog("Permission denied for approve_partner_application - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $request_id = intval($_POST['request_id'] ?? 0);
            if ($request_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE partner_applications SET status = 'approved', approved_at = NOW(), rejection_reason = NULL WHERE id = ? AND status = 'pending'");
            if ($stmt->execute([$request_id]) && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Partner application approved']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unable to approve application or already processed']);
            }
            break;

        case 'reject_partner_application':
            debugLog("Processing reject_partner_application for ID: " . ($_POST['request_id'] ?? 'null'));
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                debugLog("Permission denied for reject_partner_application - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $request_id = intval($_POST['request_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($request_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
                break;
            }

            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'A rejection reason is required']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE partner_applications SET status = 'rejected', rejection_reason = ?, approved_at = NULL WHERE id = ? AND status = 'pending'");
            if ($stmt->execute([$reason, $request_id]) && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Partner application rejected']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unable to reject application or already processed']);
            }
            break;

        case 'update_admin_role':
            debugLog("Processing update_admin_role");

            // Only Super Admin can change roles
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for update_admin_role - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($_POST['id'] ?? 0);
            $new_role = trim($_POST['role'] ?? 'admin');

            if ($id <= 0) {
                debugLog("Invalid admin ID for role update: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
                break;
            }

            // Validate role
            $allowed_roles = ['super_admin', 'admin', 'editor', 'sales'];
            if (!in_array($new_role, $allowed_roles)) {
                debugLog("Invalid role: " . $new_role);
                echo json_encode(['success' => false, 'message' => 'Invalid role']);
                break;
            }

            // Prevent changing own role if it would remove super admin
            if ($id == $_SESSION['admin_id'] && $new_role !== 'super_admin') {
                debugLog("Cannot change own role from Super Admin");
                echo json_encode(['success' => false, 'message' => 'Cannot change your own role from Super Admin']);
                break;
            }

            // Prevent removing last super admin
            if ($new_role !== 'super_admin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND is_active = 1");
                $stmt->execute();
                $superAdminCount = $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
                $stmt->execute([$id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($current && $current['role'] === 'super_admin' && $superAdminCount <= 1) {
                    debugLog("Cannot change last super admin role");
                    echo json_encode(['success' => false, 'message' => 'Cannot change the last super admin role']);
                    break;
                }
            }

            $stmt = $pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
            $success = $stmt->execute([$new_role, $id]);
            debugLog("Admin role update " . ($success ? "successful" : "failed") . " for ID: $id to role: $new_role");

            echo json_encode(['success' => $success, 'message' => $success ? 'Role updated successfully' : 'Failed to update role']);
            break;

        case 'get_admin_roles':
            debugLog("Processing get_admin_roles");

            // Only Super Admin can view role options
            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for get_admin_roles - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    ['value' => 'super_admin', 'label' => 'Super Admin'],
                    ['value' => 'admin', 'label' => 'Admin'],
                    ['value' => 'editor', 'label' => 'Editor'],
                    ['value' => 'sales', 'label' => 'Sales']
                ]
            ]);
            break;

        case 'delete_admin_user':
            debugLog("Processing delete_admin_user for ID: " . ($_POST['id'] ?? 'null'));

            if (!hasPermission(['super_admin'], $admin_role)) {
                debugLog("Permission denied for delete_admin_user - role: " . $admin_role);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }

            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                debugLog("Invalid admin ID for delete: " . $id);
                echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
                break;
            }

            if ($id == $_SESSION['admin_id']) {
                debugLog("Cannot delete own account");
                echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                break;
            }

            // Prevent deleting the last super admin
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND is_active = 1");
            $stmt->execute();
            $superAdminCount = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ? AND is_active = 1");
            $stmt->execute([$id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && $admin['role'] === 'super_admin' && $superAdminCount <= 1) {
                debugLog("Cannot delete last super admin");
                echo json_encode(['success' => false, 'message' => 'Cannot delete the last super admin account']);
                break;
            }

            // SOFT DELETE - Set is_active to 0 instead of deleting permanently
            $stmt = $pdo->prepare("UPDATE admin_users SET is_active = 0, approved = 0 WHERE id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                // Also delete any pending requests for this user to allow new requests
                $stmt = $pdo->prepare("SELECT username, email FROM admin_users WHERE id = ?");
                $stmt->execute([$id]);
                $deletedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($deletedAdmin) {
                    // Delete any pending requests with this username or email
                    $stmt = $pdo->prepare("DELETE FROM admin_registration_requests WHERE username = ? OR email = ?");
                    $stmt->execute([$deletedAdmin['username'], $deletedAdmin['email']]);
                    debugLog("Deleted pending requests for username: " . $deletedAdmin['username']);
                }

                debugLog("Admin user deactivated successfully for ID: $id");
                echo json_encode(['success' => true, 'message' => 'Admin user deactivated successfully']);
            } else {
                debugLog("Failed to deactivate admin user for ID: $id");
                echo json_encode(['success' => false, 'message' => 'Failed to deactivate admin user']);
            }
            break;

        case 'get_visa_bookings':
            debugLog("Processing get_visa_bookings");
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE destination_name = 'Visa Assistance' ORDER BY created_at DESC");
            $stmt->execute();
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $bookings]);
            break;

        case 'get_visa_booking':
            debugLog("Processing get_visa_booking for ID: " . ($_GET['id'] ?? 'null'));
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $id = intval($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND destination_name = 'Visa Assistance'");
            $stmt->execute([$id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($booking) {
                echo json_encode(['success' => true, 'data' => $booking]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Visa application not found']);
            }
            break;

        case 'update_visa_status':
            debugLog("Processing update_visa_status");
            if (!hasPermission(['super_admin', 'admin', 'sales'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $id = intval($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $reason = $_POST['reason'] ?? '';

            $booking_status = 'pending';
            if ($status === 'approved')
                $booking_status = 'confirmed';
            elseif ($status === 'declined')
                $booking_status = 'cancelled';
            elseif ($status === 'incomplete')
                $booking_status = 'pending';

            $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ?, admin_notes = ? WHERE id = ?");
            if ($stmt->execute([$booking_status, $reason, $id])) {
                // Fetch booking data for email notification (consistent with update_booking)
                $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
                $stmt->execute([$id]);
                $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);

                $emailSent = false;
                $email_functions_path = __DIR__ . '/../config/email_functions.php';
                if (file_exists($email_functions_path)) {
                    require_once $email_functions_path;
                    if (function_exists('sendVisaStatusUpdateEmail')) {
                        $emailSent = sendVisaStatusUpdateEmail($id, $status, $reason, $booking_data);
                    }
                }

                echo json_encode(['success' => true, 'message' => 'Visa status updated successfully', 'email_sent' => $emailSent]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update visa status']);
            }
            break;

        case 'update_visa_payment_status':
            $id = intval($_POST['id'] ?? 0);
            $payment_status = $_POST['payment_status'] ?? '';

            $stmt = $pdo->prepare("SELECT payment_status, destination_name FROM bookings WHERE id = ?");
            $stmt->execute([$id]);
            $old_booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_booking) {
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
            if ($stmt->execute([$payment_status, $id])) {
                $emailSent = false;
                if ($old_booking['payment_status'] !== 'paid' && $payment_status === 'paid' && $old_booking['destination_name'] === 'Visa Assistance') {
                    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
                    $stmt->execute([$id]);
                    $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);

                    $email_functions_path = __DIR__ . '/../config/email_functions.php';
                    if (file_exists($email_functions_path)) {
                        require_once $email_functions_path;
                        if (function_exists('sendVisaPaymentConfirmationEmail')) {
                            $emailSent = sendVisaPaymentConfirmationEmail($id, $booking_data);
                        }
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Payment status updated', 'email_sent' => $emailSent]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update payment status']);
            }
            break;

        case 'delete_inquiry':
            debugLog("Processing delete_inquiry for ID: " . ($_POST['id'] ?? 'null'));
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ? AND payment_method = 'Inquiry Only'");
            if ($stmt->execute([$id])) {
                echo json_encode(['success' => true, 'message' => 'Inquiry deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete inquiry']);
            }
            break;

        case 'export_inquiries':
            debugLog("Processing export_inquiries");
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $stmt = $pdo->prepare("SELECT full_name, email, phone, destination_name, package_name, travel_date, special_requests, created_at FROM bookings WHERE payment_method = 'Inquiry Only' ORDER BY created_at DESC");
            $stmt->execute();
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="inquiries_export_' . date('Y-m-d') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Full Name', 'Email', 'Phone', 'Type', 'Destination', 'Travel Date', 'Special Requests', 'Submitted At']);
            foreach ($inquiries as $inq) {
                fputcsv($output, $inq);
            }
            fclose($output);
            exit;
            break;

        case 'reject_admin_request':
            debugLog("Processing reject_admin_request for ID: " . ($_POST['request_id'] ?? 'null'));
            if (!hasPermission(['super_admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                break;
            }
            $request_id = intval($_POST['request_id'] ?? 0);
            $reason = $_POST['rejection_reason'] ?? '';

            $stmt = $pdo->prepare("UPDATE admin_registration_requests SET status = 'rejected', rejection_reason = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
            if ($stmt->execute([$reason, $_SESSION['admin_id'], $request_id])) {
                echo json_encode(['success' => true, 'message' => 'Request rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject request']);
            }
            break;

        case 'ban_partner':
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']); break;
            }
            $partner_id = intval($_POST['partner_id'] ?? 0);
            $ban_action = trim($_POST['ban_action'] ?? '');
            if ($partner_id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid partner ID']); break; }
            if ($ban_action === 'unban') {
                $stmt = $pdo->prepare("UPDATE partner_applications SET is_banned = 0, ban_until = NULL WHERE id = ?");
                $stmt->execute([$partner_id]);
                echo json_encode(['success' => true, 'message' => 'Partner has been unbanned successfully.']);
            } else {
                $ban_days = isset($_POST['ban_days']) ? intval($_POST['ban_days']) : null;
                if ($ban_days !== null && $ban_days > 0) {
                    $ban_until = date('Y-m-d H:i:s', strtotime("+{$ban_days} days"));
                    $stmt = $pdo->prepare("UPDATE partner_applications SET is_banned = 1, ban_until = ? WHERE id = ?");
                    $stmt->execute([$ban_until, $partner_id]);
                    echo json_encode(['success' => true, 'message' => "Partner banned for {$ban_days} day(s)."]);
                } else {
                    $stmt = $pdo->prepare("UPDATE partner_applications SET is_banned = 1, ban_until = NULL WHERE id = ?");
                    $stmt->execute([$partner_id]);
                    echo json_encode(['success' => true, 'message' => 'Partner has been permanently banned.']);
                }
            }
            break;

        case 'delete_partner':
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']); break;
            }
            $partner_id = intval($_POST['partner_id'] ?? 0);
            if ($partner_id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid partner ID']); break; }
            $stmt = $pdo->prepare("DELETE FROM partner_applications WHERE id = ?");
            if ($stmt->execute([$partner_id])) {
                echo json_encode(['success' => true, 'message' => 'Partner account deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete partner.']);
            }
            break;

        case 'delete_user':
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) { echo json_encode(['success' => false, 'message' => 'Permission denied']); break; }
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid user ID']); break; }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                echo json_encode(['success' => true, 'message' => 'User account deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
            }
            break;

        case 'ban_user':
            if (!hasPermission(['super_admin', 'admin'], $admin_role)) { echo json_encode(['success' => false, 'message' => 'Permission denied']); break; }
            $user_id = intval($_POST['user_id'] ?? 0);
            $ban_action = trim($_POST['ban_action'] ?? '');
            if ($user_id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid user ID']); break; }
            // Ensure columns exist
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_banned TINYINT(1) DEFAULT 0");
            } catch (Throwable $e) {}
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_until DATETIME DEFAULT NULL");
            } catch (Throwable $e) {}

            if ($ban_action === 'unban') {
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_until = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                echo json_encode(['success' => true, 'message' => 'User has been unbanned successfully.']);
            } else {
                $ban_days = isset($_POST['ban_days']) ? intval($_POST['ban_days']) : null;
                if ($ban_days !== null && $ban_days > 0) {
                    $ban_until = date('Y-m-d H:i:s', strtotime("+{$ban_days} days"));
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_until = ? WHERE id = ?");
                    $stmt->execute([$ban_until, $user_id]);
                    echo json_encode(['success' => true, 'message' => "User banned for {$ban_days} day(s)."]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_until = NULL WHERE id = ?");
                    $stmt->execute([$user_id]);
                    echo json_encode(['success' => true, 'message' => 'User has been permanently banned.']);
                }
            }
            break;

        case 'get_booking_trends':
            debugLog("Processing get_booking_trends");

            $period = ($_GET['period'] ?? 'day') === 'month' ? 'month' : 'day';

            if ($period === 'month') {
                $currentYear = (int) date('Y');
                $minYearRow = $pdo->query("SELECT MIN(YEAR(created_at)) as minY FROM bookings")->fetch();
                $minYear = $minYearRow && $minYearRow['minY'] ? (int) $minYearRow['minY'] : $currentYear;

                $requestedYear = (int) ($_GET['year'] ?? $currentYear);
                if ($requestedYear < 2000 || $requestedYear > $currentYear) {
                    echo json_encode(['success' => false, 'message' => 'Year must be between ' . $minYear . ' and ' . $currentYear . '.']);
                    break;
                }

                $stmt = $pdo->prepare("SELECT MONTH(created_at) as m, COUNT(*) as total
                    FROM bookings
                    WHERE YEAR(created_at) = ?
                    GROUP BY MONTH(created_at)");
                $stmt->execute([$requestedYear]);
                $rawCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $labels = [];
                $data = [];
                $peak = 0;
                $peakMonth = 1;
                for ($m = 1; $m <= 12; $m++) {
                    $count = (int) ($rawCounts[$m] ?? 0);
                    $labels[] = date('M', mktime(0, 0, 0, $m, 1));
                    $data[] = $count;
                    if ($count > $peak) {
                        $peak = $count;
                        $peakMonth = $m;
                    }
                }

                $total = array_sum($data);
                $avg = $total > 0 ? round($total / 12, 1) : 0;

                echo json_encode([
                    'success' => true,
                    'labels' => $labels,
                    'data' => $data,
                    'total' => $total,
                    'avg' => $avg,
                    'peak' => $peak,
                    'peakDateLabel' => $peak > 0 ? date('F Y', mktime(0, 0, 0, $peakMonth, 1, $requestedYear)) : null,
                    'rangeLabel' => 'Year ' . $requestedYear,
                    'minYear' => $minYear,
                    'maxYear' => $currentYear,
                    'year' => $requestedYear,
                    'period' => 'month',
                ]);
                break;
            }

            $maxStartDate = date('Y-m-d', strtotime('-13 days'));
            $requestedStart = $_GET['start_date'] ?? $maxStartDate;

            $startDateTime = DateTime::createFromFormat('Y-m-d', $requestedStart);
            if (!$startDateTime || $startDateTime->format('Y-m-d') !== $requestedStart) {
                echo json_encode(['success' => false, 'message' => 'Invalid start date.']);
                break;
            }
            if ($requestedStart > $maxStartDate) {
                echo json_encode(['success' => false, 'message' => 'Start date cannot be later than ' . date('M j, Y', strtotime($maxStartDate)) . ' (14 days must fit within today).']);
                break;
            }

            $startDate = $requestedStart;
            $endDate = date('Y-m-d', strtotime($startDate . ' +13 days'));

            $stmt = $pdo->prepare("SELECT DATE(created_at) as booking_date, COUNT(*) as total
                FROM bookings
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)");
            $stmt->execute([$startDate, $endDate]);
            $rawCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $labels = [];
            $data = [];
            $peak = 0;
            $peakDate = $startDate;
            for ($i = 0; $i < 14; $i++) {
                $d = date('Y-m-d', strtotime($startDate . " +{$i} days"));
                $count = (int) ($rawCounts[$d] ?? 0);
                $labels[] = date('M j', strtotime($d));
                $data[] = $count;
                if ($count > $peak) {
                    $peak = $count;
                    $peakDate = $d;
                }
            }

            $total = array_sum($data);
            $avg = $total > 0 ? round($total / 14, 1) : 0;

            echo json_encode([
                'success' => true,
                'labels' => $labels,
                'data' => $data,
                'total' => $total,
                'avg' => $avg,
                'peak' => $peak,
                'peakDateLabel' => $peak > 0 ? date('M j, Y', strtotime($peakDate)) : null,
                'rangeLabel' => date('M j', strtotime($startDate)) . ' – ' . date('M j, Y', strtotime($endDate)),
                'maxStartDate' => $maxStartDate,
                'startDate' => $startDate,
                'period' => 'day',
            ]);
            break;

        default:
            debugLog("Invalid action: " . $action);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    $errorMessage = "PDO Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    debugLog($errorMessage);
    debugLog("SQL State: " . $e->getCode());
    debugLog("POST Data: " . print_r($_POST, true));

    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => $errorMessage
    ]);
} catch (Exception $e) {
    $errorMessage = "General Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    debugLog($errorMessage);

    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'debug' => $errorMessage
    ]);
}

debugLog("=== Admin API Request Completed ===\n");
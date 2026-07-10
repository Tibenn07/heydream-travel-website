<?php
// config/database.php

// Secure Headers to prevent Clickjacking and other attacks (Helps with Google 'Dangerous' flags)
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-XSS-Protection: 1; mode=block");

// Ensure app-wide date/time uses local Philippine time for vouchers and bookings
// (forced unconditionally: the hosting environment's php.ini may set its own
// default timezone, e.g. Europe/Berlin, which must not override this)
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// IMPORTANT: Update these with your actual database credentials!
// ============================================

// ============================================
// IMPORTANT: Update these with your actual database credentials!
// ============================================

$http_host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$is_localhost = php_sapi_name() === 'cli' || in_array($http_host, ['localhost', '127.0.0.1', '::1']) || (stripos($http_host, 'localhost:') === 0);

if ($is_localhost) {
    // LOCALHOST (XAMPP) Configuration
    $host = 'localhost';
    $dbname = 'heydream_travel';
    $username = 'root';
    $password = '';
} else {
    // Suppress error display on production to prevent JSON corruption
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);

    // LIVE HOST (Hostinger) Configuration
    // Use environment variables when available, otherwise fall back to provided Hostinger credentials
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'u796368004_heydream';
    $username = getenv('DB_USER') ?: 'u796368004_heywebsite';
    $password = getenv('DB_PASS') ?: 'Heydream@12345';
}

// ============================================
// End of database configuration
// ============================================

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database connection failed: " . $e->getMessage());
    $pdo = null;
}
// Auto-maintenance: Automatically update booking_status based on TRAVEL DOCUMENTS, VISA, and PAYMENT
if ($pdo !== null) {
    try {
        // 1. Mark completed: travel_documents = 1, payment_status = 'paid', visa_status = 'APPROVED' or 'N/A'
        $pdo->exec("
            UPDATE bookings 
            SET booking_status = 'completed',
                ready_for_travel = 1
            WHERE booking_status != 'completed' 
              AND booking_status != 'cancelled'
              AND travel_documents = 1 
              AND payment_status = 'paid' 
              AND UPPER(COALESCE(visa_status, 'N/A')) IN ('APPROVED', 'N/A')
        ");

        // 2. Revert back to confirmed (if paid) or pending (if unpaid) if any requirement is pending/incomplete
        $pdo->exec("
            UPDATE bookings 
            SET booking_status = CASE WHEN payment_status = 'paid' THEN 'confirmed' ELSE 'pending' END,
                ready_for_travel = 0
            WHERE booking_status = 'completed' 
              AND NOT (
                  travel_documents = 1 
                  AND payment_status = 'paid' 
                  AND UPPER(COALESCE(visa_status, 'N/A')) IN ('APPROVED', 'N/A')
              )
        ");

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
    } catch (Throwable $e) {
        // Fail silently so database connectivity is never interrupted
    }
}

// Check if admin is logged in (for admin pages)
function isAdminLoggedIn()
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Require admin login
function requireAdminLogin()
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Check if user is super admin
function isSuperAdmin()
{
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}

// Session configuration - ONLY START SESSION IF NOT ALREADY STARTED
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// User session management functions
class Auth
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Check if user is logged in
    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT u.* FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.session_token = ? AND s.user_id = ? AND s.expires_at > NOW() LIMIT 1");
            $stmt->execute([$_SESSION['session_token'], $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->logout();
                return false;
            }

            if ($this->isUserBanned($user)) {
                $this->logout();
                return false;
            }

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Get current user
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn())
            return null;

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }

    // Get user by email
    public function getUserByEmail($email)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    // Check whether a user is currently banned
    public function isUserBanned($user)
    {
        if (empty($user) || empty($user['is_banned'])) {
            return false;
        }

        if (!empty($user['ban_until'])) {
            $banUntil = strtotime($user['ban_until']);
            if ($banUntil !== false && $banUntil <= time()) {
                try {
                    $stmt = $this->pdo->prepare("UPDATE users SET is_banned = 0, ban_until = NULL WHERE id = ?");
                    $stmt->execute([$user['id']]);
                } catch (PDOException $e) {
                    // ignore if update fails
                }
                return false;
            }
        }

        return true;
    }

    // Create user session
    public function createSession($userId)
    {
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Check if user_sessions table exists, if not create it
        try {
            $stmt = $this->pdo->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $sessionToken,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $expiresAt
            ]);
        } catch (PDOException $e) {
            // Table might not exist, create it
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS user_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    session_token VARCHAR(255) NOT NULL UNIQUE,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_session_token (session_token)
                )
            ");
            $stmt = $this->pdo->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $sessionToken,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $expiresAt
            ]);
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['session_token'] = $sessionToken;

        return $sessionToken;
    }

    // Destroy user session
    public function logout()
    {
        if (isset($_SESSION['session_token'])) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
                $stmt->execute([$_SESSION['session_token']]);
            } catch (PDOException $e) {
                // Ignore if table doesn't exist
            }
        }

        session_destroy();
        return true;
    }

    // Register new user
    public function register($fullName, $email, $password, $phone = null, $provider = 'email', $providerId = null)
    {
        // Check if users table exists, if not create it
        try {
            $this->pdo->query("SELECT 1 FROM users LIMIT 1");
        } catch (PDOException $e) {
            // Create users table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    full_name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255),
                    phone VARCHAR(20),
                    profile_pic VARCHAR(255),
                    provider ENUM('email', 'google', 'facebook', 'apple') DEFAULT 'email',
                    provider_id VARCHAR(255),
                    email_verified BOOLEAN DEFAULT FALSE,
                    verification_token VARCHAR(255),
                    is_active BOOLEAN DEFAULT TRUE,
                    is_banned TINYINT(1) DEFAULT 0,
                    ban_until DATETIME DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_provider (provider)
                )
            ");
        }

        // Check if user exists
        $existingUser = $this->getUserByEmail($email);
        if ($existingUser) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
        $verificationToken = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare("INSERT INTO users (full_name, email, password, phone, provider, provider_id, verification_token, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)");
        $result = $stmt->execute([$fullName, $email, $hashedPassword, $phone, $provider, $providerId, $verificationToken]);

        if ($result) {
            $userId = $this->pdo->lastInsertId();
            // Don't create session yet, wait for email verification
            return ['success' => true, 'user_id' => $userId, 'token' => $verificationToken];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    // Verify email address
    public function verifyEmail($token)
    {
        $stmt = $this->pdo->prepare("SELECT id, full_name, email FROM users WHERE verification_token = ? AND email_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $this->pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Create session for the verified user
            $this->createSession($user['id']);
            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'message' => 'Invalid or expired verification link.'];
    }

    // Login user
    public function login($email, $password)
    {
        $user = $this->getUserByEmail($email);

        if (!$user) {
            return ['success' => false, 'message' => 'Email not found'];
        }

        if ($this->isUserBanned($user)) {
            return ['success' => false, 'message' => 'This account is banned and cannot log in. Please contact support if you believe this is an error.'];
        }

        if (empty($user['password'])) {
            return ['success' => false, 'use_google' => true, 'message' => 'This account uses Google Sign-In. Auto-triggering Google login...'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Incorrect password'];
        }

        $this->createSession($user['id']);
        return ['success' => true, 'user' => $user];
    }

    // Social login
    public function socialLogin($email, $fullName, $provider, $providerId, $phone = null)
    {
        $existingUser = $this->getUserByEmail($email);

        if ($existingUser) {
            if ($this->isUserBanned($existingUser)) {
                return ['success' => false, 'message' => 'This account is banned and cannot log in. Please contact support.'];
            }

            // Update provider info if needed
            if ($existingUser['provider'] !== $provider) {
                $stmt = $this->pdo->prepare("UPDATE users SET provider = ?, provider_id = ? WHERE id = ?");
                $stmt->execute([$provider, $providerId, $existingUser['id']]);
            }
            $this->createSession($existingUser['id']);
            return ['success' => true, 'user' => $existingUser];
        }

        // Create new user
        return $this->register($fullName, $email, null, $phone, $provider, $providerId);
    }

    // Get user bookings
    public function getUserBookings($userId)
    {
        // Check if bookings table has user_id column
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // If user_id column doesn't exist yet, return empty array
            return [];
        }
    }

    // Get booking details with tracking
    public function getBookingDetails($bookingNumber, $userId = null)
    {
        $sql = "SELECT b.*, 
                CASE 
                    WHEN b.booking_status = 'pending' THEN 'Waiting for confirmation'
                    WHEN b.booking_status = 'confirmed' AND b.payment_status = 'unpaid' THEN 'Awaiting payment'
                    WHEN b.booking_status = 'confirmed' AND b.payment_status = 'paid' THEN 'Confirmed - Ready for travel'
                    WHEN b.booking_status = 'cancelled' THEN 'Cancelled'
                    WHEN b.booking_status = 'completed' THEN 'Completed'
                    ELSE 'Processing'
                END as status_message
                FROM bookings b";

        if ($userId) {
            $sql .= " WHERE b.booking_number = ? AND b.user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$bookingNumber, $userId]);
        } else {
            $sql .= " WHERE b.booking_number = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$bookingNumber]);
        }

        return $stmt->fetch();
    }

    // Send password reset email
    public function sendPasswordReset($email)
    {
        $user = $this->getUserByEmail($email);
        if (!$user) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete old tokens
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        // Insert new token
        $stmt = $this->pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expiresAt]);

        // Return token for email link
        return $token;
    }

    // Verify reset token
    public function verifyResetToken($token)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    // Reset password
    public function resetPassword($token, $newPassword)
    {
        $reset = $this->verifyResetToken($token);
        if (!$reset) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update user password
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $reset['email']]);

        // Mark token as used
        $stmt = $this->pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);

        return true;
    }

    // Update booking after payment
    public function updateBookingPayment($bookingNumber, $paymentMethod)
    {
        $stmt = $this->pdo->prepare("UPDATE bookings SET payment_status = 'paid', payment_method = ?, booking_status = 'confirmed' WHERE booking_number = ?");
        return $stmt->execute([$paymentMethod, $bookingNumber]);
    }
}

// Initialize Auth class
$auth = new Auth($pdo);

// Function to require login
function requireLogin()
{
    global $auth;
    if (!$auth->isLoggedIn()) {
        header('Location: User Account/login.php');
        exit;
    }
}
?>
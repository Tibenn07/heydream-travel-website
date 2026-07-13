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
        // 1. Mark completed: travel_documents Prepared(1) or Not Applicable(2), payment_status = 'paid', visa_status = 'APPROVED' or 'N/A'
        $pdo->exec("
            UPDATE bookings
            SET booking_status = 'completed',
                ready_for_travel = 1
            WHERE booking_status != 'completed'
              AND booking_status != 'cancelled'
              AND travel_documents IN (1, 2)
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
                  travel_documents IN (1, 2)
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

// Parse a partner_profiles.social_media_links value into its structured form.
// Stored as JSON: {"facebook":"","tiktok":"","x":"","youtube":"","instagram":"","other":["url", ...]}
// Falls back to treating older, pre-JSON values (one raw link per line) as "other" links.
function parseSocialLinks($raw)
{
    $defaults = ['facebook' => '', 'tiktok' => '', 'x' => '', 'youtube' => '', 'instagram' => '', 'other' => []];
    $raw = trim($raw ?? '');
    if ($raw === '') {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded) && (array_key_exists('facebook', $decoded) || array_key_exists('other', $decoded))) {
        $decoded['other'] = is_array($decoded['other'] ?? null) ? array_values(array_filter($decoded['other'])) : [];
        return array_merge($defaults, $decoded);
    }

    // Legacy plain-text value: some old rows even stored a literal backslash-n instead of a real newline.
    $raw = str_replace('\\n', "\n", $raw);
    $links = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $raw))));
    $defaults['other'] = $links;
    return $defaults;
}

// Identify a social platform from a URL for display purposes (name + FontAwesome brand icon).
// Used for the free-form "Other Links" bucket, where the platform isn't already known.
function detectSocialPlatform($url)
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host);

    $known = [
        'discord.gg' => ['Discord', 'fa-brands fa-discord'],
        'discord.com' => ['Discord', 'fa-brands fa-discord'],
        'linkedin.com' => ['LinkedIn', 'fa-brands fa-linkedin'],
        'pinterest.com' => ['Pinterest', 'fa-brands fa-pinterest'],
        'threads.net' => ['Threads', 'fa-brands fa-threads'],
        'snapchat.com' => ['Snapchat', 'fa-brands fa-snapchat'],
        'wa.me' => ['WhatsApp', 'fa-brands fa-whatsapp'],
        'whatsapp.com' => ['WhatsApp', 'fa-brands fa-whatsapp'],
        't.me' => ['Telegram', 'fa-brands fa-telegram'],
        'telegram.me' => ['Telegram', 'fa-brands fa-telegram'],
        'telegram.org' => ['Telegram', 'fa-brands fa-telegram'],
        'reddit.com' => ['Reddit', 'fa-brands fa-reddit'],
        'twitch.tv' => ['Twitch', 'fa-brands fa-twitch'],
        'viber.com' => ['Viber', 'fa-brands fa-viber'],
        'line.me' => ['Line', 'fa-brands fa-line'],
        'vimeo.com' => ['Vimeo', 'fa-brands fa-vimeo'],
        'medium.com' => ['Medium', 'fa-brands fa-medium'],
        'github.com' => ['GitHub', 'fa-brands fa-github'],
        'wechat.com' => ['WeChat', 'fa-brands fa-weixin'],
        'weibo.com' => ['Weibo', 'fa-brands fa-weibo'],
    ];

    foreach ($known as $domain => $info) {
        if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -strlen($domain) - 1) === '.' . $domain)) {
            return $info;
        }
    }

    $parts = explode('.', $host);
    $label = !empty($parts[0]) ? ucfirst($parts[0]) : 'Link';
    return [$label, 'fa-solid fa-link'];
}

// Check whether an email address's domain can actually receive mail, catching typo'd
// or made-up domains (e.g. "gmial.com") at registration time. Checks real DNS records
// (MX, falling back to A/AAAA for domains that receive mail without a dedicated MX
// record) rather than an SMTP handshake -- many hosts (including shared hosting like
// Hostinger) block outbound port 25, so an SMTP probe would be unreliable in production.
// This can't confirm the specific mailbox exists, only that the domain is real and
// mail-capable; combined with the verification email link, that's enough to catch the
// vast majority of fake/typo'd addresses without needing a paid verification API.
function emailDomainAcceptsMail($email)
{
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        return false;
    }
    $domain = substr($email, $atPos + 1);
    if ($domain === '') {
        return false;
    }

    // Convert internationalized domains to ASCII so checkdnsrr() can resolve them.
    if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7E]/', $domain)) {
        $ascii = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
        if ($ascii !== false) {
            $domain = $ascii;
        }
    }

    if (checkdnsrr($domain, 'MX')) {
        return true;
    }
    // Some domains accept mail directly on their A/AAAA record with no MX entry.
    return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
}

// Reject addresses that are the wrong shape for their own provider, e.g. a 37-character
// Gmail local part -- Gmail usernames are always 6-30 characters, letters/numbers/dots
// only, no leading/trailing/double dots. This runs before any network check, so it
// catches obviously-fake addresses even when a live mailbox probe isn't possible.
function emailLooksValidForProvider($email)
{
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        return false;
    }
    $local = substr($email, 0, $atPos);
    $domain = strtolower(substr($email, $atPos + 1));

    if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
        $len = strlen($local);
        if ($len < 6 || $len > 30) {
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9.]+$/', $local)) {
            return false;
        }
        if ($local[0] === '.' || substr($local, -1) === '.' || strpos($local, '..') !== false) {
            return false;
        }
    }

    return true;
}

// Attempt a real-time SMTP mailbox check: connect to the domain's mail server and ask
// it (via RCPT TO, without actually sending anything) whether the specific mailbox
// exists. Returns true (exists), false (server explicitly rejected it -- e.g. "550 no
// such user"), or null when the answer is inconclusive (connection blocked/refused,
// timed out, or the server gave an ambiguous response). Many hosts, including some
// shared hosting, block outbound port 25, so this fails open: null is treated as
// "couldn't verify" rather than "invalid" by the caller, and only a definitive
// rejection blocks registration.
function emailMailboxLikelyExists($email)
{
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        return null;
    }
    $domain = substr($email, $atPos + 1);

    $mxHosts = [];
    if (getmxrr($domain, $mxHosts, $mxWeights)) {
        array_multisort($mxWeights, $mxHosts);
    } else {
        $mxHosts = [$domain];
    }
    // Only the top-priority host, and a short timeout below -- this runs synchronously
    // during registration, so a blocked/slow network must fail open quickly rather than
    // stall the form for several seconds.
    $mxHosts = array_slice($mxHosts, 0, 1);

    foreach ($mxHosts as $host) {
        $result = probeSmtpMailbox($host, $email);
        if ($result !== null) {
            return $result;
        }
    }

    return null;
}

// Single SMTP conversation against one mail server. Kept short-timeout and
// best-effort: any hiccup (blocked port, slow server, protocol surprise) returns null
// rather than risking a false rejection.
function probeSmtpMailbox($host, $email)
{
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, 25, $errno, $errstr, 2.5);
    if (!$socket) {
        return null;
    }
    stream_set_timeout($socket, 2.5);

    $read = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 512)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $meta = stream_get_meta_data($socket);
        if ($meta['timed_out']) {
            return null;
        }
        return $data;
    };
    $expect = function ($response, $code) {
        return $response !== null && strpos($response, $code) === 0;
    };

    $banner = $read();
    if (!$expect($banner, '220')) {
        fclose($socket);
        return null;
    }

    fwrite($socket, "HELO heydreamtravel.com\r\n");
    if (!$expect($read(), '250')) {
        fclose($socket);
        return null;
    }

    fwrite($socket, "MAIL FROM:<verify@heydreamtravel.com>\r\n");
    if (!$expect($read(), '250')) {
        fclose($socket);
        return null;
    }

    fwrite($socket, "RCPT TO:<$email>\r\n");
    $rcptResponse = $read();

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    if ($rcptResponse === null) {
        return null;
    }
    if (strpos($rcptResponse, '250') === 0 || strpos($rcptResponse, '251') === 0) {
        return true;
    }
    if (strpos($rcptResponse, '550') === 0 || strpos($rcptResponse, '551') === 0 || strpos($rcptResponse, '553') === 0) {
        return false;
    }

    return null; // 4xx / greylisted / anything else -- inconclusive
}

// Check if admin is logged in (for admin pages)
function isAdminLoggedIn()
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Self-healing schema check for the AI Live Chat typing indicators. These
// columns only exist on this local database because they were added by hand
// during development -- there was no migration in the live request path, so
// any other database (including production) is missing them. Without this,
// admin/ai_chat_admin.php's message-fetch throws on the typing-status lookup
// AFTER already fetching the messages, and its catch block returns
// success:false without the messages -- so the admin's chat panel silently
// never shows what the customer typed, even though it saved correctly.
function ensureAiChatTypingColumns($pdo)
{
    static $checked = false;
    if ($checked) {
        return;
    }

    try {
        $existing = [];
        foreach ($pdo->query("SHOW COLUMNS FROM ai_chat_sessions") as $row) {
            $existing[$row['Field']] = true;
        }
        if (!isset($existing['customer_last_typing'])) {
            $pdo->exec("ALTER TABLE ai_chat_sessions ADD COLUMN customer_last_typing DATETIME NULL");
        }
        if (!isset($existing['admin_last_typing'])) {
            $pdo->exec("ALTER TABLE ai_chat_sessions ADD COLUMN admin_last_typing DATETIME NULL");
        }
        $checked = true;
    } catch (PDOException $e) {
        // ai_chat_sessions table itself doesn't exist yet; nothing to heal.
    }
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

    // One-time, self-running migration: an account that already logged in successfully
    // in the past (before this login page started requiring email verification) must
    // not suddenly get locked out on deploy. Grandfathering is scoped to accounts with
    // real prior login history (a row in user_sessions) -- NOT "whatever happens to be
    // unverified when this first runs" -- so a brand-new signup that registers around
    // the same time and genuinely hasn't clicked their verification link is never swept
    // in; register() never creates a session until verifyEmail() succeeds, so an
    // unverified account can only have session history if it logged in under the old,
    // no-verification-required flow. Not date-based, so this also works for anyone else
    // deploying this codebase with their own pre-existing users. Runs once (tracked via
    // app_settings) then never touches email_verified again.
    private function ensureEmailVerificationGrandfathered()
    {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value VARCHAR(255) DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            $stmt = $this->pdo->prepare("SELECT 1 FROM app_settings WHERE setting_key = 'email_verification_grandfathered' LIMIT 1");
            $stmt->execute();
            if ($stmt->fetch()) {
                return; // already ran on this database
            }

            $cols = [];
            foreach ($this->pdo->query("SHOW COLUMNS FROM users") as $row) {
                $cols[$row['Field']] = true;
            }
            if (!isset($cols['email_verified'])) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE");
            }
            if (!isset($cols['verification_token'])) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) DEFAULT NULL");
            }

            // Only grandfather accounts with proof of a real prior login (a user_sessions
            // row). Unverified accounts with no session history are genuinely new and
            // must still verify -- they are left untouched.
            $this->pdo->exec("
                UPDATE users
                SET email_verified = 1
                WHERE (email_verified = 0 OR email_verified IS NULL)
                AND id IN (SELECT DISTINCT user_id FROM user_sessions)
            ");

            $this->pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('email_verification_grandfathered', '1')")->execute();
        } catch (PDOException $e) {
            error_log('email_verification grandfather migration failed: ' . $e->getMessage());
        }
    }

    // Login user
    public function login($email, $password)
    {
        $this->ensureEmailVerificationGrandfathered();
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

        if (empty($user['email_verified'])) {
            return ['success' => false, 'needs_verification' => true, 'email' => $user['email'], 'message' => 'Please verify your email address before signing in. Check your inbox for the verification link.'];
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
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/google_oauth.php';
require_once __DIR__ . '/../config/email_functions.php';

// Simple OAuth callback handler for Google
if (isset($_GET['error'])) {
    // User denied or error
    header('Location: login.php?error=' . urlencode($_GET['error']));
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: login.php?error=missing_code');
    exit;
}


$code = $_GET['code'];

// Compute redirect URI at runtime if not set in config
$redirectUri = GOOGLE_OAUTH_REDIRECT;
if (empty($redirectUri)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['REQUEST_URI']);
    $redirectUri = $protocol . $host . rtrim($path, '/') . '/oauth_callback.php';
}

// Exchange code for tokens
$tokenEndpoint = 'https://oauth2.googleapis.com/token';
$postFields = http_build_query([
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
]);

$ch = curl_init($tokenEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$response) {
    header('Location: login.php?error=token_request_failed');
    exit;
}

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    header('Location: login.php?error=invalid_token_response');
    exit;
}

$accessToken = $tokenData['access_token'];

// Fetch user info
$userinfoEndpoint = 'https://openidconnect.googleapis.com/v1/userinfo';
$ch = curl_init($userinfoEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
$userinfoResp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$userinfoResp) {
    header('Location: login.php?error=userinfo_failed');
    exit;
}

$userInfo = json_decode($userinfoResp, true);

$email = $userInfo['email'] ?? null;
$name = $userInfo['name'] ?? ($userInfo['given_name'] ?? '');
$providerId = $userInfo['sub'] ?? null;

if (!$email || !$providerId) {
    header('Location: login.php?error=incomplete_user_info');
    exit;
}

// Use Auth socialLogin helper
$result = $auth->socialLogin($email, $name, 'google', $providerId);

if ($result['success']) {
    // If an existing user, socialLogin already created a session.
    if (isset($result['user'])) {
        header('Location: profile.php');
        exit;
    }

    // Otherwise a new user was created via register — create session and mark email verified
    if (isset($result['user_id'])) {
        $userId = $result['user_id'];
        try {
            $stmt = $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            // ignore
        }
        $auth->createSession($userId);
        // send notifications
        sendLoginNotifications($userId, 'google');
        header('Location: profile.php');
        exit;
    }
}

// Fallback: redirect back to login with message
header('Location: login.php?error=' . urlencode($result['message'] ?? 'oauth_failed'));
exit;

?>

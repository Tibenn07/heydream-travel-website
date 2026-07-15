<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/firebase_config.php';
require_once __DIR__ . '/../config/email_functions.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$idToken = $input['id_token'] ?? null;
if (!$idToken) {
    echo json_encode(['success' => false, 'message' => 'Missing id_token']);
    exit;
}

$redirect = $input['redirect'] ?? '../index.php';
// Simple validation to prevent open redirect vulnerabilities (mirrors login.php)
if (strpos($redirect, 'http') === 0 && strpos($redirect, $_SERVER['HTTP_HOST']) === false) {
    $redirect = '../index.php';
}

// Verify ID token via Google's tokeninfo endpoint using cURL
$tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
$ch = curl_init($tokenInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$resp = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $httpCode !== 200) {
    $message = 'Token verification failed';
    if ($curlError) {
        $message .= ': ' . $curlError;
    } elseif ($resp) {
        $decodedError = json_decode($resp, true);
        if (isset($decodedError['error_description'])) {
            $message .= ': ' . $decodedError['error_description'];
        } elseif (isset($decodedError['error'])) {
            $message .= ': ' . $decodedError['error'];
        } else {
            $message .= ': ' . trim($resp);
        }
    }
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$info = json_decode($resp, true);
if (!isset($info['aud'])) {
    $error = is_array($info) ? json_encode($info) : $resp;
    echo json_encode(['success' => false, 'message' => 'Invalid token response: ' . $error]);
    exit;
}

// Check audience matches your web client ID (allow either Firebase client id or Google client id if set)
$validAudiences = array_filter([defined('FIREBASE_CLIENT_ID') ? FIREBASE_CLIENT_ID : null, defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : null]);
if (!in_array($info['aud'], $validAudiences)) {
    echo json_encode(['success' => false, 'message' => 'Token audience mismatch']);
    exit;
}

$email = $info['email'] ?? null;
$name = $info['name'] ?? ($info['given_name'] ?? '');
$first_name = '';
if (!empty($name)) {
    $parts = preg_split('/\s+/', trim($name));
    $first_name = $parts[0] ?? '';
}
$sub = $info['sub'] ?? null; // provider user id

if (!$email || !$sub) {
    echo json_encode(['success' => false, 'message' => 'Incomplete user info']);
    exit;
}

// Use existing Auth helper to sign in or register
$result = $auth->socialLogin($email, $name, 'google', $sub);

if ($result['success']) {
    if (isset($result['user'])) {
        // EXISTING user — already in the database, go straight to homepage
        $user = $result['user'];
        sendLoginNotifications($user['id'], 'google');
        // If profile is incomplete, force them to complete-profile page (mirrors login.php)
        if (empty($user['country']) && empty($user['dob']) && empty($user['title'])) {
            $redirect = 'complete-profile.php';
        }
        echo json_encode(['success' => true, 'redirect' => $redirect, 'name' => $user['full_name'] ?? $name, 'first_name' => $user['first_name'] ?? $first_name, 'is_new' => false]);
        exit;
    }

    if (isset($result['user_id'])) {
        // NEW user just registered — send to complete profile
        $userId = $result['user_id'];
        try {
            $stmt = $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            // ignore
        }
        $auth->createSession($userId);
        sendLoginNotifications($userId, 'google');
        echo json_encode(['success' => true, 'redirect' => 'complete-profile.php', 'name' => $name, 'first_name' => $first_name, 'is_new' => true]);
        exit;
    }

    // Fallback for existing user via session already created
    echo json_encode(['success' => true, 'redirect' => $redirect, 'name' => $name, 'first_name' => $first_name, 'is_new' => false]);
    exit;
}

echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Social login failed']);
exit;

?>
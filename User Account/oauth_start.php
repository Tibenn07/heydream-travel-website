<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/google_oauth.php';

$provider = $_GET['provider'] ?? '';

if ($provider !== 'google') {
    header('Location: login.php');
    exit;
}

// Compute redirect URI at runtime if not explicitly set in config
$redirectUri = GOOGLE_OAUTH_REDIRECT;
if (empty($redirectUri)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['REQUEST_URI']);
    $redirectUri = $protocol . $host . rtrim($path, '/') . '/oauth_callback.php';
}

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => GOOGLE_OAUTH_SCOPE,
    'access_type' => 'offline',
    'prompt' => 'select_account consent'
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;

?>

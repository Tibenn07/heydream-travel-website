<?php
// config/google_oauth.php
// Google OAuth configuration — set these values from Google Cloud Console
// Create an OAuth 2.0 Client ID (External) and set the authorized redirect URI

// Example values (replace with your own):
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
// Set the redirect URI to the full URL for oauth_callback.php in your environment.
// e.g. 'http://localhost/HeyDream Website - anti gravity 12.1/User Account/oauth_callback.php'
define('GOOGLE_OAUTH_REDIRECT', '');

// Scopes used for sign-in
define('GOOGLE_OAUTH_SCOPE', 'openid email profile');

// NOTE: If you leave GOOGLE_OAUTH_REDIRECT empty the start/callback scripts
// will attempt to compute a reasonable redirect URI at runtime. It's
// recommended to set this explicitly to the exact redirect URI you register
// in Google Cloud Console to avoid mismatches.

?>

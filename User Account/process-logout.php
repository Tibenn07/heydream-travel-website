<?php
require_once __DIR__ . '/../config/database.php';  // CHANGE THIS LINE

// Perform logout using the Auth class
$auth->logout();

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Redirect to home page with success message
header('Location: ../index.php?logout=success');
exit;
?>

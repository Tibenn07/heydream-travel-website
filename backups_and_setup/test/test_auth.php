<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Auth System Test</h2>";

if (isset($auth)) {
    echo "✅ Auth class initialized successfully<br>";
    echo "Logged in: " . ($auth->isLoggedIn() ? "Yes" : "No") . "<br>";
    
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        echo "User: " . $user['full_name'] . "<br>";
    }
} else {
    echo "❌ Auth class NOT initialized<br>";
}

echo "<br><a href='register.php'>Go to Register</a> | ";
echo "<a href='login.php'>Go to Login</a>";
?>

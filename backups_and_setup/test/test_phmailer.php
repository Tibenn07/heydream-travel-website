<?php
echo "<h2>PHPMailer Test</h2>";

// Check if PHPMailer folder exists
$phpmailerPath = __DIR__ . '/../PHPMailer/PHPMailer.php';
if (file_exists($phpmailerPath)) {
    echo "✅ PHPMailer found at: " . $phpmailerPath . "<br>";
} else {
    echo "❌ PHPMailer NOT found at: " . $phpmailerPath . "<br>";
    echo "Please download PHPMailer and place it in the 'PHPMailer' folder.<br>";
}

// Check if config file exists
$configPath = __DIR__ . '/../config/email_config.php';
if (file_exists($configPath)) {
    echo "✅ email_config.php found<br>";
} else {
    echo "❌ email_config.php NOT found<br>";
}

// Try to include and test
if (file_exists($phpmailerPath) && file_exists($configPath)) {
    echo "<hr>";
    echo "<h3>Testing Email Configuration...</h3>";
    
    require_once __DIR__ . '/../config/email_config.php';
    
    // Test with a dummy email (won't actually send)
    $result = sendPasswordResetEmail('test@example.com', 'Test User', 'http://localhost/test');
    
    if ($result['success']) {
        echo "✅ Email configuration looks good!<br>";
    } else {
        echo "❌ Email error: " . $result['message'] . "<br>";
        echo "<p><strong>Note:</strong> This might be because you haven't configured your Gmail credentials yet.</p>";
    }
}
?>

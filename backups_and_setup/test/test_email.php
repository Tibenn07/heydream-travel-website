<?php
require_once __DIR__ . '/../config/email_config.php';

echo "<h2>Email Test</h2>";

// Test with your email address
$testEmail = 'heydreamtravelandtours@gmail.com'; // CHANGE THIS TO YOUR EMAIL
$testName = 'Steven Rebancos';
$testLink = 'http://localhost/HeyDream%20Website/reset-password.php?token=test123';

echo "Sending test email to: $testEmail<br>";

$result = sendPasswordResetEmail($testEmail, $testName, $testLink);

if ($result['success']) {
    echo "✅ Email sent successfully! Check your inbox.<br>";
} else {
    echo "❌ Email failed: " . $result['message'] . "<br>";
}
?>

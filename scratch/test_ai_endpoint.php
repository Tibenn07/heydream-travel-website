<?php
// Simulate post request to ai_chat.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['message'] = 'What packages do you have for Japan?';
$_POST['sessionId'] = 'test-session-123';

echo "Simulating POST request to inquiry/ai_chat.php...\n";
ob_start();
include __DIR__ . '/../inquiry/ai_chat.php';
$output = ob_get_clean();

echo "Response output:\n";
echo $output . "\n";
?>

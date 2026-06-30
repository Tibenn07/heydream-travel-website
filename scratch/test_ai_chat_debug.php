<?php
// Set request arguments
$input = [
    'message' => 'What destinations do you offer?',
    'session_id' => 'test-debug-' . uniqid()
];
$GLOBALS['argv'][1] = json_encode($input);

echo "Starting debug tracing of ai_chat.php...\n";

// Disable headers to prevent warnings
ob_start();
include __DIR__ . '/../inquiry/ai_chat.php';
$output = ob_get_clean();

echo "Raw output returned by ai_chat.php:\n";
echo $output . "\n";
?>

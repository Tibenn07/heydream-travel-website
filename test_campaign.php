<?php
// Temporarily bypass session check for debugging
session_start();
$_SESSION['admin_logged_in'] = true;

// Simulate the POST request
$_POST['action'] = 'send_campaign';
$_POST['subject'] = 'Test Subject';
$_POST['audience'] = 'website';
$_POST['blocks'] = json_encode([
    ['type' => 'text', 'text' => 'Hello', 'align' => 'left', 'size' => '16', 'color' => '#000', 'weight' => '400']
]);

// Capture output
ob_start();
include __DIR__ . '/admin/api/marketing_api.php';
$output = ob_get_clean();

echo "=== RAW OUTPUT ===\n";
echo $output . "\n";
echo "=== JSON DECODE ===\n";
$decoded = json_decode($output, true);
if ($decoded === null) {
    echo "INVALID JSON! json_last_error: " . json_last_error_msg() . "\n";
} else {
    print_r($decoded);
}

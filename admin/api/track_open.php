<?php
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once __DIR__ . '/../../config/database.php';

$campaign_id = $_GET['cid'] ?? null;
$email = $_GET['e'] ?? 'unknown';

if ($campaign_id) {
    try {
        // Increment open_count
        $stmt = $pdo->prepare("UPDATE marketing_campaigns SET open_count = open_count + 1 WHERE id = ?");
        $stmt->execute([$campaign_id]);
        
        // Log the open for debugging
        $logEntry = date('Y-m-d H:i:s') . " - Open recorded for Campaign #$campaign_id (Email: $email)\n";
        file_put_contents(__DIR__ . '/tracking.log', $logEntry, FILE_APPEND);
        
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/tracking.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;

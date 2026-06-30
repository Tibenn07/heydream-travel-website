<?php
// track_click.php - Tracks when a user clicks a link in the email
require_once __DIR__ . '/../../config/database.php';

$campaign_id = $_GET['cid'] ?? null;
$url = $_GET['url'] ?? 'https://heydreamtravel.kesug.com/';
$email = $_GET['e'] ?? 'unknown';

if ($campaign_id) {
    try {
        // 1. Increment open_count (if not already opened, though we can just increment)
        // Usually a click implies an open
        $stmt = $pdo->prepare("UPDATE marketing_campaigns SET open_count = open_count + 1 WHERE id = ?");
        $stmt->execute([$campaign_id]);
        
        // 2. Increment click_count
        $stmt = $pdo->prepare("UPDATE marketing_campaigns SET click_count = click_count + 1 WHERE id = ?");
        $stmt->execute([$campaign_id]);
        
    } catch (Exception $e) {}
}

// Redirect to the actual destination
header("Location: " . $url);
exit;
?>

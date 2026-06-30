<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$convo_id = $_GET['convo_id'] ?? null;
if (!$convo_id) {
    echo json_encode(['error' => 'No conversation ID']);
    exit;
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_customer = isset($_SESSION['chat_convo_id']) && $_SESSION['chat_convo_id'] == $convo_id;

if (!$is_admin && !$is_customer) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Handle status update (Typing)
    if (isset($input['typing'])) {
        $typingField = $is_admin ? 'admin_last_typing' : 'customer_last_typing';
        $val = $input['typing'] ? 'NOW()' : 'NULL';
        $pdo->query("UPDATE customer_conversations SET $typingField = $val WHERE id = " . intval($convo_id));
        echo json_encode(['success' => true]);
        exit;
    }
}

// Fetch new messages
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

$stmt = $pdo->prepare("SELECT * FROM customer_messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC");
$stmt->execute([$convo_id, $last_id]);
$new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch typing status of the OTHER party
$stmt = $pdo->prepare("SELECT admin_last_typing, customer_last_typing FROM customer_conversations WHERE id = ?");
$stmt->execute([$convo_id]);
$typingInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$other_is_typing = false;
if ($typingInfo) {
    $typingTime = $is_admin ? $typingInfo['customer_last_typing'] : $typingInfo['admin_last_typing'];
    if ($typingTime) {
        $diff = time() - strtotime($typingTime);
        if ($diff < 5) { // If typed within last 5 seconds
            $other_is_typing = true;
        }
    }
}

// Mark messages as read based on who is fetching
if ($is_admin && count($new_messages) > 0) {
    $pdo->prepare("UPDATE customer_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'Customer'")->execute([$convo_id]);
}

$formatted_messages = [];
foreach ($new_messages as $m) {
    $m['formatted_time'] = date('g:i A', strtotime($m['created_at']));
    // Determine CSS class based on viewer
    if ($is_admin) {
        $m['css_class'] = ($m['sender_type'] === 'Customer') ? 'customer' : 'admin';
    } else {
        $m['css_class'] = ($m['sender_type'] === 'Customer') ? 'msg-customer' : 'msg-admin';
    }
    $m['safe_message'] = nl2br(htmlspecialchars($m['message']));
    $formatted_messages[] = $m;
}

echo json_encode([
    'messages' => $formatted_messages,
    'other_is_typing' => $other_is_typing,
    'last_id' => end($new_messages)['id'] ?? $last_id
]);

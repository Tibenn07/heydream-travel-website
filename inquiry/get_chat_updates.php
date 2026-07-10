<?php
// inquiry/get_chat_updates.php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$sessionId = $_GET['session_id'] ?? '';
$lastId = intval($_GET['last_id'] ?? 0);
$init = intval($_GET['init'] ?? 0);

if (empty($sessionId)) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

if (!$pdo) {
    echo json_encode(['success' => true, 'messages' => []]);
    exit;
}

ensureAiChatTypingColumns($pdo);

// Handle customer typing status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['typing'])) {
        $val = $input['typing'] ? 'NOW()' : 'NULL';
        $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET customer_last_typing = $val WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        echo json_encode(['success' => true]);
        exit;
    }
}

try {
    // Check if the admin is currently typing (within the last 5 seconds). Done
    // entirely in SQL (not PHP's strtotime/time()) so it can't be thrown off by
    // the app server and DB server having different timezones -- both sides of
    // the comparison always use the DB's own clock.
    $stmt = $pdo->prepare("SELECT admin_last_typing IS NOT NULL AND admin_last_typing >= (NOW() - INTERVAL 5 SECOND) AS is_typing FROM ai_chat_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $typingRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $adminIsTyping = $typingRow ? (bool) $typingRow['is_typing'] : false;

    if ($init === 1) {
        $stmt = $pdo->prepare("SELECT id, sender, message, timestamp AS created_at FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC");
        $stmt->execute([$sessionId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $maxId = 0;
        foreach ($messages as $msg) {
            if (intval($msg['id']) > $maxId) {
                $maxId = intval($msg['id']);
            }
        }
        echo json_encode(['success' => true, 'messages' => $messages, 'max_id' => $maxId, 'admin_is_typing' => $adminIsTyping]);
        exit;
    }

    // Fetch only messages not sent by the customer that are newer than lastId (includes admin and system notices)
    $stmt = $pdo->prepare("SELECT id, sender, message, timestamp AS created_at FROM ai_chat_messages WHERE session_id = ? AND id > ? AND sender != 'customer' ORDER BY id ASC");
    $stmt->execute([$sessionId, $lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages, 'admin_is_typing' => $adminIsTyping]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

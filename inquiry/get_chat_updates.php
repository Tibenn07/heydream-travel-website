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

try {
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
        echo json_encode(['success' => true, 'messages' => $messages, 'max_id' => $maxId]);
        exit;
    }

    // Fetch only messages not sent by the customer that are newer than lastId (includes admin and system notices)
    $stmt = $pdo->prepare("SELECT id, sender, message, timestamp AS created_at FROM ai_chat_messages WHERE session_id = ? AND id > ? AND sender != 'customer' ORDER BY id ASC");
    $stmt->execute([$sessionId, $lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($pdo === null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error - HeyDream Travel</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Poppins', sans-serif; background: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
            h1 { color: #ef4444; margin-bottom: 20px; font-size: 24px; }
            p { color: #64748b; line-height: 1.6; margin-bottom: 30px; }
            .btn { background: #003580; color: white; padding: 12px 30px; text-decoration: none; border-radius: 10px; font-weight: 600; display: inline-block; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⚠️ Database Connection Failed</h1>
            <p>We are unable to connect to the database. If this is the online hosting environment, please check and update your credentials in the <strong>config/database.php</strong> file.</p>
            <a href="login.php" class="btn">Return to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle Actions (Reply, Archive, Resolve)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'reply') {
        $convo_id = $_POST['conversation_id'];
        $message = trim($_POST['message']);
        if (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO customer_messages (conversation_id, sender_type, sender_name, message) VALUES (?, 'Admin', ?, ?)");
            $stmt->execute([$convo_id, $_SESSION['admin_username'] ?? 'Admin', $message]);
            
            $pdo->prepare("UPDATE customer_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convo_id]);
        }
        header("Location: messages.php?convo_id=$convo_id&status=" . ($_GET['status'] ?? 'Active'));
        exit;
    }
    
    if (isset($_POST['action']) && in_array($_POST['action'], ['Active', 'Archived', 'Resolved'])) {
        $pdo->prepare("UPDATE customer_conversations SET status = ? WHERE id = ?")->execute([$_POST['action'], $_POST['conversation_id']]);
        header("Location: messages.php?status=" . $_POST['action']);
        exit;
    }
}

// Mark active conversation as read
$activeConvoId = $_GET['convo_id'] ?? null;
if ($activeConvoId) {
    $pdo->prepare("UPDATE customer_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'Customer'")->execute([$activeConvoId]);
}

$statusFilter = $_GET['status'] ?? 'Active';
$searchQuery = $_GET['search'] ?? '';

$where = "status = :status";
$params = ['status' => $statusFilter];

if ($searchQuery) {
    $where .= " AND (customer_name LIKE :search OR customer_email LIKE :search)";
    $params['search'] = "%$searchQuery%";
}

$stmt = $pdo->prepare("
    SELECT c.*, 
    (SELECT message FROM customer_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) as last_message,
    (SELECT COUNT(*) FROM customer_messages WHERE conversation_id = c.id AND is_read = 0 AND sender_type = 'Customer') as unread_count
    FROM customer_conversations c 
    WHERE $where 
    ORDER BY c.updated_at DESC
");
$stmt->execute($params);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activeMessages = [];
if ($activeConvoId) {
    $stmt = $pdo->prepare("SELECT * FROM customer_messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$activeConvoId]);
    $activeMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4338ca;
            --primary-dark: #3730a3;
            --bg: #f8fafc;
            --sidebar-bg: #0f172a;
            --text-main: #1e293b;
            --card-bg: #ffffff;
        }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text-main); margin: 0; padding: 0; display: flex; height: 100vh; overflow: hidden; }
        
        .messages-container {
            flex: 1;
            display: flex;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin: 20px;
            border-radius: 12px;
            overflow: hidden;
        }

        .sidebar-list {
            width: 320px;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
        }
        
        .sidebar-header h2 { margin: 0 0 15px 0; font-size: 1.25rem; display: flex; align-items: center; justify-content: space-between; }
        
        .search-box {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            box-sizing: border-box;
        }
        
        .status-filters {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .status-filters a {
            font-size: 0.8rem;
            text-decoration: none;
            color: #64748b;
            padding: 5px 10px;
            border-radius: 12px;
            background: #f1f5f9;
        }
        
        .status-filters a.active {
            background: var(--primary);
            color: white;
        }

        .convo-list {
            flex: 1;
            overflow-y: auto;
        }

        .convo-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .convo-item:hover, .convo-item.active { background: #e0e7ff; }
        
        .convo-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .convo-name { font-weight: 600; font-size: 0.95rem; }
        .convo-type { font-size: 0.7rem; color: #475569; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; }
        
        .convo-snippet { font-size: 0.85rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .unread-badge {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header-info h3 { margin: 0; font-size: 1.1rem; }
        .chat-header-info p { margin: 2px 0 0; font-size: 0.8rem; color: #64748b; }
        
        .chat-actions form { display: inline; }
        .chat-actions button {
            background: #f1f5f9; border: 1px solid #cbd5e1;
            padding: 6px 12px; border-radius: 6px; cursor: pointer;
            margin-left: 5px;
        }
        .chat-actions button:hover { background: #e2e8f0; }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            position: relative;
        }
        
        .message.customer {
            align-self: flex-start;
            background: white;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 2px;
        }
        
        .message.admin {
            align-self: flex-end;
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 2px;
        }
        
        .message-time { font-size: 0.7rem; opacity: 0.8; margin-top: 5px; text-align: right; }
        .message-sender { font-size: 0.75rem; font-weight: 600; margin-bottom: 3px;}

        .chat-input {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            background: white;
        }
        
        .chat-input form { display: flex; gap: 10px; }
        .chat-input textarea {
            flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px;
            resize: none; outline: none; font-family: inherit; font-size: 0.95rem;
        }
        .chat-input button {
            background: var(--primary); color: white; border: none;
            padding: 0 24px; border-radius: 8px; cursor: pointer; font-weight: 600;
        }
        
        .back-to-dash { position: absolute; top: 20px; left: 20px; z-index: 100; text-decoration: none; color: #1e293b; font-weight: 600; background: white; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-to-dash"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    
    <div class="messages-container" style="margin-top: 70px;">
        <div class="sidebar-list">
            <div class="sidebar-header">
                <h2>Inbox</h2>
                <form action="" method="GET">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    <input type="text" name="search" class="search-box" placeholder="Search customer..." value="<?= htmlspecialchars($searchQuery) ?>">
                </form>
                <div class="status-filters">
                    <a href="?status=Active" class="<?= $statusFilter === 'Active' ? 'active' : '' ?>">Active</a>
                    <a href="?status=Archived" class="<?= $statusFilter === 'Archived' ? 'active' : '' ?>">Archived</a>
                    <a href="?status=Resolved" class="<?= $statusFilter === 'Resolved' ? 'active' : '' ?>">Resolved</a>
                </div>
            </div>
            
            <div class="convo-list">
                <?php if (empty($conversations)): ?>
                    <div style="padding: 20px; text-align: center; color: #64748b; font-size: 0.9rem;">No conversations found.</div>
                <?php endif; ?>
                
                <?php foreach ($conversations as $c): ?>
                    <a href="?convo_id=<?= $c['id'] ?>&status=<?= $statusFilter ?>&search=<?= urlencode($searchQuery) ?>" 
                       class="convo-item <?= $activeConvoId == $c['id'] ? 'active' : '' ?>">
                        <div class="convo-header">
                            <span class="convo-name"><?= htmlspecialchars($c['customer_name']) ?></span>
                            <span class="convo-type"><?= htmlspecialchars($c['message_type']) ?></span>
                        </div>
                        <div class="convo-snippet"><?= htmlspecialchars($c['customer_email']) ?></div>
                        <div class="convo-snippet" style="margin-top: 4px; font-style: italic;">
                            <?= htmlspecialchars(substr($c['last_message'] ?? 'No messages yet', 0, 40)) ?>...
                        </div>
                        
                        <?php if ($c['unread_count'] > 0): ?>
                            <span class="unread-badge"><?= $c['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="chat-area">
            <?php if ($activeConvoId): ?>
                <?php 
                    $curr = array_filter($conversations, fn($c) => $c['id'] == $activeConvoId);
                    $activeConvo = reset($curr);
                    // Fetch if not in list
                    if (!$activeConvo) {
                        $stmt = $pdo->prepare("SELECT * FROM customer_conversations WHERE id = ?");
                        $stmt->execute([$activeConvoId]);
                        $activeConvo = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                ?>
                <div class="chat-header">
                    <div class="chat-header-info">
                        <h3><?= htmlspecialchars($activeConvo['customer_name'] ?? 'Unknown Customer') ?></h3>
                        <p><?= htmlspecialchars($activeConvo['customer_email'] ?? '') ?> • <?= htmlspecialchars($activeConvo['message_type'] ?? '') ?> • Status: <strong><?= htmlspecialchars($activeConvo['status'] ?? '') ?></strong></p>
                    </div>
                    
                    <div class="chat-actions">
                        <?php if (($activeConvo['status'] ?? '') === 'Active'): ?>
                            <form action="" method="POST">
                                <input type="hidden" name="conversation_id" value="<?= $activeConvo['id'] ?>">
                                <input type="hidden" name="action" value="Resolved">
                                <button type="submit" title="Mark as Resolved"><i class="fas fa-check-circle" style="color:#16a34a;"></i> Resolve</button>
                            </form>
                            <form action="" method="POST">
                                <input type="hidden" name="conversation_id" value="<?= $activeConvo['id'] ?>">
                                <input type="hidden" name="action" value="Archived">
                                <button type="submit" title="Archive Chat"><i class="fas fa-archive" style="color:#64748b;"></i> Archive</button>
                            </form>
                        <?php else: ?>
                            <form action="" method="POST">
                                <input type="hidden" name="conversation_id" value="<?= $activeConvo['id'] ?>">
                                <input type="hidden" name="action" value="Active">
                                <button type="submit"><i class="fas fa-undo"></i> Move to Active</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($activeMessages)): ?>
                        <div style="text-align: center; color: #64748b; margin-top: 20px;">No messages yet.</div>
                    <?php endif; ?>
                    
                    <?php 
                        $last_id = 0;
                        foreach ($activeMessages as $m): 
                            $last_id = max($last_id, $m['id']);
                    ?>
                        <div class="message <?= $m['sender_type'] === 'Customer' ? 'customer' : 'admin' ?>">
                            <?php if ($m['sender_type'] !== 'Customer'): ?>
                                <div class="message-sender"><?= htmlspecialchars($m['sender_name']) ?></div>
                            <?php endif; ?>
                            <div class="message-body"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                            <div class="message-time"><?= date('g:i A', strtotime($m['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div id="typingIndicator" style="display: none; align-self: flex-start; color: var(--muted); font-size: 0.85rem; font-style: italic; margin-left: 10px;">
                        Customer is typing...
                    </div>
                </div>
                
                <div class="chat-input">
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="conversation_id" value="<?= $activeConvoId ?>">
                        <textarea name="message" rows="2" placeholder="Type a reply..." required></textarea>
                        <button type="submit">Send <i class="fas fa-paper-plane" style="margin-left: 5px;"></i></button>
                    </form>
                </div>
                
                <script>
                    const chatMessages = document.getElementById('chatMessages');
                    const typingIndicator = document.getElementById('typingIndicator');
                    const messageInput = document.querySelector('textarea[name="message"]');
                    let lastId = <?= $last_id ?? 0 ?>;
                    const convoId = <?= $activeConvoId ?>;
                    
                    function scrollToBottom() {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                    scrollToBottom();
                    
                    function fetchMessages() {
                        fetch(`../api/chat_sync.php?convo_id=${convoId}&last_id=${lastId}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.error) return;
                                
                                // Handle new messages
                                if (data.messages && data.messages.length > 0) {
                                    data.messages.forEach(m => {
                                        const div = document.createElement('div');
                                        div.className = 'message ' + m.css_class;
                                        
                                        let innerHtml = '';
                                        if (m.sender_type !== 'Customer') {
                                            innerHtml += `<div class="message-sender">${m.sender_name}</div>`;
                                        }
                                        innerHtml += `<div class="message-body">${m.safe_message}</div>`;
                                        innerHtml += `<div class="message-time">${m.formatted_time}</div>`;
                                        
                                        div.innerHTML = innerHtml;
                                        chatMessages.insertBefore(div, typingIndicator);
                                    });
                                    lastId = data.last_id;
                                    scrollToBottom();
                                }
                                
                                // Handle typing indicator
                                if (data.other_is_typing) {
                                    typingIndicator.style.display = 'block';
                                    scrollToBottom();
                                } else {
                                    typingIndicator.style.display = 'none';
                                }
                            })
                            .catch(err => console.error(err));
                    }
                    
                    setInterval(fetchMessages, 2000);
                    
                    // Typing detection
                    let typingTimeout;
                    if (messageInput) {
                        messageInput.addEventListener('input', () => {
                            fetch('../api/chat_sync.php?convo_id=' + convoId, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ typing: true })
                            });
                            
                            clearTimeout(typingTimeout);
                            typingTimeout = setTimeout(() => {
                                fetch('../api/chat_sync.php?convo_id=' + convoId, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ typing: false })
                                });
                            }, 2000);
                        });
                    }
                </script>
            <?php else: ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8;">
                    <i class="fas fa-comments" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h2>Select a conversation</h2>
                    <p>Choose a conversation from the sidebar to start chatting.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

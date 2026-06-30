<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Handle email promotion redirection
if (isset($_GET['email']) && isset($_GET['name'])) {
    $email = trim($_GET['email']);
    $name = trim($_GET['name']);
    $type = isset($_GET['type']) ? trim($_GET['type']) : 'General Chat';
    
    // Ensure valid type
    $allowed_types = ['Tour Package Inquiry', 'Flight Booking', 'Visa Assistance', 'General Chat'];
    if (!in_array($type, $allowed_types)) {
        $type = 'General Chat';
    }
    
    // Find active conversation for this email and type, or create one
    $stmt = $pdo->prepare("SELECT id FROM customer_conversations WHERE customer_email = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $_SESSION['chat_convo_id'] = $existing['id'];
        $_SESSION['chat_email'] = $email;
        $_SESSION['chat_name'] = $name;
    } else {
        // Create new conversation
        $stmt = $pdo->prepare("INSERT INTO customer_conversations (customer_email, customer_name, message_type) VALUES (?, ?, ?)");
        $stmt->execute([$email, $name, $type]);
        
        $_SESSION['chat_convo_id'] = $pdo->lastInsertId();
        $_SESSION['chat_email'] = $email;
        $_SESSION['chat_name'] = $name;
    }
    
    // Redirect to clear URL params
    header('Location: chat.php');
    exit;
}

// Redirect back if nothing set
if (!isset($_SESSION['chat_convo_id'])) {
    // Or just show a nice "no active chat" page, but we'll try to just show empty state
    // header('Location: index.php');
    // exit;
}

$convo_id = $_SESSION['chat_convo_id'] ?? null;
$messages = [];
$convoInfo = null;

if ($convo_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
        $msg = trim($_POST['message']);
        if (!empty($msg)) {
            $stmt = $pdo->prepare("INSERT INTO customer_messages (conversation_id, sender_type, sender_name, message) VALUES (?, 'Customer', ?, ?)");
            $stmt->execute([$convo_id, $_SESSION['chat_name'], $msg]);
            
            $pdo->prepare("UPDATE customer_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convo_id]);
        }
        header("Location: chat.php");
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM customer_conversations WHERE id = ?");
    $stmt->execute([$convo_id]);
    $convoInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM customer_messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$convo_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark admin messages as read (optional, if we track it)
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Support - HeyDream Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --bg: #f3f4f6;
            --white: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
        }
        body { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background-color: var(--bg); display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        
        .chat-container {
            width: 100%;
            max-width: 500px;
            height: 90vh;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .agent-info { display: flex; align-items: center; gap: 15px; }
        .agent-avatar { width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .agent-details h2 { margin: 0; font-size: 1.1rem; font-weight: 600; }
        .agent-details p { margin: 2px 0 0; font-size: 0.8rem; opacity: 0.9; }
        .chat-badge { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; margin-top: 5px; display: inline-block; }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message { max-width: 75%; padding: 12px 16px; border-radius: 18px; position: relative; font-size: 0.95rem; line-height: 1.4; }
        
        .msg-customer {
            align-self: flex-end;
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .msg-admin {
            align-self: flex-start;
            background: white;
            color: var(--text);
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 4px;
        }
        
        .msg-sender { font-size: 0.75rem; font-weight: 600; margin-bottom: 4px; color: var(--primary); }
        .msg-customer .msg-sender { display: none; }
        .msg-time { font-size: 0.7rem; margin-top: 5px; opacity: 0.7; text-align: right; }

        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        
        .chat-input form { display: flex; gap: 10px; align-items: center; }
        
        .chat-input textarea {
            flex: 1;
            border: 1px solid #d1d5db;
            padding: 12px 15px;
            border-radius: 20px;
            resize: none;
            outline: none;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .chat-input textarea:focus { border-color: var(--primary); }
        
        .send-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .send-btn:hover { transform: scale(1.05); }

        .sys-msg { text-align: center; color: var(--muted); font-size: 0.8rem; margin: 10px 0; }
        
        .home-link { color: white; text-decoration: none; font-size: 1.2rem; }
    </style>
</head>
<body>

    <?php if (!$convo_id): ?>
        <div class="chat-container" style="justify-content: center; align-items: center; padding: 40px; box-sizing: border-box;">
            <i class="fas fa-comments" style="font-size: 50px; color: var(--primary); margin-bottom: 20px;"></i>
            <h2 style="color: var(--text); margin-bottom: 10px;">Start a Chat</h2>
            <p style="color: var(--muted); text-align: center; font-size: 0.9rem; margin-bottom: 30px;">Please enter your details below to start chatting with our support team.</p>
            
            <form action="chat.php" method="GET" style="width: 100%; display: flex; flex-direction: column; gap: 15px;">
                <input type="text" name="name" placeholder="Your Full Name" required 
                       style="padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit;">
                
                <input type="email" name="email" placeholder="Your Email Address" required 
                       style="padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit;">
                
                <select name="type" style="padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; color: var(--text);">
                    <option value="General Chat">General Chat</option>
                    <option value="Tour Package Inquiry">Tour Package Inquiry</option>
                    <option value="Flight Booking">Flight Booking</option>
                    <option value="Visa Assistance">Visa Assistance</option>
                </select>
                
                <button type="submit" style="background: var(--primary); color: white; padding: 14px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px;">Start New Chat</button>
            </form>
        </div>
    <?php else: ?>
        <div class="chat-container">
            <div class="chat-header">
                <div class="agent-info">
                    <div class="agent-avatar"><i class="fas fa-headset"></i></div>
                    <div class="agent-details">
                        <h2>HeyDream Support</h2>
                        <p>We typically reply in minutes</p>
                        <div class="chat-badge"><?= htmlspecialchars($convoInfo['message_type'] ?? 'General Chat') ?></div>
                    </div>
                </div>
                <a href="index.php" class="home-link" title="Back to Home"><i class="fas fa-times"></i></a>
            </div>
            
            <div class="chat-messages" id="chatBox">
                <div class="sys-msg">Chat started on <?= date('M d, Y', strtotime($convoInfo['created_at'])) ?></div>
                
                <?php if (empty($messages)): ?>
                    <div class="message msg-admin">
                        <div class="msg-sender">HeyDream Support</div>
                        Hi <?= htmlspecialchars($_SESSION['chat_name'] ?? 'there') ?>! 👋<br><br>
                        Thanks for reaching out about our <strong><?= htmlspecialchars($convoInfo['message_type'] ?? 'services') ?></strong>.<br>
                        How can we help you today?
                    </div>
                <?php endif; ?>
                
                <?php 
                    $last_id = 0;
                    foreach ($messages as $m): 
                        $last_id = max($last_id, $m['id']);
                ?>
                    <div class="message <?= $m['sender_type'] === 'Customer' ? 'msg-customer' : 'msg-admin' ?>">
                        <?php if ($m['sender_type'] !== 'Customer'): ?>
                            <div class="msg-sender"><?= htmlspecialchars($m['sender_name']) ?></div>
                        <?php endif; ?>
                        <?= nl2br(htmlspecialchars($m['message'])) ?>
                        <div class="msg-time"><?= date('g:i A', strtotime($m['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
                
                <div id="typingIndicator" style="display: none; align-self: flex-start; color: var(--muted); font-size: 0.85rem; font-style: italic; margin-left: 10px;">
                    Staff is typing...
                </div>
                
                <?php if ($convoInfo['status'] === 'Resolved'): ?>
                    <div class="sys-msg">This conversation has been marked as resolved.</div>
                <?php endif; ?>
            </div>
            
            <div class="chat-input">
                <?php if ($convoInfo['status'] !== 'Resolved'): ?>
                    <form action="" method="POST">
                        <textarea name="message" rows="1" placeholder="Type your message..." required oninput="this.style.height = '';this.style.height = this.scrollHeight + 'px'"></textarea>
                        <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; color: var(--muted); padding: 10px;">Chat is closed. <a href="contact.php" style="color: var(--primary);">Contact us</a> for new inquiries.</div>
                <?php endif; ?>
            </div>
            
            <script>
                const chatBox = document.getElementById('chatBox');
                const typingIndicator = document.getElementById('typingIndicator');
                const messageInput = document.querySelector('textarea[name="message"]');
                let lastId = <?= $last_id ?>;
                const convoId = <?= $convo_id ?>;
                
                function scrollToBottom() {
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
                scrollToBottom();
                
                function fetchMessages() {
                    fetch(`api/chat_sync.php?convo_id=${convoId}&last_id=${lastId}`)
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
                                        innerHtml += `<div class="msg-sender">${m.sender_name}</div>`;
                                    }
                                    innerHtml += m.safe_message;
                                    innerHtml += `<div class="msg-time">${m.formatted_time}</div>`;
                                    
                                    div.innerHTML = innerHtml;
                                    chatBox.insertBefore(div, typingIndicator);
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
                        fetch('api/chat_sync.php?convo_id=' + convoId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ typing: true })
                        });
                        
                        clearTimeout(typingTimeout);
                        typingTimeout = setTimeout(() => {
                            fetch('api/chat_sync.php?convo_id=' + convoId, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ typing: false })
                            });
                        }, 2000);
                    });
                }
            </script>
        </div>
    <?php endif; ?>
</body>
</html>

<?php
// admin/ai_chat_admin.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if ($pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please check your credentials in config/database.php.']);
    exit;
}

require_once __DIR__ . '/../api/partner-booking-tracker.php';
ensurePartnerReportedIssues($pdo);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_sessions':
        try {
            $stmt = $pdo->query("
                SELECT s.*, 
                       (SELECT message FROM ai_chat_messages WHERE session_id = s.session_id ORDER BY id DESC LIMIT 1) AS last_message,
                       (SELECT sender FROM ai_chat_messages WHERE session_id = s.session_id ORDER BY id DESC LIMIT 1) AS last_sender,
                       (SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = s.session_id AND sender = 'customer' AND admin_seen = 0) AS unread_count
                FROM ai_chat_sessions s
                ORDER BY s.last_activity DESC
            ");
            $sessions = $stmt->fetchAll();
            echo json_encode(['success' => true, 'sessions' => $sessions]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_messages':
        $sessionId = $_GET['session_id'] ?? '';
        try {
            // Mark customer messages in this session as seen/read
            if (!empty($sessionId)) {
                $updateSeen = $pdo->prepare("UPDATE ai_chat_messages SET admin_seen = 1 WHERE session_id = ? AND sender = 'customer' AND admin_seen = 0");
                $updateSeen->execute([$sessionId]);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC");
            $stmt->execute([$sessionId]);
            $messages = $stmt->fetchAll();
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'send_message':
        $input = json_decode(file_get_contents('php://input'), true);
        $sessionId = $input['session_id'] ?? '';
        $message = trim($input['message'] ?? '');

        if (empty($sessionId) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        try {
            // Auto-insert [AGENT_JOINED] system notification before first admin message (if not already sent)
            $checkJoined = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ? AND sender = 'system' AND message = '[AGENT_JOINED]'");
            $checkJoined->execute([$sessionId]);
            if ($checkJoined->fetchColumn() == 0) {
                $sysStmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'system', '[AGENT_JOINED]')");
                $sysStmt->execute([$sessionId]);
            }

            // Log admin message
            $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'admin', ?)");
            $stmt->execute([$sessionId, $message]);

            // Update session activity and status to 'taken_over'
            $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET last_activity = NOW(), status = 'taken_over' WHERE session_id = ?");
            $stmt->execute([$sessionId]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'takeover':
        $sessionId = $_GET['session_id'] ?? '';
        try {
            $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET status = 'taken_over' WHERE session_id = ?");
            $stmt->execute([$sessionId]);

            // Insert a system notification so the customer's chatbot shows "live agent joined" banner
            // Only insert if not already present for this session
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ? AND sender = 'system' AND message = '[AGENT_JOINED]'");
            $checkStmt->execute([$sessionId]);
            if ($checkStmt->fetchColumn() == 0) {
                $sysStmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'system', '[AGENT_JOINED]')");
                $sysStmt->execute([$sessionId]);
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_session':
        $sessionId = $_GET['session_id'] ?? '';
        try {
            $pdo->beginTransaction();
            // Delete messages first
            $stmt = $pdo->prepare("DELETE FROM ai_chat_messages WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            // Delete session
            $stmt = $pdo->prepare("DELETE FROM ai_chat_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'bulk_delete_sessions':
        $input = json_decode(file_get_contents('php://input'), true);
        $sessionIds = $input['session_ids'] ?? [];

        if (empty($sessionIds) || !is_array($sessionIds)) {
            echo json_encode(['success' => false, 'message' => 'Invalid session IDs']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM ai_chat_messages WHERE session_id IN ($placeholders)");
            $stmt->execute($sessionIds);
            $stmt = $pdo->prepare("DELETE FROM ai_chat_sessions WHERE session_id IN ($placeholders)");
            $stmt->execute($sessionIds);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'bulk_update_sessions':
        $input = json_decode(file_get_contents('php://input'), true);
        $sessionIds = $input['session_ids'] ?? [];
        $status = $input['status'] ?? '';

        if (empty($sessionIds) || !is_array($sessionIds) || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET status = ? WHERE session_id IN ($placeholders)");
            $stmt->execute(array_merge([$status], $sessionIds));
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_issues':
        try {
            $stmt = $pdo->query("
                SELECT ri.*, pa.company_name AS partner_company
                FROM reported_issues ri
                LEFT JOIN partner_applications pa ON ri.partner_id = pa.id
                ORDER BY ri.created_at DESC
            ");
            $issues = $stmt->fetchAll();
            echo json_encode(['success' => true, 'issues' => $issues]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update_issue_status':
        $issueId = $_GET['id'] ?? '';
        $status = $_GET['status'] ?? '';
        if (empty($issueId) || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        try {
            // Get issue details before update to send email
            $stmt = $pdo->prepare("SELECT * FROM reported_issues WHERE id = ?");
            $stmt->execute([$issueId]);
            $issue = $stmt->fetch();

            if ($issue) {
                // Update database
                $stmt = $pdo->prepare("UPDATE reported_issues SET status = ? WHERE id = ?");
                $stmt->execute([$status, $issueId]);

                // Send email notification to customer
                try {
                    require_once __DIR__ . '/../config/email_config.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->isSMTP();
                    $mail->Host = $emailConfig['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $emailConfig['username'];
                    $mail->Password = $emailConfig['password'];
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = $emailConfig['port'];
                    
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
                    $mail->addAddress($issue['email'], $issue['name']);
                    $mail->isHTML(true);
                    $mail->Subject = '🔔 HeyDream Support Ticket Update [#' . $issueId . ']';

                    $statusClass = 'status-pending';
                    if ($status === 'In Progress') $statusClass = 'status-progress';
                    else if ($status === 'Resolved') $statusClass = 'status-resolved';

                    $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <title>Ticket Status Update</title>
                        <style>
                            body { font-family: 'Poppins', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
                            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                            .header { background: #003580; padding: 30px; text-align: center; color: white; }
                            .header h1 { margin: 0; font-size: 24px; }
                            .content { padding: 30px; line-height: 1.6; color: #333; }
                            .status-box { text-align: center; margin: 25px 0; padding: 15px; border-radius: 12px; font-size: 1.1rem; font-weight: bold; }
                            .status-pending { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
                            .status-progress { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
                            .status-resolved { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
                            .details { background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 5px solid #003580; }
                            .footer { text-align: center; font-size: 12px; color: #999; padding: 20px; border-top: 1px solid #eee; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>🔔 Support Ticket Update — HeyDream</h1>
                            </div>
                            <div class='content'>
                                <p>Dear " . htmlspecialchars($issue['name']) . ",</p>
                                <p>This is an automated notification to let you know that the status of your reported issue ticket <strong>#" . htmlspecialchars($issueId) . "</strong> has been updated by our support team.</p>
                                
                                <div class='status-box " . $statusClass . "'>
                                    Current Status: " . htmlspecialchars($status) . "
                                </div>

                                <div class='details'>
                                    <strong>📋 Ticket Details:</strong><br><br>
                                    • <strong>Ticket ID:</strong> #" . htmlspecialchars($issueId) . "<br>
                                    • <strong>Category:</strong> " . htmlspecialchars($issue['category']) . "<br>
                                    • <strong>Date Submitted:</strong> " . htmlspecialchars($issue['created_at']) . "<br>
                                </div>
                                
                                <p>If you have any further questions or details to add, feel free to reply directly to this email or speak to our AI Travel Assistant on our website.</p>
                            </div>
                            <div class='footer'>
                                <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";

                    $mail->send();
                } catch (Exception $mailEx) {
                    error_log("Failed to send status update email: " . $mailEx->getMessage());
                }
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_issue':
        $issueId = $_GET['id'] ?? '';
        if (empty($issueId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM reported_issues WHERE id = ?");
            $stmt->execute([$issueId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_sales_agents':
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, full_name FROM admin_users WHERE role = 'sales' AND is_active = 1");
            $stmt->execute();
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'agents' => $agents]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'transfer_chat':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $sessionId = $_REQUEST['session_id'] ?? '';
            $agentId = $_REQUEST['agent_id'] ?? '';
        } else {
            $sessionId = $input['session_id'] ?? '';
            $agentId = $input['agent_id'] ?? '';
        }

        if (empty($sessionId) || empty($agentId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        try {
            // Fetch agent details
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ? AND role = 'sales'");
            $stmt->execute([$agentId]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$agent) {
                echo json_encode(['success' => false, 'message' => 'Sales Agent not found']);
                exit;
            }

            // Fetch session details
            $stmt = $pdo->prepare("SELECT * FROM ai_chat_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'Session not found']);
                exit;
            }

            // Update session
            $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET assigned_agent_id = ?, status = 'taken_over', last_activity = NOW() WHERE session_id = ?");
            $stmt->execute([$agentId, $sessionId]);

            // Insert system notification message
            $sysMsg = "System: Chat has been manually transferred to Sales Agent: " . htmlspecialchars($agent['full_name']);
            $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'ai', ?)");
            $stmt->execute([$sessionId, $sysMsg]);

            // Send manual transfer email notification to the agent
            try {
                require_once __DIR__ . '/../config/email_config.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host = $emailConfig['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $emailConfig['username'];
                $mail->Password = $emailConfig['password'];
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $emailConfig['port'];
                
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
                $mail->addAddress($agent['email'], $agent['full_name']);
                $mail->isHTML(true);
                $mail->Subject = '💼 HeyDream Live Chat Transferred to You!';

                $adminPanelUrl = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/HeyDream Website - anti gravity 11.5/admin/marketing.php#ai-chats";

                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>Live Chat Session Transferred</title>
                    <style>
                        body { font-family: 'Poppins', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                        .header { background: #003580; padding: 30px; text-align: center; color: white; }
                        .header h1 { margin: 0; font-size: 24px; }
                        .content { padding: 30px; line-height: 1.6; color: #333; }
                        .details { background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 5px solid #003580; }
                        .footer { text-align: center; font-size: 12px; color: #999; padding: 20px; border-top: 1px solid #eee; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>💼 Live Chat Assigned to You!</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . htmlspecialchars($agent['full_name']) . ",</p>
                            <p>An ongoing live chat session with a customer has been manually transferred to you by another administrator. Please take over the chat and respond as soon as possible.</p>
                            
                            <div class='details'>
                                <strong>📋 Transferred Session Details:</strong><br><br>
                                • <strong>Customer Name:</strong> " . htmlspecialchars($session['customer_name']) . "<br>
                                • <strong>Customer Email:</strong> " . ($session['customer_email'] ? htmlspecialchars($session['customer_email']) : 'Not Provided') . "<br>
                                • <strong>Session ID:</strong> " . htmlspecialchars($sessionId) . "<br>
                                • <strong>Transferred At:</strong> " . date('F d, Y H:i:s') . "<br>
                            </div>
                            
                            <div style='text-align: center;'>
                                <a href='{$adminPanelUrl}' target='_blank' style='display: inline-block; background-color: #ffd700; color: #003580 !important; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight: 700; text-align: center; margin-top: 15px;'>Open Chat Panel</a>
                            </div>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mail->send();
            } catch (Exception $mailEx) {
                error_log("Failed to send transfer notification email: " . $mailEx->getMessage());
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

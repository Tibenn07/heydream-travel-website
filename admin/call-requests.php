<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $requestId = (int) $_POST['request_id'];
    $newStatus = $_POST['status'];
    $assignedAgent = !empty($_POST['assigned_agent']) ? $_POST['assigned_agent'] : null;

    // fetch the request details
    $stmt = $pdo->prepare("SELECT * FROM call_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (in_array($newStatus, ['pending', 'in_progress', 'completed'])) {
        $stmt = $pdo->prepare("UPDATE call_requests SET status = ?, assigned_agent = ? WHERE id = ?");
        $stmt->execute([$newStatus, $assignedAgent, $requestId]);

        // Send assigned agent number to customer via chatbot for inbound requests
        if ($req && $req['request_type'] === 'inbound' && $newStatus === 'in_progress' && !empty($assignedAgent) && !empty($req['session_id'])) {
            if ($req['status'] !== 'in_progress' || $req['assigned_agent'] !== $assignedAgent) {
                $msg = "An agent has accepted your request! Please call this number: <strong>$assignedAgent</strong>";
                $stmt2 = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'admin', ?)");
                $stmt2->execute([$req['session_id'], $msg]);
            }
        }
    }

    header("Location: call-requests.php?success=1");
    exit;
}

// Fetch Call Requests
$stmt = $pdo->query("SELECT * FROM call_requests ORDER BY created_at DESC");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Requests | HeyDream Travel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 0;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            color: #0f172a;
            margin: 0;
        }

        .btn-back {
            background: #003580;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-back:hover {
            background: #00255c;
        }

        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f1f5f9;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 0.95rem;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-in_progress {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-completed {
            background: #dcfce3;
            color: #16a34a;
        }

        .action-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 0.9rem;
            cursor: pointer;
        }

        button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            transition: background 0.3s;
        }

        button:hover {
            background: #2563eb;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #cbd5e1;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-headset" style="color: #003580; margin-right: 15px;"></i> Live Agent Requests</h1>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div
                style="background: #dcfce3; color: #16a34a; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">
                <i class="fas fa-check-circle"></i> Status updated successfully!
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h2>No Call Requests Yet</h2>
                    <p>When customers request a callback or request a live agent, they will appear here.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Phone Number</th>
                            <th>Concern / Message</th>
                            <th>Type</th>
                            <th>Date / Time</th>
                            <th>Status & Agent</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>#
                                    <?= htmlspecialchars($req['id']) ?>
                                </td>
                                <td style="font-weight: 700; color: #0f172a;">
                                    <?= htmlspecialchars($req['customer_phone']) ?>
                                </td>
                                <td style="max-width: 200px; word-wrap: break-word;"><small>
                                        <?= htmlspecialchars($req['concern'] ?? 'N/A') ?>
                                    </small></td>
                                <td>
                                    <?php if ($req['request_type'] === 'inbound'): ?>
                                        <span style="color: #6366f1; font-weight: 600;"><i class="fas fa-phone-incoming"></i>
                                            Customer Calls</span>
                                    <?php else: ?>
                                        <span style="color: #f59e0b; font-weight: 600;"><i class="fas fa-phone-volume"></i> Request
                                            Call</span>
                                    <?php endif; ?>
                                </td>
                                <td><small>
                                        <?= date('M d, Y h:i A', strtotime($req['created_at'])) ?>
                                    </small></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($req['status']) ?>"
                                        style="display:inline-block; margin-bottom:4px;">
                                        <?= str_replace('_', ' ', htmlspecialchars($req['status'] === 'in_progress' ? 'Accepted' : $req['status'])) ?>
                                    </span><br>
                                    <small>Agent:
                                        <?= htmlspecialchars($req['assigned_agent'] ?: 'Unassigned') ?>
                                    </small>
                                </td>
                                <td>
                                    <form method="POST" class="action-form"
                                        style="flex-direction: column; align-items: stretch; gap: 6px;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">

                                        <select name="assigned_agent">
                                            <option value="">Assign Agent...</option>
                                            <option value="09916792140" <?= $req['assigned_agent'] === '09916792140' ? 'selected' : '' ?>>Agent 1 - 09916792140</option>
                                            <option value="09079128442" <?= $req['assigned_agent'] === '09079128442' ? 'selected' : '' ?>>Agent 2 - 09079128442</option>
                                            <option value="09457764140" <?= $req['assigned_agent'] === '09457764140' ? 'selected' : '' ?>>Agent 3 - 09457764140</option>
                                        </select>

                                        <div style="display: flex; gap: 6px;">
                                            <select name="status" style="flex:1;">
                                                <option value="pending" <?= $req['status'] === 'pending' ? 'selected' : '' ?>>
                                                    Pending</option>
                                                <option value="in_progress" <?= $req['status'] === 'in_progress' ? 'selected' : '' ?>>Accept Request</option>
                                                <option value="completed" <?= $req['status'] === 'completed' ? 'selected' : '' ?>>
                                                    Completed</option>
                                            </select>
                                            <button type="submit">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
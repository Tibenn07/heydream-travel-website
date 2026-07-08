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

// Fetch Inquiries for the Inquiries Tab
$stmtInq = $pdo->query("SELECT
    id,
    COALESCE(NULLIF(TRIM(full_name), ''), CONCAT('Customer ', id)) AS full_name,
    COALESCE(NULLIF(TRIM(email), ''), CONCAT('customer', id, '@example.com')) AS email,
    COALESCE(NULLIF(TRIM(phone), ''), 'N/A') AS phone,
    travel_date,
    number_of_travelers,
    COALESCE(NULLIF(TRIM(package_name), ''), NULLIF(TRIM(destination_name), ''), 'Unspecified') AS destination,
    special_requests,
    booking_status,
    marketing_consent,
    booking_number,
    created_at
FROM bookings
WHERE payment_method = 'Inquiry Only'
ORDER BY created_at DESC");
$inquiries = $stmtInq->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Campaigns
$stmtCamp = $pdo->query("SELECT * FROM marketing_campaigns ORDER BY created_at DESC LIMIT 5");
$recentCampaigns = $stmtCamp->fetchAll(PDO::FETCH_ASSOC);

// Helper to get source icon
function getSourceIcon($requests)
{
    $source = '';
    if (preg_match('/How did you hear about us: (.*)/i', $requests, $matches)) {
        $source = strtolower(trim($matches[1]));
    }

    if (strpos($source, 'facebook') !== false)
        return '<i class="fab fa-facebook" style="color: #1877F2; font-size: 1.1rem;" title="Facebook"></i>';
    if (strpos($source, 'twitter') !== false)
        return '<i class="fab fa-twitter" style="color: #1DA1F2; font-size: 1.1rem;" title="Twitter"></i>';
    if (strpos($source, 'tiktok') !== false)
        return '<i class="fab fa-tiktok" style="color: #000000; font-size: 1.1rem;" title="TikTok"></i>';
    if (strpos($source, 'instagram') !== false)
        return '<i class="fab fa-instagram" style="color: #E4405F; font-size: 1.1rem;" title="Instagram"></i>';
    if (strpos($source, 'threads') !== false)
        return '<i class="fab fa-threads" style="color: #000000; font-size: 1.1rem;" title="Threads"></i>';
    if (strpos($source, 'gmail') !== false)
        return '<i class="fas fa-envelope" style="color: #EA4335; font-size: 1.1rem;" title="Gmail"></i>';
    if (strpos($source, 'our website') !== false)
        return '<img src="../images/Heydream Logo.png" style="width: 22px; height: 22px; object-fit: contain;" title="Our Website">';
    return '<i class="fas fa-question-circle" style="color: #94a3b8; font-size: 1.1rem;" title="Unknown Source"></i>';
}

function getInquiryDisplayName($inq)
{
    $candidates = [
        $inq['full_name'] ?? '',
        $inq['customer_name'] ?? '',
        $inq['name'] ?? '',
        $inq['contact_name'] ?? '',
        $inq['customer_full_name'] ?? ''
    ];

    foreach ($candidates as $value) {
        $clean = trim((string) $value);
        if ($clean !== '' && strtolower($clean) !== 'null' && strtolower($clean) !== 'n/a') {
            return $clean;
        }
    }

    $email = getInquiryEmail($inq);
    return $email !== '' && $email !== 'No email provided' ? explode('@', $email)[0] : 'Guest';
}

function getInquiryEmail($inq)
{
    $candidates = [
        $inq['email'] ?? '',
        $inq['customer_email'] ?? '',
        $inq['contact_email'] ?? ''
    ];

    foreach ($candidates as $value) {
        $clean = trim((string) $value);
        if ($clean !== '' && strtolower($clean) !== 'null' && strtolower($clean) !== 'n/a') {
            return $clean;
        }
    }

    return '';
}

function getInquiryDestination($inq)
{
    $candidates = [
        $inq['destination'] ?? '',
        $inq['package_name'] ?? '',
        $inq['destination_name'] ?? '',
        $inq['service_type'] ?? ''
    ];

    foreach ($candidates as $value) {
        $clean = trim((string) $value);
        if ($clean !== '' && strtolower($clean) !== 'null' && strtolower($clean) !== 'n/a') {
            return $clean;
        }
    }

    return 'Unspecified';
}

// Fetch Summary Stats
$totalInquiries = count($inquiries);
$pendingInquiries = count($inquiries);
$confirmedInquiries = count(array_filter($inquiries, fn($i) => strtolower($i['booking_status']) === 'confirmed'));

// Fetch Template Count
$stmtTpl = $pdo->query("SELECT COUNT(*) FROM marketing_templates");
$templateCount = $stmtTpl->fetchColumn();

// Helper to normalize status label and CSS class
function getStatusInfo($status)
{
    $map = [
        'pending' => ['label' => 'Pending', 'class' => 'status-pending'],
        'confirmed' => ['label' => 'Reviewed', 'class' => 'status-confirmed'],
        'completed' => ['label' => 'Completed', 'class' => 'status-completed'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'status-cancelled'],
    ];
    $s = strtolower($status ?? 'pending');
    return $map[$s] ?? ['label' => ucfirst($s), 'class' => 'status-pending'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Hub | HeyDream Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/marketing.css">
    <style>
        /* Inquiry notification badge */
        .menu-item {
            position: relative;
        }

        .inq-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            border-radius: 50%;
            animation: badge-pop 0.3s ease;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 0;
            line-height: 1;
            text-align: center;
            vertical-align: middle;
        }

        @keyframes badge-pop {
            0% {
                transform: scale(0);
            }

            70% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Modern Metric Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 35px;
        }

        .metric-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.6);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .metric-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: rgba(226, 232, 240, 1);
        }

        .metric-top {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 25px;
        }

        .metric-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            transition: transform 0.3s ease;
        }

        .metric-card:hover .metric-icon {
            transform: scale(1.1) rotate(-5deg);
        }

        .metric-info {
            display: flex;
            flex-direction: column;
        }

        .metric-label {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .metric-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            letter-spacing: -1px;
        }

        .metric-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
        }

        .metric-trend {
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 50px;
        }

        .trend-up {
            background: #ecfdf5;
            color: #10b981;
        }

        .trend-down {
            background: #fef2f2;
            color: #ef4444;
        }

        .trend-neutral {
            background: #f8fafc;
            color: #94a3b8;
        }

        .btn-icon {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-icon:hover {
            background: #003580;
            color: white;
            border-color: #003580;
            transform: scale(1.05);
        }

        /* Premium Calendar Styles */
        .calendar-day-item {
            padding: 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            color: #334155;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 1;
            border: 2px solid transparent !important;
        }

        .calendar-day-item:hover {
            background: #f1f5f9;
            color: #0f172a;
            transform: scale(1.05);
        }

        .calendar-day-item.selected-day {
            border-color: #003580 !important;
            transform: scale(1.05);
            box-shadow: 0 4px 6px -1px rgba(0, 53, 128, 0.1), 0 2px 4px -1px rgba(0, 53, 128, 0.06);
        }

        @keyframes valuePop {
            0% {
                transform: scale(1);
                color: var(--primary);
            }

            50% {
                transform: scale(1.1);
                color: #2563eb;
            }

            100% {
                transform: scale(1);
                color: var(--primary);
            }
        }

        .value-updated {
            animation: valuePop 0.5s ease;
        }

        .metric-sparkline {
            width: 100px;
            height: 40px;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }

        .metric-card:hover .metric-sparkline {
            opacity: 1;
        }

        .metric-blue .metric-icon {
            background: #eff6ff;
            color: #2563eb;
        }

        .metric-orange .metric-icon {
            background: #fff7ed;
            color: #f97316;
        }

        .metric-green .metric-icon {
            background: #f0fdf4;
            color: #10b981;
        }

        .metric-purple .metric-icon {
            background: #f5f3ff;
            color: #8b5cf6;
        }

        /* AI Chat Manager Styles */
        .chat-manager-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
            height: calc(100vh - 250px);
            min-height: 600px;
        }

        .chat-sessions-card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0 !important;
        }

        .chat-sessions-card .card-title {
            padding: 20px;
            margin: 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .ai-sessions-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .chat-session-item {
            padding: 15px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-session-item.unread {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
        }

        .chat-session-item.unread h4 {
            font-weight: 800 !important;
            color: #0f172a !important;
        }

        .chat-session-item.unread .session-last-msg {
            font-weight: 700 !important;
            color: #0f172a !important;
        }

        .chat-session-item.read {
            background: #f8fafc !important;
            border: 1px solid transparent !important;
        }

        .chat-session-item.read h4 {
            font-weight: 600 !important;
            color: #475569 !important;
        }

        .chat-session-item.read .session-last-msg {
            font-weight: 400 !important;
            color: #94a3b8 !important;
        }

        .chat-session-item:hover {
            background: #f1f5f9 !important;
        }

        .chat-session-item.active {
            background: #eff6ff !important;
            border-color: #3b82f6 !important;
        }

        .session-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #475569;
            flex-shrink: 0;
        }

        .session-info {
            flex: 1;
            overflow: hidden;
        }

        .session-info h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .session-info p {
            margin: 2px 0 0;
            font-size: 0.75rem;
            color: #64748b;
        }

        .session-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #cbd5e1;
        }

        .session-status-dot.online {
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .chat-window-card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0 !important;
            background: #f8fafc !important;
        }

        .active-chat-header {
            padding: 15px 25px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-user-info h3 {
            margin: 0;
            font-size: 1.1rem;
        }

        .chat-user-info span {
            font-size: 0.8rem;
            color: #64748b;
        }

        .admin-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .chat-bubble {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 18px;
            font-size: 0.95rem;
            line-height: 1.5;
            position: relative;
        }

        .chat-bubble.customer {
            align-self: flex-start;
            background: #fff;
            color: #1e293b;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .chat-bubble.ai {
            align-self: flex-start;
            background: #f1f5f9;
            color: #475569;
            border-bottom-left-radius: 4px;
            font-style: italic;
            border-left: 3px solid #3b82f6;
        }

        .chat-bubble.admin {
            align-self: flex-end;
            background: #003580;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .bubble-time {
            font-size: 0.65rem;
            opacity: 0.7;
            margin-top: 5px;
            display: block;
            text-align: right;
        }

        .admin-chat-footer {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #e2e8f0;
        }

        .admin-chat-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .admin-chat-input-wrapper textarea {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: none;
            height: 50px;
            transition: border-color 0.2s;
        }

        .admin-chat-input-wrapper textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .admin-chat-input-wrapper textarea:disabled {
            background: #f8fafc;
        }

        .empty-chat,
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
            text-align: center;
            padding: 40px;
        }

        .empty-chat i,
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }
    </style>
    <style>
        /* =============================================
           INQUIRIES TABLE — matches reference layout
           ============================================= */
        .inquiries-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Poppins', sans-serif;
            font-size: 0.875rem;
        }

        .inquiries-table thead th {
            background: #f8fafc;
            color: #94a3b8;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            border-bottom: 2px solid #f1f5f9;
            white-space: nowrap;
        }

        .inquiries-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }

        .inquiries-table tbody tr:hover {
            background: #f8fafc;
        }

        .inquiries-table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            color: #334155;
        }

        /* Source icon cell */
        .inquiries-table .col-source {
            width: 56px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Name cell */
        .inquiries-table .col-name {
            min-width: 160px;
            font-weight: 700;
            color: #003580;
        }

        /* Email cell */
        .inquiries-table .col-email {
            min-width: 200px;
            font-size: 0.82rem;
            color: #64748b;
        }

        /* Destination cell */
        .inquiries-table .col-destination {
            min-width: 120px;
            font-weight: 600;
            color: #1e293b;
        }

        /* Status cell */
        .inquiries-table .col-status {
            width: 100px;
        }

        /* Ads/consent cell */
        .inquiries-table .col-ads {
            width: 80px;
            text-align: center;
        }

        /* Date cell */
        .inquiries-table .col-date {
            width: 110px;
            font-size: 0.82rem;
            color: #64748b;
            white-space: nowrap;
        }

        /* Actions cell */
        .inquiries-table .col-actions {
            width: 220px;
        }

        .inquiry-actions-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            cursor: pointer;
            transition: all 0.18s;
            flex-shrink: 0;
        }

        .btn-icon:hover {
            border-color: #003580;
            color: #003580;
            background: #eef2ff;
        }

        .btn-icon.danger {
            color: #ef4444;
            border-color: #fecaca;
        }

        .btn-icon.danger:hover {
            background: #fef2f2;
            border-color: #ef4444;
        }

        /* Compact status select inside table */
        .inq-status-select {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            background: #f8fafc;
            color: #334155;
            flex: 1;
            min-width: 0;
            transition: all 0.2s;
        }

        .inq-status-select:hover {
            border-color: #003580;
            background: #fff;
        }

        .inq-status-select:focus {
            outline: none;
            border-color: #003580;
            box-shadow: 0 0 0 3px rgba(0, 53, 128, 0.1);
        }

        /* Consent toggle in table */
        .consent-toggle-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            cursor: pointer;
        }

        .consent-toggle {
            width: 34px;
            height: 18px;
            background: #cbd5e1;
            border-radius: 100px;
            position: relative;
            transition: background 0.2s;
        }

        .consent-toggle.active {
            background: #003580;
        }

        .consent-handle {
            width: 14px;
            height: 14px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: left 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .consent-toggle.active .consent-handle {
            left: 18px;
        }

        .consent-label {
            font-size: 0.62rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
        }

    </style>
    <style>
        @keyframes timer-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .active-timer-glow {
            animation: timer-pulse 2s infinite;
            border-color: #10b981 !important;
        }


        /* SweetAlert2 Premium Styles & Layout Fixes */
        .swal2-popup {
            padding: 30px !important;
            border-radius: 24px !important;
            font-family: 'Poppins', sans-serif !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        }

        .swal2-title {
            font-size: 1.4rem !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            margin-bottom: 10px !important;
        }

        .swal2-select {
            min-width: unset !important;
            width: 100% !important;
            max-width: 320px !important;
            box-sizing: border-box !important;
            border-radius: 12px !important;
            border: 2px solid #e2e8f0 !important;
            padding: 12px 16px !important;
            font-family: 'Poppins', sans-serif !important;
            font-size: 0.95rem !important;
            color: #334155 !important;
            background-color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s ease !important;
            margin: 15px auto 0 auto !important;
        }

        .swal2-select:focus {
            border-color: #003580 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(0, 53, 128, 0.1) !important;
        }

        .swal2-actions {
            margin-top: 25px !important;
        }

        .swal2-confirm {
            background-color: #003580 !important;
            padding: 12px 30px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
        }

        .swal2-cancel {
            padding: 12px 30px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
        }

        /* Reported Issues: "Critical First" severity sort toggle */
        #criticalFirstToggle:checked~.critical-toggle-track {
            background: #b91c1c;
        }

        #criticalFirstToggle:checked~.critical-toggle-thumb {
            transform: translateX(13px);
        }

        /* Reported Issues: screenshot zoom lightbox */
        #screenshotZoomOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.92);
            z-index: 20000;
            align-items: center;
            justify-content: center;
            padding: 40px;
            cursor: zoom-out;
        }

        #screenshotZoomOverlay.is-open {
            display: flex;
        }

        #screenshotZoomImg {
            max-width: 100%;
            max-height: 100%;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            cursor: default;
        }

        #screenshotZoomClose {
            position: fixed;
            top: 20px;
            right: 30px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            font-size: 1.8rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }

        #screenshotZoomClose:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
    <script>
        // Pending inquiry count from server
        const INITIAL_PENDING_INQUIRIES = <?php echo (int) $pendingInquiries; ?>;
    </script>
</head>

<body>

    <!-- Sidebar Navigation -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>HeyDream Marketing</h2>
        </div>
        <div class="sidebar-menu">
            <a class="menu-item active" onclick="switchSection('dashboard')" data-target="dashboard"><i
                    class="fas fa-chart-pie"></i> Dashboard</a>
            <a class="menu-item" onclick="switchSection('emails')" data-target="emails"><i
                    class="fas fa-envelope-open-text"></i> Email Promotions</a>
            <a class="menu-item" onclick="switchSection('inquiries')" data-target="inquiries" id="menu-inquiries"><i
                    class="fas fa-headset"></i> Inquiries<?php if ($pendingInquiries > 0): ?><span class="inq-badge"
                        id="inq-badge"><?php echo $pendingInquiries; ?></span><?php endif; ?></a>
            <a class="menu-item" onclick="switchSection('ai-chats')" data-target="ai-chats"><i class="fas fa-robot"></i>
                AI Live Chats <span id="ai-chat-badge" class="inq-badge"
                    style="display:none; background: #3b82f6;">0</span></a>
            <a class="menu-item" onclick="switchSection('reported-issues')" data-target="reported-issues"><i
                    class="fas fa-exclamation-circle"></i> Reported Issues <span id="reported-issues-badge"
                    class="inq-badge" style="display:none; background: #ef4444;">0</span></a>
            <a class="menu-item" onclick="switchSection('vouchers')" data-target="vouchers"><i
                    class="fas fa-ticket-alt"></i> Voucher Management</a>
            <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                <a class="menu-item" onclick="openUnlockManager()"
                    style="color: #f87171; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 10px; padding-top: 15px; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-shield-alt" style="font-size: 1.1rem; width: 24px; text-align: center;"></i>
                        <div>
                            <span style="display: block; font-weight: 800; font-size: 0.85rem;">Unlock Requests</span>
                            <span style="display: block; font-size: 0.65rem; opacity: 0.7; font-weight: 400;">Manage Block
                                Access</span>
                        </div>
                    </div>
                    <span id="unlock-request-badge" class="inq-badge" style="background: #f87171; display: none;">0</span>
                </a>
            <?php endif; ?>
        </div>
        <div class="sidebar-footer"
            style="padding: 20px; display: flex; flex-direction: column; gap: 12px; border-top: 1px solid rgba(255,255,255,0.1);">
            <a href="messages.php" class="btn btn-outline"
                style="width: 100%; color: white; border-color: rgba(255,255,255,0.2); text-align: center; border-radius: 12px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: rgba(255,255,255,0.08);">
                <i class="fas fa-envelope-open-text"></i> Messages
            </a>
            <button class="btn btn-primary" onclick="openMessageModal()"
                style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; padding: 12px; font-weight: 700; background: #facc15; border: none; color: #002244; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease-in-out; box-shadow: 0 4px 12px rgba(250, 204, 21, 0.2);">
                <i class="fas fa-envelope"></i> Email Message
            </button>
            <a href="dashboard.php" class="btn btn-outline"
                style="width: 100%; color: white; border-color: rgba(255,255,255,0.2); text-align: center; border-radius: 12px; padding: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;"><i
                    class="fas fa-arrow-left"></i> Main Admin</a>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="main-wrapper">

        <!-- Active Access Monitor (Visible for restricted roles with active unlocks) -->
        <div id="active-access-banner"
            style="display: none; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 10px 30px; font-size: 0.85rem; font-weight: 700; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-unlock-alt"></i>
                <span id="access-banner-text">Access Granted</span>
            </div>
            <div
                style="display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.1); padding: 4px 12px; border-radius: 50px;">
                <i class="fas fa-clock"></i>
                <span id="access-countdown-timer">00:00:00</span>
            </div>
        </div>

        <!-- DASHBOARD SECTION -->
        <section id="dashboard" class="section-container active">
            <div class="page-header">
                <h1>Marketing Dashboard</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openMessageModal()"><i class="fas fa-envelope"></i> Email
                        Message</button>
                </div>
            </div>

            <div class="stats-grid">
                <!-- Total Inquiries -->
                <div class="metric-card metric-blue">
                    <div class="metric-top">
                        <div class="metric-icon"><i class="fas fa-comment-dots"></i></div>
                        <div class="metric-info">
                            <span class="metric-label">Total Inquiries</span>
                            <span class="metric-value" id="stat-total-inquiries"><?php echo $totalInquiries; ?></span>
                        </div>
                    </div>
                    <div class="metric-bottom">
                        <div class="metric-trend trend-up" id="trend-total-inquiries">
                            <i class="fas fa-arrow-up"></i> 12% <span
                                style="font-weight: 400; color: #94a3b8; margin-left: 2px;">from last week</span>
                        </div>
                        <svg class="metric-sparkline" viewBox="0 0 100 40" preserveAspectRatio="none">
                            <path d="M0,35 Q20,5 40,30 T80,10 T100,25" fill="none" stroke="#2563eb" stroke-width="2.5"
                                stroke-linecap="round" />
                        </svg>
                    </div>
                </div>

                <!-- Pending Inquiries -->
                <div class="metric-card metric-orange">
                    <div class="metric-top">
                        <div class="metric-icon"><i class="fas fa-hourglass-half"></i></div>
                        <div class="metric-info">
                            <span class="metric-label">Pending Inquiries</span>
                            <span class="metric-value"
                                id="stat-pending-inquiries"><?php echo $pendingInquiries; ?></span>
                        </div>
                    </div>
                    <div class="metric-bottom">
                        <div class="metric-trend trend-up" id="trend-pending-inquiries">
                            <i class="fas fa-arrow-up"></i> 8% <span
                                style="font-weight: 400; color: #94a3b8; margin-left: 2px;">from last week</span>
                        </div>
                        <svg class="metric-sparkline" viewBox="0 0 100 40" preserveAspectRatio="none">
                            <path d="M0,30 Q25,10 50,35 T100,15" fill="none" stroke="#f97316" stroke-width="2.5"
                                stroke-linecap="round" />
                        </svg>
                    </div>
                </div>

                <!-- Reviewed Inquiries -->
                <div class="metric-card metric-green">
                    <div class="metric-top">
                        <div class="metric-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="metric-info">
                            <span class="metric-label">Reviewed Inquiries</span>
                            <span class="metric-value"
                                id="stat-confirmed-inquiries"><?php echo $confirmedInquiries; ?></span>
                        </div>
                    </div>
                    <div class="metric-bottom">
                        <div class="metric-trend trend-up" id="trend-confirmed-inquiries">
                            <i class="fas fa-arrow-up"></i> 5% <span
                                style="font-weight: 400; color: #94a3b8; margin-left: 2px;">from last week</span>
                        </div>
                        <svg class="metric-sparkline" viewBox="0 0 100 40" preserveAspectRatio="none">
                            <path d="M0,35 L20,20 L40,30 L60,10 L80,25 L100,5" fill="none" stroke="#10b981"
                                stroke-width="2.5" stroke-linecap="round" />
                        </svg>
                    </div>
                </div>

                <!-- Templates Saved -->
                <div class="metric-card metric-purple">
                    <div class="metric-top">
                        <div class="metric-icon"><i class="fas fa-folder-open"></i></div>
                        <div class="metric-info">
                            <span class="metric-label">Templates Saved</span>
                            <span class="metric-value" id="stat-template-count"><?php echo $templateCount; ?></span>
                        </div>
                    </div>
                    <div class="metric-bottom">
                        <div class="metric-trend trend-neutral" id="trend-template-count">
                            No change <span style="font-weight: 400; color: #94a3b8; margin-left: 2px;">this week</span>
                        </div>
                        <svg class="metric-sparkline" viewBox="0 0 100 40" preserveAspectRatio="none">
                            <path d="M0,25 Q30,5 60,35 T100,15" fill="none" stroke="#8b5cf6" stroke-width="2.5"
                                stroke-linecap="round" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title"><i class="fas fa-history"></i> Recent Campaign Activity</h3>
                <div class="table-wrapper">
                    <table id="dashboard-campaign-table">
                        <thead>
                            <tr>
                                <th>Campaign Name</th>
                                <th>Sent To</th>
                                <th>Open Rate</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via JS polling -->
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    Loading activity...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- INQUIRIES SECTION -->
        <section id="inquiries" class="section-container">
            <div class="page-header">
                <h1>Marketing Inquiries</h1>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-outline" onclick="exportInquiries()"><i class="fas fa-file-export"></i>
                        Export CSV</button>
                    <button class="btn btn-primary" onclick="pollInquiryBadge()"><i class="fas fa-sync-alt"></i>
                        Refresh</button>
                </div>
            </div>

            <div class="card" style="margin-bottom: 25px;">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="text" id="inq-search" class="block-input" placeholder="Search by name or email..."
                            oninput="filterInquiries()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <select id="filter-status" class="block-input" onchange="filterInquiries()">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Reviewed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="table-wrapper">
                        <table id="inquiries-table" class="inquiries-table">
                            <thead>
                                <tr>
                                    <th class="col-source">Source</th>
                                    <th class="col-name">Name</th>
                                    <th class="col-email">Email</th>
                                    <th class="col-destination">Destination</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-ads">Ads</th>
                                    <th class="col-date">Date</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inquiries as $inq):
                                    $status = strtolower($inq['booking_status'] ?? 'pending');
                                    $statusClass = ($status === 'confirmed' || $status === 'reviewed') ? 'status-confirmed' :
                                        (($status === 'contacted') ? 'status-contacted' : 'status-pending');
                                    if ($status === 'cancelled') $statusClass = 'status-cancelled';

                                    $displayName        = getInquiryDisplayName($inq);
                                    $displayEmail       = getInquiryEmail($inq);
                                    $displayDestination = getInquiryDestination($inq);
                                ?>
                                <tr class="inquiry-row"
                                    data-name="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-email="<?php echo htmlspecialchars($displayEmail, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo $status; ?>"
                                    data-type="<?php echo strpos(strtolower($displayDestination), 'international') !== false ? 'international' : 'domestic'; ?>">

                                    <!-- Source -->
                                    <td class="col-source">
                                        <?php echo getSourceIcon($inq['special_requests'] ?? ''); ?>
                                    </td>

                                    <!-- Name -->
                                    <td class="col-name">
                                        <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <!-- Email -->
                                    <td class="col-email">
                                        <?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : 'No email', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <!-- Destination -->
                                    <td class="col-destination">
                                        <?php echo htmlspecialchars($displayDestination, ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <!-- Status -->
                                    <td class="col-status">
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                    </td>

                                    <!-- Ads (consent toggle) -->
                                    <td class="col-ads">
                                        <div class="consent-toggle-wrapper"
                                            onclick="toggleConsentInTable(<?php echo $inq['id']; ?>, this, '<?php echo $inq['booking_number']; ?>')">
                                            <div class="consent-toggle <?php echo $inq['marketing_consent'] == 1 ? 'active' : ''; ?>"
                                                id="table-consent-<?php echo $inq['booking_number']; ?>">
                                                <div class="consent-handle"></div>
                                            </div>
                                            <span class="consent-label">
                                                <?php echo $inq['marketing_consent'] == 1 ? 'ON' : 'OFF'; ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Date -->
                                    <td class="col-date">
                                        <?php echo date('M d, Y', strtotime($inq['created_at'])); ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="col-actions">
                                        <div class="inquiry-actions-row">
                                            <button class="btn-icon"
                                                onclick="viewInquiry(<?php echo $inq['id']; ?>, '<?php echo $inq['booking_number']; ?>')"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <select class="inq-status-select"
                                                onchange="updateInquiryStatus(<?php echo $inq['id']; ?>, this, '<?php echo $inq['booking_number']; ?>')">
                                                <option value="">Status...</option>
                                                <option value="pending">Pending</option>
                                                <option value="confirmed">Reviewed</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                            <button class="btn-icon danger"
                                                onclick="deleteInquiry(<?php echo $inq['id']; ?>, '<?php echo $inq['booking_number']; ?>')"
                                                title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>
            </div>
        </section>

        <!-- EMAILS (CAMPAIGN BUILDER) SECTION -->
        <section id="emails" class="section-container">
            <div class="builder-nav">
                <a href="#" class="nav-tab active" onclick="switchSection('emails')"><i class="fas fa-pencil-alt"></i>
                    Campaign Builder</a>
                <a href="#" class="nav-tab" onclick="switchSection('email-message')"
                    style="color: #003580; font-weight: 700;"><i class="fas fa-envelope-open-text"></i> Email
                    Message</a>
                <a href="#" class="nav-tab" onclick="switchSection('analytics')"><i class="fas fa-chart-line"></i>
                    Analytics &amp; History</a>
                <a href="#" class="nav-tab" onclick="switchSection('templates')"><i class="fas fa-layer-group"></i>
                    Templates</a>
            </div>

            <div class="campaign-builder-container">
                <!-- LEFT PANEL -->
                <div class="builder-panel-left">
                    <div class="panel-section" id="canvas-section">
                        <h4 class="panel-title">EMAIL CONTENT (DRAG TO REORDER)</h4>
                        <div id="builder-canvas" class="builder-canvas">
                            <!-- Dynamic blocks will be injected here -->
                        </div>
                    </div>

                    <!-- PROPERTIES PANEL (Hidden by default) -->
                    <div class="panel-section" id="properties-section" style="display: none;">
                        <div class="properties-header"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h4 class="panel-title" style="margin-bottom: 0;">ELEMENT SETTINGS</h4>
                            <button class="btn-icon" onclick="closeProperties()"><i class="fas fa-times"></i></button>
                        </div>
                        <div id="properties-content">
                            <!-- Block-specific controls will be moved here -->
                        </div>
                        <button class="btn btn-outline btn-block" style="margin-top: 20px;"
                            onclick="closeProperties()"><i class="fas fa-check"></i> Done Editing</button>
                    </div>
                </div>

                <!-- CENTER PANEL (PREVIEW) -->
                <div class="builder-panel-center">
                    <div id="email-preview-wrapper" class="preview-desktop">
                        <div id="email-preview-container">
                            <!-- PREVIEW CONTENT -->
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL -->
                <div class="builder-panel-right">
                    <div class="device-toggles">
                        <button class="active" onclick="setPreviewSize('desktop', this)"><i class="fas fa-desktop"></i>
                            Desktop</button>
                        <button onclick="setPreviewSize('tablet', this)"><i class="fas fa-tablet-alt"></i>
                            Tablet</button>
                        <button onclick="setPreviewSize('mobile', this)"><i class="fas fa-mobile-alt"></i>
                            Mobile</button>
                    </div>

                    <div class="panel-section">
                        <h4 class="panel-title">ADD ELEMENTS</h4>
                        <div class="blocks-grid">
                            <div class="block-item" data-type="header" onclick="addBlock('header')"><i
                                    class="fas fa-heading"></i> Header</div>
                            <div class="block-item" data-type="text" onclick="addBlock('text')"><i
                                    class="fas fa-font"></i> Text</div>
                            <div class="block-item" data-type="image" onclick="addBlock('image')"><i
                                    class="fas fa-image"></i> Image</div>
                            <div class="block-item" data-type="button" onclick="addBlock('button')"><i
                                    class="fas fa-link"></i> Button</div>
                            <div class="block-item" data-type="divider" onclick="addBlock('divider')"><i
                                    class="fas fa-minus"></i> Divider</div>
                            <div class="block-item" data-type="footer" onclick="addBlock('footer')"><i
                                    class="fas fa-shoe-prints"></i> Footer</div>
                        </div>
                    </div>

                    <div class="panel-section">
                        <h4 class="panel-title">HISTORY</h4>
                        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                            <button class="btn btn-outline" id="undo-btn" onclick="undo()" style="flex: 1;"
                                title="Undo (Ctrl+Z)" disabled><i class="fas fa-undo"></i> Undo</button>
                            <button class="btn btn-outline" id="redo-btn" onclick="redo()" style="flex: 1;"
                                title="Redo (Ctrl+Y)" disabled><i class="fas fa-redo"></i> Redo</button>
                        </div>
                    </div>

                    <div class="panel-section">
                        <h4 class="panel-title">ACTIONS</h4>
                        <button class="btn btn-primary btn-block" onclick="sendCampaign()"><i
                                class="fas fa-paper-plane"></i> Send Campaign</button>
                        <button class="btn btn-outline btn-block" onclick="saveTemplate()"><i class="fas fa-save"></i>
                            Save Template</button>
                        <button class="btn btn-outline btn-block spam-btn" id="spamCheckBtn"><i
                                class="fas fa-shield-virus"></i> Spam Check</button>
                        <button class="btn btn-outline btn-block" onclick="sendTest()"><i class="fas fa-vial"></i> Send
                            Test</button>
                        <button class="btn btn-outline btn-block" onclick="viewUpcomingSchedules()"
                            style="border-color: #0ea5e9; color: #0369a1; background: #f0f9ff; position: relative;">
                            <i class="fas fa-clock"></i> Upcoming Schedules
                            <span id="schedule-badge"
                                style="display: none; position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; border-radius: 50px; padding: 2px 8px; font-size: 0.7rem; font-weight: 700; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">0</span>
                        </button>
                        <div id="spamResult" style="margin-top: 15px;"></div>
                        <!-- Hidden Email Editor Bridge for Spam Check -->
                        <textarea id="emailContent" style="display:none;"></textarea>
                    </div>
                </div>
            </div>
        </section>



        <!-- EMAIL MESSAGE SECTION (inline builder tab) -->
        <section id="email-message" class="section-container">
            <div class="builder-nav">
                <a href="#" class="nav-tab" onclick="switchSection('emails')"><i class="fas fa-pencil-alt"></i> Campaign
                    Builder</a>
                <a href="#" class="nav-tab active" onclick="switchSection('email-message')"
                    style="color: #003580; font-weight: 700;"><i class="fas fa-envelope-open-text"></i> Email
                    Message</a>
                <a href="#" class="nav-tab" onclick="switchSection('analytics')"><i class="fas fa-chart-line"></i>
                    Analytics &amp; History</a>
                <a href="#" class="nav-tab" onclick="switchSection('templates')"><i class="fas fa-layer-group"></i>
                    Templates</a>
            </div>

            <div class="campaign-builder-container">

                <!-- LEFT PANEL: Canvas + Properties -->
                <div class="builder-panel-left">
                    <!-- Canvas -->
                    <div class="panel-section" id="msg-canvas-section">
                        <h4 class="panel-title">EMAIL CONTENT (DRAG TO REORDER)</h4>
                        <div id="msg-builder-canvas" class="builder-canvas">
                            <!-- Blocks injected here -->
                        </div>
                    </div>

                    <!-- Element Properties (hidden by default) -->
                    <div class="panel-section" id="msg-properties-section" style="display: none;">
                        <div class="properties-header"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h4 class="panel-title" style="margin-bottom: 0;">ELEMENT SETTINGS</h4>
                            <button class="btn-icon" onclick="closeMsgProperties()"><i
                                    class="fas fa-times"></i></button>
                        </div>
                        <div id="msg-properties-content">
                            <!-- Block-specific controls injected here -->
                        </div>
                        <button class="btn btn-outline btn-block" style="margin-top: 20px;"
                            onclick="closeMsgProperties()"><i class="fas fa-check"></i> Done Editing</button>
                    </div>
                </div>

                <!-- CENTER PANEL: Preview -->
                <div class="builder-panel-center">
                    <div id="msg-email-preview-wrapper" class="preview-desktop">
                        <div id="msg-email-preview-container">
                            <!-- Preview blocks rendered here -->
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Config + Add Elements + Actions -->
                <div class="builder-panel-right">
                    <div class="device-toggles">
                        <button class="active" id="msg-preview-desktop-btn"
                            onclick="setMsgPreviewSize('desktop', this)"><i class="fas fa-desktop"></i> Desktop</button>
                        <button id="msg-preview-tablet-btn" onclick="setMsgPreviewSize('tablet', this)"><i
                                class="fas fa-tablet-alt"></i> Tablet</button>
                        <button id="msg-preview-mobile-btn" onclick="setMsgPreviewSize('mobile', this)"><i
                                class="fas fa-mobile-alt"></i> Mobile</button>
                    </div>

                    <!-- Message Config -->
                    <div class="panel-section">
                        <h4 class="panel-title">MESSAGE CONFIG</h4>

                        <div class="form-group">
                            <label class="form-label">Subject Line</label>
                            <input type="text" id="email-msg-subject" class="block-input"
                                value="HeyDream Travel &amp; Tours Update" oninput="debouncedSaveMsgState()" />
                        </div>
                    </div>

                    <!-- Business Partners -->
                    <div class="panel-section">
                        <h4 class="panel-title">BUSINESS PARTNERS</h4>
                        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:12px;">Manage the email list
                            of business partners for direct dispatch.</p>
                        <button class="btn btn-outline btn-block" onclick="openBusinessPartnersModal()"
                            style="border-color:#003580; color:#003580;">
                            <i class="fas fa-user-tie"></i> Manage Business Partners
                        </button>
                        <div id="msg-partners-count"
                            style="font-size:0.78rem; color:var(--text-muted); text-align:center; margin-top:8px;">0
                            partners saved</div>
                    </div>

                    <!-- Add Blocks -->
                    <div class="panel-section">
                        <h4 class="panel-title">ADD ELEMENTS</h4>
                        <div class="blocks-grid">
                            <div class="block-item" onclick="addMsgBlock('header')"><i class="fas fa-heading"></i>
                                Header</div>
                            <div class="block-item" onclick="addMsgBlock('text')"><i class="fas fa-font"></i> Text</div>
                            <div class="block-item" onclick="addMsgBlock('image')"><i class="fas fa-image"></i> Image
                            </div>
                            <div class="block-item" onclick="addMsgBlock('button')"><i class="fas fa-link"></i> Button
                            </div>
                            <div class="block-item" onclick="addMsgBlock('divider')"><i class="fas fa-minus"></i>
                                Divider</div>
                            <div class="block-item" onclick="addMsgBlock('footer')"><i class="fas fa-shoe-prints"></i>
                                Footer</div>
                        </div>
                    </div>

                    <!-- History -->
                    <div class="panel-section">
                        <h4 class="panel-title">HISTORY</h4>
                        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                            <button class="btn btn-outline" id="msg-undo-btn" onclick="undoMsgState()" style="flex: 1;"
                                title="Undo (Ctrl+Z)" disabled><i class="fas fa-undo"></i> Undo</button>
                            <button class="btn btn-outline" id="msg-redo-btn" onclick="redoMsgState()" style="flex: 1;"
                                title="Redo (Ctrl+Y)" disabled><i class="fas fa-redo"></i> Redo</button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="panel-section">
                        <h4 class="panel-title">ACTIONS</h4>
                        <button class="btn btn-primary btn-block" id="email-msg-send-btn"
                            onclick="openAudiencePickerModal()"><i class="fas fa-paper-plane"></i> Send Message</button>
                        <button class="btn btn-outline btn-block spam-btn" onclick="checkMsgSpamScore()"><i
                                class="fas fa-shield-virus"></i> Spam Check</button>
                        <button class="btn btn-outline btn-block" onclick="switchSection('emails')"
                            style="margin-top: 4px;"><i class="fas fa-arrow-left"></i> Back to Campaign Builder</button>
                    </div>

                </div>
            </div>
        </section>
        <!-- ANALYTICS SECTION -->
        <section id="analytics" class="section-container">
            <div class="builder-nav">
                <a href="#" class="nav-tab" onclick="switchSection('emails')"><i class="fas fa-pencil-alt"></i> Campaign
                    Builder</a>
                <a href="#" class="nav-tab" onclick="switchSection('email-message')"
                    style="color: #003580; font-weight: 700;"><i class="fas fa-envelope-open-text"></i> Email
                    Message</a>
                <a href="#" class="nav-tab active" onclick="switchSection('analytics')"><i
                        class="fas fa-chart-line"></i> Analytics &amp; History</a>
                <a href="#" class="nav-tab" onclick="switchSection('templates')"><i class="fas fa-layer-group"></i>
                    Templates</a>
            </div>
            <div class="page-header">
                <h1>Performance Analytics</h1>
            </div>
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));">
                <div class="card">
                    <h3 class="card-title">Inquiry Sources (Social Media)</h3>
                    <div style="height: 350px; position: relative; padding: 10px;">
                        <canvas id="sourceChart"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3 class="card-title">Inquiry Status Breakdown</h3>
                    <div style="height: 350px; position: relative; padding: 10px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="card" style="grid-column: 1 / -1;">
                    <div
                        style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
                        <div>
                            <h3 class="card-title" style="margin: 0;"><i class="fas fa-mouse-pointer"></i> Social Media
                                Engagement (Clicks)</h3>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 4px 0 0;">Visual breakdown of
                                clicks. Select a campaign or date to analyze performance.</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn btn-outline" onclick="loadGlobalAnalytics()"
                                style="border-radius: 12px; font-weight: 600; font-size: 0.85rem; border-color: #003580; color: #003580; background: #f0f7ff;">
                                <i class="fas fa-globe"></i> View All Total Clicks
                            </button>
                        </div>
                    </div>

                    <div class="analytics-layout" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
                        <!-- Chart Area -->
                        <div style="height: 400px; position: relative;">
                            <canvas id="engagementChart"></canvas>
                        </div>

                        <!-- Calendar Area -->
                        <div class="calendar-card"
                            style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px;">
                            <div class="calendar-header"
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 id="calendar-month"
                                    style="font-size: 1rem; font-weight: 700; color: var(--primary);">Month Year</h4>
                                <div style="display: flex; gap: 5px;">
                                    <button onclick="changeMonth(-1)" class="btn-icon"><i
                                            class="fas fa-chevron-left"></i></button>
                                    <button onclick="changeMonth(1)" class="btn-icon"><i
                                            class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>
                            <div class="calendar-grid"
                                style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center;">
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8;">S</div>
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8;">M</div>
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8;">T</div>
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8;">W</div>
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8;">T</div>
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8;">F</div>
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8;">S</div>
                            </div>
                            <div id="calendar-days"
                                style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; margin-top: 10px;">
                                <!-- Days injected via JS -->
                            </div>
                            <div id="selected-day-campaigns"
                                style="margin-top: 20px; display: none; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                                <h5 style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 10px;">CAMPAIGNS
                                    ON THIS DAY:</h5>
                                <div id="day-campaign-list" style="display: flex; flex-direction: column; gap: 8px;">
                                    <!-- List injected via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TEMPLATES SECTION -->
        <section id="templates" class="section-container">
            <div class="builder-nav">
                <a href="#" class="nav-tab" onclick="switchSection('emails')"><i class="fas fa-pencil-alt"></i> Campaign
                    Builder</a>
                <a href="#" class="nav-tab" onclick="switchSection('email-message')"
                    style="color: #003580; font-weight: 700;"><i class="fas fa-envelope-open-text"></i> Email
                    Message</a>
                <a href="#" class="nav-tab" onclick="switchSection('analytics')"><i class="fas fa-chart-line"></i>
                    Analytics &amp; History</a>
                <a href="#" class="nav-tab active" onclick="switchSection('templates')"><i
                        class="fas fa-layer-group"></i> Templates</a>
            </div>
            <div class="page-header">
                <h1>Email Templates</h1>
                <button class="btn btn-primary" onclick="startNewTemplate()"><i class="fas fa-plus"></i> Create
                    Template</button>
            </div>
            <div id="template-list" class="stats-grid"
                style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                <!-- Templates will be loaded here via JS -->
            </div>
        </section>

        <!-- AI CHATS SECTION -->
        <section id="ai-chats" class="section-container">
            <div class="page-header">
                <h1>AI Live Chat Monitor</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="loadChatSessions()"><i class="fas fa-sync"></i> Refresh
                        Chats</button>
                </div>
            </div>

            <div class="chat-manager-grid">
                <!-- Chat Sessions List -->
                <div class="card chat-sessions-card">
                    <h3 class="card-title"><i class="fas fa-users"></i> Active Sessions</h3>
                    <div id="ai-sessions-list" class="ai-sessions-list">
                        <div class="empty-state">Loading chat sessions...</div>
                    </div>
                </div>

                <!-- Active Chat Window -->
                <div class="card chat-window-card">
                    <div id="active-chat-header" class="active-chat-header">
                        <div class="chat-user-info">
                            <i class="fas fa-robot" id="active-chat-icon"
                                style="font-size: 1.5rem; color: #3b82f6;"></i>
                            <div>
                                <h3 id="active-chat-user">Select a Chat</h3>
                                <span id="active-chat-status">No active session</span>
                            </div>
                        </div>
                        <div class="chat-actions" style="display: flex; gap: 10px;">
                            <button class="btn btn-outline" id="takeover-btn" style="display:none;"
                                onclick="takeoverChat()"><i class="fas fa-hand-paper"></i> Take Over Chat</button>
                            <button class="btn btn-outline" id="transfer-btn"
                                style="display:none; border-color: #e2e8f0; color: #475569;"
                                onclick="openTransferModal()"><i class="fas fa-random"></i> Agent Transfer</button>
                            <button class="btn btn-outline" id="delete-chat-btn"
                                style="display:none; color: #ef4444; border-color: #fecaca;" onclick="deleteChat()"><i
                                    class="fas fa-trash"></i> Delete Chat</button>
                        </div>
                    </div>

                    <div id="admin-chat-messages" class="admin-chat-messages">
                        <div class="empty-chat">
                            <i class="fas fa-comments"></i>
                            <p>Select a session from the list to view or join the conversation.</p>
                        </div>
                    </div>

                    <div class="admin-chat-footer">
                        <div class="admin-chat-input-wrapper">
                            <textarea id="admin-chat-input" placeholder="Type a message to the customer..."
                                disabled></textarea>
                            <button id="admin-send-btn" class="btn btn-primary" disabled onclick="sendAdminReply()"><i
                                    class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- REPORTED ISSUES SECTION -->
        <section id="reported-issues" class="section-container">
            <div class="page-header">
                <h1>Reported Issue Tickets</h1>
                <div class="header-actions">
                    <a href="../support.php" target="_blank" class="btn btn-outline"
                        style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: #1e293b; border: 1px solid #cbd5e1; padding: 10px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;"><i
                            class="fas fa-external-link-alt"></i> Open Support Form</a>
                    <button class="btn btn-primary" onclick="loadReportedIssues()"><i class="fas fa-sync"></i> Refresh
                        Tickets</button>
                </div>
            </div>

            <!-- STATUS FILTER TABS -->
            <div class="issue-filter-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <button class="filter-tab" onclick="setIssueFilter('All')" id="tab-all"
                    style="padding: 10px 20px; border-radius: 8px; border: 1.5px solid #cbd5e1; background: #fff; color: #1e293b; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; outline: none;">
                    <i class="fas fa-list"></i> All Issues <span class="badge" id="badge-all"
                        style="background: #e2e8f0; color: #1e293b; padding: 2px 7px; border-radius: 6px; font-size: 0.68rem; font-weight: 700;">0</span>
                </button>
                <button class="filter-tab" onclick="setIssueFilter('Pending')" id="tab-pending"
                    style="padding: 10px 20px; border-radius: 8px; border: 1.5px solid #fee2e2; background: #fff; color: #ef4444; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; outline: none;">
                    <i class="fas fa-clock"></i> Pending <span class="badge" id="badge-pending"
                        style="background: #fee2e2; color: #ef4444; padding: 2px 7px; border-radius: 6px; font-size: 0.68rem; font-weight: 700;">0</span>
                </button>
                <button class="filter-tab" onclick="setIssueFilter('In Progress')" id="tab-progress"
                    style="padding: 10px 20px; border-radius: 8px; border: 1.5px solid #fef3c7; background: #fff; color: #d97706; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; outline: none;">
                    <i class="fas fa-spinner"></i> In Progress <span class="badge" id="badge-progress"
                        style="background: #fef3c7; color: #d97706; padding: 2px 7px; border-radius: 6px; font-size: 0.68rem; font-weight: 700;">0</span>
                </button>
                <button class="filter-tab" onclick="setIssueFilter('Resolved')" id="tab-resolved"
                    style="padding: 10px 20px; border-radius: 8px; border: 1.5px solid #d1fae5; background: #fff; color: #059669; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; outline: none;">
                    <i class="fas fa-check-circle"></i> Resolved <span class="badge" id="badge-resolved"
                        style="background: #d1fae5; color: #059669; padding: 2px 7px; border-radius: 6px; font-size: 0.68rem; font-weight: 700;">0</span>
                </button>
            </div>

            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: #1e40af; font-size: 0.85rem;">
                <i class="fas fa-circle-info"></i>
                <span>Click any record below to view its full details — including the attached screenshot, if one was submitted.</span>
            </div>

            <div class="card">
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr
                                style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 0.85rem;">
                                <th style="padding: 15px 10px;">ID</th>
                                <th style="padding: 15px 10px;">Reporter</th>
                                <th style="padding: 15px 10px;">Category</th>
                                <th style="padding: 15px 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span>Severity</span>
                                        <label style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-weight: 500; font-size: 0.68rem; color: #64748b; text-transform: none; letter-spacing: normal;" title="Sort Critical severity records to the top">
                                            <span style="position: relative; display: inline-block; width: 28px; height: 15px; flex-shrink: 0;">
                                                <input type="checkbox" id="criticalFirstToggle" onchange="toggleCriticalFirstSort()" style="opacity: 0; width: 0; height: 0; position: absolute;">
                                                <span class="critical-toggle-track" style="position: absolute; inset: 0; background: #cbd5e1; border-radius: 999px; transition: 0.2s; pointer-events: none;"></span>
                                                <span class="critical-toggle-thumb" style="position: absolute; height: 11px; width: 11px; left: 2px; top: 2px; background: white; border-radius: 50%; transition: 0.2s; pointer-events: none; box-shadow: 0 1px 2px rgba(0,0,0,0.2);"></span>
                                            </span>
                                            Critical First
                                        </label>
                                    </div>
                                </th>
                                <th style="padding: 15px 10px;">Description</th>
                                <th style="padding: 15px 10px;">Status</th>
                                <th style="padding: 15px 10px;">Submitted</th>
                                <th style="padding: 15px 10px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reported-issues-list">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                    <p>Loading issue tickets...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- VOUCHER MANAGEMENT SECTION -->
        <section id="vouchers" class="section-container" style="display:none;">
            <div class="page-header" style="margin-bottom: 24px;">
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-weight: 700; color: #0f172a; margin: 0;">Voucher Wallet & Campaigns</h1>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 4px 0 0;">Create and target promotional vouchers across modules</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddVoucherModal()" style="background: #003580; color: white; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 12px 20px; border-radius: 12px; border: none; cursor: pointer; transition: all 0.2s;">
                        <i class="fas fa-plus"></i> Create Voucher
                    </button>
                </div>
            </div>

            <!-- SEARCH AND FILTER BAR -->
            <div class="card" style="background: white; border-radius: 20px; padding: 20px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px;">
                <div style="display: flex; gap: 15px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <div style="display: flex; gap: 12px; align-items: center; flex: 1; min-width: 300px; flex-wrap: wrap;">
                        <!-- Search input -->
                        <div style="position: relative; flex: 1; min-width: 200px;">
                            <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.9rem;"></i>
                            <input type="text" id="voucher-search" oninput="filterVouchers()" placeholder="Search voucher name or code..." style="width: 100%; padding: 11px 14px 11px 40px; border-radius: 12px; border: 1.5px solid #e2e8f0; font-family: inherit; font-size: 0.88rem; outline: none; transition: all 0.2s; background: #f8fafc;">
                        </div>
                        <!-- Status filter -->
                        <select id="voucher-status-filter" onchange="filterVouchers()" style="padding: 11px 14px; border-radius: 12px; border: 1.5px solid #e2e8f0; font-family: inherit; font-size: 0.88rem; outline: none; background: #fff; cursor: pointer; min-width: 130px;">
                            <option value="all">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <!-- Target Audience filter -->
                        <select id="voucher-audience-filter" onchange="filterVouchers()" style="padding: 11px 14px; border-radius: 12px; border: 1.5px solid #e2e8f0; font-family: inherit; font-size: 0.88rem; outline: none; background: #fff; cursor: pointer; min-width: 150px;">
                            <option value="all">All Audiences</option>
                            <option value="everyone">Everyone</option>
                            <option value="logged_in_only">Logged In Only</option>
                            <option value="first_time_customers">First Time Customers</option>
                            <option value="returning_customers">Returning Customers</option>
                            <option value="vip_members">VIP Members</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- VOUCHERS LIST TABLE -->
            <div class="card" style="background: white; border-radius: 20px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden;">
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid #f1f5f9; color: #475569; font-weight: 700; font-size: 0.82rem; background: #f8fafc; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th style="padding: 18px 20px;">Voucher details</th>
                                <th style="padding: 18px 20px;">Discount value</th>
                                <th style="padding: 18px 20px;">Target modules</th>
                                <th style="padding: 18px 20px;">Redemptions</th>
                                <th style="padding: 18px 20px;">Audience & Collection</th>
                                <th style="padding: 18px 20px;">Status</th>
                                <th style="padding: 18px 20px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vouchers-list-tbody">
                            <!-- Dynamic Content -->
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px; color: #003580;"></i>
                                    <p>Loading vouchers list...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal for Inquiry Details -->
    <div id="inquiry-modal" class="modal-overlay"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="card" style="width: 600px; max-height: 90vh; overflow-y: auto;">
            <div class="page-header">
                <h2>Inquiry Details</h2>
                <button class="btn btn-outline" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div id="modal-content-area">
                <!-- Content injected via JS -->
            </div>
        </div>
    </div>



    <!-- Modal for Unlock Manager (Super Admin Only) -->
    <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
        <div id="unlock-modal" class="modal-overlay"
            style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; justify-content: center; align-items: center;">
            <div class="card"
                style="width: 800px; max-height: 90vh; overflow-y: auto; background: #fff; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <div class="page-header"
                    style="background: #f8fafc; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; border-radius: 20px 20px 0 0; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin: 0; color: #1e293b; font-size: 1.5rem; font-weight: 800;">Element Unlock Manager
                        </h2>
                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #64748b;">Manage Header and Footer access
                            permissions</p>
                    </div>
                    <button class="btn btn-outline" onclick="closeUnlockModal()"
                        style="border-radius: 12px; padding: 10px;"><i class="fas fa-times"></i></button>
                </div>
                <div style="padding: 30px;">
                    <div id="unlock-request-list" style="display: flex; flex-direction: column; gap: 15px;">
                        <!-- Requests injected via JS -->
                        <div style="text-align: center; padding: 40px; color: #94a3b8;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 15px;"></i>
                            <p>Loading requests...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- AUDIENCE PICKER MODAL -->
    <div id="msg-audience-modal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9100; align-items:center; justify-content:center;">
        <div
            style="background:white; border-radius:20px; padding:36px; width:520px; max-width:92vw; box-shadow:0 25px 60px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                <div>
                    <h2 style="font-size:1.25rem; font-weight:700; color:#0f172a; margin:0;"><i
                            class="fas fa-paper-plane" style="color:#003580;"></i> Send Message To</h2>
                    <p style="font-size:0.82rem; color:#64748b; margin:4px 0 0;">Choose who will receive this email</p>
                </div>
                <button onclick="closeAudiencePickerModal()"
                    style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#94a3b8;"><i
                        class="fas fa-times"></i></button>
            </div>

            <!-- Audience Options -->
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px;">
                <label id="aud-opt-inquiries" onclick="selectAudience('inquiries')"
                    style="display:flex; align-items:center; gap:14px; padding:16px 18px; border:2px solid #e2e8f0; border-radius:12px; cursor:pointer; transition:all 0.2s;">
                    <div
                        style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-users" style="color:#003580;"></i>
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:0.95rem; color:#0f172a;">Inquiry Leads Only</div>
                        <div style="font-size:0.78rem; color:#64748b;">Clients who submitted travel inquiries</div>
                    </div>
                </label>
                <label id="aud-opt-website" onclick="selectAudience('website')"
                    style="display:flex; align-items:center; gap:14px; padding:16px 18px; border:2px solid #e2e8f0; border-radius:12px; cursor:pointer; transition:all 0.2s;">
                    <div
                        style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-globe" style="color:#16a34a;"></i>
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:0.95rem; color:#0f172a;">Website Users Only</div>
                        <div style="font-size:0.78rem; color:#64748b;">Registered customers on the website</div>
                    </div>
                </label>
                <label id="aud-opt-partners" onclick="selectAudience('partners')"
                    style="display:flex; align-items:center; gap:14px; padding:16px 18px; border:2px solid #e2e8f0; border-radius:12px; cursor:pointer; transition:all 0.2s;">
                    <div
                        style="width:40px; height:40px; border-radius:10px; background:#fdf4ff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-handshake" style="color:#9333ea;"></i>
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:0.95rem; color:#0f172a;">Business Partners</div>
                        <div style="font-size:0.78rem; color:#64748b;">Send to your saved business partner emails</div>
                    </div>
                </label>
            </div>

            <!-- Partners sub-list (shown only when partners selected) -->
            <div id="aud-partners-list"
                style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:20px; max-height:180px; overflow-y:auto;">
                <p style="font-size:0.8rem; font-weight:600; color:#003580; margin:0 0 10px;"><i
                        class="fas fa-user-tie"></i> Select partners to include:</p>
                <div id="aud-partners-checkboxes" style="display:flex; flex-direction:column; gap:8px;"></div>
                <p id="aud-no-partners-msg" style="font-size:0.8rem; color:#94a3b8; display:none;">No business partners
                    saved. <a href="#" onclick="closeAudiencePickerModal(); openBusinessPartnersModal(); return false;"
                        style="color:#003580;">Add partners first.</a></p>
            </div>

            <div style="display:flex; gap:10px;">
                <button onclick="closeAudiencePickerModal()" class="btn btn-outline" style="flex:1;">Cancel</button>
                <button id="aud-confirm-btn" onclick="confirmSendEmailMessage()" class="btn btn-primary" style="flex:2;"
                    disabled><i class="fas fa-paper-plane"></i> Confirm &amp; Send</button>
            </div>
        </div>
    </div>

    <!-- BUSINESS PARTNERS MANAGEMENT MODAL -->
    <div id="msg-biz-partners-modal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9200; align-items:center; justify-content:center;">
        <div
            style="background:white; border-radius:20px; padding:36px; width:500px; max-width:92vw; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 25px 60px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h2 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin:0;"><i class="fas fa-user-tie"
                            style="color:#003580;"></i> Business Partners</h2>
                    <p style="font-size:0.8rem; color:#64748b; margin:4px 0 0;">Manage partner emails for direct
                        dispatch</p>
                </div>
                <button onclick="closeBusinessPartnersModal()"
                    style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#94a3b8;"><i
                        class="fas fa-times"></i></button>
            </div>

            <!-- Add new partner -->
            <div style="display:flex; gap:8px; margin-bottom:20px;">
                <input type="email" id="new-partner-email-input" class="block-input" placeholder="partner@company.com"
                    style="flex:1; margin-top:0;" onkeydown="if(event.key==='Enter') addBusinessPartner();" />
                <input type="text" id="new-partner-name-input" class="block-input" placeholder="Name (optional)"
                    style="flex:1; margin-top:0;" onkeydown="if(event.key==='Enter') addBusinessPartner();" />
                <button onclick="addBusinessPartner()" class="btn btn-primary" style="flex-shrink:0;"><i
                        class="fas fa-plus"></i></button>
            </div>

            <!-- Partners list -->
            <div id="biz-partners-list"
                style="flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:8px; min-height:80px;">
                <p id="no-biz-partners-msg" style="font-size:0.85rem; color:#94a3b8; text-align:center; padding:20px;">
                    No business partners added yet.</p>
            </div>

            <div style="margin-top:20px; padding-top:16px; border-top:1px solid #f1f5f9;">
                <button onclick="closeBusinessPartnersModal()" class="btn btn-primary btn-block"><i
                        class="fas fa-check"></i> Done</button>
            </div>
        </div>
    </div>

    <!-- VOUCHER FORM MODAL -->
    <div id="voucher-form-modal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9200; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
        <div style="background: white; border-radius: 24px; padding: 0; width: 900px; max-width: 95vw; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0,0,0,0.2); overflow: hidden;">
            <div style="background: #f8fafc; padding: 24px 32px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 id="voucher-modal-title" style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 700; color: #0f172a; margin: 0;"><i class="fas fa-ticket-alt" style="color: #003580; margin-right: 8px;"></i> Create New Voucher</h2>
                    <p style="font-size: 0.8rem; color: #64748b; margin: 4px 0 0;">Define discount, limit rules, and target modules/packages</p>
                </div>
                <button onclick="closeVoucherModal()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #94a3b8; transition: all 0.2s;"><i class="fas fa-times"></i></button>
            </div>
            
            <form id="voucher-form" onsubmit="saveVoucherForm(event)" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; margin: 0;">
                <input type="hidden" id="voucher-id-input" name="id" value="">
                
                <div style="padding: 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <!-- LEFT COLUMN: General & Discount Rules -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: #003580; margin: 0 0 15px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 8px;"><i class="fas fa-cog"></i> General Rules</h3>
                            
                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Voucher Name <span style="color: #ef4444;">*</span></label>
                                <input type="text" id="v-name" name="voucher_name" required placeholder="e.g. Summer Special 10% Off" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                            </div>
                            
                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Voucher Code <span style="color: #ef4444;">*</span></label>
                                <input type="text" id="v-code" name="voucher_code" required placeholder="e.g. SUMMER10" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none; text-transform: uppercase;">
                            </div>

                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Description</label>
                                <textarea id="v-desc" name="description" placeholder="e.g. Get 10% off on all local flight packages. Min spend PHP 5,000." style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none; height: 70px; resize: none;"></textarea>
                            </div>
                        </div>

                        <div>
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: #003580; margin: 0 0 15px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 8px;"><i class="fas fa-tags"></i> Discount Settings</h3>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Discount Type <span style="color: #ef4444;">*</span></label>
                                    <select id="v-discount-type" name="discount_type" onchange="toggleDiscountFields()" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none; background: white; cursor: pointer;">
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed_amount">Fixed Amount</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Discount Value <span style="color: #ef4444;">*</span></label>
                                    <input type="number" id="v-discount-value" name="discount_value" required min="0.01" step="0.01" placeholder="e.g. 10" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Minimum Spend</label>
                                    <input type="number" id="v-min-spend" name="minimum_spend" min="0" step="0.01" value="0.00" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                </div>
                                <div id="max-discount-wrapper">
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Maximum Discount</label>
                                    <input type="number" id="v-max-discount" name="maximum_discount" min="0" step="0.01" placeholder="e.g. 1000 (Optional)" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Discount applies to first N travelers</label>
                                    <input type="number" id="v-max-discounted-travelers" name="max_discounted_travelers" min="0" value="0" placeholder="0 = no limit" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                    <p style="font-size:0.78rem; color:#64748b; margin-top:6px;">Set the maximum number of travelers eligible for this voucher discount. Leave at 0 to apply to all travelers.</p>
                                </div>
                            </div>
                            <p style="font-size:0.78rem; color:#64748b; margin-top:6px;">Enter fixed amounts in the same currency as the targeted module. For foreign destinations, use USD values.</p>
                        </div>

                        <div>
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: #003580; margin: 0 0 15px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 8px;"><i class="fas fa-history"></i> Limits & Validity</h3>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Total Usage Limit</label>
                                    <input type="number" id="v-max-total" name="max_total_redemptions" min="0" value="0" placeholder="0 = Unlimited" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Limit Per User</label>
                                    <input type="number" id="v-max-user" name="max_redemptions_per_user" min="1" value="1" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Start Date & Time <span style="color: #ef4444;">*</span></label>
                                    <input type="datetime-local" id="v-start-date" name="start_date" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                    <p style="font-size:0.78rem; color:#64748b; margin-top:6px;">Choose the exact date and time when this voucher becomes available.</p>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">End Date & Time <span style="color: #ef4444;">*</span></label>
                                    <input type="datetime-local" id="v-end-date" name="end_date" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none;">
                                    <p style="font-size:0.78rem; color:#64748b; margin-top:6px;">Choose the exact date and time when this voucher expires.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- RIGHT COLUMN: Targeting & Wallet UI -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: #003580; margin: 0 0 15px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 8px;"><i class="fas fa-users"></i> Audience & Presentation</h3>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Target Audience</label>
                                    <select id="v-audience" name="audience" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none; background: white; cursor: pointer;">
                                        <option value="everyone">Everyone</option>
                                        <option value="logged_in_only">Logged In Users</option>
                                        <option value="first_time_customers">First Time Customers</option>
                                        <option value="returning_customers">Returning Customers</option>
                                        <option value="vip_members">VIP Members</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Collection Method</label>
                                    <select id="v-collection-method" name="collection_method" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none; background: white; cursor: pointer;">
                                        <option value="user_collect" selected>User Claimable (Save to Wallet First)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Status</label>
                                    <select id="v-status" name="status" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none; background: white; cursor: pointer;">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Wallet Color Theme</label>
                                    <select id="v-color" name="color_theme" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-family: inherit; font-size: 0.88rem; outline: none; background: white; cursor: pointer;">
                                        <option value="#003580" style="background: #003580; color: white;">HeyDream Blue</option>
                                        <option value="#ef4444" style="background: #ef4444; color: white;">Crimson Red</option>
                                        <option value="#10b981" style="background: #10b981; color: white;">Emerald Green</option>
                                        <option value="#f59e0b" style="background: #f59e0b; color: white;">Amber Yellow</option>
                                        <option value="#8b5cf6" style="background: #8b5cf6; color: white;">Royal Purple</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: #003580; margin: 0 0 15px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 8px;"><i class="fas fa-bullseye"></i> Targeting Modules</h3>
                            
                            <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 8px;">Select eligible travel modules: <span style="font-weight: 400; color: #64748b;">(Leave all unchecked for site-wide)</span></label>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; max-height: 110px; overflow-y: auto; padding: 10px; border: 1.5px solid #e2e8f0; border-radius: 12px; background: #f8fafc; margin-bottom: 15px;">
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="flash_deals" onchange="loadTargetPackages()" style="accent-color: #003580;"> Flash Deals</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="local_destinations" onchange="loadTargetPackages()" style="accent-color: #003580;"> Local Destinations</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="foreign_destinations" onchange="loadTargetPackages()" style="accent-color: #003580;"> Foreign Destinations</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="flights" onchange="loadTargetPackages()" style="accent-color: #003580;"> Flights</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="flight_packages" onchange="loadTargetPackages()" style="accent-color: #003580;"> Flight Packages</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="cruises" onchange="loadTargetPackages()" style="accent-color: #003580;"> Cruises</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="experiences" onchange="loadTargetPackages()" style="accent-color: #003580;"> Experiences</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="premium_services" onchange="loadTargetPackages()" style="accent-color: #003580;"> Premium Services</label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #334155; cursor: pointer;"><input type="checkbox" name="targets[]" value="visa_services" onchange="loadTargetPackages()" style="accent-color: #003580;"> Visa Services</label>
                            </div>

                            <!-- Package-Level Targeting Section -->
                            <div id="package-targeting-container" style="display: none;">
                                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Target Specific Packages: <span style="font-weight: 400; color: #64748b;">(Optional - select only specific packages, or leave unchecked for all packages in the module)</span></label>
                                <div id="target-packages-list" style="max-height: 120px; overflow-y: auto; padding: 10px; border: 1.5px solid #e2e8f0; border-radius: 12px; background: #fff; display: flex; flex-direction: column; gap: 6px;">
                                    <!-- Dynamic Checklist items loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="background: #f8fafc; padding: 20px 32px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="closeVoucherModal()" class="btn btn-outline" style="padding: 10px 20px; border-radius: 10px; border: 1.5px solid #cbd5e1; background: white; color: #334155; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #003580; color: white; padding: 10px 24px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; transition: all 0.2s;">Save Voucher</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reported Issues: screenshot zoom lightbox (shared across all tickets) -->
    <div id="screenshotZoomOverlay" onclick="closeScreenshotZoom()">
        <button id="screenshotZoomClose" type="button" aria-label="Close" title="Close" onclick="closeScreenshotZoom()">&times;</button>
        <img id="screenshotZoomImg" alt="Screenshot (zoomed)" onclick="event.stopPropagation()">
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        const currentAdminRole = "<?php echo $_SESSION['admin_role'] ?? 'admin'; ?>";
    </script>
    <script src="js/marketing.js?v=<?php echo time(); ?>"></script>
    <script src="js/vouchers.js?v=<?php echo time(); ?>"></script>
</body>

</html>
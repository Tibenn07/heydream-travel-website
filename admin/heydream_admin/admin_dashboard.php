<?php
// admin_dashboard.php - Super Admin Dashboard
require_once 'db_config.php';

// Enable output buffering so included page redirects can send headers safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    session_start();
}

// Bypass the old login gate and open the dashboard directly
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_name'] = 'Admin';
    $_SESSION['admin_role'] = 'super_admin';
}

$conn = getDBConnection();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $currentPage = $_GET['page'] ?? 'dashboard';
    $pageNum = $_POST['page_num'] ?? 1;

    // Package approval actions are handled by pages/approvals.php.
    // Skip the generic dashboard handler so the page-specific logic can run.
    if (!in_array($action, ['approve_package', 'reject_package', 'delete_package'])) {
        if ($action === 'approve_application') {
            $stmt = $conn->prepare("UPDATE partner_applications SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'reject_application') {
            $reason = $_POST['rejection_reason'] ?? '';
            $stmt = $conn->prepare("UPDATE partner_applications SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $reason, $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'delete_application') {
            $stmt = $conn->prepare("DELETE FROM partner_applications WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'delete_partner') {
            $stmt = $conn->prepare("DELETE FROM partner_applications WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'toggle_partner_status') {
            $newStatus = $_POST['new_status'] ?? '';
            $stmt = $conn->prepare("UPDATE partner_applications SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'update_customer_status') {
            $newStatus = $_POST['new_status'] ?? '';
            $stmt = $conn->prepare("UPDATE customer_reports SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $id);
            $stmt->execute();
            $stmt->close();
            
            // Redirect back to the same page with filters preserved
            $redirectUrl = "admin_dashboard.php?page=customer&p=" . $pageNum;
            $statusFilter = $_POST['status'] ?? '';
            $categoryFilter = $_POST['category'] ?? '';
            $priorityFilter = $_POST['priority'] ?? '';
            $searchFilter = $_POST['search'] ?? '';
            if ($statusFilter && $statusFilter !== 'all') $redirectUrl .= "&status=" . urlencode($statusFilter);
            if ($categoryFilter && $categoryFilter !== 'all') $redirectUrl .= "&category=" . urlencode($categoryFilter);
            if ($priorityFilter && $priorityFilter !== 'all') $redirectUrl .= "&priority=" . urlencode($priorityFilter);
            if ($searchFilter) $redirectUrl .= "&search=" . urlencode($searchFilter);
            header("Location: " . $redirectUrl);
            exit;
        } elseif ($action === 'delete_customer') {
            // First, get the screenshot path to delete the file
            $stmt = $conn->prepare("SELECT screenshot_path FROM customer_reports WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $report = $result->fetch_assoc();
            $stmt->close();
            
            // Delete the screenshot file if it exists
            if ($report && !empty($report['screenshot_path'])) {
                $filePath = __DIR__ . '/uploads/reports/' . $report['screenshot_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Delete the report from database
            $stmt = $conn->prepare("DELETE FROM customer_reports WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // Redirect back to the same page with filters preserved
            $redirectUrl = "admin_dashboard.php?page=customer&p=" . $pageNum;
            $statusFilter = $_POST['status'] ?? '';
            $categoryFilter = $_POST['category'] ?? '';
            $priorityFilter = $_POST['priority'] ?? '';
            $searchFilter = $_POST['search'] ?? '';
            if ($statusFilter && $statusFilter !== 'all') $redirectUrl .= "&status=" . urlencode($statusFilter);
            if ($categoryFilter && $categoryFilter !== 'all') $redirectUrl .= "&category=" . urlencode($categoryFilter);
            if ($priorityFilter && $priorityFilter !== 'all') $redirectUrl .= "&priority=" . urlencode($priorityFilter);
            if ($searchFilter) $redirectUrl .= "&search=" . urlencode($searchFilter);
            header("Location: " . $redirectUrl);
            exit;
        }
 
        header("Location: admin_dashboard.php?page=" . $currentPage . "&p=" . $pageNum);
        exit;
    }
}

// Get notification counts
$notificationCounts = getNotificationCounts($conn);
$notificationItems = getNotificationItems($conn, 10);

// Check which tables exist
$existingTables = [];
$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_array()) {
        $existingTables[] = $row[0];
    }
}

// Get statistics with error handling
$stats = [];
$stats['pending_applications'] = 0;
$stats['total_applications'] = 0;
$stats['approved_applications'] = 0;
$stats['rejected_applications'] = 0;
$stats['customer_reports'] = 0;
$stats['partnership_reports'] = 0;
$stats['system_reports'] = 0;
$stats['pending_approvals'] = 0;
$stats['total_users'] = 0;
$stats['active_partners'] = 0;
$stats['new_applications_today'] = 0;

if (in_array('partner_applications', $existingTables)) {
    $stats['pending_applications'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
    $stats['total_applications'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications")->fetch_assoc()['count'] ?? 0;
    $stats['approved_applications'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'approved'")->fetch_assoc()['count'] ?? 0;
    $stats['rejected_applications'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'rejected'")->fetch_assoc()['count'] ?? 0;
    $stats['new_applications_today'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE DATE(submitted_at) = CURDATE()")->fetch_assoc()['count'] ?? 0;
    $stats['active_partners'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'approved'")->fetch_assoc()['count'] ?? 0;
}

if (in_array('customer_reports', $existingTables)) {
    $stats['customer_reports'] = $conn->query("SELECT COUNT(*) as count FROM customer_reports")->fetch_assoc()['count'] ?? 0;
}

if (in_array('partnership_reports', $existingTables)) {
    $stats['partnership_reports'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports")->fetch_assoc()['count'] ?? 0;
}

if (in_array('system_reports', $existingTables)) {
    $stats['system_reports'] = $conn->query("SELECT COUNT(*) as count FROM system_reports")->fetch_assoc()['count'] ?? 0;
}

if (in_array('approvals', $existingTables)) {
    $stats['pending_approvals'] = $conn->query("SELECT COUNT(*) as count FROM approvals WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
}

// Get pending package approvals count for sidebar badge
$pendingPackageApprovals = 0;
if (in_array('approvals', $existingTables)) {
    $pendingPackageApprovals = $conn->query("SELECT COUNT(*) as count FROM approvals WHERE type = 'package' AND status = 'pending'")->fetch_assoc()['count'] ?? 0;
}

if (in_array('users', $existingTables)) {
    $stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
} else {
    $stats['total_users'] = $stats['total_applications'] + 100;
}

$currentPage = $_GET['page'] ?? 'dashboard';

// Determine which page to load
$pageFile = 'pages/dashboard.php';
switch ($currentPage) {
    case 'applications':
        $pageFile = 'pages/applications.php';
        break;
    case 'partners':
        $pageFile = 'pages/partners.php';
        break;
    case 'partnership_lists':
        $pageFile = 'pages/partnership_lists.php';
        break;
    case 'users':
        $pageFile = 'pages/users.php';
        break;
    case 'customer':
        $pageFile = 'pages/customer_reports.php';
        break;
    case 'partnership':
        $pageFile = 'pages/partnership_reports.php';
        break;
    case 'system':
        $pageFile = 'pages/system_reports.php';
        break;
    case 'approval':
        $pageFile = 'pages/approvals.php';
        break;
    case 'bookings':
        $pageFile = 'pages/bookings.php';
        break;
    default:
        $pageFile = 'pages/dashboard.php';
        break;
}

// Check if the page file exists, if not fallback to dashboard
if (!file_exists($pageFile)) {
    $pageFile = 'pages/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream | Super Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ============================================
           MODERN DASHBOARD STYLES
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f9;
            color: #1a2332;
            display: flex;
            min-height: 100vh;
        }

        /* ============================================
           SIDEBAR
           ============================================ */
        .sidebar {
            width: 260px;
            background: #0b1a33;
            color: #b0c4de;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-header {
            padding: 1.75rem 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }

        .logo-text h1 {
            font-size: 1.3rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .logo-text span {
            font-size: 0.65rem;
            color: #6b8cae;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-weight: 500;
        }

        .nav-menu {
            flex: 1;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .nav-label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #4a6a8a;
            padding: 0.75rem 1rem 0.5rem;
            font-weight: 700;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            color: #8aacce;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            position: relative;
        }

        .nav-item i {
            width: 20px;
            font-size: 1rem;
            text-align: center;
            color: #5a7a9a;
        }

        .nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            color: white;
        }

        .nav-item:hover i {
            color: #60a5fa;
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.15);
            color: white;
        }

        .nav-item.active i {
            color: #3b82f6;
        }

        .nav-item .badge-nav {
            margin-left: auto;
            background: #ef4444;
            color: white;
            font-size: 0.55rem;
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
        }

        .nav-item .badge-nav.green {
            background: #22c55e;
        }

        .sidebar-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #6b8cae;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.5rem 0;
            transition: color 0.2s;
        }

        .logout-btn:hover {
            color: #ef4444;
        }

        .logout-btn i {
            font-size: 0.9rem;
        }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background: #f0f4f9;
        }

        /* ============================================
           TOP HEADER
           ============================================ */
        .top-header {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e8edf5;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(8px);
            background: rgba(255,255,255,0.92);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex: 1;
        }

        .page-title h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0b1a33;
        }

        .page-title p {
            font-size: 0.8rem;
            color: #6b8cae;
        }

        .search-container {
            display: flex;
            align-items: center;
            background: #f0f4f9;
            border-radius: 40px;
            padding: 0.45rem 1rem;
            border: 1px solid #e8edf5;
            transition: all 0.2s;
            flex: 1;
            max-width: 340px;
        }

        .search-container:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
            background: white;
        }

        .search-container i {
            color: #8aacce;
            margin-right: 0.5rem;
            font-size: 0.85rem;
        }

        .search-container input {
            border: none;
            background: transparent;
            width: 100%;
            outline: none;
            font-size: 0.8rem;
            color: #1a2332;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #2563eb;
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 999px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
            color: #6b8cae;
            transition: color 0.2s;
            padding: 0.3rem;
        }

        .notification-bell:hover {
            color: #3b82f6;
        }

        .notification-bell .badge-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 0.5rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            border: 2px solid white;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem 0.25rem 0.25rem;
            border-radius: 40px;
            transition: background 0.2s;
        }

        .admin-profile:hover {
            background: #f0f4f9;
        }

        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #6d5dfc);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.75rem;
        }

        .admin-info {
            line-height: 1.2;
        }

        .admin-name {
            font-weight: 600;
            font-size: 0.8rem;
            color: #0b1a33;
        }

        .admin-role {
            font-size: 0.6rem;
            color: #8aacce;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .admin-profile i {
            color: #8aacce;
            font-size: 0.6rem;
            margin-left: 0.25rem;
        }

        /* ============================================
           CONTENT PANEL
           ============================================ */
        .content-panel {
            padding: 1.75rem 2rem 2.5rem;
        }

        /* ============================================
           WELCOME SECTION
           ============================================ */
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .welcome-text h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0b1a33;
        }

        .welcome-text h2 span {
            color: #3b82f6;
        }

        .welcome-text p {
            color: #6b8cae;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .welcome-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-primary {
            background: #0b1a33;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: #1a2a4a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 26, 51, 0.2);
        }

        .btn-secondary {
            background: white;
            color: #0b1a33;
            border: 1px solid #e8edf5;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            background: #f8faff;
            border-color: #c8d4e8;
        }

        /* ============================================
           STATS GRID
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            border: 1px solid #e8edf5;
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(11, 26, 51, 0.06);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #8aacce;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.orange { background: #fff7ed; color: #f59e0b; }
        .stat-icon.green { background: #f0fdf4; color: #22c55e; }
        .stat-icon.red { background: #fef2f2; color: #ef4444; }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0b1a33;
        }

        .stat-change {
            font-size: 0.7rem;
            color: #22c55e;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        .stat-change i {
            font-size: 0.6rem;
        }

        /* ============================================
           SECONDARY STATS GRID
           ============================================ */
        .secondary-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .secondary-stat-card {
            background: white;
            border-radius: 16px;
            padding: 1rem 1.25rem;
            border: 1px solid #e8edf5;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }

        .secondary-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 26, 51, 0.05);
        }

        .secondary-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .secondary-stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .secondary-stat-icon.green { background: #f0fdf4; color: #22c55e; }
        .secondary-stat-icon.purple { background: #f5f3ff; color: #8b5cf6; }
        .secondary-stat-icon.orange { background: #fff7ed; color: #f59e0b; }

        .secondary-stat-content {
            flex: 1;
        }

        .secondary-stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0b1a33;
        }

        .secondary-stat-label {
            font-size: 0.7rem;
            color: #8aacce;
            font-weight: 500;
        }

        /* ============================================
           ANALYTICS SECTION
           ============================================ */
        .analytics-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .analytics-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #e8edf5;
        }

        .analytics-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .analytics-card .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0b1a33;
        }

        .analytics-card .card-header .period {
            font-size: 0.7rem;
            color: #8aacce;
            background: #f0f4f9;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
        }

        .chart-placeholder {
            height: 160px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.5rem;
            padding-top: 0.5rem;
        }

        .chart-bar {
            flex: 1;
            border-radius: 6px 6px 0 0;
            min-height: 20px;
            transition: all 0.3s;
            position: relative;
            cursor: pointer;
        }

        .chart-bar:hover {
            opacity: 0.8;
            transform: scaleY(1.02);
            transform-origin: bottom;
        }

        .chart-bar .bar-value {
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.6rem;
            font-weight: 700;
            color: #0b1a33;
        }

        .chart-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            font-size: 0.6rem;
            color: #8aacce;
        }

        .chart-labels span {
            flex: 1;
            text-align: center;
            font-weight: 500;
        }

        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 0.75rem;
            font-size: 0.65rem;
            color: #6b8cae;
        }

        .chart-legend span {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .chart-legend .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .chart-legend .dot.blue { background: #3b82f6; }
        .chart-legend .dot.light { background: #8aacce; }

        /* Quick Stats */
        .quick-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .quick-stat-item {
            background: #f8faff;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #eef3fc;
            text-align: center;
        }

        .quick-stat-item .qs-number {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0b1a33;
        }

        .quick-stat-item .qs-label {
            font-size: 0.65rem;
            color: #8aacce;
            font-weight: 500;
            margin-top: 0.1rem;
        }

        .quick-stat-item .qs-change {
            font-size: 0.6rem;
            color: #22c55e;
            font-weight: 600;
        }

        .quick-stat-item .qs-change.negative {
            color: #ef4444;
        }

        /* ============================================
           NOTIFICATION DROPDOWN
           ============================================ */
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 360px;
            max-width: 90vw;
            background: white;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(11, 26, 51, 0.15);
            border: 1px solid #e8edf5;
            z-index: 1000;
            margin-top: 8px;
            max-height: 480px;
            overflow: hidden;
            display: none;
        }

        .notification-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 18px;
            width: 14px;
            height: 14px;
            background: white;
            transform: rotate(45deg);
            border-left: 1px solid #e8edf5;
            border-top: 1px solid #e8edf5;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #f0f4f9;
            font-weight: 600;
            color: #0b1a33;
            background: #fafcff;
        }

        .mark-read-btn {
            background: none;
            border: none;
            color: #3b82f6;
            font-size: 0.7rem;
            cursor: pointer;
            font-weight: 500;
        }

        .notification-list {
            overflow-y: auto;
            max-height: 380px;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.25rem;
            border-bottom: 1px solid #f8faff;
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f8faff;
        }

        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 500;
            color: #0b1a33;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .notification-time {
            font-size: 0.6rem;
            color: #8aacce;
            margin-top: 0.1rem;
        }

        .notification-empty {
            text-align: center;
            padding: 2rem 1.5rem;
            color: #8aacce;
        }

        .notification-empty i {
            font-size: 2rem;
            color: #c8d4e8;
            margin-bottom: 0.5rem;
            display: block;
        }

        .notification-empty p {
            font-weight: 500;
            color: #6b8cae;
        }

        /* ============================================
           FILTER BAR
           ============================================ */
        .filter-bar-modern {
            background: white;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            border: 1px solid #e8edf5;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-group label {
            font-size: 0.65rem;
            font-weight: 600;
            color: #8aacce;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select {
            padding: 0.4rem 1rem;
            border-radius: 8px;
            border: 1px solid #e8edf5;
            background: #f8faff;
            font-size: 0.8rem;
            cursor: pointer;
            min-width: 140px;
            transition: all 0.2s;
            color: #0b1a33;
        }

        .filter-group select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
            outline: none;
        }

        .reset-filters-btn {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.4rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .reset-filters-btn:hover {
            background: #e2e8f0;
        }

        /* ============================================
           PAGINATION
           ============================================ */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.25rem;
            padding: 0.75rem 0;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .pagination-info {
            font-size: 0.75rem;
            color: #8aacce;
        }

        .pagination-links {
            display: flex;
            gap: 0.3rem;
        }

        .page-link {
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            color: #475569;
            border: 1px solid #e2e8f0;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f1f5f9;
        }

        .page-link.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ============================================
           MAP SECTION STYLES
           ============================================ */
        .map-section {
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #e8edf5;
        }

        .map-section h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }

        .map-section h4 i {
            margin-right: 0.5rem;
        }

        .location-map-container {
            background: #f8fafc;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2ff;
        }

        .location-map-container iframe {
            display: block;
            width: 100%;
            height: 250px;
            border: none;
        }

        .location-coords {
            display: flex;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            font-size: 0.75rem;
            color: #475569;
            border-top: 1px solid #eef2ff;
            flex-wrap: wrap;
            align-items: center;
        }

        .location-coords span {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .location-coords i {
            color: #3b82f6;
            font-size: 0.7rem;
        }

        .location-coords a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            transition: color 0.2s;
        }

        .location-coords a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-plane-departure"></i>
                </div>
                <div class="logo-text">
                    <h1>HeyDream</h1>
                    <span>Super Admin</span>
                </div>
            </div>
        </div>
        <nav class="nav-menu">
            <div class="nav-label">Main Menu</div>
            <a href="?page=dashboard" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="?page=applications" class="nav-item <?php echo $currentPage === 'applications' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
                <?php if ($stats['pending_applications'] > 0): ?>
                    <span class="badge-nav"><?php echo $stats['pending_applications']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=bookings" class="nav-item <?php echo $currentPage === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Customer Bookings</span>
                <?php if (in_array('bookings', $existingTables)): ?>
                    <?php $pendingBookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count']; ?>
                    <?php if ($pendingBookings > 0): ?>
                        <span class="badge-nav"><?php echo $pendingBookings; ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </a>
            <!-- NEW: Partnership Lists Button in Main Menu -->
            <a href="?page=partnership_lists" class="nav-item <?php echo $currentPage === 'partnership_lists' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i>
                <span>Partnership Lists</span>
                <span class="badge-nav green"><?php echo $stats['active_partners']; ?></span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
                <span class="badge-nav green"><?php echo number_format($stats['total_users']); ?></span>
            </a>
            
            <div class="nav-label" style="margin-top: 0.75rem;">Reports</div>
            <a href="?page=customer" class="nav-item <?php echo $currentPage === 'customer' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Customer Reports</span>
                <?php if ($stats['customer_reports'] > 0): ?>
                    <span class="badge-nav"><?php echo $stats['customer_reports']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=partnership" class="nav-item <?php echo $currentPage === 'partnership' ? 'active' : ''; ?>">
                <i class="fas fa-file-signature"></i>
                <span>Partnership Reports</span>
                <?php if ($stats['partnership_reports'] > 0): ?>
                    <span class="badge-nav"><?php echo $stats['partnership_reports']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=system" class="nav-item <?php echo $currentPage === 'system' ? 'active' : ''; ?>">
                <i class="fas fa-server"></i>
                <span>System Reports</span>
                <?php if ($stats['system_reports'] > 0): ?>
                    <span class="badge-nav"><?php echo $stats['system_reports']; ?></span>
                <?php endif; ?>
            </a>
            
            <!-- APPROVALS LINK - UPDATED WITH PENDING BADGE -->
            <a href="?page=approval" class="nav-item <?php echo $currentPage === 'approval' ? 'active' : ''; ?>">
                <i class="fas fa-check-double"></i>
                <span>Approvals</span>
                <?php if ($pendingPackageApprovals > 0): ?>
                    <span class="badge-nav"><?php echo $pendingPackageApprovals; ?></span>
                <?php endif; ?>
            </a>
            
            <div class="nav-label" style="margin-top: 0.75rem;">Settings</div>
            <a href="#" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="admin_dashboard.php?logout=1" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <!-- TOP HEADER -->
        <header class="top-header">
            <div class="header-left">
                <div class="page-title">
                    <h2><?php echo $currentPage === 'dashboard' ? 'Dashboard' : ucfirst(str_replace('_', ' ', $currentPage)); ?></h2>
                    <p>Welcome back, <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></p>
                </div>
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search..." id="searchInput" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" onkeyup="if(event.key==='Enter') searchApplications()">
                </div>
            </div>
            <div class="header-right">
                <a href="../dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Admin Dashboard</span>
                </a>
                <!-- Notification Bell -->
                <div class="notification-bell" onclick="toggleNotificationDropdown()" id="notificationBell">
                    <i class="fas fa-bell" style="font-size: 1.1rem;"></i>
                    <span class="badge-count" id="notificationCount" style="<?php echo $notificationCounts['total'] > 0 ? '' : 'display: none;'; ?>">
                        <?php echo $notificationCounts['total']; ?>
                    </span>
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <?php if ($notificationCounts['total'] > 0): ?>
                                <button onclick="markAllAsRead()" class="mark-read-btn">Mark all read</button>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($notificationItems)): ?>
                                <div class="notification-empty">
                                    <i class="fas fa-check-circle"></i>
                                    <p>All caught up!</p>
                                    <span style="font-size: 0.7rem;">No new notifications</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notificationItems as $item): ?>
                                    <a href="<?php echo $item['link']; ?>" class="notification-item">
                                        <div class="notification-icon" style="background: <?php echo $item['color']; ?>20; color: <?php echo $item['color']; ?>;">
                                            <i class="fas <?php echo $item['icon']; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                            <div class="notification-time">
                                                <?php 
                                                    $typeLabels = [
                                                        'application' => 'New Partner Application',
                                                        'customer_report' => 'Customer Report',
                                                        'partnership_report' => 'Partnership Report',
                                                        'system_report' => 'System Report',
                                                        'approval' => 'Pending Approval'
                                                    ];
                                                    echo $typeLabels[$item['type']] ?? $item['type'];
                                                ?>
                                                • <?php echo date('M j, g:i A', strtotime($item['created_at'])); ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Admin Profile -->
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <?php echo substr($_SESSION['admin_name'] ?? 'AD', 0, 2); ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?php echo $_SESSION['admin_name'] ?? 'Admin User'; ?></div>
                        <div class="admin-role"><?php echo $_SESSION['admin_role'] ?? 'Super Admin'; ?></div>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <!-- CONTENT PANEL -->
        <?php include $pageFile; ?>
    </main>

    <!-- VIEW APPLICATION MODAL -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <span class="modal-close" onclick="closeViewModal()">&times;</span>
            </div>
            <div id="viewModalBody" class="modal-body">
                <!-- Loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button class="btn-approve-modal" onclick="approveFromModal()">
                    <i class="fas fa-check"></i> Approve Application
                </button>
                <button class="btn-docs-modal" onclick="requestDocs()">
                    <i class="fas fa-file-upload"></i> Request Documents
                </button>
                <button class="btn-email-modal" onclick="emailApplicantFromModal()">
                    <i class="fas fa-envelope"></i> Email Applicant
                </button>
                <button class="btn-chat-modal" onclick="openChat()">
                    <i class="fas fa-comment"></i> Open Chat
                </button>
                <button class="btn-reject-modal" onclick="rejectFromModal()">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
        </div>
    </div>

    <!-- REJECT MODAL -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Application</h3>
                <span class="modal-close" onclick="closeRejectModal()">&times;</span>
            </div>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject_application">
                <input type="hidden" name="id" id="rejectId">
                <input type="hidden" name="current_page" value="<?php echo $currentPage; ?>">
                <input type="hidden" name="page_num" value="<?php echo $_GET['p'] ?? 1; ?>">
                <div class="modal-body">
                    <p style="margin-bottom: 1rem;">Please provide a reason for rejecting <strong id="rejectBusinessName"></strong>'s application:</p>
                    <textarea name="rejection_reason" class="rejection-textarea" rows="4" placeholder="Enter rejection reason..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeRejectModal()" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>

    <!-- FACE VERIFICATION IMAGE MODAL -->
    <div id="faceImageModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle" style="color: #3b82f6;"></i> Face Verification Photo</h3>
                <span class="modal-close" onclick="closeFaceImageModal()">&times;</span>
            </div>
            <div class="modal-body" style="text-align: center;">
                <div id="faceImageContainer" style="display: flex; justify-content: center; align-items: center; min-height: 200px;">
                    <!-- Image will be loaded here -->
                </div>
                <p style="margin-top: 1rem; color: #6b8cae; font-size: 0.85rem;">
                    <i class="fas fa-info-circle"></i> This is the applicant's face verification photo
                </p>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button onclick="closeFaceImageModal()" class="btn-outline">Close</button>
                <button onclick="downloadFaceImage()" class="btn-primary" style="background: #3b82f6;">Download</button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // NOTIFICATION FUNCTIONS
        // ============================================
        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        }

        function markAllAsRead() {
            document.getElementById('notificationCount').style.display = 'none';
            document.getElementById('notificationDropdown').style.display = 'none';
            
            fetch('api/mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            }).catch(error => {
                console.error('Error:', error);
            });
        }

        document.addEventListener('click', function(event) {
            const bell = document.getElementById('notificationBell');
            const dropdown = document.getElementById('notificationDropdown');
            if (bell && !bell.contains(event.target)) {
                if (dropdown) dropdown.style.display = 'none';
            }
        });

        function refreshNotificationCount() {
            fetch('api/get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    const countBadge = document.getElementById('notificationCount');
                    if (data.total > 0) {
                        countBadge.textContent = data.total;
                        countBadge.style.display = 'inline-block';
                    } else {
                        countBadge.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error refreshing notifications:', error);
                });
        }

        setInterval(refreshNotificationCount, 30000);

        // ============================================
        // SEARCH FUNCTIONS
        // ============================================
        function searchApplications() {
            const search = document.getElementById('searchInput').value;
            const page = '<?php echo $currentPage; ?>';
            let url = '?page=' + page + '&p=1';
            if (search) url += '&search=' + encodeURIComponent(search);
            window.location.href = url;
        }

        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }

        function closeFaceImageModal() {
            document.getElementById('faceImageModal').style.display = 'none';
        }

        function showRejectModal(id, name) {
            document.getElementById('rejectId').value = id;
            document.getElementById('rejectBusinessName').innerHTML = name;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function emailApplicant(email) {
            window.location.href = 'mailto:' + email;
        }

        // ============================================
        // VIEW APPLICATION DETAILS
        // ============================================
        function viewApplication(id) {
            fetch('api/get_application.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const app = data.data;
                        
                        let documentsHtml = '';
                        const docFields = [
                            { name: 'Government Issued ID', field: 'business_id_filename', icon: 'fa-id-card', color: '#6366f1' },
                            { name: 'Business Permit', field: 'business_permit_filename', icon: 'fa-file-pdf', color: '#ef4444' },
                            { name: 'DTI Registration', field: 'dti_filename', icon: 'fa-file-pdf', color: '#3b82f6' },
                            { name: 'SEC Registration', field: 'sec_filename', icon: 'fa-file-pdf', color: '#22c55e' },
                            { name: 'DOT Accreditation', field: 'dot_filename', icon: 'fa-file-image', color: '#8b5cf6' }
                        ];
                        
                        let hasDocs = false;
                        docFields.forEach(doc => {
                            const file = app[doc.field];
                            if (file && file !== '' && file !== null) {
                                hasDocs = true;
                                const isImage = file.match(/\.(jpg|jpeg|png|gif|webp)$/i);
                                documentsHtml += `
                                    <div class="doc-item">
                                        <div class="doc-icon" style="color: ${doc.color};">
                                            <i class="fas ${doc.icon}"></i>
                                        </div>
                                        <div class="doc-info">
                                            <span class="doc-name">${doc.name}</span>
                                            <span class="doc-filename">${file}</span>
                                        </div>
                                        <div class="doc-actions">
                                            ${isImage ? `
                                                <button onclick="viewDocumentImage('${file}')" class="doc-action-btn view" title="View Image">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            ` : ''}
                                            <button onclick="downloadDocument('${file}')" class="doc-action-btn download" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button onclick="verifyDocument('${file}')" class="doc-action-btn verify" title="Verify">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            }
                        });
                        
                        if (!hasDocs) {
                            documentsHtml = '<p style="color: #94a3b8; font-style: italic;">No documents uploaded</p>';
                        }

                        // Build face verification section
                        let faceHtml = '';
                        if (app.face_verification_filename && app.face_verification_filename !== '' && app.face_verification_filename !== null) {
                            const faceFile = app.face_verification_filename;
                            const isImage = faceFile.match(/\.(jpg|jpeg|png|gif|webp)$/i);
                            faceHtml = `
                                <div class="face-verification-section">
                                    <h4><i class="fas fa-user-circle" style="color: #3b82f6;"></i> Face Verification</h4>
                                    <div class="face-verification-container">
                                        ${isImage ? `
                                            <div class="face-preview" onclick="viewFaceImage('${faceFile}')">
                                                <img src="../uploads/applications/${faceFile}" alt="Face Verification" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #3b82f6; cursor: pointer;">
                                                <span style="font-size: 0.7rem; color: #3b82f6; margin-top: 0.3rem;">Click to view full</span>
                                            </div>
                                            <div class="face-actions">
                                                <button onclick="viewFaceImage('${faceFile}')" class="face-action-btn">
                                                    <i class="fas fa-expand"></i> View
                                                </button>
                                                <button onclick="downloadDocument('${faceFile}')" class="face-action-btn">
                                                    <i class="fas fa-download"></i> Download
                                                </button>
                                            </div>
                                        ` : `
                                            <div class="face-preview">
                                                <div style="width: 80px; height: 80px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #94a3b8;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <span style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.3rem;">Face photo not available</span>
                                            </div>
                                        `}
                                    </div>
                                </div>
                            `;
                        } else {
                            faceHtml = `
                                <div class="face-verification-section">
                                    <h4><i class="fas fa-user-circle" style="color: #94a3b8;"></i> Face Verification</h4>
                                    <p style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">No face verification photo uploaded</p>
                                </div>
                            `;
                        }

                        // Build map section if latitude and longitude exist
                        let mapHtml = '';
                        if (app.latitude && app.longitude && app.latitude !== '' && app.longitude !== '') {
                            mapHtml = `
                                <div class="view-detail-row" style="display: block; padding: 0.5rem 0;">
                                    <div class="map-section">
                                        <h4><i class="fas fa-map-marked-alt" style="color: #3b82f6;"></i> Business Location</h4>
                                        <div class="location-map-container">
                                            <iframe
                                                src="https://www.openstreetmap.org/export/embed.html?bbox=${parseFloat(app.longitude) - 0.01}%2C${parseFloat(app.latitude) - 0.01}%2C${parseFloat(app.longitude) + 0.01}%2C${parseFloat(app.latitude) + 0.01}&amp;layer=mapnik&amp;marker=${app.latitude}%2C${app.longitude}"
                                                width="100%"
                                                height="250"
                                                style="border:0; border-radius: 8px;"
                                                allowfullscreen=""
                                                loading="lazy"
                                            ></iframe>
                                            <div class="location-coords">
                                                <span><i class="fas fa-map-pin"></i> Lat: ${app.latitude}</span>
                                                <span><i class="fas fa-map-pin"></i> Lng: ${app.longitude}</span>
                                                <a href="https://www.google.com/maps?q=${app.latitude},${app.longitude}" target="_blank">
                                                    <i class="fas fa-external-link-alt"></i> Open in Google Maps
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        
                        document.getElementById('viewModalBody').innerHTML = `
                            <div class="view-modal-grid">
                                <div class="view-modal-left">
                                    <div class="view-company-header">
                                        <div class="view-company-logo">
                                            <span>${app.business_name.charAt(0).toUpperCase()}</span>
                                        </div>
                                        <div>
                                            <h3>${app.business_name}</h3>
                                            <p class="view-business-type"><i class="fas fa-tag"></i> ${app.business_type}</p>
                                            <p class="view-status-badge">
                                                <span class="badge badge-${app.status}" style="font-size:0.8rem; padding:0.3rem 0.8rem;">
                                                    ${app.status.toUpperCase()}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="view-details-grid">
                                        <div class="view-detail-row">
                                            <i class="fas fa-building"></i>
                                            <div><strong>Business Name</strong><br>${app.business_name}</div>
                                        </div>
                                        ${app.person_name ? `
                                        <div class="view-detail-row">
                                            <i class="fas fa-user-circle"></i>
                                            <div><strong>Contact Person</strong><br>${app.person_name}</div>
                                        </div>
                                        ` : ''}
                                        <div class="view-detail-row">
                                            <i class="fas fa-envelope"></i>
                                            <div><strong>Email Address</strong><br><a href="mailto:${app.email}">${app.email}</a></div>
                                        </div>
                                        <div class="view-detail-row">
                                            <i class="fas fa-phone"></i>
                                            <div><strong>Contact Number</strong><br>${app.phone}</div>
                                        </div>
                                        <div class="view-detail-row">
                                            <i class="fas fa-tag"></i>
                                            <div><strong>Business Category</strong><br>${app.business_type}</div>
                                        </div>
                                        <div class="view-detail-row">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <div><strong>Business Address</strong><br>${app.address}</div>
                                        </div>
                                        ${mapHtml}
                                        <div class="view-detail-row">
                                            <i class="fas fa-calendar-alt"></i>
                                            <div><strong>Date Submitted</strong><br>${new Date(app.submitted_at).toLocaleString()}</div>
                                        </div>
                                        ${app.rejection_reason ? `
                                        <div class="view-detail-row rejection-reason-view">
                                            <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                                            <div><strong>Rejection Reason</strong><br><span style="color:#b91c1c;">${app.rejection_reason}</span></div>
                                        </div>
                                        ` : ''}
                                        ${app.reviewed_at ? `
                                        <div class="view-detail-row">
                                            <i class="fas fa-clock"></i>
                                            <div><strong>Reviewed On</strong><br>${new Date(app.reviewed_at).toLocaleString()}</div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                
                                <div class="view-modal-right">
                                    <!-- Face Verification Section -->
                                    ${faceHtml}
                                    
                                    <div class="view-documents-section">
                                        <h4><i class="fas fa-file-pdf"></i> Uploaded Documents</h4>
                                        <div class="documents-list">
                                            ${documentsHtml}
                                        </div>
                                    </div>
                                    
                                    <div class="view-actions-quick">
                                        <button onclick="emailApplicant('${app.email}')" class="quick-action-btn email">
                                            <i class="fas fa-envelope"></i> Email
                                        </button>
                                        <button onclick="openChat()" class="quick-action-btn chat">
                                            <i class="fas fa-comment"></i> Chat
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('viewModal').style.display = 'block';
                        document.getElementById('viewModal').dataset.appId = app.id;
                        document.getElementById('viewModal').dataset.appEmail = app.email;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading application details');
                });
        }

        function viewFaceImage(filename) {
            const container = document.getElementById('faceImageContainer');
            container.innerHTML = `
                <img src="../uploads/applications/${filename}" alt="Face Verification" 
                     style="max-width: 100%; max-height: 60vh; border-radius: 12px; object-fit: contain; 
                            box-shadow: 0 4px 20px rgba(0,0,0,0.15); border: 3px solid #e8edf5;">
            `;
            document.getElementById('faceImageModal').style.display = 'block';
            document.getElementById('faceImageModal').dataset.filename = filename;
        }

        function downloadFaceImage() {
            const filename = document.getElementById('faceImageModal').dataset.filename;
            if (filename) {
                downloadDocument(filename);
            }
        }

        function viewDocumentImage(filename) {
            const imageModal = document.createElement('div');
            imageModal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.85); z-index: 9999;
                display: flex; align-items: center; justify-content: center;
                cursor: pointer;
            `;
            imageModal.innerHTML = `
                <div style="position: relative; max-width: 80%; max-height: 80%;">
                    <img src="../uploads/applications/${filename}" style="max-width: 100%; max-height: 80vh; border-radius: 8px; object-fit: contain;" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><text y=%2230%22 font-size=%2220%22>File not found</text></svg>'">
                    <button onclick="this.parentElement.parentElement.remove()" style="
                        position: absolute; top: -40px; right: -40px;
                        background: #ef4444; color: white; border: none;
                        width: 36px; height: 36px; border-radius: 50%;
                        font-size: 20px; cursor: pointer;
                    ">×</button>
                    <p style="color: white; text-align: center; margin-top: 10px; font-size: 14px;">${filename}</p>
                </div>
            `;
            imageModal.addEventListener('click', (e) => {
                if (e.target === imageModal) imageModal.remove();
            });
            document.body.appendChild(imageModal);
        }

        function downloadDocument(filename) {
            const link = document.createElement('a');
            link.href = '../uploads/applications/' + filename;
            link.download = filename;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function verifyDocument(filename) {
            if (confirm('Verify this document? This will mark it as verified.')) {
                alert('Document verified: ' + filename);
            }
        }

        function approveFromModal() {
            const id = document.getElementById('viewModal').dataset.appId;
            if (confirm('Approve this application?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_application">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="current_page" value="${'<?php echo $currentPage; ?>'}">
                    <input type="hidden" name="page_num" value="${'<?php echo $_GET['p'] ?? 1; ?>'}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectFromModal() {
            const id = document.getElementById('viewModal').dataset.appId;
            document.getElementById('rejectId').value = id;
            closeViewModal();
            document.getElementById('rejectModal').style.display = 'block';
        }

        function emailApplicantFromModal() {
            const email = document.getElementById('viewModal').dataset.appEmail;
            if (email) {
                window.location.href = 'mailto:' + email;
            } else {
                alert('Email address not found');
            }
        }

        function requestDocs() {
            alert('Request additional documents from the applicant.');
        }

        function openChat() {
            alert('Open in-app chat with the applicant.');
        }

        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const rejectModal = document.getElementById('rejectModal');
            const faceModal = document.getElementById('faceImageModal');
            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == rejectModal) {
                closeRejectModal();
            }
            if (event.target == faceModal) {
                closeFaceImageModal();
            }
        }
    </script>

    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 1.5rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-large { max-width: 1000px; }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }
        .modal-header h3 { font-size: 1.2rem; font-weight: 600; color: #0f172a; }
        .modal-header h3 i { color: #3b82f6; margin-right: 0.5rem; }
        .modal-close { font-size: 28px; font-weight: bold; cursor: pointer; color: #94a3b8; transition: color 0.2s; }
        .modal-close:hover { color: #ef4444; }
        .modal-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            margin-top: 1rem;
            justify-content: flex-start;
        }
        .view-modal-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        .view-modal-left { display: flex; flex-direction: column; gap: 0.75rem; }
        .view-company-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .view-company-logo { width: 64px; height: 64px; border-radius: 16px; background: linear-gradient(135deg, #3b82f6, #6d5dfc); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 700; flex-shrink: 0; }
        .view-business-type { color: #64748b; font-size: 0.9rem; }
        .view-status-badge { margin-top: 0.25rem; }
        .view-details-grid { display: flex; flex-direction: column; gap: 0.5rem; }
        .view-detail-row { display: flex; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; }
        .view-detail-row:last-child { border-bottom: none; }
        .view-detail-row i { width: 20px; color: #94a3b8; margin-top: 2px; flex-shrink: 0; }
        .view-detail-row strong { color: #0f172a; }
        .view-detail-row div { flex: 1; }
        .view-detail-row a { color: #3b82f6; text-decoration: none; }
        .view-detail-row a:hover { text-decoration: underline; }
        .rejection-reason-view { background: #fef2f2; padding: 0.75rem; border-radius: 8px; }
        .view-modal-right { display: flex; flex-direction: column; gap: 1.5rem; }
        
        /* Face Verification Styles */
        .face-verification-section h4 { font-size: 0.9rem; font-weight: 600; color: #0f172a; margin-bottom: 0.75rem; }
        .face-verification-container { display: flex; align-items: center; gap: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 12px; border: 1px solid #eef2ff; }
        .face-preview { display: flex; flex-direction: column; align-items: center; }
        .face-actions { display: flex; gap: 0.5rem; }
        .face-action-btn { padding: 0.3rem 0.8rem; border-radius: 6px; border: none; background: #eff6ff; color: #3b82f6; cursor: pointer; font-size: 0.7rem; transition: all 0.2s; }
        .face-action-btn:hover { background: #dbeafe; }
        
        .view-documents-section h4 { font-size: 0.9rem; font-weight: 600; color: #0f172a; margin-bottom: 0.75rem; }
        .view-documents-section h4 i { color: #3b82f6; margin-right: 0.5rem; }
        .documents-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .doc-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; border-radius: 8px; background: #f8fafc; border: 1px solid #eef2ff; transition: all 0.2s; }
        .doc-item:hover { background: #f1f5f9; }
        .doc-icon { width: 24px; font-size: 1.1rem; flex-shrink: 0; }
        .doc-info { flex: 1; }
        .doc-name { font-size: 0.8rem; font-weight: 500; color: #0f172a; display: block; }
        .doc-filename { font-size: 0.65rem; color: #94a3b8; }
        .doc-actions { display: flex; gap: 0.3rem; }
        .doc-action-btn { background: none; border: none; padding: 0.2rem 0.4rem; border-radius: 4px; cursor: pointer; font-size: 0.7rem; transition: all 0.2s; }
        .doc-action-btn.view { color: #3b82f6; }
        .doc-action-btn.view:hover { background: #dbeafe; }
        .doc-action-btn.download { color: #22c55e; }
        .doc-action-btn.download:hover { background: #dcfce7; }
        .doc-action-btn.verify { color: #8b5cf6; }
        .doc-action-btn.verify:hover { background: #ede9fe; }
        .view-actions-quick { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .quick-action-btn { flex: 1; padding: 0.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 0.3rem; font-size: 0.8rem; transition: all 0.2s; }
        .quick-action-btn.email { background: #f5f3ff; color: #6d5dfc; }
        .quick-action-btn.email:hover { background: #ede9fe; }
        .quick-action-btn.chat { background: #e0f2fe; color: #0369a1; }
        .quick-action-btn.chat:hover { background: #bae6fd; }
        
        .btn-approve-modal { background: #22c55e; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
        .btn-approve-modal:hover { background: #16a34a; }
        .btn-docs-modal { background: #f59e0b; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
        .btn-docs-modal:hover { background: #d97706; }
        .btn-email-modal { background: #6d5dfc; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
        .btn-email-modal:hover { background: #5b4bd4; }
        .btn-chat-modal { background: #8b5cf6; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
        .btn-chat-modal:hover { background: #7c3aed; }
        .btn-reject-modal { background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
        .btn-reject-modal:hover { background: #dc2626; }
        .btn-danger { background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-danger:hover { background: #dc2626; }
        .btn-outline { background: transparent; color: #475569; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-outline:hover { background: #f1f5f9; }
        .rejection-textarea { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-family: inherit; resize: vertical; }
        .rejection-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        
        .badge { font-size: 0.7rem; padding: 0.2rem 0.6rem; border-radius: 20px; display: inline-block; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; }
        
        /* Map Section Styles */
        .map-section {
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #e8edf5;
        }

        .map-section h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }

        .map-section h4 i {
            margin-right: 0.5rem;
        }

        .location-map-container {
            background: #f8fafc;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2ff;
        }

        .location-map-container iframe {
            display: block;
            width: 100%;
            height: 250px;
            border: none;
        }

        .location-coords {
            display: flex;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            font-size: 0.75rem;
            color: #475569;
            border-top: 1px solid #eef2ff;
            flex-wrap: wrap;
            align-items: center;
        }

        .location-coords span {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .location-coords i {
            color: #3b82f6;
            font-size: 0.7rem;
        }

        .location-coords a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            transition: color 0.2s;
        }

        .location-coords a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .view-modal-grid { grid-template-columns: 1fr !important; }
            .view-company-header { flex-direction: column; align-items: flex-start; }
            .document-item { flex-wrap: wrap; }
            .face-verification-container { flex-direction: column; align-items: center; }
            .location-coords { flex-direction: column; align-items: flex-start; }
            .location-coords a { margin-left: 0; }
        }
    </style>
</body>
</html>
<?php $conn->close(); ?>
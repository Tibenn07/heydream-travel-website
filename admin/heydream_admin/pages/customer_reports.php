<?php
// pages/customer_reports.php - Customer Reports Page (Premium Design)

// Check if $conn exists, if not create it
if (!isset($conn) || $conn === null) {
    require_once __DIR__ . '/../db_config.php';
    $conn = getDBConnection();
}

// Get filters from URL
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$currentPageNum = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($currentPageNum < 1) $currentPageNum = 1;

$itemsPerPage = 10;

// Check if report_type column exists
$reportTypeExists = false;
$columnsResult = $conn->query("SHOW COLUMNS FROM customer_reports");
if ($columnsResult) {
    while ($col = $columnsResult->fetch_assoc()) {
        if ($col['Field'] === 'report_type') {
            $reportTypeExists = true;
            break;
        }
    }
}

// If report_type doesn't exist, add it
if (!$reportTypeExists) {
    $conn->query("ALTER TABLE customer_reports ADD COLUMN report_type VARCHAR(50) NOT NULL DEFAULT 'general'");
    $conn->query("ALTER TABLE customer_reports ADD INDEX idx_report_type (report_type)");
}

// Build the count query
$countSql = "SELECT COUNT(*) as total FROM customer_reports";
$whereConditions = [];
$params = [];
$types = "";

if ($statusFilter && $statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if ($searchFilter) {
    $whereConditions[] = "(subject LIKE ? OR description LIKE ? OR reported_by LIKE ? OR reported_by_email LIKE ?)";
    $params[] = "%" . $searchFilter . "%";
    $params[] = "%" . $searchFilter . "%";
    $params[] = "%" . $searchFilter . "%";
    $params[] = "%" . $searchFilter . "%";
    $types .= "ssss";
}
if ($categoryFilter && $categoryFilter !== 'all') {
    $whereConditions[] = "report_type = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}
if ($priorityFilter && $priorityFilter !== 'all') {
    $whereConditions[] = "priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if (!empty($whereConditions)) {
    $countSql .= " WHERE " . implode(" AND ", $whereConditions);
}

// Get total count
$totalCount = 0;
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalCount = $totalResult->fetch_assoc()['total'];
$countStmt->close();

$totalPages = $itemsPerPage > 0 ? ceil($totalCount / $itemsPerPage) : 1;
if ($currentPageNum > $totalPages && $totalPages > 0) {
    $currentPageNum = $totalPages;
}
$offset = ($currentPageNum - 1) * $itemsPerPage;

// Build the data query
$dataSql = "SELECT * FROM customer_reports";
if (!empty($whereConditions)) {
    $dataSql .= " WHERE " . implode(" AND ", $whereConditions);
}
$dataSql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;
$types .= "ii";

$dataStmt = $conn->prepare($dataSql);
if (!empty($params)) {
    $dataStmt->bind_param($types, ...$params);
}
$dataStmt->execute();
$reports = $dataStmt->get_result();
$dataStmt->close();

// Calculate showing range
$startRange = $totalCount > 0 ? $offset + 1 : 0;
$endRange = min($offset + $itemsPerPage, $totalCount);

// Get status counts for the filter tabs
$statusCounts = [];
$statusCounts['all'] = $conn->query("SELECT COUNT(*) as count FROM customer_reports")->fetch_assoc()['count'] ?? 0;
$statusCounts['open'] = $conn->query("SELECT COUNT(*) as count FROM customer_reports WHERE status = 'open'")->fetch_assoc()['count'] ?? 0;
$statusCounts['in_review'] = $conn->query("SELECT COUNT(*) as count FROM customer_reports WHERE status = 'in_review'")->fetch_assoc()['count'] ?? 0;
$statusCounts['resolved'] = $conn->query("SELECT COUNT(*) as count FROM customer_reports WHERE status = 'resolved'")->fetch_assoc()['count'] ?? 0;

// Get category counts
$categoryCounts = [];
$categoryResult = $conn->query("SELECT report_type, COUNT(*) as count FROM customer_reports GROUP BY report_type");
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryCounts[$row['report_type']] = $row['count'];
    }
}

// Get priority counts
$priorityCounts = [];
$priorityResult = $conn->query("SELECT priority, COUNT(*) as count FROM customer_reports GROUP BY priority");
if ($priorityResult) {
    while ($row = $priorityResult->fetch_assoc()) {
        $priorityCounts[$row['priority']] = $row['count'];
    }
}

// Handle POST actions (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $currentPageNum = isset($_POST['page_num']) ? (int)$_POST['page_num'] : 1;
    
    // Get filter values from POST
    $statusFilter = $_POST['status'] ?? '';
    $categoryFilter = $_POST['category'] ?? '';
    $priorityFilter = $_POST['priority'] ?? '';
    $searchFilter = $_POST['search'] ?? '';
    
    if ($action === 'update_customer_status') {
        $newStatus = $_POST['new_status'] ?? '';
        $stmt = $conn->prepare("UPDATE customer_reports SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect back to the same page with filters preserved
        $redirectUrl = "admin_dashboard.php?page=customer&p=" . $currentPageNum;
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
            $filePath = __DIR__ . '/../uploads/reports/' . $report['screenshot_path'];
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
        $redirectUrl = "admin_dashboard.php?page=customer&p=" . $currentPageNum;
        if ($statusFilter && $statusFilter !== 'all') $redirectUrl .= "&status=" . urlencode($statusFilter);
        if ($categoryFilter && $categoryFilter !== 'all') $redirectUrl .= "&category=" . urlencode($categoryFilter);
        if ($priorityFilter && $priorityFilter !== 'all') $redirectUrl .= "&priority=" . urlencode($priorityFilter);
        if ($searchFilter) $redirectUrl .= "&search=" . urlencode($searchFilter);
        header("Location: " . $redirectUrl);
        exit;
    }
}

// Helper functions for display
function getTypeLabel($type) {
    $map = [
        'partner_hoster' => 'Partner Hoster',
        'account_problem' => 'Account Problem',
        'payment_problem' => 'Payment Problem',
        'app_error' => 'App Error',
    ];
    
    // Check if it's an "other" type (starts with "other_")
    if (strpos($type, 'other_') === 0) {
        $suggestedMap = [
            'booking_issue' => 'Booking Issue',
            'cancellation' => 'Cancellation Problem',
            'refund_issue' => 'Refund Issue',
            'communication' => 'Communication Problem',
            'service_quality' => 'Service Quality',
            'safety_concern' => 'Safety Concern',
            'privacy_issue' => 'Privacy Issue',
            'other_issue' => 'Other Issue',
        ];
        $key = str_replace('other_', '', $type);
        return $suggestedMap[$key] ?? 'Other';
    }
    
    return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function getTypeColors($type) {
    $map = [
        'partner_hoster' => ['bg' => '#F5E8D0', 'text' => '#8A6A24'],
        'account_problem' => ['bg' => '#E8F0FE', 'text' => '#2B6FB6'],
        'payment_problem' => ['bg' => '#FEF3C7', 'text' => '#B9812C'],
        'app_error' => ['bg' => '#ECEAFF', 'text' => '#5B4ED9'],
    ];
    
    // Check if it's an "other" type
    if (strpos($type, 'other_') === 0) {
        return ['bg' => '#FFF3E0', 'text' => '#E65100'];
    }
    
    return $map[$type] ?? ['bg' => '#F1F3F5', 'text' => '#6B7A8F'];
}

function getPriorityColors($priority) {
    $map = [
        'high' => ['bg' => '#FFE1E1', 'text' => '#D94A4A'],
        'medium' => ['bg' => '#FFF3D6', 'text' => '#C98A00'],
        'low' => ['bg' => '#E6F7E6', 'text' => '#3A9B3A'],
    ];
    return $map[$priority] ?? $map['medium'];
}

function getStatusColors($status) {
    $map = [
        'open' => ['bg' => '#FFE1E1', 'text' => '#D94A4A'],
        'in_review' => ['bg' => '#FFF3D6', 'text' => '#C98A00'],
        'resolved' => ['bg' => '#E6F7E6', 'text' => '#3A9B3A'],
    ];
    return $map[$status] ?? $map['open'];
}

function getStatusLabel($status) {
    $map = [
        'open' => 'Open',
        'in_review' => 'In Review',
        'resolved' => 'Resolved',
    ];
    return $map[$status] ?? $status;
}

// Get current timestamp for "last updated"
$currentTimestamp = date('M j, g:i A');

// Compute stats
$openCount = $statusCounts['open'] ?? 0;
$inReviewCount = $statusCounts['in_review'] ?? 0;
$resolvedCount = $statusCounts['resolved'] ?? 0;
$totalReports = $statusCounts['all'] ?? 0;

// Chart data for donut
$chartOpen = $openCount;
$chartResolved = $resolvedCount;
$chartTotal = $chartOpen + $chartResolved;
$chartOpenPercent = $chartTotal > 0 ? round(($chartOpen / $chartTotal) * 100) : 0;
$chartResolvedPercent = $chartTotal > 0 ? round(($chartResolved / $chartTotal) * 100) : 0;

// Determine which category tab is active
$activeCategory = $categoryFilter ?: 'all';
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           CUSTOMER REPORTS - MODERN SAAS DASHBOARD
           ============================================ */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .customer-reports-container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F5F7FB;
            padding: 24px;
            min-height: 100vh;
        }

        /* ----- Page Header ----- */
        .page-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .page-title-modern {
            font-size: 28px;
            font-weight: 700;
            color: #1A2332;
            letter-spacing: -0.3px;
            margin: 0;
        }

        .page-subtitle-modern {
            font-size: 14px;
            color: #6B7A8F;
            margin: 0;
        }

        .page-header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .notification-btn-modern {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid #E8EDF3;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6B7A8F;
            transition: all 0.2s;
            position: relative;
        }

        .notification-btn-modern:hover {
            border-color: #C8AA6E;
            color: #1A2332;
        }

        .notification-dot-modern {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: #D94A4A;
            border-radius: 50%;
            border: 2px solid white;
        }

        .user-profile-modern {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 4px 4px;
            border-radius: 10px;
            border: 1px solid #E8EDF3;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .user-profile-modern:hover {
            border-color: #C8AA6E;
        }

        .user-avatar-modern {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, #C8AA6E, #D4B87A);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
        }

        .user-info-modern {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .user-name-modern {
            font-size: 13px;
            font-weight: 600;
            color: #1A2332;
        }

        .user-role-modern {
            font-size: 10px;
            color: #6B7A8F;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .user-profile-modern i {
            color: #6B7A8F;
            font-size: 12px;
        }

        /* Small summary card */
        .summary-mini-card {
            background: white;
            border-radius: 10px;
            border: 1px solid #E8EDF3;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .summary-mini-card .label {
            font-size: 13px;
            color: #6B7A8F;
            font-weight: 500;
        }

        .summary-mini-card .value {
            font-size: 18px;
            font-weight: 700;
            color: #1A2332;
        }

        /* ----- Analytics Card ----- */
        .analytics-card-modern {
            background: white;
            border-radius: 12px;
            border: 1px solid #E8EDF3;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 20px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr 0.8fr;
            gap: 32px;
            align-items: stretch;
        }

        /* Left - Metrics */
        .metrics-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            justify-content: center;
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
        }

        .metric-item .label {
            font-size: 14px;
            font-weight: 500;
            color: #6B7A8F;
        }

        .metric-item .value {
            font-size: 14px;
            font-weight: 600;
            color: #1A2332;
        }

        /* Center - Donut Chart */
        .chart-center {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
        }

        .donut-wrapper {
            position: relative;
            width: 160px;
            height: 160px;
        }

        .donut-wrapper svg {
            transform: rotate(-90deg);
            width: 160px;
            height: 160px;
        }

        .donut-wrapper .center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .donut-wrapper .center-text .number {
            font-size: 24px;
            font-weight: 700;
            color: #1A2332;
            display: block;
            line-height: 1;
        }

        .donut-wrapper .center-text .label {
            font-size: 12px;
            color: #6B7A8F;
            font-weight: 500;
        }

        .chart-legend-modern {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #6B7A8F;
        }

        .legend-item .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-item .dot.open { background: #C8AA6E; }
        .legend-item .dot.resolved { background: #77BE6D; }

        .legend-item .count {
            font-weight: 600;
            color: #1A2332;
        }

        /* Right - Summary Blocks */
        .summary-blocks {
            display: flex;
            flex-direction: column;
            gap: 12px;
            justify-content: center;
        }

        .summary-block {
            background: #F8F9FB;
            border: 1px solid #E8EDF3;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }

        .summary-block .label {
            font-size: 12px;
            font-weight: 500;
            color: #6B7A8F;
        }

        .summary-block .value {
            font-size: 24px;
            font-weight: 700;
            color: #1A2332;
            display: block;
            margin-top: 2px;
        }

        /* ----- Report Type Pills ----- */
        .type-pills-modern {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #E8EDF3;
        }

        .type-pill {
            height: 30px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid #E7ECF2;
            background: #F8F9FB;
            font-size: 12px;
            font-weight: 500;
            color: #1A2332;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .type-pill:hover {
            background: #EEF0F4;
            border-color: #D0D5DC;
        }

        .type-pill.active {
            background: #E9EEF8;
            border-color: #C8AA6E;
        }

        .type-pill .count {
            font-weight: 400;
            color: #6B7A8F;
        }

        /* ----- Filter Toolbar ----- */
        .filter-toolbar-modern {
            background: white;
            border-radius: 12px;
            border: 1px solid #E8EDF3;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .filter-toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn-modern {
            height: 36px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #E8EDF3;
            background: white;
            font-size: 13px;
            font-weight: 500;
            color: #1A2332;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .filter-btn-modern:hover {
            background: #F8F9FB;
            border-color: #D0D5DC;
        }

        .filter-select-modern {
            height: 36px;
            padding: 0 32px 0 12px;
            border-radius: 8px;
            border: 1px solid #E8EDF3;
            background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7A8F' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 10px center;
            background-size: 12px;
            font-size: 13px;
            color: #1A2332;
            cursor: pointer;
            appearance: none;
            min-width: 130px;
            transition: all 0.2s;
        }

        .filter-select-modern:focus {
            outline: none;
            border-color: #C8AA6E;
        }

        .filter-toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-wrapper-modern {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #E8EDF3;
            border-radius: 8px;
            padding: 0 12px;
            height: 36px;
            transition: all 0.2s;
        }

        .search-wrapper-modern:focus-within {
            border-color: #C8AA6E;
        }

        .search-wrapper-modern i {
            color: #6B7A8F;
            font-size: 14px;
        }

        .search-wrapper-modern input {
            border: none;
            background: transparent;
            padding: 0 8px;
            height: 100%;
            width: 180px;
            font-size: 13px;
            color: #1A2332;
            outline: none;
            font-family: 'Inter', sans-serif;
        }

        .search-wrapper-modern input::placeholder {
            color: #9AA8B9;
        }

        .reset-btn-modern {
            height: 36px;
            padding: 0 16px;
            border-radius: 8px;
            border: 1px solid #E8EDF3;
            background: white;
            font-size: 13px;
            font-weight: 500;
            color: #1A2332;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reset-btn-modern:hover {
            background: #F8F9FB;
            border-color: #D0D5DC;
        }

        /* ----- Reports Table ----- */
        .table-card-modern {
            background: white;
            border-radius: 12px;
            border: 1px solid #E8EDF3;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #E8EDF3;
            flex-wrap: wrap;
            gap: 8px;
        }

        .table-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .table-header-left .title {
            font-size: 14px;
            font-weight: 600;
            color: #1A2332;
            letter-spacing: 0.3px;
        }

        .table-header-left .count {
            font-size: 12px;
            color: #6B7A8F;
        }

        .table-header-right {
            font-size: 12px;
            color: #6B7A8F;
        }

        .table-scroll-modern {
            overflow-x: auto;
        }

        .reports-table-modern {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .reports-table-modern thead {
            background: #F8F9FB;
        }

        .reports-table-modern th {
            text-align: left;
            padding: 10px 16px;
            font-size: 11px;
            font-weight: 600;
            color: #6B7A8F;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #E8EDF3;
        }

        .reports-table-modern td {
            padding: 10px 16px;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }

        .reports-table-modern tbody tr {
            height: 70px;
            transition: background 0.15s;
        }

        .reports-table-modern tbody tr:hover {
            background: #FAFBFC;
        }

        .reports-table-modern tbody tr:last-child td {
            border-bottom: none;
        }

        /* Report Column */
        .report-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .report-icon-box {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #F0F2F5;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: 600;
            font-size: 13px;
            color: #6B7A8F;
        }

        .report-text {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .report-text .title {
            font-weight: 500;
            color: #1A2332;
        }

        .report-text .subtitle {
            font-size: 12px;
            color: #6B7A8F;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .report-text .date {
            font-size: 11px;
            color: #9AA8B9;
        }

        /* Type Badge */
        .type-badge-modern {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Priority Badge */
        .priority-badge-modern {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Status Badge */
        .status-badge-modern {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Action Buttons */
        .action-buttons-modern {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .action-btn-modern {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #E8EDF3;
            background: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6B7A8F;
            transition: all 0.2s;
            font-size: 13px;
            text-decoration: none;
        }

        .action-btn-modern:hover {
            background: #F8F9FB;
            border-color: #D0D5DC;
            color: #1A2332;
        }

        .action-btn-modern.delete:hover {
            background: #FFE1E1;
            border-color: #D94A4A;
            color: #D94A4A;
        }

        .action-btn-modern.view:hover {
            background: #E9EEF8;
            border-color: #C8AA6E;
            color: #C8AA6E;
        }

        .action-btn-modern.email:hover {
            background: #ECEAFF;
            border-color: #5B4ED9;
            color: #5B4ED9;
        }

        /* More dropdown */
        .more-dropdown {
            position: relative;
        }

        .more-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 8px;
            border: 1px solid #E8EDF3;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            min-width: 160px;
            z-index: 10;
            padding: 4px 0;
        }

        .more-dropdown-content.show {
            display: block;
        }

        .more-dropdown-content a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            font-size: 13px;
            color: #1A2332;
            text-decoration: none;
            transition: background 0.15s;
        }

        .more-dropdown-content a:hover {
            background: #F8F9FB;
        }

        /* ----- Pagination ----- */
        .pagination-modern {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 16px 20px;
            border-top: 1px solid #E8EDF3;
            gap: 8px;
            flex-wrap: wrap;
        }

        .page-btn-modern {
            height: 32px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #E8EDF3;
            background: white;
            font-size: 12px;
            font-weight: 500;
            color: #1A2332;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .page-btn-modern:hover:not(.disabled) {
            background: #F8F9FB;
            border-color: #D0D5DC;
        }

        .page-btn-modern.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .page-num-modern {
            height: 32px;
            min-width: 32px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            font-size: 12px;
            font-weight: 500;
            color: #6B7A8F;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
        }

        .page-num-modern:hover {
            background: #F8F9FB;
            border-color: #E8EDF3;
        }

        .page-num-modern.active {
            background: #E9EEF8;
            border-color: #C8AA6E;
            color: #1A2332;
        }

        /* ----- Empty State ----- */
        .empty-state-modern {
            text-align: center;
            padding: 48px 20px;
        }

        .empty-state-modern .icon {
            font-size: 48px;
            color: #D0D5DC;
            margin-bottom: 12px;
        }

        .empty-state-modern .title {
            font-size: 18px;
            font-weight: 600;
            color: #1A2332;
            margin-bottom: 4px;
        }

        .empty-state-modern .subtitle {
            font-size: 14px;
            color: #6B7A8F;
        }

        /* ----- Responsive ----- */
        @media (max-width: 1024px) {
            .analytics-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
            .summary-blocks {
                flex-direction: row;
            }
        }

        @media (max-width: 768px) {
            .analytics-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .filter-toolbar-modern {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-toolbar-left {
                flex-wrap: wrap;
            }
            .filter-toolbar-right {
                flex-wrap: wrap;
            }
            .search-wrapper-modern input {
                width: 120px;
            }
            .page-header-modern {
                flex-direction: column;
                align-items: flex-start;
            }
            .table-scroll-modern {
                overflow-x: auto;
            }
            .reports-table-modern {
                min-width: 700px;
            }
            .pagination-modern {
                justify-content: center;
            }
            .chart-center {
                flex-direction: column;
            }
            .summary-blocks {
                flex-direction: row;
            }
        }

        @media (max-width: 480px) {
            .customer-reports-container {
                padding: 12px;
            }
            .page-title-modern {
                font-size: 22px;
            }
            .analytics-card-modern {
                padding: 16px;
            }
            .filter-select-modern {
                min-width: 100px;
                font-size: 12px;
            }
            .filter-btn-modern {
                font-size: 12px;
                padding: 0 10px;
            }
            .type-pill {
                font-size: 11px;
                padding: 0 10px;
            }
            .action-buttons-modern {
                gap: 2px;
            }
            .action-btn-modern {
                width: 28px;
                height: 28px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
<div class="customer-reports-container">

    <!-- ==========================================
         PAGE HEADER
         ========================================== -->
    <div class="page-header-modern">
        <div class="page-header-left">
            <h1 class="page-title-modern">Customer Reports</h1>
            <p class="page-subtitle-modern">Review and manage customer reports submitted from the mobile app.</p>
        </div>
        <div class="page-header-right">
            <!-- Summary Mini Card -->
            <div class="summary-mini-card">
                <span class="label">Total Reports</span>
                <span class="value"><?php echo $totalCount; ?></span>
            </div>
            <!-- Notification Bell -->
            <div class="notification-btn-modern">
                <i class="fas fa-bell"></i>
                <span class="notification-dot-modern"></span>
            </div>
            <!-- User Profile -->
            <div class="user-profile-modern">
                <div class="user-avatar-modern">SA</div>
                <div class="user-info-modern">
                    <span class="user-name-modern">Super Admin</span>
                    <span class="user-role-modern">SUPER_ADMIN</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>

    <!-- ==========================================
         ANALYTICS CARD
         ========================================== -->
    <div class="analytics-card-modern">
        <div class="analytics-grid">
            <!-- Left: Metrics -->
            <div class="metrics-list">
                <div class="metric-item">
                    <span class="label">Total Reports</span>
                    <span class="value"><?php echo $totalCount; ?></span>
                </div>
                <div class="metric-item">
                    <span class="label">Open</span>
                    <span class="value"><?php echo $openCount; ?></span>
                </div>
                <div class="metric-item">
                    <span class="label">In Review</span>
                    <span class="value"><?php echo $inReviewCount; ?></span>
                </div>
                <div class="metric-item">
                    <span class="label">Resolved</span>
                    <span class="value"><?php echo $resolvedCount; ?></span>
                </div>
            </div>

            <!-- Center: Donut Chart -->
            <div class="chart-center">
                <div class="donut-wrapper">
                    <svg viewBox="0 0 160 160">
                        <?php
                        $radius = 60;
                        $circumference = 2 * M_PI * $radius;
                        $openDash = ($chartTotal > 0) ? ($chartOpen / $chartTotal) * $circumference : 0;
                        $resolvedDash = ($chartTotal > 0) ? ($chartResolved / $chartTotal) * $circumference : 0;
                        $offset = 0;
                        ?>
                        <!-- Background -->
                        <circle cx="80" cy="80" r="<?php echo $radius; ?>" fill="none" stroke="#F0F2F5" stroke-width="16"/>
                        <!-- Open segment -->
                        <?php if ($openDash > 0): ?>
                        <circle cx="80" cy="80" r="<?php echo $radius; ?>" fill="none" stroke="#C8AA6E" stroke-width="16"
                                stroke-dasharray="<?php echo $openDash; ?> <?php echo $circumference - $openDash; ?>"
                                stroke-dashoffset="<?php echo -$offset; ?>"
                                stroke-linecap="round"/>
                        <?php $offset += $openDash; endif; ?>
                        <!-- Resolved segment -->
                        <?php if ($resolvedDash > 0): ?>
                        <circle cx="80" cy="80" r="<?php echo $radius; ?>" fill="none" stroke="#77BE6D" stroke-width="16"
                                stroke-dasharray="<?php echo $resolvedDash; ?> <?php echo $circumference - $resolvedDash; ?>"
                                stroke-dashoffset="<?php echo -$offset; ?>"
                                stroke-linecap="round"/>
                        <?php endif; ?>
                    </svg>
                    <div class="center-text">
                        <span class="number"><?php echo $chartTotal; ?></span>
                        <span class="label">Total</span>
                    </div>
                </div>
                <div class="chart-legend-modern">
                    <div class="legend-item">
                        <span class="dot open"></span>
                        Open <span class="count"><?php echo $chartOpen; ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="dot resolved"></span>
                        Resolved <span class="count"><?php echo $chartResolved; ?></span>
                    </div>
                </div>
            </div>

            <!-- Right: Summary Blocks -->
            <div class="summary-blocks">
                <div class="summary-block">
                    <span class="label">Open</span>
                    <span class="value"><?php echo $openCount; ?></span>
                </div>
                <div class="summary-block">
                    <span class="label">Resolved</span>
                    <span class="value"><?php echo $resolvedCount; ?></span>
                </div>
            </div>
        </div>

        <!-- Report Type Pills -->
        <div class="type-pills-modern">
            <a href="?page=customer&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" 
               class="type-pill <?php echo $activeCategory === 'all' ? 'active' : ''; ?>">
                All Reports <span class="count">(<?php echo $totalCount; ?>)</span>
            </a>
            <?php foreach ($categoryCounts as $type => $count): ?>
                <a href="?page=customer&category=<?php echo urlencode($type); ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" 
                   class="type-pill <?php echo $activeCategory === $type ? 'active' : ''; ?>">
                    <?php echo getTypeLabel($type); ?> <span class="count">(<?php echo $count; ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ==========================================
         FILTER TOOLBAR
         ========================================== -->
    <form method="GET" class="filter-toolbar-modern" id="filterForm">
        <input type="hidden" name="page" value="customer">
        <div class="filter-toolbar-left">
            <button type="button" class="filter-btn-modern">
                <i class="fas fa-sliders-h"></i> Filters
            </button>
            <select name="priority" class="filter-select-modern" onchange="this.form.submit()">
                <option value="all" <?php echo $priorityFilter === 'all' || $priorityFilter === '' ? 'selected' : ''; ?>>All Priority</option>
                <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
            <select name="category" class="filter-select-modern" onchange="this.form.submit()">
                <option value="all" <?php echo $categoryFilter === 'all' || $categoryFilter === '' ? 'selected' : ''; ?>>All Types</option>
                <option value="partner_hoster" <?php echo $categoryFilter === 'partner_hoster' ? 'selected' : ''; ?>>Partner Hoster</option>
                <option value="account_problem" <?php echo $categoryFilter === 'account_problem' ? 'selected' : ''; ?>>Account Problem</option>
                <option value="payment_problem" <?php echo $categoryFilter === 'payment_problem' ? 'selected' : ''; ?>>Payment Problem</option>
                <option value="app_error" <?php echo $categoryFilter === 'app_error' ? 'selected' : ''; ?>>App Error</option>
                <?php foreach ($categoryCounts as $type => $count): ?>
                    <?php if (strpos($type, 'other_') === 0): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $categoryFilter === $type ? 'selected' : ''; ?>>
                            <?php echo getTypeLabel($type); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        </div>
        <div class="filter-toolbar-right">
            <div class="search-wrapper-modern">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search reports" 
                       value="<?php echo htmlspecialchars($searchFilter); ?>"
                       onkeydown="if(event.key==='Enter') this.form.submit()">
            </div>
            <a href="?page=customer" class="reset-btn-modern">
                <i class="fas fa-undo"></i> Reset
            </a>
        </div>
    </form>

    <!-- ==========================================
         REPORTS TABLE
         ========================================== -->
    <div class="table-card-modern">
        <div class="table-header-modern">
            <div class="table-header-left">
                <span class="title">REPORTS</span>
                <span class="count"><?php echo $totalCount; ?> total</span>
            </div>
            <div class="table-header-right">
                Last updated: <?php echo $currentTimestamp; ?>
            </div>
        </div>

        <div class="table-scroll-modern">
            <table class="reports-table-modern">
                <thead>
                    <tr>
                        <th>REPORT</th>
                        <th>TYPE</th>
                        <th>REPORTED BY</th>
                        <th>PRIORITY</th>
                        <th>STATUS</th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reports && $reports->num_rows > 0): ?>
                        <?php while ($report = $reports->fetch_assoc()): 
                            $reportType = $report['report_type'] ?? 'general';
                            $priority = strtolower($report['priority'] ?? 'medium');
                            $status = $report['status'] ?? 'open';
                            $typeColors = getTypeColors($reportType);
                            $priorityColors = getPriorityColors($priority);
                            $statusColors = getStatusColors($status);
                            $statusLabel = getStatusLabel($status);
                            $typeLabel = getTypeLabel($reportType);
                        ?>
                            <tr>
                                <!-- REPORT Column -->
                                <td>
                                    <div class="report-cell">
                                        <div class="report-icon-box">
                                            <?php echo strtoupper(substr($report['subject'] ?? 'R', 0, 1)); ?>
                                        </div>
                                        <div class="report-text">
                                            <span class="title"><?php echo htmlspecialchars($report['subject'] ?? 'No Subject'); ?></span>
                                            <span class="subtitle"><?php echo htmlspecialchars(substr($report['description'] ?? '', 0, 50)); ?><?php echo strlen($report['description'] ?? '') > 50 ? '...' : ''; ?></span>
                                            <span class="date"><?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- TYPE -->
                                <td>
                                    <span class="type-badge-modern" style="background: <?php echo $typeColors['bg']; ?>; color: <?php echo $typeColors['text']; ?>;">
                                        <?php echo $typeLabel; ?>
                                    </span>
                                </td>

                                <!-- REPORTED BY -->
                                <td>
                                    <div style="font-weight: 500; color: #1A2332;"><?php echo htmlspecialchars($report['reported_by'] ?? 'Unknown'); ?></div>
                                    <div style="font-size: 11px; color: #6B7A8F;"><?php echo htmlspecialchars($report['reported_by_email'] ?? ''); ?></div>
                                </td>

                                <!-- PRIORITY -->
                                <td>
                                    <span class="priority-badge-modern" style="background: <?php echo $priorityColors['bg']; ?>; color: <?php echo $priorityColors['text']; ?>;">
                                        <?php echo ucfirst($priority); ?>
                                    </span>
                                </td>

                                <!-- STATUS -->
                                <td>
                                    <span class="status-badge-modern" style="background: <?php echo $statusColors['bg']; ?>; color: <?php echo $statusColors['text']; ?>;">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </td>

                                <!-- ACTIONS -->
                                <td style="text-align: right;">
                                    <div class="action-buttons-modern" style="justify-content: flex-end;">
                                        <!-- View -->
                                        <button onclick="viewCustomerReport('<?php echo $report['id']; ?>')" class="action-btn-modern view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <!-- Edit -->
                                        <button onclick="editCustomerReport('<?php echo $report['id']; ?>')" class="action-btn-modern" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <!-- Comment -->
                                        <button onclick="commentCustomerReport('<?php echo $report['id']; ?>')" class="action-btn-modern email" title="Comment">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                        <!-- Delete -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this report?');">
                                            <input type="hidden" name="action" value="delete_customer">
                                            <input type="hidden" name="id" value="<?php echo $report['id']; ?>">
                                            <input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">
                                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">
                                            <input type="hidden" name="priority" value="<?php echo htmlspecialchars($priorityFilter); ?>">
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>">
                                            <button type="submit" class="action-btn-modern delete" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        <!-- More Dropdown -->
                                        <div class="more-dropdown">
                                            <button class="action-btn-modern" onclick="toggleMoreDropdown(this)" title="More">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="more-dropdown-content">
                                                <a href="#" onclick="assignReport('<?php echo $report['id']; ?>')">
                                                    <i class="fas fa-user-plus"></i> Assign to Team
                                                </a>
                                                <?php if ($status !== 'resolved'): ?>
                                                <a href="#" onclick="markResolved('<?php echo $report['id']; ?>')">
                                                    <i class="fas fa-check-circle"></i> Mark as Resolved
                                                </a>
                                                <?php endif; ?>
                                                <a href="#" onclick="emailCustomer('<?php echo $report['reported_by_email']; ?>')">
                                                    <i class="fas fa-envelope"></i> Email Customer
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state-modern">
                                    <div class="icon"><i class="fas fa-inbox"></i></div>
                                    <div class="title">No reports found</div>
                                    <div class="subtitle">Customer reports submitted from the mobile app will appear here.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ==========================================
             PAGINATION
             ========================================== -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-modern">
            <?php if ($currentPageNum > 1): ?>
                <a href="?page=customer&p=<?php echo $currentPageNum - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-btn-modern">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php else: ?>
                <span class="page-btn-modern disabled"><i class="fas fa-chevron-left"></i> Previous</span>
            <?php endif; ?>

            <?php
            $maxPagesToShow = 5;
            $halfRange = floor($maxPagesToShow / 2);
            $startPage = max(1, $currentPageNum - $halfRange);
            $endPage = min($totalPages, $currentPageNum + $halfRange);
            
            if ($startPage > 1) {
                echo '<a href="?page=customer&p=1&status=' . urlencode($statusFilter) . '&category=' . urlencode($categoryFilter) . '&priority=' . urlencode($priorityFilter) . '&search=' . urlencode($searchFilter) . '" class="page-num-modern">1</a>';
                if ($startPage > 2) {
                    echo '<span class="page-num-modern" style="border-color:transparent;cursor:default;">…</span>';
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=customer&p=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-num-modern <?php echo $i === $currentPageNum ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    echo '<span class="page-num-modern" style="border-color:transparent;cursor:default;">…</span>';
                }
                echo '<a href="?page=customer&p=' . $totalPages . '&status=' . urlencode($statusFilter) . '&category=' . urlencode($categoryFilter) . '&priority=' . urlencode($priorityFilter) . '&search=' . urlencode($searchFilter) . '" class="page-num-modern">' . $totalPages . '</a>';
            }
            ?>

            <?php if ($currentPageNum < $totalPages): ?>
                <a href="?page=customer&p=<?php echo $currentPageNum + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-btn-modern">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="page-btn-modern disabled">Next <i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ==========================================
         VIEW REPORT MODAL
         ========================================== -->
    <div id="viewReportModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div style="background:white; border-radius:16px; width:90%; max-width:720px; max-height:90vh; overflow-y:auto; padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding-bottom:16px; border-bottom:1px solid #E8EDF3; margin-bottom:16px;">
                <h3 style="font-size:18px; font-weight:600; color:#1A2332; margin:0;">Report Details</h3>
                <button onclick="closeViewReportModal()" style="background:none; border:none; font-size:24px; color:#6B7A8F; cursor:pointer;">&times;</button>
            </div>
            <div id="viewReportModalBody">
                <!-- Loaded via JavaScript -->
            </div>
            <div style="display:flex; gap:8px; padding-top:16px; border-top:1px solid #E8EDF3; margin-top:16px; flex-wrap:wrap;">
                <button onclick="emailCustomerFromModal()" style="padding:8px 16px; border-radius:8px; border:none; background:#ECEAFF; color:#5B4ED9; font-weight:500; cursor:pointer;">Email Customer</button>
                <button onclick="closeViewReportModal()" style="padding:8px 16px; border-radius:8px; border:1px solid #E8EDF3; background:white; font-weight:500; cursor:pointer;">Close</button>
            </div>
        </div>
    </div>

</div>

<!-- ==========================================
     JAVASCRIPT - MODAL & ACTIONS
     ========================================== -->
<script>
function viewCustomerReport(id) {
    fetch('api/get_customer_report.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const report = data.data;
                
                // Type colors mapping
                const typeColors = {
                    'partner_hoster': { bg: '#F5E8D0', text: '#8A6A24' },
                    'account_problem': { bg: '#E8F0FE', text: '#2B6FB6' },
                    'payment_problem': { bg: '#FEF3C7', text: '#B9812C' },
                    'app_error': { bg: '#ECEAFF', text: '#5B4ED9' },
                };
                
                // Check if it's an "other" type
                let colors = typeColors[report.report_type] || { bg: '#F1F3F5', text: '#6B7A8F' };
                if (report.report_type && report.report_type.indexOf('other_') === 0) {
                    colors = { bg: '#FFF3E0', text: '#E65100' };
                }
                
                // Get display label for type
                const typeLabels = {
                    'partner_hoster': 'Partner Hoster',
                    'account_problem': 'Account Problem',
                    'payment_problem': 'Payment Problem',
                    'app_error': 'App Error',
                };
                
                let typeLabel = report.report_type || 'General';
                if (typeLabels[typeLabel]) {
                    typeLabel = typeLabels[typeLabel];
                } else if (typeLabel.indexOf('other_') === 0) {
                    const suggestedMap = {
                        'booking_issue': 'Booking Issue',
                        'cancellation': 'Cancellation Problem',
                        'refund_issue': 'Refund Issue',
                        'communication': 'Communication Problem',
                        'service_quality': 'Service Quality',
                        'safety_concern': 'Safety Concern',
                        'privacy_issue': 'Privacy Issue',
                        'other_issue': 'Other Issue',
                    };
                    const key = typeLabel.replace('other_', '');
                    typeLabel = suggestedMap[key] || 'Other';
                }
                
                // Get status label
                const statusLabels = {
                    'open': 'Open',
                    'in_review': 'In Review',
                    'resolved': 'Resolved',
                };
                const statusLabel = statusLabels[report.status] || report.status || 'Open';
                
                // Get priority label
                const priorityLabel = report.priority ? report.priority.charAt(0).toUpperCase() + report.priority.slice(1) : 'Medium';
                
                // FIXED: Correct path for images
                let screenshotHtml = '';
                if (report.screenshot_path && report.screenshot_path !== '' && report.screenshot_path !== null) {
                    const imagePath = '../heydream_admin/uploads/reports/' + report.screenshot_path;
                    screenshotHtml = `
                        <div style="margin-top:12px; background:#F8F9FB; border-radius:12px; overflow:hidden; border:1px solid #E8EDF3;">
                            <img src="${imagePath}" alt="Screenshot" 
                                 style="width:100%; max-height:200px; object-fit:contain; cursor:pointer;" 
                                 onclick="window.open('${imagePath}', '_blank')"
                                 onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML+='<div style=\\'padding:20px; text-align:center; color:#6B7A8F;\\'><i class=\\'fas fa-image\\' style=\\'font-size:32px; display:block; margin-bottom:8px; color:#D0D5DC;\\'></i><p>Image not found<br><small style=\\'font-size:11px;\\'>${report.screenshot_path}</small></p></div>'">
                            <div style="padding:8px 12px; background:#F0F2F5; font-size:11px; color:#6B7A8F; display:flex; justify-content:space-between;">
                                <span>${report.screenshot_path}</span>
                                <a href="${imagePath}" download style="color:#C8AA6E; text-decoration:none;">Download</a>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('viewReportModalBody').innerHTML = `
                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:24px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                <div style="width:48px; height:48px; border-radius:12px; background:${colors.bg}; color:${colors.text}; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:700;">
                                    ${(report.subject || 'R').charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <h4 style="font-size:16px; font-weight:600; color:#1A2332; margin:0;">${report.subject || 'No Subject'}</h4>
                                    <span style="font-size:12px; padding:2px 10px; border-radius:999px; background:${colors.bg}; color:${colors.text}; display:inline-block; margin-top:4px;">
                                        ${typeLabel}
                                    </span>
                                </div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:8px; margin-top:12px;">
                                <div><span style="color:#6B7A8F; font-size:12px;">Reported By</span><br><span style="font-weight:500;">${report.reported_by || 'Unknown'}</span></div>
                                <div><span style="color:#6B7A8F; font-size:12px;">Email</span><br><a href="mailto:${report.reported_by_email || ''}" style="color:#C8AA6E; text-decoration:none;">${report.reported_by_email || 'N/A'}</a></div>
                                ${report.partner_name ? `<div><span style="color:#6B7A8F; font-size:12px;">Partner</span><br><span>${report.partner_name}</span></div>` : ''}
                                <div><span style="color:#6B7A8F; font-size:12px;">Priority</span><br><span style="font-weight:500;">${priorityLabel}</span></div>
                                <div><span style="color:#6B7A8F; font-size:12px;">Status</span><br><span style="font-weight:500;">${statusLabel}</span></div>
                                <div><span style="color:#6B7A8F; font-size:12px;">Reported On</span><br><span>${new Date(report.created_at).toLocaleString()}</span></div>
                            </div>
                        </div>
                        <div>
                            <div style="background:#F8F9FB; padding:16px; border-radius:12px; border:1px solid #E8EDF3;">
                                <h5 style="font-size:13px; font-weight:600; color:#1A2332; margin:0 0 8px 0;">Description</h5>
                                <p style="color:#6B7A8F; font-size:14px; line-height:1.6; margin:0;">${report.description || 'No description provided.'}</p>
                            </div>
                            ${screenshotHtml}
                        </div>
                    </div>
                `;
                document.getElementById('viewReportModal').style.display = 'flex';
                document.getElementById('viewReportModal').dataset.customerEmail = report.reported_by_email || '';
            } else {
                alert('Error loading report details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading report details');
        });
}

function closeViewReportModal() {
    document.getElementById('viewReportModal').style.display = 'none';
}

function emailCustomerFromModal() {
    const email = document.getElementById('viewReportModal').dataset.customerEmail;
    if (email) window.location.href = 'mailto:' + email;
}

function emailCustomer(email) {
    if (email) window.location.href = 'mailto:' + email;
}

function editCustomerReport(id) {
    alert('Edit report #' + id);
}

function commentCustomerReport(id) {
    alert('Open comment section for report #' + id);
}

function assignReport(id) {
    alert('Assign report #' + id + ' to team member');
}

function markResolved(id) {
    if (confirm('Mark this report as resolved?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_customer_status">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="new_status" value="resolved">
            <input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">
            <input type="hidden" name="priority" value="<?php echo htmlspecialchars($priorityFilter); ?>">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleMoreDropdown(btn) {
    const content = btn.parentElement.querySelector('.more-dropdown-content');
    content.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.more-dropdown')) {
        document.querySelectorAll('.more-dropdown-content').forEach(el => el.classList.remove('show'));
    }
});

// Close modal on overlay click
window.onclick = function(event) {
    const modal = document.getElementById('viewReportModal');
    if (event.target == modal) closeViewReportModal();
}

// Auto-close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeViewReportModal();
});
</script>

</body>
</html>
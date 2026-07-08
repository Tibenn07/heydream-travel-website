<?php
// pages/partnership_reports.php - Premium Partnership Reports Page (FIXED)

// Get filters
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$currentPageNum = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($currentPageNum < 1) $currentPageNum = 1;
$itemsPerPage = 10;

// Determine which date column exists
$dateColumn = 'created_at';
$columnsResult = $conn->query("SHOW COLUMNS FROM partnership_reports");
if ($columnsResult) {
    while ($col = $columnsResult->fetch_assoc()) {
        if ($col['Field'] === 'createdAt') {
            $dateColumn = 'createdAt';
            break;
        }
        if ($col['Field'] === 'created_at') {
            $dateColumn = 'created_at';
            break;
        }
    }
}

// Build query
$where = [];
$params = [];
$types = "";

if ($statusFilter && $statusFilter !== 'all') {
    $where[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if ($searchFilter) {
    $where[] = "(title LIKE ? OR reported_by_name LIKE ? OR reported_by_email LIKE ? OR id LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $types .= "ssss";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM partnership_reports $whereSQL");
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalCount = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = max(1, ceil($totalCount / $itemsPerPage));
if ($currentPageNum > $totalPages && $totalPages > 0) $currentPageNum = $totalPages;
$offset = ($currentPageNum - 1) * $itemsPerPage;

// Get data
$dataSql = "SELECT * FROM partnership_reports $whereSQL ORDER BY $dateColumn DESC LIMIT ? OFFSET ?";
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

$startRange = $totalCount > 0 ? $offset + 1 : 0;
$endRange = min($offset + $itemsPerPage, $totalCount);

// Status counts
$statusCounts = [];
$statusCounts['all'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports")->fetch_assoc()['count'] ?? 0;
$statusCounts['pending'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$statusCounts['approved'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status = 'approved'")->fetch_assoc()['count'] ?? 0;
$statusCounts['rejected'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status = 'rejected'")->fetch_assoc()['count'] ?? 0;
$statusCounts['accepted'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status = 'accepted'")->fetch_assoc()['count'] ?? 0;
$statusCounts['open'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status = 'open'")->fetch_assoc()['count'] ?? 0;
$statusCounts['in_review'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status = 'in_review'")->fetch_assoc()['count'] ?? 0;
$statusCounts['resolved'] = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status = 'resolved'")->fetch_assoc()['count'] ?? 0;

$pendingCount = $statusCounts['pending'] + $statusCounts['open'];
$approvedCount = $statusCounts['approved'] + $statusCounts['accepted'];
$rejectedCount = $statusCounts['rejected'];
$resolvedCount = $statusCounts['resolved'];
$totalReports = $statusCounts['all'];

// Calculate rates
$approvalRate = $totalReports > 0 ? round(($approvedCount / $totalReports) * 100) : 0;
$resolutionRate = $totalReports > 0 ? round(($resolvedCount / $totalReports) * 100) : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? '';
    
    if ($action === 'accept_partnership' || $action === 'approve_partnership') {
        $stmt = $conn->prepare("UPDATE partnership_reports SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'reject_partnership') {
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $conn->prepare("UPDATE partnership_reports SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $reason, $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete_partnership') {
        $stmt = $conn->prepare("DELETE FROM partnership_reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'resolve_partnership') {
        $stmt = $conn->prepare("UPDATE partnership_reports SET status = 'resolved', reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: ?page=partnership&status=" . urlencode($statusFilter) . "&p=" . $currentPageNum);
    exit;
}

// ============================================
// CATEGORY STYLING FUNCTION (FIXED)
// ============================================
function getCategoryStyle($category) {
    $categoryColors = [
        'Payment Dispute' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'No Show' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
        'No-Show' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
        'Aggressive Behavior' => ['bg' => '#fecaca', 'text' => '#991b1b'],
        'Fake Listing' => ['bg' => '#fce4ec', 'text' => '#9a3412'],
        'Fraud' => ['bg' => '#ffcdd2', 'text' => '#b91c1c'],
        'Service Complaint' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
        'Harassment' => ['bg' => '#fce4ec', 'text' => '#9a3412'],
        'Payment Issue' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'Review' => ['bg' => '#e0e7ff', 'text' => '#3730a3'],
        'General' => ['bg' => '#f0f4f9', 'text' => '#475569'],
        'Other' => ['bg' => '#f0f4f9', 'text' => '#475569'],
    ];
    
    $key = trim($category ?? 'General');
    
    // Direct match
    if (isset($categoryColors[$key])) {
        return $categoryColors[$key];
    }
    
    // Case-insensitive match
    foreach ($categoryColors as $catKey => $value) {
        if (strtolower($catKey) === strtolower($key)) {
            return $value;
        }
    }
    
    // Fallback to General
    return $categoryColors['General'];
}
?>

<!-- ============================================
     PREMIUM PARTNERSHIP REPORTS DASHBOARD
     ============================================ -->
<div class="partnership-dashboard">

    <!-- ==========================================
         PAGE HEADER
         ========================================== -->
    <div class="page-header-premium">
        <div class="header-left">
            <div class="header-icon-wrapper">
                <div class="header-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div>
                <h1 class="page-title-premium">Partnership Reports</h1>
                <p class="page-subtitle-premium">Review, manage, and resolve partnership reports submitted by partners across the HeyDream platform.</p>
            </div>
        </div>
        <div class="header-right-premium">
            <div class="notification-wrapper">
                <div class="notification-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span class="notification-dot"></span>
                </div>
            </div>
            <div class="admin-profile-premium">
                <div class="admin-avatar-premium">
                    <span>SA</span>
                </div>
                <div class="admin-info-premium">
                    <div class="admin-name-premium">Super Admin</div>
                    <div class="admin-role-premium">Administrator</div>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- ==========================================
         STATISTICS ROW - Premium KPI Cards
         ========================================== -->
    <div class="stats-row-premium">
        <!-- Pending -->
        <div class="stat-card-premium">
            <div class="stat-card-content">
                <div class="stat-icon-wrapper orange">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo number_format($pendingCount); ?></span>
                    <span class="stat-label">Pending Reports</span>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                    12% this week
                </span>
                <span class="stat-sub">Awaiting review</span>
            </div>
            <div class="stat-accent orange"></div>
        </div>

        <!-- Approved -->
        <div class="stat-card-premium">
            <div class="stat-card-content">
                <div class="stat-icon-wrapper green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo number_format($approvedCount); ?></span>
                    <span class="stat-label">Approved Reports</span>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                    <?php echo $approvalRate; ?>% approval rate
                </span>
                <span class="stat-sub">+8 this month</span>
            </div>
            <div class="stat-accent green"></div>
        </div>

        <!-- Rejected -->
        <div class="stat-card-premium">
            <div class="stat-card-content">
                <div class="stat-icon-wrapper red">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo number_format($rejectedCount); ?></span>
                    <span class="stat-label">Rejected Reports</span>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-trend down">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                        <polyline points="17 18 23 18 23 12"/>
                    </svg>
                    -3% this month
                </span>
                <span class="stat-sub"><?php echo $totalReports > 0 ? round(($rejectedCount / $totalReports) * 100) : 0; ?>% of total</span>
            </div>
            <div class="stat-accent red"></div>
        </div>

        <!-- Resolved -->
        <div class="stat-card-premium">
            <div class="stat-card-content">
                <div class="stat-icon-wrapper blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo number_format($resolvedCount); ?></span>
                    <span class="stat-label">Resolved Reports</span>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                    <?php echo $resolutionRate; ?>% resolution rate
                </span>
                <span class="stat-sub">+5 this week</span>
            </div>
            <div class="stat-accent blue"></div>
        </div>
    </div>

    <!-- ==========================================
         FILTER PILLS
         ========================================== -->
    <div class="filter-pills-container">
        <div class="filter-pills">
            <a href="?page=partnership&status=all" class="filter-pill <?php echo $statusFilter === '' || $statusFilter === 'all' ? 'active' : ''; ?>">
                All Reports
                <span class="pill-count"><?php echo $totalReports; ?></span>
            </a>
            <a href="?page=partnership&status=pending" class="filter-pill <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                <span class="pill-dot pending"></span>
                Pending
                <span class="pill-count"><?php echo $pendingCount; ?></span>
            </a>
            <a href="?page=partnership&status=approved" class="filter-pill <?php echo $statusFilter === 'approved' || $statusFilter === 'accepted' ? 'active' : ''; ?>">
                <span class="pill-dot approved"></span>
                Approved
                <span class="pill-count"><?php echo $approvedCount; ?></span>
            </a>
            <a href="?page=partnership&status=rejected" class="filter-pill <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">
                <span class="pill-dot rejected"></span>
                Rejected
                <span class="pill-count"><?php echo $rejectedCount; ?></span>
            </a>
            <a href="?page=partnership&status=resolved" class="filter-pill <?php echo $statusFilter === 'resolved' ? 'active' : ''; ?>">
                <span class="pill-dot resolved"></span>
                Resolved
                <span class="pill-count"><?php echo $resolvedCount; ?></span>
            </a>
        </div>
    </div>

    <!-- ==========================================
         SEARCH & CONTROLS BAR
         ========================================== -->
    <div class="controls-bar-premium">
        <div class="search-wrapper-premium">
            <svg class="search-icon-premium" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="searchInput" class="search-input-premium" 
                   placeholder="Search partner, reporter, email, or report ID..." 
                   value="<?php echo htmlspecialchars($searchFilter); ?>" 
                   onkeypress="if(event.key==='Enter') searchPartnerships()">
            <?php if ($searchFilter): ?>
                <button class="search-clear" onclick="clearSearch()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            <?php endif; ?>
        </div>
        <div class="controls-right">
            <div class="control-group">
                <select id="sortFilter" class="control-select" onchange="applyFilters()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="priority">Priority</option>
                </select>
            </div>
            <div class="control-group">
                <select id="dateFilter" class="control-select" onchange="applyFilters()">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>
            <button class="control-btn export" onclick="exportReports()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </button>
            <button class="control-btn refresh" onclick="window.location.reload()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- ==========================================
         REPORTS TABLE
         ========================================== -->
    <div class="table-container-premium">
        <div class="table-header-premium">
            <div class="table-title-section">
                <span class="table-title">All Reports</span>
                <span class="table-count"><?php echo $totalCount; ?> reports</span>
            </div>
            <div class="table-actions">
                <span class="table-last-updated">Last updated: <?php echo date('M j, g:i A'); ?></span>
            </div>
        </div>

        <?php if ($reports && $reports->num_rows > 0): ?>
            <div class="table-scroll">
                <table class="reports-table-premium">
                    <thead>
                        <tr>
                            <th>Report Details</th>
                            <th>Category</th>
                            <th>Submitted</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($report = $reports->fetch_assoc()): 
                            $status = $report['status'] ?? 'pending';
                            $dateValue = $report[$dateColumn] ?? $report['created_at'] ?? $report['createdAt'] ?? 'now';
                            $reportId = $report['id'];
                            $reportTitle = addslashes($report['title'] ?? 'Partner');
                            $reportEmail = $report['reported_by_email'] ?? '';
                            $priority = $report['priority'] ?? 'Medium';
                            $category = $report['category'] ?? 'General';
                            
                            // Get category style using the safe function
                            $catStyle = getCategoryStyle($category);
                            
                            // Priority color
                            $priorityColor = [
                                'High' => 'high',
                                'Medium' => 'medium',
                                'Low' => 'low'
                            ][$priority] ?? 'medium';
                            
                            // Status labels
                            $statusLabels = [
                                'pending' => 'Pending',
                                'open' => 'Pending',
                                'in_review' => 'In Review',
                                'approved' => 'Approved',
                                'accepted' => 'Approved',
                                'rejected' => 'Rejected',
                                'resolved' => 'Resolved'
                            ];
                            
                            // Status colors
                            $statusColor = [
                                'pending' => 'pending',
                                'open' => 'pending',
                                'in_review' => 'in-review',
                                'approved' => 'approved',
                                'accepted' => 'approved',
                                'rejected' => 'rejected',
                                'resolved' => 'resolved'
                            ][$status] ?? 'pending';
                        ?>
                            <tr class="report-row">
                                <td>
                                    <div class="report-details-cell">
                                        <div class="report-avatar" style="background: <?php 
                                            echo $status === 'approved' || $status === 'accepted' ? '#dcfce7' : 
                                                 ($status === 'rejected' ? '#fee2e2' : '#fef3c7'); 
                                        ?>;">
                                            <?php echo strtoupper(substr($report['title'] ?? 'P', 0, 2)); ?>
                                        </div>
                                        <div class="report-meta">
                                            <div class="report-title-text"><?php echo htmlspecialchars($report['title'] ?? 'Unknown Report'); ?></div>
                                            <div class="report-reporter">
                                                <span class="reporter-name"><?php echo htmlspecialchars($report['reported_by_name'] ?? 'Unknown'); ?></span>
                                                <span class="reporter-email">• <?php echo htmlspecialchars($reportEmail); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="category-badge-premium" style="background: <?php echo $catStyle['bg']; ?>; color: <?php echo $catStyle['text']; ?>;">
                                        <?php echo htmlspecialchars($category); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-cell-premium">
                                        <span class="date-main"><?php echo date('M j, Y', strtotime($dateValue)); ?></span>
                                        <span class="date-time"><?php echo date('g:i A', strtotime($dateValue)); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo $priorityColor; ?>">
                                        <span class="priority-dot"></span>
                                        <?php echo $priority; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill <?php echo $statusColor; ?>">
                                        <?php echo $statusLabels[$status] ?? ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="actions-col">
                                    <div class="action-buttons-premium">
                                        <button onclick="viewPartnership('<?php echo $reportId; ?>')" class="action-btn-premium view" title="View Details">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </button>
                                        <button onclick="emailPartner('<?php echo $reportEmail; ?>')" class="action-btn-premium message" title="Send Message">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                            </svg>
                                        </button>
                                        <?php if (in_array($status, ['pending', 'open', 'in_review'])): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="approve_partnership">
                                                <input type="hidden" name="id" value="<?php echo $reportId; ?>">
                                                <button type="submit" class="action-btn-premium approve" onclick="return confirm('Approve this partnership report?')" title="Approve">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="20 6 9 17 4 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <button onclick="showRejectModal('<?php echo $reportId; ?>', '<?php echo $reportTitle; ?>')" class="action-btn-premium reject" title="Reject">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($status !== 'resolved' && $status !== 'rejected'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="resolve_partnership">
                                                <input type="hidden" name="id" value="<?php echo $reportId; ?>">
                                                <button type="submit" class="action-btn-premium resolve" onclick="return confirm('Mark as resolved?')" title="Resolve">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_partnership">
                                            <input type="hidden" name="id" value="<?php echo $reportId; ?>">
                                            <button type="submit" class="action-btn-premium delete" onclick="return confirm('Delete this report?')" title="Delete">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- ==========================================
                 PAGINATION
                 ========================================== -->
            <div class="pagination-premium">
                <span class="pagination-info-premium">
                    Showing <strong><?php echo $startRange; ?></strong> to <strong><?php echo $endRange; ?></strong> of <strong><?php echo $totalCount; ?></strong> reports
                </span>
                <div class="pagination-controls">
                    <?php if ($currentPageNum > 1): ?>
                        <a href="?page=partnership&p=<?php echo $currentPageNum - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            Previous
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            Previous
                        </span>
                    <?php endif; ?>

                    <div class="page-numbers">
                        <?php
                        $maxPagesToShow = 5;
                        $halfRange = floor($maxPagesToShow / 2);
                        $startPage = max(1, $currentPageNum - $halfRange);
                        $endPage = min($totalPages, $currentPageNum + $halfRange);
                        
                        if ($startPage > 1) {
                            echo '<a href="?page=partnership&p=1&status=' . urlencode($statusFilter) . '&search=' . urlencode($searchFilter) . '" class="page-num">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="page-ellipsis">…</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?page=partnership&p=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-num <?php echo $i === $currentPageNum ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="page-ellipsis">…</span>';
                            }
                            echo '<a href="?page=partnership&p=' . $totalPages . '&status=' . urlencode($statusFilter) . '&search=' . urlencode($searchFilter) . '" class="page-num">' . $totalPages . '</a>';
                        }
                        ?>
                    </div>

                    <?php if ($currentPageNum < $totalPages): ?>
                        <a href="?page=partnership&p=<?php echo $currentPageNum + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-btn">
                            Next
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled">
                            Next
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- ==========================================
                   EMPTY STATE
                   ========================================== -->
            <div class="empty-state-premium">
                <div class="empty-icon-wrapper">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                </div>
                <h3 class="empty-title">No partnership reports found</h3>
                <p class="empty-description">
                    <?php if ($statusFilter && $statusFilter !== 'all'): ?>
                        No <?php echo ucfirst($statusFilter); ?> reports found. Try adjusting your filters.
                    <?php else: ?>
                        Partnership reports submitted by partners will appear here once they are created.
                    <?php endif; ?>
                </p>
                <?php if ($statusFilter && $statusFilter !== 'all'): ?>
                    <button class="empty-action-btn" onclick="resetFilters()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                        Reset Filters
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ==========================================
         VIEW MODAL
         ========================================== -->
    <div id="viewModal" class="modal-premium">
        <div class="modal-premium-content">
            <div class="modal-premium-header">
                <h3>Report Details</h3>
                <button class="modal-premium-close" onclick="closeModal('viewModal')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div id="viewModalBody" class="modal-premium-body">
                <!-- Loaded via JavaScript -->
            </div>
            <div class="modal-premium-footer">
                <button class="modal-btn approve" onclick="approveFromModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Approve
                </button>
                <button class="modal-btn email" onclick="emailFromModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    Email
                </button>
                <button class="modal-btn reject" onclick="rejectFromModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    Reject
                </button>
                <button class="modal-btn resolve" onclick="resolveFromModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Resolve
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================
         REJECT MODAL
         ========================================== -->
    <div id="rejectModal" class="modal-premium">
        <div class="modal-premium-content" style="max-width: 480px;">
            <div class="modal-premium-header">
                <h3>Reject Report</h3>
                <button class="modal-premium-close" onclick="closeModal('rejectModal')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject_partnership">
                <input type="hidden" name="id" id="rejectId">
                <div class="modal-premium-body">
                    <p class="reject-modal-text">Please provide a reason for rejecting <strong id="rejectName"></strong>'s report:</p>
                    <textarea name="rejection_reason" class="reject-textarea-premium" rows="4" placeholder="Enter rejection reason..."></textarea>
                </div>
                <div class="modal-premium-footer">
                    <button type="button" onclick="closeModal('rejectModal')" class="modal-btn cancel">Cancel</button>
                    <button type="submit" class="modal-btn reject">Reject Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     JAVASCRIPT
     ========================================== -->
<script>
function searchPartnerships() {
    const search = document.getElementById('searchInput').value;
    const status = '<?php echo $statusFilter; ?>';
    let url = '?page=partnership&p=1';
    if (status && status !== 'all') url += '&status=' + encodeURIComponent(status);
    if (search) url += '&search=' + encodeURIComponent(search);
    window.location.href = url;
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchPartnerships();
}

function applyFilters() {
    searchPartnerships();
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    window.location.href = '?page=partnership&p=1';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function exportReports() {
    alert('Exporting reports data...');
}

function viewPartnership(id) {
    fetch('api/get_partnership.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const r = data.data;
                const status = r.status || 'pending';
                const statusLabels = {
                    'pending': 'Pending',
                    'open': 'Pending',
                    'in_review': 'In Review',
                    'approved': 'Approved',
                    'accepted': 'Approved',
                    'rejected': 'Rejected',
                    'resolved': 'Resolved'
                };
                const statusColors = {
                    'pending': 'pending',
                    'open': 'pending',
                    'in_review': 'in-review',
                    'approved': 'approved',
                    'accepted': 'approved',
                    'rejected': 'rejected',
                    'resolved': 'resolved'
                };
                
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="modal-detail-grid">
                        <div class="modal-detail-left">
                            <div class="modal-detail-header">
                                <div class="modal-detail-avatar" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                    ${(r.title || 'P').charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <h4 style="font-size: 1.1rem; font-weight: 700; color: #0b1a33; margin: 0;">${r.title || 'Unknown Report'}</h4>
                                    <p style="color: #64748b; font-size: 0.85rem; margin: 0.25rem 0 0 0;">
                                        <span class="status-pill ${statusColors[status] || 'pending'}" style="font-size: 0.65rem;">
                                            ${statusLabels[status] || status.toUpperCase()}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="modal-detail-row">
                                <span class="modal-detail-label">Reported By</span>
                                <span class="modal-detail-value">${r.reported_by_name || 'Unknown'}</span>
                            </div>
                            <div class="modal-detail-row">
                                <span class="modal-detail-label">Email</span>
                                <span class="modal-detail-value"><a href="mailto:${r.reported_by_email}">${r.reported_by_email || 'N/A'}</a></span>
                            </div>
                            <div class="modal-detail-row">
                                <span class="modal-detail-label">Category</span>
                                <span class="modal-detail-value">${r.category || 'General'}</span>
                            </div>
                            <div class="modal-detail-row">
                                <span class="modal-detail-label">Priority</span>
                                <span class="modal-detail-value"><span class="priority-badge ${(r.priority || 'Medium').toLowerCase()}">${r.priority || 'Medium'}</span></span>
                            </div>
                            <div class="modal-detail-row">
                                <span class="modal-detail-label">Submitted</span>
                                <span class="modal-detail-value">${new Date(r.created_at || r.createdAt || Date.now()).toLocaleString()}</span>
                            </div>
                            ${r.reviewed_at ? `
                            <div class="modal-detail-row">
                                <span class="modal-detail-label">Reviewed At</span>
                                <span class="modal-detail-value">${new Date(r.reviewed_at).toLocaleString()}</span>
                            </div>
                            ` : ''}
                            ${r.rejection_reason ? `
                            <div class="modal-detail-row rejection-reason">
                                <span class="modal-detail-label">Rejection Reason</span>
                                <span class="modal-detail-value" style="color: #b91c1c;">${r.rejection_reason}</span>
                            </div>
                            ` : ''}
                        </div>
                        <div class="modal-detail-right">
                            <div class="modal-description-box">
                                <h5 style="font-size: 0.8rem; font-weight: 600; color: #0b1a33; margin: 0 0 0.5rem 0;">Description</h5>
                                <p style="color: #475569; font-size: 0.9rem; line-height: 1.6; margin: 0;">${r.description || 'No description provided.'}</p>
                            </div>
                            <div class="modal-quick-actions">
                                <button onclick="emailPartner('${r.reported_by_email}')" class="modal-quick-btn email">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Email
                                </button>
                                <button onclick="openChat()" class="modal-quick-btn chat">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Chat
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('viewModal').dataset.reportId = r.id;
                document.getElementById('viewModal').dataset.reportEmail = r.reported_by_email;
                document.getElementById('viewModal').dataset.reportStatus = status;
                document.getElementById('viewModal').style.display = 'flex';
            } else {
                alert('Error loading report details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading report details');
        });
}

function emailPartner(email) {
    if (email) {
        window.location.href = 'mailto:' + email;
    }
}

function emailFromModal() {
    const email = document.getElementById('viewModal').dataset.reportEmail;
    if (email) window.location.href = 'mailto:' + email;
}

function approveFromModal() {
    const id = document.getElementById('viewModal').dataset.reportId;
    if (id && confirm('Approve this partnership report?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve_partnership">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectFromModal() {
    const id = document.getElementById('viewModal').dataset.reportId;
    if (id) {
        document.getElementById('rejectId').value = id;
        closeModal('viewModal');
        document.getElementById('rejectModal').style.display = 'flex';
    }
}

function resolveFromModal() {
    const id = document.getElementById('viewModal').dataset.reportId;
    if (id && confirm('Mark this report as resolved?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="resolve_partnership">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showRejectModal(id, name) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectName').innerHTML = name;
    document.getElementById('rejectModal').style.display = 'flex';
}

function openChat() {
    alert('Open in-app chat with the reporter.');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-premium')) {
        event.target.style.display = 'none';
    }
}

// Auto-close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal-premium').forEach(m => m.style.display = 'none');
    }
});
</script>

<style>
/* ============================================
   PARTNERSHIP REPORTS - PREMIUM DASHBOARD
   ============================================ */

/* ----- Reset & Base ----- */
.partnership-dashboard {
    padding: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #0b1a33;
}

/* ----- Page Header ----- */
.page-header-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon-wrapper {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #2563EB, #3B82F6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.header-icon {
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.page-title-premium {
    font-size: 1.75rem;
    font-weight: 800;
    color: #0b1a33;
    letter-spacing: -0.02em;
    margin: 0;
}

.page-subtitle-premium {
    font-size: 0.9rem;
    color: #6b8cae;
    margin: 0.2rem 0 0 0;
}

.header-right-premium {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.notification-wrapper {
    position: relative;
}

.notification-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: 1px solid #e8edf5;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: #64748b;
}

.notification-btn:hover {
    border-color: #2563EB;
    color: #2563EB;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
}

.notification-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    border: 2px solid white;
}

.admin-profile-premium {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.4rem 0.8rem 0.4rem 0.4rem;
    border-radius: 12px;
    background: white;
    border: 1px solid #e8edf5;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-profile-premium:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.admin-avatar-premium {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #2563EB, #3B82F6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.7rem;
}

.admin-info-premium {
    line-height: 1.2;
}

.admin-name-premium {
    font-weight: 600;
    font-size: 0.8rem;
    color: #0b1a33;
}

.admin-role-premium {
    font-size: 0.6rem;
    color: #8aacce;
}

/* ----- Stats Row ----- */
.stats-row-premium {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.stat-card-premium {
    background: white;
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    border: 1px solid #e8edf5;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: default;
}

.stat-card-premium:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(11, 26, 51, 0.08);
}

.stat-card-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon-wrapper.orange {
    background: #fff7ed;
    color: #f59e0b;
}

.stat-icon-wrapper.green {
    background: #f0fdf4;
    color: #22c55e;
}

.stat-icon-wrapper.red {
    background: #fef2f2;
    color: #ef4444;
}

.stat-icon-wrapper.blue {
    background: #eff6ff;
    color: #3b82f6;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #0b1a33;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    color: #6b8cae;
    font-weight: 500;
}

.stat-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.25rem;
}

.stat-trend {
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-trend.up {
    color: #22c55e;
}

.stat-trend.down {
    color: #ef4444;
}

.stat-sub {
    font-size: 0.65rem;
    color: #94a3b8;
}

.stat-accent {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.stat-accent.orange {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.stat-accent.green {
    background: linear-gradient(90deg, #22c55e, #4ade80);
}

.stat-accent.red {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

.stat-accent.blue {
    background: linear-gradient(90deg, #3b82f6, #60a5fa);
}

/* ----- Filter Pills ----- */
.filter-pills-container {
    margin-bottom: 1.5rem;
}

.filter-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 1.2rem;
    border-radius: 40px;
    background: white;
    color: #475569;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid #e8edf5;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.filter-pill:hover {
    border-color: #cbd5e1;
    background: #f8faff;
    transform: translateY(-1px);
}

.filter-pill.active {
    background: linear-gradient(135deg, #2563EB, #3B82F6);
    color: white;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.filter-pill.active .pill-count {
    background: rgba(255,255,255,0.2);
    color: white;
}

.pill-count {
    padding: 0.05rem 0.6rem;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #475569;
}

.pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.pill-dot.pending { background: #f59e0b; }
.pill-dot.approved { background: #22c55e; }
.pill-dot.rejected { background: #ef4444; }
.pill-dot.resolved { background: #3b82f6; }

/* ----- Controls Bar ----- */
.controls-bar-premium {
    background: white;
    border-radius: 16px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    border: 1px solid #e8edf5;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.search-wrapper-premium {
    flex: 1;
    min-width: 200px;
    display: flex;
    align-items: center;
    background: #f8faff;
    border-radius: 10px;
    padding: 0.4rem 0.8rem;
    border: 1px solid #e8edf5;
    transition: all 0.2s;
}

.search-wrapper-premium:focus-within {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    background: white;
}

.search-icon-premium {
    color: #94a3b8;
    flex-shrink: 0;
}

.search-input-premium {
    border: none;
    background: transparent;
    width: 100%;
    outline: none;
    font-size: 0.85rem;
    color: #0b1a33;
    padding: 0.3rem 0.5rem;
}

.search-input-premium::placeholder {
    color: #94a3b8;
}

.search-clear {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.2rem;
    border-radius: 4px;
    transition: all 0.2s;
}

.search-clear:hover {
    color: #ef4444;
    background: #fef2f2;
}

.controls-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.control-group {
    display: flex;
    align-items: center;
}

.control-select {
    padding: 0.4rem 2rem 0.4rem 0.8rem;
    border-radius: 10px;
    border: 1px solid #e8edf5;
    background: #f8faff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b8cae' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 0.6rem center;
    background-size: 12px;
    font-size: 0.8rem;
    color: #0b1a33;
    cursor: pointer;
    appearance: none;
    transition: all 0.2s;
    min-width: 120px;
}

.control-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.control-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border-radius: 10px;
    border: 1px solid #e8edf5;
    background: #f8faff;
    font-size: 0.8rem;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}

.control-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.control-btn.export {
    background: linear-gradient(135deg, #2563EB, #3B82F6);
    color: white;
    border-color: transparent;
}

.control-btn.export:hover {
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    transform: translateY(-1px);
}

.control-btn.refresh {
    padding: 0.4rem 0.7rem;
}

/* ----- Table Container ----- */
.table-container-premium {
    background: white;
    border-radius: 16px;
    border: 1px solid #e8edf5;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}

.table-header-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e8edf5;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.table-title-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #0b1a33;
}

.table-count {
    font-size: 0.7rem;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 0.1rem 0.6rem;
    border-radius: 20px;
}

.table-last-updated {
    font-size: 0.65rem;
    color: #94a3b8;
}

.table-scroll {
    overflow-x: auto;
}

/* ----- Reports Table ----- */
.reports-table-premium {
    width: 100%;
    border-collapse: collapse;
}

.reports-table-premium thead {
    background: #f8faff;
}

.reports-table-premium th {
    text-align: left;
    padding: 0.7rem 1rem;
    font-size: 0.65rem;
    font-weight: 600;
    color: #6b8cae;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e8edf5;
    white-space: nowrap;
}

.reports-table-premium td {
    padding: 0.7rem 1rem;
    border-bottom: 1px solid #f0f4f9;
    vertical-align: middle;
    font-size: 0.8rem;
}

.reports-table-premium tbody tr {
    transition: background 0.15s;
}

.reports-table-premium tbody tr:hover {
    background: #fafcff;
}

.reports-table-premium tbody tr:last-child td {
    border-bottom: none;
}

/* ----- Report Details Cell ----- */
.report-details-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 200px;
}

.report-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.7rem;
    flex-shrink: 0;
    color: #0b1a33;
}

.report-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.report-title-text {
    font-weight: 600;
    color: #0b1a33;
}

.report-reporter {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    color: #94a3b8;
    flex-wrap: wrap;
}

.reporter-name {
    color: #475569;
}

.reporter-email {
    color: #94a3b8;
}

/* ----- Category Badge ----- */
.category-badge-premium {
    font-size: 0.65rem;
    padding: 0.2rem 0.7rem;
    border-radius: 20px;
    font-weight: 500;
    display: inline-block;
    white-space: nowrap;
}

/* ----- Date Cell ----- */
.date-cell-premium {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}

.date-main {
    font-weight: 500;
    color: #0b1a33;
}

.date-time {
    font-size: 0.6rem;
    color: #94a3b8;
}

/* ----- Priority Badge ----- */
.priority-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.2rem 0.7rem;
    border-radius: 20px;
}

.priority-badge .priority-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.priority-badge.high {
    background: #fef2f2;
    color: #dc2626;
}

.priority-badge.high .priority-dot {
    background: #dc2626;
}

.priority-badge.medium {
    background: #fff7ed;
    color: #d97706;
}

.priority-badge.medium .priority-dot {
    background: #d97706;
}

.priority-badge.low {
    background: #f0fdf4;
    color: #16a34a;
}

.priority-badge.low .priority-dot {
    background: #16a34a;
}

/* ----- Status Pill ----- */
.status-pill {
    font-size: 0.6rem;
    font-weight: 600;
    padding: 0.2rem 0.7rem;
    border-radius: 20px;
    display: inline-block;
}

.status-pill.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-pill.in-review {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-pill.approved {
    background: #dcfce7;
    color: #15803d;
}

.status-pill.rejected {
    background: #fee2e2;
    color: #b91c1c;
}

.status-pill.resolved {
    background: #f0fdf4;
    color: #16a34a;
}

/* ----- Action Buttons ----- */
.action-buttons-premium {
    display: flex;
    gap: 0.3rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.action-btn-premium {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: #94a3b8;
    background: transparent;
}

.action-btn-premium:hover {
    background: #f1f5f9;
    color: #475569;
}

.action-btn-premium.view:hover {
    background: #eff6ff;
    color: #3b82f6;
}

.action-btn-premium.message:hover {
    background: #f5f3ff;
    color: #6d5dfc;
}

.action-btn-premium.approve:hover {
    background: #dcfce7;
    color: #22c55e;
}

.action-btn-premium.reject:hover {
    background: #fee2e2;
    color: #ef4444;
}

.action-btn-premium.resolve:hover {
    background: #dbeafe;
    color: #3b82f6;
}

.action-btn-premium.delete:hover {
    background: #f1f5f9;
    color: #ef4444;
}

/* ----- Pagination ----- */
.pagination-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e8edf5;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.pagination-info-premium {
    font-size: 0.8rem;
    color: #6b8cae;
}

.pagination-info-premium strong {
    color: #0b1a33;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.page-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.8rem;
    border-radius: 8px;
    border: 1px solid #e8edf5;
    background: white;
    font-size: 0.75rem;
    font-weight: 500;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
}

.page-btn:hover:not(.disabled) {
    background: #f8faff;
    border-color: #cbd5e1;
}

.page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-numbers {
    display: flex;
    align-items: center;
    gap: 0.2rem;
}

.page-num {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 0.8rem;
    font-weight: 500;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s;
}

.page-num:hover {
    background: #f8faff;
    border-color: #e8edf5;
}

.page-num.active {
    background: linear-gradient(135deg, #2563EB, #3B82F6);
    color: white;
    border-color: transparent;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}

.page-ellipsis {
    color: #94a3b8;
    padding: 0 0.2rem;
}

/* ----- Empty State ----- */
.empty-state-premium {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon-wrapper {
    display: inline-block;
    margin-bottom: 1.5rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f8faff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c8d4e8;
}

.empty-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0b1a33;
    margin: 0 0 0.5rem 0;
}

.empty-description {
    color: #6b8cae;
    font-size: 0.9rem;
    margin: 0 0 1.5rem 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.empty-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.2rem;
    border-radius: 10px;
    border: 1px solid #e8edf5;
    background: white;
    font-size: 0.8rem;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}

.empty-action-btn:hover {
    background: #f8faff;
    border-color: #cbd5e1;
}

/* ----- Modals ----- */
.modal-premium {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    align-items: center;
    justify-content: center;
}

.modal-premium-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 720px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes modalSlideUp {
    from { transform: translateY(20px) scale(0.98); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.modal-premium-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e8edf5;
    position: sticky;
    top: 0;
    background: white;
    border-radius: 20px 20px 0 0;
    z-index: 1;
}

.modal-premium-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0b1a33;
    margin: 0;
}

.modal-premium-close {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 8px;
    transition: all 0.2s;
}

.modal-premium-close:hover {
    background: #f1f5f9;
    color: #ef4444;
}

.modal-premium-body {
    padding: 1.5rem;
}

.modal-premium-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e8edf5;
}

/* Modal Detail Grid */
.modal-detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.modal-detail-left {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.modal-detail-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.modal-detail-avatar {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
}

.modal-detail-row {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.4rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.modal-detail-row:last-child {
    border-bottom: none;
}

.modal-detail-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modal-detail-value {
    font-size: 0.85rem;
    color: #0b1a33;
}

.modal-detail-value a {
    color: #3b82f6;
    text-decoration: none;
}

.modal-detail-value a:hover {
    text-decoration: underline;
}

.rejection-reason {
    background: #fef2f2;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #fecaca;
}

.modal-detail-right {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.modal-description-box {
    background: #f8faff;
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid #eef3fc;
}

.modal-quick-actions {
    display: flex;
    gap: 0.5rem;
}

.modal-quick-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    padding: 0.5rem;
    border-radius: 10px;
    border: 1px solid #e8edf5;
    background: white;
    font-size: 0.75rem;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-quick-btn.email:hover {
    background: #f5f3ff;
    border-color: #6d5dfc;
    color: #6d5dfc;
}

.modal-quick-btn.chat:hover {
    background: #e0f2fe;
    border-color: #0369a1;
    color: #0369a1;
}

/* Modal Buttons */
.modal-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    border: none;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-btn.approve {
    background: #dcfce7;
    color: #15803d;
}

.modal-btn.approve:hover {
    background: #bbf7d0;
}

.modal-btn.email {
    background: #f5f3ff;
    color: #6d5dfc;
}

.modal-btn.email:hover {
    background: #ede9fe;
}

.modal-btn.reject {
    background: #fee2e2;
    color: #b91c1c;
}

.modal-btn.reject:hover {
    background: #fecaca;
}

.modal-btn.resolve {
    background: #dbeafe;
    color: #1d4ed8;
}

.modal-btn.resolve:hover {
    background: #bfdbfe;
}

.modal-btn.cancel {
    background: #f1f5f9;
    color: #475569;
}

.modal-btn.cancel:hover {
    background: #e2e8f0;
}

/* Reject Modal */
.reject-modal-text {
    font-size: 0.9rem;
    color: #475569;
    margin: 0 0 1rem 0;
}

.reject-textarea-premium {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 0.9rem;
    resize: vertical;
    transition: all 0.2s;
}

.reject-textarea-premium:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1200px) {
    .stats-row-premium {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .modal-detail-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-header-premium {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-right-premium {
        width: 100%;
        justify-content: flex-start;
    }
    
    .stats-row-premium {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .stat-card-premium {
        padding: 1rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .controls-bar-premium {
        flex-direction: column;
        align-items: stretch;
        padding: 0.75rem;
    }
    
    .search-wrapper-premium {
        width: 100%;
    }
    
    .controls-right {
        width: 100%;
        justify-content: stretch;
    }
    
    .control-group {
        flex: 1;
    }
    
    .control-select {
        width: 100%;
        min-width: 0;
    }
    
    .filter-pills {
        gap: 0.3rem;
    }
    
    .filter-pill {
        font-size: 0.7rem;
        padding: 0.3rem 0.8rem;
    }
    
    .table-scroll {
        overflow-x: auto;
    }
    
    .reports-table-premium {
        min-width: 700px;
    }
    
    .pagination-premium {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .pagination-controls {
        justify-content: center;
    }
    
    .modal-premium-content {
        width: 95%;
        margin: 0 auto;
    }
    
    .modal-premium-footer {
        flex-direction: column;
    }
    
    .modal-premium-footer .modal-btn {
        width: 100%;
        justify-content: center;
    }
    
    .modal-detail-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-quick-actions {
        flex-direction: column;
    }
    
    .admin-info-premium {
        display: none;
    }
}

@media (max-width: 480px) {
    .stats-row-premium {
        grid-template-columns: 1fr;
    }
    
    .page-title-premium {
        font-size: 1.3rem;
    }
    
    .header-icon-wrapper {
        width: 40px;
        height: 40px;
    }
    
    .filter-pills {
        gap: 0.2rem;
    }
    
    .filter-pill {
        font-size: 0.65rem;
        padding: 0.2rem 0.6rem;
    }
    
    .pill-count {
        font-size: 0.5rem;
        padding: 0.05rem 0.4rem;
    }
    
    .action-buttons-premium {
        gap: 0.15rem;
    }
    
    .action-btn-premium {
        width: 26px;
        height: 26px;
    }
}
</style>
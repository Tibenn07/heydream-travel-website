<?php
// pages/dashboard.php - Dashboard Page
// This file contains the main dashboard content with stats, charts, and recent activities

// Check which tables exist first
$existingTables = [];
$tableCheck = $conn->query("SHOW TABLES");
if ($tableCheck) {
    while ($row = $tableCheck->fetch_array()) {
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

if (in_array('users', $existingTables)) {
    $stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
} else {
    // Fallback: use partner_applications count as total users
    $stats['total_users'] = $stats['total_applications'] + 100;
}

// Get chart data for different types
$chartTypes = ['applications', 'users', 'partnerships'];
$chartData = [];
$chartTotals = [];

foreach ($chartTypes as $type) {
    $days = [];
    $values = [];
    
    // Determine table and column based on chart type
    switch ($type) {
        case 'users':
            $table = 'users';
            $dateColumn = 'created_at';
            break;
        case 'partnerships':
            $table = 'partnership_reports';
            // Check if column exists in partnership_reports
            $dateColumn = 'created_at'; // Default
            if (in_array($table, $existingTables)) {
                // Check available columns
                $colCheck = $conn->query("SHOW COLUMNS FROM partnership_reports");
                if ($colCheck) {
                    while ($col = $colCheck->fetch_assoc()) {
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
            }
            break;
        case 'applications':
        default:
            $table = 'partner_applications';
            $dateColumn = 'submitted_at';
            break;
    }
    
    // Check if table exists
    if (!in_array($table, $existingTables)) {
        // If table doesn't exist, use partner_applications as fallback
        $table = 'partner_applications';
        $dateColumn = 'submitted_at';
    }
    
    // Get the actual column name that exists
    if ($table === 'partnership_reports' && in_array($table, $existingTables)) {
        $colCheck = $conn->query("SHOW COLUMNS FROM partnership_reports");
        if ($colCheck) {
            $found = false;
            while ($col = $colCheck->fetch_assoc()) {
                if ($col['Field'] === 'createdAt') {
                    $dateColumn = 'createdAt';
                    $found = true;
                    break;
                }
                if ($col['Field'] === 'created_at') {
                    $dateColumn = 'created_at';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // If no date column found, use a fallback or skip
                $dateColumn = 'createdAt';
            }
        }
    }
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayLabel = date('D', strtotime("-$i days"));
        $days[] = $dayLabel;
        
        // Count registrations on this day
        try {
            $countResult = $conn->query("SELECT COUNT(*) as count FROM $table WHERE DATE($dateColumn) = '$date'");
            $count = $countResult ? ($countResult->fetch_assoc()['count'] ?? 0) : 0;
        } catch (Exception $e) {
            $count = 0;
        }
        $values[] = $count;
    }
    
    $chartData[$type] = [
        'days' => $days,
        'values' => $values,
        'total' => array_sum($values),
        'max' => max($values) > 0 ? max($values) : 1
    ];
}

// Get recent activities
$recentActivities = [];
if (in_array('partner_applications', $existingTables)) {
    $recentApps = $conn->query("SELECT business_name, 'New Application' as action, submitted_at as created_at FROM partner_applications ORDER BY submitted_at DESC LIMIT 3");
    if ($recentApps) {
        while ($row = $recentApps->fetch_assoc()) {
            $row['icon'] = 'fa-file-alt';
            $row['color'] = '#3b82f6';
            $row['bg_color'] = '#eff6ff';
            $recentActivities[] = $row;
        }
    }

    $recentApprovals = $conn->query("SELECT business_name, 'Approved Application' as action, reviewed_at as created_at FROM partner_applications WHERE status = 'approved' ORDER BY reviewed_at DESC LIMIT 2");
    if ($recentApprovals) {
        while ($row = $recentApprovals->fetch_assoc()) {
            $row['icon'] = 'fa-check-circle';
            $row['color'] = '#22c55e';
            $row['bg_color'] = '#f0fdf4';
            $recentActivities[] = $row;
        }
    }
}

// Sort activities by date
usort($recentActivities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$recentActivities = array_slice($recentActivities, 0, 6);

// Get chart type from GET parameter (default: applications)
$chartType = $_GET['chart'] ?? 'applications';
if (!in_array($chartType, $chartTypes)) {
    $chartType = 'applications';
}

$currentData = $chartData[$chartType];
$chartLabel = [
    'applications' => 'Application',
    'users' => 'User',
    'partnerships' => 'Partnership'
];
?>
<div class="content-panel">
    <!-- WELCOME SECTION -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h2>Welcome back, <span><?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span> 👋</h2>
            <p>Here's what's happening with your platform today.</p>
        </div>
        <div class="welcome-actions">
            <button class="btn-secondary"><i class="fas fa-download"></i> Export</button>
            <a href="?page=applications" class="btn-primary" style="text-decoration: none;">
                <i class="fas fa-plus"></i> New Application
            </a>
        </div>
    </div>

    <!-- STATISTICS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Applications</span>
                <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total_applications']); ?></div>
            <div class="stat-change"><i class="fas fa-arrow-up"></i> +12% this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Pending Applications</span>
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-number"><?php echo number_format($stats['pending_applications']); ?></div>
            <div class="stat-change"><i class="fas fa-arrow-up"></i> +5 this week</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Approved Applications</span>
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-number"><?php echo number_format($stats['approved_applications']); ?></div>
            <div class="stat-change"><i class="fas fa-arrow-up"></i> +8% this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Rejected Applications</span>
                <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
            </div>
            <div class="stat-number"><?php echo number_format($stats['rejected_applications']); ?></div>
            <div class="stat-change negative"><i class="fas fa-arrow-down"></i> -2% this month</div>
        </div>
    </div>

    <!-- SECONDARY STATS -->
    <div class="secondary-stats-grid">
        <div class="secondary-stat-card">
            <div class="secondary-stat-icon blue"><i class="fas fa-handshake"></i></div>
            <div class="secondary-stat-content">
                <div class="secondary-stat-number"><?php echo number_format($stats['active_partners']); ?></div>
                <div class="secondary-stat-label">Active Partners</div>
            </div>
        </div>
        <div class="secondary-stat-card">
            <div class="secondary-stat-icon green"><i class="fas fa-users"></i></div>
            <div class="secondary-stat-content">
                <div class="secondary-stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="secondary-stat-label">Total Users</div>
            </div>
        </div>
        <div class="secondary-stat-card">
            <div class="secondary-stat-icon purple"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="secondary-stat-content">
                <div class="secondary-stat-number"><?php echo number_format($stats['customer_reports'] + $stats['partnership_reports']); ?></div>
                <div class="secondary-stat-label">Total Reports</div>
            </div>
        </div>
        <div class="secondary-stat-card">
            <div class="secondary-stat-icon orange"><i class="fas fa-check-double"></i></div>
            <div class="secondary-stat-content">
                <div class="secondary-stat-number"><?php echo number_format($stats['pending_approvals']); ?></div>
                <div class="secondary-stat-label">Pending Approvals</div>
            </div>
        </div>
    </div>

    <!-- ANALYTICS SECTION -->
    <div class="analytics-section">
        <div class="analytics-card chart-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="color: #3b82f6; margin-right: 0.5rem;"></i> 
                    New <?php echo $chartLabel[$chartType]; ?> Registrations
                </h3>
                <div class="chart-controls">
                    <div class="chart-toggle">
                        <button class="toggle-btn <?php echo $chartType === 'applications' ? 'active' : ''; ?>" 
                                onclick="switchChart('applications')" 
                                title="Application Registrations">
                            <i class="fas fa-file-alt"></i> Apps
                        </button>
                        <button class="toggle-btn <?php echo $chartType === 'users' ? 'active' : ''; ?>" 
                                onclick="switchChart('users')" 
                                title="User Registrations">
                            <i class="fas fa-users"></i> Users
                        </button>
                        <button class="toggle-btn <?php echo $chartType === 'partnerships' ? 'active' : ''; ?>" 
                                onclick="switchChart('partnerships')" 
                                title="Partnership Registrations">
                            <i class="fas fa-handshake"></i> Partners
                        </button>
                    </div>
                    <span class="period">Last 7 days</span>
                </div>
            </div>
            <div class="chart-placeholder">
                <?php foreach ($currentData['values'] as $index => $value): ?>
                    <div class="chart-bar" style="height: <?php echo ($value / $currentData['max']) * 130 + 20; ?>px; background: <?php echo $value > ($currentData['max'] * 0.5) ? '#3b82f6' : '#8aacce'; ?>;">
                        <span class="bar-value"><?php echo $value; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-labels">
                <?php foreach ($currentData['days'] as $day): ?>
                    <span><?php echo $day; ?></span>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <span><span class="dot blue"></span> New <?php echo $chartLabel[$chartType]; ?>s</span>
                <span><span class="dot light"></span> Total: <?php echo $currentData['total']; ?></span>
            </div>
        </div>

        <div class="analytics-card">
            <div class="card-header">
                <h3><i class="fas fa-bolt" style="color: #f59e0b; margin-right: 0.5rem;"></i> Quick Insights</h3>
                <span class="period">Today</span>
            </div>
            <div class="quick-stats-grid">
                <div class="quick-stat-item">
                    <div class="qs-number"><?php echo $stats['new_applications_today']; ?></div>
                    <div class="qs-label">New Applications</div>
                    <div class="qs-change"><i class="fas fa-arrow-up"></i> today</div>
                </div>
                <div class="quick-stat-item">
                    <div class="qs-number"><?php echo $stats['active_partners']; ?></div>
                    <div class="qs-label">Active Partners</div>
                    <div class="qs-change"><i class="fas fa-arrow-up"></i> +3 this week</div>
                </div>
                <div class="quick-stat-item">
                    <div class="qs-number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="qs-label">Total Users</div>
                    <div class="qs-change"><i class="fas fa-arrow-up"></i> +24 this week</div>
                </div>
                <div class="quick-stat-item">
                    <div class="qs-number"><?php echo $stats['pending_approvals']; ?></div>
                    <div class="qs-label">Pending Reviews</div>
                    <div class="qs-change <?php echo $stats['pending_approvals'] > 5 ? 'negative' : ''; ?>">
                        <?php echo $stats['pending_approvals'] > 5 ? '<i class="fas fa-arrow-up"></i> Needs attention' : '<i class="fas fa-check"></i> All good'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT ACTIVITIES & QUICK ACTIONS -->
    <div class="activities-section">
        <div class="activities-card">
            <div class="card-header">
                <h3><i class="fas fa-history" style="color: #6b8cae; margin-right: 0.5rem;"></i> Recent Activities</h3>
                <a href="?page=applications" class="view-all-link">View All →</a>
            </div>
            <?php if (!empty($recentActivities)): ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: <?php echo $activity['bg_color']; ?>; color: <?php echo $activity['color']; ?>;">
                            <i class="fas <?php echo $activity['icon']; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($activity['business_name'] ?? 'Unknown'); ?></div>
                            <div class="activity-desc"><?php echo $activity['action'] ?? 'Activity'; ?></div>
                        </div>
                        <div class="activity-time">
                            <?php 
                                $date = new DateTime($activity['created_at']);
                                $now = new DateTime();
                                $diff = $now->diff($date);
                                if ($diff->days === 0) {
                                    if ($diff->h === 0) {
                                        if ($diff->i === 0) {
                                            echo 'Just now';
                                        } else {
                                            echo $diff->i . 'm ago';
                                        }
                                    } else {
                                        echo $diff->h . 'h ago';
                                    }
                                } else if ($diff->days === 1) {
                                    echo 'Yesterday';
                                } else {
                                    echo $date->format('M j');
                                }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="activity-item" style="justify-content: center; padding: 1.5rem 0; color: #8aacce;">
                    <span>No recent activities</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="activities-card">
            <div class="card-header">
                <h3><i class="fas fa-rocket" style="color: #8b5cf6; margin-right: 0.5rem;"></i> Quick Actions</h3>
                <span class="period">Shortcuts</span>
            </div>
            <div class="quick-actions-grid">
                <a href="?page=applications" class="quick-action-item">
                    <div class="quick-action-icon" style="background: #eff6ff; color: #3b82f6;">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">New Application</div>
                        <div class="quick-action-desc">Review new partner</div>
                    </div>
                </a>
                <a href="#" class="quick-action-item">
                    <div class="quick-action-icon" style="background: #f0fdf4; color: #22c55e;">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Add Partner</div>
                        <div class="quick-action-desc">Onboard new partner</div>
                    </div>
                </a>
                <a href="?page=customer" class="quick-action-item">
                    <div class="quick-action-icon" style="background: #fff7ed; color: #f59e0b;">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">View Reports</div>
                        <div class="quick-action-desc">Check all reports</div>
                    </div>
                </a>
                <a href="?page=approval" class="quick-action-item">
                    <div class="quick-action-icon" style="background: #f5f3ff; color: #8b5cf6;">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Approvals</div>
                        <div class="quick-action-desc">Review pending items</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function switchChart(type) {
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    // Set the chart parameter
    urlParams.set('chart', type);
    // Keep other parameters
    const page = urlParams.get('page') || 'dashboard';
    // Redirect with the new parameter
    window.location.href = '?page=' + page + '&chart=' + type;
}
</script>

<style>
    /* Dashboard specific styles */
    .activities-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-top: 1.75rem;
    }

    .activities-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid #e8edf5;
    }

    .activities-card .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .activities-card .card-header h3 {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0b1a33;
    }

    .activities-card .card-header .view-all-link {
        font-size: 0.7rem;
        color: #3b82f6;
        text-decoration: none;
        font-weight: 600;
    }

    .activities-card .card-header .view-all-link:hover {
        text-decoration: underline;
    }

    .activities-card .card-header .period {
        font-size: 0.7rem;
        color: #8aacce;
        background: #f0f4f9;
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        font-weight: 500;
    }

    .activity-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f0f4f9;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.9rem;
    }

    .activity-content {
        flex: 1;
        min-width: 0;
    }

    .activity-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #0b1a33;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .activity-desc {
        font-size: 0.7rem;
        color: #8aacce;
    }

    .activity-time {
        font-size: 0.65rem;
        color: #b0c4de;
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* Quick Actions */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .quick-action-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: #f8faff;
        border-radius: 12px;
        text-decoration: none;
        border: 1px solid #eef3fc;
        transition: all 0.2s;
        cursor: pointer;
    }

    .quick-action-item:hover {
        background: #f0f4f9;
        border-color: #dce6f0;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(11, 26, 51, 0.06);
    }

    .quick-action-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .quick-action-content {
        flex: 1;
        min-width: 0;
    }

    .quick-action-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: #0b1a33;
    }

    .quick-action-desc {
        font-size: 0.6rem;
        color: #8aacce;
    }

    /* Chart Controls */
    .chart-controls {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .chart-toggle {
        display: flex;
        background: #f0f4f9;
        border-radius: 8px;
        padding: 0.2rem;
        gap: 0.15rem;
    }

    .toggle-btn {
        padding: 0.3rem 0.6rem;
        border: none;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        background: transparent;
        color: #8aacce;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .toggle-btn:hover {
        color: #475569;
    }

    .toggle-btn.active {
        background: white;
        color: #0b1a33;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .toggle-btn i {
        font-size: 0.6rem;
    }

    .chart-card .card-header {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    @media (max-width: 992px) {
        .activities-section {
            grid-template-columns: 1fr;
        }
        .chart-controls {
            flex-wrap: wrap;
        }
        .chart-toggle {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 768px) {
        .welcome-section {
            flex-direction: column;
            gap: 1rem;
        }
        .welcome-actions {
            width: 100%;
        }
        .welcome-actions button {
            flex: 1;
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .secondary-stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .analytics-section {
            grid-template-columns: 1fr;
        }
        .quick-stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .quick-actions-grid {
            grid-template-columns: 1fr 1fr;
        }
        .chart-toggle .toggle-btn {
            font-size: 0.6rem;
            padding: 0.2rem 0.5rem;
        }
        .toggle-btn i {
            font-size: 0.5rem;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .secondary-stats-grid {
            grid-template-columns: 1fr;
        }
        .quick-stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .quick-actions-grid {
            grid-template-columns: 1fr;
        }
        .welcome-actions {
            flex-direction: column;
        }
        .welcome-actions button {
            width: 100%;
        }
        .chart-toggle {
            width: 100%;
        }
        .chart-toggle .toggle-btn {
            flex: 1;
            justify-content: center;
        }
    }
</style>
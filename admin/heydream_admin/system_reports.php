<?php
// system_reports.php - System App Reports in Table Format

// Mock data for system reports with reporter information
$reports = [
    [
        'id' => 'sys_1',
        'issue' => 'Payment gateway timeout',
        'reported_by_name' => 'Jane D.',
        'reported_by_email' => 'jane.doe@email.com',
        'reported_to' => 'Customer',
        'status' => 'Open',
        'date_reported' => '2024-05-23 10:24:00',
        'severity' => 'High'
    ],
    [
        'id' => 'sys_2',
        'issue' => 'React Native navigation crash',
        'reported_by_name' => 'Mike R.',
        'reported_by_email' => 'mike@email.com',
        'reported_to' => 'Customer',
        'status' => 'In Progress',
        'date_reported' => '2024-05-22 15:15:00',
        'severity' => 'Medium'
    ],
    [
        'id' => 'sys_3',
        'issue' => 'Partner package not synced',
        'reported_by_name' => 'Partner Team',
        'reported_by_email' => 'partner@email.com',
        'reported_to' => 'Partner',
        'status' => 'Open',
        'date_reported' => '2024-05-22 11:48:00',
        'severity' => 'High'
    ],
    [
        'id' => 'sys_4',
        'issue' => 'Server error on profile update',
        'reported_by_name' => 'Sarah L.',
        'reported_by_email' => 'sarah@email.com',
        'reported_to' => 'Customer',
        'status' => 'Resolved',
        'date_reported' => '2024-05-21 09:30:00',
        'severity' => 'Medium'
    ],
    [
        'id' => 'sys_5',
        'issue' => 'Map not loading correctly',
        'reported_by_name' => 'Partner Adventures',
        'reported_by_email' => 'partner.adventures@email.com',
        'reported_to' => 'Partner',
        'status' => 'Resolved',
        'date_reported' => '2024-05-20 18:05:00',
        'severity' => 'Low'
    ],
    [
        'id' => 'sys_6',
        'issue' => 'Push notification delay',
        'reported_by_name' => 'Tech Support',
        'reported_by_email' => 'tech@heydream.com',
        'reported_to' => 'System',
        'status' => 'In Progress',
        'date_reported' => '2024-05-24 08:30:00',
        'severity' => 'Medium'
    ],
    [
        'id' => 'sys_7',
        'issue' => 'Search results not loading',
        'reported_by_name' => 'John D.',
        'reported_by_email' => 'john@email.com',
        'reported_to' => 'Customer',
        'status' => 'Open',
        'date_reported' => '2024-05-24 14:20:00',
        'severity' => 'High'
    ]
];

// Calculate stats
$totalReports = count($reports);
$openReports = count(array_filter($reports, fn($r) => $r['status'] === 'Open'));
$resolvedReports = count(array_filter($reports, fn($r) => $r['status'] === 'Resolved'));
$inProgressReports = count(array_filter($reports, fn($r) => $r['status'] === 'In Progress'));

// Handle filtering
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$reportedToFilter = $_GET['reported_to'] ?? '';

$filteredReports = array_filter($reports, function($r) use ($search, $statusFilter, $reportedToFilter) {
    if ($search && stripos($r['issue'], $search) === false && stripos($r['reported_by_name'], $search) === false) {
        return false;
    }
    if ($statusFilter && $r['status'] !== $statusFilter) {
        return false;
    }
    if ($reportedToFilter && $r['reported_to'] !== $reportedToFilter) {
        return false;
    }
    return true;
});

$filteredCount = count($filteredReports);
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$itemsPerPage = 5;
$totalPages = ceil($filteredCount / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;
$paginatedReports = array_slice(array_values($filteredReports), $offset, $itemsPerPage);
?>
<div class="system-reports-container">
    <!-- Header Section -->
    <div class="reports-header">
        <p class="reports-description">Monitor and manage technical issues, system errors, and performance problems.</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Total Reports</span><i class="fas fa-chart-line stat-icon"></i></div>
            <div class="stat-number"><?php echo $totalReports; ?></div>
            <div class="stat-change">+16% vs last 7 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Open</span><i class="fas fa-clock stat-icon"></i></div>
            <div class="stat-number"><?php echo $openReports; ?></div>
            <div class="stat-change">+12% vs last 7 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Resolved</span><i class="fas fa-check-circle stat-icon"></i></div>
            <div class="stat-number"><?php echo $resolvedReports; ?></div>
            <div class="stat-change">+25% vs last 7 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">In Progress</span><i class="fas fa-spinner stat-icon"></i></div>
            <div class="stat-number"><?php echo $inProgressReports; ?></div>
            <div class="stat-change negative">-5% vs last 7 days</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" style="display: contents;">
            <input type="hidden" name="page" value="system">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search reports, keywords, users..." value="<?php echo escapeHtml($search); ?>">
            </div>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="Open" <?php echo $statusFilter === 'Open' ? 'selected' : ''; ?>>Open</option>
                <option value="In Progress" <?php echo $statusFilter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="Resolved" <?php echo $statusFilter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
            </select>
            <select name="reported_to" class="filter-select" onchange="this.form.submit()">
                <option value="">All Reported To</option>
                <option value="Customer" <?php echo $reportedToFilter === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                <option value="Partner" <?php echo $reportedToFilter === 'Partner' ? 'selected' : ''; ?>>Partner</option>
                <option value="System" <?php echo $reportedToFilter === 'System' ? 'selected' : ''; ?>>System</option>
            </select>
            <a href="?page=system" class="reset-btn"><i class="fas fa-undo"></i> Reset</a>
        </form>
    </div>

    <!-- Reports Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Issue</th>
                    <th>Reported By</th>
                    <th>Reported To</th>
                    <th>Status</th>
                    <th>Date Reported</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paginatedReports as $report): ?>
                <tr>
                    <td class="issue-cell">
                        <div class="issue-title"><?php echo escapeHtml($report['issue']); ?></div>
                        <?php if ($report['severity'] === 'High'): ?>
                            <span class="severity-tag high">High Priority</span>
                        <?php elseif ($report['severity'] === 'Medium'): ?>
                            <span class="severity-tag medium">Medium Priority</span>
                        <?php else: ?>
                            <span class="severity-tag low">Low Priority</span>
                        <?php endif; ?>
                    </td>
                    <td class="reporter-cell">
                        <div class="reporter-name"><?php echo escapeHtml($report['reported_by_name']); ?></div>
                        <div class="reporter-email"><?php echo escapeHtml($report['reported_by_email']); ?></div>
                    </td>
                    <td>
                        <span class="reported-to-badge <?php echo strtolower($report['reported_to']); ?>">
                            <?php echo $report['reported_to']; ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                            <?php echo $report['status']; ?>
                        </span>
                    </td>
                    <td><?php echo formatDate($report['date_reported']); ?></td>
                    <td class="action-buttons-cell">
                        <button onclick="sendMessage('<?php echo $report['id']; ?>')" class="action-icon" title="Message in App">
                            <i class="fas fa-comment-dots"></i>
                        </button>
                        <button onclick="sendEmail('<?php echo $report['reported_by_email']; ?>', '<?php echo escapeHtml($report['issue']); ?>')" class="action-icon" title="Email">
                            <i class="fas fa-envelope"></i>
                        </button>
                        <?php if ($report['status'] !== 'Resolved'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="resolve_system">
                            <input type="hidden" name="id" value="<?php echo $report['id']; ?>">
                            <input type="hidden" name="redirect_page" value="system">
                            <button type="submit" class="action-icon" title="Mark Resolved">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($paginatedReports)): ?>
                <tr>
                    <td colspan="6" class="empty-message">No reports found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <span>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $itemsPerPage, $filteredCount); ?> of <?php echo $filteredCount; ?> reports</span>
        <div class="pagination-links">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=system&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&reported_to=<?php echo urlencode($reportedToFilter); ?>" class="page-link <?php echo $i === $currentPage ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
function sendMessage(reportId) {
    alert('Opening in-app chat for report #' + reportId);
}

function sendEmail(email, issue) {
    window.location.href = 'mailto:' + email + '?subject=Regarding: ' + encodeURIComponent(issue);
}
</script>

<style>
.system-reports-container {
    background: transparent;
}

.reports-header {
    margin-bottom: 1.5rem;
}

.reports-description {
    color: #64748b;
    font-size: 0.9rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: 1rem;
    padding: 1rem 1.2rem;
    border: 1px solid #eef2ff;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #0f172a;
}

.stat-label {
    font-size: 0.8rem;
    color: #64748b;
}

.stat-change {
    font-size: 0.7rem;
    color: #22c55e;
    margin-top: 0.25rem;
}

.stat-change.negative {
    color: #ef4444;
}

.stat-icon {
    font-size: 1.5rem;
    opacity: 0.5;
}

.filter-bar {
    background: white;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    border: 1px solid #eef2ff;
}

.search-box {
    flex: 1;
    min-width: 200px;
    display: flex;
    align-items: center;
    background: #f8fafc;
    border-radius: 40px;
    padding: 0.5rem 1rem;
    border: 1px solid #e2e8f0;
}

.search-box i {
    color: #94a3b8;
    margin-right: 0.5rem;
}

.search-box input {
    border: none;
    background: transparent;
    width: 100%;
    outline: none;
    font-size: 0.85rem;
}

.filter-select {
    padding: 0.5rem 1rem;
    border-radius: 40px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.85rem;
    cursor: pointer;
}

.reset-btn {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 0.5rem 1.2rem;
    border-radius: 40px;
    text-decoration: none;
    font-size: 0.85rem;
}

.data-table {
    background: white;
    border-radius: 1rem;
    border: 1px solid #eef2ff;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 1rem 1rem;
    background: #f8fafc;
    font-weight: 600;
    font-size: 0.8rem;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 1rem 1rem;
    border-bottom: 1px solid #eef2ff;
    font-size: 0.85rem;
    vertical-align: top;
}

tr:hover {
    background: #fafcff;
}

.issue-cell {
    min-width: 200px;
}

.issue-title {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 0.3rem;
}

.severity-tag {
    font-size: 0.65rem;
    padding: 0.15rem 0.5rem;
    border-radius: 12px;
    display: inline-block;
}

.severity-tag.high {
    background: #fee2e2;
    color: #b91c1c;
}

.severity-tag.medium {
    background: #fff3e3;
    color: #b45309;
}

.severity-tag.low {
    background: #dcfce7;
    color: #15803d;
}

.reporter-cell {
    min-width: 150px;
}

.reporter-name {
    font-weight: 500;
    color: #0f172a;
}

.reporter-email {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 0.2rem;
}

.reported-to-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    display: inline-block;
}

.reported-to-badge.customer {
    background: #e0f2fe;
    color: #0369a1;
}

.reported-to-badge.partner {
    background: #dcfce7;
    color: #15803d;
}

.reported-to-badge.system {
    background: #f1f5f9;
    color: #475569;
}

.status-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    display: inline-block;
}

.status-badge.open {
    background: #fee2e2;
    color: #b91c1c;
}

.status-badge.in-progress {
    background: #fff3e3;
    color: #b45309;
}

.status-badge.resolved {
    background: #dcfce7;
    color: #15803d;
}

.action-buttons-cell {
    white-space: nowrap;
}

.action-icon {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    font-size: 1rem;
    margin: 0 0.3rem;
    transition: color 0.2s;
}

.action-icon:hover {
    color: #3b82f6;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.5rem;
    padding: 1rem;
    background: white;
    border-radius: 1rem;
    border: 1px solid #eef2ff;
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination-links {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.page-link {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    text-decoration: none;
    color: #475569;
    border: 1px solid #e2e8f0;
    font-size: 0.85rem;
}

.page-link.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.empty-message {
    text-align: center;
    padding: 2rem;
    color: #94a3b8;
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .pagination {
        flex-direction: column;
        text-align: center;
    }
    
    th, td {
        padding: 0.75rem;
    }
}
</style>
<?php
// partnership_reports.php
$reports = $_SESSION['heydream_data']['partnership_reports'];
$stats = [
    'total' => count($reports),
    'open' => count(array_filter($reports, fn($r) => $r['status'] === 'open')),
    'in_review' => count(array_filter($reports, fn($r) => $r['status'] === 'in_review')),
    'resolved' => count(array_filter($reports, fn($r) => $r['status'] === 'resolved'))
];
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-header"><span class="stat-label">Total Reports</span><i class="fas fa-chart-line stat-icon"></i></div><div class="stat-number"><?php echo $stats['total']; ?></div><div class="stat-change">+45% vs last 7 days</div></div>
    <div class="stat-card"><div class="stat-header"><span class="stat-label">Open Reports</span><i class="fas fa-clock stat-icon"></i></div><div class="stat-number"><?php echo $stats['open']; ?></div><div class="stat-change">+35% vs last 7 days</div></div>
    <div class="stat-card"><div class="stat-header"><span class="stat-label">Resolved Reports</span><i class="fas fa-check-circle stat-icon"></i></div><div class="stat-number"><?php echo $stats['resolved']; ?></div><div class="stat-change">+18% vs last 7 days</div></div>
    <div class="stat-card"><div class="stat-header"><span class="stat-label">In Review</span><i class="fas fa-search stat-icon"></i></div><div class="stat-number"><?php echo $stats['in_review']; ?></div><div class="stat-change">+12% vs last 7 days</div></div>
</div>

<div class="filter-bar">
    <form method="GET" style="display: contents;">
        <input type="hidden" name="page" value="partnership">
        <div class="search-box"><i class="fas fa-search"></i><input type="text" name="search" placeholder="Search reports, partners..." value="<?php echo $_GET['search'] ?? ''; ?>"></div>
        <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="open" <?php echo ($_GET['status'] ?? '') === 'open' ? 'selected' : ''; ?>>Open</option>
            <option value="in_review" <?php echo ($_GET['status'] ?? '') === 'in_review' ? 'selected' : ''; ?>>In Review</option>
            <option value="resolved" <?php echo ($_GET['status'] ?? '') === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
        </select>
        <a href="?page=partnership" class="reset-btn"><i class="fas fa-undo"></i> Reset</a>
    </form>
</div>

<div class="data-table">
    <table>
        <thead><tr><th>Report</th><th>Reported By</th><th>Category</th><th>Priority</th><th>Status</th><th>Date Reported</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
            <tr>
                <td><div class="report-title-cell"><?php echo escapeHtml($report['title']); ?></div><div class="report-desc" style="font-size:0.75rem;"><?php echo escapeHtml(substr($report['description'], 0, 50)); ?>...</div></td>
                <td><?php echo escapeHtml($report['reportedBy']); ?><div class="report-email"><?php echo escapeHtml($report['reportedEmail']); ?></div></td>
                <td><span class="category-badge"><?php echo $report['category']; ?></span></td>
                <td><?php echo getPriorityBadge($report['priority']); ?></td>
                <td><?php echo getStatusBadge($report['status']); ?></td>
                <td><?php echo formatDate($report['createdAt']); ?></td>
                <td class="action-icons">
                    <form method="POST"><input type="hidden" name="action" value="update_partner_status"><input type="hidden" name="id" value="<?php echo $report['id']; ?>"><input type="hidden" name="new_status" value="in_review"><input type="hidden" name="redirect_page" value="partnership"><button title="Review"><i class="fas fa-eye"></i></button></form>
                    <form method="POST"><input type="hidden" name="action" value="update_partner_status"><input type="hidden" name="id" value="<?php echo $report['id']; ?>"><input type="hidden" name="new_status" value="resolved"><input type="hidden" name="redirect_page" value="partnership"><button title="Resolve"><i class="fas fa-check-circle"></i></button></form>
                    <form method="POST" onsubmit="return confirm('Delete this report?')"><input type="hidden" name="action" value="delete_partner"><input type="hidden" name="id" value="<?php echo $report['id']; ?>"><input type="hidden" name="redirect_page" value="partnership"><button title="Delete"><i class="fas fa-trash-alt"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
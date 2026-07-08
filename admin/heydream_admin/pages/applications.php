<?php
// pages/applications.php - Partner Applications Page

// Get filters from URL
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$sortFilter = $_GET['sort'] ?? 'newest';
$currentPageNum = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($currentPageNum < 1) $currentPageNum = 1;

$itemsPerPage = 10;

// Build the count query
$countSql = "SELECT COUNT(*) as total FROM partner_applications";
$whereConditions = [];
$params = [];
$types = "";

if ($statusFilter && $statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if ($searchFilter) {
    $whereConditions[] = "(business_name LIKE ? OR email LIKE ?)";
    $params[] = "%" . $searchFilter . "%";
    $params[] = "%" . $searchFilter . "%";
    $types .= "ss";
}
if ($categoryFilter && $categoryFilter !== 'all') {
    $whereConditions[] = "business_type = ?";
    $params[] = $categoryFilter;
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
$dataSql = "SELECT * FROM partner_applications";
if (!empty($whereConditions)) {
    $dataSql .= " WHERE " . implode(" AND ", $whereConditions);
}

// Sorting
switch ($sortFilter) {
    case 'oldest':
        $dataSql .= " ORDER BY submitted_at ASC";
        break;
    case 'name':
        $dataSql .= " ORDER BY business_name ASC";
        break;
    default:
        $dataSql .= " ORDER BY submitted_at DESC";
        break;
}

$dataSql .= " LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;
$types .= "ii";

$dataStmt = $conn->prepare($dataSql);
if (!empty($params)) {
    $dataStmt->bind_param($types, ...$params);
}
$dataStmt->execute();
$applications = $dataStmt->get_result();
$dataStmt->close();

// Calculate showing range
$startRange = $totalCount > 0 ? $offset + 1 : 0;
$endRange = min($offset + $itemsPerPage, $totalCount);

// Get status counts for the filter tabs
$statusCounts = [];
$statusCounts['all'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications")->fetch_assoc()['count'] ?? 0;
$statusCounts['pending'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$statusCounts['approved'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'approved'")->fetch_assoc()['count'] ?? 0;
$statusCounts['rejected'] = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'rejected'")->fetch_assoc()['count'] ?? 0;

// Get category counts
$categoryCounts = [];
$categoryResult = $conn->query("SELECT business_type, COUNT(*) as count FROM partner_applications GROUP BY business_type");
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryCounts[$row['business_type']] = $row['count'];
    }
}
?>
<div class="content-panel">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #0b1a33;">Partner Applications</h2>
            <p style="color: #6b8cae; font-size: 0.9rem; margin-top: 0.25rem;">
                Review and manage all partner applications submitted to HeyDream Travel & Tours.
            </p>
        </div>
        <div>
            <span style="font-size: 0.85rem; color: #6b8cae; background: #f0f4f9; padding: 0.4rem 1rem; border-radius: 40px;">
                <i class="fas fa-file-alt"></i> <?php echo $totalCount; ?> total applications
            </span>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="status-tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <a href="?page=applications&status=all" class="status-tab <?php echo $statusFilter === '' || $statusFilter === 'all' ? 'active' : ''; ?>">
            All <span class="tab-count"><?php echo $statusCounts['all']; ?></span>
        </a>
        <a href="?page=applications&status=pending" class="status-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
            Pending <span class="tab-count pending"><?php echo $statusCounts['pending']; ?></span>
        </a>
        <a href="?page=applications&status=approved" class="status-tab <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">
            Approved <span class="tab-count approved"><?php echo $statusCounts['approved']; ?></span>
        </a>
        <a href="?page=applications&status=rejected" class="status-tab <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">
            Rejected <span class="tab-count rejected"><?php echo $statusCounts['rejected']; ?></span>
        </a>
    </div>

    <!-- FILTERS -->
    <div class="filter-bar-modern">
        <div class="filter-group">
            <label>Category</label>
            <select id="categoryFilter" onchange="filterApplications()">
                <option value="all" <?php echo $categoryFilter === 'all' || $categoryFilter === '' ? 'selected' : ''; ?>>All Categories</option>
                <?php foreach (array_keys($categoryCounts) as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Sort By</label>
            <select id="sortFilter" onchange="filterApplications()">
                <option value="newest" <?php echo $sortFilter === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sortFilter === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="name" <?php echo $sortFilter === 'name' ? 'selected' : ''; ?>>Business Name</option>
            </select>
        </div>
        <button class="reset-filters-btn" onclick="resetFilters()">
            <i class="fas fa-undo"></i> Reset
        </button>
    </div>

    <!-- APPLICATIONS TABLE -->
    <div class="applications-table-wrapper">
        <table class="applications-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Business Type</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($applications && $applications->num_rows > 0): ?>
                    <?php while ($app = $applications->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="app-name-cell">
                                    <div class="app-avatar-sm">
                                        <?php echo strtoupper(substr($app['business_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div class="app-name-text"><?php echo htmlspecialchars($app['business_name']); ?></div>
                                        <div class="app-email"><?php echo htmlspecialchars($app['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: #475569;"><?php echo htmlspecialchars($app['business_type']); ?></span>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: #6b8cae;">
                                    <?php echo date('M j, Y', strtotime($app['submitted_at'])); ?>
                                    <br>
                                    <small style="font-size: 0.6rem; color: #94a3b8;"><?php echo date('g:i A', strtotime($app['submitted_at'])); ?></small>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-btns" style="justify-content: flex-end;">
                                    <button onclick="viewApplication('<?php echo $app['id']; ?>')" class="action-btn-sm view">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button onclick="emailApplicant('<?php echo $app['email']; ?>')" class="action-btn-sm email">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                    <?php if ($app['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="approve_application">
                                            <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="current_page" value="applications">
                                            <input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">
                                            <button type="submit" class="action-btn-sm approve" onclick="return confirm('Approve this application?')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <button onclick="showRejectModal(<?php echo $app['id']; ?>, '<?php echo addslashes($app['business_name']); ?>')" class="action-btn-sm reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_application">
                                        <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="current_page" value="applications">
                                        <input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">
                                        <button type="submit" class="action-btn-sm delete" onclick="return confirm('Delete this application?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state-table">
                                <i class="fas fa-inbox"></i>
                                <p style="font-weight: 500; color: #475569;">No applications found</p>
                                <p style="font-size: 0.75rem; color: #94a3b8;">
                                    <?php if ($statusFilter && $statusFilter !== 'all'): ?>
                                        No <?php echo ucfirst($statusFilter); ?> applications found.
                                    <?php else: ?>
                                        Applications submitted by partners will appear here.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">
            Showing <?php echo $startRange; ?> to <?php echo $endRange; ?> of <?php echo $totalCount; ?> applications
        </span>
        <div class="pagination-links">
            <?php if ($currentPageNum > 1): ?>
                <a href="?page=applications&p=<?php echo $currentPageNum - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&sort=<?php echo urlencode($sortFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-link">
                    ← Prev
                </a>
            <?php else: ?>
                <span class="page-link disabled">← Prev</span>
            <?php endif; ?>
            
            <?php
            $maxPagesToShow = 5;
            $halfRange = floor($maxPagesToShow / 2);
            $startPage = max(1, $currentPageNum - $halfRange);
            $endPage = min($totalPages, $currentPageNum + $halfRange);
            
            if ($startPage > 1) {
                echo '<a href="?page=applications&p=1&status=' . urlencode($statusFilter) . '&category=' . urlencode($categoryFilter) . '&sort=' . urlencode($sortFilter) . '&search=' . urlencode($searchFilter) . '" class="page-link">1</a>';
                if ($startPage > 2) {
                    echo '<span class="page-link disabled">…</span>';
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=applications&p=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&sort=<?php echo urlencode($sortFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-link <?php echo $i === $currentPageNum ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    echo '<span class="page-link disabled">…</span>';
                }
                echo '<a href="?page=applications&p=' . $totalPages . '&status=' . urlencode($statusFilter) . '&category=' . urlencode($categoryFilter) . '&sort=' . urlencode($sortFilter) . '&search=' . urlencode($searchFilter) . '" class="page-link">' . $totalPages . '</a>';
            }
            ?>
            
            <?php if ($currentPageNum < $totalPages): ?>
                <a href="?page=applications&p=<?php echo $currentPageNum + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&sort=<?php echo urlencode($sortFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" class="page-link">
                    Next →
                </a>
            <?php else: ?>
                <span class="page-link disabled">Next →</span>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="pagination">
        <span class="pagination-info">
            Showing <?php echo $startRange; ?> to <?php echo $endRange; ?> of <?php echo $totalCount; ?> applications
        </span>
        <div class="pagination-links">
            <span class="page-link disabled">← Prev</span>
            <span class="page-link active">1</span>
            <span class="page-link disabled">Next →</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Applications Page Specific Styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .status-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .status-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1.2rem;
        border-radius: 40px;
        background: white;
        color: #475569;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }

    .status-tab:hover {
        background: #f8faff;
        border-color: #cbd5e1;
    }

    .status-tab.active {
        background: #0b1a33;
        color: white;
        border-color: #0b1a33;
    }

    .status-tab.active .tab-count {
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .tab-count {
        display: inline-block;
        padding: 0.05rem 0.5rem;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #f1f5f9;
        color: #475569;
    }

    .tab-count.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .tab-count.approved {
        background: #dcfce7;
        color: #15803d;
    }

    .tab-count.rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .applications-table-wrapper {
        background: white;
        border-radius: 16px;
        border: 1px solid #e8edf5;
        overflow: hidden;
    }

    .applications-table {
        width: 100%;
        border-collapse: collapse;
    }

    .applications-table thead {
        background: #f8faff;
    }

    .applications-table th {
        text-align: left;
        padding: 0.75rem 1rem;
        font-size: 0.7rem;
        font-weight: 600;
        color: #6b8cae;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e8edf5;
    }

    .applications-table td {
        padding: 0.75rem 1rem;
        font-size: 0.8rem;
        border-bottom: 1px solid #f0f4f9;
        vertical-align: middle;
    }

    .applications-table tr:last-child td {
        border-bottom: none;
    }

    .applications-table tr:hover td {
        background: #fafcff;
    }

    .app-name-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .app-avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #eff6ff;
        color: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.7rem;
        flex-shrink: 0;
    }

    .app-name-text {
        font-weight: 600;
        color: #0b1a33;
    }

    .app-email {
        font-size: 0.65rem;
        color: #8aacce;
    }

    .badge-status {
        font-size: 0.6rem;
        padding: 0.2rem 0.6rem;
        border-radius: 20px;
        font-weight: 600;
        display: inline-block;
    }

    .badge-status.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-status.approved {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-status.rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .action-btns {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }

    .action-btn-sm {
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        font-size: 0.6rem;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        font-family: inherit;
        text-decoration: none;
    }

    .action-btn-sm.view {
        background: #eff6ff;
        color: #3b82f6;
    }

    .action-btn-sm.view:hover {
        background: #dbeafe;
    }

    .action-btn-sm.email {
        background: #f5f3ff;
        color: #6d5dfc;
    }

    .action-btn-sm.email:hover {
        background: #ede9fe;
    }

    .action-btn-sm.approve {
        background: #dcfce7;
        color: #22c55e;
    }

    .action-btn-sm.approve:hover {
        background: #bbf7d0;
    }

    .action-btn-sm.reject {
        background: #fee2e2;
        color: #ef4444;
    }

    .action-btn-sm.reject:hover {
        background: #fecaca;
    }

    .action-btn-sm.delete {
        background: #f1f5f9;
        color: #94a3b8;
    }

    .action-btn-sm.delete:hover {
        background: #e2e8f0;
    }

    .empty-state-table {
        text-align: center;
        padding: 2.5rem 1.5rem;
        color: #8aacce;
    }

    .empty-state-table i {
        font-size: 2.5rem;
        color: #c8d4e8;
        display: block;
        margin-bottom: 0.5rem;
    }

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

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .status-tabs {
            gap: 0.3rem;
        }
        
        .status-tab {
            font-size: 0.7rem;
            padding: 0.3rem 0.8rem;
        }
        
        .applications-table-wrapper {
            overflow-x: auto;
        }
        
        .applications-table {
            min-width: 700px;
        }
        
        .pagination {
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }
    }
</style>
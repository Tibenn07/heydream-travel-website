<?php
// pages/bookings.php - Customer Bookings Page

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'Confirmed' => '<span style="background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px;">✓ Confirmed</span>',
        'Pending' => '<span style="background: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px;">⏳ Pending</span>',
        'Completed' => '<span style="background: #cce5ff; color: #0c5460; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px;">✓ Completed</span>',
        'Cancelled' => '<span style="background: #f8d7da; color: #721c24; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px;">✗ Cancelled</span>',
    ];
    return $badges[$status] ?? '<span style="background: #e2e3e5; color: #383d41; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px;">Unknown</span>';
}

function formatDate($dateString) {
    if (empty($dateString)) return 'N/A';
    $date = new DateTime($dateString);
    return $date->format('M d, Y • h:i A');
}

$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$sortFilter = $_GET['sort'] ?? 'newest';
$currentPageNum = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($currentPageNum < 1) $currentPageNum = 1;

$itemsPerPage = 10;

// Build the query
$whereClauses = [];
$params = [];
$types = "";

if ($statusFilter && $statusFilter !== 'all') {
    $whereClauses[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($searchFilter) {
    $whereClauses[] = "(booking_id LIKE ? OR lead_name LIKE ? OR lead_email LIKE ? OR package_title LIKE ?)";
    $params[] = "%" . $searchFilter . "%";
    $params[] = "%" . $searchFilter . "%";
    $params[] = "%" . $searchFilter . "%";
    $params[] = "%" . $searchFilter . "%";
    $types .= "ssss";
}

$whereSQL = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

// Get total count
$countSQL = "SELECT COUNT(*) as total FROM bookings" . $whereSQL;
$countStmt = $conn->prepare($countSQL);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalCount = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalCount / $itemsPerPage);
if ($currentPageNum > $totalPages && $totalPages > 0) $currentPageNum = $totalPages;

$offset = ($currentPageNum - 1) * $itemsPerPage;

// Get bookings
$sortSQL = match($sortFilter) {
    'oldest' => "ORDER BY submitted_at ASC",
    default => "ORDER BY submitted_at DESC",
};

$bookingsSQL = "SELECT * FROM bookings" . $whereSQL . " " . $sortSQL . " LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;
$types .= "ii";

$bookingsStmt = $conn->prepare($bookingsSQL);
if (!empty($params)) {
    $bookingsStmt->bind_param($types, ...$params);
}
$bookingsStmt->execute();
$bookings = $bookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bookingsStmt->close();
?>

<div class="page-container">
    <div class="page-header">
        <h2>Customer Bookings</h2>
        <p class="subtitle">View and manage all customer package bookings</p>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <input type="hidden" name="page" value="bookings">
            
            <div class="filter-group">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by booking ID, name, or email..."
                    value="<?php echo htmlspecialchars($searchFilter); ?>"
                    class="filter-input"
                >
            </div>

            <div class="filter-group">
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="Confirmed" <?php echo $statusFilter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div class="filter-group">
                <select name="sort" class="filter-select">
                    <option value="newest" <?php echo $sortFilter === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sortFilter === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                </select>
            </div>

            <button type="submit" class="btn-filter">Apply Filters</button>
            <a href="?page=bookings" class="btn-reset">Reset</a>
        </form>
    </div>

    <!-- Stats Cards -->
    <?php
    $totalBookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
    $confirmedCount = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Confirmed'")->fetch_assoc()['count'];
    $pendingCount = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'")->fetch_assoc()['count'];
    $totalRevenue = $conn->query("SELECT SUM(CAST(REPLACE(price, 'PHP ', '') as UNSIGNED)) as total FROM bookings WHERE price IS NOT NULL")->fetch_assoc()['total'] ?? 0;
    ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;">📦</div>
            <div class="stat-content">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?php echo number_format($totalBookings); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f3e5f5;">✓</div>
            <div class="stat-content">
                <div class="stat-label">Confirmed</div>
                <div class="stat-value"><?php echo number_format($confirmedCount); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3e0;">⏳</div>
            <div class="stat-content">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo number_format($pendingCount); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;">₱</div>
            <div class="stat-content">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value"><?php echo "₱" . number_format($totalRevenue); ?></div>
            </div>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="table-container">
        <?php if (!empty($bookings)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Package</th>
                        <th>Traveler Name</th>
                        <th>Email</th>
                        <th>Travel Dates</th>
                        <th>Travelers</th>
                        <th>Price</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($booking['booking_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($booking['package_title']); ?></td>
                            <td><?php echo htmlspecialchars($booking['lead_name']); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($booking['lead_email']); ?>"><?php echo htmlspecialchars($booking['lead_email']); ?></a></td>
                            <td><?php echo htmlspecialchars($booking['travel_dates']); ?></td>
                            <td><?php echo $booking['travelers']; ?></td>
                            <td><?php echo htmlspecialchars($booking['price']); ?></td>
                            <td><?php echo htmlspecialchars($booking['payment_method'] ?? 'N/A'); ?></td>
                            <td><?php echo getStatusBadge($booking['status']); ?></td>
                            <td><?php echo formatDate($booking['submitted_at']); ?></td>
                            <td>
                                <button class="btn-small btn-view" onclick="openBookingModal(<?php echo htmlspecialchars(json_encode($booking)); ?>)">View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($currentPageNum > 1): ?>
                        <a href="?page=bookings&p=1<?php echo $statusFilter ? "&status=" . urlencode($statusFilter) : ""; ?><?php echo $searchFilter ? "&search=" . urlencode($searchFilter) : ""; ?>" class="pag-btn">« First</a>
                        <a href="?page=bookings&p=<?php echo $currentPageNum - 1; ?><?php echo $statusFilter ? "&status=" . urlencode($statusFilter) : ""; ?><?php echo $searchFilter ? "&search=" . urlencode($searchFilter) : ""; ?>" class="pag-btn">‹ Prev</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $currentPageNum - 2);
                    $end = min($totalPages, $currentPageNum + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?page=bookings&p=<?php echo $i; ?><?php echo $statusFilter ? "&status=" . urlencode($statusFilter) : ""; ?><?php echo $searchFilter ? "&search=" . urlencode($searchFilter) : ""; ?>" class="pag-btn <?php echo $i === $currentPageNum ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($currentPageNum < $totalPages): ?>
                        <a href="?page=bookings&p=<?php echo $currentPageNum + 1; ?><?php echo $statusFilter ? "&status=" . urlencode($statusFilter) : ""; ?><?php echo $searchFilter ? "&search=" . urlencode($searchFilter) : ""; ?>" class="pag-btn">Next ›</a>
                        <a href="?page=bookings&p=<?php echo $totalPages; ?><?php echo $statusFilter ? "&status=" . urlencode($statusFilter) : ""; ?><?php echo $searchFilter ? "&search=" . urlencode($searchFilter) : ""; ?>" class="pag-btn">Last »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No bookings found</h3>
                <p>There are no customer bookings yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.filters-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.filters-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-input,
.filter-select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
}

.filter-input:focus,
.filter-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-filter,
.btn-reset {
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-filter {
    background: #3b82f6;
    color: white;
}

.btn-filter:hover {
    background: #2563eb;
}

.btn-reset {
    background: #f3f4f6;
    color: #374151;
}

.btn-reset:hover {
    background: #e5e7eb;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.stat-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 4px;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.data-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
    color: #334155;
}

.data-table tr:hover {
    background: #f8fafc;
}

.data-table strong {
    color: #0f172a;
    font-weight: 700;
}

.data-table a {
    color: #3b82f6;
    text-decoration: none;
}

.data-table a:hover {
    text-decoration: underline;
}

.btn-small {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-view {
    background: #dbeafe;
    color: #0d47a1;
}

.btn-view:hover {
    background: #bfdbfe;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 20px;
}

.pag-btn {
    padding: 8px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    text-decoration: none;
    color: #334155;
    font-size: 14px;
    transition: all 0.2s;
}

.pag-btn:hover {
    background: #f3f4f6;
}

.pag-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 18px;
    color: #0f172a;
    margin-bottom: 8px;
}

.empty-state p {
    color: #64748b;
    font-size: 14px;
}

/* Modern Booking Modal Styles */
.booking-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(8px);
    z-index: 1000;
    animation: fadeIn 0.3s ease-out;
}

.booking-modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.booking-modal {
    background: white;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    max-width: 900px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.booking-modal-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px 16px 0 0;
}

.booking-modal-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.booking-modal-title-section {
    flex: 1;
}

.booking-modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.booking-modal-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 4px 0 0 0;
}

.booking-modal-close {
    width: 40px;
    height: 40px;
    background: none;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.booking-modal-close:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}

.booking-modal-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    padding: 24px;
}

@media (max-width: 768px) {
    .booking-modal-content {
        grid-template-columns: 1fr;
    }
}

.booking-modal-section {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.booking-modal-section-title {
    font-size: 14px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.booking-modal-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    transition: all 0.2s;
}

.booking-modal-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.booking-info-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.booking-info-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.booking-info-value {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    word-break: break-word;
}

.booking-info-value.highlight {
    color: #3b82f6;
}

.booking-info-value.email {
    color: #3b82f6;
    text-decoration: none;
}

.booking-info-value.email:hover {
    text-decoration: underline;
}

.booking-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    width: fit-content;
}

.booking-status-confirmed {
    background: #d1fae5;
    color: #065f46;
}

.booking-status-pending {
    background: #fef3c7;
    color: #92400e;
}

.booking-status-completed {
    background: #dbeafe;
    color: #0c4a6e;
}

.booking-status-cancelled {
    background: #fee2e2;
    color: #7f1d1d;
}

.booking-price-display {
    background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}

.booking-price-label {
    font-size: 12px;
    color: #0c4a6e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 8px;
}

.booking-price-value {
    font-size: 28px;
    font-weight: 800;
    color: #1e40af;
}

.booking-modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-radius: 0 0 16px 16px;
}

@media (max-width: 640px) {
    .booking-modal-footer {
        flex-direction: column;
    }
}

.booking-modal-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.booking-modal-btn-secondary {
    background: #e2e8f0;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.booking-modal-btn-secondary:hover {
    background: #cbd5e1;
}

.booking-modal-btn-primary {
    background: #3b82f6;
    color: white;
}

.booking-modal-btn-primary:hover {
    background: #2563eb;
}

.booking-modal-btn-print {
    background: #f3f4f6;
    color: #1f2937;
    border: 1px solid #d1d5db;
}

.booking-modal-btn-print:hover {
    background: #e5e7eb;
}

@media print {
    body * {
        display: none;
    }
    .booking-modal {
        display: block;
        max-width: 100%;
        width: 100%;
        height: auto;
        box-shadow: none;
        border: none;
    }
    .booking-modal-footer {
        display: none;
    }
}
</style>

<!-- Booking Details Modal -->
<div class="booking-modal-overlay" id="bookingModalOverlay">
    <div class="booking-modal" id="bookingModal">
        <div class="booking-modal-header">
            <div class="booking-modal-icon">📋</div>
            <div class="booking-modal-title-section">
                <h2 class="booking-modal-title">Booking Details</h2>
                <p class="booking-modal-subtitle">Detailed information about this booking.</p>
            </div>
            <button class="booking-modal-close" onclick="closeBookingModal()" title="Close">✕</button>
        </div>

        <div class="booking-modal-content">
            <!-- Left Column: Booking Information -->
            <div class="booking-modal-section">
                <h3 class="booking-modal-section-title">📌 Booking Information</h3>
                
                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Booking ID</span>
                        <span class="booking-info-value highlight" id="modalBookingId">—</span>
                    </div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Package</span>
                        <span class="booking-info-value" id="modalPackageTitle">—</span>
                    </div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Traveler Name</span>
                        <span class="booking-info-value" id="modalLeadName">—</span>
                    </div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Email</span>
                        <a class="booking-info-value email" id="modalLeadEmail" href="mailto:">—</a>
                    </div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Phone Number</span>
                        <span class="booking-info-value" id="modalLeadPhone">—</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Booking Summary -->
            <div class="booking-modal-section">
                <h3 class="booking-modal-section-title">✓ Booking Summary</h3>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Travel Dates</span>
                        <span class="booking-info-value" id="modalTravelDates">—</span>
                    </div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Number of Travelers</span>
                        <span class="booking-info-value" id="modalTravelers">—</span>
                    </div>
                </div>

                <div class="booking-price-display">
                    <div class="booking-price-label">Total Price</div>
                    <div class="booking-price-value" id="modalPrice">₱—</div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Payment Method</span>
                        <span class="booking-info-value" id="modalPaymentMethod">—</span>
                    </div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Status</span>
                        <div id="modalStatusBadge">—</div>
                    </div>
                </div>

                <div class="booking-modal-card">
                    <div class="booking-info-item">
                        <span class="booking-info-label">Special Requests</span>
                        <span class="booking-info-value" id="modalSpecialRequests">None</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="booking-modal-footer">
            <button class="booking-modal-btn booking-modal-btn-print" onclick="printBooking()">
                🖨️ Print Booking
            </button>
            <button class="booking-modal-btn booking-modal-btn-primary" onclick="closeBookingModal()">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function openBookingModal(booking) {
    // Populate modal fields
    document.getElementById('modalBookingId').textContent = booking.booking_id || '—';
    document.getElementById('modalPackageTitle').textContent = booking.package_title || '—';
    document.getElementById('modalLeadName').textContent = booking.lead_name || '—';
    
    const emailEl = document.getElementById('modalLeadEmail');
    const email = booking.lead_email || '—';
    emailEl.textContent = email;
    emailEl.href = email !== '—' ? 'mailto:' + email : '#';
    
    document.getElementById('modalLeadPhone').textContent = booking.lead_phone || '—';
    document.getElementById('modalTravelDates').textContent = booking.travel_dates || '—';
    document.getElementById('modalTravelers').textContent = (booking.travelers || '—') + ' traveler' + (booking.travelers > 1 ? 's' : '');
    document.getElementById('modalPrice').textContent = booking.price || '₱—';
    document.getElementById('modalPaymentMethod').textContent = booking.payment_method || 'N/A';
    document.getElementById('modalSpecialRequests').textContent = booking.special_requests || 'None';
    
    // Set status badge
    const statusBadgeEl = document.getElementById('modalStatusBadge');
    const status = booking.status || 'Unknown';
    const statusClass = 'booking-status-' + status.toLowerCase();
    const statusIcon = status === 'Confirmed' ? '✓' : (status === 'Pending' ? '⏳' : (status === 'Completed' ? '✓' : '✗'));
    statusBadgeEl.innerHTML = `<span class="booking-status-badge ${statusClass}">${statusIcon} ${status}</span>`;
    
    // Show modal with animation
    const overlay = document.getElementById('bookingModalOverlay');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeBookingModal() {
    const overlay = document.getElementById('bookingModalOverlay');
    overlay.classList.remove('active');
    document.body.style.overflow = 'auto';
}

function printBooking() {
    window.print();
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('bookingModalOverlay');
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeBookingModal();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeBookingModal();
        }
    });
});
</script>

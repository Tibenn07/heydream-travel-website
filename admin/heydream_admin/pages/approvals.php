<?php
// pages/approvals.php - Package Approvals Page (FIXED - No Header Errors)

// Start output buffering to prevent header errors
ob_start();

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getDBConnection();

$statusFilter = $_GET['status'] ?? 'pending';
$searchFilter = $_GET['search'] ?? '';
$currentPageNum = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($currentPageNum < 1) $currentPageNum = 1;
$itemsPerPage = 10;

// ============================================================
// HANDLE POST ACTIONS - FIXED with output buffering
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $currentPageNum = isset($_POST['page_num']) ? (int)$_POST['page_num'] : 1;
    
    if ($action === 'approve_package') {
        $conn->begin_transaction();
        
        try {
            // 1. Get the pending approval data
            $getStmt = $conn->prepare("SELECT * FROM approvals WHERE id = ? AND status = 'pending'");
            $getStmt->bind_param("i", $id);
            $getStmt->execute();
            $result = $getStmt->get_result();
            $approval = $result->fetch_assoc();
            $getStmt->close();
            
            if (!$approval) {
                throw new Exception('Approval not found or already processed');
            }
            
            // 2. Decode package data
            $packageData = json_decode($approval['metadata'], true);
            if (!$packageData) {
                $packageData = json_decode($approval['content'], true);
            }
            
            if (!$packageData) {
                throw new Exception('Could not decode package data');
            }
            
            // 3. Check if flight_packages table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'flight_packages'");
            if ($tableCheck->num_rows == 0) {
                // Create the table
                $conn->query("
                    CREATE TABLE flight_packages (
                        id VARCHAR(50) PRIMARY KEY,
                        partner_id VARCHAR(50) NOT NULL,
                        partner_name VARCHAR(255) NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        destination VARCHAR(255),
                        address TEXT,
                        duration VARCHAR(50),
                        price VARCHAR(50),
                        description TEXT,
                        image VARCHAR(500),
                        gallery JSON,
                        inclusions JSON,
                        exclusions JSON,
                        itinerary JSON,
                        pricing_tiers JSON,
                        latitude DECIMAL(10,8),
                        longitude DECIMAL(11,8),
                        package_type VARCHAR(50),
                        category VARCHAR(50),
                        approved BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_partner (partner_id),
                        INDEX idx_approved (approved)
                    )
                ");
            }
            
            // 4. Generate unique ID
            $packageId = 'PKG-' . date('Ymd') . '-' . rand(1000, 9999);
            
            // 5. Prepare data with proper field mapping
            $partner_id = $packageData['partnerId'] ?? $approval['partner_id'] ?? 'partner_1';
            $partner_name = $packageData['partnerName'] ?? $approval['partner_name'] ?? 'Unknown';
            $title = $packageData['title'] ?? $approval['title'] ?? 'Untitled';
            $destination = $packageData['destination'] ?? '';
            $address = $packageData['address'] ?? '';
            $duration = $packageData['duration'] ?? '';
            $price = $packageData['price'] ?? '';
            $description = $packageData['flightDetails'] ?? $packageData['description'] ?? '';
            $image = $packageData['image'] ?? '';
            $gallery = json_encode($packageData['gallery'] ?? []);
            $inclusions = json_encode($packageData['inclusions'] ?? []);
            $exclusions = json_encode($packageData['exclusions'] ?? []);
            $itinerary = json_encode($packageData['itinerary'] ?? []);
            $pricing_tiers = json_encode($packageData['pricingTiers'] ?? []);
            $latitude = $packageData['latitude'] ?? null;
            $longitude = $packageData['longitude'] ?? null;
            $package_type = $packageData['packageType'] ?? 'Package';
            $category = $packageData['category'] ?? $package_type;
            
            // 6. Insert into flight_packages
            $insertStmt = $conn->prepare("
                INSERT INTO flight_packages (
                    id, partner_id, partner_name, title, destination, address, 
                    duration, price, description, image, gallery, inclusions, 
                    exclusions, itinerary, pricing_tiers, latitude, longitude, 
                    package_type, category, approved
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $insertStmt->bind_param(
                "sssssssssssssssssss",
                $packageId,
                $partner_id,
                $partner_name,
                $title,
                $destination,
                $address,
                $duration,
                $price,
                $description,
                $image,
                $gallery,
                $inclusions,
                $exclusions,
                $itinerary,
                $pricing_tiers,
                $latitude,
                $longitude,
                $package_type,
                $category
            );
            
            if (!$insertStmt->execute()) {
                throw new Exception('Failed to insert package: ' . $insertStmt->error);
            }
            $insertStmt->close();
            
            // 7. Update approval status
            $updateStmt = $conn->prepare("
                UPDATE approvals 
                SET status = 'approved', reviewed_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->bind_param("i", $id);
            $updateStmt->execute();
            $updateStmt->close();
            
            $conn->commit();
            $_SESSION['success_message'] = '✅ Package approved and published successfully!';
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        
        // Clear any output buffers before redirect
        ob_end_clean();
        header("Location: admin_dashboard.php?page=approval&p=" . $currentPageNum);
        exit;
        
    } elseif ($action === 'reject_package') {
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $conn->prepare("UPDATE approvals SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $reason, $id);
        $stmt->execute();
        $stmt->close();
        
        ob_end_clean();
        header("Location: admin_dashboard.php?page=approval&p=" . $currentPageNum);
        exit;
        
    } elseif ($action === 'delete_package') {
        $stmt = $conn->prepare("DELETE FROM approvals WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        ob_end_clean();
        header("Location: admin_dashboard.php?page=approval&p=" . $currentPageNum);
        exit;
    }
}

// ============================================================
// BUILD QUERY
// ============================================================
$where = ["type = 'package'"];

if ($statusFilter !== 'all') {
    $where[] = "status = ?";
    $params = [$statusFilter];
    $types = "s";
} else {
    $params = [];
    $types = "";
}

if ($searchFilter) {
    $where[] = "(title LIKE ? OR partner_name LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $types .= "ss";
}

$whereSQL = "WHERE " . implode(" AND ", $where);

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM approvals $whereSQL");
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalCount = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = max(1, ceil($totalCount / $itemsPerPage));
if ($currentPageNum > $totalPages && $totalPages > 0) $currentPageNum = $totalPages;
$offset = ($currentPageNum - 1) * $itemsPerPage;

$dataSql = "SELECT * FROM approvals $whereSQL ORDER BY submitted_at DESC LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;
$types .= "ii";

$dataStmt = $conn->prepare($dataSql);
if (!empty($params)) {
    $dataStmt->bind_param($types, ...$params);
}
$dataStmt->execute();
$approvals = $dataStmt->get_result();
$dataStmt->close();

$startRange = $totalCount > 0 ? $offset + 1 : 0;
$endRange = min($offset + $itemsPerPage, $totalCount);

$counts = [];
$counts['pending'] = $conn->query("SELECT COUNT(*) as count FROM approvals WHERE type = 'package' AND status = 'pending'")->fetch_assoc()['count'] ?? 0;
$counts['approved'] = $conn->query("SELECT COUNT(*) as count FROM approvals WHERE type = 'package' AND status = 'approved'")->fetch_assoc()['count'] ?? 0;
$counts['rejected'] = $conn->query("SELECT COUNT(*) as count FROM approvals WHERE type = 'package' AND status = 'rejected'")->fetch_assoc()['count'] ?? 0;
$counts['all'] = $counts['pending'] + $counts['approved'] + $counts['rejected'];

// Clear output buffer before displaying content
ob_end_clean();
?>
<div class="content-panel">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #22c55e;">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #ef4444;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #0b1a33;">
                <i class="fas fa-box" style="color: #3b82f6;"></i> Package Approvals
            </h2>
            <p style="color: #6b8cae; font-size: 0.9rem; margin-top: 0.25rem;">
                Review and approve packages submitted by partners.
            </p>
        </div>
        <div>
            <span style="font-size: 0.85rem; color: #6b8cae; background: #f0f4f9; padding: 0.4rem 1rem; border-radius: 40px;">
                <i class="fas fa-clock"></i> <?php echo $counts['pending']; ?> pending
            </span>
        </div>
    </div>

    <div class="status-tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <a href="?page=approval&status=pending" class="status-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
            Pending <span class="tab-count pending"><?php echo $counts['pending']; ?></span>
        </a>
        <a href="?page=approval&status=approved" class="status-tab <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">
            Approved <span class="tab-count approved"><?php echo $counts['approved']; ?></span>
        </a>
        <a href="?page=approval&status=rejected" class="status-tab <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">
            Rejected <span class="tab-count rejected"><?php echo $counts['rejected']; ?></span>
        </a>
        <a href="?page=approval&status=all" class="status-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
            All <span class="tab-count"><?php echo $counts['all']; ?></span>
        </a>
    </div>

    <div class="search-bar" style="margin-bottom: 1.5rem;">
        <form method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <input type="hidden" name="page" value="approval">
            <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
            <div style="flex: 1; min-width: 200px; display: flex; align-items: center; background: white; border-radius: 40px; padding: 0 1rem; border: 1px solid #e8edf5;">
                <i class="fas fa-search" style="color: #8aacce;"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>" 
                       placeholder="Search by title or partner..." 
                       style="border: none; padding: 0.6rem 0.75rem; width: 100%; outline: none; font-size: 0.85rem;">
            </div>
            <button type="submit" class="btn-primary" style="padding: 0.5rem 1.5rem; border-radius: 40px; border: none; background: #0b1a33; color: white; cursor: pointer;">
                Search
            </button>
            <?php if ($searchFilter): ?>
                <a href="?page=approval&status=<?php echo $statusFilter; ?>" class="btn-secondary" style="padding: 0.5rem 1.2rem; border-radius: 40px; background: #f1f5f9; color: #475569; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div style="background: white; border-radius: 16px; border: 1px solid #e8edf5; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f8faff;">
                <tr>
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: #6b8cae; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e8edf5;">Package</th>
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: #6b8cae; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e8edf5;">Partner</th>
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: #6b8cae; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e8edf5;">Submitted</th>
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: #6b8cae; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e8edf5;">Status</th>
                    <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: #6b8cae; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e8edf5;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($approvals && $approvals->num_rows > 0): ?>
                    <?php while ($item = $approvals->fetch_assoc()): ?>
                        <?php $isPending = $item['status'] === 'pending'; ?>
                        <tr>
                            <td style="padding: 0.75rem 1rem; border-bottom: 1px solid #f0f4f9; vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #dbeafe; display: flex; align-items: center; justify-content: center; font-size: 1rem;">📦</div>
                                    <div>
                                        <div style="font-weight: 600; color: #0b1a33; font-size: 0.85rem;"><?php echo htmlspecialchars($item['title']); ?></div>
                                        <?php 
                                            $meta = [];
                                            if (!empty($item['metadata'])) {
                                                $meta = json_decode($item['metadata'], true);
                                                if (!is_array($meta)) $meta = [];
                                            }
                                            $packageType = htmlspecialchars($meta['packageType'] ?? $meta['category'] ?? '');
                                            $destination = htmlspecialchars($meta['destination'] ?? '');
                                            $price = htmlspecialchars($meta['price'] ?? '');
                                            $duration = htmlspecialchars($meta['duration'] ?? '');
                                            $address = htmlspecialchars($meta['address'] ?? '');
                                            $descriptionLine = htmlspecialchars($meta['flightDetails'] ?? $meta['description'] ?? '');
                                            $summaryParts = array_filter([$packageType, $destination, $price]);
                                        ?>
                                        <div style="font-size: 0.65rem; color: #8aacce;">
                                            <?php echo implode(' • ', $summaryParts); ?>
                                        </div>
                                        <?php if ($duration || $address): ?>
                                            <div style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.2rem;">
                                                <?php echo htmlspecialchars(trim(($duration ? "Duration: $duration" : '') . ($duration && $address ? ' • ' : '') . ($address ? "Location: $address" : ''))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($descriptionLine): ?>
                                            <div style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.2rem;">
                                                <?php echo htmlspecialchars(mb_strimwidth($descriptionLine, 0, 120, '...')); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 0.75rem 1rem; border-bottom: 1px solid #f0f4f9; vertical-align: middle;">
                                <div style="font-weight: 500; color: #0b1a33; font-size: 0.8rem;"><?php echo htmlspecialchars($item['partner_name']); ?></div>
                            </td>
                            <td style="padding: 0.75rem 1rem; border-bottom: 1px solid #f0f4f9; vertical-align: middle;">
                                <div style="font-size: 0.75rem; color: #475569;"><?php echo date('M j, Y', strtotime($item['submitted_at'])); ?></div>
                                <div style="font-size: 0.6rem; color: #8aacce;"><?php echo date('g:i A', strtotime($item['submitted_at'])); ?></div>
                            </td>
                            <td style="padding: 0.75rem 1rem; border-bottom: 1px solid #f0f4f9; vertical-align: middle;">
                                <span style="font-size: 0.65rem; padding: 0.2rem 0.7rem; border-radius: 20px; 
                                    <?php 
                                        if ($item['status'] === 'pending') echo 'background: #fef3c7; color: #92400e;';
                                        elseif ($item['status'] === 'approved') echo 'background: #dcfce7; color: #15803d;';
                                        else echo 'background: #fee2e2; color: #b91c1c;';
                                    ?>
                                ">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem; border-bottom: 1px solid #f0f4f9; vertical-align: middle; text-align: right;">
                                <div style="display: flex; gap: 0.3rem; justify-content: flex-end; flex-wrap: wrap;">
                                    <button onclick="viewPackage('<?php echo $item['id']; ?>')" style="padding: 0.2rem 0.6rem; border-radius: 6px; border: none; cursor: pointer; font-size: 0.65rem; background: #eff6ff; color: #3b82f6;">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    
                                    <?php if ($isPending): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this package? It will be published immediately.');">
                                            <input type="hidden" name="action" value="approve_package">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">
                                            <button type="submit" style="padding: 0.2rem 0.6rem; border-radius: 6px; border: none; cursor: pointer; font-size: 0.65rem; background: #dcfce7; color: #22c55e;">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <button onclick="showRejectModal('<?php echo $item['id']; ?>', '<?php echo addslashes($item['title']); ?>')" style="padding: 0.2rem 0.6rem; border-radius: 6px; border: none; cursor: pointer; font-size: 0.65rem; background: #fee2e2; color: #ef4444;">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this entry?');">
                                        <input type="hidden" name="action" value="delete_package">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">
                                        <button type="submit" style="padding: 0.2rem 0.6rem; border-radius: 6px; border: none; cursor: pointer; font-size: 0.65rem; background: #f1f5f9; color: #94a3b8;">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 3rem 1.5rem; text-align: center;">
                            <div style="color: #8aacce;">
                                <i class="fas fa-box-open" style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem; color: #c8d4e8;"></i>
                                <p style="font-weight: 500; color: #475569;">No packages found</p>
                                <p style="font-size: 0.75rem; color: #94a3b8;">
                                    <?php if ($statusFilter === 'pending'): ?>
                                        All packages have been reviewed. Great job!
                                    <?php else: ?>
                                        Packages submitted by partners will appear here.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.25rem; padding: 0.75rem 0; flex-wrap: wrap; gap: 0.5rem;">
        <span style="font-size: 0.75rem; color: #8aacce;">Showing <?php echo $startRange; ?> to <?php echo $endRange; ?> of <?php echo $totalCount; ?></span>
        <div style="display: flex; gap: 0.3rem;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=approval&p=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchFilter); ?>" style="padding: 0.3rem 0.7rem; border-radius: 6px; text-decoration: none; color: <?php echo $i === $currentPageNum ? 'white' : '#475569'; ?>; border: 1px solid <?php echo $i === $currentPageNum ? '#3b82f6' : '#e2e8f0'; ?>; font-size: 0.75rem; background: <?php echo $i === $currentPageNum ? '#3b82f6' : 'transparent'; ?>;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- View Modal -->
<div id="viewModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
    <div style="background: white; margin: 2% auto; padding: 1.5rem; border-radius: 1rem; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1rem;">
            <h3 style="font-size: 1.2rem; font-weight: 600; color: #0f172a;"><i class="fas fa-box" style="color: #3b82f6;"></i> Package Details</h3>
            <span onclick="closeViewModal()" style="font-size: 28px; font-weight: bold; cursor: pointer; color: #94a3b8;">&times;</span>
        </div>
        <div id="viewModalBody"></div>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; margin-top: 1rem;">
            <button onclick="approveFromModal()" style="background: #22c55e; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500;"><i class="fas fa-check"></i> Approve</button>
            <button onclick="rejectFromModal()" style="background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500;"><i class="fas fa-times"></i> Reject</button>
            <button onclick="closeViewModal()" style="background: transparent; color: #475569; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
    <div style="background: white; margin: 2% auto; padding: 1.5rem; border-radius: 1rem; width: 90%; max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1rem;">
            <h3 style="font-size: 1.2rem; font-weight: 600; color: #0f172a;"><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Package</h3>
            <span onclick="closeRejectModal()" style="font-size: 28px; font-weight: bold; cursor: pointer; color: #94a3b8;">&times;</span>
        </div>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action" value="reject_package">
            <input type="hidden" name="id" id="rejectId">
            <input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">
            <div>
                <p style="margin-bottom: 1rem;">Reason for rejecting <strong id="rejectTitle"></strong>:</p>
                <textarea name="rejection_reason" rows="4" placeholder="Enter rejection reason..." style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-family: inherit; resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: 0.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; margin-top: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeRejectModal()" style="background: transparent; color: #475569; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500;">Cancel</button>
                <button type="submit" style="background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500;">Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewPackage(id) {
    fetch('api/get_approval_full.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const item = data.data;
                const meta = item.metadata || {};

                function escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function sanitizeUrl(value) {
                    if (!value || typeof value !== 'string') {
                        return null;
                    }
                    const url = value.trim();
                    if (/^(https?:\/\/|data:image\/|data:video\/)/i.test(url)) {
                        return escapeHtml(url);
                    }
                    return null;
                }

                function isEmptyValue(value) {
                    if (value === null || value === undefined || value === '') return true;
                    if (Array.isArray(value)) return value.length === 0;
                    if (typeof value === 'object') {
                        const keys = Object.keys(value);
                        if (keys.length === 0) return true;
                        return keys.every(key => isEmptyValue(value[key]));
                    }
                    return false;
                }

                function renderValue(value) {
                    if (isEmptyValue(value)) {
                        return 'N/A';
                    }
                    if (Array.isArray(value)) {
                        return `<div style="display: grid; gap: 0.35rem;">${value.map(item => {
                            if (typeof item === 'object') {
                                return `<div style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;"><pre style=\"margin:0; color:#475569; white-space:pre-wrap; word-break:break-word;\">${escapeHtml(JSON.stringify(item, null, 2))}</pre></div>`;
                            }
                            return `<div>• ${escapeHtml(String(item))}</div>`;
                        }).join('')}</div>`;
                    }
                    if (typeof value === 'object') {
                        return `<div style="display: grid; gap: 0.35rem;">${Object.entries(value).map(([key, val]) => `
                            <div style="display:flex; gap:.5rem; align-items:flex-start;">
                                <strong style=\"color:#0b1a33; width:130px;\">${escapeHtml(key)}:</strong>
                                <span style=\"color:#475569;\">${renderValue(val)}</span>
                            </div>
                        `).join('')}</div>`;
                    }
                    return escapeHtml(String(value));
                }

                function renderField(label, value) {
                    if (isEmptyValue(value)) return '';
                    return `
                        <div style="display:flex; gap:0.75rem; padding:0.45rem 0; border-bottom:1px solid #f1f5f9;">
                            <div style="width:160px; font-weight:600; color:#0b1a33;">${escapeHtml(label)}</div>
                            <div style="color:#475569; flex:1;">${renderValue(value)}</div>
                        </div>
                    `;
                }

                function renderSection(title, content) {
                    if (!content) return '';
                    return `
                        <div style="background:#ffffff; padding:1rem; border-radius:12px; border:1px solid #eef3fc;">
                            <h4 style="font-size:0.95rem; font-weight:600; color:#0b1a33; margin-bottom:0.75rem;">${escapeHtml(title)}</h4>
                            ${content}
                        </div>
                    `;
                }

                function renderObjectSection(title, obj) {
                    if (isEmptyValue(obj)) return '';
                    const rows = Object.entries(obj).map(([key, value]) => renderField(key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase()), value)).join('');
                    return renderSection(title, rows);
                }

                function renderMedia() {
                    const mediaSources = [];
                    const imgSource = sanitizeUrl(meta.image);
                    if (imgSource) mediaSources.push({ type: 'image', src: imgSource });

                    const galleryItems = Array.isArray(meta.gallery) ? meta.gallery : [];
                    galleryItems.forEach(entry => {
                        const url = sanitizeUrl(entry);
                        if (url) {
                            const type = /\.(mp4|webm|ogg)(\?|$)/i.test(url) ? 'video' : 'image';
                            mediaSources.push({ type, src: url });
                        }
                    });

                    if (mediaSources.length === 0) return '';
                    return renderSection('Media Preview', `<div style="display:grid; gap:1rem;">${mediaSources.map(source => {
                        if (source.type === 'video') {
                            return `<video controls style="width:100%; max-height:360px; background:#000; border-radius:12px; border:1px solid #e2e8f0;"><source src="${source.src}" type="video/mp4">Your browser does not support the video tag.</video>`;
                        }
                        return `<img src="${source.src}" alt="Package media" style="width:100%; max-height:360px; object-fit:contain; border-radius:12px; border:1px solid #e2e8f0;" loading="lazy">`;
                    }).join('')}</div>`);
                }

                function renderItinerary(itinerary) {
                    if (!Array.isArray(itinerary) || itinerary.length === 0) return '';
                    return renderSection('Itinerary', itinerary.map((entry, idx) => {
                        if (typeof entry === 'object') {
                            const content = Object.entries(entry).map(([key, value]) => renderField(key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase()), value)).join('');
                            return `<div style="padding:0.75rem; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; margin-bottom:0.75rem;"><strong style="display:block; margin-bottom:0.5rem; color:#0b1a33;">Step ${idx + 1}</strong>${content}</div>`;
                        }
                        return `<div style="padding:0.5rem 0;">• ${escapeHtml(String(entry))}</div>`;
                    }).join(''));
                }

                function renderPricingTiers(pricingTiers) {
                    if (!Array.isArray(pricingTiers) || pricingTiers.length === 0) return '';
                    return renderSection('Pricing Tiers', pricingTiers.map((tier, idx) => {
                        if (typeof tier === 'object') {
                            const rows = Object.entries(tier).map(([key, value]) => renderField(key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase()), value)).join('');
                            return `<div style="padding:0.75rem; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; margin-bottom:0.75rem;"><strong style="display:block; margin-bottom:0.5rem; color:#0b1a33;">Tier ${idx + 1}</strong>${rows}</div>`;
                        }
                        return `<div style="padding:0.5rem 0;">• ${escapeHtml(String(tier))}</div>`;
                    }).join(''));
                }

                const generalHtml = renderSection('Details', [
                    renderField('Title', item.title || meta.title),
                    renderField('Partner', item.partner_name || meta.partnerName),
                    renderField('Package Type', meta.packageType || meta.category),
                    renderField('Category', meta.category),
                    renderField('Destination', meta.destination),
                    renderField('Address', meta.address),
                    renderField('Duration', meta.duration),
                    renderField('Price', meta.price),
                    renderField('Available Slots', meta.availableSlots),
                    renderField('Featured', meta.featured === true ? 'Yes' : (meta.featured === false ? 'No' : '')),
                    renderField('Listing Type', meta.destinationType),
                    renderField('Sub Type', meta.subType),
                    renderField('Pending Approval', meta.pendingApproval === true ? 'Yes' : (meta.pendingApproval === false ? 'No' : '')),
                    renderField('Show Itinerary', meta.showItinerary === true ? 'Yes' : (meta.showItinerary === false ? 'No' : '')),
                    renderField('Show Inclusions', meta.showInclusions === true ? 'Yes' : (meta.showInclusions === false ? 'No' : '')),
                    renderField('Show Exclusions', meta.showExclusions === true ? 'Yes' : (meta.showExclusions === false ? 'No' : '')),
                    renderField('Show Schedule', meta.showSchedule === true ? 'Yes' : (meta.showSchedule === false ? 'No' : '')),
                    renderField('Schedule Text', meta.scheduleText),
                    renderField('Latitude', meta.latitude),
                    renderField('Longitude', meta.longitude),
                    renderField('Status', item.status),
                    renderField('Submitted At', item.submitted_at ? new Date(item.submitted_at).toLocaleString() : ''),
                    renderField('Review Date', item.reviewed_at || ''),
                ].join(''));

                const descriptionHtml = renderSection('Description', `<div style="font-size:0.9rem; color:#475569; white-space:pre-wrap; word-break:break-word;">${escapeHtml(meta.flightDetails || meta.description || 'No description available.')}</div>`);

                const extraHtml = [
                    renderField('Inclusions', meta.inclusions),
                    renderField('Exclusions', meta.exclusions),
                    renderField('Amenities', meta.amenities),
                    renderObjectSection('Vehicle Info', meta.vehicleInfo),
                    renderObjectSection('Rental Info', meta.rentalInfo),
                    renderObjectSection('Ticketing Info', meta.ticketingInfo),
                    renderObjectSection('Transportation Info', meta.transportationInfo),
                    renderObjectSection('Cruise Info', meta.cruiseInfo),
                    renderObjectSection('Stay Info', meta.stayInfo),
                    renderObjectSection('Experience Info', meta.experienceInfo),
                    renderObjectSection('Service / Visa Info', meta.serviceInfo),
                    renderObjectSection('Route Info', meta.routeInfo),
                    renderPricingTiers(meta.pricingTiers),
                    renderItinerary(meta.itinerary),
                ].join('');

                document.getElementById('viewModalBody').innerHTML = `
                    <div style="display:grid; gap:1rem;">
                        ${renderMedia()}
                        ${generalHtml}
                        ${descriptionHtml}
                        ${extraHtml}
                    </div>
                `;
                document.getElementById('viewModal').style.display = 'block';
                document.getElementById('viewModal').dataset.approvalId = id;
            } else {
                alert('Error loading package details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading package details');
        });
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function showRejectModal(id, title) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectTitle').innerHTML = title;
    document.getElementById('rejectModal').style.display = 'block';
}

function approveFromModal() {
    const id = document.getElementById('viewModal').dataset.approvalId;
    if (id && confirm('Approve this package?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="approve_package"><input type="hidden" name="id" value="${id}"><input type="hidden" name="page_num" value="<?php echo $currentPageNum; ?>">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectFromModal() {
    const id = document.getElementById('viewModal').dataset.approvalId;
    if (id) {
        document.getElementById('rejectId').value = id;
        closeViewModal();
        document.getElementById('rejectModal').style.display = 'block';
    }
}

window.onclick = function(event) {
    if (event.target.id === 'viewModal') closeViewModal();
    if (event.target.id === 'rejectModal') closeRejectModal();
}
</script>
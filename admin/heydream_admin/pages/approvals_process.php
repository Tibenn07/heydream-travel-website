<?php
// pages/approvals_process.php - Approval POST processing (No HTML output)

// This file is included from admin_dashboard.php BEFORE any HTML is sent

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
    
} elseif ($action === 'reject_package') {
    $reason = $_POST['rejection_reason'] ?? '';
    $stmt = $conn->prepare("UPDATE approvals SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $reason, $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = 'Package rejected.';
    
} elseif ($action === 'delete_package') {
    $stmt = $conn->prepare("DELETE FROM approvals WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = 'Package entry deleted.';
}

// Redirect back
header("Location: admin_dashboard.php?page=approval&p=" . $currentPageNum);
exit;
?>
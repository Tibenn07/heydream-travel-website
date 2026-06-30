<?php
// File: admin/api/voucher_api.php
ob_start();
header('Content-Type: application/json');

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once __DIR__ . '/../../config/database.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_vouchers':
        try {
            $stmt = $pdo->query("SELECT v.*, 
                (SELECT COUNT(*) FROM voucher_redemptions WHERE voucher_id = v.id) as redemption_count 
                FROM vouchers v ORDER BY v.created_at DESC");
            $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch targets and packages for each voucher
            foreach ($vouchers as &$voucher) {
                // Targets
                $tStmt = $pdo->prepare("SELECT target_type FROM voucher_targets WHERE voucher_id = ?");
                $tStmt->execute([$voucher['id']]);
                $voucher['targets'] = $tStmt->fetchAll(PDO::FETCH_COLUMN);

                // Packages
                $pStmt = $pdo->prepare("SELECT target_type, package_id FROM voucher_packages WHERE voucher_id = ?");
                $pStmt->execute([$voucher['id']]);
                $voucher['packages'] = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            ob_clean();
            echo json_encode(['success' => true, 'data' => $vouchers]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_voucher':
        try {
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
            $stmt->execute([$id]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($voucher) {
                // Fetch targets
                $tStmt = $pdo->prepare("SELECT target_type FROM voucher_targets WHERE voucher_id = ?");
                $tStmt->execute([$id]);
                $voucher['targets'] = $tStmt->fetchAll(PDO::FETCH_COLUMN);

                // Fetch packages
                $pStmt = $pdo->prepare("SELECT target_type, package_id FROM voucher_packages WHERE voucher_id = ?");
                $pStmt->execute([$id]);
                $voucher['packages'] = $pStmt->fetchAll(PDO::FETCH_ASSOC);

                ob_clean();
                echo json_encode(['success' => true, 'data' => $voucher]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Voucher not found']);
            }
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_voucher':
        try {
            $id = $_POST['id'] ?? '';
            $voucher_name = $_POST['voucher_name'] ?? '';
            $voucher_code = strtoupper(trim($_POST['voucher_code'] ?? ''));
            $description = $_POST['description'] ?? '';
            $discount_type = $_POST['discount_type'] ?? 'fixed_amount';
            $discount_value = floatval($_POST['discount_value'] ?? 0);
            $minimum_spend = floatval($_POST['minimum_spend'] ?? 0);
            $maximum_discount = !empty($_POST['maximum_discount']) ? floatval($_POST['maximum_discount']) : null;
            $max_total_redemptions = intval($_POST['max_total_redemptions'] ?? 0);
            $max_redemptions_per_user = intval($_POST['max_redemptions_per_user'] ?? 1);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $priority = intval($_POST['priority'] ?? 0);
            $display_order = intval($_POST['display_order'] ?? 0);
            $banner_image_url = $_POST['banner_image_url'] ?? null;
            $color_theme = $_POST['color_theme'] ?? null;
            $audience = $_POST['audience'] ?? 'everyone';
            $collection_method = $_POST['collection_method'] ?? 'auto_available';

            // Selected target types (sections)
            $targets = $_POST['targets'] ?? []; // Array of strings
            // Selected package ids mapping
            $packages = $_POST['packages'] ?? []; // Expected format: Array of objects/arrays with target_type and package_id

            if (empty($voucher_name) || empty($voucher_code) || empty($start_date) || empty($end_date) || $discount_value <= 0) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Please fill in all required fields and ensure discount value is greater than 0.']);
                exit;
            }

            // Check if code is unique
            $checkStmt = $pdo->prepare("SELECT id FROM vouchers WHERE voucher_code = ? AND id != ?");
            $checkStmt->execute([$voucher_code, intval($id)]);
            if ($checkStmt->fetch()) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Voucher code already exists. Please use a unique code.']);
                exit;
            }

            $pdo->beginTransaction();

            if (!empty($id)) {
                // Update
                $stmt = $pdo->prepare("UPDATE vouchers SET 
                    voucher_name = ?, voucher_code = ?, description = ?, discount_type = ?, 
                    discount_value = ?, minimum_spend = ?, maximum_discount = ?, 
                    max_total_redemptions = ?, max_redemptions_per_user = ?, 
                    start_date = ?, end_date = ?, status = ?, priority = ?, 
                    display_order = ?, banner_image_url = ?, color_theme = ?, 
                    audience = ?, collection_method = ?
                    WHERE id = ?");
                $stmt->execute([
                    $voucher_name, $voucher_code, $description, $discount_type,
                    $discount_value, $minimum_spend, $maximum_discount,
                    $max_total_redemptions, $max_redemptions_per_user,
                    $start_date, $end_date, $status, $priority,
                    $display_order, $banner_image_url, $color_theme,
                    $audience, $collection_method, $id
                ]);
                $voucherId = $id;

                // Clear targets and packages
                $pdo->prepare("DELETE FROM voucher_targets WHERE voucher_id = ?")->execute([$voucherId]);
                $pdo->prepare("DELETE FROM voucher_packages WHERE voucher_id = ?")->execute([$voucherId]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO vouchers (
                    voucher_name, voucher_code, description, discount_type, 
                    discount_value, minimum_spend, maximum_discount, 
                    max_total_redemptions, max_redemptions_per_user, 
                    start_date, end_date, status, priority, 
                    display_order, banner_image_url, color_theme, 
                    audience, collection_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $voucher_name, $voucher_code, $description, $discount_type,
                    $discount_value, $minimum_spend, $maximum_discount,
                    $max_total_redemptions, $max_redemptions_per_user,
                    $start_date, $end_date, $status, $priority,
                    $display_order, $banner_image_url, $color_theme,
                    $audience, $collection_method
                ]);
                $voucherId = $pdo->lastInsertId();
            }

            // Insert targets
            if (!empty($targets)) {
                $targetStmt = $pdo->prepare("INSERT INTO voucher_targets (voucher_id, target_type) VALUES (?, ?)");
                foreach ($targets as $target) {
                    $targetStmt->execute([$voucherId, $target]);
                }
            }

            // Insert packages
            if (!empty($packages)) {
                $packageStmt = $pdo->prepare("INSERT INTO voucher_packages (voucher_id, target_type, package_id) VALUES (?, ?, ?)");
                foreach ($packages as $pkg) {
                    if (is_string($pkg)) {
                        $pkgDecoded = json_decode($pkg, true);
                    } else {
                        $pkgDecoded = $pkg;
                    }
                    if (isset($pkgDecoded['target_type']) && isset($pkgDecoded['package_id'])) {
                        $packageStmt->execute([$voucherId, $pkgDecoded['target_type'], $pkgDecoded['package_id']]);
                    }
                }
            }

            $pdo->commit();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Voucher saved successfully!', 'id' => $voucherId]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_voucher':
        try {
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
            $stmt->execute([$id]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Voucher deleted successfully!']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'duplicate_voucher':
        try {
            $id = $_POST['id'] ?? 0;
            
            // Fetch original
            $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
            $stmt->execute([$id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Original voucher not found.']);
                exit;
            }

            $pdo->beginTransaction();

            // Create duplicate code
            $newCode = $original['voucher_code'] . '_COPY';
            // Verify new code uniqueness, add random number if duplicate
            $checkStmt = $pdo->prepare("SELECT id FROM vouchers WHERE voucher_code = ?");
            $checkStmt->execute([$newCode]);
            if ($checkStmt->fetch()) {
                $newCode = $original['voucher_code'] . rand(100, 999);
            }

            $dupStmt = $pdo->prepare("INSERT INTO vouchers (
                voucher_name, voucher_code, description, discount_type, 
                discount_value, minimum_spend, maximum_discount, 
                max_total_redemptions, max_redemptions_per_user, 
                start_date, end_date, status, priority, 
                display_order, banner_image_url, color_theme, 
                audience, collection_method
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $dupStmt->execute([
                $original['voucher_name'] . ' (Copy)', $newCode, $original['description'], $original['discount_type'],
                $original['discount_value'], $original['minimum_spend'], $original['maximum_discount'],
                $original['max_total_redemptions'], $original['max_redemptions_per_user'],
                $original['start_date'], $original['end_date'], 'inactive', $original['priority'],
                $original['display_order'], $original['banner_image_url'], $original['color_theme'],
                $original['audience'], $original['collection_method']
            ]);
            $newVoucherId = $pdo->lastInsertId();

            // Copy targets
            $tStmt = $pdo->prepare("SELECT target_type FROM voucher_targets WHERE voucher_id = ?");
            $tStmt->execute([$id]);
            $targets = $tStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($targets)) {
                $targetStmt = $pdo->prepare("INSERT INTO voucher_targets (voucher_id, target_type) VALUES (?, ?)");
                foreach ($targets as $target) {
                    $targetStmt->execute([$newVoucherId, $target]);
                }
            }

            // Copy packages
            $pStmt = $pdo->prepare("SELECT target_type, package_id FROM voucher_packages WHERE voucher_id = ?");
            $pStmt->execute([$id]);
            $packages = $pStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($packages)) {
                $packageStmt = $pdo->prepare("INSERT INTO voucher_packages (voucher_id, target_type, package_id) VALUES (?, ?, ?)");
                foreach ($packages as $pkg) {
                    $packageStmt->execute([$newVoucherId, $pkg['target_type'], $pkg['package_id']]);
                }
            }

            $pdo->commit();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Voucher duplicated successfully as ' . $newCode, 'new_id' => $newVoucherId]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_packages_for_target':
        try {
            $target_type = $_GET['target_type'] ?? '';
            $packages = [];

            switch ($target_type) {
                case 'flash_deals':
                    $stmt = $pdo->query("SELECT id, title AS name FROM flash_deals WHERE is_active = 1 ORDER BY title ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'local_destinations':
                    $stmt = $pdo->query("SELECT id, name FROM destinations WHERE type = 'local' AND is_active = 1 ORDER BY name ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'foreign_destinations':
                    // Fetch from destinations and foreign_destinations table
                    $stmt = $pdo->query("
                        SELECT id, name FROM destinations WHERE type = 'foreign' AND is_active = 1 
                        UNION 
                        SELECT id, name FROM foreign_destinations WHERE is_active = 1 
                        ORDER BY name ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'flights':
                    $stmt = $pdo->query("SELECT id, title AS name FROM site_services WHERE service_type = 'flight' AND is_active = 1 ORDER BY title ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'flight_packages':
                    // Flight packages can fall under standard packages or site_services
                    $stmt = $pdo->query("SELECT id, title AS name FROM site_services WHERE service_type = 'flight' AND is_active = 1 ORDER BY title ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'cruises':
                    $stmt = $pdo->query("
                        SELECT id, title AS name FROM cruises WHERE status = 'Available' AND is_published = 1 
                        UNION 
                        SELECT id, title AS name FROM site_services WHERE service_type = 'cruise' AND is_active = 1 
                        ORDER BY name ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'experiences':
                    $stmt = $pdo->query("SELECT id, title AS name FROM site_services WHERE service_type = 'experience' AND is_active = 1 ORDER BY title ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'premium_services':
                    $stmt = $pdo->query("SELECT id, title AS name FROM site_services WHERE service_type = 'premium' AND is_active = 1 ORDER BY title ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'visa_services':
                    $stmt = $pdo->query("SELECT id, title AS name FROM visas WHERE is_active = 1 ORDER BY title ASC");
                    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
            }

            ob_clean();
            echo json_encode(['success' => true, 'data' => $packages]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

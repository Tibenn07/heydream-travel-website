<?php
// File: api/user_voucher_api.php
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? null;

// Helper: respond
function respond($success, $data = null, $message = '') {
    ob_clean();
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

// Helper: get active voucher targets
function getVoucherTargets($pdo, $voucherId) {
    $stmt = $pdo->prepare("SELECT target_type FROM voucher_targets WHERE voucher_id = ?");
    $stmt->execute([$voucherId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Helper: get per-package voucher restrictions
function getVoucherPackageTargets($pdo, $voucherId) {
    $stmt = $pdo->prepare("SELECT target_type, package_id FROM voucher_packages WHERE voucher_id = ?");
    $stmt->execute([$voucherId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper: check if user has already claimed a voucher
function userHasClaimed($pdo, $userId, $voucherId) {
    $stmt = $pdo->prepare("SELECT id FROM user_vouchers WHERE user_id = ? AND voucher_id = ?");
    $stmt->execute([$userId, $voucherId]);
    return (bool)$stmt->fetch();
}

// Helper: count total redemptions of a voucher
function countRedemptions($pdo, $voucherId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM voucher_redemptions WHERE voucher_id = ?");
    $stmt->execute([$voucherId]);
    return (int)$stmt->fetchColumn();
}

switch ($action) {

    // ── GET MY VOUCHERS ──────────────────────────────────────────────────
    case 'get_my_vouchers':
        if (!$userId) respond(false, null, 'You must be logged in to view your vouchers.');

        try {
            $stmt = $pdo->prepare("
                SELECT v.*, uv.id AS user_voucher_id, uv.collected_at, uv.is_used
                FROM user_vouchers uv
                JOIN vouchers v ON v.id = uv.voucher_id
                WHERE uv.user_id = ?
                ORDER BY uv.collected_at DESC
            ");
            $stmt->execute([$userId]);
            $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($vouchers as &$v) {
                $v['targets'] = getVoucherTargets($pdo, $v['id']);
                $v['package_targets'] = getVoucherPackageTargets($pdo, $v['id']);
            }

            respond(true, $vouchers);
        } catch (PDOException $e) {
            respond(false, null, $e->getMessage());
        }
        break;

    // ── GET AVAILABLE VOUCHERS ───────────────────────────────────────────
    case 'get_available_vouchers':
        try {
            $today = date('Y-m-d');

            // Build audience filter
            $audienceSql = "(v.audience = 'everyone'";
            if ($userId) {
                $audienceSql .= " OR v.audience = 'logged_in_only'";

                // Check if first-time customer
                $bookingCountStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
                $bookingCountStmt->execute([$userId]);
                $bookingCount = (int)$bookingCountStmt->fetchColumn();

                if ($bookingCount === 0) {
                    $audienceSql .= " OR v.audience = 'first_time_customers'";
                } else {
                    $audienceSql .= " OR v.audience = 'returning_customers'";
                }
            }
            $audienceSql .= ")";

            $stmt = $pdo->prepare("
                SELECT v.*
                FROM vouchers v
                WHERE v.status = 'active'
                  AND v.start_date <= :today
                  AND v.end_date >= :today2
                  AND $audienceSql
                ORDER BY v.priority DESC, v.created_at DESC
            ");
            $stmt->execute([':today' => $today, ':today2' => $today]);
            $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($vouchers as &$v) {
                $v['targets'] = getVoucherTargets($pdo, $v['id']);
                $v['package_targets'] = getVoucherPackageTargets($pdo, $v['id']);

                // Check if this user has already claimed it
                $v['already_claimed'] = $userId ? userHasClaimed($pdo, $userId, $v['id']) : false;

                // Check total usage limit
                if ((int)$v['max_total_redemptions'] > 0) {
                    $used = countRedemptions($pdo, $v['id']);
                    if ($used >= (int)$v['max_total_redemptions']) {
                        continue; // Skip exhausted vouchers
                    }
                }
            }

            // Filter out null entries from exhausted vouchers
            $vouchers = array_values(array_filter($vouchers));

            respond(true, $vouchers);
        } catch (PDOException $e) {
            respond(false, null, $e->getMessage());
        }
        break;

    // ── CLAIM VOUCHER ────────────────────────────────────────────────────
    case 'claim_voucher':
        if (!$userId) respond(false, null, 'You must be logged in to claim vouchers.');

        $voucherId = intval($_POST['voucher_id'] ?? 0);
        if (!$voucherId) respond(false, null, 'Invalid voucher.');

        try {
            // Fetch the voucher
            $vStmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND status = 'active'");
            $vStmt->execute([$voucherId]);
            $voucher = $vStmt->fetch(PDO::FETCH_ASSOC);

            if (!$voucher) respond(false, null, 'Voucher not found or is no longer active.');

            // Check date validity
            $today = date('Y-m-d');
            if ($voucher['start_date'] > $today || $voucher['end_date'] < $today) {
                respond(false, null, 'This voucher is outside its validity period.');
            }

            // Check if auto-available (no claim needed)
            if ($voucher['collection_method'] === 'auto_available') {
                respond(false, null, 'This voucher is automatically applied at checkout — no need to claim it.');
            }

            // Check if already claimed
            if (userHasClaimed($pdo, $userId, $voucherId)) {
                respond(false, null, 'You have already claimed this voucher.');
            }

            // Check per-user redemption limit against wallet count
            $userClaimedCount = $pdo->prepare("SELECT COUNT(*) FROM user_vouchers WHERE user_id = ? AND voucher_id = ?");
            $userClaimedCount->execute([$userId, $voucherId]);
            $alreadyClaimedTimes = (int)$userClaimedCount->fetchColumn();

            if ((int)$voucher['max_redemptions_per_user'] > 0 && $alreadyClaimedTimes >= (int)$voucher['max_redemptions_per_user']) {
                respond(false, null, 'You have reached the claim limit for this voucher.');
            }

            // Check total usage limit
            if ((int)$voucher['max_total_redemptions'] > 0) {
                $totalUsed = countRedemptions($pdo, $voucherId);
                if ($totalUsed >= (int)$voucher['max_total_redemptions']) {
                    respond(false, null, 'Sorry, this voucher has reached its maximum redemption limit.');
                }
            }

            // Save to user_vouchers
            $insertStmt = $pdo->prepare("INSERT INTO user_vouchers (user_id, voucher_id, is_used, collected_at) VALUES (?, ?, 0, NOW())");
            $insertStmt->execute([$userId, $voucherId]);

            respond(true, null, "Voucher \"{$voucher['voucher_code']}\" has been added to your wallet!");
        } catch (PDOException $e) {
            respond(false, null, $e->getMessage());
        }
        break;

    // ── VALIDATE VOUCHER (for checkout) ─────────────────────────────────
    case 'validate_voucher':
        if (!$userId) respond(false, null, 'You must be logged in.');

$voucherId       = intval($_POST['voucher_id'] ?? 0);
            $totalAmount     = floatval($_POST['total_amount'] ?? 0);
            $travelers       = intval($_POST['travelers'] ?? 0);
            $targetType      = $_POST['target_type'] ?? '';    // e.g. "local_destinations"
        $targetPackageId = intval($_POST['package_id'] ?? 0);

        if (!$voucherId) respond(false, null, 'No voucher selected.');

        try {
            // Fetch user_voucher record OR auto-available voucher
            $uvStmt = $pdo->prepare("
                SELECT uv.*, v.*
                FROM user_vouchers uv
                JOIN vouchers v ON v.id = uv.voucher_id
                WHERE uv.user_id = ? AND uv.voucher_id = ? AND uv.is_used = 0
            ");
            $uvStmt->execute([$userId, $voucherId]);
            $uv = $uvStmt->fetch(PDO::FETCH_ASSOC);

            // If not in wallet, check auto_available
            if (!$uv) {
                $avStmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND collection_method = 'auto_available' AND status = 'active'");
                $avStmt->execute([$voucherId]);
                $uv = $avStmt->fetch(PDO::FETCH_ASSOC);
                if ($uv) {
                    $uv['is_used'] = 0; // Treat as usable
                }
            }

            if (!$uv) respond(false, null, 'Voucher not found in your wallet or already used.');

            // Check date
            $today = date('Y-m-d');
            if (($uv['start_date'] ?? $uv['v_start_date']) > $today || ($uv['end_date'] ?? $uv['v_end_date']) < $today) {
                respond(false, null, 'This voucher has expired.');
            }

            // Fetch actual voucher if not merged
            $vStmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
            $vStmt->execute([$voucherId]);
            $voucher = $vStmt->fetch(PDO::FETCH_ASSOC);

            if (!$voucher || $voucher['status'] !== 'active') {
                respond(false, null, 'This voucher is no longer active.');
            }

            // Determine currency symbol by target type
            $currencySymbol = ($targetType === 'foreign_destinations') ? '$' : '₱';

            // Check min spend
            if (floatval($voucher['minimum_spend']) > 0 && $totalAmount < floatval($voucher['minimum_spend'])) {
                respond(false, null, "Minimum spend of {$currencySymbol}" . number_format($voucher['minimum_spend'], 2) . " required to use this voucher.");
            }

            // Check target type eligibility
            $targets = getVoucherTargets($pdo, $voucherId);
            if (!empty($targets) && $targetType && !in_array($targetType, $targets)) {
                respond(false, null, 'This voucher is not valid for the selected booking type.');
            }

            // Check package-level targeting
            if (!empty($targets) && $targetPackageId > 0) {
                $pkgStmt = $pdo->prepare("SELECT id FROM voucher_packages WHERE voucher_id = ? AND target_type = ?");
                $pkgStmt->execute([$voucherId, $targetType]);
                $specificPackages = $pkgStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                if (!empty($specificPackages) && !in_array($targetPackageId, $specificPackages)) {
                    respond(false, null, 'This voucher is not valid for the selected package.');
                }
            }

            // Per-user redemption check (from voucher_redemptions)
            if ((int)$voucher['max_redemptions_per_user'] > 0) {
                $userRedCount = $pdo->prepare("SELECT COUNT(*) FROM voucher_redemptions WHERE voucher_id = ? AND user_id = ?");
                $userRedCount->execute([$voucherId, $userId]);
                if ((int)$userRedCount->fetchColumn() >= (int)$voucher['max_redemptions_per_user']) {
                    respond(false, null, 'You have reached your redemption limit for this voucher.');
                }
            }

            // Total usage limit check
            if ((int)$voucher['max_total_redemptions'] > 0) {
                $totalUsed = countRedemptions($pdo, $voucherId);
                if ($totalUsed >= (int)$voucher['max_total_redemptions']) {
                    respond(false, null, 'This voucher has reached its total redemption limit.');
                }
            }

            // Calculate discount per traveler cap
            $discountAmount = 0;
            $eligibleTravelers = max(0, $travelers);
            $maxDiscountedTravelers = intval($voucher['max_discounted_travelers'] ?? 0);
            if ($maxDiscountedTravelers > 0) {
                if ($travelers < $maxDiscountedTravelers) {
                    $plural = $maxDiscountedTravelers === 1 ? '' : 's';
                    respond(false, null, "This voucher requires at least {$maxDiscountedTravelers} traveler{$plural}.");
                }
                $eligibleTravelers = $maxDiscountedTravelers;
            } else {
                if ($eligibleTravelers === 0) {
                    $eligibleTravelers = $travelers = max(1, $travelers);
                }
            }
            $ratio = ($travelers > 0) ? ($eligibleTravelers / $travelers) : 1;
            $discountBaseAmount = $totalAmount * $ratio;

            if ($voucher['discount_type'] === 'percentage') {
                $discountAmount = ($discountBaseAmount * floatval($voucher['discount_value'])) / 100;
                if (floatval($voucher['maximum_discount']) > 0) {
                    $discountAmount = min($discountAmount, floatval($voucher['maximum_discount']));
                }
            } else {
                $discountAmount = floatval($voucher['discount_value']);
                $discountAmount = min($discountAmount, $discountBaseAmount);
            }
            $discountAmount = min($discountAmount, $totalAmount); // Can't discount more than total
            $finalAmount = $totalAmount - $discountAmount;

            respond(true, [
                'voucher_id'          => $voucherId,
                'voucher_code'        => $voucher['voucher_code'],
                'voucher_name'        => $voucher['voucher_name'],
                'discount_type'       => $voucher['discount_type'],
                'discount_value'      => $voucher['discount_value'],
                'discount_amount'     => round($discountAmount, 2),
                'final_amount'        => round($finalAmount, 2),
                'original_amount'     => $totalAmount,
                'eligible_travelers'  => $eligibleTravelers,
                'total_travelers'     => $travelers,
                'max_discounted_travelers' => $maxDiscountedTravelers,
            ], 'Voucher applied successfully!');
        } catch (PDOException $e) {
            respond(false, null, $e->getMessage());
        }
        break;

    // ── APPLY VOUCHER REDEMPTION (called after booking is confirmed) ──────
    case 'redeem_voucher':
        if (!$userId) respond(false, null, 'Unauthorized.');

        $voucherId   = intval($_POST['voucher_id'] ?? 0);
        $bookingId   = intval($_POST['booking_id'] ?? 0);
        $discountAmount = floatval($_POST['discount_amount'] ?? 0);

        if (!$voucherId || !$bookingId) respond(false, null, 'Missing required data.');

        try {
            $pdo->beginTransaction();

            // Record redemption
            $redStmt = $pdo->prepare("INSERT INTO voucher_redemptions (voucher_id, user_id, booking_id, redemption_amount, redemption_date) VALUES (?, ?, ?, ?, NOW())");
            $redStmt->execute([$voucherId, $userId, $bookingId, $discountAmount]);

            // Mark user_voucher as used (if it was a collected one)
            $markStmt = $pdo->prepare("UPDATE user_vouchers SET is_used = 1, used_at = NOW() WHERE user_id = ? AND voucher_id = ? AND is_used = 0 LIMIT 1");
            $markStmt->execute([$userId, $voucherId]);

            $pdo->commit();
            respond(true, null, 'Voucher redeemed successfully.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, null, $e->getMessage());
        }
        break;

    default:
        respond(false, null, 'Invalid action.');
        break;
}

<?php
// File: api/save-service-booking.php
// Generic API to save various service bookings to the unified bookings table

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partner-booking-tracker.php';

ensurePartnerBookingTracking($pdo);

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get input from either JSON or POST (FormData)
$is_form_data = !empty($_POST);
if ($is_form_data) {
    $input = $_POST;
} else {
    $input = json_decode(file_get_contents('php://input'), true);
}

error_log("Service booking request: " . json_encode($input));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Required fields for any booking
$required = ['service_type', 'package_name', 'full_name', 'phone', 'total_amount'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Handle Payment Proof File Upload
$payment_proof_path = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
    $new_filename = 'REC_' . uniqid() . '_' . date('Ymd') . '.' . $file_ext;
    $target_file = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_file)) {
        $payment_proof_path = 'uploads/receipts/' . $new_filename;
    }
}

try {
    // Get user details from session
    $user_id = null;
    $user_email = $input['email'] ?? null;

    if (isset($auth) && $auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        $user_id = $user['id'];
        $user_email = $user['email'];
    }

    if (empty($user_email)) {
        echo json_encode(['success' => false, 'message' => 'User email is required. Please log in.']);
        exit;
    }

    // Generate booking number based on service type
    $prefix = strtoupper(substr($input['service_type'], 0, 2));
    $booking_number = $prefix . '-' . strtoupper(substr(uniqid(), -6)) . date('Ymd');

    // Ensure unique booking number
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_number = ?");
    $stmt->execute([$booking_number]);
    while ($stmt->fetch()) {
        $booking_number = $prefix . '-' . strtoupper(substr(uniqid(), -6)) . date('Ymd');
        $stmt->execute([$booking_number]);
    }

    // Prepare payment data - Force 'unpaid' for all new bookings
    $payment_status = 'unpaid';
    $payment_method = $input['payment_method'] ?? null;
    $payment_reference = $input['payment_reference'] ?? null;
    $partnerMeta = resolvePartnerBookingMeta($pdo, $input);

    // Status stays 'unpaid' until admin verifies reference
    error_log("Service booking: method=$payment_method, ref=$payment_reference, status=unpaid");

    // Insert into bookings table
    $sql = "INSERT INTO bookings (
        user_id, 
        booking_number, 
        destination_name, 
        package_name, 
        package_duration,
        price_per_person, 
        full_name, 
        email, 
        phone, 
        travel_date,
        number_of_travelers, 
        special_requests, 
        total_amount, 
        booking_status, 
        payment_status, 
        payment_method, 
        payment_reference,
        payment_proof,
        currency,
        partner_id,
        partner_company,
        partner_package_id,
        partner_package_name,
        partner_source,
        partner_approved
    ) VALUES (
        :user_id, 
        :booking_number, 
        :destination_name, 
        :package_name, 
        :package_duration,
        :price_per_person, 
        :full_name, 
        :email, 
        :phone, 
        :travel_date,
        :number_of_travelers, 
        :special_requests, 
        :total_amount, 
        'pending', 
        :payment_status, 
        :payment_method, 
        :payment_reference,
        :payment_proof,
        :currency,
        :partner_id,
        :partner_company,
        :partner_package_id,
        :partner_package_name,
        :partner_source,
        :partner_approved
    )";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':booking_number' => $booking_number,
        ':destination_name' => $input['service_type'], // e.g. "Visa Assistance"
        ':package_name' => $input['package_name'], // e.g. "Schengen Visa"
        ':package_duration' => $input['package_duration'] ?? 'N/A',
        ':price_per_person' => $input['price_per_person'] ?? $input['total_amount'],
        ':full_name' => $input['full_name'],
        ':email' => $user_email,
        ':phone' => $input['phone'],
        ':travel_date' => !empty($input['travel_date']) ? $input['travel_date'] : (!empty($input['travel_dates']) ? $input['travel_dates'] : date('Y-m-d')),
        ':number_of_travelers' => $input['number_of_travelers'] ?? $input['travelers'] ?? 1,
        ':special_requests' => $input['special_requests'] ?? null,
        ':total_amount' => $input['total_amount'],
        ':payment_status' => $payment_status,
        ':payment_method' => $payment_method,
        ':payment_reference' => $payment_reference,
        ':payment_proof' => $payment_proof_path,
        ':currency' => $input['currency'] ?? '₱',
        ':partner_id' => $partnerMeta['partner_id'],
        ':partner_company' => $partnerMeta['partner_company'],
        ':partner_package_id' => $partnerMeta['partner_package_id'],
        ':partner_package_name' => $partnerMeta['partner_package_name'],
        ':partner_source' => $partnerMeta['partner_source'],
        ':partner_approved' => !empty($partnerMeta['partner_id']) ? 0 : 1
    ]);

    if ($result) {
        $booking_id = $pdo->lastInsertId();

        // ── Voucher Redemption ────────────────────────────────────────
        $voucher_id       = intval($input['voucher_id']       ?? 0);
        $voucher_discount = floatval($input['voucher_discount'] ?? 0);
        if ($voucher_id > 0 && $user_id) {
            try {
                $redStmt = $pdo->prepare(
                    "INSERT INTO voucher_redemptions (voucher_id, user_id, booking_id, redemption_amount, redemption_date)
                     VALUES (?, ?, ?, ?, NOW())"
                );
                $redStmt->execute([$voucher_id, $user_id, $booking_id, $voucher_discount]);

                $markStmt = $pdo->prepare(
                    "UPDATE user_vouchers SET is_used = 1, used_at = NOW()
                     WHERE user_id = ? AND voucher_id = ? AND is_used = 0 LIMIT 1"
                );
                $markStmt->execute([$user_id, $voucher_id]);
            } catch (Exception $ex) {
                error_log("Voucher redemption error: " . $ex->getMessage());
                // Non-fatal: booking still succeeded
            }
        }
        // ─────────────────────────────────────────────────────────────

        // Include email functions
        require_once __DIR__ . '/../config/email_functions.php';

        // Fetch full booking data
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        // Send confirmation email
        $emailSent = false;
        if ($booking) {
            $emailSent = sendBookingConfirmationEmail($booking_id, $booking);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Booking saved successfully',
            'booking_number' => $booking_number,
            'email_sent' => $emailSent
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save booking']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
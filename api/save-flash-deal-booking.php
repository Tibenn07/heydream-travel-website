<?php
// File: api/save-flash-deal-booking.php
// API endpoint to save flash deal bookings to bookings table

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partner-booking-tracker.php';

ensurePartnerBookingTracking($pdo);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
error_log("Flash deal booking request: " . json_encode($input));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Validate required fields (email no longer required from frontend)
$required = ['deal_id', 'deal_title', 'price_per_person', 'full_name', 'phone', 'travel_date', 'number_of_travelers', 'total_amount', 'currency'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Generate unique booking number
$booking_number = $input['booking_number'] ?? 'FD-' . strtoupper(substr(uniqid(), -6)) . date('Ymd');

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
    
    // Prepare payment data - Force 'unpaid' for all new bookings
    $payment_status = 'unpaid';
    $payment_method = $input['payment_method'] ?? null;
    $payment_reference = $input['payment_reference'] ?? null;
    $partnerMeta = resolvePartnerBookingMeta($pdo, $input);
    
    // If resolution failed but frontend provided partner data, use that as fallback
    if (empty($partnerMeta['partner_id']) && !empty($input['partner_id'])) {
        $partnerMeta['partner_id'] = $input['partner_id'];
    }
    if (empty($partnerMeta['partner_company']) && !empty($input['partner_company'])) {
        $partnerMeta['partner_company'] = $input['partner_company'];
    }
    if (empty($partnerMeta['partner_source']) && !empty($input['partner_source'])) {
        $partnerMeta['partner_source'] = $input['partner_source'];
    }
    if (empty($partnerMeta['partner_package_name']) && !empty($input['partner_package_name'])) {
        $partnerMeta['partner_package_name'] = $input['partner_package_name'];
    }
    
    // Status remains 'unpaid' until manually reviewed by admin
    error_log("Flash deal booking: method=$payment_method, ref=$payment_reference, status=unpaid");
    
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
        ':destination_name' => $input['deal_title'],
        ':package_name' => 'Flash Deal: ' . $input['deal_title'],
        ':package_duration' => $input['package_duration'] ?? '3D/2N',
        ':price_per_person' => $input['price_per_person'],
        ':full_name' => $input['full_name'],
        ':email' => $user_email,
        ':phone' => $input['phone'],
        ':travel_date' => $input['travel_date'],
        ':number_of_travelers' => $input['number_of_travelers'],
        ':special_requests' => $input['special_requests'] ?? null,
        ':total_amount' => $input['total_amount'],
        ':payment_status' => $payment_status,
        ':payment_method' => $payment_method,
        ':payment_reference' => $payment_reference,
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
        error_log("Flash deal booking created successfully! ID: $booking_id, Number: $booking_number");
        
        // Include email functions
        require_once __DIR__ . '/../config/email_functions.php';
        
        // Fetch the full booking data for email
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send confirmation email
        $emailSent = false;
        if ($booking) {
            $emailSent = sendBookingConfirmationEmail($booking_id, $booking);
            error_log("Flash deal confirmation email sent to: {$booking['email']}, Success: " . ($emailSent ? 'Yes' : 'No'));
        }
        
        // --- VOUCHER REDEMPTION ---
        if (!empty($input['voucher_id']) && $user_id) {
            $voucherId = intval($input['voucher_id']);
            $discountApplied = floatval($input['voucher_discount'] ?? 0);
            try {
                $pdo->prepare("INSERT INTO voucher_redemptions (voucher_id, user_id, booking_id, redemption_amount, redemption_date) VALUES (?, ?, ?, ?, NOW())")
                    ->execute([$voucherId, $user_id, $booking_id, $discountApplied]);
                $pdo->prepare("UPDATE user_vouchers SET is_used = 1, used_at = NOW() WHERE user_id = ? AND voucher_id = ? AND is_used = 0 LIMIT 1")
                    ->execute([$user_id, $voucherId]);
            } catch (Exception $voucherEx) {
                error_log("Voucher redemption error: " . $voucherEx->getMessage());
            }
        }
        // --- END VOUCHER ---

        echo json_encode([
            'success' => true,
            'message' => 'Booking saved successfully',
            'booking_number' => $booking_number,
            'email_sent' => $emailSent
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save booking to database']);
    }
    
} catch (PDOException $e) {
    error_log("Flash deal booking error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Booking failed due to database error: ' . $e->getMessage()
    ]);
}
?>

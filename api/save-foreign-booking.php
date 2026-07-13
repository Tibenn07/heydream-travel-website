<?php
// File: api/save-foreign-booking.php
// API endpoint to save foreign destination bookings to bookings table with email

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partner-booking-tracker.php';

ensurePartnerBookingTracking($pdo);

header('Content-Type: application/json');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$input = json_decode(file_get_contents('php://input'), true);
error_log("Foreign booking request: " . json_encode($input));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Validate required fields (email no longer required from frontend)
$required = ['destination_key', 'destination_name', 'price_per_person', 'full_name', 'phone', 'travel_date', 'number_of_travelers', 'total_amount', 'currency'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Generate unique booking number
    $booking_number = 'FOR-' . strtoupper(substr(uniqid(), -6)) . date('Ymd');
    
    // Ensure unique booking number
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_number = ?");
    $stmt->execute([$booking_number]);
    while ($stmt->fetch()) {
        $booking_number = 'FOR-' . strtoupper(substr(uniqid(), -6)) . date('Ymd');
        $stmt->execute([$booking_number]);
    }
    
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
    
    // Status remains 'unpaid' until manually reviewed by admin
    error_log("Foreign booking: method=$payment_method, ref=$payment_reference, status=unpaid");
    
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
        ':destination_name' => $input['destination_name'],
        ':package_name' => $input['destination_name'] . ' Tour Package',
        ':package_duration' => $input['package_duration'] ?? '4D/3N',
        ':price_per_person' => $input['price_per_person'] ?? 0,
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
        error_log("Foreign booking created! ID: $booking_id, Number: $booking_number");
        
        // Include email functions and send email
        require_once __DIR__ . '/../config/email_functions.php';
        
        // Get full booking data
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send confirmation email
        $emailSent = false;
        if ($booking) {
            $emailSent = sendBookingConfirmationEmail($booking_id, $booking);
            error_log("Foreign booking email sent status: " . ($emailSent ? 'true' : 'false'));
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
            'message' => 'Booking created successfully',
            'booking_id' => $booking_id,
            'booking_number' => $booking_number,
            'email_sent' => $emailSent
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save booking']);
    }
    
} catch (PDOException $e) {
    error_log("Foreign booking error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

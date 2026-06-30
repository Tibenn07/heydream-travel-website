<?php
// File: api/save-local-booking.php
// API endpoint to save local destination bookings

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
error_log("Local booking request: " . json_encode($input));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Validate required fields (email no longer required from frontend)
$required = ['destination_name', 'full_name', 'phone', 'travel_date', 'number_of_travelers', 'total_amount', 'currency'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Generate unique booking number
$booking_number = $input['booking_number'] ?? 'LOC-' . strtoupper(substr(uniqid(), -6)) . date('Ymd');

try {
    // Get user details from session
    $user_id = null;
    $user_email = $input['email'] ?? null; // Fallback to input if provided (though we'll prefer session)
    
    if (isset($auth) && $auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        $user_id = $user['id'];
        $user_email = $user['email']; // Always use account email if logged in
    }
    
    if (empty($user_email)) {
        echo json_encode(['success' => false, 'message' => 'User email is required. Please log in.']);
        exit;
    }
    
    // Prepare payment data - Force 'unpaid' for all new bookings
    $payment_status = 'unpaid';
    $payment_method = $input['payment_method'] ?? null;
    $payment_reference = $input['payment_reference'] ?? null;
    
    // Even if payment info is provided, it remains 'unpaid' until admin confirms it.
    error_log("Booking created with payment method: $payment_method, Ref: $payment_reference. Status: unpaid (pending confirmation)");
    
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
        currency
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
        :currency
    )";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':booking_number' => $booking_number,
        ':destination_name' => $input['destination_name'],
        ':package_name' => 'Local Destination: ' . $input['destination_name'],
        ':package_duration' => $input['package_duration'] ?? 'N/A',
        ':price_per_person' => $input['price_per_person'] ?? 0,
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
        ':currency' => $input['currency'] ?? '₱'
    ]);
    
    if ($result) {
        $booking_id = $pdo->lastInsertId();
        error_log("Local booking created successfully! ID: $booking_id, Number: $booking_number");
        
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
            error_log("Local booking confirmation email sent to: {$booking['email']}, Success: " . ($emailSent ? 'Yes' : 'No'));
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
    error_log("Local booking error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Booking failed due to database error: ' . $e->getMessage()
    ]);
}
?>

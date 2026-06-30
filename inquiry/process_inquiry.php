<?php
// inquiry/process_inquiry.php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Initialize Auth if available
$user_id = null;
if (class_exists('Auth')) {
    $auth = new Auth($pdo);
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        $user_id = $user['id'];
    }
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    // Prevent duplicate active inquiries from the same email
    $customer_email = $input['email'] ?? '';
    if ($customer_email) {
        $stmtCheck = $pdo->prepare("SELECT id FROM bookings 
            WHERE email = ? 
            AND payment_method = 'Inquiry Only' 
            AND booking_status NOT IN ('confirmed', 'completed', 'cancelled')");
        $stmtCheck->execute([$customer_email]);
        if ($stmtCheck->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'You already have an active inquiry being processed. Please wait for our team to contact you or complete your current request before submitting a new one.'
            ]);
            exit;
        }
    }

    // Generate unique booking number
    $prefix = 'INQ';
    $booking_number = $prefix . '-' . strtoupper(substr(uniqid(), -6)) . date('Ymd');

    // Combine special requests and preferences
    $special_requests = "Destination: " . ($input['destination'] ?? 'Not specified') . "\n";
    $special_requests .= "Travel Type: " . ($input['travel_type'] ?? 'Not specified') . "\n";
    $special_requests .= "Budget: " . ($input['budget'] ?? 'Not specified') . "\n";
    $special_requests .= "Hotel Preference: " . ($input['hotel_type'] ?? 'Not specified') . "\n";

    if (!empty($input['interests'])) {
        $special_requests .= "Interests: " . implode(', ', $input['interests']) . "\n";
    }

    if (!empty($input['special_requests'])) {
        $special_requests .= "Special Requests: " . $input['special_requests'] . "\n";
    }

    if ($input['travel_type'] === 'visa-assistance') {
        $special_requests .= "\n--- Visa Details ---\n";
        $special_requests .= "Needs Assistance: " . ($input['visa_assistance'] ?? 'No') . "\n";
        $special_requests .= "Passport Ready: " . ($input['passport_ready'] ?? 'No') . "\n";
        $special_requests .= "Travel History: " . ($input['travel_history'] ?? 'None') . "\n";
    }

    if (!empty($input['message'])) {
        $special_requests .= "\nMessage: " . $input['message'];
    }

    if (!empty($input['referral_source'])) {
        $special_requests .= "\nHow did you hear about us: " . $input['referral_source'];
    }

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
        marketing_consent
    ) VALUES (
        :user_id, 
        :booking_number, 
        :destination_name, 
        :package_name, 
        'N/A',
        0, 
        :full_name, 
        :email, 
        :phone, 
        :travel_date,
        :number_of_travelers, 
        :special_requests, 
        0, 
        'pending', 
        'unpaid', 
        'Inquiry Only',
        :marketing_consent
    )";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':booking_number' => $booking_number,
        ':destination_name' => $input['travel_type'] ?? 'General Inquiry',
        ':package_name' => $input['destination'] ?? 'Custom Trip',
        ':full_name' => $input['full_name'],
        ':email' => $input['email'],
        ':phone' => $input['contact_number'],
        ':travel_date' => !empty($input['travel_dates']) ? $input['travel_dates'] : (!empty($input['travel_date']) ? $input['travel_date'] : date('Y-m-d')),
        ':number_of_travelers' => (int) ($input['adults'] ?? 1) + (int) ($input['kids'] ?? 0),
        ':special_requests' => $special_requests,
        ':marketing_consent' => (int) ($input['marketing_consent'] ?? 0)
    ]);

    if ($result) {
        $booking_id = $pdo->lastInsertId();

        // Send Thank You Email
        $email_sent = false;
        $email_functions_path = __DIR__ . '/../config/email_functions.php';
        if (file_exists($email_functions_path)) {
            require_once $email_functions_path;
            if (function_exists('sendInquiryAcknowledgmentEmail')) {
                $email_sent = sendInquiryAcknowledgmentEmail($booking_id);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Inquiry recorded successfully',
            'booking_number' => $booking_number,
            'email_sent' => $email_sent
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record inquiry']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

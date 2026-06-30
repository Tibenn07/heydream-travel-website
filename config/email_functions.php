<?php
// File: config/email_functions.php
// Email functions for booking notifications

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Determine base URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';

$base_path = '';
if (strpos($script_path, '/admin/') !== false) {
    $base_path = substr($script_path, 0, strpos($script_path, '/admin/'));
} elseif (strpos($script_path, '/api/') !== false) {
    $base_path = substr($script_path, 0, strpos($script_path, '/api/'));
} elseif (strpos($script_path, '/User Account/') !== false) {
    $base_path = substr($script_path, 0, strpos($script_path, '/User Account/'));
} else {
    $base_path = str_replace('\\', '/', rtrim(dirname($script_path), '/\\'));
    if ($base_path === '/')
        $base_path = '';
}
// Ensure the base path is URL-encoded for spaces so email links don't break
$safe_base_path = str_replace(' ', '%20', $base_path);
$GLOBALS['APP_BASE_URL'] = $protocol . $host . $safe_base_path;

/**
 * Send booking confirmation email to customer
 */
function sendBookingConfirmationEmail($booking_id, $booking_data = null)
{
    global $pdo, $emailConfig;

    try {
        // If booking data not provided, fetch from database
        if (!$booking_data) {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $booking = $booking_data;
        }

        if (!$booking) {
            error_log("Booking not found for email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        // Recipients
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmation - HeyDream Travel and Tours';

        $total_amount = number_format($booking['total_amount'], 2);
        $price_per_person = number_format($booking['price_per_person'] ?? 0, 2);

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Booking Confirmation - HeyDream Travel and Tours</title>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f4f7f6; }
                .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .email-header { background: linear-gradient(135deg, #003580, #1a4b8c); padding: 30px; text-align: center; color: white; }
                .email-header h1 { margin: 0; font-size: 28px; }
                .email-body { padding: 30px; }
                .thankyou-message { text-align: center; margin-bottom: 30px; }
                .thankyou-message h2 { color: #28a745; margin: 10px 0; }
                .booking-details { background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 25px; }
                .booking-details h3 { color: #003580; margin-top: 0; margin-bottom: 15px; border-left: 4px solid #ff9800; padding-left: 12px; }
                .detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
                .detail-label { width: 140px; font-weight: 600; color: #666; }
                .detail-value { flex: 1; color: #333; }
                .total-amount { font-size: 20px; font-weight: 700; color: #ff9800; text-align: right; margin-top: 10px; }
                .payment-info { background: #fff9e6; border-radius: 12px; padding: 15px; margin: 20px 0; border-left: 4px solid #ff9800; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
                .button { display: inline-block; background: #ff9800; color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; margin-top: 20px; font-weight: 600; }
                @media (max-width: 480px) {
                    .detail-row { flex-direction: column; }
                    .detail-label { width: 100%; margin-bottom: 5px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>HeyDream Travel and Tours</h1>
                    <p>Your Journey Begins Here</p>
                </div>
                <div class='email-body'>
                    <div class='thankyou-message'>
                        <h2>Thank You, " . htmlspecialchars($booking['full_name']) . "! 🎉</h2>
                        <p>Your booking has been received and is being processed.</p>
                    </div>
                    
                    <div class='booking-details'>
                        <h3>📋 Booking Details</h3>
                        <div class='detail-row'>
                            <div class='detail-label'>Booking Number:</div>
                            <div class='detail-value'><strong>" . htmlspecialchars($booking['booking_number']) . "</strong></div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Booking Date:</div>
                            <div class='detail-value'>" . date('F d, Y H:i', strtotime($booking['created_at'])) . "</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Destination:</div>
                            <div class='detail-value'>" . htmlspecialchars($booking['destination_name']) . "</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Travel Date:</div>
                            <div class='detail-value'>" . date('F d, Y', strtotime($booking['travel_date'])) . "</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Travelers:</div>
                            <div class='detail-value'>" . $booking['number_of_travelers'] . " person(s)</div>
                        </div>
                        <div class='total-amount'>
                            Total Amount: ₱$total_amount
                        </div>
                    </div>
                    
                    <div class='payment-info'>
                        <h4 style='margin: 0 0 10px; color: #003580;'>💳 Payment Information</h4>
                        <p><strong>Status:</strong> " . ucfirst($booking['payment_status']) . "</p>
                        " . ($booking['payment_method'] ? "<p><strong>Payment Method:</strong> " . ucfirst($booking['payment_method']) . "</p>" : '') . "
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='" . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}' class='button'>View My Bookings</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                    <p>📞 0945 776 4140 | ✉️ heydreamtravelandtours@gmail.com</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "Thank you for booking with HeyDream Travel and Tours!\n\n"
            . "Booking Number: {$booking['booking_number']}\n"
            . "Destination: {$booking['destination_name']}\n"
            . "Travel Date: " . date('F d, Y', strtotime($booking['travel_date'])) . "\n"
            . "Travelers: {$booking['number_of_travelers']}\n"
            . "Total Amount: ₱$total_amount\n\n"
            . "View your bookings at: " . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}\n\n"
            . "© " . date('Y') . " HeyDream Travel and Tours";

        $mail->send();
        error_log("Booking confirmation email sent to: {$booking['email']}");
        return true;

    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send booking status update email to customer
 */
function sendBookingStatusEmail($booking_id, $booking_data = null)
{
    global $pdo, $emailConfig;

    try {
        // If booking data not provided, fetch from database
        if (!$booking_data) {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $booking = $booking_data;
        }

        if (!$booking) {
            error_log("Booking not found for status email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        // Recipients
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        $mail->isHTML(true);

        $total_amount = number_format($booking['total_amount'], 2);

        // Set email content based on new status
        switch ($booking['booking_status']) {
            case 'confirmed':
                if ($booking['destination_name'] === 'Visa Assistance' || stripos($booking['package_name'], 'visa') !== false) {
                    $mail->Subject = 'Visa Application Review & Payment - HeyDream';
                    $statusColor = '#007bff';
                    $statusIcon = '🔍';
                    $statusMessage = 'Checking Application & Payment Required';
                    $additionalMessage = 'Our agents are currently checking your visa application. To proceed, please fulfill the required documents and complete the payment below.';
                } else {
                    $mail->Subject = 'Booking Confirmed - HeyDream Travel and Tours';
                    $statusColor = '#28a745';
                    $statusIcon = '✅';
                    $statusMessage = 'Your booking has been CONFIRMED!';
                    $additionalMessage = 'Your travel arrangements are now confirmed. Please review the details below.';
                }
                break;
            case 'cancelled':
                $mail->Subject = 'Booking Cancelled - HeyDream Travel and Tours';
                $statusColor = '#dc3545';
                $statusIcon = '❌';
                $statusMessage = 'Your booking has been CANCELLED';
                $additionalMessage = 'Your booking has been cancelled. If you have any questions, please contact our support team.';
                break;
            case 'completed':
                $mail->Subject = 'Booking Completed - Thank You!';
                $statusColor = '#17a2b8';
                $statusIcon = '🎉';
                $statusMessage = 'Your journey is complete!';
                $additionalMessage = 'Thank you for traveling with HeyDream. We hope you had a wonderful experience!';
                break;
            case 'pending':
                $mail->Subject = 'Booking Status Update - HeyDream Travel and Tours';
                $statusColor = '#ff9800';
                $statusIcon = '⏳';
                $statusMessage = 'Your booking is pending confirmation';
                $additionalMessage = 'Your booking is being processed. We will notify you once it is confirmed.';
                break;
            default:
                $mail->Subject = 'Booking Status Update - HeyDream Travel and Tours';
                $statusColor = '#ff9800';
                $statusIcon = 'ℹ️';
                $statusMessage = 'Your booking status has been updated';
                $additionalMessage = 'Please review your booking details below.';
        }

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Booking Status Update - HeyDream Travel and Tours</title>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f4f7f6; }
                .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .email-header { background: linear-gradient(135deg, #003580, #1a4b8c); padding: 30px; text-align: center; color: white; }
                .email-header h1 { margin: 0; font-size: 28px; }
                .email-body { padding: 30px; }
                .status-badge { display: inline-block; background: $statusColor; color: white; padding: 8px 20px; border-radius: 30px; font-size: 16px; font-weight: 600; margin: 15px 0; }
                .booking-details { background: #f8f9fa; border-radius: 16px; padding: 20px; margin: 20px 0; }
                .booking-details h3 { color: #003580; margin-top: 0; margin-bottom: 15px; border-left: 4px solid #ff9800; padding-left: 12px; }
                .detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
                .detail-label { width: 140px; font-weight: 600; color: #666; }
                .detail-value { flex: 1; color: #333; }
                .total-amount { font-size: 20px; font-weight: 700; color: #ff9800; text-align: right; margin-top: 10px; }
                .admin-note { background: #e8f0fe; padding: 12px; border-radius: 8px; margin: 15px 0; font-size: 12px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
                .button { display: inline-block; background: #ff9800; color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; margin-top: 20px; font-weight: 600; }
                @media (max-width: 480px) {
                    .detail-row { flex-direction: column; }
                    .detail-label { width: 100%; margin-bottom: 5px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>HeyDream Travel and Tours</h1>
                    <p>Your Journey Matters</p>
                </div>
                <div class='email-body'>
                    <div style='text-align: center;'>
                        <div class='status-badge'>$statusIcon $statusMessage</div>
                        <p style='color: #666;'>$additionalMessage</p>
                    </div>
                    
                    <div class='booking-details'>
                        <h3>📋 Booking Details</h3>
                        <div class='detail-row'>
                            <div class='detail-label'>Booking Number:</div>
                            <div class='detail-value'><strong>" . htmlspecialchars($booking['booking_number']) . "</strong></div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Destination:</div>
                            <div class='detail-value'>" . htmlspecialchars($booking['destination_name']) . "</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Travel Date:</div>
                            <div class='detail-value'>" . date('F d, Y', strtotime($booking['travel_date'])) . "</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Travelers:</div>
                            <div class='detail-value'>" . $booking['number_of_travelers'] . " person(s)</div>
                        </div>
                        <div class='total-amount'>
                            Total Amount: ₱$total_amount
                        </div>
                    </div>
                    
                    " . (!empty($booking['admin_notes']) ? "
                    <div class='admin-note'>
                        <strong>📝 " . (($booking['destination_name'] === 'Visa Assistance' || stripos($booking['package_name'], 'visa') !== false) ? "Documents Needed / Note from Agent:" : "Note from Admin:") . "</strong><br>
                        " . nl2br(htmlspecialchars($booking['admin_notes'])) . "
                    </div>" : "") . "
                    
                    " . (($booking['booking_status'] === 'confirmed' && ($booking['destination_name'] === 'Visa Assistance' || stripos($booking['package_name'], 'visa') !== false)) ? "
                    <div style='background: #fff9e6; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 5px solid #ff9800;'>
                        <h4 style='margin: 0 0 10px; color: #003580;'>💳 Payment Required</h4>
                        <p style='margin-bottom: 15px; font-size: 14px;'>To proceed with your application, please complete the payment via GCash.</p>
                        <div style='text-align: center; margin: 15px 0;'>
                            <img src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=09457764140' alt='GCash QR Code' style='border: 1px solid #ccc; border-radius: 8px; padding: 5px; background: white;'>
                            <p style='margin: 10px 0 5px; font-size: 14px;'><strong>GCash Number:</strong> <span style='background:#f0f2f5; padding:3px 8px; border-radius:4px;'>0945 776 4140</span></p>
                            <p style='margin: 5px 0; font-size: 14px;'><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                        </div>
                        <p style='font-size: 13px; color: #666; margin-top: 15px;'>Once paid, please <strong>reply directly to this email</strong> and attach your screenshot/proof of payment. Our staff will review it shortly.</p>
                    </div>" : "") . "
                    
                    " . (!empty($booking['flight_details']) ? "
                    <div class='details-box' style='margin-top: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px;'>
                        <strong style='color: #003580; font-size: 1.1em;'><i class='fas fa-plane-departure'></i> Flight Details:</strong><br>
                        <div style='margin-top: 10px; font-size: 0.95em; color: #334155;'>
                            " . nl2br(htmlspecialchars($booking['flight_details'])) . "
                        </div>
                    </div>" : "") . "
                    
                    <div style='text-align: center;'>
                        <a href='" . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}' class='button'>View My Bookings</a>
                    </div>
                    
                    <div style='margin-top: 20px; padding: 15px; background: #e8f0fe; border-radius: 12px; text-align: center;'>
                        <p style='margin: 0; color: #003580;'>✈️ Need help? Contact us at 0945 776 4140 or heydreamtravelandtours@gmail.com</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "HeyDream Travel and Tours Booking Status Update\n\n"
            . "Booking: {$booking['booking_number']}\n"
            . "Status: " . strtoupper($booking['booking_status']) . "\n\n"
            . "Destination: {$booking['destination_name']}\n"
            . "Travel Date: " . date('F d, Y', strtotime($booking['travel_date'])) . "\n"
            . "Total: ₱$total_amount\n\n"
            . "View your bookings: " . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}\n\n"
            . "© " . date('Y') . " HeyDream Travel and Tours";

        $mail->send();
        error_log("Status update email sent to: {$booking['email']} for booking: {$booking['booking_number']} (Status: {$booking['booking_status']})");
        return true;

    } catch (Exception $e) {
        error_log("Status email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome email to newly registered user with verification link
 */
function sendWelcomeEmail($toEmail, $toName, $token)
{
    global $emailConfig;

    $verificationLink = "" . $GLOBALS['APP_BASE_URL'] . "/User Account/verify-email.php?token=" . $token;

    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        // Additional SMTP settings for Gmail
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to HeyDream Travel and Tours! 🎉';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to HeyDream Travel and Tours</title>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
                .email-header { background: linear-gradient(135deg, #003580, #1a4b8c); padding: 40px; text-align: center; color: white; }
                .email-header h1 { margin: 0; font-size: 32px; font-weight: 700; }
                .email-body { padding: 40px; line-height: 1.6; color: #4a5568; }
                .welcome-text { font-size: 24px; color: #003580; margin-bottom: 20px; font-weight: 700; text-align: center; }
                .features-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
                .feature-item { background: #f8f9fa; padding: 15px; border-radius: 16px; text-align: center; border: 1px solid #edf2f7; }
                .feature-icon { font-size: 24px; margin-bottom: 10px; }
                .cta-section { text-align: center; margin-top: 30px; }
                .button { display: inline-block; background: #ff9800; color: white; padding: 14px 35px; text-decoration: none; border-radius: 50px; font-weight: 700; box-shadow: 0 8px 15px rgba(255, 152, 0, 0.3); }
                .footer { background: #f8f9fa; padding: 25px; text-align: center; font-size: 13px; color: #718096; border-top: 1px solid #edf2f7; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>HeyDream Travel and Tours</h1>
                    <p>Your Adventure Starts Now</p>
                </div>
                <div class='email-body'>
                    <div class='welcome-text'>Hello, " . htmlspecialchars($toName) . "! 🎉</div>
                    <p>Welcome to the HeyDream family! We're thrilled to have you with us. Your account has been successfully created, and you're now ready to explore the most amazing destinations across the globe.</p>
                    
                    <div class='features-grid'>
                        <div class='feature-item'>
                            <div class='feature-icon'>🏝️</div>
                            <strong>Local Gems</strong>
                            <p style='font-size: 12px; margin: 5px 0 0;'>Discover the Philippines</p>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>🌏</div>
                            <strong>Global Tours</strong>
                            <p style='font-size: 12px; margin: 5px 0 0;'>Explore the world</p>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>⚡</div>
                            <strong>Flash Deals</strong>
                            <p style='font-size: 12px; margin: 5px 0 0;'>Unbeatable prices</p>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>⭐</div>
                            <strong>Loyalty Perks</strong>
                            <p style='font-size: 12px; margin: 5px 0 0;'>Exclusive rewards</p>
                        </div>
                    </div>
                    
                    <p>Whether you're looking for a relaxing beach getaway, a cultural expedition, or a thrilling adventure, we've got you covered. To get started and unlock all booking features, please confirm your email address below:</p>
                    
                    <div class='cta-section'>
                        <a href='$verificationLink' class='button'>Confirm My Account</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>Follow your dreams with HeyDream Travel & Tours</p>
                    <p>📞 0945 776 4140 | ✉️ heydreamtravelandtours@gmail.com</p>
                    <p style='margin-top: 15px;'>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "Welcome to HeyDream Travel and Tours, {$toName}!\n\n"
            . "Your account has been successfully created. Please confirm your email address to activate your account and start booking.\n\n"
            . "Confirm your account here: {$verificationLink}\n\n"
            . "Best regards,\nHeyDream Travel and Tours Team";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Welcome email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send login notification emails: notify admin and inform the user
 */
function sendLoginNotifications($userId, $method = 'email')
{
    // User login notifications have been disabled as per request
    return ['success' => true];
}

/**
 * Send admin login notification
 */
function sendAdminLoginNotification($adminId, $method = 'password')
{
    global $pdo, $emailConfig;
    try {
        $stmt = $pdo->prepare('SELECT id, full_name, email, username FROM admin_users WHERE id = ?');
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin)
            return ['success' => false, 'message' => 'Admin not found'];

        $device = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $time = date('F d, Y H:i:s');

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($emailConfig['from_email']);
        $mail->isHTML(true);
        $mail->Subject = 'Admin Login Notification - HeyDream';
        $mail->Body = "<h3>Admin Logged In</h3>
            <p><strong>Name:</strong> " . htmlspecialchars($admin['full_name']) . "</p>
            <p><strong>Username:</strong> " . htmlspecialchars($admin['username']) . "</p>
            <p><strong>Email:</strong> " . htmlspecialchars($admin['email']) . "</p>
            <p><strong>Method:</strong> " . htmlspecialchars($method) . "</p>
            <p><strong>Time:</strong> $time</p>
            <p><strong>IP:</strong> $ip</p>
            <p><strong>Device:</strong> " . htmlspecialchars($device) . "</p>";
        $mail->AltBody = "Admin Logged In\nName: {$admin['full_name']}\nUsername: {$admin['username']}\nEmail: {$admin['email']}\nMethod: {$method}\nTime: {$time}\nIP: {$ip}\nDevice: {$device}";
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log('Admin login notification failed: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send visa status update email to customer (Approved, Incomplete, Declined)
 */
function sendVisaStatusUpdateEmail($booking_id, $status, $reason = '', $booking_data = null)
{
    global $pdo, $emailConfig;

    try {
        if (!$booking_data) {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $booking = $booking_data;
        }

        if (!$booking) {
            error_log("Visa booking not found for status email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        $mail->isHTML(true);

        $statusTheme = [
            'approved' => [
                'title' => 'APPLICATION APPROVED',
                'bg' => 'linear-gradient(135deg, #6c5ce7, #8a7cff)',
                'badge_bg' => '#eafaf1',
                'badge_color' => '#1e8449',
                'badge_border' => '#a9dfbf',
                'subject' => 'Visa Application Approved - HeyDream Travel and Tours',
                'message' => 'Great news! Our agents have reviewed and <strong>approved</strong> your visa application assessment. We are now ready to proceed with the next steps.'
            ],
            'incomplete' => [
                'title' => 'ACTION REQUIRED',
                'bg' => 'linear-gradient(135deg, #ff9f43, #ff6b6b)',
                'badge_bg' => '#fff4e6',
                'badge_color' => '#d35400',
                'badge_border' => '#f5c6cb',
                'subject' => 'Action Required: Incomplete Visa Application - HeyDream Travel',
                'message' => 'We have reviewed your visa application, but we found that some information or documents are **incomplete or missing**.'
            ],
            'declined' => [
                'title' => 'APPLICATION DECLINED',
                'bg' => 'linear-gradient(135deg, #ee5253, #ff6b6b)',
                'badge_bg' => '#fdecea',
                'badge_color' => '#c0392b',
                'badge_border' => '#f5c6cb',
                'subject' => 'Update Regarding Your Visa Application - HeyDream Travel',
                'message' => 'We regret to inform you that your visa application assessment has been **declined** at this time.'
            ]
        ];

        $theme = $statusTheme[$status] ?? $statusTheme['incomplete'];
        $mail->Subject = $theme['subject'];

        $total_amount = number_format($booking['total_amount'], 2);

        $reasonHtml = '';
        if ($reason) {
            $reasonHtml = "
            <div style='background: #fff; border: 2px dashed {$theme['badge_color']}; border-radius: 12px; padding: 20px; margin-top: 25px;'>
                <h4 style='margin: 0 0 10px; color: {$theme['badge_color']};'><i class='fas fa-comment-dots'></i> Agent Feedback:</h4>
                <p style='margin: 0; font-size: 15px; color: #333; font-style: italic;'>\"" . nl2br(htmlspecialchars($reason)) . "\"</p>
            </div>";
        }

        $nextStepHtml = '';
        if ($status === 'approved') {
            $total_amt = isset($booking_data['total_amount']) ? $booking_data['total_amount'] : ($booking['total_amount'] ?? 0);
            $nextStepHtml = "
            <div class='action-step' style='background: #f0f7ff; border-left: 5px solid #6c5ce7; border-radius: 12px; padding: 20px; margin-top: 25px;'>
                <h4 style='margin: 0 0 10px; color: #003580;'>What's Next? Payment Required</h4>
                <p style='margin: 0 0 15px 0; font-size: 14px;'>Your application has passed the initial review! To proceed with the official embassy processing, please complete the payment for your visa assistance fee (<strong>₱" . number_format($total_amt, 2) . "</strong>).</p>
                
                <div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #dce4ec; margin-bottom: 15px;'>
                    <h5 style='margin: 0 0 10px 0; color: #333;'><i class='fas fa-mobile-alt' style='color:#003580;'></i> GCash Payment</h5>
                    <p style='margin: 5px 0; font-size: 13px;'><strong>Number:</strong> <span style='background:#f0f2f5; padding:3px 8px; border-radius:4px;'>0945 776 4140</span></p>
                    <p style='margin: 5px 0; font-size: 13px;'><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                </div>
                
                <p style='margin: 0; font-size: 14px; font-weight: bold;'>After Payment:</p>
                <p style='margin: 5px 0 0 0; font-size: 13px;'>Please <strong>reply directly to this email</strong> and attach your screenshot/proof of payment. You can also log in to your account to upload it there. For assistance, our agent will contact you shortly.</p>
            </div>";
        } else if ($status === 'incomplete') {
            $nextStepHtml = "
            <div class='action-step' style='background: #fff9e6; border-left: 5px solid #ff9f43; border-radius: 12px; padding: 20px; margin-top: 25px;'>
                <h4 style='margin: 0 0 10px; color: #856404;'>How to Proceed?</h4>
                <p style='margin: 0; font-size: 14px;'>Please address the feedback mentioned above. Our agent will contact you shortly to help you complete your application.</p>
            </div>";
        } else {
            $nextStepHtml = "
            <div class='action-step' style='background: #f8f9fa; border-left: 5px solid #ee5253; border-radius: 12px; padding: 20px; margin-top: 25px;'>
                <h4 style='margin: 0 0 10px; color: #444;'>Need Clarification?</h4>
                <p style='margin: 0; font-size: 14px;'>If you have questions regarding this decision, please feel free to contact our support team.</p>
            </div>";
        }

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
                .header { background: {$theme['bg']}; padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
                .content { padding: 40px 30px; color: #444; line-height: 1.6; }
                .status-badge { display: inline-block; background: {$theme['badge_bg']}; color: {$theme['badge_color']}; padding: 10px 25px; border-radius: 50px; font-weight: 800; font-size: 14px; margin-bottom: 25px; border: 1px solid {$theme['badge_border']}; }
                .details-box { background: #f8f9fa; border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #eee; }
                .details-box h3 { margin-top: 0; color: #003580; font-size: 18px; }
                .row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                .label { width: 140px; font-weight: 700; color: #666; font-size: 14px; }
                .val { flex: 1; color: #333; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
                .btn { display: inline-block; background: #6c5ce7; color: white; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: 700; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HeyDream Visa Assistance</h1>
                    <p style='opacity: 0.9; margin-top: 5px;'>Travel Document Support</p>
                </div>
                <div class='content'>
                    <div style='text-align: center;'>
                        <div class='status-badge'>" . $theme['title'] . "</div>
                    </div>
                    <p>Dear <strong>" . htmlspecialchars($booking['full_name']) . "</strong>,</p>
                    <p>{$theme['message']}</p>
                    
                    <div class='details-box'>
                        <h3>📋 Application Details</h3>
                        <div class='row'><div class='label'>Ref Number:</div><div class='val'>" . htmlspecialchars($booking['booking_number']) . "</div></div>
                        <div class='row'><div class='label'>Visa Type:</div><div class='val'>" . htmlspecialchars($booking['package_name']) . "</div></div>
                        <div class='row'><div class='label'>Processing:</div><div class='val'>" . htmlspecialchars($booking['package_duration']) . "</div></div>
                    </div>

                    $reasonHtml
                    
                    $nextStepHtml
                    
                    <div style='text-align: center; margin-top: 35px;'>
                        <p>Questions? Contact our support team.</p>
                        <a href='mailto:heydreamtravelandtours@gmail.com' class='btn'>Talk to an Agent</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                    <p>Manila, Philippines | 📞 0945 776 4140</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "Update regarding your visa application ({$booking['booking_number']}): " . strip_tags($theme['message']) . ($reason ? " Reason: $reason" : "");

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Visa status update email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Backward compatibility wrapper
 */
function sendVisaApprovalEmail($booking_id, $booking_data = null)
{
    return sendVisaStatusUpdateEmail($booking_id, 'approved', '', $booking_data);
}

/**
 * Send visa submission confirmation email to customer
 */
function sendVisaSubmissionConfirmationEmail($booking_id, $booking_data = null)
{
    global $pdo, $emailConfig;

    try {
        if (!$booking_data) {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $booking = $booking_data;
        }

        if (!$booking) {
            error_log("Visa booking not found for email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Visa Application Received - HeyDream Travel and Tours';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #003580, #1a4b8c); padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
                .content { padding: 40px 30px; color: #444; line-height: 1.6; }
                .status-badge { display: inline-block; background: #e8f4fd; color: #004085; padding: 10px 25px; border-radius: 50px; font-weight: 800; font-size: 14px; margin-bottom: 25px; border: 1px solid #b8daff; }
                .details-box { background: #f8f9fa; border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #eee; }
                .details-box h3 { margin-top: 0; color: #003580; font-size: 18px; }
                .row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                .label { width: 140px; font-weight: 700; color: #666; font-size: 14px; }
                .val { flex: 1; color: #333; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
                .btn { display: inline-block; background: #ff9800; color: white; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: 700; margin-top: 10px; }
                .action-step { background: #fff9e6; border-left: 5px solid #ff9f43; border-radius: 12px; padding: 20px; margin-top: 25px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HeyDream Visa Assistance</h1>
                    <p style='opacity: 0.9; margin-top: 5px;'>Travel Document Support</p>
                </div>
                <div class='content'>
                    <div style='text-align: center;'>
                        <div class='status-badge'>APPLICATION RECEIVED</div>
                    </div>
                    <p>Dear <strong>" . htmlspecialchars($booking['full_name']) . "</strong>,</p>
                    <p>Thank you for choosing HeyDream. Your visa application has been successfully submitted and is currently being reviewed by our assigned agent.</p>
                    
                    <div class='details-box'>
                        <h3>📋 Application Details</h3>
                        <div class='row'><div class='label'>Ref Number:</div><div class='val'><strong>" . htmlspecialchars($booking['booking_number']) . "</strong></div></div>
                        <div class='row'><div class='label'>Visa Type:</div><div class='val'>" . htmlspecialchars($booking['package_name']) . "</div></div>
                        <div class='row'><div class='label'>Processing:</div><div class='val'>" . htmlspecialchars($booking['package_duration']) . "</div></div>
                    </div>
                    
                    <div class='action-step'>
                        <h4 style='margin: 0 0 10px; color: #856404;'>What happens next?</h4>
                        <p style='margin: 0; font-size: 14px;'>Our agent will verify your application to ensure all provided information is complete. You will receive another email shortly with the result of this review.</p>
                    </div>
                    
                    <div style='text-align: center; margin-top: 35px;'>
                        <a href='" . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}' class='btn'>View My Bookings</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                    <p>Manila, Philippines | 📞 0945 776 4140</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "HeyDream Travel and Tours - Visa Application Received\n\nYour application (Ref: {$booking['booking_number']}) for {$booking['package_name']} has been received and is being reviewed. We will notify you once the review is finished.\n\n© " . date('Y') . " HeyDream Travel and Tours";

        $mail->send();
        error_log("Visa submission confirmation email sent to: {$booking['email']}");
        return true;

    } catch (Exception $e) {
        error_log("Visa submission confirmation email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send visa payment confirmation email to customer
 */
function sendVisaPaymentConfirmationEmail($booking_id, $booking_data = null)
{
    global $pdo, $emailConfig;

    try {
        if (!$booking_data) {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $booking = $booking_data;
        }

        if (!$booking) {
            error_log("Visa booking not found for payment email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmed - Visa Processing Started';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #10ac84, #1dd1a1); padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
                .content { padding: 40px 30px; color: #444; line-height: 1.6; }
                .status-badge { display: inline-block; background: #eafaf1; color: #1e8449; padding: 10px 25px; border-radius: 50px; font-weight: 800; font-size: 14px; margin-bottom: 25px; border: 1px solid #a9dfbf; }
                .details-box { background: #f8f9fa; border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #eee; }
                .details-box h3 { margin-top: 0; color: #003580; font-size: 18px; }
                .row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                .label { width: 140px; font-weight: 700; color: #666; font-size: 14px; }
                .val { flex: 1; color: #333; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
                .btn { display: inline-block; background: #6c5ce7; color: white; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: 700; margin-top: 10px; }
                .action-step { background: #f0f7ff; border-left: 5px solid #6c5ce7; border-radius: 12px; padding: 20px; margin-top: 25px; }
                .admin-note { background: #fffcf0; border-left: 5px solid #ff9800; padding: 15px 20px; margin: 25px 0; border-radius: 8px; font-size: 14px; line-height: 1.5; color: #856404; }
                .flight-details-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-top: 25px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HeyDream Visa Assistance</h1>
                    <p style='opacity: 0.9; margin-top: 5px;'>Payment Confirmed</p>
                </div>
                <div class='content'>
                    <div style='text-align: center;'>
                        <div class='status-badge'>PAYMENT RECEIVED ✅</div>
                    </div>
                    <p>Dear <strong>" . htmlspecialchars($booking['full_name']) . "</strong>,</p>
                    <p>We have successfully received your payment. Your visa application has now moved forward to the official processing stage.</p>
                    
                    <div class='details-box'>
                        <h3>📋 Application Details</h3>
                        <div class='row'><div class='label'>Ref Number:</div><div class='val'><strong>" . htmlspecialchars($booking['booking_number']) . "</strong></div></div>
                        <div class='row'><div class='label'>Visa Type:</div><div class='val'>" . htmlspecialchars($booking['package_name']) . "</div></div>
                        <div class='row'><div class='label'>Amount Paid:</div><div class='val'>₱" . number_format($booking['total_amount'], 2) . "</div></div>
                    </div>
                    
                    " . (!empty($booking['admin_notes']) ? "
                    <div class='admin-note'>
                        <strong style='color: #856404; display: block; margin-bottom: 5px;'>📝 Note from Admin:</strong>
                        " . nl2br(htmlspecialchars($booking['admin_notes'])) . "
                    </div>" : "") . "

                    " . (!empty($booking['flight_details']) ? "
                    <div class='flight-details-box'>
                        <strong style='color: #003580; display: block; margin-bottom: 10px; font-size: 16px;'><i class='fas fa-plane-departure'></i> Flight Information:</strong>
                        <div style='font-size: 14px; color: #334155; line-height: 1.6;'>
                            " . nl2br(htmlspecialchars($booking['flight_details'])) . "
                        </div>
                    </div>" : "") . "
                    
                    <div class='action-step'>
                        <h4 style='margin: 0 0 10px; color: #003580;'>Processing Started</h4>
                        <p style='margin: 0; font-size: 14px;'>Our team is now processing your documents with the embassy/consulate. We will keep you updated via email regarding your application status.</p>
                    </div>
                    
                    <div style='text-align: center; margin-top: 35px;'>
                        <a href='" . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}' class='btn'>Track Application</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                    <p>Manila, Philippines | 📞 0945 776 4140</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "HeyDream Travel and Tours - Payment Received\n\nYour payment for application (Ref: {$booking['booking_number']}) has been confirmed. Your visa is now in the official processing stage. We will keep you updated.\n\n© " . date('Y') . " HeyDream Travel and Tours";

        $mail->send();
        error_log("Visa payment confirmation email sent to: {$booking['email']}");
        return true;

    } catch (Exception $e) {
        error_log("Visa payment confirmation email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send tracking update email (Travel Documents Prepared, Ready for Travel)
 */
function sendTrackingUpdateEmail($booking_id, $step, $booking_data = null)
{
    global $pdo, $emailConfig;

    try {
        if (!$booking_data) {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $booking = $booking_data;
        }

        if (!$booking) {
            error_log("Booking not found for tracking email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        $mail->isHTML(true);

        $title = '';
        $message = '';
        $icon = '';
        $bg_color = '';

        if ($step === 'travel_documents') {
            $mail->Subject = 'Travel Documents Prepared - HeyDream Travel and Tours';
            $title = 'DOCUMENTS PREPARED';
            $message = 'Great news! Your travel documents have been successfully prepared and verified by our team. We are one step closer to getting you ready for your journey.';
            $icon = '📄';
            $bg_color = 'linear-gradient(135deg, #f39c12, #e67e22)';
        } else if ($step === 'ready_for_travel') {
            $mail->Subject = 'Ready for Travel! - HeyDream Travel and Tours';
            $title = 'READY FOR TRAVEL';
            $message = 'Congratulations! Everything is set and you are now officially ready for travel! Please ensure you have all your physical documents packed and review any final instructions carefully.';
            $icon = '✈️';
            $bg_color = 'linear-gradient(135deg, #2ecc71, #27ae60)';
        } else {
            return false;
        }

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
                .header { background: $bg_color; padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
                .content { padding: 40px 30px; color: #444; line-height: 1.6; }
                .status-badge { display: inline-block; background: #f8f9fa; color: #333; padding: 10px 25px; border-radius: 50px; font-weight: 800; font-size: 14px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 2px solid #eee; }
                .details-box { background: #f8f9fa; border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #eee; }
                .details-box h3 { margin-top: 0; color: #003580; font-size: 18px; }
                .row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                .label { width: 140px; font-weight: 700; color: #666; font-size: 14px; }
                .val { flex: 1; color: #333; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
                .btn { display: inline-block; background: #003580; color: white; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: 700; margin-top: 10px; }
                .admin-note { background: #fffcf0; border-left: 5px solid #ff9800; padding: 15px 20px; margin: 25px 0; border-radius: 8px; font-size: 14px; line-height: 1.5; color: #856404; }
                .flight-details-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-top: 25px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HeyDream Updates</h1>
                    <p style='opacity: 0.9; margin-top: 5px;'>Your Tracking Timeline Updates</p>
                </div>
                <div class='content'>
                    <div style='text-align: center;'>
                        <div class='status-badge'>$icon $title</div>
                    </div>
                    <p>Dear <strong>" . htmlspecialchars($booking['full_name']) . "</strong>,</p>
                    <p>$message</p>
                    
                    <div class='details-box'>
                        <h3>📋 Booking Details</h3>
                        <div class='row'><div class='label'>Ref Number:</div><div class='val'><strong>" . htmlspecialchars($booking['booking_number']) . "</strong></div></div>
                        <div class='row'><div class='label'>Package:</div><div class='val'>" . htmlspecialchars($booking['package_name']) . "</div></div>
                        <div class='row'><div class='label'>Destination:</div><div class='val'>" . htmlspecialchars($booking['destination_name']) . "</div></div>
                    </div>

                    " . (!empty($booking['admin_notes']) ? "
                    <div class='admin-note'>
                        <strong style='color: #856404; display: block; margin-bottom: 5px;'>📝 Note from Admin:</strong>
                        " . nl2br(htmlspecialchars($booking['admin_notes'])) . "
                    </div>" : "") . "

                    " . (!empty($booking['flight_details']) ? "
                    <div class='flight-details-box'>
                        <strong style='color: #003580; display: block; margin-bottom: 10px; font-size: 16px;'><i class='fas fa-plane-departure'></i> Flight Information:</strong>
                        <div style='font-size: 14px; color: #334155; line-height: 1.6;'>
                            " . nl2br(htmlspecialchars($booking['flight_details'])) . "
                        </div>
                    </div>" : "") . "
                    
                    <div style='text-align: center; margin-top: 35px;'>
                        <a href='" . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}' class='btn'>View My Tracking Timeline</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                    <p>Manila, Philippines | 📞 0945 776 4140</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "HeyDream Travel Tracking Update for {$booking['booking_number']}:\n\n$title\n\n$message\n\nView your timeline at " . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}\n\n© " . date('Y') . " HeyDream Travel and Tours";

        $mail->send();
        error_log("Tracking update email sent to: {$booking['email']} for step: $step");
        return true;

    } catch (Exception $e) {
        error_log("Tracking update email failed: " . $e->getMessage());
        return false;
    }
}


/**
 * Send flight reminder email to customer (1-2 days before)
 */
function sendFlightReminderEmail($booking_id, $booking_data = null)
{
    global $pdo, $emailConfig;

    try {
        if (!$booking_data) {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $booking = $booking_data;
        }

        if (!$booking) {
            error_log("Booking not found for reminder email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Upcoming Flight Reminder: ' . $booking['booking_number'] . ' - HeyDream Travel';

        $travel_date = date('F d, Y', strtotime($booking['travel_date']));
        $days_left = ceil((strtotime($booking['travel_date']) - time()) / 86400);

        if ($days_left <= 0) {
            $reminder_text = "Your flight is today!";
        } elseif ($days_left == 1) {
            $reminder_text = "Your flight is tomorrow!";
        } else {
            $reminder_text = "Your flight is just " . $days_left . " days away!";
        }

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #4f46e5, #6366f1); padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
                .content { padding: 40px 30px; color: #444; line-height: 1.6; }
                .status-badge { display: inline-block; background: #eef2ff; color: #4f46e5; padding: 10px 25px; border-radius: 50px; font-weight: 800; font-size: 14px; margin-bottom: 25px; border: 1px solid #c7d2fe; }
                .details-box { background: #f8f9fa; border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #4f46e5; }
                .details-box h3 { margin-top: 0; color: #1e293b; font-size: 18px; }
                .row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                .label { width: 140px; font-weight: 700; color: #64748b; font-size: 14px; }
                .val { flex: 1; color: #0f172a; font-size: 14px; font-weight: 600; }
                .footer { background: #f8fafc; padding: 30px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
                .btn { display: inline-block; background: #4f46e5; color: white; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: 700; margin-top: 10px; }
                .checklist { background: #fffbeb; border-radius: 12px; padding: 20px; margin-top: 25px; border: 1px solid #fde68a; }
                .checklist h4 { margin: 0 0 10px; color: #92400e; }
                .checklist ul { margin: 0; padding-left: 20px; font-size: 14px; color: #92400e; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HeyDream Flight Reminder</h1>
                    <p style='opacity: 0.9; margin-top: 5px;'>Preparing for your journey</p>
                </div>
                <div class='content'>
                    <div style='text-align: center;'>
                        <div class='status-badge'>✈️ $reminder_text</div>
                    </div>
                    <p>Dear <strong>" . htmlspecialchars($booking['full_name']) . "</strong>,</p>
                    <p>We're getting excited for your upcoming journey! This is a friendly reminder that your flight to <strong>" . htmlspecialchars($booking['destination_name']) . "</strong> is scheduled for <strong>$travel_date</strong>.</p>
                    
                    <div class='details-box'>
                        <h3>📋 Travel Summary</h3>
                        <div class='row'><div class='label'>Booking Number:</div><div class='val'>" . htmlspecialchars($booking['booking_number']) . "</div></div>
                        <div class='row'><div class='label'>Destination:</div><div class='val'>" . htmlspecialchars($booking['destination_name']) . "</div></div>
                        <div class='row'><div class='label'>Package:</div><div class='val'>" . htmlspecialchars($booking['package_name']) . "</div></div>
                        <div class='row'><div class='label'>Travel Date:</div><div class='val'>$travel_date</div></div>
                    </div>

                    <div class='checklist'>
                        <h4>🎒 Final Checklist:</h4>
                        <ul>
                            <li>Ensure your passport and travel documents are ready.</li>
                            <li>Check your flight status with the airline.</li>
                            <li>Review your itinerary and packing list.</li>
                            <li>Arrive at the airport at least 3-4 hours before your flight.</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center; margin-top: 35px;'>
                        <p>Need to review your booking or documents?</p>
                        <a href='" . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}' class='btn'>Go to My Dashboard</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                    <p>Manila, Philippines | 📞 0945 776 4140</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "HeyDream Flight Reminder: $reminder_text\n\n"
            . "Destination: {$booking['destination_name']}\n"
            . "Date: $travel_date\n"
            . "Booking Number: {$booking['booking_number']}\n\n"
            . "Please ensure you have all your documents ready. View your details at " . $GLOBALS['APP_BASE_URL'] . "/User%20Account/profile.php?booking_number={$booking['booking_number']}\n\n"
            . "Safe travels!\n© " . date('Y') . " HeyDream Travel and Tours";

        $mail->send();

        // Update database to mark reminder as sent
        $stmt = $pdo->prepare("UPDATE bookings SET reminder_sent = 1 WHERE id = ?");
        $stmt->execute([$booking_id]);

        error_log("Flight reminder email sent to: {$booking['email']} for booking: {$booking['booking_number']}");
        return true;
    } catch (Exception $e) {
        error_log("Flight reminder email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send inquiry confirmation email when admin marks inquiry as contacted
 */
function sendInquiryContactedEmail($booking_id)
{
    global $pdo, $emailConfig;

    try {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            error_log("Inquiry not found for contacted email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your Inquiry Has Been Received – HeyDream Travel and Tours';

        $safeName = htmlspecialchars($booking['full_name']);
        $bookingNumber = htmlspecialchars($booking['booking_number']);
        $currentYear = date('Y');

        // Format special_requests nicely
        $requestsHtml = '';
        if (!empty($booking['special_requests'])) {
            $lines = explode("\n", trim($booking['special_requests']));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;
                // Bold the label before the colon
                $line = preg_replace('/^([^:]+:)/', '<strong>$1</strong>', htmlspecialchars($line));
                $requestsHtml .= "<div style='padding: 6px 0; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #334155;'>$line</div>";
            }
        }

        $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Inquiry Confirmation – HeyDream Travel</title>
        </head>
        <body style='margin:0; padding:0; background-color:#f1f5f9; font-family: Arial, sans-serif;'>
            <div style='max-width:600px; margin:30px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.1);'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #003580, #0057b8); padding:40px 30px; text-align:center;'>
                    <h1 style='margin:0; color:#ffffff; font-size:26px; font-weight:800; letter-spacing:-0.5px;'>HeyDream Travel and Tours</h1>
                    <p style='margin:8px 0 0; color:#FFF3B0; font-size:14px; font-weight:600; letter-spacing:1px; text-transform:uppercase;'>Your Dream Vacation Starts Here</p>
                </div>

                <!-- Body -->
                <div style='padding:40px 30px;'>
                    <div style='text-align:center; margin-bottom:30px;'>
                        <div style='display:inline-block; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:10px 28px; border-radius:50px; font-weight:700; font-size:15px;'>
                            ✅ Inquiry Reviewed!
                        </div>
                    </div>

                    <p style='color:#1e293b; font-size:18px; font-weight:700; margin:0 0 8px;'>Hello, {$safeName}! 👋</p>
                    <p style='color:#475569; font-size:14px; line-height:1.7; margin:0 0 25px;'>
                        Thank you for reaching out to us! We have received your travel inquiry and one of our travel agents will be in touch with you very soon to discuss the best options tailored just for you.
                    </p>

                    <!-- Inquiry Summary -->
                    <div style='background:#f8fafc; border-radius:12px; padding:20px 24px; border-left:4px solid #003580; margin-bottom:25px;'>
                        <h3 style='margin:0 0 15px; color:#003580; font-size:15px;'>📋 Your Inquiry Summary</h3>
                        <div style='font-size:14px; color:#64748b; margin-bottom:8px;'><strong style='color:#334155;'>Reference No.:</strong> {$bookingNumber}</div>
                        " . ($requestsHtml ?: "<div style='font-size:14px; color:#64748b;'>No additional details provided.</div>") . "
                    </div>

                    <!-- Contact Info -->
                    <div style='background:#eff6ff; border-radius:12px; padding:20px 24px; margin-bottom:25px;'>
                        <h3 style='margin:0 0 15px; color:#1d4ed8; font-size:15px;'>📞 Reach Us Directly</h3>
                        <table style='width:100%; border-collapse:collapse;'>
                            <tr>
                                <td style='padding:6px 0; font-size:14px; color:#334155; width:30px;'>📱</td>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'><strong>Phone / GCash:</strong> 0945 776 4140</td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'>✉️</td>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'><strong>Email:</strong> <a href='mailto:heydreamtravelandtours@gmail.com' style='color:#003580; text-decoration:none;'>heydreamtravelandtours@gmail.com</a></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Social Media -->
                    <div style='background:#f8fafc; border-radius:12px; padding:20px 24px; margin-bottom:30px;'>
                        <h3 style='margin:0 0 15px; color:#334155; font-size:15px;'>🌐 Follow Us</h3>
                        <table style='width:100%; border-collapse:collapse;'>
                            <tr>
                                <td style='padding:6px 0; font-size:14px; color:#334155; width:30px;'>📘</td>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'><strong>Facebook:</strong> <a href='https://www.facebook.com/heydreamtravelandtours' style='color:#003580; text-decoration:none;'>HeyDream Travel and Tours</a></td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'>📸</td>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'><strong>Instagram:</strong> <a href='https://www.instagram.com/heydreamtravelandtours' style='color:#003580; text-decoration:none;'>@heydreamtravelandtours</a></td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'>🌍</td>
                                <td style='padding:6px 0; font-size:14px; color:#334155;'><strong>Website:</strong> <a href='https://heydreamtravel.kesug.com/' style='color:#003580; text-decoration:none;'>heydreamtravel.kesug.com</a></td>
                            </tr>
                        </table>
                    </div>

                    <p style='color:#94a3b8; font-size:13px; text-align:center; margin:0;'>
                        We aim to reply within <strong>24 hours</strong> on business days. We look forward to planning your dream trip! ✈️
                    </p>
                </div>

                <!-- Footer -->
                <div style='background:#f8fafc; padding:24px 30px; text-align:center; border-top:1px solid #e2e8f0;'>
                    <p style='margin:0 0 6px; color:#94a3b8; font-size:12px;'>© {$currentYear} HeyDream Travel and Tours. All rights reserved.</p>
                    <p style='margin:0; color:#94a3b8; font-size:12px;'>📞 0945 776 4140 &nbsp;|&nbsp; ✉️ heydreamtravelandtours@gmail.com</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "Hello {$safeName},\n\nThank you for your inquiry! We have received it (Ref: {$bookingNumber}) and will be in touch shortly.\n\nContact Us:\nPhone: 0945 776 4140\nEmail: heydreamtravelandtours@gmail.com\nFacebook: facebook.com/heydreamtravelandtours\nWebsite: heydreamtravel.kesug.com\n\n© {$currentYear} HeyDream Travel and Tours";

        $mail->send();
        error_log("Inquiry contacted email sent to: {$booking['email']}");
        return true;

    } catch (Exception $e) {
        error_log("Inquiry contacted email failed: " . $e->getMessage());
        return false;
    }
}


/**
 * Send inquiry cancellation email when admin marks inquiry as cancelled
 */
function sendInquiryCancelledEmail($booking_id)
{
    global $pdo, $emailConfig;

    try {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Update Regarding Your Travel Inquiry – HeyDream Travel';

        $safeName = htmlspecialchars($booking['full_name']);
        $bookingNumber = htmlspecialchars($booking['booking_number']);
        $currentYear = date('Y');

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <body style='margin:0; padding:0; background-color:#f8fafc; font-family: Arial, sans-serif;'>
            <div style='max-width:600px; margin:30px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05);'>
                <div style='background: #64748b; padding:30px; text-align:center;'>
                    <h1 style='margin:0; color:#ffffff; font-size:22px;'>HeyDream Travel</h1>
                </div>
                <div style='padding:40px 30px;'>
                    <p style='color:#1e293b; font-size:18px; font-weight:700;'>Hello, {$safeName}!</p>
                    <p style='color:#475569; font-size:14px; line-height:1.7;'>
                        We are writing to inform you that your travel inquiry (Ref: <strong>{$bookingNumber}</strong>) has been cancelled and will not be processed at this time.
                    </p>
                    <p style='color:#475569; font-size:14px; line-height:1.7;'>
                        If you have any questions or would like to submit a new inquiry in the future, we would be happy to assist you then.
                    </p>
                    <div style='margin-top:30px; padding-top:20px; border-top:1px solid #e2e8f0; text-align:center;'>
                        <p style='color:#94a3b8; font-size:13px;'>Thank you for your interest in HeyDream Travel and Tours.</p>
                    </div>
                </div>
                <div style='background:#f8fafc; padding:20px; text-align:center; color:#94a3b8; font-size:12px;'>
                    © {$currentYear} HeyDream Travel and Tours. All rights reserved.
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "Hello {$safeName},\n\nYour travel inquiry (Ref: {$bookingNumber}) has been cancelled and will not be processed at this time.\n\nThank you for your interest,\nHeyDream Travel and Tours";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Inquiry cancelled email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send custom message email to customer from admin dashboard
 */
function sendCustomCustomerEmail($booking_id, $custom_message)
{
    global $pdo, $emailConfig;

    try {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking || empty($booking['email'])) {
            error_log("Booking or email not found for custom email: ID $booking_id");
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($booking['email'], $booking['full_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Message regarding your booking #' . $booking['booking_number'] . ' - HeyDream Travel';

        $safeName = htmlspecialchars($booking['full_name']);
        $bookingNumber = htmlspecialchars($booking['booking_number']);
        $customMessageHtml = nl2br(htmlspecialchars($custom_message));
        $currentYear = date('Y');

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #003580, #1a4b8c); padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
                .content { padding: 40px 30px; color: #444; line-height: 1.6; }
                .message-box { background: #f8fafc; border-radius: 15px; padding: 25px; margin: 25px 0; border-left: 5px solid #0284c7; font-size: 15px; color: #1e293b; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HeyDream Travel and Tours</h1>
                    <p style='opacity: 0.9; margin-top: 5px;'>Message from your Travel Agent</p>
                </div>
                <div class='content'>
                    <p>Dear <strong>{$safeName}</strong>,</p>
                    <p>We are reaching out regarding your booking (Ref: <strong>{$bookingNumber}</strong>).</p>
                    
                    <div class='message-box'>
                        {$customMessageHtml}
                    </div>
                    
                    <p>If you have any questions or need further assistance, please reply directly to this email or reach us at our contact numbers below.</p>
                    
                    <div style='margin-top: 35px; text-align: center;'>
                        <p style='margin: 0; color: #003580; font-weight: 600;'>✈️ We look forward to making your dream trip come true!</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>© {$currentYear} HeyDream Travel and Tours. All rights reserved.</p>
                    <p>Manila, Philippines | 📞 0945 776 4140 | ✉️ heydreamtravelandtours@gmail.com</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "Dear {$safeName},\n\nWe are reaching out regarding your booking (Ref: {$bookingNumber}).\n\n{$custom_message}\n\nIf you have any questions, please reply directly to this email.\n\n© {$currentYear} HeyDream Travel and Tours";

        $mail->send();
        error_log("Custom customer email sent to: {$booking['email']}");
        return true;

    } catch (Exception $e) {
        error_log("Custom customer email failed: " . $e->getMessage());
        return false;
    }
}

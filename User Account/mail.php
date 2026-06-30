<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendResetEmail($email, $resetLink, $userName) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com'; // Your email
        $mail->Password   = 'your-app-password'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('heydreamtravelandtours@gmail.com', 'HeyDream Travel and Tours');
        $mail->addAddress($email, $userName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your HeyDream Password';
        $mail->Body = "
            <h2>Password Reset Request</h2>
            <p>Hello $userName,</p>
            <p>You requested to reset your password. Click the button below to create a new password:</p>
            <p><a href='$resetLink' style='display: inline-block; background: #ff9800; color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px;'>Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, please ignore this email.</p>
            <br>
            <p>Best regards,<br>HeyDream Travel and Tours Team</p>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

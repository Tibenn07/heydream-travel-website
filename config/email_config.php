<?php
// File: config/email_config.php

// Email configuration
$emailConfig = [
    'host' => 'smtp.gmail.com',
    'username' => 'heydreamtravelandtours@gmail.com', // Your Gmail address
    'password' => 'lflzaihbsrmuihyk', // Your App Password (the one you have)
    'port' => 587,
    'from_email' => 'heydreamtravelandtours@gmail.com',
    'from_name' => 'HeyDream Travel and Tours'
];

// Include PHPMailer files
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendPasswordResetEmail($toEmail, $toName, $resetLink)
{
    global $emailConfig;

    $mail = new PHPMailer(true);

    try {
        // Enable verbose debug output (remove after testing)
        $mail->SMTPDebug = 2; // Set to 2 for detailed output, 0 for production
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer Debug: $str");
        };

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
        $mail->addReplyTo($emailConfig['username'], $emailConfig['from_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your HeyDream Admin Password';
        $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <meta name='color-scheme' content='light dark'>
                <meta name='supported-color-schemes' content='light dark'>
                <title>Password Reset - HeyDream</title>
                <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap' rel='stylesheet'>
                <style>
                    /* Base styles */
                    body {
                        font-family: 'Inter', Arial, sans-serif;
                        background-color: #f4f7f6;
                        margin: 0;
                        padding: 0;
                        color: #333333;
                    }
                    .wrapper {
                        width: 100%;
                        table-layout: fixed;
                        background-color: #f4f7f6;
                        padding: 40px 0;
                    }
                    .main-container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 12px;
                        overflow: hidden;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
                    }
                    .header {
                        background-color: #0077B6;
                        padding: 40px 30px;
                        text-align: center;
                    }
                    .header h1 {
                        margin: 0;
                        color: #ffffff;
                        font-size: 28px;
                        font-weight: 800;
                        letter-spacing: -0.5px;
                    }
                    .header p {
                        margin: 5px 0 0;
                        color: #FFF3B0;
                        font-size: 16px;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    }
                    .content {
                        padding: 40px 30px;
                        background-color: #ffffff;
                    }
                    .content h2 {
                        margin: 0 0 20px;
                        color: #1a1a1a;
                        font-size: 20px;
                    }
                    .content p {
                        margin: 0 0 20px;
                        color: #4a4a4a;
                        line-height: 1.6;
                        font-size: 16px;
                    }
                    .button-wrapper {
                        text-align: center;
                        margin: 35px 0;
                    }
                    .button {
                        display: inline-block;
                        background-color: #0077B6;
                        color: #ffffff !important;
                        padding: 14px 32px;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 16px;
                        transition: background-color 0.3s;
                        border: 2px solid #0077B6;
                    }
                    .button:hover {
                        background-color: #005f92;
                        border-color: #005f92;
                    }
                    .divider {
                        border-top: 1px solid #eeeeee;
                        margin: 30px 0;
                    }
                    .sub-text {
                        font-size: 13px;
                        color: #888888;
                        margin-bottom: 0;
                    }
                    .footer {
                        background-color: #FFF3B0;
                        padding: 30px;
                        text-align: center;
                        border-top: 1px solid #f0e4a0;
                    }
                    .footer p {
                        margin: 5px 0;
                        color: #0077B6;
                        font-size: 14px;
                        font-weight: 600;
                    }
                    .footer .contact {
                        color: #555555;
                        font-size: 13px;
                        font-weight: 500;
                        margin-top: 15px;
                    }
                    .footer a {
                        color: #0077B6;
                        text-decoration: none;
                        font-weight: 600;
                    }
            
                    /* Dark mode overrides */
                    @media (prefers-color-scheme: dark) {
                        body, .wrapper {
                            background-color: #121212 !important;
                        }
                        .main-container {
                            background-color: #1e1e1e !important;
                            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
                        }
                        .content {
                            background-color: #1e1e1e !important;
                        }
                        .content h2 {
                            color: #ffffff !important;
                        }
                        .content p {
                            color: #cccccc !important;
                        }
                        .divider {
                            border-top-color: #333333 !important;
                        }
                        .sub-text {
                            color: #999999 !important;
                        }
                        .footer {
                            background-color: #2a2818 !important; /* Muted yellow/brown for dark mode */
                            border-top-color: #3d3a24 !important;
                        }
                        .footer p, .footer a {
                            color: #66b3ff !important; /* Lighter ocean blue for contrast */
                        }
                        .header p {
                            color: #FFF3B0 !important;
                        }
                        .footer .contact {
                             color: #dddddd !important;
                        }
                    }
                </style>
            </head>
            <body>
                <div class='wrapper'>
                    <div class='main-container'>
                        <div class='header'>
                            <h1>HeyDream Travel and Tours</h1>
                            <p>Admin Portal</p>
                        </div>
                        <div class='content'>
                            <h2>Hello " . htmlspecialchars($toName) . ",</h2>
                            <p>We received a request to reset the password for your administrator account.</p>
                            <div class='button-wrapper'>
                                <a href='" . $resetLink . "' class='button'>Reset Password</a>
                            </div>
                            <p>This secure link will expire in 1 hour.</p>
                            <p>If you did not request a password reset, please ignore this email.</p>
                            <div class='divider'></div>
                            <p class='sub-text'>For security purposes, this password reset link can only be used once.</p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " HeyDream Travel and Tours.</p>
                            <p>All rights reserved.</p>
                            <div class='contact'>
                                📞 0945 776 4140 &nbsp;|&nbsp; ✉️ <a href='mailto:heydreamtravelandtours@gmail.com'>heydreamtravelandtours@gmail.com</a>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "Hello " . $toName . ",\n\n"
            . "We received a request to reset your admin account password.\n\n"
            . "Click this link to reset your password: " . $resetLink . "\n\n"
            . "This link will expire in 1 hour.\n\n"
            . "If you didn't request this, please ignore this email.\n\n"
            . "© " . date('Y') . " HeyDream Travel & Tours";

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>

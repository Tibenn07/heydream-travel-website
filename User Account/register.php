<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_functions.php';

$error = '';
$success = false;
$registered_name = '';
$registered_email = '';

// Check if user is already logged in
if (isset($auth) && $auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        // Process registration
        $result = $auth->register($full_name, $email, $password, $phone);

        if ($result['success']) {
            $success = true;
            $registered_name = $full_name;
            $registered_email = $email;

            // Send welcome email with verification token
            sendWelcomeEmail($email, $full_name, $result['token']);
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - Register</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 20px 60px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .auth-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 480px;
            width: 100%;
            padding: 40px;
            animation: fadeInUp 0.5s ease;
            position: relative;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header h1 {
            color: #003580;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .auth-header p {
            color: #666;
        }

        .social-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 25px;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            text-decoration: none;
            color: #333;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .social-btn.google:hover {
            border-color: #DB4437;
            color: #DB4437;
        }

        .social-btn.facebook:hover {
            border-color: #4267B2;
            color: #4267B2;
        }

        .social-btn.apple:hover {
            border-color: #000;
            color: #000;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #999;
            margin: 20px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ddd;
        }

        .divider span {
            margin: 0 10px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #003580;
            box-shadow: 0 0 0 3px rgba(0, 53, 128, 0.1);
        }

        .register-btn {
            width: 100%;
            background: #003580;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .register-btn:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .auth-footer a {
            color: #003580;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            color: #ff9800;
        }

        .error-message {
            background: #fdf2f2;
            color: #9b1c1c;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #f05252;
            font-size: 14px;
            animation: shake 0.4s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-align: center;
            width: 100%;
            color: #666;
            text-decoration: none;
        }

        .back-link:hover {
            color: #003580;
        }

        /* Success Alert Modal */
        .success-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .success-overlay.active {
            display: flex;
            opacity: 1;
        }

        .success-modal {
            background: white;
            border-radius: 30px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            transform: scale(0.8) translateY(20px);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .success-overlay.active .success-modal {
            transform: scale(1) translateY(0);
        }

        .success-icon-wrapper {
            width: 80px;
            height: 80px;
            background: #def7ec;
            color: #03543f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            animation: iconBlink 2s infinite ease-in-out;
        }

        @keyframes iconBlink {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.2);
            }

            50% {
                transform: scale(1.1);
                box-shadow: 0 0 20px 10px rgba(40, 167, 69, 0.1);
            }
        }

        .success-modal h2 {
            color: #03543f;
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .success-modal p {
            color: #4b5563;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .redirect-timer {
            font-size: 14px;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .timer-bar {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 10px;
            margin-top: 15px;
            overflow: hidden;
        }

        .timer-progress {
            height: 100%;
            background: linear-gradient(90deg, #31c48d 0%, #057a55 100%);
            width: 100%;
            transition: width 3s linear;
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo" style="height: 37px; width: auto;"
                onclick="window.location.href='../index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Join HeyDream and start your journey</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>


            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name <span style="color: #ff4444;">*</span></label>
                    <input type="text" name="full_name" required placeholder="Enter your full name"
                        value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Email Address <span style="color: #ff4444;">*</span></label>
                    <input type="email" name="email" required placeholder="your@email.com"
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="+63 912 345 6789"
                        value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Password <span style="color: #ff4444;">*</span></label>
                    <input type="password" name="password" required placeholder="Minimum 6 characters">
                </div>

                <div class="form-group">
                    <label>Confirm Password <span style="color: #ff4444;">*</span></label>
                    <input type="password" name="confirm_password" required placeholder="Confirm your password">
                </div>

                <button type="submit" class="register-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
            </div>

            <a href="../index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- Success Modal Overlay -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-modal">
            <div class="success-icon-wrapper">
                <i class="fas fa-check"></i>
            </div>
            <h2>Verify Your Email! 📧</h2>
            <p>Welcome to HeyDream, <strong><?= htmlspecialchars($registered_name) ?></strong>! We've sent a
                verification link to <strong><?= htmlspecialchars($registered_email) ?></strong>.</p>
            <p style="font-size: 14px; margin-top: 10px;">Please check your inbox (and spam folder) and click the link
                to activate your account.</p>
            <div class="redirect-timer" style="margin-top: 20px;">
                <i class="fas fa-check-circle"></i> Awaiting verification...
            </div>
            <div style="margin-top: 20px;">
                <a href="../index.php" class="back-link" style="margin-top: 0; color: #003580; font-weight: 600;">Back
                    to Home</a>
            </div>
            <div class="timer-bar">
                <div class="timer-progress" id="timerProgress"></div>
            </div>
        </div>
    </div>

    <script>
        function socialLogin(provider) {
            alert('Social login with ' + provider + ' would integrate with OAuth API');
        }

        // Show success modal if registration was successful
        <?php if ($success): ?>
            window.addEventListener('load', function () {
                const overlay = document.getElementById('successOverlay');
                overlay.classList.add('active');

                // Progress bar (optional for the "Check Email" state)
                setTimeout(() => {
                    document.getElementById('timerProgress').style.width = '100%';
                }, 100);
            });
        <?php endif; ?>
    </script>
</body>

</html>
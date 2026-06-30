<?php
require_once __DIR__ . '/../config/database.php';

$success = false;
$error = '';
$user_name = '';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid verification link. No token provided.';
} else {
    $result = $auth->verifyEmail($token);

    if ($result['success']) {
        $success = true;
        $user_name = $result['user']['full_name'];
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - Email Verification</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .verification-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            text-align: center;
        }

        .error-card {
            background: white;
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 480px;
            width: 100%;
        }

        .error-icon {
            font-size: 60px;
            color: #ef4444;
            margin-bottom: 20px;
        }

        .error-card h1 {
            color: #1f2937;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .error-card p {
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn-retry {
            display: inline-block;
            background: #003580;
            color: white;
            padding: 12px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-retry:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }

        /* Success Alert Modal - Reusing the premium design */
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
    <div class="verification-container">
        <?php if ($error): ?>
            <div class="error-card">
                <div class="error-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h1>Verification Failed</h1>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="register.php" class="btn-retry">Try Registering Again</a>
                <br><br>
                <a href="../index.php" style="color: #6b7280; text-decoration: none; font-size: 14px;">Back to Home</a>
            </div>
        <?php else: ?>
            <div class="processing-card">
                <i class="fas fa-spinner fa-spin" style="font-size: 40px; color: #003580;"></i>
                <p style="margin-top: 20px;">Verifying your account...</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Success Modal Overlay (Triggers on verification success) -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-modal">
            <div class="success-icon-wrapper">
                <i class="fas fa-check"></i>
            </div>
            <h2>Account Approved! 🎉</h2>
            <p>Congratulations, <strong><?= htmlspecialchars($user_name) ?></strong>! Your account has been verified
                successfully. You can now start booking your dream vacations.</p>
            <div class="redirect-timer">
                <i class="fas fa-spinner fa-spin"></i> Taking you home in 3s...
            </div>
            <div class="timer-bar">
                <div class="timer-progress" id="timerProgress"></div>
            </div>
        </div>
    </div>

    <script>
        <?php if ($success): ?>
            window.addEventListener('load', function () {
                const overlay = document.getElementById('successOverlay');
                overlay.classList.add('active');

                // Animate progress bar (starts at 100% then goes to 0% in CSS, oh wait, my CSS was width 100%)
                // Let's do it like register.php
                setTimeout(() => {
                    document.getElementById('timerProgress').style.width = '0%';
                }, 100);

                // Redirect after 3 seconds to complete profile
                setTimeout(() => {
                    window.location.href = 'complete-profile.php';
                }, 3000);
            });
        <?php endif; ?>
    </script>
</body>

</html>
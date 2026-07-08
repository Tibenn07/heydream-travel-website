<?php
require_once __DIR__ . '/../../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, company_name, contact_person, email, password, status, rejection_reason, is_banned, ban_until FROM partner_applications WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $partner = $stmt->fetch();

        if (!$partner) {
            $error = 'No partner account found with that email address.';
        } elseif ($partner['status'] === 'pending') {
            $error = 'Your application is still pending review. Please check back later.';
        } elseif ($partner['status'] === 'rejected') {
            $error = 'Your application was rejected. Reason: ' . htmlspecialchars($partner['rejection_reason'] ?: 'No reason provided.');
        } elseif ($partner['is_banned']) {
            if ($partner['ban_until']) {
                $banUntil = new DateTime($partner['ban_until']);
                $now = new DateTime();
                if ($banUntil > $now) {
                    $diff = $now->diff($banUntil);
                    $timeLeft = '';
                    if ($diff->days > 0) $timeLeft .= $diff->days . ' day(s) ';
                    if ($diff->h > 0) $timeLeft .= $diff->h . ' hour(s) ';
                    if ($diff->days === 0 && $diff->h === 0) $timeLeft = 'less than an hour';
                    $error = '⛔ Your account is temporarily banned. Time remaining: ' . trim($timeLeft) . '. Ban lifts on: ' . $banUntil->format('F j, Y g:i A');
                } else {
                    // Ban expired — auto-unban
                    $pdo->prepare("UPDATE partner_applications SET is_banned = 0, ban_until = NULL WHERE id = ?")->execute([$partner['id']]);
                    $_SESSION['partner_id'] = $partner['id'];
                    $_SESSION['partner_company'] = $partner['company_name'];
                    $_SESSION['partner_contact'] = $partner['contact_person'];
                    header('Location: partner-dashboard.php');
                    exit;
                }
            } else {
                $error = '🚫 Your account has been permanently banned. Please contact HeyDream support for assistance.';
            }
        } elseif (!password_verify($password, $partner['password'])) {
            $error = 'Invalid password. Please try again.';
        } else {
            $_SESSION['partner_id'] = $partner['id'];
            $_SESSION['partner_company'] = $partner['company_name'];
            $_SESSION['partner_contact'] = $partner['contact_person'];
            header('Location: partner-dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Login - HeyDream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Caveat:wght@600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
            background:
                linear-gradient(rgba(8, 27, 51, 0.32), rgba(8, 27, 51, 0.32)),
                url('../../images/palawan 3.jpg') center/cover no-repeat fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 6vw;
            position: relative;
            overflow-x: hidden;
        }

        .plane-trail {
            position: fixed;
            top: 8%;
            left: -5%;
            font-size: 1.6rem;
            color: #2563eb;
            opacity: 0.55;
            transform: rotate(20deg);
            pointer-events: none;
        }

        .plane-trail::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 220px;
            height: 1px;
            border-top: 2px dashed rgba(37, 99, 235, 0.35);
        }

        .auth-container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 28px;
            padding: 40px 40px 32px;
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.35);
            position: relative;
            z-index: 2;
        }

        .auth-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 14px;
        }

        .auth-logo img {
            height: 64px;
            width: auto;
            object-fit: contain;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 22px;
        }

        .auth-header h1 {
            font-size: 1.9rem;
            font-weight: 700;
            color: #0f3d63;
            margin: 0 0 8px;
        }

        .auth-header p {
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.6;
            margin: 0;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0 22px;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .auth-divider span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            margin: 0 12px;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #1f2937;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i.field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .input-wrapper input {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 14px;
            padding: 13px 16px 13px 42px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            background: #fff;
        }

        .input-wrapper .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.95rem;
            padding: 0;
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            font-size: 0.87rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            cursor: pointer;
        }

        .remember-me input {
            width: auto;
            cursor: pointer;
        }

        .forgot-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: white;
            font-weight: 700;
            font-size: 0.98rem;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(37, 99, 235, 0.28);
        }

        .message-box {
            border-radius: 16px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .message-box.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .footer-link {
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }

        .footer-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            body {
                justify-content: center;
                padding: 30px 16px;
            }

            .auth-container {
                margin-right: 0;
            }

            .plane-trail {
                display: none;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .auth-container {
                max-width: 340px;
                padding: 26px 22px 22px;
                border-radius: 20px;
            }

            .auth-logo img {
                height: 44px;
            }

            .auth-header {
                margin-bottom: 14px;
            }

            .auth-header h1 {
                font-size: 1.4rem;
                margin-bottom: 6px;
            }

            .auth-header p {
                font-size: 0.8rem;
            }

            .auth-divider {
                margin: 14px 0 16px;
            }

            .auth-divider span {
                width: 28px;
                height: 28px;
                font-size: 0.78rem;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-group label {
                font-size: 0.82rem;
                margin-bottom: 6px;
            }

            .input-wrapper input {
                padding: 11px 14px 11px 38px;
                font-size: 0.85rem;
                border-radius: 12px;
            }

            .input-wrapper i.field-icon {
                left: 14px;
                font-size: 0.85rem;
            }

            .input-wrapper .toggle-password {
                right: 14px;
                font-size: 0.85rem;
            }

            .form-options {
                margin-bottom: 16px;
                font-size: 0.78rem;
            }

            .submit-btn {
                padding: 12px;
                font-size: 0.88rem;
            }

            .footer-link {
                margin-top: 14px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <i class="fas fa-plane plane-trail"></i>

    <div class="auth-container">
        <div class="auth-logo">
            <img src="../../images/Heydream Logo.png" alt="HeyDream Logo">
        </div>

        <div class="auth-header">
            <h1>Partner Login</h1>
            <p>Access the HeyDream partner portal after your application has been approved.</p>
        </div>

        <div class="auth-divider"><span><i class="fas fa-lock"></i></span></div>

        <?php if (!empty($error)): ?>
            <div class="message-box error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope field-icon"></i>
                    <input type="email" id="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()"><i class="fas fa-eye" id="toggleIcon"></i></button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="submit-btn"><i class="fas fa-plane"></i> Log In</button>
        </form>

        <div class="footer-link">
            Not a partner yet? <a href="partner-register.php">Apply for partnership &rarr;</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>

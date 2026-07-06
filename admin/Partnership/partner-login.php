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
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(180deg, #f7fbff 0%, #f0f7ff 100%);
            color: #1f2937;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
        }

        .auth-container {
            max-width: 520px;
            margin: 0 auto;
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.09);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .auth-header h1 {
            font-size: 2.4rem;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .auth-header p {
            color: #475569;
            line-height: 1.7;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 0.95rem;
        }

        .submit-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px;
            border-radius: 999px;
            border: none;
            background: #0f172a;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .message-box {
            border-radius: 20px;
            padding: 18px 20px;
            margin-bottom: 24px;
            font-size: 0.95rem;
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
            font-size: 0.95rem;
        }

        .footer-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Partner Login</h1>
            <p>Access the HeyDream partner portal after your application has been approved.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="message-box error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="submit-btn"><i class="fas fa-sign-in-alt"></i> Log In</button>
        </form>

        <div class="footer-link">
            Not a partner yet? <a href="partner-register.php">Apply for partnership</a>
        </div>
    </div>
</body>

</html>

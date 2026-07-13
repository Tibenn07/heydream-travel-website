<?php
// admin/register.php - Redesigned with Blue and Yellow Theme
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'admin';
        
        $errors = [];
        if (empty($username)) $errors[] = 'Username is required';
        if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must include uppercase, lowercase, number, and special character.';
        }
        if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Check for ACTUAL active accounts in admin_users
                $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = 'An active account already exists with this username or email.';
                    $pdo->rollBack();
                } else {
                    // 2. Check for PENDING requests
                    $stmt = $pdo->prepare("SELECT id FROM admin_registration_requests WHERE (username = ? OR email = ?) AND status = 'pending'");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        $error = 'A pending request already exists for this account.';
                        $pdo->rollBack();
                    } else {
                        // 3. CLEANUP: Clear any ghost accounts (is_active=0) and old requests (approved/rejected)
                        // This allows a person to re-register after being deleted or approved.
                        $pdo->prepare("DELETE FROM admin_users WHERE (username = ? OR email = ?) AND (is_active = 0 OR is_active IS NULL)")->execute([$username, $email]);
                        $pdo->prepare("DELETE FROM admin_registration_requests WHERE (username = ? OR email = ?) AND status IN ('approved', 'rejected')")->execute([$username, $email]);
                        
                        // 4. Proceed with new registration request
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $requestToken = bin2hex(random_bytes(32));
                        
                        $stmt = $pdo->prepare("INSERT INTO admin_registration_requests (username, email, password, full_name, role, request_token) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $hashedPassword, $full_name, $role, $requestToken]);
                        
                        $pdo->commit();
                        $success = 'Your request has been submitted to the Super Admin for approval.';
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to submit request: ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Registration - HeyDream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #003580;
            --accent-color: #ffd700;
            --accent-dark: #ccac00;
            --glass-bg: rgba(224, 242, 254, 0.4); /* Lighter Sky Blue Tint */
            --glass-border: rgba(255, 255, 255, 0.5);
            --glass-shadow: 0 25px 50px -12px rgba(0, 53, 128, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: url('../images/philippines-samal-island-resort-beach-aerial.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 40px 20px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 53, 128, 0.8), rgba(0, 53, 128, 0.4));
            z-index: 1;
        }
        
        .bg-glow {
            position: absolute;
            width: 500px;
            height: 500px;
            background: var(--accent-color);
            filter: blur(150px);
            opacity: 0.1;
            bottom: -150px;
            left: -150px;
            border-radius: 50%;
            z-index: 0;
            animation: float 20s infinite alternate;
        }

        @keyframes float {
            from { transform: translate(0, 0); }
            to { transform: translate(100px, -100px); }
        }
        
        .register-container {
            width: 100%;
            max-width: 550px;
            position: relative;
            z-index: 10;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 40px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: cardEntrance 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        /* Boarding Pass Motif */
        .register-card::after {
            content: '';
            position: absolute;
            top: 155px; /* Between header and body */
            left: -15px;
            right: -15px;
            height: 30px;
            background-image: radial-gradient(circle at 15px 15px, transparent 15px, rgba(255, 215, 0, 0.1) 0);
            background-size: 30px 40px;
            background-position: top;
            background-repeat: repeat-x;
            opacity: 0.3;
            pointer-events: none;
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(40px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .register-header {
            padding: 40px 40px 20px;
            text-align: center;
        }
        
        /* Travel Themed Elements */
        .airplane {
            position: absolute;
            color: rgba(255, 255, 255, 0.4);
            font-size: 2.5rem;
            z-index: 2;
            pointer-events: none;
            animation: cruise 60s linear infinite;
        }

        @keyframes cruise {
            0% { left: -100px; top: 10%; transform: rotate(15deg); opacity: 0; }
            5% { opacity: 1; }
            95% { opacity: 1; }
            100% { left: 110%; top: 30%; transform: rotate(15deg); opacity: 0; }
        }

        .logo-container {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%; /* Circular Logo */
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.2);
            animation: floatLogo 4s ease-in-out infinite; /* Floating Animation */
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            border: 4px solid white;
        }

        @keyframes floatLogo {
            0%, 100% { transform: translateY(0) rotate(-3deg); }
            50% { transform: translateY(-15px) rotate(3deg); }
        }

        .logo-container::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 215, 0, 0.2), transparent);
            animation: shine 4s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            20%, 100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        @keyframes bounceIn {
            0% { transform: scale(0) rotate(-45deg); }
            60% { transform: scale(1.1) rotate(5deg); }
            100% { transform: scale(1) rotate(-3deg); }
        }
        
        .logo-container img { width: 70%; position: relative; z-index: 1; }
        
        .register-header h1 {
            font-size: 2.2rem;
            color: white;
            margin-bottom: 8px;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        
        .register-header p {
            color: var(--accent-color);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .register-body { padding: 0 40px 40px; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group { margin-bottom: 18px; }
        .form-group.full-width { grid-column: 1 / span 2; }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            font-size: 0.9rem;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Poppins', sans-serif;
            outline: none;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: var(--accent-color);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
            transform: translateY(-2px);
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        /* Hide native Edge/IE password reveal button */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }

        .form-group select option {
            background: #1e3a8a;
            color: white;
        }
        
        .register-btn {
            width: 100%;
            background: var(--accent-color);
            color: var(--primary-color);
            border: none;
            padding: 18px;
            border-radius: 18px;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .register-btn:hover {
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }

        .register-btn i {
            transition: transform 0.3s ease;
        }

        .register-btn:hover i {
            transform: translateX(5px);
        }
        
        .error-message, .success-message {
            padding: 15px 20px;
            border-radius: 18px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .error-message { 
            background: rgba(239, 68, 68, 0.2); 
            color: #fca5a5; 
            border: 1px solid rgba(239, 68, 68, 0.3);
            backdrop-filter: blur(5px);
        }
        
        .success-message { 
            background: rgba(34, 197, 94, 0.2); 
            color: #bbf7d0; 
            border: 1px solid rgba(34, 197, 94, 0.3);
            backdrop-filter: blur(5px);
            flex-direction: column; 
            text-align: center; 
        }
        
        .success-message i { font-size: 3rem; margin-bottom: 15px; color: var(--accent-color); }
 
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #1e40af;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .back-link:hover { color: #003580; text-decoration: underline; }
 
        .info-strip {
            background: rgba(255, 255, 255, 0.15);
            padding: 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            color: white;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
        }

        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.7);
        }

        .toggle-password:hover {
            color: white;
        }

        .password-strength-container {
            margin-top: 8px;
            font-size: 11px;
            display: none;
            color: white;
            grid-column: span 2;
        }

        .strength-bar {
            height: 6px;
            width: 100%;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            margin-bottom: 4px;
            overflow: hidden;
            display: flex;
        }

        .strength-fill {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }

        .weak { background: #ef4444; width: 33.33%; }
        .medium { background: #f59e0b; width: 66.66%; }
        .strong { background: #10b981; width: 100%; }

        .strength-text { font-weight: 600; }
        .text-weak { color: #fca5a5; }
        .text-medium { color: #fde047; }
        .text-strong { color: #86efac; }

        .password-tips {
            margin-top: 6px;
            color: rgba(255,255,255,0.8);
        }

        .password-tips ul {
            padding-left: 15px;
            margin: 4px 0 0 0;
        }

        .tip-valid {
            color: #86efac;
            text-decoration: line-through;
        }

 
        .suggest-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 11px;
            cursor: pointer;
            margin-top: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            grid-column: span 2;
        }

        .suggest-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        @media (max-width: 768px) {
            .register-container {
                width: 95%;
                max-width: 450px;
                margin: 20px auto;
            }
            .register-card {
                border-radius: 24px;
            }
            .register-header {
                padding: 30px 20px 15px;
            }
            .logo-container {
                width: 70px;
                height: 70px;
                margin-bottom: 15px;
            }
            .register-header h1 {
                font-size: 1.5rem;
            }
            .register-header p {
                font-size: 0.85rem;
            }
            .register-body {
                padding: 0 20px 30px;
            }
            .form-grid { 
                grid-template-columns: 1fr !important; 
                gap: 12px;
            }
            .form-group {
                grid-column: span 1 !important;
                margin-bottom: 15px;
            }
            .form-group label {
                margin-bottom: 6px;
                font-size: 0.75rem;
            }
            .form-group input, .form-group select {
                padding: 12px 15px;
                font-size: 0.85rem;
                border-radius: 12px;
            }
            .register-btn {
                padding: 15px;
                font-size: 1rem;
                border-radius: 15px;
            }
            .register-card::after {
                top: 125px;
            }
            .info-strip {
                padding: 10px;
                font-size: 0.75rem;
                margin-bottom: 15px;
            }
            .back-link {
                margin-top: 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <i class="fas fa-plane airplane"></i>
    <div class="bg-glow"></div>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo-container">
                    <img src="../images/Heydream Logo.png" alt="Logo">
                </div>
                <h1>Agent Application</h1>
                <p>Begin Your Professional Journey</p>
            </div>
            <div class="register-body">
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-paper-plane"></i>
                        <h3>Submission Successful!</h3>
                        <p><?= $success ?></p>
                        <a href="login.php" style="margin-top:20px; color: var(--primary-color); font-weight: 700;">Back to Login</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <div class="info-strip">
                        <i class="fas fa-info-circle"></i>
                        <span>Your request will be manually reviewed by our Super Admin team for security.</span>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" required placeholder="Pick a username">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" required placeholder="agent@agency.com">
                            </div>
                            <div class="form-group full-width">
                                <label>Full Name / Agency Name</label>
                                <input type="text" name="full_name" required placeholder="Enter your full business name">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>Assigned Role</label>
                                <select name="role">
                                    <option value="admin">Standard Admin</option>
                                    <option value="editor">Content Editor</option>
                                    <option value="sales">Sales Agent</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <div class="password-container">
                                    <input type="password" id="passwordInput" name="password" required placeholder="Min. 8 chars">
                                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <div class="password-container">
                                    <input type="password" id="confirmPasswordInput" name="confirm_password" required placeholder="Repeat password">
                                    <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                                </div>
                            </div>
                            
                            <div class="password-strength-container" id="strengthContainer">
                                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                                <div>Strength: <span class="strength-text" id="strengthText">None</span></div>
                                <div class="password-tips">
                                    Tips:
                                    <ul>
                                        <li id="tip-length">8-12+ characters</li>
                                        <li id="tip-upperlower">Uppercase & lowercase letters</li>
                                        <li id="tip-number">At least one number</li>
                                        <li id="tip-special">At least one special character</li>
                                    </ul>
                                </div>
                            </div>
                            <button type="button" class="suggest-btn" id="suggestBtn">
                                <i class="fas fa-magic"></i> Suggest Strong Password
                            </button>
                        </div>
                        <button type="submit" class="register-btn"><i class="fas fa-user-plus"></i> Submit Application</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <script>
        // Password matching and visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });

        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordInput = document.getElementById('confirmPasswordInput');

        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });

        // Real-time password strength checker
        const strengthContainer = document.getElementById('strengthContainer');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const tipLength = document.getElementById('tip-length');
        const tipUpperLower = document.getElementById('tip-upperlower');
        const tipNumber = document.getElementById('tip-number');
        const tipSpecial = document.getElementById('tip-special');

        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            
            if (val.length > 0) {
                strengthContainer.style.display = 'block';
            } else {
                strengthContainer.style.display = 'none';
                return;
            }

            let score = 0;

            const hasLength = val.length >= 8;
            const hasUpperLower = /[a-z]/.test(val) && /[A-Z]/.test(val);
            const hasNumber = /[0-9]/.test(val);
            const hasSpecial = /[^A-Za-z0-9]/.test(val);

            if (hasLength) { tipLength.classList.add('tip-valid'); score += 1; } else { tipLength.classList.remove('tip-valid'); }
            if (hasUpperLower) { tipUpperLower.classList.add('tip-valid'); score += 1; } else { tipUpperLower.classList.remove('tip-valid'); }
            if (hasNumber) { tipNumber.classList.add('tip-valid'); score += 1; } else { tipNumber.classList.remove('tip-valid'); }
            if (hasSpecial) { tipSpecial.classList.add('tip-valid'); score += 1; } else { tipSpecial.classList.remove('tip-valid'); }

            strengthFill.className = 'strength-fill';
            strengthText.className = 'strength-text';
            
            if (score <= 1) {
                strengthFill.classList.add('weak');
                strengthText.textContent = 'Weak';
                strengthText.classList.add('text-weak');
            } else if (score === 2 || score === 3) {
                strengthFill.classList.add('medium');
                strengthText.textContent = 'Medium';
                strengthText.classList.add('text-medium');
            } else if (score === 4) {
                strengthFill.classList.add('strong');
                strengthText.textContent = 'Strong';
                strengthText.classList.add('text-strong');
            }
        });

        // Suggest Password
        document.getElementById('suggestBtn').addEventListener('click', function() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~`|{}[]:;?<>,./';
            let password = '';
            
            password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)];
            password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
            password += '0123456789'[Math.floor(Math.random() * 10)];
            password += '!@#$%^&*'[(Math.floor(Math.random() * 8))];

            for (let i = 0; i < 8; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }

            password = password.split('').sort(() => 0.5 - Math.random()).join('');

            passwordInput.value = password;
            confirmPasswordInput.value = password;
            
            passwordInput.setAttribute('type', 'text');
            confirmPasswordInput.setAttribute('type', 'text');
            togglePassword.classList.add('fa-eye-slash');
            toggleConfirmPassword.classList.add('fa-eye-slash');

            passwordInput.dispatchEvent(new Event('input'));
        });
    </script>
</body>
</html>

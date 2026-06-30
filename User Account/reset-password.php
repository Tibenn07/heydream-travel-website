<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;
$email = '';

// Verify token
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if ($reset) {
        $validToken = true;
        $email = $reset['email'];
    } else {
        $error = 'Invalid or expired reset link. Please request a new one.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
    
    if (empty($errors)) {
        try {
            // Hash the new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $email]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = true;
        } catch (PDOException $e) {
            $error = 'Something went wrong. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - Reset Password</title>
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
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 480px;
            width: 100%;
            padding: 40px;
            animation: fadeInUp 0.5s ease;
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
            box-shadow: 0 0 0 3px rgba(0,53,128,0.1);
        }
        
        .reset-btn {
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .reset-btn:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-align: center;
            width: 100%;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            color: #003580;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }
        
        .success-message i {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ff9800; }
        .strength-strong { color: #28a745; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-login {
            display: inline-block;
            background: #003580;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: #ff9800;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<header class="navbar" id="navbar">
    <div class="nav-left">
        <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo" style="height: 37px; width: auto;" onclick="window.location.href='../index.php'">
        <div class="company-name">
            <span class="line1">HeyDream Travel</span>
            <span class="line2">and Tours</span>
        </div>
    </div>
</header>

    <div class="auth-container">
        <div class="auth-card">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2 style="color: #155724; margin-bottom: 10px;">Password Reset Successful!</h2>
                    <p>Your password has been changed successfully.</p>
                    <a href="login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Sign In Now
                    </a>
                </div>
            <?php elseif (!$validToken && !empty($token)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
                <a href="forgot-password.php" class="reset-btn" style="background: #ff9800; text-decoration: none; display: block; text-align: center;">
                    <i class="fas fa-paper-plane"></i> Request New Reset Link
                </a>
            <?php elseif ($validToken): ?>
                <div class="auth-header">
                    <i class="fas fa-lock" style="font-size: 3rem; color: #ff9800; margin-bottom: 15px;"></i>
                    <h1>Create New Password</h1>
                    <p>Enter your new password below</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>New Password <span style="color: #ff4444;">*</span></label>
                        <input type="password" name="password" id="password" required placeholder="Minimum 6 characters">
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password <span style="color: #ff4444;">*</span></label>
                        <input type="password" name="confirm_password" id="confirmPassword" required placeholder="Confirm your new password">
                    </div>
                    
                    <button type="submit" class="reset-btn">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Sign In
                </a>
            <?php else: ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    Invalid reset link. Please request a new one.
                </div>
                <a href="forgot-password.php" class="reset-btn" style="background: #ff9800; text-decoration: none; display: block; text-align: center;">
                    <i class="fas fa-paper-plane"></i> Request Reset Link
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.getElementById('passwordStrength');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let message = '';
                let className = '';
                
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]+/)) strength++;
                if (password.match(/[A-Z]+/)) strength++;
                if (password.match(/[0-9]+/)) strength++;
                if (password.match(/[$@#&!]+/)) strength++;
                
                if (password.length === 0) {
                    message = '';
                } else if (strength < 2) {
                    message = 'Weak password';
                    className = 'strength-weak';
                } else if (strength < 4) {
                    message = 'Medium password';
                    className = 'strength-medium';
                } else {
                    message = 'Strong password!';
                    className = 'strength-strong';
                }
                
                strengthDiv.innerHTML = message;
                strengthDiv.className = 'password-strength ' + className;
            });
        }
        
        // Confirm password validation
        const confirmInput = document.getElementById('confirmPassword');
        if (confirmInput) {
            confirmInput.addEventListener('input', function() {
                const password = document.getElementById('password').value;
                if (this.value !== password && this.value.length > 0) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = '#ddd';
                }
            });
        }
    </script>
</body>
</html>

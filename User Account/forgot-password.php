<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if user exists
        $user = $auth->getUserByEmail($email);
        
        if (!$user) {
            $error = 'No account found with this email address';
        } else {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            try {
                // Delete any existing tokens for this email
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
                
                // Insert new token
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expiresAt]);
                
                // Create reset link. Build the base path dynamically from the current
                // script's location instead of hardcoding a folder name -- a stale
                // hardcoded "/HeyDream Website/User Account/..." path here previously
                // sent users to a 404 because the project folder had since been renamed.
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $basePath = str_replace('\\', '/', rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'));
                $safeBasePath = str_replace(' ', '%20', $basePath);
                $resetLink = $protocol . $_SERVER['HTTP_HOST'] . $safeBasePath . "/User%20Account/reset-password.php?token=" . $token;
                
                // Send email
                $result = sendPasswordResetEmail($email, $user['full_name'], $resetLink);
                
                if ($result['success']) {
                    $success = "
                        <div style='text-align: center;'>
                            <i class='fas fa-envelope' style='font-size: 3rem; color: #28a745; margin-bottom: 15px; display: block;'></i>
                            <strong>Password reset link sent!</strong><br>
                            We've sent a password reset link to <strong>$email</strong><br>
                            Please check your inbox and spam folder.
                        </div>
                    ";
                } else {
                    // Show error for debugging
                    $error = "Email error: " . $result['message'];
                }
                
            } catch (PDOException $e) {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - Forgot Password</title>
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
        
        .reset-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
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
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .info-text {
            background: #e8f0fe;
            color: #003580;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 13px;
            text-align: center;
        }
        
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
        
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
            <div class="auth-header">
                <i class="fas fa-key" style="font-size: 3rem; color: #ff9800; margin-bottom: 15px;"></i>
                <h1>Forgot Password?</h1>
                <p>Enter your email address and we'll send you a link to reset your password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <div class="info-text">
                <i class="fas fa-info-circle"></i> We'll send a password reset link to your email address
            </div>
            
            <form method="POST" action="" id="resetForm">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="email" required placeholder="your@email.com">
                </div>
                
                <button type="submit" class="reset-btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            
            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Sign In
            </a>
        </div>
    </div>
    
    <script>
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span> Sending...';
            btn.disabled = true;
            
            // Allow form to submit
            setTimeout(() => {
                // Re-enable after 30 seconds if form doesn't submit
                btn.disabled = false;
                btn.innerHTML = originalText;
            }, 30000);
        });
    </script>
</body>
</html>

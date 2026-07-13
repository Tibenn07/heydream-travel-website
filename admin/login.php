<?php
// admin/login.php - Redesigned with Blue and Pastel Yellow Palette
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_functions.php';

$error = '';
$success = '';

if ($pdo === null) {
    $error = 'Database connection failed. Please check your credentials in config/database.php.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username)) {
        $error = 'Please enter username or email';
    } elseif (empty($password)) {
        $error = 'Please enter password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1 AND approved = 1");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_full_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                
                $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
                // Send admin login notification
                sendAdminLoginNotification($admin['id'], 'password');

                $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => 'dashboard.php', 'name' => $admin['full_name']]);
                    exit;
                }

                header('Location: dashboard.php');
                exit;
            } else {
                $stmt = $pdo->prepare("SELECT approved, is_active FROM admin_users WHERE (username = ? OR email = ?)");
                $stmt->execute([$username, $username]);
                $check = $stmt->fetch();
                
                if ($check) {
                    if (!$check['approved']) {
                        $error = 'Your account is pending approval.';
                    } elseif (!$check['is_active']) {
                        $error = 'Your account has been deactivated.';
                    } else {
                        $error = 'Invalid password';
                    }
                } else {
                    $error = 'Invalid username/email or password';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - HeyDream Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #003580; /* Vivid Blue */
            --accent-color: #ffd700; /* Vibrant Yellow */
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
            /* overflow: hidden here used to clip the card with no way to scroll
               to the rest of it on shorter/smaller laptop screens (e.g. 1366x768
               or a zoomed-in browser) where the card + decorative elements are
               taller than the viewport. overflow-x stays hidden so the animated
               airplane/bubbles never cause a horizontal scrollbar; overflow-y is
               auto so the page scrolls instead of clipping when content doesn't fit. */
            overflow-x: hidden;
            overflow-y: auto;
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
        
        /* Modern Background Elements */
        .bg-glow {
            position: absolute;
            width: 400px;
            height: 400px;
            background: var(--accent-color);
            filter: blur(120px);
            opacity: 0.1;
            top: -100px;
            right: -100px;
            border-radius: 50%;
            z-index: 0;
            animation: float 15s infinite alternate;
        }

        @keyframes float {
            from { transform: translate(0, 0); }
            to { transform: translate(-50px, 50px); }
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

        .bg-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 2;
        }
        
        .bg-bubbles li {
            position: absolute;
            list-style: none;
            display: block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.05);
            bottom: -160px;
            animation: square 18s infinite;
            transition-timing-function: linear;
            border-radius: 50%;
        }
        
        .bg-bubbles li:nth-child(1) { left: 10%; width: 80px; height: 80px; animation-delay: 0s; }
        .bg-bubbles li:nth-child(2) { left: 20%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 17s; }
        .bg-bubbles li:nth-child(3) { left: 25%; width: 60px; height: 60px; animation-delay: 4s; }
        .bg-bubbles li:nth-child(4) { left: 40%; width: 100px; height: 100px; animation-delay: 0s; animation-duration: 22s; }
        .bg-bubbles li:nth-child(5) { left: 70%; width: 40px; height: 40px; animation-delay: 0s; }
        
        @keyframes square {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
        }
        
        .admin-login-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 10;
        }
        
        .login-card {
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
        .login-card::after {
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
        
        .login-header {
            padding: 45px 40px 25px;
            text-align: center;
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

        .logo-container:hover {
            transform: rotate(0deg) scale(1.05);
        }

        @keyframes bounceIn {
            0% { transform: scale(0) rotate(-45deg); }
            60% { transform: scale(1.1) rotate(5deg); }
            100% { transform: scale(1) rotate(-3deg); }
        }
        
        .logo-container img { width: 70%; position: relative; z-index: 1; }
        
        .login-header h1 {
            font-size: 2.2rem;
            color: white;
            margin-bottom: 8px;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        
        .login-header p {
            color: var(--accent-color);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .login-body { padding: 0 45px 45px; }
        
        .form-group { margin-bottom: 22px; }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            margin-bottom: 12px;
            color: white;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .form-group label i { color: var(--accent-color); }
        
        .form-group input {
            width: 100%;
            padding: 18px 22px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            font-size: 1rem;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Poppins', sans-serif;
            outline: none;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-group input:focus {
            border-color: var(--accent-color);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.15);
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
        
        .login-btn {
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
            margin-top: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .login-btn:hover {
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }

        .login-btn i {
            transition: transform 0.3s ease;
        }

        .login-btn:hover i {
            transform: translateX(5px);
        }
        
        .error-message, .success-message {
            padding: 14px 20px;
            border-radius: 18px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            animation: bounceInUp 0.5s;
        }

        @keyframes bounceInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
        }
        
        .forgot-link {
            display: block;
            text-align: right;
            margin-top: 15px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .forgot-link:hover { color: var(--accent-color); text-decoration: underline; }
        
        .register-link {
            text-align: center;
            margin-top: 35px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .register-link a { 
            color: var(--accent-color); 
            font-weight: 800; 
            text-decoration: none;
            margin-left: 5px;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
            color: white;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .back-home {
            text-align: center;
            margin-top: 25px;
        }

        .back-home a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-home a:hover {
            color: white;
        }


        .register-link a:hover {
            background: var(--accent-dark);
            transform: scale(1.05);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            color: white;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .back-link:hover { 
            background: var(--accent-color);
            color: var(--primary-color);
            transform: translateX(-5px);
        }

        @media (max-width: 480px) {
            .admin-login-container {
                width: 90%;
                max-width: 360px;
                margin: 0 auto;
            }
            .login-card {
                border-radius: 24px;
            }
            .login-header {
                padding: 25px 20px 10px;
            }
            .logo-container {
                width: 70px;
                height: 70px;
                margin-bottom: 12px;
            }
            .login-header h1 {
                font-size: 1.4rem;
            }
            .login-header p {
                font-size: 0.8rem;
            }
            .login-body {
                padding: 0 20px 25px;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                margin-bottom: 8px;
                font-size: 0.75rem;
            }
            .form-group input {
                padding: 12px 15px;
                font-size: 0.85rem;
                border-radius: 15px;
            }
            .login-btn {
                padding: 12px;
                font-size: 0.95rem;
                border-radius: 15px;
                margin-top: 10px;
            }
            .login-card::after {
                top: 110px; /* Adjusted for smaller header */
            }
            .register-link {
                margin-top: 25px;
                padding-top: 20px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <i class="fas fa-plane airplane"></i>
    <div class="bg-glow"></div>
    <ul class="bg-bubbles">
        <li></li><li></li><li></li><li></li><li></li>
    </ul>
    <div class="admin-login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <img src="../images/Heydream Logo.png" alt="HeyDream Logo">
                </div>
                <h1>Agent Account</h1>
                <p>HeyDream Travel Management System</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-message"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-plane-departure"></i> Username or Email</label>
                        <input type="text" name="username" required placeholder="Entern username or email" autofocus>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-passport"></i> Password</label>
                        <input type="password" name="password" required placeholder="Your Password">
                    </div>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                    <button type="submit" class="login-btn"><i class="fas fa-shield-alt"></i> Login to Dashboard</button>
                </form>
                <div class="register-link">
                    New Agent Member? <a href="register.php">Request Access Now</a>
                </div>
            </div>
        </div>
        <div class="back-home">
            <a href="../index.php">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Intercept admin login form for AJAX flow. This was previously pasted
        // inside a CSS rule in <style> by mistake, so it silently never ran —
        // the form just fell back to a normal full-page POST submit instead.
        const adminForm = document.querySelector('form');
        if (adminForm) {
            adminForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(adminForm);
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ title: 'Welcome back', html: `<strong>${data.name || 'Admin'}</strong>`, icon: 'success', showConfirmButton: false, timer: 1200 })
                                .then(() => { window.location.href = data.redirect || 'dashboard.php'; });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Login Failed', text: data.message || 'Invalid credentials' });
                        }
                    }).catch(err => {
                        console.error(err);
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    });
            });
        }
    </script>
</body>
</html>

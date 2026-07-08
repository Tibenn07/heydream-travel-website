<?php
ob_start(); // Buffer all output to prevent stray warnings from corrupting JSON responses
require_once __DIR__ . '/../config/database.php';  // CHANGE THIS LINE
require_once __DIR__ . '/../config/firebase_config.php';
require_once __DIR__ . '/../config/email_functions.php';

// Check if $auth is defined
if (!isset($auth)) {
    die('Authentication system not initialized. Please check database configuration.');
}

$error = '';

// Allow logged-in users to come back to login (e.g. from Complete Profile "Back" button)
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $auth->logout();
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    session_start();
}

if ($auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password';
    } else {
        $result = $auth->login($email, $password);

        if ($result['success']) {
            // Send login notifications (admin + user)
            if (isset($result['user']['id'])) {
                sendLoginNotifications($result['user']['id'], 'email');
            }
            $redirect = $_GET['redirect'] ?? '../index.php';
            // Simple validation to prevent open redirect vulnerabilities
            if (strpos($redirect, 'http') === 0 && strpos($redirect, $_SERVER['HTTP_HOST']) === false) {
                $redirect = '../index.php';
            }
            // If profile is incomplete, force them to complete-profile page
            if (empty($result['user']['country']) && empty($result['user']['dob']) && empty($result['user']['title'])) {
                $redirect = 'complete-profile.php';
            }
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                $fullName = $result['user']['full_name'] ?? '';
                $displayName = !empty($fullName)
                    ? explode(' ', trim($fullName))[0]
                    : ucfirst(explode('@', $result['user']['email'] ?? $email)[0]);
                $firstName = !empty($fullName) ? explode(' ', trim($fullName))[0] : ($displayName ?? '');
                echo json_encode(['success' => true, 'redirect' => $redirect, 'name' => $displayName, 'first_name' => $firstName, 'is_new' => false]);
                exit;
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
            $useGoogle = !empty($result['use_google']);
        }
    }

    if ($isAjax) {
        ob_end_clean();
        header('Content-Type: application/json');
        $resp = ['success' => false, 'message' => $error ?: 'Login failed'];
        if (!empty($useGoogle)) $resp['use_google'] = true;
        echo json_encode($resp);
        exit;
    }
}

// Clear the output buffer before sending HTML
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - Sign In</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Fix logo size */
        .logo {
            height: 37px !important;
            width: auto !important;
        }

        .nav-left .company-name .line1 {
            font-size: 1.2rem;
        }

        .nav-left .company-name .line2 {
            font-size: 0.7rem;
        }

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

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-me input {
            width: auto;
            cursor: pointer;
        }

        .forgot-link {
            color: #ff9800;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .login-btn {
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
        }

        .login-btn:hover {
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
            background: #fee;
            color: #c33;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
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

        .error-message i {
            flex-shrink: 0;
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
    </style>
    <style>
        /* Custom SweetAlert2 modal redesign */
        .swal2-backdrop-show {
            background: rgba(15, 23, 42, 0.4) !important;
            backdrop-filter: blur(8px) !important;
        }

        .my-swal-popup {
            width: 520px !important;
            max-width: 90% !important;
            border-radius: 24px !important;
            padding: 32px 28px 30px !important;
            box-shadow: 0 40px 100px rgba(15, 23, 42, 0.18) !important;
            background: linear-gradient(180deg, #ffffff 0%, #f5f8ff 100%) !important;
            border: 1px solid rgba(59, 130, 246, 0.14) !important;
            font-family: 'Poppins', sans-serif !important;
            text-align: center;
            color: #111827;
            margin-top: -60px !important;
        }

        /* ── Premium Login Success Popup ── */
        .my-swal-popup {
            border-radius: 24px !important;
            padding: 36px 32px 28px !important;
            max-width: 420px !important;
            width: calc(100% - 40px) !important;
            margin: -60px auto 0 !important;
            overflow: hidden !important;
            position: relative !important;
            background: #ffffff !important;
        }

        /* decorative blobs */
        .my-swal-popup::before {
            content: '' !important;
            position: absolute !important;
            bottom: -60px !important;
            right: -60px !important;
            width: 200px !important;
            height: 200px !important;
            border-radius: 50% !important;
            background: radial-gradient(circle, rgba(99, 179, 237, 0.18) 0%, transparent 70%) !important;
            pointer-events: none !important;
        }

        .my-swal-popup::after {
            content: '' !important;
            position: absolute !important;
            top: -50px !important;
            left: -50px !important;
            width: 180px !important;
            height: 180px !important;
            border-radius: 50% !important;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%) !important;
            pointer-events: none !important;
        }

        .my-swal-title {
            font-size: 26px !important;
            font-weight: 800 !important;
            letter-spacing: -0.01em !important;
            color: #0f172a !important;
            margin: 18px 0 8px !important;
        }

        .my-swal-html {
            color: #4b5563 !important;
            font-size: 15px !important;
            margin: 0 auto 10px !important;
            line-height: 1.7 !important;
            max-width: 340px;
        }

        .swal-username {
            color: #1d4ed8 !important;
            font-weight: 700 !important;
        }

        /* Sparkle area wrapping the checkmark icon */
        .my-swal-icon-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 130px;
            height: 130px;
            margin: 0 auto 4px;
        }

        .my-swal-icon {
            width: 88px !important;
            height: 88px !important;
            border-radius: 999px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.22) 0%, rgba(16, 185, 129, 0.04) 70%) !important;
            color: #059669 !important;
            font-size: 38px !important;
            border: none !important;
            box-shadow: 0 0 0 18px rgba(16, 185, 129, 0.07), 0 0 0 36px rgba(16, 185, 129, 0.03) !important;
            position: relative;
            z-index: 1;
        }

        /* Sparkle dots around the icon */
        .swal-sparkle {
            position: absolute;
            border-radius: 50%;
            animation: sparklePop 1.6s ease-in-out infinite alternate;
        }

        .swal-sparkle.s1 {
            width: 8px;
            height: 8px;
            background: #3b82f6;
            top: 8px;
            left: 30px;
            animation-delay: 0s;
        }

        .swal-sparkle.s2 {
            width: 5px;
            height: 5px;
            background: #10b981;
            top: 16px;
            right: 24px;
            animation-delay: 0.2s;
        }

        .swal-sparkle.s3 {
            width: 4px;
            height: 4px;
            background: #6366f1;
            bottom: 18px;
            left: 18px;
            animation-delay: 0.4s;
        }

        .swal-sparkle.s4 {
            width: 6px;
            height: 6px;
            background: #3b82f6;
            bottom: 10px;
            right: 20px;
            animation-delay: 0.15s;
        }

        .swal-sparkle.s5 {
            width: 10px;
            height: 10px;
            background: #a5f3fc;
            top: 32px;
            right: 6px;
            border-radius: 2px;
            animation-delay: 0.35s;
        }

        .swal-sparkle.s6 {
            width: 4px;
            height: 4px;
            background: #6ee7b7;
            top: 52px;
            left: 6px;
            animation-delay: 0.5s;
        }

        @keyframes sparklePop {
            0% {
                transform: scale(0.7) translateY(0);
                opacity: 0.5;
            }

            100% {
                transform: scale(1.2) translateY(-4px);
                opacity: 1;
            }
        }

        /* Redirect pill */
        .my-swal-redirect-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.88rem;
            color: #475569;
            margin-top: 12px;
        }

        .my-swal-redirect-pill i {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Gradient progress bar override — grows left-to-right (forward) */
        .swal2-timer-progress-bar {
            background: linear-gradient(90deg, #10b981, #3b82f6) !important;
            height: 5px !important;
            border-radius: 99px !important;
            animation: growBar 2s linear forwards !important;
        }

        @keyframes growBar {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }

        .swal2-timer-progress-bar-container {
            border-radius: 0 0 24px 24px !important;
            background: #e2e8f0 !important;
            overflow: hidden !important;
        }

        .swal2-confirm {
            display: none !important;
        }

        .swal2-title::before {
            display: none !important;
        }

        .swal2-show.my-swal-popup {
            transform: translateY(0) !important;
        }

        .company-logo-img {
            height: 80px;
            /* Desktop Size - You can edit this */
            object-fit: contain;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .company-logo-img:hover {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .company-logo-img {
                height: 45px;
                /* Mobile Size - You can edit this! */
            }
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo" style="height: 37px; width: auto;"
                onclick="window.location.href='../index.php'">
            <div class="company-name">
                <img src="../images/Localista (1).png" alt="HeyDream Travel and Tours"
                    class="company-logo-img"
                    onclick="window.location.href='index.php'">
            </div>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Sign in to continue your travel journey</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="social-buttons">
                <a href="#" class="social-btn google" onclick="firebaseSignIn(); return false;">
                    <i class="fab fa-google"></i> Continue with Google
                </a>
                <a href="#" class="social-btn facebook" onclick="socialLogin('facebook'); return false;"
                    style="display:none;">
                    <i class="fab fa-facebook-f"></i> Continue with Facebook
                </a>
                <a href="#" class="social-btn apple" onclick="socialLogin('apple'); return false;"
                    style="display:none;">
                    <i class="fab fa-apple"></i> Continue with Apple
                </a>
            </div>

            <div class="divider">
                <span>or sign in with email</span>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a
                        href="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">Create
                        one</a></p>
            </div>

            <a href="../index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <script>
        function socialLogin(provider) {
            if (provider === 'google') {
                window.location.href = 'oauth_start.php?provider=google';
                return;
            }
            alert('Social login with ' + provider + ' would integrate with OAuth API');
            // In production, redirect to OAuth provider
        }

        function showPhoneSignIn() {
            const phoneNumber = prompt('Enter your phone number (with country code, e.g., +1234567890):');
            if (phoneNumber) {
                // Initialize reCAPTCHA for phone authentication
                const appVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                    'size': 'invisible',
                    'callback': (response) => {
                        // reCAPTCHA solved, allow signInWithPhoneNumber.
                    }
                });

                firebase.auth().signInWithPhoneNumber(phoneNumber, appVerifier)
                    .then(confirmationResult => {
                        // SMS sent. Prompt user to type the code they received.
                        const code = prompt('Enter the verification code sent to your phone:');
                        return confirmationResult.confirm(code);
                    })
                    .then(result => {
                        const phoneIdToken = result?.user?.uid;
                        if (!phoneIdToken) {
                            throw new Error('Phone authentication failed');
                        }
                        return fetch('firebase_auth.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ phone_uid: phoneIdToken })
                        });
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'dashboard.php';
                        } else {
                            alert('Phone authentication failed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Phone sign-in error: ' + error.message);
                    });
            }
        }
    </script>
    <!-- Firebase App (compat) and Auth (compat) -->
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/recaptcha/api.js"></script>
    <script>
        let recaptchaVerifier;
        let confirmationResult;

        // Initialize Firebase using server-side constants
        const firebaseConfig = {
            apiKey: "<?= FIREBASE_API_KEY ?>",
            authDomain: "<?= FIREBASE_AUTH_DOMAIN ?>",
            projectId: "<?= FIREBASE_PROJECT_ID ?>",
        };

        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }

        const auth = firebase.auth();
        auth.languageCode = 'it';

        // Initialize reCAPTCHA for phone authentication
        function initializeRecaptcha() {
            if (!recaptchaVerifier) {
                recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                    'size': 'invisible',
                    'callback': (response) => {
                        // reCAPTCHA solved, allow signInWithPhoneNumber.
                        console.log('reCAPTCHA solved');
                    },
                    'expired-callback': () => {
                        console.log('reCAPTCHA expired');
                    }
                });

                // Pre-render the reCAPTCHA widget
                recaptchaVerifier.render().then((widgetId) => {
                    window.recaptchaWidgetId = widgetId;
                    console.log('reCAPTCHA pre-rendered with widget ID:', widgetId);
                }).catch((error) => {
                    console.error('Failed to render reCAPTCHA:', error);
                });
            }
            return recaptchaVerifier;
        }

        function getRecaptchaResponse() {
            if (window.recaptchaWidgetId !== undefined) {
                const recaptchaResponse = grecaptcha.getResponse(window.recaptchaWidgetId);
                return recaptchaResponse;
            }
            return null;
        }

        function showPhoneSignIn() {
            alert('Social login with phone would integrate with OAuth API');
        }

        function handleSignOut() {
            firebase.auth().signOut()
                .then(() => {
                    // Sign-out successful
                    console.log('User signed out successfully');
                    window.location.href = 'login.php';
                })
                .catch((error) => {
                    // An error happened
                    console.error('Sign-out error:', error);
                    alert('Error signing out: ' + error.message);
                });
        }

        function firebaseSignIn() {
            const provider = new firebase.auth.GoogleAuthProvider();
            provider.setCustomParameters({
                prompt: 'select_account'
            });
            firebase.auth().signInWithPopup(provider)
                .then(result => {
                    // Capture client-side display name as a fallback if server doesn't return first_name
                    const clientFullName = result?.user?.displayName || (result?.additionalUserInfo?.profile?.name) || '';
                    const clientFirstName = (clientFullName || '').split(' ')[0] || '';
                    const googleIdToken = result?.credential?.idToken;
                    if (!googleIdToken) {
                        throw new Error('Google ID token missing');
                    }
                    return fetch('firebase_auth.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_token: googleIdToken })
                    }).then(resp => resp.json()).then(data => ({ data, clientFirstName }));
                })
                .then(({ data, clientFirstName }) => {
                    console.log('Google login response (server):', data, 'clientFirstName:', clientFirstName);
                    if (data.success) {
                        const displayName = data.first_name || clientFirstName || ((data.name || '').split(' ')[0]) || 'Traveler';
                        // Show success flow
                        Swal.fire({
                            customClass: {
                                popup: 'my-swal-popup',
                                title: 'my-swal-title',
                                htmlContainer: 'my-swal-html'
                            },
                            title: false,
                            html: `
                                <div class="my-swal-icon-wrapper">
                                    <span class="swal-sparkle s1"></span>
                                    <span class="swal-sparkle s2"></span>
                                    <span class="swal-sparkle s3"></span>
                                    <span class="swal-sparkle s4"></span>
                                    <span class="swal-sparkle s5"></span>
                                    <span class="swal-sparkle s6"></span>
                                    <div class="my-swal-icon"><i class="fas fa-check"></i></div>
                                </div>
                                <h2 class="my-swal-title" style="display:block;">Login Successful!</h2>
                                <div style="margin-top:4px;">
                                    ${data.is_new ? 'Welcome' : 'Welcome back'}, <span class="swal-username">${displayName}</span>.
                                </div>
                                <div class="my-swal-redirect-pill">
                                    <i class="fas fa-clock"></i> Redirecting you to our website…
                                </div>
                            `,
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true,
                            backdrop: true
                        }).then(() => {
                            document.body.style.transition = 'opacity 300ms ease';
                            document.body.style.opacity = '0.6';
                            window.location.href = data.redirect ?? 'profile.php';
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Login Failed', text: data.message || 'Unknown error' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (err.code !== 'auth/popup-closed-by-user') {
                        Swal.fire({ icon: 'error', title: 'Sign-in Failed', text: err.message });
                    }
                    const submitBtn = document.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
                    }
                });
        }
    </script>
    <!-- SweetAlert2 for modern toasts and success flow -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Intercept the login form to use AJAX and show success flow
        const loginForm = document.querySelector('form[method="POST"]');
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();
                
                const submitBtn = loginForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait...';
                
                const formData = new FormData(loginForm);
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                customClass: {
                                    popup: 'my-swal-popup',
                                    title: 'my-swal-title',
                                    htmlContainer: 'my-swal-html'
                                },
                                title: false,
                                html: `
                                <div class="my-swal-icon-wrapper">
                                    <span class="swal-sparkle s1"></span>
                                    <span class="swal-sparkle s2"></span>
                                    <span class="swal-sparkle s3"></span>
                                    <span class="swal-sparkle s4"></span>
                                    <span class="swal-sparkle s5"></span>
                                    <span class="swal-sparkle s6"></span>
                                    <div class="my-swal-icon"><i class="fas fa-check"></i></div>
                                </div>
                                <h2 class="my-swal-title" style="display:block;">Login Successful!</h2>
                                <div style="margin-top:4px;">
                                    ${data.is_new ? 'Welcome' : 'Welcome back'}, <span class="swal-username">${data.first_name || ((data.name || '').split(' ')[0]) || 'Traveler'}</span>.
                                </div>
                                <div class="my-swal-redirect-pill">
                                    <i class="fas fa-clock"></i> Redirecting you to our website…
                                </div>
                            `,
                                showConfirmButton: false,
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                // Add a smooth fade/zoom before redirect
                                document.body.style.transition = 'opacity 300ms ease, transform 300ms ease';
                                document.body.style.opacity = '0.6';
                                window.location.href = data.redirect || 'profile.php';
                            });
                        } else {
                            if (data.use_google) {
                                // Silently auto-trigger Google sign-in for users who registered via Google but try to login manually
                                firebaseSignIn();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Login Failed', text: data.message || 'Invalid credentials' });
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalBtnText;
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({ icon: 'error', title: 'Login Error', text: err.message });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    });
            });
        }
    </script>

    <?php if (false): ?>
        <!-- Phone Sign-In Modal -->
        <div id="phoneModal" class="phone-modal-overlay">
            <div class="phone-modal">
                <button class="phone-modal-close" onclick="closePhoneModal()">&times;</button>

                <div class="phone-modal-header">
                    <h2>Sign in with Phone</h2>
                    <p>Enter your phone number to receive a verification code</p>
                </div>

                <!-- Phone Number Input Section -->
                <div id="phoneInputSection" class="phone-input-section">
                    <div class="phone-input-group">
                        <label>Phone Number</label>
                        <div class="phone-input-wrapper">
                            <select id="countryCode" class="country-code-select">
                                <option value="+1">+1 US</option>
                                <option value="+44">+44 UK</option>
                                <option value="+91">+91 IN</option>
                                <option value="+86">+86 CN</option>
                                <option value="+81">+81 JP</option>
                                <option value="+33">+33 FR</option>
                                <option value="+49">+49 DE</option>
                                <option value="+39">+39 IT</option>
                                <option value="+34">+34 ES</option>
                                <option value="+61">+61 AU</option>
                                <option value="+27">+27 ZA</option>
                                <option value="+55">+55 BR</option>
                                <option value="+52">+52 MX</option>
                                <option value="+64">+64 NZ</option>
                                <option value="+65">+65 SG</option>
                                <option value="+60">+60 MY</option>
                                <option value="+66">+66 TH</option>
                                <option value="+62">+62 ID</option>
                                <option value="+63">+63 PH</option>
                                <option value="+84">+84 VN</option>
                            </select>
                            <input type="tel" id="phoneNumber" placeholder="Enter your phone number" maxlength="15">
                        </div>
                    </div>

                    <div class="phone-modal-actions">
                        <button class="phone-modal-btn secondary" onclick="closePhoneModal()">Cancel</button>
                        <button class="phone-modal-btn primary" onclick="sendPhoneVerification()">Send Code</button>
                    </div>
                </div>

                <!-- Verification Code Input Section -->
                <div id="verificationSection" class="verification-section">
                    <div class="phone-input-group">
                        <label>Verification Code</label>
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Enter the 6-digit code sent to your
                            phone</p>
                        <div class="verification-code-input">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" data-index="0">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" data-index="1">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" data-index="2">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" data-index="3">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" data-index="4">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" data-index="5">
                        </div>
                    </div>

                    <div class="verification-timer">
                        <span>Code expires in <span id="timerDisplay">59</span>s</span>
                    </div>

                    <div class="phone-modal-actions">
                        <button class="phone-modal-btn secondary" onclick="closePhoneModal()">Cancel</button>
                        <button class="phone-modal-btn primary" id="verifyBtn" onclick="verifyPhoneCode()">Verify</button>
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <span style="color: #666; font-size: 14px;">Didn't receive code? </span>
                        <button class="resend-btn" id="resendBtn" onclick="resendCode()" disabled>Resend in 60s</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            let phoneVerificationState = {
                phoneNumber: '',
                confirmationResult: null,
                resendTimeout: null,
                timerInterval: null
            };

            function openPhoneModal() {
                document.getElementById('phoneModal').classList.add('active');
                document.getElementById('phoneInputSection').style.display = 'block';
                document.getElementById('verificationSection').classList.remove('active');
            }

            function closePhoneModal() {
                document.getElementById('phoneModal').classList.remove('active');
                clearTimeout(phoneVerificationState.resendTimeout);
                clearInterval(phoneVerificationState.timerInterval);
                document.getElementById('phoneNumber').value = '';
                document.querySelectorAll('.code-input').forEach(input => input.value = '');
            }

            function sendPhoneVerification() {
                const countryCode = document.getElementById('countryCode').value;
                const phoneNumber = document.getElementById('phoneNumber').value.trim();

                if (!phoneNumber) {
                    alert('Please enter your phone number');
                    return;
                }

                const fullPhoneNumber = countryCode + phoneNumber;
                console.log('Sending verification to:', fullPhoneNumber);

                try {
                    const appVerifier = initializeRecaptcha();

                    document.getElementById('verifyBtn').disabled = true;
                    document.getElementById('verifyBtn').textContent = 'Sending...';

                    firebase.auth().signInWithPhoneNumber(fullPhoneNumber, appVerifier)
                        .then((confirmationResult_) => {
                            phoneVerificationState.phoneNumber = fullPhoneNumber;
                            phoneVerificationState.confirmationResult = confirmationResult_;

                            console.log('SMS sent successfully');

                            document.getElementById('phoneInputSection').style.display = 'none';
                            document.getElementById('verificationSection').classList.add('active');
                            document.getElementById('verifyBtn').disabled = false;
                            document.getElementById('verifyBtn').textContent = 'Verify';

                            document.querySelector('.code-input[data-index="0"]').focus();
                            startTimer();
                        })
                        .catch(error => {
                            console.error('Error sending SMS:', error);
                            if (recaptchaVerifier) {
                                recaptchaVerifier.render().then(function (widgetId) {
                                    grecaptcha.reset(widgetId);
                                });
                            }
                            alert('Error sending verification code: ' + error.message);
                            document.getElementById('verifyBtn').disabled = false;
                            document.getElementById('verifyBtn').textContent = 'Send Code';
                        });
                } catch (error) {
                    console.error('Exception:', error);
                    alert('Error: ' + error.message);
                    document.getElementById('verifyBtn').disabled = false;
                    document.getElementById('verifyBtn').textContent = 'Send Code';
                }
            }

            function verifyPhoneCode() {
                const codeInputs = document.querySelectorAll('.code-input');
                const code = Array.from(codeInputs).map(input => input.value).join('');

                if (code.length !== 6) {
                    alert('Please enter a 6-digit code');
                    return;
                }

                if (!phoneVerificationState.confirmationResult) {
                    alert('No confirmation result found. Please try again.');
                    return;
                }

                const verifyBtn = document.getElementById('verifyBtn');
                verifyBtn.disabled = true;
                verifyBtn.textContent = 'Verifying...';

                phoneVerificationState.confirmationResult.confirm(code)
                    .then(result => {
                        const user = result.user;
                        const phoneIdToken = user?.uid;

                        if (!phoneIdToken) {
                            throw new Error('Phone authentication failed');
                        }

                        console.log('Phone authentication successful');
                        closePhoneModal();

                        return fetch('firebase_auth.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                phone_uid: phoneIdToken,
                                phone_number: user.phoneNumber
                            })
                        });
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                customClass: {
                                    popup: 'my-swal-popup',
                                    title: 'my-swal-title',
                                    htmlContainer: 'my-swal-html'
                                },
                                title: 'Login Successful',
                                html: `
                                <div class="my-swal-icon"><i class="fas fa-check"></i></div>
                                <div>
                                    ${data.is_new ? 'Welcome' : 'Welcome back'}, <span class="swal-username">${data.first_name || ((data.name || '').split(' ')[0]) || 'Traveler'}</span>.
                                    <div class="my-swal-footer">Redirecting you to your dashboard…</div>
                                </div>
                            `,
                                showConfirmButton: false,
                                timer: 1200,
                                didClose: () => {
                                    window.location.href = data.redirect || 'dashboard.php';
                                }
                            });
                        } else {
                            alert('Authentication failed: ' + (data.message || 'Unknown error'));
                            verifyBtn.disabled = false;
                            verifyBtn.textContent = 'Verify';
                        }
                    })
                    .catch(error => {
                        console.error('Verification error:', error);
                        alert('Verification failed: ' + error.message);
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Verify';
                    });
            }

            function startTimer() {
                let timeLeft = 60;
                document.getElementById('timerDisplay').textContent = timeLeft;
                document.getElementById('resendBtn').disabled = true;

                phoneVerificationState.timerInterval = setInterval(() => {
                    timeLeft--;
                    document.getElementById('timerDisplay').textContent = timeLeft;

                    if (timeLeft <= 0) {
                        clearInterval(phoneVerificationState.timerInterval);
                        document.getElementById('resendBtn').disabled = false;
                        document.getElementById('resendBtn').textContent = 'Resend Now';
                        document.getElementById('timerDisplay').parentElement.classList.add('expired');
                    }
                }, 1000);
            }

            function resendCode() {
                const resendBtn = document.getElementById('resendBtn');
                resendBtn.disabled = true;
                resendBtn.textContent = 'Sending...';

                sendPhoneVerification();

                setTimeout(() => {
                    resendBtn.textContent = 'Resend in 60s';
                }, 1000);
            }

            // Handle code input auto-focus
            document.addEventListener('DOMContentLoaded', function () {
                const codeInputs = document.querySelectorAll('.code-input');

                codeInputs.forEach((input, index) => {
                    input.addEventListener('keyup', (e) => {
                        if (e.key >= '0' && e.key <= '9' && index < codeInputs.length - 1) {
                            codeInputs[index + 1].focus();
                        }
                    });

                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace' && input.value === '' && index > 0) {
                            codeInputs[index - 1].focus();
                        }
                    });
                });
            });
        </script>
    <?php endif; ?>
<?php
// admin/reset-password.php - Redesigned with Blue and Yellow Theme
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - HeyDream Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #003580; /* Vivid Blue */
            --accent-color: #ffd700; /* Vibrant Yellow */
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
            overflow: hidden;
            padding: 20px;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 53, 128, 0.8), rgba(0, 53, 128, 0.4));
            z-index: 1;
        }
        
        .bg-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
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
            border-radius: 50%;
        }
        
        .bg-bubbles li:nth-child(1) { left: 10%; width: 80px; height: 80px; animation-delay: 0s; }
        .bg-bubbles li:nth-child(2) { left: 20%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 17s; }
        .bg-bubbles li:nth-child(3) { left: 40%; width: 100px; height: 100px; animation-delay: 0s; }
        
        @keyframes square {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
        }
        
        .reset-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 10;
        }
        
        .reset-card {
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
        .reset-card::after {
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
        
        .reset-header {
            padding: 45px 40px 25px;
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
        
        .reset-header h1 {
            font-size: 2.2rem;
            color: white;
            margin-bottom: 8px;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        
        .reset-header p {
            color: var(--accent-color);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .reset-body { padding: 0 45px 45px; }
        
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
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
            transform: translateY(-2px);
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .reset-btn {
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
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .reset-btn:hover {
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }

        .reset-btn i {
            transition: transform 0.3s ease;
        }

        .reset-btn:hover i {
            transform: translateX(5px);
        }
        
        .error-message, .success-message {
            padding: 14px 20px;
            border-radius: 18px;
            margin-bottom: 25px;
            display: none;
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
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #1e40af;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .back-link:hover { color: #003580; text-decoration: underline; }
    </style>
</head>
<body>
    <i class="fas fa-plane airplane"></i>
    <ul class="bg-bubbles">
        <li></li><li></li><li></li>
    </ul>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="logo-container">
                    <img src="../images/Heydream Logo.png" alt="Logo">
                </div>
                <h1>Reset Password</h1>
                <p>Create a new password</p>
            </div>
            <div class="reset-body">
                <div class="error-message" id="errorMessage">
                    <i class="fas fa-exclamation-circle"></i> <span></span>
                </div>
                <div class="success-message" id="successMessage">
                    <i class="fas fa-check-circle"></i> <span></span>
                </div>

                <?php if (empty($token)): ?>
                    <div style="text-align: center;">
                        <div class="error-message" style="display: flex;">
                            <i class="fas fa-exclamation-circle"></i> <span>Missing reset token.</span>
                        </div>
                        <a href="forgot-password.php" class="reset-btn" style="text-decoration: none; margin-top:20px;">
                            <i class="fas fa-paper-plane"></i> Request New Link
                        </a>
                    </div>
                <?php else: ?>
                    <form id="resetPasswordForm">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password</label>
                            <input type="password" name="new_password" required placeholder="Minimum 6 characters">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-check-double"></i> Confirm New Password</label>
                            <input type="password" name="confirm_password" required placeholder="Confirm new password">
                        </div>

                        <button type="submit" class="reset-btn" id="submitBtn">
                            <i class="fas fa-save"></i> Save New Password
                        </button>
                    </form>
                <?php endif; ?>

                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to login
                </a>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $('#resetPasswordForm').on('submit', function (e) {
                e.preventDefault();
                $('#errorMessage').hide();
                $('#successMessage').hide();

                const btn = $('#submitBtn');
                const originalText = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin"></i> Securing...');
                btn.prop('disabled', true);

                $.ajax({
                    url: 'admin-api.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#successMessage span').text(response.message);
                            $('#successMessage').css('display', 'flex');
                            $('#resetPasswordForm')[0].reset();
                            btn.hide();
                            setTimeout(function () {
                                window.location.href = 'login.php';
                            }, 2000);
                        } else {
                            $('#errorMessage span').text(response.message || 'An error occurred.');
                            $('#errorMessage').css('display', 'flex');
                            btn.html(originalText);
                            btn.prop('disabled', false);
                        }
                    },
                    error: function () {
                        btn.html(originalText);
                        btn.prop('disabled', false);
                        $('#errorMessage span').text('Protocol error. Please retry.');
                        $('#errorMessage').css('display', 'flex');
                    }
                });
            });
        });
    </script>
</body>
</html>

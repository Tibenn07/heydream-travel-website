<?php
require_once __DIR__ . '/../config/database.php';  // CHANGE THIS LINE

// Check if user is logged in
if ($auth->isLoggedIn()) {
    $userName = $auth->getCurrentUser()['full_name'];
}
?>
<!-- Rest of your logout.php HTML remains the same -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - HeyDream</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .logout-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #eef2f7 0%, #f8fafc 100%);
            margin: 0;
        }

        .logout-card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            max-width: 430px;
            width: 100%;
            box-shadow: 0 30px 90px rgba(15, 23, 42, 0.16);
            border: 1px solid rgba(15, 23, 42, 0.08);
            animation: fadeInUp 0.45s ease;
        }

        .logout-card-header {
            background: linear-gradient(135deg, #f44336 0%, #dc3545 100%);
            padding: 34px 24px 24px;
            text-align: center;
        }

        .logout-icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.12);
        }

        .logout-icon i {
            color: white;
            font-size: 2rem;
            text-shadow: 0 0 12px rgba(255, 255, 255, 0.55);
        }

        .logout-card-header h2 {
            color: #111827;
            margin: 0;
            font-size: 2rem;
            letter-spacing: -0.3px;
            font-weight: 800;
        }

        .logout-body {
            padding: 30px 26px 34px;
            text-align: center;
        }

        .logout-body p {
            color: #475569;
            margin-bottom: 24px;
            line-height: 1.8;
            font-size: 1rem;
        }

        .logout-user-greeting {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.05rem;
            margin-bottom: 20px;
        }

        .button-group {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-cancel,
        .btn-logout {
            flex: 1;
            min-width: 140px;
            padding: 14px 26px;
            border-radius: 18px;
            font-size: 0.98rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s ease;
            border: none;
            cursor: pointer;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #111827;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        .btn-logout {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 14px 26px rgba(220, 53, 69, 0.25);
        }

        .btn-logout:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        @media (max-width: 520px) {
            .button-group {
                flex-direction: column;
            }
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

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-card-header">
                <div class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h2>Secure Sign Out</h2>
            </div>
            <div class="logout-body">
                <?php if (isset($userName)): ?>
                    <p class="logout-user-greeting"><?= htmlspecialchars($userName) ?></p>
                <?php endif; ?>
                <p>You are about to sign out of your HeyDream Travel account. For your security, please confirm if you wish to proceed.</p>
                <div class="button-group">
                    <a href="javascript:history.back()" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="process-logout.php" class="btn-logout" id="logoutBtn">
                        <i class="fas fa-check"></i> Confirm Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('logoutBtn')?.addEventListener('click', function (e) {
            e.preventDefault();
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> Signing out...';
            btn.disabled = true;

            // Proceed to logout
            window.location.href = 'process-logout.php';
        });
    </script>
</body>

</html>
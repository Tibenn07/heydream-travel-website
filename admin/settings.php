<?php
// admin/settings.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

// Check if logged in and has permission
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['admin_role'] !== 'super_admin' && $_SESSION['admin_role'] !== 'admin') {
    die('Unauthorized access');
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 40px 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h2 {
            margin-top: 0;
            color: #003580;
            border-bottom: 3px solid #ffd700;
            padding-bottom: 15px;
            font-weight: 800;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"],
        input[type="number"],
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .btn {
            background: #003580;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 53, 128, 0.2);
        }

        .btn:hover {
            background: #ffd700;
            color: #003580;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #003580;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h2><i class="fas fa-envelope"></i> Email Management Settings</h2>

        <div id="alertBox" class="alert"></div>

        <form id="emailSettingsForm">
            <input type="hidden" name="action" value="update_email_settings">

            <div class="form-group">
                <label>SMTP Host</label>
                <input type="text" name="host" value="<?= htmlspecialchars($emailConfig['host']) ?>" required>
            </div>

            <div class="form-group">
                <label>SMTP Port</label>
                <input type="number" name="port" value="<?= htmlspecialchars($emailConfig['port']) ?>" required>
            </div>

            <div class="form-group">
                <label>SMTP Username (Gmail)</label>
                <input type="email" name="username" value="<?= htmlspecialchars($emailConfig['username']) ?>" required>
            </div>

            <div class="form-group">
                <label>SMTP App Password</label>
                <input type="password" name="password" value="<?= htmlspecialchars($emailConfig['password']) ?>"
                    required>
                <small style="color: #666;">Leave as is, or enter a new Gmail App Password if modifying</small>
            </div>

            <div class="form-group">
                <label>From Email</label>
                <input type="email" name="from_email" value="<?= htmlspecialchars($emailConfig['from_email']) ?>"
                    required>
            </div>

            <div class="form-group">
                <label>From Name</label>
                <input type="text" name="from_name" value="<?= htmlspecialchars($emailConfig['from_name']) ?>" required>
            </div>

            <button type="submit" class="btn" id="saveBtn"><i class="fas fa-save"></i> Save Settings</button>
        </form>
    </div>

    <script>
        $(document).ready(function () {
            $('#emailSettingsForm').on('submit', function (e) {
                e.preventDefault();
                $('#alertBox').hide().removeClass('alert-success alert-danger');

                const btn = $('#saveBtn');
                const originalText = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                btn.prop('disabled', true);

                $.ajax({
                    url: 'admin-api.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function (res) {
                        btn.html(originalText);
                        btn.prop('disabled', false);

                        if (res.success) {
                            $('#alertBox').addClass('alert-success').html('<i class="fas fa-check"></i> ' + res.message).show();
                        } else {
                            $('#alertBox').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle"></i> ' + res.message).show();
                        }
                    },
                    error: function () {
                        btn.html(originalText);
                        btn.prop('disabled', false);
                        $('#alertBox').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle"></i> Server Error').show();
                    }
                });
            });
        });
    </script>
</body>

</html>

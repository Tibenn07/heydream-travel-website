<?php
// ========================================
// FILE: support.php
// DESCRIPTION: Report an Issue / Help & Support ticket form
// ========================================
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = new Auth($pdo);
$currentUser = $auth->isLoggedIn() ? $auth->getCurrentUser() : null;

$REPORT_TYPES = [
    'partner_hoster' => ['label' => 'Report Partner Hoster', 'icon' => '🏢', 'description' => 'Report issues with a partner hoster', 'color' => '#FF6B6B', 'requiresHostName' => true, 'severity' => 'Medium'],
    'account_problem' => ['label' => 'Account Problem', 'icon' => '🔐', 'description' => 'Issues with your account access or settings', 'color' => '#4ECDC4', 'requiresHostName' => false, 'severity' => 'High'],
    'payment_problem' => ['label' => 'Payment Problem', 'icon' => '💳', 'description' => 'Issues with payments, refunds, or billing', 'color' => '#FFD93D', 'requiresHostName' => false, 'severity' => 'High'],
    'app_error' => ['label' => 'Website Error or Issues', 'icon' => '🐛', 'description' => 'Technical issues or bugs on the website', 'color' => '#6C5CE7', 'requiresHostName' => false, 'severity' => 'Medium'],
    'other' => ['label' => 'Other', 'icon' => '📌', 'description' => 'Other issues not listed above', 'color' => '#FF8F00', 'requiresHostName' => false, 'severity' => 'Medium'],
];

$SUGGESTED_PROBLEMS = [
    'booking_issue' => ['label' => 'Booking Issue', 'icon' => '📅'],
    'cancellation' => ['label' => 'Cancellation Problem', 'icon' => '❌'],
    'refund_issue' => ['label' => 'Refund Issue', 'icon' => '💰'],
    'communication' => ['label' => 'Communication Problem', 'icon' => '💬'],
    'service_quality' => ['label' => 'Service Quality', 'icon' => '⭐'],
    'safety_concern' => ['label' => 'Safety Concern', 'icon' => '🛡️'],
    'privacy_issue' => ['label' => 'Privacy Issue', 'icon' => '🔒'],
    'other_issue' => ['label' => 'Other Issue', 'icon' => '📌'],
];

function ensureReportColumns(PDO $pdo)
{
    $existing = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM reported_issues");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Field']] = true;
    }
    $columns = [
        'subject' => 'VARCHAR(255) DEFAULT NULL',
        'screenshot_path' => 'VARCHAR(500) DEFAULT NULL',
    ];
    foreach ($columns as $column => $definition) {
        if (!isset($existing[$column])) {
            $pdo->exec("ALTER TABLE reported_issues ADD COLUMN `$column` $definition");
        }
    }
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = trim($_POST['report_type'] ?? '');
    $host_name = trim($_POST['host_name'] ?? '');
    $suggested_problem = trim($_POST['suggested_problem'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reporter_name = trim($_POST['reporter_name'] ?? '');
    $reporter_email = trim($_POST['reporter_email'] ?? '');
    $reporter_phone = trim($_POST['reporter_phone'] ?? '');
    $urgent = isset($_POST['urgent']) ? 1 : 0;

    $errors = [];
    if (empty($report_type) || !isset($REPORT_TYPES[$report_type])) $errors[] = 'Please select a report type.';
    if (empty($subject)) $errors[] = 'Please enter a subject.';
    if (empty($description)) $errors[] = 'Please describe the issue.';
    if (empty($reporter_name)) $errors[] = 'Your name is required.';
    if (empty($reporter_email) || !filter_var($reporter_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (isset($REPORT_TYPES[$report_type]) && $REPORT_TYPES[$report_type]['requiresHostName'] && empty($host_name)) {
        $errors[] = 'Please enter the host/partner name.';
    }

    $screenshotPath = null;
    if (empty($errors) && !empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Screenshot upload failed. Please try again.';
        } else {
            $allowed = ['jpg' => true, 'jpeg' => true, 'png' => true];
            $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
            if (!isset($allowed[$ext])) {
                $errors[] = 'Screenshot must be a PNG or JPG image.';
            } elseif ($_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Screenshot must be under 5MB.';
            }
        }
    }

    if (empty($errors)) {
        try {
            ensureReportColumns($pdo);

            if (!empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/reports/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                    file_put_contents($uploadDir . '.htaccess', "Options -Indexes\nphp_flag engine off\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n    Require all denied\n</FilesMatch>\n");
                }
                $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
                $filename = 'report_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadDir . $filename)) {
                    $screenshotPath = 'uploads/reports/' . $filename;
                }
            }

            $typeInfo = $REPORT_TYPES[$report_type];
            $category = $typeInfo['label'];
            if ($report_type === 'other' && isset($SUGGESTED_PROBLEMS[$suggested_problem])) {
                $category = 'Other: ' . $SUGGESTED_PROBLEMS[$suggested_problem]['label'];
            }

            $severity = $urgent ? 'Critical' : $typeInfo['severity'];

            $descriptionParts = [];
            $descriptionParts[] = 'Subject: ' . $subject;
            if ($typeInfo['requiresHostName'] && !empty($host_name)) {
                $descriptionParts[] = 'Host/Partner Name: ' . $host_name;
            }
            $descriptionParts[] = '';
            $descriptionParts[] = $description;
            $fullDescription = implode("\n", $descriptionParts);

            $stmt = $pdo->prepare("INSERT INTO reported_issues
                (name, email, contact, category, severity, description, status, subject, screenshot_path)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)");
            $stmt->execute([
                $reporter_name, $reporter_email, $reporter_phone ?: null, $category, $severity, $fullDescription,
                $subject, $screenshotPath,
            ]);

            $success = true;
        } catch (PDOException $e) {
            $error = 'Unable to submit your report. Please try again later.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$prefillName = $currentUser['full_name'] ?? '';
$prefillEmail = $currentUser['email'] ?? '';
$prefillPhone = $currentUser['phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Report an Issue</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/sidepanel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f7ff 100%);
            font-family: 'Poppins', sans-serif;
        }

        .service-hero {
            background: linear-gradient(135deg, #008b8b 0%, #17a2b8 50%, #20c997 100%);
            padding: 100px 5% 60px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .service-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .service-hero-icon {
            font-size: 2.6rem;
            margin-bottom: 10px;
        }

        .service-hero h1 {
            font-size: 2rem;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .service-hero p {
            font-size: 0.95rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
            line-height: 1.6;
        }

        .report-content {
            max-width: 720px;
            margin: -40px auto 0;
            padding: 0 5% 60px;
            position: relative;
            z-index: 5;
        }

        .report-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.1);
            margin-bottom: 20px;
        }

        .section-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .required-star {
            color: #ef4444;
        }

        .field-group {
            margin-bottom: 20px;
        }

        .field-hint {
            color: #94a3b8;
            font-size: 0.78rem;
            margin-top: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea {
            width: 100%;
            border: 1.5px solid #e0e0e0;
            border-radius: 14px;
            padding: 13px 16px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            color: #1a1a1a;
            background: white;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #17a2b8;
            box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.12);
        }

        textarea {
            min-height: 130px;
            resize: none;
        }

        .type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }

        .type-card {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 16px;
            padding: 14px;
            cursor: pointer;
            background: white;
            transition: all 0.2s ease;
            text-align: left;
        }

        .type-card:hover {
            border-color: #17a2b8;
        }

        .type-card.is-selected {
            border-color: var(--type-color, #17a2b8);
            background: color-mix(in srgb, var(--type-color, #17a2b8) 8%, white);
        }

        .type-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background: color-mix(in srgb, var(--type-color, #17a2b8) 18%, white);
            flex-shrink: 0;
        }

        .type-card-label {
            font-weight: 700;
            font-size: 0.9rem;
            color: #1a1a1a;
        }

        .type-card-desc {
            font-size: 0.76rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        .suggested-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .suggested-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f5f5f5;
            border: 1.5px solid transparent;
            padding: 9px 14px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.82rem;
            color: #666;
            font-weight: 500;
        }

        .suggested-chip.is-active {
            background: #fff3e0;
            border-color: #ff8f00;
            color: #e65100;
        }

        .screenshot-drop {
            border: 1.5px dashed #e0e0e0;
            border-radius: 16px;
            min-height: 130px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            cursor: pointer;
            text-align: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .screenshot-drop-icon {
            font-size: 2rem;
        }

        .screenshot-drop-text {
            font-weight: 600;
            font-size: 0.92rem;
            color: #1a1a1a;
        }

        .screenshot-drop-hint {
            font-size: 0.76rem;
            color: #94a3b8;
        }

        #screenshotPreviewImg {
            max-width: 100%;
            max-height: 220px;
            border-radius: 12px;
            display: none;
        }

        .screenshot-actions {
            display: none;
            gap: 10px;
            margin-top: 10px;
        }

        .screenshot-actions button {
            border: none;
            border-radius: 999px;
            padding: 7px 16px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-change {
            background: #e6f7ff;
            color: #17a2b8;
        }

        .btn-remove {
            background: #fee2e2;
            color: #ef4444;
        }

        .urgent-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff7ed;
            border: 1.5px solid #ffedd5;
            border-radius: 16px;
            padding: 14px 16px;
        }

        .urgent-toggle input {
            width: 20px;
            height: 20px;
            accent-color: #ea580c;
            cursor: pointer;
        }

        .urgent-toggle-text strong {
            display: block;
            color: #9a3412;
            font-size: 0.88rem;
        }

        .urgent-toggle-text span {
            color: #c2410c;
            font-size: 0.76rem;
        }

        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 16px;
            padding: 16px;
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            font-weight: 700;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .message-box {
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .message-box.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .success-card {
            text-align: center;
        }

        .success-icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        .success-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .success-text {
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.7;
            max-width: 460px;
            margin: 0 auto 24px;
        }

        .success-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .success-actions a {
            text-decoration: none;
            padding: 13px 24px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-home {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
        }

        .btn-another {
            background: #e6f7ff;
            color: #17a2b8;
        }

        .back-button-container {
            text-align: center;
            padding: 10px 15px 40px;
        }

        .back-button {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
        }

        @media (max-width: 768px) {
            .service-hero {
                padding: 80px 5% 50px;
            }

            .service-hero-icon {
                font-size: 2.1rem;
            }

            .service-hero h1 {
                font-size: 1.6rem;
            }

            .service-hero p {
                font-size: 0.88rem;
            }

            .report-content {
                margin-top: -25px;
            }
        }

        @media (max-width: 600px) {
            .report-content {
                padding: 0 4% 40px;
            }

            .report-card {
                padding: 20px 16px;
                border-radius: 20px;
            }

            .type-grid {
                grid-template-columns: 1fr;
            }

            .type-card {
                padding: 12px;
            }

            .success-actions {
                flex-direction: column;
            }

            .success-actions a {
                justify-content: center;
                width: 100%;
            }

            .urgent-toggle {
                align-items: flex-start;
            }

            .screenshot-drop {
                min-height: 110px;
                padding: 16px;
            }
        }

        @media (max-width: 400px) {
            .service-hero h1 {
                font-size: 1.35rem;
            }

            .type-card-desc {
                display: none;
            }
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left"><img src="images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='index.php'">
            <div class="company-name"><span class="line1">HeyDream Travel</span><span class="line2">and Tours</span>
            </div>
        </div>
        <div class="nav-container">
            <div class="hamburger-menu"><button class="hamburger-icon"
                    id="menuToggle"><span></span><span></span><span></span></button></div>
        </div>
    </header>
    <div class="panel-overlay" id="panelOverlay"></div>

    <div class="side-panel" id="sidePanel">
        <div class="sidebar-profile">
            <div class="sidebar-avatar" id="sidebarAvatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="sidebar-user-info" id="sidebarUserInfo">
                <div class="sidebar-user-role" id="sidebarUserRole">Guest</div>
                <div class="sidebar-user-name" id="sidebarUserName">Welcome!</div>
            </div>
        </div>

        <div class="sidebar-nav-body">
            <div class="sidebar-section-label">Main Menu</div>

            <a href="index.php" class="sidebar-nav-item" id="nav-home">
                <i class="fas fa-home sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Home</span>
                <span class="sidebar-tooltip">Home</span>
            </a>

            <a href="local-destination.php" class="sidebar-nav-item" id="nav-local">
                <i class="fas fa-map-marker-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Local Tours</span>
                <span class="sidebar-tooltip">Local Tours</span>
            </a>

            <a href="foreign-destinations.php" class="sidebar-nav-item" id="nav-foreign">
                <i class="fas fa-plane sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Foreign Tours</span>
                <span class="sidebar-tooltip">Foreign Tours</span>
            </a>

            <a href="flash-deals.php" class="sidebar-nav-item" id="nav-deals">
                <i class="fas fa-tag sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Flash Deals</span>
                <span class="sidebar-tooltip">Flash Deals</span>
            </a>

            <button class="sidebar-nav-item" id="nav-my-booking" onclick="requireLogin('goToProfile')"
                style="border:none; text-align:left; background:#ffffff; width:100%;">
                <i class="fas fa-calendar-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">My Booking</span>
                <span class="sidebar-tooltip">My Booking</span>
            </button>

            <button class="sidebar-nav-item" id="nav-account-toggle"
                onclick="toggleSidebarDropdown('accountDropdown', this)">
                <i class="fas fa-user sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">My Account</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">My Account</span>
            </button>
            <div class="sidebar-dropdown-content" id="accountDropdown">
                <a href="User Account/my-profile.php" class="sidebar-sub-item">
                    <i class="fas fa-user-edit" style="color:#003580;font-size:0.8rem;"></i> My Profile
                </a>
                <button class="sidebar-sub-item" onclick="requireLogin('goToSaved')">
                    <i class="fas fa-star" style="color:#ff9800;font-size:0.8rem;"></i>
                    Saved
                    <span
                        style="background:#ff9800;color:white;padding:1px 7px;border-radius:20px;font-size:0.7rem;margin-left:6px;"
                        id="savedCount">0</span>
                </button>
            </div>

            <button class="sidebar-nav-item" id="nav-social-toggle"
                onclick="toggleSidebarDropdown('socialDropdown', this)">
                <i class="fas fa-share-nodes sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Social Media</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Social Media</span>
            </button>
            <div class="sidebar-dropdown-content" id="socialDropdown">
                <a href="https://www.facebook.com/profile.php?id=61583752858443" target="_blank"
                    class="sidebar-sub-item">
                    <i class="fab fa-facebook-f" style="color:#1877f2;font-size:0.8rem;"></i> Facebook
                </a>
                <a href="https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                    target="_blank" class="sidebar-sub-item">
                    <i class="fab fa-instagram" style="color:#e4405f;font-size:0.8rem;"></i> Instagram
                </a>
                <a href="https://x.com/HeyDreamTravel?s=20" target="_blank" class="sidebar-sub-item">
                    <i class="fa-brands fa-x-twitter" style="color:#000;font-size:0.8rem;"></i> X (Twitter)
                </a>
                <a href="https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc"
                    target="_blank" class="sidebar-sub-item">
                    <i class="fab fa-tiktok" style="color:#000;font-size:0.8rem;"></i> TikTok
                </a>
            </div>

            <button class="sidebar-nav-item active" id="nav-help-toggle"
                onclick="toggleSidebarDropdown('helpDropdown', this)">
                <i class="fas fa-headset sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Help &amp; Support</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Help &amp; Support</span>
            </button>
            <div class="sidebar-dropdown-content" id="helpDropdown">
                <a href="help-support.php" class="sidebar-sub-item">
                    <i class="fas fa-question-circle" style="color:#003580;font-size:0.8rem;"></i> Help Center
                </a>
                <a href="support.php" class="sidebar-sub-item">
                    <i class="fas fa-headset" style="color:#003580;font-size:0.8rem;"></i> Report an Issue
                </a>
            </div>

            <div class="sidebar-divider"></div>

            <div class="sidebar-section-label">Settings</div>

            <button class="sidebar-nav-item" id="nav-settings-toggle"
                onclick="toggleSidebarDropdown('settingsDropdown', this)">
                <i class="fas fa-cog sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Settings</span>
                <i class="fas fa-chevron-down sidebar-chevron"></i>
                <span class="sidebar-tooltip">Settings</span>
            </button>
            <div class="sidebar-dropdown-content" id="settingsDropdown">
                <a href="buttons/about.php" class="sidebar-sub-item">
                    <i class="fas fa-info-circle" style="color:#003580;"></i> About Us
                </a>
                <a href="terms.php" class="sidebar-sub-item">
                    <i class="fas fa-file-alt" style="color:#003580;"></i> Terms of Service
                </a>
                <a href="User Account/change-password.php" class="sidebar-sub-item" id="nav-change-password"
                    style="<?php echo (isset($auth) && $auth->isLoggedIn()) ? 'display:block;' : 'display:none;'; ?>">
                    <i class="fas fa-key" style="color:#003580;"></i> Change Password
                </a>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-divider" style="margin:4px 0; opacity: 0.5;"></div>
            <a href="#" onclick="event.preventDefault(); showLogoutConfirmPopup();" class="sidebar-footer-item logout"
                id="sidebarLogoutBtn" style="display:none;">
                <i class="fas fa-sign-out-alt sidebar-nav-icon"></i>
                <span class="sidebar-nav-label">Logout Account</span>
                <span class="sidebar-tooltip">Logout</span>
            </a>
            <a href="User Account/login.php" class="sidebar-footer-item" id="sidebarLoginBtn">
                <i class="fas fa-sign-in-alt sidebar-nav-icon" style="color:#003580;"></i>
                <span class="sidebar-nav-label">Sign In</span>
                <span class="sidebar-tooltip">Sign In</span>
            </a>
        </div>
    </div><!-- /side-panel -->

    <section class="service-hero">
        <div class="service-hero-icon">🛡️</div>
        <h1>We're here to help</h1>
        <p>Report any issues you're experiencing and our team will assist you promptly.</p>
    </section>

    <div class="report-content">
        <?php if (!empty($error)): ?>
            <div class="report-card">
                <div class="message-box error"><?= $error ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="report-card success-card">
                <div class="success-icon">✅</div>
                <h2 class="success-title">Report Submitted</h2>
                <p class="success-text">Thank you for your report. Our team will review it and get back to you soon via the email you provided.</p>
                <div class="success-actions">
                    <a href="support.php" class="btn-another"><i class="fas fa-plus"></i> Submit Another Report</a>
                    <a href="index.php" class="btn-home"><i class="fas fa-home"></i> Back to Home</a>
                </div>
            </div>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" id="reportForm">
                <div class="report-card">
                    <div class="field-group">
                        <div class="section-label">Report Type <span class="required-star">*</span></div>
                        <div class="type-grid">
                            <?php foreach ($REPORT_TYPES as $id => $type): ?>
                                <div class="type-card" data-type="<?= $id ?>" style="--type-color: <?= $type['color'] ?>" onclick="selectReportType('<?= $id ?>')">
                                    <div class="type-card-icon"><?= $type['icon'] ?></div>
                                    <div>
                                        <div class="type-card-label"><?= htmlspecialchars($type['label']) ?></div>
                                        <div class="type-card-desc"><?= htmlspecialchars($type['description']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="report_type" id="report_type" required>
                    </div>

                    <div class="field-group" id="suggestedProblemGroup" style="display:none;">
                        <div class="section-label">What seems to be the problem?</div>
                        <div class="suggested-grid">
                            <?php foreach ($SUGGESTED_PROBLEMS as $id => $problem): ?>
                                <div class="suggested-chip" data-problem="<?= $id ?>" onclick="selectSuggestedProblem('<?= $id ?>', '<?= htmlspecialchars($problem['label'], ENT_QUOTES) ?>')">
                                    <?= $problem['icon'] ?> <?= htmlspecialchars($problem['label']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="suggested_problem" id="suggested_problem">
                    </div>

                    <div class="field-group" id="hostNameGroup" style="display:none;">
                        <div class="section-label">Host / Partner Name <span class="required-star">*</span></div>
                        <input type="text" name="host_name" id="host_name" placeholder="Enter host or partner name">
                    </div>

                    <div class="field-group">
                        <div class="section-label">Subject <span class="required-star">*</span></div>
                        <input type="text" name="subject" id="subject" placeholder="Brief subject of your report" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
                    </div>

                    <div class="field-group">
                        <div class="section-label">Description <span class="required-star">*</span></div>
                        <textarea name="description" id="description" placeholder="Please describe the issue in detail..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="report-card">
                    <div class="section-label" style="margin-bottom: 16px;">Your Contact Information</div>

                    <div class="field-group">
                        <div class="section-label">Full Name <span class="required-star">*</span></div>
                        <input type="text" name="reporter_name" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['reporter_name'] ?? $prefillName) ?>" required>
                    </div>

                    <div class="field-group">
                        <div class="section-label">Email <span class="required-star">*</span></div>
                        <input type="email" name="reporter_email" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['reporter_email'] ?? $prefillEmail) ?>" required>
                    </div>

                    <div class="field-group" style="margin-bottom: 0;">
                        <div class="section-label">Phone (Optional)</div>
                        <input type="tel" name="reporter_phone" placeholder="+63 912 345 6789" value="<?= htmlspecialchars($_POST['reporter_phone'] ?? $prefillPhone) ?>">
                    </div>
                </div>

                <div class="report-card">
                    <div class="field-group" style="margin-bottom: 20px;">
                        <div class="section-label">Screenshot (Optional)</div>
                        <div class="screenshot-drop" id="screenshotDrop" onclick="document.getElementById('screenshot').click()">
                            <div id="screenshotPromptState">
                                <div class="screenshot-drop-icon">📷</div>
                                <div class="screenshot-drop-text">Upload Screenshot</div>
                                <div class="screenshot-drop-hint">PNG, JPG up to 5MB</div>
                            </div>
                            <img id="screenshotPreviewImg" alt="Screenshot preview">
                        </div>
                        <div class="screenshot-actions" id="screenshotActions">
                            <button type="button" class="btn-change" onclick="event.stopPropagation(); document.getElementById('screenshot').click();">Change</button>
                            <button type="button" class="btn-remove" onclick="event.stopPropagation(); removeScreenshot();">Remove</button>
                        </div>
                        <input type="file" name="screenshot" id="screenshot" accept="image/png,image/jpeg" style="display:none;" onchange="handleScreenshotChange(this)">
                    </div>

                    <div class="urgent-toggle">
                        <input type="checkbox" name="urgent" id="urgent">
                        <label for="urgent" class="urgent-toggle-text" style="cursor:pointer;">
                            <strong>Mark as Urgent</strong>
                            <span>Flags this report as Critical priority for faster review</span>
                        </label>
                    </div>
                </div>

                <div class="report-card">
                    <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit Report</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="back-button-container">
        <a href="help-support.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Help & Support</a>
    </div>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-logo-section">
                <div class="footer-logo">
                    <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="footer-logo-img">
                    <span class="footer-brand">HeyDream</span>
                </div>
                <div class="footer-country"><i class="fas fa-globe"></i> Philippines (Pilipinas)</div>
            </div>
            <div class="footer-links-grid">
                <div class="footer-column">
                    <h4>Contact Us</h4>
                    <ul class="contact-list">
                        <li><i class="fas fa-map-marker-alt"></i> 3104 Tektite East Tower, Philippine Stock Exchange,
                            Ortigas</li>
                        <li><i class="fas fa-phone-alt"></i> 0945 776 4140</li>
                        <li><i class="fas fa-envelope"></i> heydreamtravelandtours@gmail.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9AM-6PM<br>Sat: 9AM-1PM</li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="buttons/about.php">About Us</a></li>
                        <li><a href="career.php">Career</a></li>
                        <li><a href="privacy.php">Data Privacy Policy</a></li>
                        <li><a href="terms.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-social">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/menu.js"></script>
    <script src="js/auth-menu.js"></script>
    <script>
        const REPORT_TYPES = <?= json_encode($REPORT_TYPES) ?>;

        function selectReportType(id) {
            document.getElementById('report_type').value = id;
            document.querySelectorAll('.type-card').forEach(card => {
                card.classList.toggle('is-selected', card.dataset.type === id);
            });

            const hostGroup = document.getElementById('hostNameGroup');
            hostGroup.style.display = REPORT_TYPES[id].requiresHostName ? 'block' : 'none';
            document.getElementById('host_name').required = REPORT_TYPES[id].requiresHostName;

            const suggestedGroup = document.getElementById('suggestedProblemGroup');
            if (id === 'other') {
                suggestedGroup.style.display = 'block';
            } else {
                suggestedGroup.style.display = 'none';
                document.getElementById('suggested_problem').value = '';
                document.querySelectorAll('.suggested-chip').forEach(chip => chip.classList.remove('is-active'));
            }
        }

        function selectSuggestedProblem(id, label) {
            document.getElementById('suggested_problem').value = id;
            document.querySelectorAll('.suggested-chip').forEach(chip => {
                chip.classList.toggle('is-active', chip.dataset.problem === id);
            });
            const subjectInput = document.getElementById('subject');
            if (!subjectInput.value.trim()) {
                subjectInput.value = label;
            }
        }

        function handleScreenshotChange(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                alert('Screenshot must be under 5MB.');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('screenshotPromptState').style.display = 'none';
                const img = document.getElementById('screenshotPreviewImg');
                img.src = e.target.result;
                img.style.display = 'block';
                document.getElementById('screenshotActions').style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }

        function removeScreenshot() {
            const input = document.getElementById('screenshot');
            input.value = '';
            document.getElementById('screenshotPromptState').style.display = 'block';
            document.getElementById('screenshotPreviewImg').style.display = 'none';
            document.getElementById('screenshotActions').style.display = 'none';
        }

        document.getElementById('reportForm')?.addEventListener('submit', function(e) {
            if (!document.getElementById('report_type').value) {
                e.preventDefault();
                alert('Please select a report type.');
                return;
            }
            const reportType = document.getElementById('report_type').value;
            if (REPORT_TYPES[reportType].requiresHostName && !document.getElementById('host_name').value.trim()) {
                e.preventDefault();
                alert('Please enter the host/partner name.');
                return;
            }
        });
    </script>
</body>

</html>

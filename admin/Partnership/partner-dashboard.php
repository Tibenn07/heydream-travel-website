<?php
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['partner_id']) || empty($_SESSION['partner_id'])) {
    header('Location: partner-login.php');
    exit;
}

$partnerId = (int)$_SESSION['partner_id'];
$stmt = $pdo->prepare("SELECT * FROM partner_applications WHERE id = ? LIMIT 1");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();

if (!$partner || $partner['status'] !== 'approved') {
    header('Location: partner-login.php');
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partner_package_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            partner_company VARCHAR(255) NOT NULL,
            uploaded_by_name VARCHAR(255) NOT NULL,
            uploaded_by_email VARCHAR(255) NOT NULL,
            package_name VARCHAR(255) NOT NULL,
            destination_name VARCHAR(255) DEFAULT '',
            duration VARCHAR(80) DEFAULT '',
            price DECIMAL(10,2) DEFAULT 0,
            description TEXT,
            upload_status VARCHAR(30) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_partner_uploads_partner (partner_id),
            INDEX idx_partner_uploads_status (upload_status)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partner_support_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            priority VARCHAR(30) DEFAULT 'medium',
            status VARCHAR(30) DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partner_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL UNIQUE,
            business_display_name VARCHAR(255),
            bio TEXT,
            description TEXT,
            phone VARCHAR(50),
            address VARCHAR(255),
            city VARCHAR(100),
            country VARCHAR(100),
            website VARCHAR(255),
            logo_path VARCHAR(500),
            banner_image_path VARCHAR(500),
            specialties VARCHAR(500),
            years_in_business INT,
            team_size INT,
            certifications TEXT,
            social_media_links TEXT,
            is_verified TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (partner_id) REFERENCES partner_applications(id) ON DELETE CASCADE
        )
    ");

    try {
        $pdo->exec("ALTER TABLE partner_profiles ADD COLUMN business_display_name VARCHAR(255) AFTER partner_id");
    } catch (Throwable $e) {
        // Column already exists, ignore
    }
} catch (Throwable $e) {
}

$section = $_GET['section'] ?? 'dashboard';
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_package') {
    $packageName = trim($_POST['package_name'] ?? '');
    $destinationName = trim($_POST['destination_name'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($packageName === '') {
        $errorMessage = 'Please enter a package name before uploading.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO partner_package_uploads (
                partner_id, partner_company, uploaded_by_name, uploaded_by_email,
                package_name, destination_name, duration, price, description, upload_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $partnerId,
            $partner['company_name'],
            $partner['contact_person'],
            $partner['email'],
            $packageName,
            $destinationName,
            $duration,
            $price,
            $description
        ]);
        $successMessage = 'Package uploaded successfully and is waiting for review.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = trim($_POST['priority'] ?? 'medium');

    if ($subject === '' || $message === '') {
        $errorMessage = 'Please add a subject and a detailed description.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO partner_support_reports (partner_id, subject, message, priority, status) VALUES (?, ?, ?, ?, 'open')");
        $stmt->execute([$partnerId, $subject, $message, $priority]);
        $successMessage = 'Your report has been submitted successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $businessDisplayName = trim($_POST['business_display_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $specialties = trim($_POST['specialties'] ?? '');
    $yearsInBusiness = (int)($_POST['years_in_business'] ?? 0);
    $teamSize = (int)($_POST['team_size'] ?? 0);
    $certifications = trim($_POST['certifications'] ?? '');

    $stmt = $pdo->prepare("
        INSERT INTO partner_profiles (partner_id, business_display_name, bio, description, phone, address, city, country, website, specialties, years_in_business, team_size, certifications)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            business_display_name = ?, bio = ?, description = ?, phone = ?, address = ?, city = ?, country = ?, website = ?, specialties = ?, years_in_business = ?, team_size = ?, certifications = ?, updated_at = NOW()
    ");
    $stmt->execute([
        $partnerId, $businessDisplayName, $bio, $description, $phone, $address, $city, $country, $website, $specialties, $yearsInBusiness, $teamSize, $certifications,
        $businessDisplayName, $bio, $description, $phone, $address, $city, $country, $website, $specialties, $yearsInBusiness, $teamSize, $certifications
    ]);
    $successMessage = 'Profile updated successfully!';
}

$packageStmt = $pdo->prepare("SELECT * FROM partner_package_uploads WHERE partner_id = ? ORDER BY created_at DESC");
$packageStmt->execute([$partnerId]);
$uploads = $packageStmt->fetchAll();

$reportStmt = $pdo->prepare("SELECT * FROM partner_support_reports WHERE partner_id = ? ORDER BY created_at DESC LIMIT 5");
$reportStmt->execute([$partnerId]);
$reports = $reportStmt->fetchAll();

$profileStmt = $pdo->prepare("SELECT * FROM partner_profiles WHERE partner_id = ? LIMIT 1");
$profileStmt->execute([$partnerId]);
$profile = $profileStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Dashboard - HeyDream</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7fb;
            --panel: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #0f4c81;
            --primary-soft: #e8f2ff;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #eef6ff 0%, #f8fafc 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .admin-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #07233f 0%, #0f4c81 100%);
            color: white;
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .brand-block {
            border-bottom: 1px solid rgba(255,255,255,0.16);
            padding-bottom: 16px;
        }

        .brand-block .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 0.75rem;
            color: #cde6ff;
            margin-bottom: 6px;
        }

        .brand-block h2 {
            margin: 0;
            font-size: 1.3rem;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-list a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            text-decoration: none;
            color: white;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .nav-list a.active,
        .nav-list a:hover {
            background: rgba(255,255,255,0.16);
        }

        .sidebar-card {
            margin-top: auto;
            padding: 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.12);
            font-size: 0.94rem;
            line-height: 1.6;
        }

        .main-area {
            flex: 1;
            padding: 28px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 1.7rem;
        }

        .topbar p {
            margin: 4px 0 0;
            color: var(--muted);
        }

        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            text-decoration: none;
            background: var(--panel);
            color: var(--text);
            border: 1px solid var(--border);
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(15,23,42,0.04);
        }

        .pill-btn.primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 22px;
            margin-bottom: 18px;
        }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .panel-head h3 {
            margin: 0;
            font-size: 1.08rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .stat-card {
            padding: 16px;
            border-radius: 16px;
            background: var(--primary-soft);
            border: 1px solid #d8e8ff;
        }

        .stat-card strong {
            display: block;
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .stat-card span {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #d1fae5;
            color: #047857;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #b45309;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 14px;
            margin-bottom: 14px;
            font-weight: 600;
        }

        .alert-success { background: #ecfdf5; color: #047857; }
        .alert-error { background: #fef2f2; color: #b91c1c; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--muted);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 11px 12px;
            font: inherit;
            background: #fcfdff;
        }

        .form-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .submit-btn {
            border: none;
            border-radius: 12px;
            padding: 11px 16px;
            background: var(--primary);
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
        th { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .muted { color: var(--muted); font-size: 0.92rem; }
        .uploader {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        @media (max-width: 900px) {
            .admin-shell { flex-direction: column; }
            .sidebar { width: 100%; }
            .main-area { padding: 16px; }
        }
    </style>
</head>
<body>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand-block">
                <div class="eyebrow">Partner Portal</div>
                <h2>HeyDream Travel</h2>
            </div>
            <nav class="nav-list">
                <a href="partner-dashboard.php" class="<?= $section === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="partner-dashboard.php?section=profile" class="<?= $section === 'profile' ? 'active' : '' ?>"><i class="fas fa-user-tie"></i> My Profile</a>
                <a href="partner-content-manager.php" class="nav-item"><i class="fas fa-edit"></i> Content Manager</a>
                <a href="partner-dashboard.php?section=report-problems" class="<?= $section === 'report-problems' ? 'active' : '' ?>"><i class="fas fa-headset"></i> Report problems</a>
            </nav>
            <div class="sidebar-card">
                <strong><?= htmlspecialchars($partner['company_name']) ?></strong><br>
                <span><?= htmlspecialchars($partner['contact_person']) ?></span>
            </div>
        </aside>

        <main class="main-area">
            <div class="topbar">
                <div>
                    <h1><?= $section === 'partner-content-manager' ? 'Content Manager' : ($section === 'profile' ? 'My Profile' : ($section === 'report-problems' ? 'Report problems' : 'Dashboard')) ?></h1>
                    <p>Manage your partnership activity in a content-manager style workspace.</p>
                </div>
                <div class="top-actions">
                    <a class="pill-btn" href="partner-logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <?php if ($successMessage !== ''): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
                <section class="panel">
                    <div class="panel-head">
                        <h3>Partnership Overview</h3>
                        <span class="status-badge <?= htmlspecialchars($partner['status']) ?>"><?= strtoupper(htmlspecialchars($partner['status'])) ?></span>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <strong>Company</strong>
                            <span><?= htmlspecialchars($partner['company_name']) ?></span>
                        </div>
                        <div class="stat-card">
                            <strong>Contact Person</strong>
                            <span><?= htmlspecialchars($partner['contact_person']) ?></span>
                        </div>
                        <div class="stat-card">
                            <strong>Packages Uploaded</strong>
                            <span><?= count($uploads) ?></span>
                        </div>
                        <div class="stat-card">
                            <strong>Reports Submitted</strong>
                            <span><?= count($reports) ?></span>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-head">
                        <h3>Application Summary</h3>
                    </div>
                    <p class="muted" style="margin: 0; line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($partner['message'] ?: 'No submitted message available.')) ?>
                    </p>
                </section>
            <?php elseif ($section === 'partner-content-manager'): ?>
                <section class="panel">
                    <div class="panel-head">
                        <h3>Upload New Package</h3>
                        <span class="status-badge pending">Pending review</span>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="upload_package">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="package_name">Package Name</label>
                                <input type="text" id="package_name" name="package_name" placeholder="Example: Bali Escape Tour" required>
                            </div>
                            <div class="form-group">
                                <label for="destination_name">Destination</label>
                                <input type="text" id="destination_name" name="destination_name" placeholder="Example: Bali, Indonesia">
                            </div>
                            <div class="form-group">
                                <label for="duration">Duration</label>
                                <input type="text" id="duration" name="duration" placeholder="5D/4N">
                            </div>
                            <div class="form-group">
                                <label for="price">Price</label>
                                <input type="number" step="0.01" id="price" name="price" placeholder="0.00">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" placeholder="Describe the package highlights, inclusions, and notes."></textarea>
                        </div>
                        <div style="margin-top: 16px;"><button class="submit-btn" type="submit"><i class="fas fa-upload"></i> Upload Package</button></div>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-head">
                        <h3>Partner Uploads</h3>
                        <span class="muted">Each entry shows the uploader profile from your partnership.</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Package</th>
                                    <th>Destination</th>
                                    <th>Uploader</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($uploads)): ?>
                                    <tr><td colspan="5" class="muted">No packages uploaded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($uploads as $upload): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($upload['package_name']) ?></strong><br>
                                                <span class="muted"><?= htmlspecialchars($upload['duration'] ?: 'Duration not added') ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($upload['destination_name'] ?: 'Not specified') ?></td>
                                            <td>
                                                <div class="uploader">
                                                    <span class="avatar"><?= htmlspecialchars(substr($upload['uploaded_by_name'], 0, 1)) ?></span>
                                                    <div>
                                                        <div style="font-weight:700;"><?= htmlspecialchars($upload['uploaded_by_name']) ?></div>
                                                        <div class="muted"><?= htmlspecialchars($upload['partner_company']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="status-badge <?= htmlspecialchars($upload['upload_status']) ?>"><?= htmlspecialchars(ucfirst($upload['upload_status'])) ?></span></td>
                                            <td><?= htmlspecialchars(date('M d, Y', strtotime($upload['created_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($section === 'profile'): ?>
                <section class="panel">
                    <div class="panel-head">
                        <h3>Edit Your Profile</h3>
                        <span class="muted">Update information that will be visible to customers</span>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="business_display_name">Business Display Name</label>
                            <input type="text" id="business_display_name" name="business_display_name" placeholder="Your partnership business name" value="<?= htmlspecialchars($profile['business_display_name'] ?? $partner['company_name'] ?? '') ?>">
                            <small style="color: var(--muted); font-size: 0.85rem; display: block; margin-top: 4px;">This name will be displayed to customers on your public profile</small>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" placeholder="+1 (555) 123-4567" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="website">Website</label>
                                <input type="url" id="website" name="website" placeholder="https://yourwebsite.com" value="<?= htmlspecialchars($profile['website'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" placeholder="Your City" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" id="country" name="country" placeholder="Your Country" value="<?= htmlspecialchars($profile['country'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="years_in_business">Years in Business</label>
                                <input type="number" id="years_in_business" name="years_in_business" placeholder="5" value="<?= htmlspecialchars($profile['years_in_business'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="team_size">Team Size</label>
                                <input type="number" id="team_size" name="team_size" placeholder="10" value="<?= htmlspecialchars($profile['team_size'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" placeholder="Street Address" value="<?= htmlspecialchars($profile['address'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="bio">Short Bio</label>
                            <textarea id="bio" name="bio" placeholder="Write a short bio about your company (e.g., Founded in 2015, we specialize in luxury travel experiences...)" style="min-height: 90px;"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="description">Company Description</label>
                            <textarea id="description" name="description" placeholder="Detailed description of your services, specialties, and what makes you unique..." style="min-height: 120px;"><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="specialties">Specialties (comma-separated)</label>
                            <input type="text" id="specialties" name="specialties" placeholder="e.g., Luxury Tours, Budget Travel, Adventure, Cruises" value="<?= htmlspecialchars($profile['specialties'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="certifications">Certifications & Accreditations</label>
                            <textarea id="certifications" name="certifications" placeholder="List any relevant certifications or accreditations..." style="min-height: 90px;"><?= htmlspecialchars($profile['certifications'] ?? '') ?></textarea>
                        </div>
                        <div style="margin-top: 16px;"><button class="submit-btn" type="submit"><i class="fas fa-save"></i> Save Profile</button></div>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-head">
                        <h3>Profile Preview</h3>
                        <span class="muted">This is how your profile appears to customers</span>
                    </div>
                    <div style="background: var(--primary-soft); padding: 20px; border-radius: 12px; border: 1px solid #d8e8ff;">
                        <div style="margin-bottom: 16px;">
                            <strong style="font-size: 1.2rem;"><?= htmlspecialchars($profile['business_display_name'] ?? $partner['company_name']) ?></strong><br>
                            <span class="muted" style="font-size: 0.9rem;"><?= htmlspecialchars($profile['address'] ?? 'Address not provided') ?>, <?= htmlspecialchars($profile['city'] ?? '') ?> <?= htmlspecialchars($profile['country'] ?? '') ?></span>
                        </div>
                        <?php if (!empty($profile['bio'])): ?>
                            <div style="margin-bottom: 12px; line-height: 1.6;"><?= htmlspecialchars($profile['bio']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($profile['specialties'])): ?>
                            <div style="margin-bottom: 12px;">
                                <strong>Specialties:</strong> <?= htmlspecialchars($profile['specialties']) ?>
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px;">
                            <?php if (!empty($profile['phone'])): ?>
                                <span><i class="fas fa-phone" style="margin-right: 6px;"></i> <?= htmlspecialchars($profile['phone']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($profile['website'])): ?>
                                <span><i class="fas fa-globe" style="margin-right: 6px;"></i> <a href="<?= htmlspecialchars($profile['website']) ?>" target="_blank"><?= htmlspecialchars($profile['website']) ?></a></span>
                            <?php endif; ?>
                            <?php if (!empty($profile['years_in_business'])): ?>
                                <span><i class="fas fa-calendar" style="margin-right: 6px;"></i> <?= htmlspecialchars($profile['years_in_business']) ?> years</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <section class="panel">
                    <div class="panel-head">
                        <h3>Submit a Report</h3>
                        <span class="muted">Share an issue with the HeyDream team.</span>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="submit_report">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" placeholder="Example: Upload issue" required>
                            </div>
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="message">What is the issue?</label>
                            <textarea id="message" name="message" placeholder="Tell us what went wrong or what you need help with."></textarea>
                        </div>
                        <div style="margin-top: 16px;"><button class="submit-btn" type="submit"><i class="fas fa-paper-plane"></i> Send Report</button></div>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-head">
                        <h3>Recent Reports</h3>
                    </div>
                    <?php if (empty($reports)): ?>
                        <p class="muted">No reports submitted yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($report['subject']) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($report['priority'])) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($report['status'])) ?></td>
                                            <td><?= htmlspecialchars(date('M d, Y', strtotime($report['created_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

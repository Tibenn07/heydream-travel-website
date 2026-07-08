<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/partner-booking-tracker.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function uploadPartnerProfileAsset($file, $oldPath = null)
{
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => true, 'path' => $oldPath];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes, true)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, GIF, and WEBP images are allowed.'];
    }

    $targetDir = __DIR__ . '/../../uploads/partner-profiles/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        if (!empty($oldPath) && file_exists(__DIR__ . '/../../' . $oldPath)) {
            @unlink(__DIR__ . '/../../' . $oldPath);
        }
        return ['success' => true, 'path' => 'uploads/partner-profiles/' . $filename];
    }

    return ['success' => false, 'message' => 'Image upload failed.'];
}

function uploadPartnerPackageAsset($file, $oldPath = null)
{
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => true, 'path' => $oldPath];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes, true)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, GIF, and WEBP images are allowed.'];
    }

    $targetDir = __DIR__ . '/../../uploads/partner-packages/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        if (!empty($oldPath) && file_exists(__DIR__ . '/../../' . $oldPath)) {
            @unlink(__DIR__ . '/../../' . $oldPath);
        }
        return ['success' => true, 'path' => 'uploads/partner-packages/' . $filename];
    }

    return ['success' => false, 'message' => 'Package image upload failed.'];
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

    try {
        $pdo->exec("ALTER TABLE partner_profiles ADD COLUMN operating_hours VARCHAR(255) AFTER website");
    } catch (Throwable $e) {
        // Column already exists, ignore
    }

    try {
        $pdo->exec("ALTER TABLE partner_package_uploads ADD COLUMN image_path VARCHAR(500) DEFAULT NULL AFTER description");
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

    $imageUpload = uploadPartnerPackageAsset($_FILES['package_image'] ?? null);

    if (!$imageUpload['success']) {
        $errorMessage = $imageUpload['message'];
    } elseif ($packageName === '') {
        $errorMessage = 'Please enter a package name before uploading.';
    } else {
        $imagePath = $imageUpload['path'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO partner_package_uploads (
                partner_id, partner_company, uploaded_by_name, uploaded_by_email,
                package_name, destination_name, duration, price, description, image_path, upload_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
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
            $description,
            $imagePath
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
    $operatingHours = trim($_POST['operating_hours'] ?? '');
    $specialties = trim($_POST['specialties'] ?? '');
    $yearsInBusiness = (int)($_POST['years_in_business'] ?? 0);
    $teamSize = (int)($_POST['team_size'] ?? 0);
    $certifications = trim($_POST['certifications'] ?? '');
    $socialLinks = trim($_POST['social_links'] ?? '');

    $existingProfile = $pdo->prepare("SELECT logo_path, banner_image_path FROM partner_profiles WHERE partner_id = ? LIMIT 1");
    $existingProfile->execute([$partnerId]);
    $existingProfileData = $existingProfile->fetch(PDO::FETCH_ASSOC);

    $logoUpload = uploadPartnerProfileAsset($_FILES['logo_image'] ?? null, $existingProfileData['logo_path'] ?? null);
    $bannerUpload = uploadPartnerProfileAsset($_FILES['banner_image'] ?? null, $existingProfileData['banner_image_path'] ?? null);

    if (!$logoUpload['success']) {
        $errorMessage = $logoUpload['message'];
    } elseif (!$bannerUpload['success']) {
        $errorMessage = $bannerUpload['message'];
    } else {
        $logoPath = $logoUpload['path'] ?? $existingProfileData['logo_path'] ?? null;
        $bannerPath = $bannerUpload['path'] ?? $existingProfileData['banner_image_path'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO partner_profiles (partner_id, business_display_name, bio, description, phone, address, city, country, website, operating_hours, specialties, years_in_business, team_size, certifications, logo_path, banner_image_path, social_media_links)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                business_display_name = ?, bio = ?, description = ?, phone = ?, address = ?, city = ?, country = ?, website = ?, operating_hours = ?, specialties = ?, years_in_business = ?, team_size = ?, certifications = ?, logo_path = ?, banner_image_path = ?, social_media_links = ?, updated_at = NOW()
        ");
        $stmt->execute([
            $partnerId, $businessDisplayName, $bio, $description, $phone, $address, $city, $country, $website, $operatingHours, $specialties, $yearsInBusiness, $teamSize, $certifications, $logoPath, $bannerPath, $socialLinks,
            $businessDisplayName, $bio, $description, $phone, $address, $city, $country, $website, $operatingHours, $specialties, $yearsInBusiness, $teamSize, $certifications, $logoPath, $bannerPath, $socialLinks
        ]);
        $successMessage = 'Profile updated successfully!';
    }
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

ensurePartnerBookingTracking($pdo);

$partnerBookingStatsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_bookings, COALESCE(SUM(CASE WHEN payment_status = 'paid' OR booking_status IN ('confirmed','completed') THEN total_amount ELSE 0 END), 0) AS paid_revenue, COALESCE(SUM(CASE WHEN payment_status = 'unpaid' AND booking_status = 'pending' THEN total_amount ELSE 0 END), 0) AS pending_revenue FROM bookings WHERE partner_id = ? OR (partner_id IS NULL AND partner_company = ?)"
);
$partnerBookingStatsStmt->execute([$partnerId, $partner['company_name'] ?? '']);
$partnerBookingStats = $partnerBookingStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_bookings' => 0,
    'paid_revenue' => 0,
    'pending_revenue' => 0,
];

$partnerBookingsStmt = $pdo->prepare(
    "SELECT id, booking_number, full_name, package_name, partner_package_name, destination_name, total_amount, booking_status, payment_status, created_at FROM bookings WHERE partner_id = ? OR (partner_id IS NULL AND partner_company = ?) ORDER BY created_at DESC LIMIT 10"
);
$partnerBookingsStmt->execute([$partnerId, $partner['company_name'] ?? '']);
$partnerBookings = $partnerBookingsStmt->fetchAll();
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

        .uploader-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .social-profile-shell {
            border: 1px solid #dbeafe;
            border-radius: 24px;
            overflow: hidden;
            background: #f8fbff;
        }

        .social-cover {
            min-height: 180px;
            background: linear-gradient(135deg, #0f4c81 0%, #5ca6ff 100%);
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            padding: 20px;
        }

        .social-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            inset: 0;
        }

        .social-cover .cover-overlay {
            position: relative;
            z-index: 1;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }

        .social-hero {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-start;
            padding: 20px 22px 0;
            margin-top: -34px;
            position: relative;
            z-index: 2;
        }

        .social-avatar {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            border: 4px solid white;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
        }

        .social-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .social-hero-copy {
            flex: 1;
            min-width: 240px;
            background: white;
            border-radius: 18px;
            padding: 16px 18px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
        }

        .social-hero-copy h4 {
            margin: 0 0 6px;
            font-size: 1.3rem;
        }

        .social-hero-copy p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .social-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            padding: 20px 22px 22px;
        }

        .social-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #e2e8f0;
        }

        .social-card h5 {
            margin: 0 0 8px;
            font-size: 0.92rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .social-card p,
        .social-card ul {
            margin: 0;
            color: var(--text);
            line-height: 1.6;
        }

        .social-card ul {
            padding-left: 18px;
        }

        .social-link-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .social-link-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .social-form-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 18px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .admin-shell { flex-direction: column; }
            .sidebar { width: 100%; }
            .main-area { padding: 16px; }
        }

        /* ===================== My Profile redesign (scoped to .mp-*) ===================== */
        .mp-wrap { display: flex; flex-direction: column; gap: 28px; }

        /* Hero / overview card */
        .mp-hero {
            position: relative;
            border-radius: 24px;
            padding: 36px;
            background: linear-gradient(135deg, #0f4c81 0%, #1d6fc7 55%, #4c9ce8 100%);
            color: #fff;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(15, 76, 129, 0.28);
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            align-items: center;
            justify-content: space-between;
        }
        .mp-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.16;
            background-image:
                radial-gradient(circle at 85% 15%, #ffffff 0, transparent 40%),
                radial-gradient(circle at 95% 80%, #ffffff 0, transparent 35%);
            pointer-events: none;
        }
        .mp-hero::after {
            content: '\f5b0';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -20px;
            bottom: -30px;
            font-size: 200px;
            color: rgba(255,255,255,0.08);
            transform: rotate(-8deg);
            pointer-events: none;
        }
        .mp-hero-left {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 20px;
            min-width: 260px;
        }
        .mp-hero-logo {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.85);
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 10px 26px rgba(0,0,0,0.18);
        }
        .mp-hero-logo img { width: 100%; height: 100%; object-fit: cover; }
        .mp-hero-name { font-size: 1.5rem; font-weight: 700; margin: 0 0 4px; }
        .mp-hero-email { margin: 0 0 10px; color: rgba(255,255,255,0.85); font-size: 0.92rem; }
        .mp-verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.18);
            border: 1px solid rgba(16, 185, 129, 0.55);
            color: #d1fae5;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .mp-verified-badge i { color: #34d399; }

        .mp-hero-stats {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(4, minmax(120px, 1fr));
            gap: 14px;
            flex: 1;
            min-width: 320px;
        }
        .mp-stat {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 18px;
            padding: 16px 14px;
            backdrop-filter: blur(6px);
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .mp-stat:hover { transform: translateY(-3px); background: rgba(255,255,255,0.18); }
        .mp-stat i { font-size: 1.1rem; color: #cfe6ff; margin-bottom: 8px; display: block; }
        .mp-stat .mp-stat-title { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.8); margin-bottom: 4px; }
        .mp-stat .mp-stat-value { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
        .mp-stat .mp-stat-sub { font-size: 0.76rem; color: rgba(255,255,255,0.7); margin-top: 2px; }

        /* Main grid */
        .mp-grid {
            display: grid;
            grid-template-columns: 65% 35%;
            gap: 28px;
            align-items: start;
        }
        .mp-col { display: flex; flex-direction: column; gap: 28px; min-width: 0; }

        .mp-card {
            background: var(--panel);
            border-radius: 22px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
            padding: 26px 28px;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .mp-card:hover { box-shadow: 0 18px 40px rgba(15, 23, 42, 0.09); transform: translateY(-2px); }

        .mp-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }
        .mp-card-head h3 { margin: 0 0 4px; font-size: 1.12rem; }
        .mp-card-head p { margin: 0; color: var(--muted); font-size: 0.88rem; }

        .mp-edit-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-soft);
            color: var(--primary);
            border: none;
            padding: 9px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.86rem;
            cursor: pointer;
            transition: background 0.2s ease;
            white-space: nowrap;
        }
        .mp-edit-btn:hover { background: #d6e9ff; }

        /* Info rows (read view) */
        .mp-info-row {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }
        .mp-info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .mp-info-row:first-child { padding-top: 0; }
        .mp-info-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.95rem;
        }
        .mp-info-body { min-width: 0; flex: 1; }
        .mp-info-label { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 3px; }
        .mp-info-value { font-size: 0.96rem; color: var(--text); line-height: 1.55; word-break: break-word; }
        .mp-info-value.mp-empty { color: #94a3b8; font-style: italic; }

        /* Edit form (hidden by default) */
        .mp-edit-form { display: none; }
        .mp-edit-form.mp-active { display: block; }
        .mp-view.mp-hidden { display: none; }

        .mp-field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .mp-field { margin-bottom: 16px; }
        .mp-field label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }
        .mp-field input,
        .mp-field textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.92rem;
            background: #f8fafc;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .mp-field input:focus,
        .mp-field textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
        }
        .mp-field textarea { min-height: 90px; resize: vertical; }

        .mp-form-actions { display: flex; gap: 10px; margin-top: 6px; }
        .mp-btn-primary,
        .mp-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
        }
        .mp-btn-primary {
            background: linear-gradient(135deg, var(--primary), #2f7fd1);
            color: #fff;
            box-shadow: 0 10px 22px rgba(15, 76, 129, 0.25);
        }
        .mp-btn-primary:hover { filter: brightness(1.05); }
        .mp-btn-secondary { background: #f1f5f9; color: var(--text); }
        .mp-btn-secondary:hover { background: #e2e8f0; }

        /* Right column cards */
        .mp-media-block { margin-bottom: 20px; }
        .mp-media-block:last-child { margin-bottom: 0; }
        .mp-media-label { font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
        .mp-logo-preview {
            width: 84px;
            height: 84px;
            border-radius: 20px;
            overflow: hidden;
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 10px;
            border: 1px solid var(--border);
        }
        .mp-logo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .mp-cover-preview {
            width: 100%;
            height: 110px;
            border-radius: 16px;
            overflow: hidden;
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
            border: 1px solid var(--border);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }
        .mp-cover-preview img { width: 100%; height: 100%; object-fit: cover; }
        .mp-media-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #f1f5f9;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
        }
        .mp-media-btn:hover { background: #e2e8f0; }
        .mp-media-file-input { display: none; }

        .mp-security-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #fef3c7;
            color: #b45309;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 12px;
        }

        .mp-progress-track {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #eef2f7;
            overflow: hidden;
            margin: 6px 0 4px;
        }
        .mp-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--primary), #4c9ce8);
        }
        .mp-progress-percent { font-weight: 700; font-size: 0.95rem; color: var(--text); }
        .mp-checklist { list-style: none; margin: 16px 0 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
        .mp-checklist li { display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: var(--text); }
        .mp-checklist li.mp-pending { color: #94a3b8; }
        .mp-checklist .mp-check-icon { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; flex-shrink: 0; }
        .mp-checklist li:not(.mp-pending) .mp-check-icon { background: #d1fae5; color: #059669; }
        .mp-checklist li.mp-pending .mp-check-icon { background: #f1f5f9; color: #cbd5e1; border: 1px solid #e2e8f0; }

        @media (max-width: 1080px) {
            .mp-grid { grid-template-columns: 1fr; }
            .mp-hero-stats { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .mp-hero { padding: 24px; flex-direction: column; align-items: flex-start; }
            .mp-hero-stats { grid-template-columns: 1fr 1fr; }
            .mp-field-grid { grid-template-columns: 1fr; }
            .mp-card { padding: 20px; }
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
                            <strong>Customer Bookings</strong>
                            <span><?= (int)($partnerBookingStats['total_bookings'] ?? 0) ?></span>
                        </div>
                        <div class="stat-card">
                            <strong>Revenue</strong>
                            <span>₱<?= number_format((float)($partnerBookingStats['paid_revenue'] ?? 0), 2) ?></span>
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

                <section class="panel">
                    <div class="panel-head">
                        <h3>Customer Booking Activity</h3>
                        <span class="muted">Packages booked by customers from your partnership listings</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Booked Package</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($partnerBookings)): ?>
                                    <tr><td colspan="5" class="muted">No customer bookings recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($partnerBookings as $booking): ?>
                                        <?php $displayPackage = $booking['partner_package_name'] ?: $booking['package_name']; ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($booking['full_name'] ?: 'Customer') ?></strong><br>
                                                <span class="muted"><?= htmlspecialchars($booking['booking_number']) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($displayPackage ?: 'Package not listed') ?></td>
                                            <td>₱<?= number_format((float)($booking['total_amount'] ?? 0), 2) ?></td>
                                            <td><span class="status-badge <?= htmlspecialchars(($booking['payment_status'] === 'paid' || $booking['booking_status'] === 'confirmed' || $booking['booking_status'] === 'completed') ? 'success' : 'pending') ?>"><?= htmlspecialchars(ucfirst($booking['payment_status'] ?: $booking['booking_status'] ?: 'pending')) ?></span></td>
                                            <td><?= htmlspecialchars(date('M d, Y', strtotime($booking['created_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($section === 'partner-content-manager'): ?>
                <section class="panel">
                    <div class="panel-head">
                        <h3>Upload New Package</h3>
                        <span class="status-badge pending">Pending review</span>
                    </div>
                    <form method="post" enctype="multipart/form-data">
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
                        <div class="form-group" style="margin-top: 14px;">
                            <label for="package_image">Package Cover Image</label>
                            <input type="file" id="package_image" name="package_image" accept="image/*">
                            <div class="muted" style="margin-top: 6px; font-size: 0.9rem;">Recommended: JPG, PNG, WEBP. This image will appear on the partner package listing.</div>
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
                                                <a class="uploader-link" href="partner-profile.php?id=<?= (int)$upload['partner_id'] ?>">
                                                    <div class="uploader">
                                                        <span class="avatar"><?= htmlspecialchars(substr($upload['uploaded_by_name'], 0, 1)) ?></span>
                                                        <div>
                                                            <div style="font-weight:700;"><?= htmlspecialchars($upload['uploaded_by_name']) ?></div>
                                                            <div class="muted"><?= htmlspecialchars($upload['partner_company']) ?></div>
                                                        </div>
                                                    </div>
                                                </a>
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
                <?php
                    $mpBusinessName = $profile['business_display_name'] ?? $partner['company_name'] ?? 'Partner Name';
                    $mpEmail = $partner['email'] ?? '';
                    $mpJoinedSource = $profile['created_at'] ?? null;
                    $mpBusinessSince = $mpJoinedSource ? date('Y', strtotime($mpJoinedSource)) : date('Y');
                    $mpPackagesListed = count($uploads);
                    $mpTotalBookings = (int)($partnerBookingStats['total_bookings'] ?? 0);
                    $mpRevenue = (float)($partnerBookingStats['paid_revenue'] ?? 0);
                    $mpCustomerRating = null;

                    $mpCompletionChecks = [
                        'Business Information' => (!empty($profile['business_display_name']) && !empty($profile['phone']) && !empty($profile['address'])),
                        'Cover Photo' => !empty($profile['banner_image_path']),
                        'Profile Logo' => !empty($profile['logo_path']),
                        'Business Description' => !empty($profile['description']),
                    ];
                    $mpCompletedCount = count(array_filter($mpCompletionChecks));
                    $mpCompletionPercent = (int) round(($mpCompletedCount / count($mpCompletionChecks)) * 100);
                    $mpAddressParts = array_filter([$profile['address'] ?? '', $profile['city'] ?? '', $profile['country'] ?? '']);
                ?>

                <div class="mp-wrap">
                    <!-- Partner Overview -->
                    <div class="mp-hero">
                        <div class="mp-hero-left">
                            <div class="mp-hero-logo">
                                <?php if (!empty($profile['logo_path'])): ?>
                                    <img src="../<?= htmlspecialchars($profile['logo_path']) ?>" alt="Logo">
                                <?php else: ?>
                                    <span><?= htmlspecialchars(strtoupper(substr($mpBusinessName, 0, 1))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="mp-hero-name"><?= htmlspecialchars($mpBusinessName) ?></p>
                                <p class="mp-hero-email"><?= htmlspecialchars($mpEmail) ?></p>
                                <span class="mp-verified-badge"><i class="fas fa-circle-check"></i> Verified Partner</span>
                            </div>
                        </div>
                        <div class="mp-hero-stats">
                            <div class="mp-stat">
                                <i class="fas fa-calendar-check"></i>
                                <div class="mp-stat-title">Business Since</div>
                                <div class="mp-stat-value"><?= htmlspecialchars($mpBusinessSince) ?></div>
                                <div class="mp-stat-sub">Partner with HeyDream</div>
                            </div>
                            <div class="mp-stat">
                                <i class="fas fa-suitcase-rolling"></i>
                                <div class="mp-stat-title">Packages Listed</div>
                                <div class="mp-stat-value"><?= (int) $mpPackagesListed ?></div>
                                <div class="mp-stat-sub"><?= $mpPackagesListed === 1 ? 'Package uploaded' : 'Packages uploaded' ?></div>
                            </div>
                            <div class="mp-stat">
                                <i class="fas fa-wallet"></i>
                                <div class="mp-stat-title">Revenue Earned</div>
                                <div class="mp-stat-value">₱<?= number_format($mpRevenue, 2) ?></div>
                                <div class="mp-stat-sub">From completed bookings</div>
                            </div>
                            <div class="mp-stat">
                                <i class="fas fa-receipt"></i>
                                <div class="mp-stat-title">Total Bookings</div>
                                <div class="mp-stat-value"><?= $mpTotalBookings !== null ? (int) $mpTotalBookings : 0 ?></div>
                                <div class="mp-stat-sub">Since joining</div>
                            </div>
                        </div>
                    </div>

                    <div class="mp-grid">
                        <!-- Left column: 65% -->
                        <div class="mp-col">
                            <div class="mp-card">
                                <div class="mp-card-head">
                                    <div>
                                        <h3>Business Information</h3>
                                        <p>Your public business details</p>
                                    </div>
                                    <button type="button" class="mp-edit-btn" onclick="mpToggleEdit()">
                                        <i class="fas fa-pen"></i> Edit
                                    </button>
                                </div>

                                <!-- Read-only account-style view -->
                                <div class="mp-view" id="mpViewMode">
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-building"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Business Name</div>
                                            <div class="mp-info-value"><?= htmlspecialchars($mpBusinessName) ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-envelope"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Email</div>
                                            <div class="mp-info-value <?= empty($mpEmail) ? 'mp-empty' : '' ?>"><?= htmlspecialchars($mpEmail ?: 'Not provided') ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-phone"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Phone Number</div>
                                            <div class="mp-info-value <?= empty($profile['phone']) ? 'mp-empty' : '' ?>"><?= htmlspecialchars($profile['phone'] ?: 'Not provided') ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-globe"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Website</div>
                                            <div class="mp-info-value <?= empty($profile['website']) ? 'mp-empty' : '' ?>"><?= htmlspecialchars($profile['website'] ?: 'Not provided') ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-location-dot"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Address</div>
                                            <div class="mp-info-value <?= empty($mpAddressParts) ? 'mp-empty' : '' ?>"><?= $mpAddressParts ? htmlspecialchars(implode(', ', $mpAddressParts)) : 'Not provided' ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-clock"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Operating Hours</div>
                                            <div class="mp-info-value <?= empty($profile['operating_hours']) ? 'mp-empty' : '' ?>"><?= htmlspecialchars($profile['operating_hours'] ?: 'Not provided') ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-award"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Years in Business</div>
                                            <div class="mp-info-value <?= empty($profile['years_in_business']) ? 'mp-empty' : '' ?>"><?= !empty($profile['years_in_business']) ? htmlspecialchars($profile['years_in_business']) . ' years' : 'Not provided' ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-tags"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">Specialties</div>
                                            <div class="mp-info-value <?= empty($profile['specialties']) ? 'mp-empty' : '' ?>"><?= htmlspecialchars($profile['specialties'] ?: 'Not provided') ?></div>
                                        </div>
                                    </div>
                                    <div class="mp-info-row">
                                        <div class="mp-info-icon"><i class="fas fa-align-left"></i></div>
                                        <div class="mp-info-body">
                                            <div class="mp-info-label">About Your Business</div>
                                            <div class="mp-info-value <?= empty($profile['description']) ? 'mp-empty' : '' ?>"><?= nl2br(htmlspecialchars($profile['description'] ?: 'Share your journey, expertise, and what customers can expect from your services.')) ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit form (hidden until Edit is clicked) -->
                                <form method="post" enctype="multipart/form-data" class="mp-edit-form" id="mpEditMode">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="mp-field">
                                        <label for="business_display_name">Business Name</label>
                                        <input type="text" id="business_display_name" name="business_display_name" placeholder="Your partnership business name" value="<?= htmlspecialchars($profile['business_display_name'] ?? $partner['company_name'] ?? '') ?>">
                                    </div>
                                    <div class="mp-field-grid">
                                        <div class="mp-field">
                                            <label for="phone">Phone Number</label>
                                            <input type="tel" id="phone" name="phone" placeholder="+1 (555) 123-4567" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                                        </div>
                                        <div class="mp-field">
                                            <label for="website">Website</label>
                                            <input type="url" id="website" name="website" placeholder="https://yourwebsite.com" value="<?= htmlspecialchars($profile['website'] ?? '') ?>">
                                        </div>
                                        <div class="mp-field">
                                            <label for="city">City</label>
                                            <input type="text" id="city" name="city" placeholder="Your City" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                                        </div>
                                        <div class="mp-field">
                                            <label for="country">Country</label>
                                            <input type="text" id="country" name="country" placeholder="Your Country" value="<?= htmlspecialchars($profile['country'] ?? '') ?>">
                                        </div>
                                        <div class="mp-field">
                                            <label for="operating_hours">Operating Hours</label>
                                            <input type="text" id="operating_hours" name="operating_hours" placeholder="Mon - Fri, 9am - 6pm" value="<?= htmlspecialchars($profile['operating_hours'] ?? '') ?>">
                                        </div>
                                        <div class="mp-field">
                                            <label for="years_in_business">Years in Business</label>
                                            <input type="number" id="years_in_business" name="years_in_business" placeholder="5" value="<?= htmlspecialchars($profile['years_in_business'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mp-field">
                                        <label for="address">Address</label>
                                        <input type="text" id="address" name="address" placeholder="Street Address" value="<?= htmlspecialchars($profile['address'] ?? '') ?>">
                                    </div>
                                    <div class="mp-field">
                                        <label for="specialties">Specialties (comma-separated)</label>
                                        <input type="text" id="specialties" name="specialties" placeholder="Luxury Tours, Adventure, Cruises" value="<?= htmlspecialchars($profile['specialties'] ?? '') ?>">
                                    </div>
                                    <div class="mp-field">
                                        <label for="description">About Your Business</label>
                                        <textarea id="description" name="description" placeholder="Share your journey, expertise, and what customers can expect from your services..."><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
                                    </div>

                                    <!-- Preserved fields (not shown as rows) so existing data is never lost on save -->
                                    <div class="mp-field">
                                        <label for="bio">Short Bio</label>
                                        <textarea id="bio" name="bio" placeholder="A short intro that appears under your name..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mp-field-grid">
                                        <div class="mp-field">
                                            <label for="team_size">Team Size</label>
                                            <input type="number" id="team_size" name="team_size" placeholder="10" value="<?= htmlspecialchars($profile['team_size'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mp-field">
                                        <label for="certifications">Certifications & Accreditations</label>
                                        <textarea id="certifications" name="certifications" placeholder="List certifications or accreditations..."><?= htmlspecialchars($profile['certifications'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mp-field">
                                        <label for="social_links">Social Links</label>
                                        <textarea id="social_links" name="social_links" placeholder="Paste one social link per line"><?= htmlspecialchars($profile['social_media_links'] ?? '') ?></textarea>
                                    </div>

                                    <div class="mp-form-actions">
                                        <button class="mp-btn-primary" type="submit"><i class="fas fa-check"></i> Save Changes</button>
                                        <button class="mp-btn-secondary" type="button" onclick="mpToggleEdit()"><i class="fas fa-xmark"></i> Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Right column: 35% -->
                        <div class="mp-col">
                            <div class="mp-card">
                                <div class="mp-card-head">
                                    <div>
                                        <h3>Profile Media</h3>
                                        <p>Logo and cover photo</p>
                                    </div>
                                </div>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="business_display_name" value="<?= htmlspecialchars($profile['business_display_name'] ?? $partner['company_name'] ?? '') ?>">
                                    <input type="hidden" name="bio" value="<?= htmlspecialchars($profile['bio'] ?? '') ?>">
                                    <input type="hidden" name="description" value="<?= htmlspecialchars($profile['description'] ?? '') ?>">
                                    <input type="hidden" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                                    <input type="hidden" name="address" value="<?= htmlspecialchars($profile['address'] ?? '') ?>">
                                    <input type="hidden" name="city" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                                    <input type="hidden" name="country" value="<?= htmlspecialchars($profile['country'] ?? '') ?>">
                                    <input type="hidden" name="website" value="<?= htmlspecialchars($profile['website'] ?? '') ?>">
                                    <input type="hidden" name="operating_hours" value="<?= htmlspecialchars($profile['operating_hours'] ?? '') ?>">
                                    <input type="hidden" name="specialties" value="<?= htmlspecialchars($profile['specialties'] ?? '') ?>">
                                    <input type="hidden" name="years_in_business" value="<?= htmlspecialchars($profile['years_in_business'] ?? '') ?>">
                                    <input type="hidden" name="team_size" value="<?= htmlspecialchars($profile['team_size'] ?? '') ?>">
                                    <input type="hidden" name="certifications" value="<?= htmlspecialchars($profile['certifications'] ?? '') ?>">
                                    <input type="hidden" name="social_links" value="<?= htmlspecialchars($profile['social_media_links'] ?? '') ?>">

                                    <div class="mp-media-block">
                                        <div class="mp-media-label">Profile Logo</div>
                                        <div class="mp-logo-preview">
                                            <?php if (!empty($profile['logo_path'])): ?>
                                                <img src="../<?= htmlspecialchars($profile['logo_path']) ?>" alt="Logo">
                                            <?php else: ?>
                                                <i class="fas fa-image"></i>
                                            <?php endif; ?>
                                        </div>
                                        <label class="mp-media-btn" for="mp_logo_image"><i class="fas fa-camera"></i> Change Logo</label>
                                        <input type="file" id="mp_logo_image" name="logo_image" accept="image/*" class="mp-media-file-input" onchange="this.form.submit()">
                                    </div>

                                    <div class="mp-media-block">
                                        <div class="mp-media-label">Cover Photo</div>
                                        <div class="mp-cover-preview">
                                            <?php if (!empty($profile['banner_image_path'])): ?>
                                                <img src="../<?= htmlspecialchars($profile['banner_image_path']) ?>" alt="Cover photo">
                                            <?php else: ?>
                                                <i class="fas fa-panorama"></i>
                                            <?php endif; ?>
                                        </div>
                                        <label class="mp-media-btn" for="mp_banner_image"><i class="fas fa-camera"></i> Change Cover</label>
                                        <input type="file" id="mp_banner_image" name="banner_image" accept="image/*" class="mp-media-file-input" onchange="this.form.submit()">
                                    </div>
                                </form>
                            </div>

                            <div class="mp-card">
                                <div class="mp-security-icon"><i class="fas fa-shield-halved"></i></div>
                                <h3 style="margin: 0 0 4px;">Account Security</h3>
                                <p style="margin: 0 0 16px; color: var(--muted); font-size: 0.88rem;">Keep your account safe and secure.</p>
                                <button type="button" class="mp-btn-secondary"><i class="fas fa-key"></i> Change Password</button>
                            </div>

                            <div class="mp-card">
                                <h3 style="margin: 0 0 4px;">Profile Completion</h3>
                                <p style="margin: 0 0 4px; color: var(--muted); font-size: 0.88rem;">Complete your profile to build trust with customers.</p>
                                <div class="mp-progress-track">
                                    <div class="mp-progress-fill" style="width: <?= $mpCompletionPercent ?>%;"></div>
                                </div>
                                <div class="mp-progress-percent"><?= $mpCompletionPercent ?>% Complete</div>
                                <ul class="mp-checklist">
                                    <?php foreach ($mpCompletionChecks as $mpLabel => $mpDone): ?>
                                        <li class="<?= $mpDone ? '' : 'mp-pending' ?>">
                                            <span class="mp-check-icon"><i class="fas <?= $mpDone ? 'fa-check' : 'fa-circle' ?>"></i></span>
                                            <?= htmlspecialchars($mpLabel) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div style="margin-top: 16px;">
                                    <a class="social-link-chip" href="partner-profile.php?id=<?= (int)$partnerId ?>" target="_blank" style="text-decoration:none;"><i class="fas fa-external-link-alt"></i> View Public Profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function mpToggleEdit() {
                        var viewMode = document.getElementById('mpViewMode');
                        var editMode = document.getElementById('mpEditMode');
                        var isActive = editMode.classList.contains('mp-active');
                        if (isActive) {
                            editMode.classList.remove('mp-active');
                            viewMode.classList.remove('mp-hidden');
                        } else {
                            editMode.classList.add('mp-active');
                            viewMode.classList.add('mp-hidden');
                        }
                    }
                </script>
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
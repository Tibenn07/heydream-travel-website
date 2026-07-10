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

ensurePartnerBookingTracking($pdo);

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

function assetUrl($path)
{
    if (!$path) {
        return '';
    }

    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '//') === 0) {
        return $path;
    }

    if (strpos($path, '../') === 0 || strpos($path, './') === 0) {
        return $path;
    }

    return '../../' . ltrim($path, '/');
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
$viewUploadDetails = null;
if ($section === 'partner-content-manager' && isset($_GET['view_upload_id']) && ctype_digit($_GET['view_upload_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM partner_package_uploads WHERE id = ? AND partner_id = ? LIMIT 1");
    $stmt->execute([$_GET['view_upload_id'], $partnerId]);
    $viewUploadDetails = $stmt->fetch(PDO::FETCH_ASSOC);
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId > 0) {
        $checkStmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND partner_id = ?");
        $checkStmt->execute([$bookingId, $partnerId]);
        if ($checkStmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'confirmed', payment_status = 'paid' WHERE id = ? AND partner_id = ?");
            $stmt->execute([$bookingId, $partnerId]);
            $successMessage = 'Booking #' . $bookingId . ' has been confirmed successfully!';
        } else {
            $errorMessage = 'You do not have permission to confirm this booking.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId > 0) {
        $checkStmt = $pdo->prepare(
            "SELECT booking_status, payment_status, email, full_name, booking_number, destination_name, package_name, partner_package_name, travel_date, number_of_travelers, total_amount, travel_documents, ready_for_travel
             FROM bookings WHERE id = ? AND partner_id = ?"
        );
        $checkStmt->execute([$bookingId, $partnerId]);
        $oldBooking = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($oldBooking) {
            $newStatus  = trim($_POST['booking_status'] ?? 'pending');
            $newPayment = trim($_POST['payment_status'] ?? 'unpaid');
            $newVisa    = trim($_POST['visa_status'] ?? 'PENDING');
            $newPricePerPerson = (float)($_POST['price_per_person'] ?? 0);
            $newAmount  = (float)($_POST['total_amount'] ?? 0);
            $newTravel  = trim($_POST['travel_date'] ?? '');
            $newTravelers = (int)($_POST['number_of_travelers'] ?? 1);
            $newTravelDocs = isset($_POST['travel_documents']) ? 1 : 0;
            $newReadyForTravel = isset($_POST['ready_for_travel']) ? 1 : 0;
            $newFlightDetails = trim($_POST['flight_details'] ?? '');
            $newAdminNotes = trim($_POST['admin_notes'] ?? '');

            // Auto-complete logic (matches admin dashboard): once travel docs are
            // ready, payment is paid, and visa (if applicable) is approved/n-a,
            // the booking status is bumped to completed automatically.
            $isVisaRelated = ($oldBooking['destination_name'] === 'Visa Assistance'
                || stripos($oldBooking['package_name'] ?? '', 'Visa') !== false
                || (isset($oldBooking['booking_number']) && strpos($oldBooking['booking_number'], 'VI-') === 0));
            $isVisaMatch = (strtoupper($newVisa) === 'APPROVED' || strtoupper($newVisa) === 'N/A' || !$isVisaRelated);
            $isNowFullyCompleted = ($newTravelDocs === 1 && $newPayment === 'paid' && $isVisaMatch);
            if ($isNowFullyCompleted) {
                $newStatus = 'completed';
            }

            $stmt = $pdo->prepare("UPDATE bookings SET booking_status=?, payment_status=?, visa_status=?, price_per_person=?, total_amount=?, travel_date=?, number_of_travelers=?, travel_documents=?, ready_for_travel=?, flight_details=?, admin_notes=? WHERE id=? AND partner_id=?");
            $updateSuccess = $stmt->execute([$newStatus, $newPayment, $newVisa, $newPricePerPerson, $newAmount, $newTravel, $newTravelers, $newTravelDocs, $newReadyForTravel, $newFlightDetails, $newAdminNotes, $bookingId, $partnerId]);

            $emailSent = false;
            if ($updateSuccess) {
                $emailFunctionsPath = __DIR__ . '/../../config/email_functions.php';
                if (file_exists($emailFunctionsPath)) {
                    require_once $emailFunctionsPath;

                    $bookingDataForEmail = [
                        'id' => $bookingId,
                        'booking_number' => $oldBooking['booking_number'] ?? '',
                        'full_name' => $oldBooking['full_name'] ?? '',
                        'email' => $oldBooking['email'] ?? '',
                        'destination_name' => $oldBooking['destination_name'] ?? '',
                        'package_name' => $oldBooking['partner_package_name'] ?: ($oldBooking['package_name'] ?? ''),
                        'travel_date' => $newTravel,
                        'number_of_travelers' => $newTravelers,
                        'total_amount' => $newAmount,
                        'booking_status' => $newStatus,
                        'payment_status' => $newPayment,
                        'admin_notes' => $newAdminNotes,
                        'flight_details' => $newFlightDetails,
                    ];

                    if ($oldBooking['booking_status'] != $newStatus && $newStatus !== 'completed' && function_exists('sendBookingStatusEmail')) {
                        $emailSent = sendBookingStatusEmail($bookingId, $bookingDataForEmail);
                    }

                    if ($oldBooking['payment_status'] != 'paid' && $newPayment == 'paid' && $oldBooking['destination_name'] === 'Visa Assistance' && function_exists('sendVisaPaymentConfirmationEmail')) {
                        $emailSent = sendVisaPaymentConfirmationEmail($bookingId, $bookingDataForEmail);
                    }

                    if ($oldBooking['travel_documents'] == 0 && $newTravelDocs == 1 && !$isNowFullyCompleted && function_exists('sendTrackingUpdateEmail')) {
                        $emailSent = sendTrackingUpdateEmail($bookingId, 'travel_documents', $bookingDataForEmail);
                    }

                    if ($oldBooking['ready_for_travel'] == 0 && $newReadyForTravel == 1 && function_exists('sendTrackingUpdateEmail')) {
                        $emailSent = sendTrackingUpdateEmail($bookingId, 'ready_for_travel', $bookingDataForEmail);
                    }
                }
            }

            if ($updateSuccess) {
                $successMessage = 'Booking updated successfully!' . ($emailSent ? ' Customer notified by email.' : '');
            } else {
                $errorMessage = 'Failed to update booking.';
            }
        } else {
            $errorMessage = 'You do not have permission to edit this booking.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId > 0) {
        $checkStmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND partner_id = ?");
        $checkStmt->execute([$bookingId, $partnerId]);
        if ($checkStmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ? AND partner_id = ?");
            $stmt->execute([$bookingId, $partnerId]);
            $successMessage = 'Booking deleted successfully!';
        } else {
            $errorMessage = 'You do not have permission to delete this booking.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {
    $reportCategory = trim($_POST['category'] ?? '');
    $reportSubject = trim($_POST['subject'] ?? '');
    $reportMessage = trim($_POST['message'] ?? '');
    $reportUrgent = isset($_POST['urgent']) ? 1 : 0;

    if ($reportCategory === '' || $reportSubject === '' || $reportMessage === '') {
        $errorMessage = 'Please select a category and fill in the subject and issue description.';
    } else {
        $screenshotPath = null;
        $screenshotError = null;

        if (!empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
                $screenshotError = 'Screenshot upload failed. Please try again.';
            } else {
                $allowedExtToMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
                $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
                if (!isset($allowedExtToMime[$ext])) {
                    $screenshotError = 'Screenshot must be a JPG or PNG image.';
                } elseif ($_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
                    $screenshotError = 'Screenshot must be under 5MB.';
                } elseif (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $actualMime = finfo_file($finfo, $_FILES['screenshot']['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($actualMime, ['image/jpeg', 'image/png'], true)) {
                        $screenshotError = 'Screenshot must be a genuine JPG or PNG image.';
                    }
                }
            }
        }

        if ($screenshotError !== null) {
            $errorMessage = $screenshotError;
        } else {
            if (!empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/reports/';
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

            $severity = $reportUrgent ? 'Critical' : 'Medium';
            $reporterName = $partner['contact_person'] ?: $partner['company_name'];

            $stmt = $pdo->prepare("
                INSERT INTO reported_issues (name, email, contact, category, severity, description, status, subject, screenshot_path, partner_id)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)
            ");
            $stmt->execute([
                $reporterName, $partner['email'], $partner['phone'],
                $reportCategory, $severity, $reportMessage, $reportSubject, $screenshotPath, $partnerId,
            ]);
            $successMessage = 'Your report has been submitted successfully.';
        }
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
    $otherSocialLinks = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $_POST['social_links_other'] ?? ''))));
    $socialLinks = json_encode([
        'facebook' => trim($_POST['social_link_facebook'] ?? ''),
        'tiktok' => trim($_POST['social_link_tiktok'] ?? ''),
        'x' => trim($_POST['social_link_x'] ?? ''),
        'youtube' => trim($_POST['social_link_youtube'] ?? ''),
        'instagram' => trim($_POST['social_link_instagram'] ?? ''),
        'other' => $otherSocialLinks,
    ]);

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

$pwModalShouldOpen = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMessage = 'Please fill in all password fields.';
        $pwModalShouldOpen = true;
    } elseif (!password_verify($currentPassword, $partner['password'])) {
        $errorMessage = 'Your current password is incorrect.';
        $pwModalShouldOpen = true;
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = 'New password must be at least 8 characters long.';
        $pwModalShouldOpen = true;
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'New password and confirmation do not match.';
        $pwModalShouldOpen = true;
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE partner_applications SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $partnerId]);
        $partner['password'] = $newHash;
        $successMessage = 'Password changed successfully!';
    }
}

$uploads = [];

function getPartnerContentManagerDetailUrl($upload) {
    if (empty($upload['source_type']) || empty($upload['source_id'])) {
        return null;
    }

    switch ($upload['source_type']) {
        case 'partner_package':
            return 'partner-dashboard.php?section=partner-content-manager&view_upload_id=' . urlencode($upload['source_id']);
        case 'flash_deal':
            return 'partner-content-manager.php?page=flash-deals&source_type=flash_deal&source_id=' . urlencode($upload['source_id']);
        case 'foreign_destination':
            return 'partner-content-manager.php?page=foreign-destinations&source_type=foreign_destination&source_id=' . urlencode($upload['source_id']);
        case 'local_destination':
            return 'partner-content-manager.php?page=local-destinations&source_type=local_destination&source_id=' . urlencode($upload['source_id']);
        case 'cruise':
            return 'partner-content-manager.php?page=cruises&source_type=cruise&source_id=' . urlencode($upload['source_id']);
        case 'site_service':
            if (!empty($upload['service_type'])) {
                if ($upload['service_type'] === 'premium') {
                    return 'partner-content-manager.php?page=premium-services&source_type=site_service&source_id=' . urlencode($upload['source_id']);
                }
                if ($upload['service_type'] === 'experience') {
                    return 'partner-content-manager.php?page=experiences&source_type=site_service&source_id=' . urlencode($upload['source_id']);
                }
                if ($upload['service_type'] === 'flight') {
                    return 'partner-content-manager.php?page=flight-bookings&source_type=site_service&source_id=' . urlencode($upload['source_id']);
                }
            }
            return 'partner-content-manager.php?page=premium-services&source_type=site_service&source_id=' . urlencode($upload['source_id']);
        case 'visa':
            return 'partner-content-manager.php?page=visa-assistance&source_type=visa&source_id=' . urlencode($upload['source_id']);
    }

    return null;
}

$packageSources = [
    [
        'query' => "SELECT id AS source_id, package_name AS title, destination_name, duration, price, upload_status AS status, created_at, 'Partner Upload' AS source, 'partner_package' AS source_type FROM partner_package_uploads WHERE partner_id = ?",
    ],
    [
        'query' => "SELECT id AS source_id, title AS title, location AS destination_name, duration, price, IF(is_active = 1, 'active', 'inactive') AS status, created_at, 'Flash Deal' AS source, 'flash_deal' AS source_type FROM flash_deals WHERE partner_id = ?",
    ],
    [
        'query' => "SELECT id AS source_id, name AS title, country AS destination_name, duration, price, IF(is_active = 1, 'active', 'inactive') AS status, created_at, 'Foreign Destination' AS source, 'foreign_destination' AS source_type FROM foreign_destinations WHERE partner_id = ?",
    ],
    [
        'query' => "SELECT id AS source_id, name AS title, city AS destination_name, duration, price, IF(is_active = 1, 'active', 'inactive') AS status, created_at, 'Local Destination' AS source, 'local_destination' AS source_type FROM destinations WHERE type = 'local' AND partner_id = ?",
    ],
    [
        'query' => "SELECT id AS source_id, title AS title, category AS destination_name, duration, price, IF(is_active = 1, 'active', 'inactive') AS status, created_at, 'Cruise' AS source, 'cruise' AS source_type FROM cruises WHERE partner_id = ?",
    ],
    [
        'query' => "SELECT id AS source_id, title AS title, category AS destination_name, duration, price, IF(is_active = 1, 'active', 'inactive') AS status, created_at, 'Service' AS source, 'site_service' AS source_type, service_type FROM site_services WHERE partner_id = ?",
    ],
    [
        'query' => "SELECT id AS source_id, title AS title, category AS destination_name, NULL AS duration, price, IF(is_active = 1, 'active', 'inactive') AS status, created_at, 'Visa' AS source, 'visa' AS source_type FROM visas WHERE partner_id = ?",
    ],
];

foreach ($packageSources as $sourceConfig) {
    try {
        $stmt = $pdo->prepare($sourceConfig['query']);
        $stmt->execute([$partnerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $upload = [
                'package_name' => $row['title'] ?? 'Untitled package',
                'destination_name' => $row['destination_name'] ?? 'Not specified',
                'duration' => $row['duration'] ?? '',
                'price' => isset($row['price']) ? (float)$row['price'] : 0,
                'upload_status' => $row['status'] ?? 'active',
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                'source' => $row['source'] ?? 'Partner',
                'source_type' => $row['source_type'] ?? 'partner_package',
                'source_id' => $row['source_id'] ?? null,
                'service_type' => $row['service_type'] ?? null,
            ];
            $upload['detail_url'] = getPartnerContentManagerDetailUrl($upload);
            $uploads[] = $upload;
        }
    } catch (Throwable $e) {
        // Ignore missing tables or read errors for optional partner content tables
    }
}

usort($uploads, function ($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$reportStmt = $pdo->prepare("SELECT * FROM reported_issues WHERE partner_id = ? ORDER BY created_at DESC LIMIT 8");
$reportStmt->execute([$partnerId]);
$reports = $reportStmt->fetchAll(PDO::FETCH_ASSOC);

$profileStmt = $pdo->prepare("SELECT * FROM partner_profiles WHERE partner_id = ? LIMIT 1");
$profileStmt->execute([$partnerId]);
$profile = array_merge([
    'business_display_name' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'country' => '',
    'website' => '',
    'operating_hours' => '',
    'specialties' => '',
    'years_in_business' => '',
    'team_size' => '',
    'certifications' => '',
    'social_media_links' => '',
    'bio' => '',
    'description' => '',
    'logo_path' => '',
    'banner_image_path' => '',
    'created_at' => null,
], $profileStmt->fetch() ?: []);

$partnerBookingStatsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_bookings,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' OR booking_status IN ('confirmed','completed') THEN total_amount ELSE 0 END), 0) AS paid_revenue,
            COALESCE(SUM(CASE WHEN payment_status = 'unpaid' AND booking_status = 'pending' THEN total_amount ELSE 0 END), 0) AS pending_revenue
     FROM bookings WHERE partner_id = ?"
);
$partnerBookingStatsStmt->execute([$partnerId]);
$partnerBookingStats = $partnerBookingStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_bookings' => 0,
    'paid_revenue' => 0,
    'pending_revenue' => 0,
];

$recentBookingsStmt = $pdo->prepare(
    "SELECT id, booking_number, full_name, package_name, partner_package_name, destination_name, total_amount, booking_status, payment_status, created_at
     FROM bookings WHERE partner_id = ? ORDER BY created_at DESC LIMIT 10"
);
$recentBookingsStmt->execute([$partnerId]);
$partnerBookings = $recentBookingsStmt->fetchAll();

$paginatedBookings = [];
$totalPages = 1;
$currentPage = 1;
$searchQuery = '';
$statusFilter = '';

if (($section ?? 'dashboard') === 'bookings') {
    $searchQuery = trim($_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $limit = 10;
    $offset = ($currentPage - 1) * $limit;

    $whereClause = "partner_id = :partner_id";
    $params = [
        'partner_id' => $partnerId,
    ];

    if ($searchQuery !== '') {
        $whereClause .= " AND (full_name LIKE :search OR package_name LIKE :search OR partner_package_name LIKE :search)";
        $params['search'] = "%{$searchQuery}%";
    }

    if ($statusFilter !== '') {
        $whereClause .= " AND booking_status = :status";
        $params['status'] = $statusFilter;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE {$whereClause}");
    $countStmt->execute($params);
    $totalBookingsCount = $countStmt->fetchColumn();
    $totalPages = ceil($totalBookingsCount / $limit) ?: 1;

    $paginatedBookingsStmt = $pdo->prepare(
        "SELECT id, booking_number, package_name, partner_package_name, full_name, email, phone, number_of_travelers, travel_date, created_at, total_amount, payment_status, booking_status, payment_method,
                address, destination_name, package_duration, special_requests, visa_status, price_per_person,
                payment_reference, payment_proof, admin_notes, flight_details, travel_documents, ready_for_travel
         FROM bookings
         WHERE {$whereClause}
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $val) {
        $paginatedBookingsStmt->bindValue(":$key", $val);
    }
    $paginatedBookingsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $paginatedBookingsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $paginatedBookingsStmt->execute();
    $paginatedBookings = $paginatedBookingsStmt->fetchAll(PDO::FETCH_ASSOC);
}
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

        .mobile-topbar {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 14px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(15,23,42,0.06);
        }

        .mobile-topbar .page-title {
            font-weight: 700;
            color: #0f4c81;
            font-size: 1rem;
            flex: 1;
        }

        .mobile-logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fef2f2;
            color: #ef4444;
            font-size: 1rem;
            flex-shrink: 0;
            transition: background 0.2s;
        }

        .mobile-logout-btn:hover {
            background: #fee2e2;
        }

        .menu-toggle {
            display: none;
            background: #eff6ff;
            border: 1px solid rgba(15,76,129,0.15);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            color: #0f4c81;
            font-size: 1.05rem;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .menu-toggle:hover {
            background: #dbeafe;
        }

        .sidebar-close-btn {
            display: none;
            position: absolute;
            top: 18px;
            right: 18px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            border: none;
            color: #fff;
            font-size: 0.95rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .sidebar-close-btn:hover {
            background: rgba(255,255,255,0.22);
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            z-index: 1900;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #07233f 0%, #0f4c81 100%);
            color: white;
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            position: relative;
        }

        .brand-block {
            border-bottom: 1px solid rgba(255,255,255,0.16);
            padding-bottom: 16px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .brand-block .brand-logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-block .brand-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .brand-block .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 0.75rem;
            color: #ffffff;
            margin: 0;
        }

        .brand-block h2 {
            margin: 0;
            font-size: 1.3rem;
            color: #ffffff;
            line-height: 1.2;
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

        .dashboard-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding: 28px;
            margin-bottom: 18px;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(15,76,129,0.1), rgba(255,255,255,0.95));
            border: 1px solid rgba(15,76,129,0.12);
            box-shadow: 0 20px 45px rgba(15,23,42,0.08);
        }

        .dashboard-hero-copy {
            flex: 1;
            min-width: 280px;
        }

        .dashboard-hero-copy h1 {
            margin: 12px 0 0;
            font-size: 2.05rem;
            line-height: 1.1;
        }

        .hero-note {
            margin: 18px 0 0;
            color: var(--muted);
            max-width: 640px;
            line-height: 1.8;
        }

        .hero-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .dashboard-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .dashboard-hero-actions .pill-btn {
            min-width: 170px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #e2e8ff;
            color: #1d4ed8;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.02em;
        }

        .status-pill.approved {
            background: #d1fae5;
            color: #047857;
        }

        .status-pill.pending {
            background: #fef3c7;
            color: #b45309;
        }

        .status-pill.rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dashboard-primary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .dashboard-mini-card {
            background: #f8fbff;
            border: 1px solid #dbeafe;
            border-radius: 20px;
            padding: 22px;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.8);
        }

        .dashboard-mini-card:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }

        .dashboard-mini-card .card-label {
            font-size: 0.85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 10px;
        }

        .dashboard-mini-card .card-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 10px;
        }

        .dashboard-mini-card .card-note {
            color: #475569;
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .dashboard-hero-panel {
            overflow: hidden;
        }

        .panel-head p {
            margin: 6px 0 0;
            color: var(--muted);
        }

        .panel-head > div {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .panel-head h3 {
            margin: 0;
            font-size: 1.15rem;
        }

        .panel-head span.status-pill {
            margin-top: 0;
        }

        .dashboard-hero-panel .status-pill {
            background: #e0f2fe;
            color: #0369a1;
        }

        .dashboard-hero-panel .status-pill.approved {
            background: #d1fae5;
            color: #047857;
        }

        .dashboard-hero-panel .status-pill.pending {
            background: #fef3c7;
            color: #b45309;
        }

        .dashboard-hero-panel .status-pill.rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dashboard-hero-panel .panel-head {
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .dashboard-hero-panel .panel-head > div {
            min-width: 0;
        }

        .dashboard-hero-panel .status-pill {
            flex-shrink: 0;
        }

        .dashboard-hero-panel .dashboard-primary-grid {
            margin-top: 20px;
        }

        .dashboard-hero-panel .dashboard-mini-card {
            padding: 24px;
        }

        .package-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
            min-width: 760px;
        }

        .package-table th {
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .package-table td {
            padding: 16px 14px;
            background: #f8fbff;
            border: none;
            vertical-align: top;
        }

        .package-table tbody tr:hover td {
            background: #eef4ff;
        }

        .package-name {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .package-name strong {
            font-size: 0.98rem;
            color: var(--text);
        }

        .package-meta {
            color: var(--muted);
            font-size: 0.88rem;
        }

        .package-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .package-status.active {
            background: #ecfdf5;
            color: #166534;
        }

        .package-status.inactive,
        .package-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .package-status.rejected,
        .package-status.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .package-source {
            color: #475569;
            font-size: 0.82rem;
            margin-top: 6px;
        }

        .dashboard-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding: 28px;
            margin-bottom: 18px;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(15,76,129,0.1), rgba(255,255,255,0.95));
            border: 1px solid rgba(15,76,129,0.12);
            box-shadow: 0 20px 45px rgba(15,23,42,0.08);
        }

        @media (max-width: 900px) {
            .dashboard-hero { flex-direction: column; }
            .dashboard-hero-actions { justify-content: flex-start; }
            .dashboard-hero { padding: 22px; }
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
            .mobile-topbar { display: flex; }
            .menu-toggle { display: flex; }
            .sidebar-close-btn { display: flex; }
            .dashboard-hero-actions .logout-btn { display: none; }
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                height: 100vh;
                z-index: 2000;
                transition: left 0.3s ease;
                box-shadow: 5px 0 25px rgba(0, 0, 0, 0.15);
            }
            .sidebar.active { left: 0; }
            .main-area { padding: 16px; width: 100%; }
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
        .mp-hero-email { margin: 0 0 10px; color: rgba(255,255,255,0.85); font-size: 0.92rem; overflow-wrap: anywhere; }
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
        .mp-field label small { font-weight: 400; text-transform: none; color: var(--muted); letter-spacing: 0; }

        .mp-social-input-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .mp-social-input { display: flex; flex-direction: column; align-items: stretch; gap: 0; border: 1px solid var(--border); border-radius: 12px; background: #f8fafc; overflow: hidden; transition: border-color 0.2s ease, background 0.2s ease; }
        .mp-social-input:focus-within { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.08); }
        .mp-social-input-icon { width: 100%; height: 34px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.95rem; }
        .mp-social-input-icon.fb { background: #1877f2; }
        .mp-social-input-icon.tiktok { background: #010101; }
        .mp-social-input-icon.x { background: #000000; }
        .mp-social-input-icon.yt { background: #ff0000; }
        .mp-social-input-icon.ig { background: radial-gradient(circle at 30% 110%, #fdf497, #fd5949 45%, #d6249f 60%, #285AEB 90%); }
        .mp-social-input input { width: 100%; border: none; background: transparent; padding: 8px 10px; font-family: inherit; font-size: 0.8rem; min-width: 0; text-align: center; }
        .mp-social-input input:focus { outline: none; }
        .mp-social-input input::placeholder { color: #94a3b8; }
        @media (max-width: 900px) { .mp-social-input-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 480px) { .mp-social-input-grid { grid-template-columns: repeat(2, 1fr); } }

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
        .mp-media-preview-wrap {
            position: relative;
            margin-bottom: 52px;
        }
        .mp-cover-preview {
            width: 100%;
            height: 150px;
            border-radius: 18px;
            overflow: hidden;
            background: linear-gradient(135deg, #0f4c81 0%, #1d6fc7 55%, #4c9ce8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.75);
            font-size: 1.6rem;
        }
        .mp-cover-preview img { width: 100%; height: 100%; object-fit: cover; }
        .mp-cover-camera-btn {
            position: absolute;
            top: 12px;
            right: 12px;
        }
        .mp-logo-preview {
            position: absolute;
            left: 20px;
            bottom: -34px;
            width: 84px;
            height: 84px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--primary-soft);
            border: 4px solid #fff;
            box-shadow: 0 8px 20px rgba(15,23,42,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.3rem;
        }
        .mp-logo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .mp-logo-camera-btn {
            position: absolute;
            left: 84px;
            bottom: -30px;
        }
        .mp-camera-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            border: 2px solid #fff;
            cursor: pointer;
            font-size: 0.8rem;
            box-shadow: 0 4px 10px rgba(15,23,42,0.2);
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .mp-camera-btn:hover { background: #0d3f6b; transform: scale(1.08); }
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

        .pw-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .pw-modal-overlay.active {
            display: flex;
        }

        .pw-modal {
            background: #fff;
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            padding: 28px;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
        }

        .pw-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .pw-modal-head h3 {
            margin: 0;
            font-size: 1.15rem;
            color: var(--text);
        }

        .pw-modal-close {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            color: #64748b;
            font-size: 0.9rem;
        }

        .pw-modal-close:hover {
            background: #e2e8f0;
        }

        .pw-modal .mp-field {
            margin-bottom: 14px;
        }

        .pw-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .pw-modal-actions .mp-btn-primary,
        .pw-modal-actions .mp-btn-secondary {
            flex: 1;
            justify-content: center;
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
        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .edit-btn,
        .delete-btn,
        .view-btn,
        .approve-btn,
        .reject-btn,
        .incomplete-btn {
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .edit-btn:empty,
        .delete-btn:empty,
        .view-btn:empty,
        .approve-btn:empty,
        .reject-btn:empty,
        .incomplete-btn:empty,
        .edit-btn i:only-child,
        .delete-btn i:only-child,
        .view-btn i:only-child,
        .approve-btn i:only-child,
        .reject-btn i:only-child,
        .incomplete-btn i:only-child {
            padding: 0;
            width: 36px;
        }

        .edit-btn {
            background: #fef3c7;
            color: #d97706;
        }

        .edit-btn:hover {
            background: #d97706;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.2);
        }

        .delete-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        .delete-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .view-btn {
            background: #e0f2fe;
            color: #0284c7;
        }

        .view-btn:hover {
            background: #0284c7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
        }

        .approve-btn,
        .confirm-btn {
            background: #dcfce7;
            color: #16a34a;
        }

        .approve-btn:hover,
        .confirm-btn:hover {
            background: #16a34a;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
        }

        /* ===================== Report Problems redesign (scoped to .rp-*) ===================== */
        /* Submit Report on top, Recent Reports below — stacked at every screen size. */
        .rp-grid { display: flex; flex-direction: column; gap: 20px; }

        .rp-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 640px) {
            .rp-form-row { grid-template-columns: 1fr; }
        }

        .rp-form-group { margin-bottom: 14px; }
        .rp-label { display: block; margin-bottom: 6px; font-weight: 700; font-size: 0.82rem; color: var(--text); }
        .rp-select,
        .rp-input,
        .rp-textarea {
            width: 100%;
            background: white;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text);
        }
        .rp-select:focus,
        .rp-input:focus,
        .rp-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        .rp-textarea { min-height: 80px; resize: vertical; }

        .rp-dropzone {
            border: 2px dashed #c7d9f5;
            border-radius: 18px;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            text-align: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #fafcff, #f3f8ff);
            transition: all 0.2s ease;
        }
        .rp-dropzone:hover {
            border-color: var(--primary);
            background: var(--primary-soft);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(15,76,129,0.08);
        }
        .rp-dropzone-icon-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
        .rp-dropzone-text { font-weight: 700; font-size: 0.92rem; color: var(--text); }
        .rp-dropzone-hint { font-size: 0.76rem; color: var(--muted); }
        .rp-preview-img { max-width: 100%; max-height: 200px; border-radius: 14px; display: none; box-shadow: 0 4px 14px rgba(15,23,42,0.1); }
        .rp-dropzone-actions { display: none; gap: 10px; margin-top: 10px; justify-content: center; }
        .rp-dropzone-actions button {
            border: none; border-radius: 999px; padding: 8px 18px;
            font-size: 0.78rem; font-weight: 700; cursor: pointer;
            transition: transform 0.15s ease;
        }
        .rp-dropzone-actions button:hover { transform: translateY(-1px); }
        .rp-btn-change { background: var(--primary-soft); color: var(--primary); }
        .rp-btn-remove { background: #fee2e2; color: var(--danger); }

        .rp-urgent-toggle {
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(180deg, #fff7ed, #fffaf3);
            border: 2px solid #ffedd5;
            border-radius: 18px;
            padding: 16px 20px;
            height: 100%;
            box-sizing: border-box;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .rp-urgent-toggle:hover { border-color: var(--accent); box-shadow: 0 8px 20px rgba(245,158,11,0.1); }
        .rp-urgent-toggle input { width: 20px; height: 20px; accent-color: var(--accent); cursor: pointer; flex-shrink: 0; }
        .rp-urgent-icon-circle {
            width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
            background: #ffedd5; color: var(--accent);
            display: flex; align-items: center; justify-content: center; font-size: 1rem;
        }
        .rp-urgent-toggle-text strong { display: block; color: #9a3412; font-size: 0.9rem; }
        .rp-urgent-toggle-text span { color: #c2410c; font-size: 0.76rem; }

        .rp-send-btn {
            border: none;
            border-radius: 999px;
            padding: 14px 36px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 24px rgba(15,76,129,0.22);
            transition: all 0.2s ease;
        }
        .rp-send-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(15,76,129,0.3); }
        .rp-send-btn:active { transform: translateY(0); }

        /* Decorative backdrop behind the Screenshot/Urgent/Send block so the
           left/right margins around the centered content read as a designed
           panel instead of dead whitespace. */
        .rp-finish-wrap {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            padding: 28px 20px 32px;
            margin-top: 6px;
            background: linear-gradient(135deg, #f7fbff 0%, #fff9f2 100%);
            border: 1px solid var(--border);
        }
        .rp-finish-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 6% 15%, var(--primary-soft) 0, transparent 32%),
                radial-gradient(circle at 94% 12%, #ffedd5 0, transparent 30%),
                radial-gradient(circle at 8% 90%, #ffedd5 0, transparent 28%),
                radial-gradient(circle at 95% 92%, var(--primary-soft) 0, transparent 30%);
            pointer-events: none;
        }
        .rp-finish-decor {
            position: absolute;
            font-size: 3.2rem;
            color: var(--primary);
            opacity: 0.07;
            pointer-events: none;
            transform: rotate(-12deg);
        }
        .rp-finish-decor.rp-decor-tl { top: 10px; left: 18px; }
        .rp-finish-decor.rp-decor-tr { top: 10px; right: 18px; color: var(--accent); transform: rotate(14deg); }
        .rp-finish-decor.rp-decor-bl { bottom: 6px; left: 22px; color: var(--accent); transform: rotate(10deg); }
        .rp-finish-decor.rp-decor-br { bottom: 6px; right: 22px; transform: rotate(-10deg); }
        @media (max-width: 640px) {
            .rp-finish-decor { display: none; }
        }
        .rp-finish-content { position: relative; z-index: 1; }

        .rp-table tbody tr { cursor: pointer; }
        .rp-table tbody tr:hover td { background-color: var(--bg); }
        .rp-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 999px;
            font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
        }
        .rp-badge-category { background: #f1f5f9; color: #475569; }
        .rp-badge-critical { background: #fee2e2; color: #b91c1c; }
        .rp-badge-medium { background: #fef3c7; color: #b45309; }
        .rp-status-pending { background: #fee2e2; color: #ef4444; }
        .rp-status-progress { background: #fef3c7; color: #d97706; }
        .rp-status-resolved { background: #d1fae5; color: #059669; }
        .rp-empty { text-align: center; padding: 40px 20px; color: var(--muted); }
        .rp-empty i { font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5; }
    </style>
</head>
<body>
    <div class="admin-shell">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="sidebar" id="mainSidebar">
            <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="brand-block">
                <div class="brand-logo">
                    <img src="../../images/Heydream Logo.png" alt="HeyDream Logo">
                </div>
                <div class="eyebrow">Partner Portal</div>
                <h2><?= htmlspecialchars($profile['business_display_name'] ?: $partner['company_name'] ?: 'Partner') ?></h2>
            </div>
            <nav class="nav-list">
                <a href="partner-dashboard.php" class="<?= $section === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="partner-dashboard.php?section=bookings" class="<?= $section === 'bookings' ? 'active' : '' ?>"><i class="fas fa-book-open"></i> Bookings</a>
                <a href="partner-dashboard.php?section=profile" class="<?= $section === 'profile' ? 'active' : '' ?>"><i class="fas fa-user-tie"></i> My Profile</a>
                <a href="partner-content-manager.php" class="nav-item"><i class="fas fa-edit"></i> Content Manager</a>
                <a href="partner-dashboard.php?section=report-problems" class="<?= $section === 'report-problems' ? 'active' : '' ?>"><i class="fas fa-headset"></i> Report problems</a>
            </nav>
            <div class="sidebar-card">
                <strong><?= htmlspecialchars($profile['business_display_name'] ?: $partner['company_name']) ?></strong><br>
                <span><?= htmlspecialchars($partner['contact_person'] ?: 'Partner contact') ?></span>
            </div>
        </aside>

        <main class="main-area">
            <div class="mobile-topbar">
                <button class="menu-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="page-title"><?= $section === 'bookings' ? 'Bookings' : ($section === 'profile' ? 'My Profile' : ($section === 'report-problems' ? 'Report problems' : 'Dashboard')) ?></span>
                <a href="partner-logout.php" class="mobile-logout-btn" aria-label="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
            <div class="dashboard-hero">
                <div class="dashboard-hero-copy">
                    <div class="eyebrow"><?= $section === 'partner-content-manager' ? 'Content Manager' : ($section === 'bookings' ? 'Bookings' : ($section === 'profile' ? 'My Profile' : ($section === 'report-problems' ? 'Report problems' : 'Dashboard'))) ?></div>
                    <h1>Good to see you, <?= htmlspecialchars($partner['contact_person'] ?: $profile['business_display_name'] ?: $partner['company_name']) ?>.</h1>
                    <p class="hero-note">
                        <?= $section === 'dashboard' ? 'Your partner portal hub for high-value bookings, package growth, and revenue monitoring.' : 'Manage your partnership activity in a smooth, modern workspace.' ?>
                    </p>
                    <?php if ($section === 'dashboard'): ?>
                        <div class="hero-badges">
                            <span class="status-pill <?= htmlspecialchars($partner['status']) ?>"><?= strtoupper(htmlspecialchars($partner['status'])) ?></span>
                            <span class="status-pill">Packages <?= count($uploads) ?></span>
                            <span class="status-pill">Bookings <?= (int)($partnerBookingStats['total_bookings'] ?? 0) ?></span>
                            <span class="status-pill">Reports <?= count($reports) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="dashboard-hero-actions">
                    <?php if ($section === 'dashboard'): ?>
                        <a class="pill-btn primary" href="partner-content-manager.php"><i class="fas fa-upload"></i> Upload Package</a>
                        <a class="pill-btn" href="partner-dashboard.php?section=bookings"><i class="fas fa-book-open"></i> View Bookings</a>
                        <a class="pill-btn" href="partner-dashboard.php?section=profile"><i class="fas fa-user-tie"></i> Profile</a>
                    <?php endif; ?>
                    <a class="pill-btn logout-btn" href="partner-logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <?php if ($successMessage !== ''): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
                <section class="panel dashboard-hero-panel">
                    <div class="panel-head">
                        <div>
                            <h3>Partner Performance</h3>
                            <p class="muted">A polished snapshot of your revenue, bookings, and package health.</p>
                        </div>
                        <span class="status-pill <?= htmlspecialchars($partner['status']) ?>"><?= strtoupper(htmlspecialchars($partner['status'])) ?></span>
                    </div>
                    <div class="dashboard-primary-grid">
                        <div class="dashboard-mini-card">
                            <div class="card-label">Total Bookings</div>
                            <div class="card-value"><?= (int)($partnerBookingStats['total_bookings'] ?? 0) ?></div>
                            <div class="card-note">Bookings from all partner listings</div>
                        </div>
                        <div class="dashboard-mini-card">
                            <div class="card-label">Paid Revenue</div>
                            <div class="card-value">₱<?= number_format((float)($partnerBookingStats['paid_revenue'] ?? 0), 2) ?></div>
                            <div class="card-note">Collected from confirmed orders</div>
                        </div>
                        <div class="dashboard-mini-card">
                            <div class="card-label">Pending Revenue</div>
                            <div class="card-value">₱<?= number_format((float)($partnerBookingStats['pending_revenue'] ?? 0), 2) ?></div>
                            <div class="card-note">Awaiting payment or confirmation</div>
                        </div>
                        <div class="dashboard-mini-card">
                            <div class="card-label">Packages Listed</div>
                            <div class="card-value"><?= count($uploads) ?></div>
                            <div class="card-note">Latest listings and active offers</div>
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
                        <h3>Uploaded Packages</h3>
                        <span class="muted">Packages created or uploaded through your partnership Content Manager</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Package</th>
                                    <th>Destination</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($uploads)): ?>
                                    <tr><td colspan="5" class="muted">No packages uploaded yet. Use the Content Manager to add a package.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($uploads as $upload): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($upload['package_name'] ?: 'Untitled package') ?></strong><br>
                                                <span class="muted"><?= htmlspecialchars($upload['duration'] ?: 'Duration not added') ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($upload['destination_name'] ?: 'Not specified') ?></td>
                                            <td>₱<?= number_format((float)($upload['price'] ?? 0), 2) ?></td>
                                            <td><span class="status-badge <?= htmlspecialchars($upload['upload_status']) ?>"><?= htmlspecialchars(ucfirst($upload['upload_status'] ?: 'pending')) ?></span></td>
                                            <td><?= htmlspecialchars(date('M d, Y', strtotime($upload['created_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
            <?php elseif ($section === 'bookings'): ?>
                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h3>Bookings from Partner Packages</h3>
                            <span class="muted">List of customer bookings for your uploaded partnership packages.</span>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <a class="pill-btn" href="partner-dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        </div>
                    </div>

                    <form method="get" class="filter-form" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                        <input type="hidden" name="section" value="bookings">
                        <input type="text" name="search" placeholder="Search by customer or package..." value="<?= htmlspecialchars($searchQuery) ?>" style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                        <select name="status" style="padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="submit-btn" style="padding: 10px 20px; margin: 0;"><i class="fas fa-search"></i> Search</button>
                        <?php if ($searchQuery || $statusFilter): ?>
                            <a href="?section=bookings" class="pill-btn" style="padding: 10px 20px; line-height: 1.5; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                    <!-- Stats Cards styled like admin dashboard -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div style="background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 52px; height: 52px; border-radius: 12px; background: #e3f2fd; display: flex; align-items: center; justify-content: center; font-size: 24px;">📦</div>
                            <div>
                                <div style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Total Bookings</div>
                                <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; letter-spacing: -0.5px;"><?= (int)($partnerBookingStats['total_bookings'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div style="background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 52px; height: 52px; border-radius: 12px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; font-size: 24px;">₱</div>
                            <div>
                                <div style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Paid Revenue</div>
                                <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; letter-spacing: -0.5px;">₱<?= number_format((float)($partnerBookingStats['paid_revenue'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                        <div style="background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 52px; height: 52px; border-radius: 12px; background: #fffbeb; display: flex; align-items: center; justify-content: center; font-size: 24px;">⏳</div>
                            <div>
                                <div style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Pending Revenue</div>
                                <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; letter-spacing: -0.5px;">₱<?= number_format((float)($partnerBookingStats['pending_revenue'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin-style data table -->
                    <div style="background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; overflow: hidden; margin-top: 0;">
                        <div style="padding: 20px 28px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to right, #fafafa, #ffffff);">
                            <h2 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; margin: 0;">
                                <i class="fas fa-list-ul" style="color: hsl(35, 100%, 55%);"></i>
                                All Bookings
                                <span style="font-size: 0.85rem; color: #64748b; font-weight: 500;">(<?= count($paginatedBookings) ?> shown)</span>
                            </h2>
                        </div>
                        <div style="width: 100%; overflow-x: auto;">
                            <table style="width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px;">
                                <thead>
                                    <tr>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: left;">PHONE</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: left;">CUSTOMER</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: left;">SERVICE #</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: left;">APPLIED ON</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: left;">PACKAGE</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: left;">TRAVEL DATE</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: center;">PAYMENT</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: center;">STATUS</th>
                                        <th style="background: #f8fafc; padding: 14px 20px; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; text-align: center;">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($paginatedBookings)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 60px 20px; color: #64748b; font-size: 0.95rem;">
                                                <div style="font-size: 3rem; margin-bottom: 12px;">📭</div>
                                                <strong>No bookings found</strong><br>
                                                <span style="font-size: 0.85rem;">No customer bookings match your current filters.</span>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($paginatedBookings as $booking): ?>
                                            <?php
                                                $displayPackage = $booking['partner_package_name'] ?: $booking['package_name'];
                                                $bStatus = strtolower($booking['booking_status'] ?: 'pending');
                                                $pStatus = strtolower($booking['payment_status'] ?: 'unpaid');

                                                // Booking status badge styles (matching admin dashboard)
                                                $bBadgeStyle = match($bStatus) {
                                                    'confirmed'  => 'background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;',
                                                    'completed'  => 'background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;',
                                                    'cancelled'  => 'background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;',
                                                    default      => 'background:#fffbeb; color:#b45309; border:1px solid #fde68a;',
                                                };
                                                // Payment badge
                                                $pBadgeStyle = $pStatus === 'paid'
                                                    ? 'background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;'
                                                    : 'background:#fffbeb; color:#b45309; border:1px solid #fde68a;';
                                            ?>
                                            <tr style="transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-weight: 700;">
                                                    <?= htmlspecialchars($booking['phone'] ?: 'N/A') ?>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; color: #1e293b;">
                                                    <span style="font-weight: 700; color: #4f46e5;"><?= htmlspecialchars($booking['full_name'] ?: '—') ?></span>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; color: #1e293b;">
                                                    <strong><?= htmlspecialchars($booking['booking_number'] ?: '—') ?></strong>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; color: #1e293b;">
                                                    <div style="font-weight: 700; color: #1e293b;"><?= date('M j, Y', strtotime($booking['created_at'])) ?></div>
                                                    <div style="font-size: 0.75rem; color: #64748b;"><?= date('h:i A', strtotime($booking['created_at'])) ?></div>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; color: #334155;">
                                                    <?= htmlspecialchars($displayPackage ?: 'Package not listed') ?>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 600;">
                                                    <?= htmlspecialchars($booking['travel_date'] ?: '—') ?>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                                    <span style="padding: 6px 14px; border-radius: 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; <?= $pBadgeStyle ?>">
                                                        <?= strtoupper($pStatus) ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                                    <span style="padding: 6px 14px; border-radius: 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; <?= $bBadgeStyle ?>">
                                                        <?= strtoupper($bStatus) ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 16px 20px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                        <button type="button" class="view-btn"
                                                            onclick="openBookingDetailModal(<?= htmlspecialchars(json_encode($booking)) ?>)"
                                                            title="Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="edit-btn"
                                                            onclick="openEditBookingModal(<?= htmlspecialchars(json_encode($booking)) ?>)"
                                                            title="Edit Booking">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="delete-btn"
                                                            onclick="confirmDeleteBooking(<?= $booking['id'] ?>)"
                                                            title="Delete Booking">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php if ($bStatus === 'pending'): ?>
                                                        <form method="post" action="partner-dashboard.php?section=bookings" style="display:inline;" onsubmit="return confirm('Confirm this booking?');">
                                                            <input type="hidden" name="action" value="confirm_booking">
                                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                            <button type="submit" class="approve-btn" title="Confirm Booking">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div style="display: flex; gap: 6px; margin-top: 20px; justify-content: flex-end;">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?section=bookings&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>&p=<?= $i ?>"
                                   style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;text-decoration:none;font-size:0.85rem;font-weight:600;transition:all 0.2s;
                                   <?= $i === $currentPage ? 'background:hsl(230,60%,50%);color:white;box-shadow:0 4px 12px rgba(59,70,163,0.3);' : 'background:white;color:#334155;border:1px solid #e2e8f0;' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Booking Detail Modal — matches admin dashboard "Booking and Customer Details" -->
                    <div id="bookingDetailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); z-index:10000; justify-content:center; align-items:center;">
                        <div style="background:white; border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.25); max-width:700px; width:92%; max-height:90vh; overflow-y:auto; animation:slideIn 0.25s ease;">
                            <!-- Header -->
                            <div style="display:flex; align-items:center; gap:16px; padding:24px 28px; border-bottom:1px solid #f1f5f9; background:linear-gradient(to right,#fafafa,#ffffff); border-radius:20px 20px 0 0;">
                                <div style="flex:1;">
                                    <h2 style="margin:0;font-size:1.15rem;font-weight:700;color:#0f172a;" id="bm_modal_title">Booking and Customer Details</h2>
                                </div>
                                <button type="button" onclick="document.getElementById('bookingDetailModal').style.display='none'; document.body.style.overflow='';"
                                    style="width:38px;height:38px;background:none;border:1px solid #e2e8f0;border-radius:10px;font-size:18px;cursor:pointer;color:#64748b;transition:all 0.2s;"
                                    onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">✕</button>
                            </div>
                            <!-- Body — matches admin's confirmation-details exactly -->
                            <div style="padding:24px 28px;" id="bm_modal_body">
                                <div class="confirmation-details" style="font-family:inherit;">
                                    <h4 style="color:#1e293b;font-size:1.1rem;font-weight:800;border-bottom:2px solid #f1f5f9;padding-bottom:12px;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
                                        <i class="fas fa-ticket-alt" style="color:#0284c7;"></i>
                                        <span id="bm_section_title">Booking and Customer Details</span>
                                    </h4>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:25px;line-height:1.6;font-size:0.95rem;">
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Booking Number</strong> <span style="color:#0f172a;font-weight:600;" id="bm_id">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Receipt Number</strong> <span style="color:#6366f1;font-weight:800;font-family:monospace;" id="bm_receipt">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Booking Date</strong> <span style="color:#0f172a;font-weight:600;" id="bm_created">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Customer Name</strong> <span style="color:#0f172a;font-weight:600;" id="bm_name">—</span></div>
                                        <div style="min-width:0;"><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Email</strong> <span style="color:#0f172a;font-weight:600;word-break:break-all;" id="bm_email">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Phone</strong> <span style="color:#0f172a;font-weight:600;" id="bm_phone">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Address</strong> <span style="color:#0f172a;font-weight:600;" id="bm_address">N/A</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Location / Destination</strong> <span style="color:#0f172a;font-weight:600;" id="bm_destination">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Package</strong> <span style="color:#0f172a;font-weight:600;" id="bm_package">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Duration</strong> <span style="color:#0f172a;font-weight:600;" id="bm_duration">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Travel Date</strong> <span style="color:#0f172a;font-weight:600;" id="bm_travel">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Travelers</strong> <span style="color:#0f172a;font-weight:600;" id="bm_travelers">—</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Price per Person</strong> <span style="color:#0f172a;font-weight:600;" id="bm_price_per_person">—</span></div>
                                        <!-- Total Amount — full-width row -->
                                        <div style="grid-column:1/-1;background:#f8fafc;padding:12px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;border:1px solid #e2e8f0;margin-top:5px;">
                                            <strong style="color:#1e293b;font-size:1rem;">Total Amount</strong>
                                            <span style="color:#0284c7;font-weight:800;font-size:1.1rem;" id="bm_amount">—</span>
                                        </div>
                                        <!-- Status badges row -->
                                        <div style="grid-column:1/-1;display:flex;justify-content:space-between;gap:0;align-items:flex-start;flex-wrap:wrap;padding:14px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                                            <div style="text-align:center;flex:1;"><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Visa Status</strong><span id="bm_visa_badge">—</span></div>
                                            <div style="text-align:center;flex:1;"><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Booking Status</strong><span id="bm_status">—</span></div>
                                            <div style="text-align:center;flex:1;"><strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Payment Status</strong><span id="bm_payment_status">—</span></div>
                                        </div>
                                    </div>
                                    <!-- Payment Information -->
                                    <div id="bm_payment_wrap" style="display:none;margin-top:20px;padding:16px;background:#f0fdf4;border-radius:12px;border:1px solid #bbf7d0;">
                                        <h5 style="margin:0 0 12px;color:#15803d;font-weight:700;font-size:0.95rem;"><i class="fas fa-credit-card" style="color:#16a34a;margin-right:6px;"></i> Payment Information</h5>
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            <div id="bm_payment_method_row" style="display:none;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Method:</span><span style="color:#0f172a;font-weight:700;text-transform:capitalize;" id="bm_payment_method"></span></div>
                                            <div id="bm_payment_ref_row" style="display:none;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Reference #:</span><span style="color:#0f172a;font-weight:700;font-family:monospace;" id="bm_payment_ref"></span></div>
                                            <div id="bm_payment_proof_wrap" style="display:none;margin-top:8px;">
                                                <span style="color:#64748b;font-weight:600;font-size:0.9rem;display:block;margin-bottom:8px;">Payment Proof:</span>
                                                <div id="bm_payment_proof_content"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Special Requests -->
                                    <div id="bm_special_req_wrap" style="display:none;margin-top:20px;padding:16px;background:#eff6ff;border-radius:12px;border:1px solid #bfdbfe;">
                                        <h5 style="margin:0 0 8px;color:#1e3a8a;font-weight:700;font-size:0.95rem;"><i class="fas fa-star" style="color:#3b82f6;margin-right:6px;"></i> Special Requests</h5>
                                        <div style="font-size:0.9rem;line-height:1.6;color:#1e40af;" id="bm_special_req"></div>
                                    </div>
                                    <!-- Flight Details -->
                                    <div id="bm_flight_wrap" style="display:none;margin-top:20px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
                                        <h5 style="margin:0 0 8px;color:#1e293b;font-weight:700;font-size:0.95rem;"><i class="fas fa-plane-departure" style="color:#6366f1;margin-right:6px;"></i> Flight Details</h5>
                                        <div style="font-size:0.9rem;line-height:1.6;color:#334155;white-space:pre-wrap;" id="bm_flight_details"></div>
                                    </div>
                                    <!-- Admin Notes -->
                                    <div id="bm_notes_wrap" style="display:none;margin-top:15px;padding:16px;background:#fffcf0;border-radius:12px;border:1px dashed #ff9800;">
                                        <h5 style="margin:0 0 8px;color:#856404;font-weight:700;font-size:0.95rem;"><i class="fas fa-comment-dots" style="color:#eab308;margin-right:6px;"></i> Admin Notes</h5>
                                        <div style="font-size:0.9rem;font-style:italic;color:#333;" id="bm_notes"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div style="padding:18px 28px;border-top:1px solid #f1f5f9;background:linear-gradient(to right,#fafafa,#ffffff);display:flex;gap:10px;justify-content:flex-end;border-radius:0 0 20px 20px;">
                                <form id="bm_confirm_form" method="post" action="partner-dashboard.php?section=bookings" style="display:none;">
                                    <input type="hidden" name="action" value="confirm_booking">
                                    <input type="hidden" name="booking_id" id="bm_confirm_id" value="">
                                    <button type="submit" class="confirm-btn" onclick="return confirm('Confirm this booking?')" style="height:40px;padding:0 20px;font-size:0.9rem;font-weight:700;">
                                        <i class="fas fa-check"></i> Confirm Booking
                                    </button>
                                </form>
                                <button type="button" onclick="document.getElementById('bookingDetailModal').style.display='none'; document.body.style.overflow='';"
                                    style="height:40px;padding:0 20px;border-radius:10px;display:inline-flex;align-items:center;gap:8px;border:1px solid #e2e8f0;cursor:pointer;background:#e2e8f0;color:#334155;font-weight:600;font-size:0.9rem;transition:all 0.3s;"
                                    onmouseover="this.style.background='#cbd5e1'" onmouseout="this.style.background='#e2e8f0'">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>


                    <script>
                    function openBookingDetailModal(b) {
                        const fmt = (v) => v || 'N/A';
                        const fmtDate = (v) => {
                            if (!v || v === '0000-00-00' || v === '0000-00-00 00:00:00') return '—';
                            return new Date(v).toLocaleDateString('en-PH', {year:'numeric', month:'long', day:'numeric'});
                        };
                        const fmtMoney = (v) => {
                            const n = parseFloat(v);
                            return isNaN(n) ? '₱0.00' : '₱' + n.toLocaleString('en-PH', {minimumFractionDigits:2});
                        };
                        const escHtml = (s) => s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';

                        // Header
                        document.getElementById('bm_modal_title').textContent = 'Booking and Customer Details';
                        document.getElementById('bm_section_title').textContent = 'Booking and Customer Details';

                        // Fields
                        document.getElementById('bm_id').textContent = b.booking_number || b.id || '—';
                        document.getElementById('bm_receipt').textContent = 'HD-REC-' + String(b.id || 0).padStart(6, '0');
                        document.getElementById('bm_created').textContent = fmtDate(b.created_at);
                        document.getElementById('bm_name').textContent = fmt(b.full_name);
                        document.getElementById('bm_email').textContent = fmt(b.email);
                        document.getElementById('bm_phone').textContent = fmt(b.phone);
                        document.getElementById('bm_address').textContent = b.address || 'N/A';
                        document.getElementById('bm_destination').textContent = fmt(b.destination_name || b.package_location);
                        document.getElementById('bm_package').textContent = fmt(b.partner_package_name || b.package_name);
                        document.getElementById('bm_duration').textContent = fmt(b.package_duration);
                        document.getElementById('bm_travel').textContent = fmtDate(b.travel_date);
                        document.getElementById('bm_travelers').textContent = b.number_of_travelers || '—';
                        document.getElementById('bm_price_per_person').textContent = fmtMoney(b.price_per_person || 0);
                        document.getElementById('bm_amount').textContent = fmtMoney(b.total_amount);

                        // Booking status badge — matching admin style
                        const bStatus = (b.booking_status || 'pending').toLowerCase();
                        const bBadgeColors = {
                            confirmed: 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;',
                            completed: 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;',
                            cancelled:  'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
                            pending:    'background:#fffbeb;color:#b45309;border:1px solid #fde68a;'
                        };
                        const bBadge = bBadgeColors[bStatus] || bBadgeColors.pending;
                        document.getElementById('bm_status').innerHTML = `<span style="display:inline-flex;align-items:center;padding:6px 14px;border-radius:10px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;box-shadow:0 2px 4px rgba(0,0,0,0.05);${bBadge}">${bStatus.toUpperCase()}</span>`;

                        // Payment status badge
                        const pStatus = (b.payment_status || 'unpaid').toLowerCase();
                        const pBadge = pStatus === 'paid'
                            ? 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;'
                            : 'background:#fffbeb;color:#b45309;border:1px solid #fde68a;';
                        document.getElementById('bm_payment_status').innerHTML = `<span style="display:inline-flex;align-items:center;padding:6px 14px;border-radius:10px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;box-shadow:0 2px 4px rgba(0,0,0,0.05);${pBadge}">${pStatus.toUpperCase()}</span>`;

                        // Visa status badge
                        const vRaw = (b.visa_status || 'PENDING').toUpperCase();
                        const vBadge = vRaw === 'APPROVED' ? 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;'
                                     : vRaw === 'DECLINED'  ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;'
                                                            : 'background:#fffbeb;color:#b45309;border:1px solid #fde68a;';
                        document.getElementById('bm_visa_badge').innerHTML = `<span style="display:inline-flex;align-items:center;padding:6px 14px;border-radius:10px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;box-shadow:0 2px 4px rgba(0,0,0,0.05);${vBadge}">${vRaw}</span>`;

                        // Payment Information (method / reference / proof — matches admin's design)
                        const paymentWrap = document.getElementById('bm_payment_wrap');
                        if (b.payment_method || b.payment_reference || b.payment_proof) {
                            const methodRow = document.getElementById('bm_payment_method_row');
                            if (b.payment_method) {
                                document.getElementById('bm_payment_method').textContent = b.payment_method;
                                methodRow.style.display = 'flex';
                            } else {
                                methodRow.style.display = 'none';
                            }
                            const refRow = document.getElementById('bm_payment_ref_row');
                            if (b.payment_reference) {
                                document.getElementById('bm_payment_ref').textContent = b.payment_reference;
                                refRow.style.display = 'flex';
                            } else {
                                refRow.style.display = 'none';
                            }
                            const proofWrap = document.getElementById('bm_payment_proof_wrap');
                            if (b.payment_proof) {
                                const proofUrl = normalizeAssetPath(b.payment_proof);
                                const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(b.payment_proof);
                                document.getElementById('bm_payment_proof_content').innerHTML = isImage
                                    ? `<a href="${proofUrl}" target="_blank" title="Click to view full image"><img src="${proofUrl}" alt="Payment Proof" style="max-width:100%;max-height:220px;border-radius:10px;border:2px solid #bbf7d0;box-shadow:0 4px 12px rgba(0,0,0,0.1);cursor:pointer;object-fit:cover;display:block;"></a><p style="font-size:0.78rem;color:#64748b;margin-top:6px;text-align:center;"><i class="fas fa-search-plus" style="margin-right:4px;"></i>Click image to view full size</p>`
                                    : `<a href="${proofUrl}" target="_blank" class="view-btn" style="padding:8px 16px;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;border-radius:8px;"><i class="fas fa-file-download"></i> Download / View Receipt</a>`;
                                proofWrap.style.display = 'block';
                            } else {
                                proofWrap.style.display = 'none';
                            }
                            paymentWrap.style.display = 'block';
                        } else {
                            paymentWrap.style.display = 'none';
                        }

                        // Special requests
                        const srWrap = document.getElementById('bm_special_req_wrap');
                        if (b.special_requests) {
                            document.getElementById('bm_special_req').textContent = b.special_requests;
                            srWrap.style.display = 'block';
                        } else {
                            srWrap.style.display = 'none';
                        }

                        // Flight Details
                        const flightWrap = document.getElementById('bm_flight_wrap');
                        if (b.flight_details) {
                            document.getElementById('bm_flight_details').textContent = b.flight_details;
                            flightWrap.style.display = 'block';
                        } else {
                            flightWrap.style.display = 'none';
                        }

                        // Admin Notes
                        const notesWrap = document.getElementById('bm_notes_wrap');
                        if (b.admin_notes) {
                            document.getElementById('bm_notes').textContent = '"' + b.admin_notes + '"';
                            notesWrap.style.display = 'block';
                        } else {
                            notesWrap.style.display = 'none';
                        }

                        // Show Confirm button only for pending bookings
                        document.getElementById('bm_confirm_id').value = b.id;
                        document.getElementById('bm_confirm_form').style.display = bStatus === 'pending' ? 'inline-flex' : 'none';

                        document.getElementById('bookingDetailModal').style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }

                    document.getElementById('bookingDetailModal').addEventListener('click', function(e) {
                        if (e.target === this) { this.style.display='none'; document.body.style.overflow=''; }
                    });
                    document.addEventListener('keydown', function(e) {
                        if (e.key==='Escape') {
                            document.getElementById('bookingDetailModal').style.display='none';
                            document.getElementById('editBookingModal').style.display='none';
                            document.body.style.overflow='';
                        }
                    });

                    // ── Edit Booking Modal ────────────────────────────────
                    function openEditBookingModal(b) {
                        document.getElementById('edit_booking_id').value       = b.id;
                        document.getElementById('edit_booking_status').value   = b.booking_status || 'pending';
                        document.getElementById('edit_payment_status').value   = b.payment_status || 'unpaid';
                        document.getElementById('edit_visa_status').value      = (b.visa_status && b.visa_status !== 'N/A') ? b.visa_status.toUpperCase() : 'PENDING';
                        document.getElementById('edit_price_per_person').value = b.price_per_person || 0;
                        document.getElementById('edit_total_amount').value     = b.total_amount || 0;
                        document.getElementById('edit_travel_date').value      = b.travel_date || '';
                        document.getElementById('edit_travelers').value        = b.number_of_travelers || 1;
                        document.getElementById('edit_travel_documents').checked = !!(b.travel_documents == 1 || b.travel_documents === true);
                        document.getElementById('edit_ready_for_travel').checked = !!(b.ready_for_travel == 1 || b.ready_for_travel === true);
                        document.getElementById('edit_flight_details').value   = b.flight_details || '';
                        document.getElementById('edit_admin_notes').value      = b.admin_notes || '';
                        document.getElementById('edit_bm_label').textContent   = '#' + (b.booking_number || b.id);
                        document.getElementById('edit_customer_name').textContent = b.full_name || '—';

                        // Read-only Payment Proof from Customer — matches admin's edit-booking design exactly
                        const editProofWrap = document.getElementById('edit_payment_proof_wrap');
                        if (b.payment_method || b.payment_reference || b.payment_proof) {
                            const methodRow = document.getElementById('edit_payment_method_row');
                            if (b.payment_method) {
                                document.getElementById('edit_payment_method_val').textContent = b.payment_method;
                                methodRow.style.display = 'flex';
                            } else { methodRow.style.display = 'none'; }
                            const refRow = document.getElementById('edit_payment_ref_row');
                            if (b.payment_reference) {
                                document.getElementById('edit_payment_ref_val').textContent = b.payment_reference;
                                refRow.style.display = 'flex';
                            } else { refRow.style.display = 'none'; }
                            const proofContent = document.getElementById('edit_payment_proof_content');
                            if (b.payment_proof) {
                                const proofUrl = normalizeAssetPath(b.payment_proof);
                                const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(b.payment_proof);
                                proofContent.innerHTML = isImage
                                    ? `<a href="${proofUrl}" target="_blank" title="Click to view full image"><img src="${proofUrl}" alt="Payment Proof" style="max-width:100%;max-height:220px;border-radius:12px;border:2px solid #bbf7d0;box-shadow:0 4px 12px rgba(0,0,0,0.1);cursor:zoom-in;object-fit:contain;display:block;background:#f8fafc;"></a>`
                                    : `<a href="${proofUrl}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#16a34a;color:white;padding:10px 18px;border-radius:10px;font-size:0.88rem;font-weight:600;text-decoration:none;"><i class="fas fa-file-download"></i> Open / Download Receipt</a>`;
                            } else {
                                proofContent.innerHTML = `<div style="color:#64748b;font-size:0.88rem;padding:10px;background:#f8fafc;border-radius:8px;border:1px dashed #cbd5e1;text-align:center;margin-top:6px;"><i class="fas fa-image" style="margin-right:6px;opacity:0.5;"></i>No payment screenshot uploaded yet</div>`;
                            }
                            editProofWrap.style.display = 'block';
                        } else {
                            editProofWrap.style.display = 'none';
                        }

                        document.getElementById('editBookingModal').style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }

                    function updatePartnerEditTotal() {
                        const travelers = parseFloat(document.getElementById('edit_travelers').value) || 0;
                        const pricePerPerson = parseFloat(document.getElementById('edit_price_per_person').value) || 0;
                        document.getElementById('edit_total_amount').value = (travelers * pricePerPerson).toFixed(2);
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('editBookingModal').addEventListener('click', function(e) {
                            if (e.target === this) { this.style.display='none'; document.body.style.overflow=''; }
                        });
                    });

                    // ── Delete Booking ────────────────────────────────────
                    function confirmDeleteBooking(id) {
                        if (!confirm('Are you sure you want to delete this booking? This action cannot be undone.')) return;
                        document.getElementById('delete_booking_id').value = id;
                        document.getElementById('deleteBookingForm').submit();
                    }
                    </script>

                    <!-- Edit Booking Modal (matching admin dashboard style) -->
                    <div id="editBookingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); z-index:10000; justify-content:center; align-items:center;">
                        <div style="background:white; border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.25); max-width:560px; width:92%; max-height:88vh; overflow-y:auto; animation:slideIn 0.25s ease;">
                            <!-- Header -->
                            <div style="display:flex; align-items:center; gap:16px; padding:24px 28px; border-bottom:1px solid #f1f5f9; background:linear-gradient(to right,#fafafa,#ffffff); border-radius:20px 20px 0 0;">
                                <div style="width:48px;height:48px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;">✏️</div>
                                <div style="flex:1;">
                                    <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:#0f172a;">Edit Booking <span id="edit_bm_label" style="color:#d97706;"></span></h2>
                                    <p style="margin:4px 0 0;font-size:0.8rem;color:#64748b;">Customer: <span id="edit_customer_name" style="font-weight:600;color:#1e293b;"></span></p>
                                </div>
                                <button onclick="document.getElementById('editBookingModal').style.display='none'; document.body.style.overflow='';"
                                    style="width:38px;height:38px;background:none;border:1px solid #e2e8f0;border-radius:10px;font-size:18px;cursor:pointer;color:#64748b;transition:all 0.2s;"
                                    onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">✕</button>
                            </div>
                            <!-- Form -->
                            <form method="post" action="partner-dashboard.php?section=bookings" style="padding:24px 28px;">
                                <input type="hidden" name="action" value="edit_booking">
                                <input type="hidden" name="booking_id" id="edit_booking_id" value="">

                                <div style="margin-bottom:20px;">
                                    <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Booking Status</label>
                                    <select name="booking_status" id="edit_booking_status" style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-family:inherit;font-size:0.9rem;">
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>

                                <div style="margin-bottom:20px;">
                                    <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Payment Status</label>
                                    <select name="payment_status" id="edit_payment_status" style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-family:inherit;font-size:0.9rem;">
                                        <option value="unpaid">Unpaid</option>
                                        <option value="paid">Paid</option>
                                        <option value="refunded">Refunded</option>
                                    </select>
                                </div>

                                <div style="margin-bottom:20px;">
                                    <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Visa Status</label>
                                    <select name="visa_status" id="edit_visa_status" style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-family:inherit;font-size:0.9rem;">
                                        <option value="PENDING">Pending</option>
                                        <option value="APPROVED">Approved</option>
                                        <option value="DECLINED">Declined</option>
                                    </select>
                                </div>

                                <div style="margin-bottom:20px;">
                                    <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Travel Date</label>
                                    <input type="date" name="travel_date" id="edit_travel_date" style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-family:inherit;font-size:0.9rem;">
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; margin-bottom:20px;">
                                    <div>
                                        <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Travelers</label>
                                        <input type="number" min="1" name="number_of_travelers" id="edit_travelers" oninput="updatePartnerEditTotal()" style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:12px;font-family:inherit;font-size:0.9rem;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Price / Person</label>
                                        <input type="number" step="0.01" min="0" name="price_per_person" id="edit_price_per_person" oninput="updatePartnerEditTotal()" style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:12px;font-family:inherit;font-size:0.9rem;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Total Amount</label>
                                        <input type="number" step="0.01" name="total_amount" id="edit_total_amount" readonly style="width:100%;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:12px;padding:12px;font-family:inherit;font-size:0.9rem;font-weight:800;color:#0284c7;cursor:not-allowed;">
                                    </div>
                                </div>

                                <div style="margin-bottom:25px;">
                                    <label style="display:flex;align-items:center;gap:6px;font-weight:700;font-size:0.85rem;color:#1e293b;margin-bottom:12px;"><i class="fas fa-tasks" style="color:#0284c7;"></i> Booking Tracking Steps</label>
                                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:15px;">
                                        <label style="display:flex;align-items:center;gap:12px;background:#f8fafc;padding:12px 16px;border-radius:12px;border:1px solid #e2e8f0;cursor:pointer;">
                                            <input type="checkbox" name="travel_documents" id="edit_travel_documents" value="1" style="width:20px;height:20px;accent-color:#ff9800;margin:0;cursor:pointer;">
                                            <span style="font-weight:600;color:#334155;font-size:0.95rem;"><i class="fas fa-file-alt" style="color:#ff9800;margin-right:8px;"></i> Travel Documents Prepared</span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:12px;background:#f8fafc;padding:12px 16px;border-radius:12px;border:1px solid #e2e8f0;cursor:pointer;">
                                            <input type="checkbox" name="ready_for_travel" id="edit_ready_for_travel" value="1" style="width:20px;height:20px;accent-color:#22c55e;margin:0;cursor:pointer;">
                                            <span style="font-weight:600;color:#334155;font-size:0.95rem;"><i class="fas fa-check-double" style="color:#22c55e;margin-right:8px;"></i> Ready for Travel</span>
                                        </label>
                                    </div>
                                    <div style="background:#eff6ff;padding:14px 16px;border-radius:12px;font-size:0.85rem;color:#1d4ed8;display:flex;align-items:flex-start;gap:10px;border:1px solid #bfdbfe;">
                                        <i class="fas fa-info-circle" style="margin-top:2px;"></i>
                                        <div>These steps are visible to the customer in their <strong>Booking Tracking</strong> view.</div>
                                    </div>
                                </div>

                                <div id="edit_payment_proof_wrap" style="display:none;margin-bottom:25px;padding:16px;background:#f0fdf4;border-radius:12px;border:1px solid #bbf7d0;">
                                    <h5 style="margin:0 0 12px;color:#15803d;font-weight:700;font-size:0.95rem;"><i class="fas fa-credit-card" style="color:#16a34a;margin-right:6px;"></i> Payment Proof from Customer</h5>
                                    <div style="display:flex;flex-direction:column;gap:8px;">
                                        <div id="edit_payment_method_row" style="display:none;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Method:</span><span id="edit_payment_method_val" style="color:#0f172a;font-weight:700;text-transform:capitalize;"></span></div>
                                        <div id="edit_payment_ref_row" style="display:none;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Reference #:</span><span id="edit_payment_ref_val" style="color:#0f172a;font-weight:700;font-family:monospace;"></span></div>
                                        <div id="edit_payment_proof_content"></div>
                                    </div>
                                </div>

                                <div style="margin-bottom:25px;">
                                    <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Flight Details <span style="color:#64748b;font-weight:normal;font-size:0.8rem;">(visible to customer in email)</span></label>
                                    <textarea name="flight_details" id="edit_flight_details" rows="3" placeholder="Enter flight number, departure/arrival times, etc..." style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:16px;box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);resize:vertical;font-family:inherit;font-size:0.9rem;"></textarea>
                                </div>

                                <div style="margin-bottom:24px;">
                                    <label style="display:block;margin-bottom:8px;font-weight:700;font-size:0.85rem;color:#1e293b;">Admin Notes <span style="color:#64748b;font-weight:normal;font-size:0.8rem;">(private note for this booking)</span></label>
                                    <textarea name="admin_notes" id="edit_admin_notes" rows="4" placeholder="Add any notes about this booking..." style="width:100%;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:16px;box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);resize:vertical;font-family:inherit;font-size:0.9rem;"></textarea>
                                </div>

                                <div style="background:#f1f5f9;padding:14px 16px;border-radius:12px;color:#334155;font-size:0.85rem;display:flex;align-items:center;gap:10px;border:1px solid #e2e8f0;margin-bottom:20px;">
                                    <i class="fas fa-circle-info" style="font-size:1.1rem;color:#64748b;"></i>
                                    <span>Changes are saved and reflected immediately.</span>
                                </div>

                                <div style="display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #f1f5f9; padding-top:20px;">
                                    <button type="button" onclick="document.getElementById('editBookingModal').style.display='none'; document.body.style.overflow='';"
                                        style="height:40px;padding:0 20px;border-radius:10px;border:1px solid #e2e8f0;cursor:pointer;background:#f8fafc;color:#334155;font-weight:600;font-size:0.9rem;transition:all 0.2s;"
                                        onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f8fafc'">Cancel</button>
                                    <button type="submit"
                                        style="height:40px;padding:0 20px;border-radius:10px;border:none;cursor:pointer;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;font-weight:700;font-size:0.9rem;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px;"
                                        onmouseover="this.style.opacity='0.9';this.style.transform='translateY(-1px)'" onmouseout="this.style.opacity='1';this.style.transform=''">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Hidden Delete Form -->
                    <form id="deleteBookingForm" method="post" action="partner-dashboard.php?section=bookings" style="display:none;">
                        <input type="hidden" name="action" value="delete_booking">
                        <input type="hidden" name="booking_id" id="delete_booking_id" value="">
                    </form>
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
                    $mpSocialLinks = parseSocialLinks($profile['social_media_links'] ?? '');
                ?>

                <div class="mp-wrap">
                    <!-- Partner Overview -->
                    <div class="mp-hero">
                        <div class="mp-hero-left">
                            <div class="mp-hero-logo">
                                <?php if (!empty($profile['logo_path'])): ?>
                                    <img src="<?= assetUrl($profile['logo_path']) ?>" alt="Logo">
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
                                        <label>Social Links</label>
                                        <div class="mp-social-input-grid">
                                            <div class="mp-social-input">
                                                <span class="mp-social-input-icon fb" title="Facebook"><i class="fa-brands fa-facebook-f"></i></span>
                                                <input type="url" name="social_link_facebook" placeholder="Facebook" title="Facebook page URL" value="<?= htmlspecialchars($mpSocialLinks['facebook']) ?>">
                                            </div>
                                            <div class="mp-social-input">
                                                <span class="mp-social-input-icon tiktok" title="TikTok"><i class="fa-brands fa-tiktok"></i></span>
                                                <input type="url" name="social_link_tiktok" placeholder="TikTok" title="TikTok profile URL" value="<?= htmlspecialchars($mpSocialLinks['tiktok']) ?>">
                                            </div>
                                            <div class="mp-social-input">
                                                <span class="mp-social-input-icon x" title="X (Twitter)"><i class="fa-brands fa-x-twitter"></i></span>
                                                <input type="url" name="social_link_x" placeholder="X / Twitter" title="X (Twitter) profile URL" value="<?= htmlspecialchars($mpSocialLinks['x']) ?>">
                                            </div>
                                            <div class="mp-social-input">
                                                <span class="mp-social-input-icon yt" title="YouTube"><i class="fa-brands fa-youtube"></i></span>
                                                <input type="url" name="social_link_youtube" placeholder="YouTube" title="YouTube channel URL" value="<?= htmlspecialchars($mpSocialLinks['youtube']) ?>">
                                            </div>
                                            <div class="mp-social-input">
                                                <span class="mp-social-input-icon ig" title="Instagram"><i class="fa-brands fa-instagram"></i></span>
                                                <input type="url" name="social_link_instagram" placeholder="Instagram" title="Instagram profile URL" value="<?= htmlspecialchars($mpSocialLinks['instagram']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mp-field">
                                        <label for="social_links_other">Other Links <small>(one per line &mdash; Discord, LinkedIn, Pinterest, etc.)</small></label>
                                        <textarea id="social_links_other" name="social_links_other" placeholder="https://discord.gg/yourinvite&#10;https://linkedin.com/company/yourbusiness"><?= htmlspecialchars(implode("\n", $mpSocialLinks['other'])) ?></textarea>
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
                                    <input type="hidden" name="social_link_facebook" value="<?= htmlspecialchars($mpSocialLinks['facebook']) ?>">
                                    <input type="hidden" name="social_link_tiktok" value="<?= htmlspecialchars($mpSocialLinks['tiktok']) ?>">
                                    <input type="hidden" name="social_link_x" value="<?= htmlspecialchars($mpSocialLinks['x']) ?>">
                                    <input type="hidden" name="social_link_youtube" value="<?= htmlspecialchars($mpSocialLinks['youtube']) ?>">
                                    <input type="hidden" name="social_link_instagram" value="<?= htmlspecialchars($mpSocialLinks['instagram']) ?>">
                                    <input type="hidden" name="social_links_other" value="<?= htmlspecialchars(implode("\n", $mpSocialLinks['other'])) ?>">

                                    <div class="mp-media-preview-wrap">
                                        <div class="mp-cover-preview">
                                            <?php if (!empty($profile['banner_image_path'])): ?>
                                                <img src="<?= assetUrl($profile['banner_image_path']) ?>" alt="Cover photo">
                                            <?php else: ?>
                                                <i class="fas fa-panorama"></i>
                                            <?php endif; ?>
                                        </div>
                                        <label class="mp-camera-btn mp-cover-camera-btn" for="mp_banner_image" title="Change cover photo"><i class="fas fa-camera"></i></label>

                                        <div class="mp-logo-preview">
                                            <?php if (!empty($profile['logo_path'])): ?>
                                                <img src="<?= assetUrl($profile['logo_path']) ?>" alt="Logo">
                                            <?php else: ?>
                                                <i class="fas fa-image"></i>
                                            <?php endif; ?>
                                        </div>
                                        <label class="mp-camera-btn mp-logo-camera-btn" for="mp_logo_image" title="Change logo"><i class="fas fa-camera"></i></label>
                                    </div>
                                    <input type="file" id="mp_logo_image" name="logo_image" accept="image/*" class="mp-media-file-input" onchange="this.form.submit()">
                                    <input type="file" id="mp_banner_image" name="banner_image" accept="image/*" class="mp-media-file-input" onchange="this.form.submit()">
                                </form>
                            </div>

                            <div class="mp-card">
                                <div class="mp-security-icon"><i class="fas fa-shield-halved"></i></div>
                                <h3 style="margin: 0 0 4px;">Account Security</h3>
                                <p style="margin: 0 0 16px; color: var(--muted); font-size: 0.88rem;">Keep your account safe and secure.</p>
                                <button type="button" class="mp-btn-secondary" onclick="pwOpenModal()"><i class="fas fa-key"></i> Change Password</button>
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

                <div class="pw-modal-overlay<?= $pwModalShouldOpen ? ' active' : '' ?>" id="pwModalOverlay">
                    <div class="pw-modal">
                        <div class="pw-modal-head">
                            <h3><i class="fas fa-key"></i> Change Password</h3>
                            <button type="button" class="pw-modal-close" onclick="pwCloseModal()"><i class="fas fa-xmark"></i></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mp-field">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                            </div>
                            <div class="mp-field">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="mp-field">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="pw-modal-actions">
                                <button class="mp-btn-primary" type="submit"><i class="fas fa-check"></i> Update Password</button>
                                <button class="mp-btn-secondary" type="button" onclick="pwCloseModal()">Cancel</button>
                            </div>
                        </form>
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

                    function pwOpenModal() {
                        document.getElementById('pwModalOverlay').classList.add('active');
                    }

                    function pwCloseModal() {
                        document.getElementById('pwModalOverlay').classList.remove('active');
                    }

                    document.getElementById('pwModalOverlay')?.addEventListener('click', function (e) {
                        if (e.target === this) pwCloseModal();
                    });
                </script>
            <?php else: ?>
                <?php
                    $rpCategories = ['Booking Issue', 'Payment Issue', 'Technical / Website Issue', 'Content / Package Issue', 'Account Issue', 'Other'];
                ?>
                <div class="rp-grid">
                    <section class="panel">
                        <div class="panel-head">
                            <h3>Submit a Report</h3>
                            <span class="muted">Share an issue with the HeyDream team.</span>
                        </div>
                        <form method="post" enctype="multipart/form-data" id="rpForm">
                            <input type="hidden" name="action" value="submit_report">

                            <div class="rp-form-row">
                                <div class="rp-form-group">
                                    <label class="rp-label" for="rp_category">Category <span style="color:var(--danger)">*</span></label>
                                    <select class="rp-select" id="rp_category" name="category" required>
                                        <option value="" hidden selected>-- Select a category --</option>
                                        <?php foreach ($rpCategories as $rpCat): ?>
                                            <option value="<?= htmlspecialchars($rpCat) ?>"><?= htmlspecialchars($rpCat) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="rp-form-group">
                                    <label class="rp-label" for="rp_subject">Subject <span style="color:var(--danger)">*</span></label>
                                    <input class="rp-input" type="text" id="rp_subject" name="subject" placeholder="Example: Upload issue" required>
                                </div>
                            </div>

                            <div class="rp-form-group">
                                <label class="rp-label" for="rp_message">What is the issue? <span style="color:var(--danger)">*</span></label>
                                <textarea class="rp-textarea" id="rp_message" name="message" placeholder="Tell us what went wrong or what you need help with." required></textarea>
                            </div>

                            <div class="rp-finish-wrap">
                                <i class="fas fa-camera-retro rp-finish-decor rp-decor-tl"></i>
                                <i class="fas fa-bolt rp-finish-decor rp-decor-tr"></i>
                                <i class="fas fa-headset rp-finish-decor rp-decor-bl"></i>
                                <i class="fas fa-paper-plane rp-finish-decor rp-decor-br"></i>

                                <div class="rp-finish-content">
                                    <div class="rp-form-group" style="text-align:center;">
                                        <label class="rp-label" style="text-align:center;">Screenshot <span style="color:var(--muted);font-weight:normal;">(optional)</span></label>
                                        <div class="rp-dropzone" id="rpDropzone" onclick="document.getElementById('rp_screenshot').click()" style="max-width:420px;margin:0 auto;">
                                            <div id="rpDropzonePrompt">
                                                <div class="rp-dropzone-icon-circle" style="margin:0 auto 4px;"><i class="fas fa-camera"></i></div>
                                                <div class="rp-dropzone-text">Click to upload a screenshot</div>
                                                <div class="rp-dropzone-hint">PNG or JPG, up to 5MB</div>
                                            </div>
                                            <img id="rpPreviewImg" class="rp-preview-img" alt="Screenshot preview">
                                        </div>
                                        <div class="rp-dropzone-actions" id="rpDropzoneActions" style="justify-content:center;">
                                            <button type="button" class="rp-btn-change" onclick="event.stopPropagation(); document.getElementById('rp_screenshot').click();">Change</button>
                                            <button type="button" class="rp-btn-remove" onclick="event.stopPropagation(); rpRemoveScreenshot();">Remove</button>
                                        </div>
                                        <input type="file" name="screenshot" id="rp_screenshot" accept=".jpg,.jpeg,.png" style="display:none;" onchange="rpHandleScreenshotChange(this)">
                                    </div>

                                    <div class="rp-form-group" style="display:flex; justify-content:center;">
                                        <label class="rp-urgent-toggle" style="max-width:420px;">
                                            <div class="rp-urgent-icon-circle"><i class="fas fa-bolt"></i></div>
                                            <input type="checkbox" name="urgent" id="rp_urgent">
                                            <span class="rp-urgent-toggle-text">
                                                <strong>Mark as Urgent</strong>
                                                <span>Flags this report as Critical priority for faster review</span>
                                            </span>
                                        </label>
                                    </div>

                                    <div style="text-align:center; margin-top:6px;"><button class="rp-send-btn" type="submit"><i class="fas fa-paper-plane"></i> Send Report</button></div>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <h3>Recent Reports</h3>
                        </div>
                        <?php if (empty($reports)): ?>
                            <div class="rp-empty"><i class="fas fa-inbox"></i>No reports submitted yet.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="rp-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Severity</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report):
                                            $rpSeverity = strtolower($report['severity'] ?? '');
                                            $rpBadgeClass = $rpSeverity === 'critical' ? 'rp-badge-critical' : 'rp-badge-medium';
                                            $rpStatus = $report['status'] ?? 'Pending';
                                            $rpStatusClass = $rpStatus === 'Resolved' ? 'rp-status-resolved' : ($rpStatus === 'In Progress' ? 'rp-status-progress' : 'rp-status-pending');
                                        ?>
                                            <tr onclick="openReportDetailModal(<?= htmlspecialchars(json_encode($report)) ?>)">
                                                <td><span class="rp-badge rp-badge-category"><?= htmlspecialchars($report['category']) ?></span></td>
                                                <td><span class="rp-badge <?= $rpBadgeClass ?>"><?= htmlspecialchars($report['severity']) ?></span></td>
                                                <td><?= htmlspecialchars($report['subject'] ?: '(No subject)') ?></td>
                                                <td><span class="rp-badge <?= $rpStatusClass ?>"><?= htmlspecialchars($rpStatus) ?></span></td>
                                                <td><?= htmlspecialchars(date('M d, Y', strtotime($report['created_at']))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Report Detail Modal -->
                <div id="reportDetailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); z-index:10000; justify-content:center; align-items:center;">
                    <div style="background:white; border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.25); max-width:600px; width:92%; max-height:88vh; overflow-y:auto; animation:slideIn 0.25s ease;">
                        <div style="display:flex; align-items:center; gap:16px; padding:24px 28px; border-bottom:1px solid #f1f5f9; background:linear-gradient(to right,#fafafa,#ffffff); border-radius:20px 20px 0 0;">
                            <div style="width:48px;height:48px;background:linear-gradient(135deg,#0f4c81,#4c9ce8);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;color:white;"><i class="fas fa-headset"></i></div>
                            <div style="flex:1;">
                                <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:#0f172a;" id="rpm_subject">—</h2>
                                <p style="margin:4px 0 0;font-size:0.8rem;color:#64748b;">Submitted <span id="rpm_date">—</span></p>
                            </div>
                            <button type="button" onclick="document.getElementById('reportDetailModal').style.display='none'; document.body.style.overflow='';"
                                style="width:38px;height:38px;background:none;border:1px solid #e2e8f0;border-radius:10px;font-size:18px;cursor:pointer;color:#64748b;">✕</button>
                        </div>
                        <div style="padding:24px 28px;">
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;" id="rpm_badges"></div>

                            <div style="margin-bottom:20px;">
                                <strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Description</strong>
                                <div style="font-size:0.92rem;line-height:1.6;color:#334155;white-space:pre-wrap;background:#f8fafc;padding:14px;border-radius:12px;border:1px solid #e2e8f0;" id="rpm_description">—</div>
                            </div>

                            <div id="rpm_screenshot_wrap" style="display:none;">
                                <strong style="color:#64748b;display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Screenshot</strong>
                                <div style="text-align:center;" id="rpm_screenshot_content"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function openReportDetailModal(r) {
                        document.getElementById('rpm_subject').textContent = r.subject || '(No subject)';
                        document.getElementById('rpm_date').textContent = new Date(r.created_at.replace(' ', 'T')).toLocaleString();
                        document.getElementById('rpm_description').textContent = r.description || '—';

                        const severity = (r.severity || '').toLowerCase();
                        const sevClass = severity === 'critical' ? 'rp-badge-critical' : 'rp-badge-medium';
                        const status = r.status || 'Pending';
                        const statusClass = status === 'Resolved' ? 'rp-status-resolved' : (status === 'In Progress' ? 'rp-status-progress' : 'rp-status-pending');
                        document.getElementById('rpm_badges').innerHTML = `
                            <span class="rp-badge rp-badge-category">${r.category}</span>
                            <span class="rp-badge ${sevClass}">${r.severity}</span>
                            <span class="rp-badge ${statusClass}">${status}</span>
                        `;

                        const screenshotWrap = document.getElementById('rpm_screenshot_wrap');
                        if (r.screenshot_path) {
                            const url = '../../' + r.screenshot_path;
                            document.getElementById('rpm_screenshot_content').innerHTML =
                                `<a href="${url}" target="_blank" title="Click to view full size"><img src="${url}" alt="Screenshot" style="max-width:100%;max-height:320px;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 4px 12px rgba(0,0,0,0.1);cursor:zoom-in;"></a>`;
                            screenshotWrap.style.display = 'block';
                        } else {
                            screenshotWrap.style.display = 'none';
                        }

                        document.getElementById('reportDetailModal').style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        const modal = document.getElementById('reportDetailModal');
                        if (modal) {
                            modal.addEventListener('click', function(e) {
                                if (e.target === this) { this.style.display = 'none'; document.body.style.overflow = ''; }
                            });
                        }
                    });

                    function rpHandleScreenshotChange(input) {
                        if (!input.files || !input.files[0]) return;
                        const file = input.files[0];
                        const extMatch = /\.([a-z0-9]+)$/i.exec(file.name || '');
                        const ext = extMatch ? extMatch[1].toLowerCase() : '';
                        if (!['jpg', 'jpeg', 'png'].includes(ext)) {
                            alert('Screenshot must be a .jpg, .jpeg, or .png file.');
                            input.value = '';
                            return;
                        }
                        if (file.size > 5 * 1024 * 1024) {
                            alert('Screenshot must be under 5MB.');
                            input.value = '';
                            return;
                        }
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('rpDropzonePrompt').style.display = 'none';
                            const img = document.getElementById('rpPreviewImg');
                            img.src = e.target.result;
                            img.style.display = 'block';
                            document.getElementById('rpDropzoneActions').style.display = 'flex';
                        };
                        reader.readAsDataURL(file);
                    }

                    function rpRemoveScreenshot() {
                        const input = document.getElementById('rp_screenshot');
                        input.value = '';
                        document.getElementById('rpDropzonePrompt').style.display = 'block';
                        document.getElementById('rpPreviewImg').style.display = 'none';
                        document.getElementById('rpDropzoneActions').style.display = 'none';
                    }
                </script>
            <?php endif; ?>
        </main>
    </div>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainSidebar = document.getElementById('mainSidebar');

        function closeSidebar() {
            mainSidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                mainSidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
    </script>
</body>
</html>
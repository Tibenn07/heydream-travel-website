<?php
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = false;

function ensurePartnerColumns(PDO $pdo)
{
    static $existing = null;
    if ($existing === null) {
        $existing = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM partner_applications");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['Field']] = true;
        }
    }

    $columns = [
        'address' => 'TEXT DEFAULT NULL',
        'latitude' => 'VARCHAR(50) DEFAULT NULL',
        'longitude' => 'VARCHAR(50) DEFAULT NULL',
        'business_id_filename' => 'VARCHAR(255) DEFAULT NULL',
        'business_id_path' => 'VARCHAR(500) DEFAULT NULL',
        'business_permit_filename' => 'VARCHAR(255) DEFAULT NULL',
        'business_permit_path' => 'VARCHAR(500) DEFAULT NULL',
        'dti_filename' => 'VARCHAR(255) DEFAULT NULL',
        'dti_path' => 'VARCHAR(500) DEFAULT NULL',
        'sec_filename' => 'VARCHAR(255) DEFAULT NULL',
        'sec_path' => 'VARCHAR(500) DEFAULT NULL',
        'dot_filename' => 'VARCHAR(255) DEFAULT NULL',
        'dot_path' => 'VARCHAR(500) DEFAULT NULL',
        'face_verification_filename' => 'VARCHAR(255) DEFAULT NULL',
        'face_verification_path' => 'VARCHAR(500) DEFAULT NULL',
        'confirm_valid_docs' => 'TINYINT(1) DEFAULT 0',
        'agree_terms' => 'TINYINT(1) DEFAULT 0',
    ];

    foreach ($columns as $column => $definition) {
        if (!isset($existing[$column])) {
            $pdo->exec("ALTER TABLE partner_applications ADD COLUMN `$column` $definition");
            $existing[$column] = true;
        }
    }
}

function savePartnerUpload(string $fileKey, string $prefix, string $uploadDir): ?array
{
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return null;
    }
    if ($_FILES[$fileKey]['size'] > 10 * 1024 * 1024) {
        return null;
    }

    $filename = $prefix . '_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.' . $ext;
    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destination)) {
        return null;
    }

    return [
        'filename' => $filename,
        'path' => 'uploads/applications/' . $filename,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $confirm_valid_docs = isset($_POST['confirm_valid_docs']) ? 1 : 0;
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;

    $errors = [];
    if (empty($company_name)) $errors[] = 'Business name is required.';
    if (empty($contact_person)) $errors[] = 'Contact person is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid business email is required.';
    if (empty($phone)) $errors[] = 'Contact number is required.';
    if (empty($business_type)) $errors[] = 'Business type is required.';
    if (empty($address)) $errors[] = 'Business address is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    $hasBusinessDoc = false;
    foreach (['business_id', 'business_permit', 'dti_registration', 'sec_registration', 'dot_accreditation'] as $docKey) {
        if (!empty($_FILES[$docKey]) && $_FILES[$docKey]['error'] === UPLOAD_ERR_OK) {
            $hasBusinessDoc = true;
            break;
        }
    }
    if (!$hasBusinessDoc) $errors[] = 'Please upload at least one business document (Government ID, Business Permit, DTI, SEC, or DOT Accreditation).';

    if (empty($_FILES['face_verification']) || $_FILES['face_verification']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'A photo for identity verification is required.';
    }

    if (!$confirm_valid_docs || !$agree_terms) {
        $errors[] = 'Please confirm the required declarations before submitting.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM partner_applications WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            if ($existing && in_array($existing['status'], ['pending', 'approved'])) {
                $error = 'An application or account already exists with this email. If you have already applied, please login for status updates.';
            } else {
                ensurePartnerColumns($pdo);

                $uploadDir = __DIR__ . '/uploads/applications/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                    file_put_contents($uploadDir . '.htaccess', "Options -Indexes\nphp_flag engine off\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n    Require all denied\n</FilesMatch>\n");
                }

                $govId = savePartnerUpload('business_id', 'business_id', $uploadDir);
                $businessPermit = savePartnerUpload('business_permit', 'business_permit', $uploadDir);
                $dti = savePartnerUpload('dti_registration', 'dti', $uploadDir);
                $sec = savePartnerUpload('sec_registration', 'sec', $uploadDir);
                $dot = savePartnerUpload('dot_accreditation', 'dot', $uploadDir);
                $facePhoto = savePartnerUpload('face_verification', 'face_verification', $uploadDir);

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO partner_applications
                    (company_name, contact_person, email, phone, website, business_type, message, password, status,
                     address, latitude, longitude,
                     business_id_filename, business_id_path,
                     business_permit_filename, business_permit_path,
                     dti_filename, dti_path,
                     sec_filename, sec_path,
                     dot_filename, dot_path,
                     face_verification_filename, face_verification_path,
                     confirm_valid_docs, agree_terms)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $company_name, $contact_person, $email, $phone, '', $business_type, '', $hashedPassword,
                    $address, $latitude ?: null, $longitude ?: null,
                    $govId['filename'] ?? null, $govId['path'] ?? null,
                    $businessPermit['filename'] ?? null, $businessPermit['path'] ?? null,
                    $dti['filename'] ?? null, $dti['path'] ?? null,
                    $sec['filename'] ?? null, $sec['path'] ?? null,
                    $dot['filename'] ?? null, $dot['path'] ?? null,
                    $facePhoto['filename'] ?? null, $facePhoto['path'] ?? null,
                    $confirm_valid_docs, $agree_terms,
                ]);
                $success = true;
            }
        } catch (PDOException $e) {
            $error = 'Unable to submit your application. Please try again later.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Registration - HeyDream</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            background: linear-gradient(180deg, #f3f7ff 0%, #eef5ff 100%);
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
        }

        .auth-container {
            max-width: 820px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 32px;
            padding: 34px 36px;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            margin-bottom: 24px;
        }

        .eyebrow {
            display: block;
            color: #f59e0b;
            font-weight: 800;
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .hero-title {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 14px;
            line-height: 1.25;
        }

        .hero-description {
            color: #475569;
            font-size: 0.98rem;
            line-height: 1.7;
            margin: 0 0 20px;
        }

        .info-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .info-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eaf1ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 9px 16px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .section-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 20px;
        }

        .section-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin: -12px 0 20px;
            line-height: 1.6;
        }

        .required-star {
            color: #ef4444;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input,
        textarea,
        select {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 0.95rem;
            color: #0f172a;
            background: white;
            font-family: 'Poppins', sans-serif;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        .password-hint {
            color: #64748b;
            font-size: 0.82rem;
            margin: -10px 0 18px;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Address / map */
        .search-row {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            position: relative;
        }

        .search-row input {
            flex: 1;
        }

        .search-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            border: none;
            background: #0d47a1;
            color: white;
            border-radius: 14px;
            padding: 0 20px;
            font-weight: 700;
            cursor: pointer;
        }

        .suggestions-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            z-index: 20;
            max-height: 220px;
            overflow-y: auto;
            display: none;
        }

        .suggestion-item {
            padding: 12px 16px;
            font-size: 0.88rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }

        .suggestion-item:hover {
            background: #f8fafc;
        }

        .location-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            background: #0d47a1;
            color: white;
            border-radius: 14px;
            padding: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 12px;
        }

        .map-placeholder {
            border: 2px dashed #cbd5e1;
            border-radius: 18px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            color: #64748b;
            margin-bottom: 14px;
        }

        .map-placeholder .map-emoji {
            font-size: 2.2rem;
            display: block;
            margin-bottom: 10px;
        }

        .map-placeholder strong {
            display: block;
            color: #1d4ed8;
            font-size: 1rem;
            margin-bottom: 4px;
        }

        #mapContainer {
            width: 100%;
            height: 280px;
            border-radius: 18px;
            margin-bottom: 14px;
            display: none;
        }

        .map-hint {
            background: #eaf1ff;
            border-radius: 14px;
            padding: 14px 16px;
            font-size: 0.85rem;
            color: #1d4ed8;
            text-align: center;
            line-height: 1.6;
        }

        /* Photo verification */
        .photo-card {
            background: #f8fbff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 20px;
        }

        .photo-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 14px;
        }

        .required-badge {
            background: #fee2e2;
            color: #dc2626;
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .optional-badge {
            background: #fff7d6;
            color: #a06b00;
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .photo-capture-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 34px 20px;
            text-align: center;
            cursor: pointer;
            background: white;
        }

        .camera-icon {
            font-size: 2.4rem;
            margin-bottom: 10px;
        }

        .photo-cta {
            color: #1d4ed8;
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 4px;
        }

        .photo-sub {
            color: #94a3b8;
            font-size: 0.85rem;
            margin-bottom: 14px;
        }

        .photo-checklist {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px;
            color: #16a34a;
            font-size: 0.8rem;
            font-weight: 600;
        }

        #photoPreview {
            max-width: 100%;
            max-height: 260px;
            border-radius: 14px;
            display: none;
            margin: 0 auto 14px;
        }

        .camera-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.85);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .camera-modal.is-open {
            display: flex;
        }

        .camera-modal-inner {
            background: #0f172a;
            border-radius: 20px;
            padding: 16px;
            max-width: 480px;
            width: 100%;
        }

        #cameraVideo {
            width: 100%;
            border-radius: 14px;
            transform: scaleX(-1);
            background: #000;
            max-height: 60vh;
        }

        .camera-modal-controls {
            display: flex;
            gap: 12px;
            margin-top: 14px;
        }

        .camera-modal-controls .submit-btn,
        .camera-modal-controls .secondary-button {
            width: auto;
            flex: 1;
            justify-content: center;
        }

        /* Privacy box */
        .privacy-box {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 20px;
            padding: 22px 24px;
            margin: 22px 0;
        }

        .privacy-box h4 {
            color: #047857;
            margin: 0 0 12px;
            font-size: 0.98rem;
        }

        .privacy-box ul {
            margin: 0;
            padding-left: 20px;
            color: #065f46;
            font-size: 0.87rem;
            line-height: 1.9;
        }

        /* Document cards */
        .doc-card {
            background: #f8fbff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .doc-card.is-optional {
            border-color: #ffe08a;
            background: #fffdf5;
        }

        .doc-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .doc-number {
            flex-shrink: 0;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #0d47a1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .doc-info {
            flex: 1;
        }

        .doc-info h4 {
            margin: 0 0 4px;
            font-size: 1rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .doc-info p {
            margin: 0;
            color: #64748b;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .doc-upload-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 16px;
            color: #1d4ed8;
            font-weight: 700;
            cursor: pointer;
            background: white;
            font-size: 0.92rem;
            text-align: center;
        }

        .doc-card.is-optional .doc-upload-box {
            border-color: #ffe08a;
        }

        .doc-note {
            background: #fff7d6;
            color: #a06b00;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.8rem;
            margin-top: 12px;
            line-height: 1.5;
        }

        .upload-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            border-top: 1px solid #eef2f7;
            padding-top: 16px;
            margin-top: 4px;
            color: #334e68;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .upload-summary small {
            display: block;
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.78rem;
            margin-top: 2px;
        }

        /* Toggles */
        .toggle-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .toggle-row:last-child {
            border-bottom: none;
        }

        .toggle-label {
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.5;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 26px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #cbd5e1;
            transition: 0.25s;
            border-radius: 999px;
        }

        .slider:before {
            content: "";
            position: absolute;
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: 0.25s;
            border-radius: 50%;
        }

        .switch input:checked+.slider {
            background: #0d47a1;
        }

        .switch input:checked+.slider:before {
            transform: translateX(20px);
        }

        .submit-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 17px 26px;
            border: none;
            border-radius: 999px;
            background: #0f172a;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            font-family: 'Poppins', sans-serif;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .message-box {
            border-radius: 20px;
            padding: 18px 20px;
            margin-bottom: 24px;
            font-size: 0.96rem;
            line-height: 1.6;
        }

        .message-box.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .footer-link {
            margin-top: 18px;
            text-align: center;
            font-size: 0.95rem;
        }

        .footer-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }

        .success-card {
            text-align: center;
        }

        .badge-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff7d6;
            color: #a06b00;
            border-radius: 999px;
            padding: 7px 16px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .success-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: #102a43;
            margin: 0 0 10px;
        }

        .success-text {
            color: #4b6382;
            font-size: 0.95rem;
            line-height: 1.7;
            max-width: 560px;
            margin: 0 auto 26px;
        }

        .flow-box {
            background: #f2f7ff;
            border: 1px solid #d8e3f0;
            border-radius: 20px;
            padding: 24px 20px;
            margin-bottom: 26px;
            text-align: left;
        }

        .flow-label {
            color: #0d47a1;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 18px;
            text-align: center;
        }

        .progress-steps {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 4px;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .progress-step .step-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #64748b;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .progress-step.is-done .step-icon {
            background: #0d47a1;
            color: white;
        }

        .progress-step.is-active .step-icon {
            background: #ffd700;
            color: #7a5b00;
        }

        .progress-step .step-label {
            font-size: 0.72rem;
            color: #334e68;
            text-align: center;
            line-height: 1.4;
            font-weight: 600;
        }

        .step-connector {
            height: 2px;
            background: #d8e3f0;
            flex: 0.6;
            margin-top: 21px;
        }

        .secondary-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff7d6;
            color: #a06b00;
            border-radius: 999px;
            padding: 14px 28px;
            font-weight: 700;
            text-decoration: none;
        }

        .secondary-button:hover {
            box-shadow: 0 12px 30px rgba(160, 107, 0, 0.18);
        }

        @media (max-width: 780px) {
            .panel-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 26px 20px;
            }

            .progress-steps {
                flex-wrap: wrap;
                justify-content: center;
                gap: 18px;
            }

            .progress-step {
                flex: 0 0 30%;
            }

            .step-connector {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <?php if (!empty($error)): ?>
            <div class="card">
                <div class="message-box error"><?= $error ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="card">
                <div class="success-card">
                    <div class="badge-row">
                        <span class="status-badge"><i class="fas fa-paper-plane"></i> Pending Review</span>
                    </div>
                    <h2 class="success-title">Thank you for applying!</h2>
                    <p class="success-text">Your application has been submitted and is awaiting admin verification. We will contact you through your email address.</p>

                    <div class="flow-box">
                        <div class="flow-label">Approval Flow</div>
                        <div class="progress-steps">
                            <div class="progress-step is-done">
                                <div class="step-icon"><i class="fas fa-paper-plane"></i></div>
                                <div class="step-label">Submit<br>Application</div>
                            </div>
                            <div class="step-connector"></div>
                            <div class="progress-step is-active">
                                <div class="step-icon"><i class="fas fa-user-clock"></i></div>
                                <div class="step-label">Admin<br>Review</div>
                            </div>
                            <div class="step-connector"></div>
                            <div class="progress-step">
                                <div class="step-icon"><i class="fas fa-clipboard-check"></i></div>
                                <div class="step-label">Approved /<br>Rejected</div>
                            </div>
                            <div class="step-connector"></div>
                            <div class="progress-step">
                                <div class="step-icon"><i class="fas fa-envelope"></i></div>
                                <div class="step-label">Email<br>Notification</div>
                            </div>
                            <div class="step-connector"></div>
                            <div class="progress-step">
                                <div class="step-icon"><i class="fas fa-id-card"></i></div>
                                <div class="step-label">Complete<br>Profile</div>
                            </div>
                            <div class="step-connector"></div>
                            <div class="progress-step">
                                <div class="step-icon"><i class="fas fa-box-open"></i></div>
                                <div class="step-label">Post Packages<br>&amp; Listings</div>
                            </div>
                        </div>
                    </div>

                    <a href="partner-login.php" class="secondary-button"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            </div>
        <?php else: ?>

            <div class="card">
                <span class="eyebrow">Travel Partnership</span>
                <h1 class="hero-title">Grow your business with Hey Dream Travel and Tours</h1>
                <p class="hero-description">Join our platform to showcase your travel services, accommodations, tours, activities, and packages to more customers.</p>
                <div class="info-pill-row">
                    <span class="info-pill"><i class="fas fa-check"></i> Fast review</span>
                    <span class="info-pill"><i class="fas fa-check"></i> Trusted platform</span>
                    <span class="info-pill"><i class="fas fa-check"></i> More bookings</span>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data" id="partnerForm">
                <div class="card">
                    <h2 class="section-title">Application Form</h2>

                    <div class="form-group">
                        <label for="company_name">Business Name <span class="required-star">*</span></label>
                        <input type="text" id="company_name" name="company_name" placeholder="Enter your business name" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Contact Person <span class="required-star">*</span></label>
                        <input type="text" id="contact_person" name="contact_person" placeholder="Enter the contact person's name" value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Business Email Address <span class="required-star">*</span></label>
                        <input type="email" id="email" name="email" placeholder="partner@business.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Contact Number <span class="required-star">*</span></label>
                        <input type="text" id="phone" name="phone" placeholder="+63 XXX XXX XXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="business_type">Business Type <span class="required-star">*</span></label>
                        <input type="text" id="business_type" name="business_type" placeholder="e.g., Tour Operator, Travel Agency, Hotel" value="<?= htmlspecialchars($_POST['business_type'] ?? '') ?>" required>
                    </div>

                    <div class="panel-grid">
                        <div class="form-group">
                            <label for="password">Account Password <span class="required-star">*</span></label>
                            <input type="password" id="password" name="password" placeholder="Create a password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required-star">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                        </div>
                    </div>
                    <p class="password-hint">You'll use this password to log in to the partner portal once your application is approved.</p>
                </div>

                <div class="card">
                    <h2 class="section-title">Business Address <span class="required-star">*</span></h2>

                    <div class="search-row">
                        <input type="text" id="searchQuery" placeholder="Search for a location..." autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();searchLocation();}">
                        <button type="button" class="search-btn" onclick="searchLocation()"><i class="fas fa-search"></i> Search</button>
                        <div class="suggestions-dropdown" id="suggestionsDropdown"></div>
                    </div>

                    <button type="button" class="location-btn" onclick="useCurrentLocation()"><i class="fas fa-location-dot"></i> Use My Current Location</button>

                    <div class="form-group">
                        <textarea id="address" name="address" placeholder="Or type address manually" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="map-placeholder" id="mapPlaceholder" onclick="loadMap()">
                        <span class="map-emoji">🗺️</span>
                        <strong>Tap to load map and select location</strong>
                        <span>You can also search for a location above</span>
                    </div>
                    <div id="mapContainer"></div>

                    <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">

                    <div class="map-hint">
                        💡 Tap on the map to select your business location<br>
                        Or use the search bar above to find a location
                    </div>
                </div>

                <div class="card">
                    <h2 class="section-title">Photo Verification <span class="required-star">*</span></h2>
                    <p class="section-subtitle">Please take a clear photo of yourself for identity verification.</p>

                    <div class="photo-card">
                        <div class="photo-card-header">
                            <span>📸 Your Photo</span>
                            <span class="required-badge">Required</span>
                        </div>
                        <div class="photo-capture-box" onclick="openCamera()">
                            <div id="photoPromptState">
                                <div class="camera-icon">📷</div>
                                <div class="photo-cta">Tap to Take Photo</div>
                                <div class="photo-sub">Take a clear photo of your face</div>
                                <div class="photo-checklist">
                                    <span>✓ Clear Photo</span>
                                    <span>✓ Good Lighting</span>
                                    <span>✓ Face Visible</span>
                                </div>
                            </div>
                            <img id="photoPreview" alt="Captured photo preview">
                            <div id="photoRetake" style="display:none;">
                                <button type="button" class="search-btn" onclick="event.stopPropagation();openCamera()"><i class="fas fa-rotate"></i> Retake Photo</button>
                            </div>
                        </div>
                        <input type="file" id="face_verification" name="face_verification" style="display:none;" required>
                    </div>

                    <div class="camera-modal" id="cameraModal">
                        <div class="camera-modal-inner">
                            <video id="cameraVideo" autoplay playsinline muted></video>
                            <canvas id="cameraCanvas" style="display:none;"></canvas>
                            <div class="camera-modal-controls">
                                <button type="button" class="submit-btn" onclick="capturePhoto()"><i class="fas fa-camera"></i> Capture</button>
                                <button type="button" class="secondary-button" onclick="closeCamera()"><i class="fas fa-xmark"></i> Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div class="privacy-box">
                        <h4>🔒 Privacy &amp; Security</h4>
                        <ul>
                            <li>Your photo is protected and will only be used for verification purposes</li>
                            <li>All uploaded documents are encrypted and stored securely</li>
                            <li>Our admin team will review your application and documents</li>
                            <li>You will be notified via email about the status of your application</li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <h2 class="section-title">Business Documents <span class="required-star">*</span></h2>
                    <p class="section-subtitle">Upload at least one document to verify your business</p>

                    <div class="doc-card">
                        <div class="doc-header">
                            <div class="doc-number">1</div>
                            <div class="doc-info">
                                <h4>Government Issued ID</h4>
                                <p>Passport, Driver's License, National ID, etc.</p>
                            </div>
                        </div>
                        <label class="doc-upload-box" for="business_id"><i class="fas fa-paperclip"></i> <span id="business_id_label">Upload Government Issued ID</span></label>
                        <input type="file" id="business_id" name="business_id" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleDocChange(this,'business_id_label','Upload Government Issued ID')">
                    </div>

                    <div class="doc-card">
                        <div class="doc-header">
                            <div class="doc-number">2</div>
                            <div class="doc-info">
                                <h4>Business Permit</h4>
                                <p>Mayor's Permit / Municipal License</p>
                            </div>
                        </div>
                        <label class="doc-upload-box" for="business_permit"><i class="fas fa-paperclip"></i> <span id="business_permit_label">Upload Business Permit</span></label>
                        <input type="file" id="business_permit" name="business_permit" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleDocChange(this,'business_permit_label','Upload Business Permit')">
                    </div>

                    <div class="doc-card">
                        <div class="doc-header">
                            <div class="doc-number">3</div>
                            <div class="doc-info">
                                <h4>DTI Registration</h4>
                                <p>Department of Trade and Industry (Sole Proprietorship)</p>
                            </div>
                        </div>
                        <label class="doc-upload-box" for="dti_registration"><i class="fas fa-paperclip"></i> <span id="dti_registration_label">Upload DTI Registration</span></label>
                        <input type="file" id="dti_registration" name="dti_registration" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleDocChange(this,'dti_registration_label','Upload DTI Registration')">
                    </div>

                    <div class="doc-card">
                        <div class="doc-header">
                            <div class="doc-number">4</div>
                            <div class="doc-info">
                                <h4>SEC Registration</h4>
                                <p>Securities and Exchange Commission (Corporation/Partnership)</p>
                            </div>
                        </div>
                        <label class="doc-upload-box" for="sec_registration"><i class="fas fa-paperclip"></i> <span id="sec_registration_label">Upload SEC Registration</span></label>
                        <input type="file" id="sec_registration" name="sec_registration" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleDocChange(this,'sec_registration_label','Upload SEC Registration')">
                    </div>

                    <div class="doc-card is-optional">
                        <div class="doc-header">
                            <div class="doc-number">5</div>
                            <div class="doc-info">
                                <h4>DOT Accreditation <span class="optional-badge">Optional</span></h4>
                                <p>Department of Tourism (Travel Agencies/Tour Operators)</p>
                            </div>
                        </div>
                        <label class="doc-upload-box" for="dot_accreditation"><i class="fas fa-paperclip"></i> <span id="dot_accreditation_label">Upload DOT Accreditation (Optional)</span></label>
                        <input type="file" id="dot_accreditation" name="dot_accreditation" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleDocChange(this,'dot_accreditation_label','Upload DOT Accreditation (Optional)')">
                        <div class="doc-note">ℹ️ DOT Accreditation is optional for travel agencies and tour operators</div>
                    </div>

                    <div class="upload-summary">
                        <span><i class="fas fa-folder-open"></i> <span id="docCount">0</span> document(s) uploaded</span>
                        <small>Supported formats: PDF, JPG, PNG (Max 10MB each)</small>
                    </div>
                </div>

                <div class="card">
                    <div class="toggle-row">
                        <label class="switch">
                            <input type="checkbox" id="confirm_valid_docs" name="confirm_valid_docs" value="1">
                            <span class="slider"></span>
                        </label>
                        <span class="toggle-label">I confirm that the submitted documents are valid and belong to my business.</span>
                    </div>
                    <div class="toggle-row">
                        <label class="switch">
                            <input type="checkbox" id="agree_terms" name="agree_terms" value="1">
                            <span class="slider"></span>
                        </label>
                        <span class="toggle-label">I agree to the Terms and Conditions.</span>
                    </div>
                </div>

                <div class="card">
                    <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit Partnership Application</button>
                    <div class="footer-link">
                        Already applied or have an approved account? <a href="partner-login.php">Partner Login</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map, marker, locationSelected = false;
        const defaultLat = 14.5995, defaultLng = 120.9842;

        function loadMap() {
            document.getElementById('mapPlaceholder').style.display = 'none';
            const mapEl = document.getElementById('mapContainer');
            mapEl.style.display = 'block';
            if (map) return;

            const lat = parseFloat(document.getElementById('latitude').value) || defaultLat;
            const lng = parseFloat(document.getElementById('longitude').value) || defaultLng;

            map = L.map('mapContainer').setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                setLocation(pos.lat, pos.lng, true);
            });
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                setLocation(e.latlng.lat, e.latlng.lng, true);
            });
        }

        function setLocation(lat, lng, reverseGeocodeAddress) {
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            locationSelected = true;
            if (reverseGeocodeAddress) {
                reverseGeocode(lat, lng);
            }
        }

        async function reverseGeocode(lat, lng) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&addressdetails=1`);
                const data = await res.json();
                if (data && data.display_name) {
                    document.getElementById('address').value = data.display_name;
                }
            } catch (e) {
                console.error('Reverse geocode failed', e);
            }
        }

        function useCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser. Please search or type your address manually.');
                return;
            }
            navigator.geolocation.getCurrentPosition(function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                loadMap();
                if (map) {
                    map.setView([lat, lng], 15);
                    marker.setLatLng([lat, lng]);
                }
                setLocation(lat, lng, true);
            }, function() {
                alert('Unable to access your location. This requires a secure (HTTPS) connection and location permission. Please search or type your address manually.');
            }, { enableHighAccuracy: true, timeout: 10000 });
        }

        let searchDebounce;
        document.getElementById('searchQuery').addEventListener('input', function() {
            clearTimeout(searchDebounce);
            const query = this.value.trim();
            const dropdown = document.getElementById('suggestionsDropdown');
            if (query.length < 2) {
                dropdown.style.display = 'none';
                return;
            }
            searchDebounce = setTimeout(() => fetchSuggestions(query), 500);
        });

        async function fetchSuggestions(query) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&addressdetails=1&limit=8&countrycodes=ph&accept-language=en`);
                const data = await res.json();
                const dropdown = document.getElementById('suggestionsDropdown');
                dropdown.innerHTML = '';
                if (!data || data.length === 0) {
                    dropdown.style.display = 'none';
                    return;
                }
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = item.display_name;
                    div.onclick = () => selectSuggestion(item);
                    dropdown.appendChild(div);
                });
                dropdown.style.display = 'block';
            } catch (e) {
                console.error('Search failed', e);
            }
        }

        function selectSuggestion(item) {
            document.getElementById('address').value = item.display_name;
            document.getElementById('searchQuery').value = '';
            document.getElementById('suggestionsDropdown').style.display = 'none';
            const lat = parseFloat(item.lat), lng = parseFloat(item.lon);
            loadMap();
            if (map) {
                map.setView([lat, lng], 15);
                marker.setLatLng([lat, lng]);
            }
            setLocation(lat, lng, false);
        }

        async function searchLocation() {
            const query = document.getElementById('searchQuery').value.trim();
            if (!query) return;
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&addressdetails=1&limit=1&countrycodes=ph&accept-language=en`);
                const data = await res.json();
                if (data && data.length > 0) {
                    selectSuggestion(data[0]);
                } else {
                    alert('No results found for that location. Please try a different search or type your address manually.');
                }
            } catch (e) {
                console.error('Search failed', e);
            }
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('suggestionsDropdown');
            if (dropdown && !e.target.closest('.search-row')) {
                dropdown.style.display = 'none';
            }
        });

        let cameraStream = null;

        async function openCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera access is not supported in this browser. Please use a different browser to complete photo verification.');
                return;
            }
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            } catch (err) {
                alert('Camera access is required for photo verification. Please allow camera permission and try again. (Note: this requires a secure HTTPS connection, or localhost.)');
                return;
            }
            document.getElementById('cameraModal').classList.add('is-open');
            document.getElementById('cameraVideo').srcObject = cameraStream;
        }

        function closeCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            document.getElementById('cameraModal').classList.remove('is-open');
        }

        function capturePhoto() {
            const video = document.getElementById('cameraVideo');
            const canvas = document.getElementById('cameraCanvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0);

            canvas.toBlob(function(blob) {
                const file = new File([blob], 'verification_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                const dt = new DataTransfer();
                dt.items.add(file);
                const input = document.getElementById('face_verification');
                input.files = dt.files;

                const preview = document.getElementById('photoPreview');
                preview.src = URL.createObjectURL(blob);
                preview.style.display = 'block';
                document.getElementById('photoPromptState').style.display = 'none';
                document.getElementById('photoRetake').style.display = 'block';

                closeCamera();
                updateDocCount();
            }, 'image/jpeg', 0.9);
        }

        function handleDocChange(input, labelId, defaultText) {
            const label = document.getElementById(labelId);
            if (input.files && input.files[0]) {
                label.textContent = '✓ ' + input.files[0].name;
            } else {
                label.textContent = defaultText;
            }
            updateDocCount();
        }

        function updateDocCount() {
            const docInputs = ['business_id', 'business_permit', 'dti_registration', 'sec_registration', 'dot_accreditation', 'face_verification'];
            let count = 0;
            docInputs.forEach(id => {
                const el = document.getElementById(id);
                if (el && el.files && el.files.length > 0) count++;
            });
            document.getElementById('docCount').textContent = count;
        }

        document.getElementById('partnerForm').addEventListener('submit', function(e) {
            const docInputs = ['business_id', 'business_permit', 'dti_registration', 'sec_registration', 'dot_accreditation'];
            const hasDoc = docInputs.some(id => {
                const el = document.getElementById(id);
                return el && el.files && el.files.length > 0;
            });
            if (!hasDoc) {
                e.preventDefault();
                alert('Please upload at least one business document (Government ID, Business Permit, DTI, SEC, or DOT Accreditation).');
                return;
            }
            const faceInput = document.getElementById('face_verification');
            if (!faceInput.files || faceInput.files.length === 0) {
                e.preventDefault();
                alert('Please take a photo for identity verification.');
                return;
            }
            if (!document.getElementById('address').value.trim()) {
                e.preventDefault();
                alert('Please provide your business address.');
                return;
            }
            if (!document.getElementById('confirm_valid_docs').checked || !document.getElementById('agree_terms').checked) {
                e.preventDefault();
                alert('Please confirm the required declarations before submitting.');
                return;
            }
        });
    </script>
</body>

</html>

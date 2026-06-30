<?php
require_once __DIR__ . '/../config/database.php';

requireLogin();

$user = $auth->getCurrentUser();

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $title = trim($_POST['title'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($fullName)) {
        $errorMessage = "Name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET title = ?, full_name = ?, dob = ?, country = ?, phone = ? WHERE id = ?");
            if ($stmt->execute([$title, $fullName, $dob, $country, $phone, $_SESSION['user_id']])) {
                
                // Handle profile picture upload
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../images/profiles/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileInfo = pathinfo($_FILES['profile_pic']['name']);
                    $extension = strtolower($fileInfo['extension']);
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($extension, $allowedExtensions)) {
                        $newFileName = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                        $destination = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                            $stmtPic = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                            $stmtPic->execute(['images/profiles/' . $newFileName, $_SESSION['user_id']]);
                        }
                    } else {
                        $errorMessage = "Invalid image format. Only JPG, PNG, and GIF are allowed.";
                    }
                }
                
                if (empty($errorMessage)) {
                    $successMessage = "Profile updated successfully!";
                }
                
                // Refresh user data
                $user = $auth->getCurrentUser();
            } else {
                $errorMessage = "Failed to update profile. Please try again.";
            }
        } catch (PDOException $e) {
            $errorMessage = "An error occurred while updating your profile.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeyDream - My Profile Settings</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .profile-container {
            padding: 120px 5% 60px;
            max-width: 900px;
            margin: 0 auto;
        }

        .profile-header {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            border-radius: 24px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: #ff9800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            position: relative;
            overflow: hidden;
            border: 4px solid rgba(255, 255, 255, 0.3);
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .avatar-upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 35%;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
            opacity: 0;
            cursor: pointer;
        }

        .profile-avatar:hover .avatar-upload-overlay {
            opacity: 1;
        }

        .profile-details h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .profile-details p {
            opacity: 0.9;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #003580;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 53, 128, 0.1);
        }

        .form-control[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
            color: #666;
        }

        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .form-col {
            flex: 1;
            min-width: 200px;
        }

        .btn-save {
            background: #ff9800;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
            margin-top: 10px;
        }

        .btn-save:hover {
            background: #e68a00;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 152, 0, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #003580;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #ff9800;
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar" style="background: white; box-shadow: 0 2px 15px rgba(0,0,0,0.05);">
        <div class="nav-left">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo" style="height: 37px; width: auto;"
                onclick="window.location.href='../index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="profile-header">
            <div class="profile-avatar" onclick="document.getElementById('profilePicInput').click()">
                <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/../' . $user['profile_pic'])): ?>
                    <img id="avatarPreview" src="../<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <img id="avatarPreview" src="" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                    <span id="avatarInitials"><?= strtoupper(substr($user['full_name'], 0, 2)) ?></span>
                <?php endif; ?>
                <div class="avatar-upload-overlay">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <div class="profile-details">
                <h1>My Profile Details</h1>
                <p>Update your personal information to help us serve you better.</p>
                <p style="font-size: 0.8rem; margin-top: 5px; opacity: 0.8;"><i class="fas fa-info-circle"></i> Click your avatar to change photo</p>
            </div>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="file" id="profilePicInput" name="profile_pic" accept="image/png, image/jpeg, image/jpg, image/gif" style="display: none;" onchange="previewAvatar(this)">
                <div class="form-row">
                    <div class="form-group form-col" style="flex: 0.3;">
                        <label class="form-label">Title</label>
                        <select class="form-control" name="title">
                            <option value="" <?= empty($user['title']) ? 'selected' : '' ?>>Select</option>
                            <option value="Mr" <?= ($user['title'] ?? '') === 'Mr' ? 'selected' : '' ?>>Mr</option>
                            <option value="Mrs" <?= ($user['title'] ?? '') === 'Mrs' ? 'selected' : '' ?>>Mrs</option>
                            <option value="Ms" <?= ($user['title'] ?? '') === 'Ms' ? 'selected' : '' ?>>Ms</option>
                            <option value="Mx" <?= ($user['title'] ?? '') === 'Mx' ? 'selected' : '' ?>>Mx</option>
                        </select>
                    </div>
                    <div class="form-group form-col">
                        <label class="form-label">Full Name <span style="color:red;">*</span></label>
                        <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address (Read-only)</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                </div>

                <div class="form-row">
                    <div class="form-group form-col">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>">
                    </div>
                    <div class="form-group form-col">
                        <label class="form-label">Mobile Number</label>
                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+63 917 123 4567">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Country of Residence</label>
                    <select class="form-control" name="country">
                        <option value="" <?= empty($user['country']) ? 'selected' : '' ?>>Select Country</option>
                        <option value="Philippines" <?= ($user['country'] ?? '') === 'Philippines' ? 'selected' : '' ?>>Philippines</option>
                        <option value="United States" <?= ($user['country'] ?? '') === 'United States' ? 'selected' : '' ?>>United States</option>
                        <option value="Canada" <?= ($user['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
                        <option value="United Kingdom" <?= ($user['country'] ?? '') === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
                        <option value="Australia" <?= ($user['country'] ?? '') === 'Australia' ? 'selected' : '' ?>>Australia</option>
                        <option value="Japan" <?= ($user['country'] ?? '') === 'Japan' ? 'selected' : '' ?>>Japan</option>
                        <option value="South Korea" <?= ($user['country'] ?? '') === 'South Korea' ? 'selected' : '' ?>>South Korea</option>
                        <option value="Singapore" <?= ($user['country'] ?? '') === 'Singapore' ? 'selected' : '' ?>>Singapore</option>
                        <!-- Add other relevant countries as needed -->
                        <option value="Other" <?= ($user['country'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <button type="submit" name="update_profile" class="btn-save">
                    <i class="fas fa-save"></i> Save Profile Details
                </button>
            </form>
        </div>
    </div>

    <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatarPreview');
                    const initials = document.getElementById('avatarInitials');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (initials) initials.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>

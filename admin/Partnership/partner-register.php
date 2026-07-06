<?php
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    $other_business_type = trim($_POST['other_business_type'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (empty($company_name)) $errors[] = 'Company name is required.';
    if (empty($contact_person)) $errors[] = 'Contact person is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($business_type)) $errors[] = 'Business type is required.';
    if ($business_type === 'Other' && empty($other_business_type)) $errors[] = 'Please provide your business type.';
    if (empty($password)) $errors[] = 'Password is required.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        if ($business_type === 'Other') {
            $business_type = $other_business_type;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, status FROM partner_applications WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            if ($existing && in_array($existing['status'], ['pending', 'approved'])) {
                $error = 'An application or account already exists with this email. If you have already applied, please login for status updates.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO partner_applications (company_name, contact_person, email, phone, website, business_type, message, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$company_name, $contact_person, $email, $phone, '', $business_type, '', $hashedPassword]);
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            max-width: 760px;
            margin: 0 auto;
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header h1 {
            margin-bottom: 10px;
            font-size: 2.45rem;
            color: #0f172a;
        }

        .auth-header p {
            color: #475569;
            font-size: 1rem;
            line-height: 1.7;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .panel-card {
            background: #f8fbff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 22px;
        }

        .panel-card h3 {
            margin: 0 0 12px;
            font-size: 1rem;
            color: #0f172a;
        }

        .panel-card p {
            margin: 0;
            color: #475569;
            line-height: 1.7;
            font-size: 0.95rem;
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
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 26px;
            border: none;
            border-radius: 999px;
            background: #0f172a;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
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

        .message-box.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
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

        @media (max-width: 780px) {
            .panel-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function toggleOtherTypeField() {
            const businessType = document.getElementById('business_type').value;
            const otherField = document.getElementById('other-business-type-field');
            if (!otherField) return;
            otherField.style.display = businessType === 'Other' ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleOtherTypeField();
        });
    </script>
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Partner Registration</h1>
            <p>Apply to join the HeyDream Travel partner program. After review, approved partners can access the partner portal.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="message-box error"><?= $error ?></div>
        <?php elseif (!empty($success)): ?>
            <div class="message-box success">Your application has been submitted successfully. You can return to the login page anytime to check status.</div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
            <form method="post">
                <div class="panel-grid">
                    <div class="panel-card">
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Business Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="panel-card">
                        <div class="form-group">
                            <label for="business_type">Business Type</label>
                            <select id="business_type" name="business_type" required onchange="toggleOtherTypeField()">
                                <option value="" <?= empty($_POST['business_type']) ? 'selected' : '' ?>>Select one</option>
                                <option value="Travel Agency" <?= ($_POST['business_type'] ?? '') === 'Travel Agency' ? 'selected' : '' ?>>Travel Agency</option>
                                <option value="Hotel / Resort" <?= ($_POST['business_type'] ?? '') === 'Hotel / Resort' ? 'selected' : '' ?>>Hotel / Resort</option>
                                <option value="Airline" <?= ($_POST['business_type'] ?? '') === 'Airline' ? 'selected' : '' ?>>Airline</option>
                                <option value="Tour Operator" <?= ($_POST['business_type'] ?? '') === 'Tour Operator' ? 'selected' : '' ?>>Tour Operator</option>
                                <option value="Corporate Travel" <?= ($_POST['business_type'] ?? '') === 'Corporate Travel' ? 'selected' : '' ?>>Corporate Travel</option>
                                <option value="Other" <?= ($_POST['business_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group" id="other-business-type-field" style="display: <?= (($_POST['business_type'] ?? '') === 'Other') ? 'block' : 'none' ?>;">
                            <label for="other_business_type">Please specify your business type</label>
                            <input type="text" id="other_business_type" name="other_business_type" value="<?= htmlspecialchars($_POST['other_business_type'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit Partnership Application</button>
            </form>
        <?php endif; ?>

        <div class="footer-link">
            Already applied or have an approved account? <a href="partner-login.php">Partner Login</a>
        </div>
    </div>
</body>

</html>

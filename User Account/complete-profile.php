<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_functions.php';

$error = '';
$pref = [
    'title' => '',
    'full_name' => '',
    'dob' => '',
    'country' => '',
    'phone' => '',
    'profile_pic' => ''
];

// Determine current user (session) or via registration token
$currentUser = null;
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
} else {
    // allow access if user_id and token provided (post-registration)
    if (!empty($_GET['user_id']) && !empty($_GET['token'])) {
        $uid = intval($_GET['user_id']);
        $token = $_GET['token'];
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND verification_token = ?');
            $stmt->execute([$uid, $token]);
            $row = $stmt->fetch();
            if ($row) {
                $currentUser = $row;
            } else {
                $error = 'Invalid or expired profile completion link.';
            }
        } catch (Exception $e) {
            $error = 'Unable to load user information.';
        }
    } else {
        // Not logged in and no token — require login
        $error = 'Please login to continue or use the link from your email.';
    }
}

// Always show empty fields — do not pre-fill from existing user data
// (prevents info from one user appearing for another user)
if ($currentUser) {
    // Only load profile_pic for avatar display; keep all form fields blank
    $pref['profile_pic'] = $currentUser['profile_pic'] ?? '';
    // All other fields intentionally left empty so the form is always blank
    $pref['title'] = '';
    $pref['full_name'] = '';
    $pref['dob'] = '';
    $pref['country'] = '';
    $pref['phone'] = '';
}

// Handle POST (save or skip)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // determine target user id
    $targetUserId = $currentUser['id'] ?? null;
    if (isset($_POST['skip'])) {
        if (!$targetUserId) {
            $error = 'Unable to skip: user not found.';
        } else {
            // create session and redirect
            $auth->createSession($targetUserId);
            sendLoginNotifications($targetUserId, 'profile-skip');
            header('Location: ../index.php');
            exit;
        }
    } else {
        // Save profile
        $title = trim($_POST['title'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Removed validation to make full name optional
        if (false) {
            $error = 'Full name is required.';
        } else {
            // handle file upload if provided
            $newProfilePath = null;
            if (!empty($_FILES['profile_pic']['name'])) {
                $file = $_FILES['profile_pic'];
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                if (!in_array($file['type'], $allowed)) {
                    $error = 'Invalid image type. Use JPG, PNG or GIF.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $error = 'Image too large (max 5MB).';
                } else {
                    $uploadDir = __DIR__ . '/../images/profiles/';
                    if (!is_dir($uploadDir))
                        @mkdir($uploadDir, 0755, true);
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $dest = $uploadDir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $newProfilePath = 'images/profiles/' . $filename;
                        // optionally remove old file
                        if (!empty($pref['profile_pic']) && file_exists(__DIR__ . '/../' . $pref['profile_pic'])) {
                            @unlink(__DIR__ . '/../' . $pref['profile_pic']);
                        }
                    } else {
                        $error = 'Failed to save uploaded image.';
                    }
                }
            }

            if (empty($error)) {
                // Ensure users table has expected columns (safe-add if missing)
                try {
                    $needed = [
                        'title' => "VARCHAR(20) DEFAULT ''",
                        'dob' => "DATE NULL",
                        'country' => "VARCHAR(100) DEFAULT ''",
                        'phone' => "VARCHAR(30) DEFAULT ''",
                        'profile_pic' => "VARCHAR(255) DEFAULT NULL"
                    ];
                    foreach ($needed as $col => $definition) {
                        $stmtCol = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
                        $stmtCol->execute([$col]);
                        $count = $stmtCol->fetchColumn();
                        if (empty($count)) {
                            $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $definition");
                        }
                    }
                } catch (Exception $e) {
                    // ignore migration errors
                }
                try {
                    $cols = ['full_name' => $full_name, 'title' => $title, 'dob' => $dob, 'country' => $country, 'phone' => $phone];
                    $params = [$full_name, $title, $dob, $country, $phone];
                    $sql = 'UPDATE users SET full_name = ?, title = ?, dob = ?, country = ?, phone = ?';
                    if ($newProfilePath) {
                        $sql .= ', profile_pic = ?';
                        $params[] = $newProfilePath;
                    }
                    $sql .= ' WHERE id = ?';
                    $params[] = $targetUserId;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // ensure session exists
                    if (!$auth->isLoggedIn()) {
                        $auth->createSession($targetUserId);
                    }
                    sendLoginNotifications($targetUserId, 'profile-complete');
                    // Return JSON for AJAX requests so JS can redirect instantly
                    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirect' => '../index.php']);
                        exit;
                    }
                    header('Location: ../index.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Unable to save profile. Please try again.';
                }
            }
        }
    }
}

// List of countries with their flags (emoji flags)
$countries = [
    ['code' => 'AF', 'name' => 'Afghanistan', 'flag' => '🇦🇫'],
    ['code' => 'AL', 'name' => 'Albania', 'flag' => '🇦🇱'],
    ['code' => 'DZ', 'name' => 'Algeria', 'flag' => '🇩🇿'],
    ['code' => 'AD', 'name' => 'Andorra', 'flag' => '🇦🇩'],
    ['code' => 'AO', 'name' => 'Angola', 'flag' => '🇦🇴'],
    ['code' => 'AG', 'name' => 'Antigua and Barbuda', 'flag' => '🇦🇬'],
    ['code' => 'AR', 'name' => 'Argentina', 'flag' => '🇦🇷'],
    ['code' => 'AM', 'name' => 'Armenia', 'flag' => '🇦🇲'],
    ['code' => 'AU', 'name' => 'Australia', 'flag' => '🇦🇺'],
    ['code' => 'AT', 'name' => 'Austria', 'flag' => '🇦🇹'],
    ['code' => 'AZ', 'name' => 'Azerbaijan', 'flag' => '🇦🇿'],
    ['code' => 'BS', 'name' => 'Bahamas', 'flag' => '🇧🇸'],
    ['code' => 'BH', 'name' => 'Bahrain', 'flag' => '🇧🇭'],
    ['code' => 'BD', 'name' => 'Bangladesh', 'flag' => '🇧🇩'],
    ['code' => 'BB', 'name' => 'Barbados', 'flag' => '🇧🇧'],
    ['code' => 'BY', 'name' => 'Belarus', 'flag' => '🇧🇾'],
    ['code' => 'BE', 'name' => 'Belgium', 'flag' => '🇧🇪'],
    ['code' => 'BZ', 'name' => 'Belize', 'flag' => '🇧🇿'],
    ['code' => 'BJ', 'name' => 'Benin', 'flag' => '🇧🇯'],
    ['code' => 'BT', 'name' => 'Bhutan', 'flag' => '🇧🇹'],
    ['code' => 'BO', 'name' => 'Bolivia', 'flag' => '🇧🇴'],
    ['code' => 'BA', 'name' => 'Bosnia and Herzegovina', 'flag' => '🇧🇦'],
    ['code' => 'BW', 'name' => 'Botswana', 'flag' => '🇧🇼'],
    ['code' => 'BR', 'name' => 'Brazil', 'flag' => '🇧🇷'],
    ['code' => 'BN', 'name' => 'Brunei', 'flag' => '🇧🇳'],
    ['code' => 'BG', 'name' => 'Bulgaria', 'flag' => '🇧🇬'],
    ['code' => 'BF', 'name' => 'Burkina Faso', 'flag' => '🇧🇫'],
    ['code' => 'BI', 'name' => 'Burundi', 'flag' => '🇧🇮'],
    ['code' => 'CV', 'name' => 'Cabo Verde', 'flag' => '🇨🇻'],
    ['code' => 'KH', 'name' => 'Cambodia', 'flag' => '🇰🇭'],
    ['code' => 'CM', 'name' => 'Cameroon', 'flag' => '🇨🇲'],
    ['code' => 'CA', 'name' => 'Canada', 'flag' => '🇨🇦'],
    ['code' => 'CF', 'name' => 'Central African Republic', 'flag' => '🇨🇫'],
    ['code' => 'TD', 'name' => 'Chad', 'flag' => '🇹🇩'],
    ['code' => 'CL', 'name' => 'Chile', 'flag' => '🇨🇱'],
    ['code' => 'CN', 'name' => 'China', 'flag' => '🇨🇳'],
    ['code' => 'CO', 'name' => 'Colombia', 'flag' => '🇨🇴'],
    ['code' => 'KM', 'name' => 'Comoros', 'flag' => '🇰🇲'],
    ['code' => 'CG', 'name' => 'Congo', 'flag' => '🇨🇬'],
    ['code' => 'CR', 'name' => 'Costa Rica', 'flag' => '🇨🇷'],
    ['code' => 'HR', 'name' => 'Croatia', 'flag' => '🇭🇷'],
    ['code' => 'CU', 'name' => 'Cuba', 'flag' => '🇨🇺'],
    ['code' => 'CY', 'name' => 'Cyprus', 'flag' => '🇨🇾'],
    ['code' => 'CZ', 'name' => 'Czech Republic', 'flag' => '🇨🇿'],
    ['code' => 'DK', 'name' => 'Denmark', 'flag' => '🇩🇰'],
    ['code' => 'DJ', 'name' => 'Djibouti', 'flag' => '🇩🇯'],
    ['code' => 'DM', 'name' => 'Dominica', 'flag' => '🇩🇲'],
    ['code' => 'DO', 'name' => 'Dominican Republic', 'flag' => '🇩🇴'],
    ['code' => 'EC', 'name' => 'Ecuador', 'flag' => '🇪🇨'],
    ['code' => 'EG', 'name' => 'Egypt', 'flag' => '🇪🇬'],
    ['code' => 'SV', 'name' => 'El Salvador', 'flag' => '🇸🇻'],
    ['code' => 'GQ', 'name' => 'Equatorial Guinea', 'flag' => '🇬🇶'],
    ['code' => 'ER', 'name' => 'Eritrea', 'flag' => '🇪🇷'],
    ['code' => 'EE', 'name' => 'Estonia', 'flag' => '🇪🇪'],
    ['code' => 'SZ', 'name' => 'Eswatini', 'flag' => '🇸🇿'],
    ['code' => 'ET', 'name' => 'Ethiopia', 'flag' => '🇪🇹'],
    ['code' => 'FJ', 'name' => 'Fiji', 'flag' => '🇫🇯'],
    ['code' => 'FI', 'name' => 'Finland', 'flag' => '🇫🇮'],
    ['code' => 'FR', 'name' => 'France', 'flag' => '🇫🇷'],
    ['code' => 'GA', 'name' => 'Gabon', 'flag' => '🇬🇦'],
    ['code' => 'GM', 'name' => 'Gambia', 'flag' => '🇬🇲'],
    ['code' => 'GE', 'name' => 'Georgia', 'flag' => '🇬🇪'],
    ['code' => 'DE', 'name' => 'Germany', 'flag' => '🇩🇪'],
    ['code' => 'GH', 'name' => 'Ghana', 'flag' => '🇬🇭'],
    ['code' => 'GR', 'name' => 'Greece', 'flag' => '🇬🇷'],
    ['code' => 'GD', 'name' => 'Grenada', 'flag' => '🇬🇩'],
    ['code' => 'GT', 'name' => 'Guatemala', 'flag' => '🇬🇹'],
    ['code' => 'GN', 'name' => 'Guinea', 'flag' => '🇬🇳'],
    ['code' => 'GW', 'name' => 'Guinea-Bissau', 'flag' => '🇬🇼'],
    ['code' => 'GY', 'name' => 'Guyana', 'flag' => '🇬🇾'],
    ['code' => 'HT', 'name' => 'Haiti', 'flag' => '🇭🇹'],
    ['code' => 'HN', 'name' => 'Honduras', 'flag' => '🇭🇳'],
    ['code' => 'HU', 'name' => 'Hungary', 'flag' => '🇭🇺'],
    ['code' => 'IS', 'name' => 'Iceland', 'flag' => '🇮🇸'],
    ['code' => 'IN', 'name' => 'India', 'flag' => '🇮🇳'],
    ['code' => 'ID', 'name' => 'Indonesia', 'flag' => '🇮🇩'],
    ['code' => 'IR', 'name' => 'Iran', 'flag' => '🇮🇷'],
    ['code' => 'IQ', 'name' => 'Iraq', 'flag' => '🇮🇶'],
    ['code' => 'IE', 'name' => 'Ireland', 'flag' => '🇮🇪'],
    ['code' => 'IL', 'name' => 'Israel', 'flag' => '🇮🇱'],
    ['code' => 'IT', 'name' => 'Italy', 'flag' => '🇮🇹'],
    ['code' => 'JM', 'name' => 'Jamaica', 'flag' => '🇯🇲'],
    ['code' => 'JP', 'name' => 'Japan', 'flag' => '🇯🇵'],
    ['code' => 'JO', 'name' => 'Jordan', 'flag' => '🇯🇴'],
    ['code' => 'KZ', 'name' => 'Kazakhstan', 'flag' => '🇰🇿'],
    ['code' => 'KE', 'name' => 'Kenya', 'flag' => '🇰🇪'],
    ['code' => 'KI', 'name' => 'Kiribati', 'flag' => '🇰🇮'],
    ['code' => 'XK', 'name' => 'Kosovo', 'flag' => '🇽🇰'],
    ['code' => 'KW', 'name' => 'Kuwait', 'flag' => '🇰🇼'],
    ['code' => 'KG', 'name' => 'Kyrgyzstan', 'flag' => '🇰🇬'],
    ['code' => 'LA', 'name' => 'Laos', 'flag' => '🇱🇦'],
    ['code' => 'LV', 'name' => 'Latvia', 'flag' => '🇱🇻'],
    ['code' => 'LB', 'name' => 'Lebanon', 'flag' => '🇱🇧'],
    ['code' => 'LS', 'name' => 'Lesotho', 'flag' => '🇱🇸'],
    ['code' => 'LR', 'name' => 'Liberia', 'flag' => '🇱🇷'],
    ['code' => 'LY', 'name' => 'Libya', 'flag' => '🇱🇾'],
    ['code' => 'LI', 'name' => 'Liechtenstein', 'flag' => '🇱🇮'],
    ['code' => 'LT', 'name' => 'Lithuania', 'flag' => '🇱🇹'],
    ['code' => 'LU', 'name' => 'Luxembourg', 'flag' => '🇱🇺'],
    ['code' => 'MG', 'name' => 'Madagascar', 'flag' => '🇲🇬'],
    ['code' => 'MW', 'name' => 'Malawi', 'flag' => '🇲🇼'],
    ['code' => 'MY', 'name' => 'Malaysia', 'flag' => '🇲🇾'],
    ['code' => 'MV', 'name' => 'Maldives', 'flag' => '🇲🇻'],
    ['code' => 'ML', 'name' => 'Mali', 'flag' => '🇲🇱'],
    ['code' => 'MT', 'name' => 'Malta', 'flag' => '🇲🇹'],
    ['code' => 'MH', 'name' => 'Marshall Islands', 'flag' => '🇲🇭'],
    ['code' => 'MR', 'name' => 'Mauritania', 'flag' => '🇲🇷'],
    ['code' => 'MU', 'name' => 'Mauritius', 'flag' => '🇲🇺'],
    ['code' => 'MX', 'name' => 'Mexico', 'flag' => '🇲🇽'],
    ['code' => 'FM', 'name' => 'Micronesia', 'flag' => '🇫🇲'],
    ['code' => 'MD', 'name' => 'Moldova', 'flag' => '🇲🇩'],
    ['code' => 'MC', 'name' => 'Monaco', 'flag' => '🇲🇨'],
    ['code' => 'MN', 'name' => 'Mongolia', 'flag' => '🇲🇳'],
    ['code' => 'ME', 'name' => 'Montenegro', 'flag' => '🇲🇪'],
    ['code' => 'MA', 'name' => 'Morocco', 'flag' => '🇲🇦'],
    ['code' => 'MZ', 'name' => 'Mozambique', 'flag' => '🇲🇿'],
    ['code' => 'MM', 'name' => 'Myanmar', 'flag' => '🇲🇲'],
    ['code' => 'NA', 'name' => 'Namibia', 'flag' => '🇳🇦'],
    ['code' => 'NR', 'name' => 'Nauru', 'flag' => '🇳🇷'],
    ['code' => 'NP', 'name' => 'Nepal', 'flag' => '🇳🇵'],
    ['code' => 'NL', 'name' => 'Netherlands', 'flag' => '🇳🇱'],
    ['code' => 'NZ', 'name' => 'New Zealand', 'flag' => '🇳🇿'],
    ['code' => 'NI', 'name' => 'Nicaragua', 'flag' => '🇳🇮'],
    ['code' => 'NE', 'name' => 'Niger', 'flag' => '🇳🇪'],
    ['code' => 'NG', 'name' => 'Nigeria', 'flag' => '🇳🇬'],
    ['code' => 'KP', 'name' => 'North Korea', 'flag' => '🇰🇵'],
    ['code' => 'MK', 'name' => 'North Macedonia', 'flag' => '🇲🇰'],
    ['code' => 'NO', 'name' => 'Norway', 'flag' => '🇳🇴'],
    ['code' => 'OM', 'name' => 'Oman', 'flag' => '🇴🇲'],
    ['code' => 'PK', 'name' => 'Pakistan', 'flag' => '🇵🇰'],
    ['code' => 'PW', 'name' => 'Palau', 'flag' => '🇵🇼'],
    ['code' => 'PA', 'name' => 'Panama', 'flag' => '🇵🇦'],
    ['code' => 'PG', 'name' => 'Papua New Guinea', 'flag' => '🇵🇬'],
    ['code' => 'PY', 'name' => 'Paraguay', 'flag' => '🇵🇾'],
    ['code' => 'PE', 'name' => 'Peru', 'flag' => '🇵🇪'],
    ['code' => 'PH', 'name' => 'Philippines', 'flag' => '🇵🇭'],
    ['code' => 'PL', 'name' => 'Poland', 'flag' => '🇵🇱'],
    ['code' => 'PT', 'name' => 'Portugal', 'flag' => '🇵🇹'],
    ['code' => 'QA', 'name' => 'Qatar', 'flag' => '🇶🇦'],
    ['code' => 'RO', 'name' => 'Romania', 'flag' => '🇷🇴'],
    ['code' => 'RU', 'name' => 'Russia', 'flag' => '🇷🇺'],
    ['code' => 'RW', 'name' => 'Rwanda', 'flag' => '🇷🇼'],
    ['code' => 'KN', 'name' => 'Saint Kitts and Nevis', 'flag' => '🇰🇳'],
    ['code' => 'LC', 'name' => 'Saint Lucia', 'flag' => '🇱🇨'],
    ['code' => 'VC', 'name' => 'Saint Vincent and the Grenadines', 'flag' => '🇻🇨'],
    ['code' => 'WS', 'name' => 'Samoa', 'flag' => '🇼🇸'],
    ['code' => 'SM', 'name' => 'San Marino', 'flag' => '🇸🇲'],
    ['code' => 'ST', 'name' => 'Sao Tome and Principe', 'flag' => '🇸🇹'],
    ['code' => 'SA', 'name' => 'Saudi Arabia', 'flag' => '🇸🇦'],
    ['code' => 'SN', 'name' => 'Senegal', 'flag' => '🇸🇳'],
    ['code' => 'RS', 'name' => 'Serbia', 'flag' => '🇷🇸'],
    ['code' => 'SC', 'name' => 'Seychelles', 'flag' => '🇸🇨'],
    ['code' => 'SL', 'name' => 'Sierra Leone', 'flag' => '🇸🇱'],
    ['code' => 'SG', 'name' => 'Singapore', 'flag' => '🇸🇬'],
    ['code' => 'SK', 'name' => 'Slovakia', 'flag' => '🇸🇰'],
    ['code' => 'SI', 'name' => 'Slovenia', 'flag' => '🇸🇮'],
    ['code' => 'SB', 'name' => 'Solomon Islands', 'flag' => '🇸🇧'],
    ['code' => 'SO', 'name' => 'Somalia', 'flag' => '🇸🇴'],
    ['code' => 'ZA', 'name' => 'South Africa', 'flag' => '🇿🇦'],
    ['code' => 'KR', 'name' => 'South Korea', 'flag' => '🇰🇷'],
    ['code' => 'SS', 'name' => 'South Sudan', 'flag' => '🇸🇸'],
    ['code' => 'ES', 'name' => 'Spain', 'flag' => '🇪🇸'],
    ['code' => 'LK', 'name' => 'Sri Lanka', 'flag' => '🇱🇰'],
    ['code' => 'SD', 'name' => 'Sudan', 'flag' => '🇸🇩'],
    ['code' => 'SR', 'name' => 'Suriname', 'flag' => '🇸🇷'],
    ['code' => 'SE', 'name' => 'Sweden', 'flag' => '🇸🇪'],
    ['code' => 'CH', 'name' => 'Switzerland', 'flag' => '🇨🇭'],
    ['code' => 'SY', 'name' => 'Syria', 'flag' => '🇸🇾'],
    ['code' => 'TW', 'name' => 'Taiwan', 'flag' => '🇹🇼'],
    ['code' => 'TJ', 'name' => 'Tajikistan', 'flag' => '🇹🇯'],
    ['code' => 'TZ', 'name' => 'Tanzania', 'flag' => '🇹🇿'],
    ['code' => 'TH', 'name' => 'Thailand', 'flag' => '🇹🇭'],
    ['code' => 'TL', 'name' => 'Timor-Leste', 'flag' => '🇹🇱'],
    ['code' => 'TG', 'name' => 'Togo', 'flag' => '🇹🇬'],
    ['code' => 'TO', 'name' => 'Tonga', 'flag' => '🇹🇴'],
    ['code' => 'TT', 'name' => 'Trinidad and Tobago', 'flag' => '🇹🇹'],
    ['code' => 'TN', 'name' => 'Tunisia', 'flag' => '🇹🇳'],
    ['code' => 'TR', 'name' => 'Turkey', 'flag' => '🇹🇷'],
    ['code' => 'TM', 'name' => 'Turkmenistan', 'flag' => '🇹🇲'],
    ['code' => 'TV', 'name' => 'Tuvalu', 'flag' => '🇹🇻'],
    ['code' => 'UG', 'name' => 'Uganda', 'flag' => '🇺🇬'],
    ['code' => 'UA', 'name' => 'Ukraine', 'flag' => '🇺🇦'],
    ['code' => 'AE', 'name' => 'United Arab Emirates', 'flag' => '🇦🇪'],
    ['code' => 'GB', 'name' => 'United Kingdom', 'flag' => '🇬🇧'],
    ['code' => 'US', 'name' => 'United States', 'flag' => '🇺🇸'],
    ['code' => 'UY', 'name' => 'Uruguay', 'flag' => '🇺🇾'],
    ['code' => 'UZ', 'name' => 'Uzbekistan', 'flag' => '🇺🇿'],
    ['code' => 'VU', 'name' => 'Vanuatu', 'flag' => '🇻🇺'],
    ['code' => 'VA', 'name' => 'Vatican City', 'flag' => '🇻🇦'],
    ['code' => 'VE', 'name' => 'Venezuela', 'flag' => '🇻🇪'],
    ['code' => 'VN', 'name' => 'Vietnam', 'flag' => '🇻🇳'],
    ['code' => 'YE', 'name' => 'Yemen', 'flag' => '🇾🇪'],
    ['code' => 'ZM', 'name' => 'Zambia', 'flag' => '🇿🇲'],
    ['code' => 'ZW', 'name' => 'Zimbabwe', 'flag' => '🇿🇼']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Complete Your Profile - HeyDream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fc 0%, #eef2f8 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        /* animated background particles */
        .bg-shape {
            position: fixed;
            z-index: 0;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.3;
            animation: floatShape 18s infinite ease-in-out;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            background: #4f46e5;
            top: -100px;
            left: -100px;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            background: #06b6d4;
            bottom: -150px;
            right: -120px;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 200px;
            height: 200px;
            background: #f97316;
            top: 40%;
            right: 10%;
            animation-delay: -10s;
            opacity: 0.2;
        }

        @keyframes floatShape {
            0% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(30px, 40px) scale(1.1);
            }

            100% {
                transform: translate(0, 0) scale(1);
            }
        }

        .container {
            max-width: 880px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        /* back link with slide effect */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(4px);
            padding: 8px 18px;
            border-radius: 40px;
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 24px;
            transition: all 0.25s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .back-link i {
            transition: transform 0.2s ease;
        }

        .back-link:hover {
            background: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            transform: translateX(4px);
            border-color: #e2e8f0;
        }

        .back-link:hover i {
            transform: translateX(3px);
        }

        /* main card with entrance animation */
        .card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 36px;
            padding: 36px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.02);
            backdrop-filter: blur(2px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: cardGlideUp 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        @keyframes cardGlideUp {
            0% {
                opacity: 0;
                transform: translateY(28px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card:hover {
            box-shadow: 0 30px 55px -12px rgba(0, 0, 0, 0.2);
        }

        h2 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -0.02em;
            margin-bottom: 10px;
        }

        .subhead {
            color: #475569;
            font-size: 0.95rem;
            border-left: 3px solid #4f46e5;
            padding-left: 14px;
            margin: 8px 0 24px 0;
            line-height: 1.4;
        }

        /* error alert animation */
        .error-alert {
            background: #fff1f0;
            border-left: 4px solid #e11d48;
            padding: 16px 20px;
            border-radius: 20px;
            margin: 20px 0;
            color: #be123c;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shakeFade 0.4s ease;
            font-size: 0.9rem;
        }

        @keyframes shakeFade {
            0% {
                opacity: 0;
                transform: translateX(-8px);
            }

            60% {
                transform: translateX(4px);
            }

            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* avatar section modern */
        .avatar-section {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 28px;
            margin-bottom: 32px;
            background: #f8fafc;
            padding: 20px 24px;
            border-radius: 28px;
            transition: all 0.2s;
        }

        .avatar-wrapper {
            position: relative;
        }

        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(145deg, #eef2ff, #e0e7ff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            font-weight: 600;
            color: #4f46e5;
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.15);
            overflow: hidden;
            transition: all 0.25s ease;
            border: 2px solid white;
            outline: 1px solid rgba(79, 70, 229, 0.2);
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .avatar-preview:hover img {
            transform: scale(1.03);
        }

        .upload-label {
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 40px;
            padding: 8px 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        .upload-label:hover {
            background: #f1f5f9;
            border-color: #4f46e5;
            color: #4f46e5;
            transform: translateY(-2px);
        }

        input[type="file"] {
            display: none;
        }

        /* form styling */
        .form-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .form-col {
            flex: 1;
            min-width: 170px;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 8px;
        }

        input,
        select {
            width: 100%;
            padding: 14px 16px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            transition: all 0.2s ease;
            outline: none;
            color: #0f172a;
        }

        input:focus,
        select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            transform: scale(1.01);
        }

        input:hover,
        select:hover {
            border-color: #cbd5e1;
        }

        /* Country dropdown with search inside */
        .country-selector {
            position: relative;
            width: 100%;
        }

        .country-display {
            width: 100%;
            padding: 14px 16px;
            font-size: 0.95rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }

        .country-display:hover {
            border-color: #cbd5e1;
            background: #fafcff;
        }

        .display-flag {
            font-size: 1.3rem;
            width: 32px;
        }

        .display-name {
            flex: 1;
            color: #0f172a;
        }

        .display-placeholder {
            flex: 1;
            color: #94a3b8;
        }

        .dropdown-arrow {
            color: #94a3b8;
            transition: transform 0.2s;
            font-size: 0.8rem;
        }

        .country-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            max-height: 340px;
            overflow: hidden;
            z-index: 100;
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
        }

        .country-dropdown.show {
            display: flex;
            animation: dropdownFade 0.2s ease;
        }

        @keyframes dropdownFade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-search {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        .dropdown-search input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            font-size: 0.85rem;
            border-radius: 30px;
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .dropdown-search input:focus {
            background: white;
        }

        .search-icon {
            position: absolute;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.8rem;
            pointer-events: none;
        }

        .countries-list {
            max-height: 260px;
            overflow-y: auto;
        }

        .country-option {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }

        .country-option:hover {
            background: #f8fafc;
        }

        .country-option.selected {
            background: #eef2ff;
            color: #4f46e5;
        }

        .option-flag {
            font-size: 1.2rem;
            width: 28px;
        }

        .option-name {
            flex: 1;
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .hidden-country-input {
            display: none;
        }

        .countries-list::-webkit-scrollbar {
            width: 5px;
        }

        .countries-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .countries-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        /* button group */
        .button-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            margin-top: 28px;
        }

        .btn-primary {
            background: linear-gradient(105deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .btn-primary i {
            font-size: 0.9rem;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            background: linear-gradient(105deg, #1e293b, #0f172a);
            transform: translateY(-2px);
            box-shadow: 0 12px 22px -10px rgba(15, 23, 42, 0.3);
        }

        .btn-primary:hover i {
            transform: translateX(4px);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #cbd5e1;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 500;
            color: #334155;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            transform: translateY(-1px);
        }

        /* micro-interactions for row */
        .form-col {
            animation: fadeSlide 0.5s ease backwards;
            animation-delay: calc(var(--order, 0) * 0.05s);
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* responsive */
        @media (max-width: 640px) {
            .card {
                padding: 24px 20px;
            }

            .avatar-section {
                flex-direction: column;
                align-items: flex-start;
            }

            h2 {
                font-size: 1.6rem;
            }
        }

        /* help text */
        .photo-hint {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 6px;
        }

        .toast-message {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: white;
            padding: 10px 24px;
            border-radius: 60px;
            font-size: 0.85rem;
            font-weight: 500;
            z-index: 1000;
            animation: fadeUpOut 2.5s forwards;
            pointer-events: none;
        }

        @keyframes fadeUpOut {
            0% {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }

            15% {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }

            85% {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }

            100% {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
                visibility: hidden;
            }
        }
    </style>
</head>

<body>
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>
    <div class="bg-shape shape-3"></div>

    <div class="container">
        <div style="display: flex; justify-content: flex-end;">
            <a href="login.php?logout=1" class="back-link">
                Back <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="card">
            <h2><i class="fas fa-user-astronaut"
                    style="font-size: 1.8rem; margin-right: 6px; background: linear-gradient(135deg,#4f46e5,#06b6d4); background-clip: text; -webkit-background-clip: text; color: transparent;"></i>
                Complete your profile</h2>
            <div class="subhead">Tell us a bit about yourself — personalize your HeyDream experience</div>

            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!$error): ?>
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <!-- avatar + upload modern -->
                    <div class="avatar-section">
                        <div class="avatar-wrapper">
                            <div class="avatar-preview" id="avatarPreview">
                                <?php if (!empty($pref['profile_pic']) && file_exists(__DIR__ . '/../' . $pref['profile_pic'])): ?>
                                    <img src="../<?= htmlspecialchars($pref['profile_pic']) ?>" alt="profile" id="avatarImg">
                                <?php else: ?>
                                    <span id="avatarInitials"><?= strtoupper(substr($pref['full_name'] ?? 'U', 0, 2)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label for="profile_pic" class="upload-label">
                                <i class="fas fa-camera"></i> Upload new photo
                            </label>
                            <input type="file" name="profile_pic" id="profile_pic"
                                accept="image/jpeg,image/png,image/gif,image/jpg">
                            <div class="photo-hint"><i class="far fa-image"></i> JPG, PNG or GIF, max 5MB</div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-row">
                            <div class="form-col" style="--order:1">
                                <label><i class="fas fa-user-tag"></i> Title</label>
                                <select name="title">
                                    <option value="" <?= $pref['title'] == '' ? 'selected' : '' ?>>Select</option>
                                    <option value="Mr" <?= $pref['title'] == 'Mr' ? 'selected' : '' ?>>Mr</option>
                                    <option value="Ms" <?= $pref['title'] == 'Ms' ? 'selected' : '' ?>>Ms</option>
                                    <option value="Mrs" <?= $pref['title'] == 'Mrs' ? 'selected' : '' ?>>Mrs</option>
                                    <option value="Mx" <?= $pref['title'] == 'Mx' ? 'selected' : '' ?>>Mx</option>
                                </select>
                            </div>
                            <div class="form-col" style="flex:2; --order:2">
                                <label><i class="fas fa-signature"></i> Full name</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($pref['full_name']) ?>"
                                    placeholder="e.g., Emily Rodriguez">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col" style="--order:3">
                                <label><i class="fas fa-calendar-alt"></i> Date of birth</label>
                                <input type="date" name="dob" value="<?= htmlspecialchars($pref['dob']) ?>">
                            </div>
                            <div class="form-col" style="--order:4; position: relative;">
                                <label><i class="fas fa-globe-americas"></i> Country</label>
                                <div class="country-selector" id="countrySelector">
                                    <div class="country-display" id="countryDisplay">
                                        <?php
                                        $selectedCountryData = null;
                                        foreach ($countries as $c) {
                                            if ($c['name'] === $pref['country']) {
                                                $selectedCountryData = $c;
                                                break;
                                            }
                                        }
                                        ?>
                                        <span class="display-flag"
                                            id="displayFlag"><?= $selectedCountryData ? $selectedCountryData['flag'] : '🌍' ?></span>
                                        <span class="display-name"
                                            id="displayName"><?= htmlspecialchars($pref['country'] ?: 'Select your country') ?></span>
                                        <?php if (!$pref['country']): ?>
                                            <span class="display-placeholder" id="displayPlaceholder"
                                                style="display: none;">Select your country</span>
                                        <?php endif; ?>
                                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                                    </div>
                                    <div class="country-dropdown" id="countryDropdown">
                                        <div class="dropdown-search" style="position: relative;">
                                            <i class="fas fa-search search-icon"></i>
                                            <input type="text" id="countrySearchInput" placeholder="Search country..."
                                                autocomplete="off">
                                        </div>
                                        <div class="countries-list" id="countriesList"></div>
                                    </div>
                                    <input type="hidden" name="country" id="countryInput"
                                        value="<?= htmlspecialchars($pref['country']) ?>">
                                </div>
                            </div>
                            <div class="form-col" style="--order:5">
                                <label><i class="fas fa-phone-alt"></i> Phone</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($pref['phone']) ?>"
                                    placeholder="+1 234 567 890">
                            </div>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-primary" id="saveBtn">
                            <i class="fas fa-check-circle"></i> Save & Continue
                        </button>
                        <button type="submit" name="skip" value="1" class="btn-secondary" formnovalidate>
                            <i class="fas fa-clock"></i> Do this later
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        (function () {
            // Country data from PHP
            const countriesData = <?php echo json_encode($countries); ?>;
            const preSelectedCountry = <?php echo json_encode($pref['country']); ?>;

            // DOM elements
            const countrySelector = document.getElementById('countrySelector');
            const countryDisplay = document.getElementById('countryDisplay');
            const countryDropdown = document.getElementById('countryDropdown');
            const countrySearchInput = document.getElementById('countrySearchInput');
            const countriesList = document.getElementById('countriesList');
            const displayFlag = document.getElementById('displayFlag');
            const displayName = document.getElementById('displayName');
            const countryInput = document.getElementById('countryInput');

            let currentSelected = preSelectedCountry || '';
            let isDropdownOpen = false;

            // Render countries list based on search filter
            function renderCountries(filterText = '') {
                const filtered = countriesData.filter(c =>
                    c.name.toLowerCase().includes(filterText.toLowerCase())
                );

                if (filtered.length === 0) {
                    countriesList.innerHTML = '<div class="no-results"><i class="fas fa-search"></i> No countries found</div>';
                    return;
                }

                countriesList.innerHTML = filtered.map(country => `
                <div class="country-option" data-country="${country.name}" data-flag="${country.flag}">
                    <span class="option-flag">${country.flag}</span>
                    <span class="option-name">${country.name}</span>
                </div>
            `).join('');

                // Add click handlers and highlight selected
                document.querySelectorAll('.country-option').forEach(opt => {
                    const countryName = opt.getAttribute('data-country');
                    if (countryName === currentSelected) {
                        opt.classList.add('selected');
                    }

                    opt.addEventListener('click', function (e) {
                        const countryName = this.getAttribute('data-country');
                        const flag = this.getAttribute('data-flag');
                        selectCountry(countryName, flag);
                        closeDropdown();
                    });
                });
            }

            // Select a country
            function selectCountry(countryName, flag) {
                currentSelected = countryName;
                displayFlag.textContent = flag || '🌍';
                displayName.textContent = countryName;
                displayName.style.color = '#0f172a';
                countryInput.value = countryName;

                // Update selected class in dropdown
                document.querySelectorAll('.country-option').forEach(opt => {
                    if (opt.getAttribute('data-country') === countryName) {
                        opt.classList.add('selected');
                    } else {
                        opt.classList.remove('selected');
                    }
                });
            }

            // Open dropdown
            function openDropdown() {
                if (isDropdownOpen) return;
                isDropdownOpen = true;
                countryDropdown.classList.add('show');
                renderCountries('');
                countrySearchInput.value = '';
                countrySearchInput.focus();
            }

            // Close dropdown
            function closeDropdown() {
                isDropdownOpen = false;
                countryDropdown.classList.remove('show');
            }

            // Toggle dropdown
            function toggleDropdown(e) {
                e.stopPropagation();
                if (isDropdownOpen) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            }

            // Event listeners
            countryDisplay.addEventListener('click', toggleDropdown);

            countrySearchInput.addEventListener('input', function (e) {
                if (isDropdownOpen) {
                    renderCountries(e.target.value);
                }
            });

            countrySearchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeDropdown();
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (countrySelector && !countrySelector.contains(e.target)) {
                    closeDropdown();
                }
            });

            // Prevent closing when clicking inside dropdown
            countryDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            // Initialize display
            if (currentSelected) {
                const selected = countriesData.find(c => c.name === currentSelected);
                if (selected) {
                    displayFlag.textContent = selected.flag;
                    displayName.textContent = selected.name;
                    displayName.style.color = '#0f172a';
                }
            } else {
                displayName.textContent = 'Select your country';
                displayName.style.color = '#94a3b8';
            }

            // live avatar preview & animation on file select
            const fileInput = document.getElementById('profile_pic');
            const avatarPreviewDiv = document.querySelector('.avatar-preview');
            const fullNameInput = document.querySelector('input[name="full_name"]');

            function updateAvatarPreview(file) {
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function (e) {
                    avatarPreviewDiv.innerHTML = '';
                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.style.width = '100%';
                    newImg.style.height = '100%';
                    newImg.style.objectFit = 'cover';
                    newImg.alt = 'profile preview';
                    avatarPreviewDiv.appendChild(newImg);
                    newImg.style.animation = 'scalePop 0.25s ease';
                    const styleAnim = document.createElement('style');
                    if (!document.querySelector('#popAnim')) {
                        styleAnim.id = 'popAnim';
                        styleAnim.innerText = `@keyframes scalePop { 0% { transform: scale(0.9); opacity:0; } 100% { transform: scale(1); opacity:1; } }`;
                        document.head.appendChild(styleAnim);
                    }
                };
                reader.readAsDataURL(file);
            }

            if (fileInput) {
                fileInput.addEventListener('change', function (e) {
                    if (this.files && this.files[0]) {
                        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        if (!allowed.includes(this.files[0].type)) {
                            showFloatingMessage('Please select a valid image (JPG, PNG, GIF)', 2000);
                            this.value = '';
                            return;
                        }
                        if (this.files[0].size > 5 * 1024 * 1024) {
                            showFloatingMessage('Image should be less than 5MB', 2200);
                            this.value = '';
                            return;
                        }
                        updateAvatarPreview(this.files[0]);
                    }
                });
            }

            function updateInitials() {
                if (avatarPreviewDiv && !avatarPreviewDiv.querySelector('img') && fullNameInput) {
                    let nameVal = fullNameInput.value.trim();
                    let initials = 'U';
                    if (nameVal.length > 0) {
                        const parts = nameVal.split(' ');
                        if (parts.length === 1) initials = parts[0].substring(0, 2).toUpperCase();
                        else initials = (parts[0].charAt(0) + (parts[parts.length - 1].charAt(0) || '')).toUpperCase();
                        if (initials.length > 2) initials = initials.substring(0, 2);
                    }
                    if (!avatarPreviewDiv.querySelector('img')) {
                        avatarPreviewDiv.innerHTML = `<span style="font-size:2.2rem; font-weight:600;">${initials}</span>`;
                    }
                }
            }

            if (fullNameInput) {
                fullNameInput.addEventListener('input', function () {
                    if (!avatarPreviewDiv.querySelector('img')) {
                        updateInitials();
                    }
                });
            }

            function showFloatingMessage(msg, duration = 2000) {
                let existingToast = document.querySelector('.toast-message');
                if (existingToast) existingToast.remove();
                const toast = document.createElement('div');
                toast.className = 'toast-message';
                toast.innerHTML = `<i class="fas fa-info-circle" style="margin-right:8px;"></i>${msg}`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    if (toast && toast.parentNode) toast.remove();
                }, duration);
            }

            const form = document.getElementById('profileForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    const submitter = e.submitter || document.activeElement;
                    if (submitter && submitter.name === 'skip') {
                        return true;
                    }

                    e.preventDefault();

                    const saveBtn = document.getElementById('saveBtn');
                    if (saveBtn) {
                        saveBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Saving...';
                        saveBtn.disabled = true;
                    }

                    const formData = new FormData(form);
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-check-circle"></i> Done!';
                                setTimeout(() => {
                                    window.location.href = data.redirect || '../index.php';
                                }, 150);
                            } else {
                                showFloatingMessage(data.error || 'Failed to save. Please try again.', 3000);
                                if (saveBtn) {
                                    saveBtn.disabled = false;
                                    saveBtn.innerHTML = '<i class="fas fa-check-circle"></i> Save & Continue';
                                }
                            }
                        })
                        .catch(() => {
                            showFloatingMessage('Network error. Please try again.', 3000);
                            if (saveBtn) {
                                saveBtn.disabled = false;
                                saveBtn.innerHTML = '<i class="fas fa-check-circle"></i> Save & Continue';
                            }
                        });
                });

                const skipBtn = form.querySelector('button[name="skip"]');
                if (skipBtn) {
                    skipBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        skipBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Redirecting...';
                        // Redirect instantly via JS — session already exists from login
                        setTimeout(() => {
                            window.location.href = '../index.php';
                        }, 150);
                    });
                }
            }

            // Initialize avatar initials
            if (fullNameInput && avatarPreviewDiv && !avatarPreviewDiv.querySelector('img')) {
                updateInitials();
            }
        })();
    </script>
    <style>
        .btn-primary:active {
            transform: scale(0.97);
        }

        input,
        select {
            transition: all 0.2s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        .avatar-preview {
            transition: all 0.2s ease;
        }

        .card {
            transition: transform 0.25s ease, box-shadow 0.3s;
        }

        .upload-label i {
            pointer-events: none;
        }
    </style>
</body>

</html>
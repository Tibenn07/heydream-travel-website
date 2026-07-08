<?php
// File: admin/api/marketing_api.php
ob_start(); // Start buffer first thing
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

// Load PHPMailer files at the top
require_once __DIR__ . '/../../PHPMailer/Exception.php';
require_once __DIR__ . '/../../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$debugLog = __DIR__ . '/../../admin/admin_api_debug.log';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// General logging for debugging (matching admin-api.php pattern)
$logEntry = "\n" . date('Y-m-d H:i:s') . " - === Marketing API Request Started ===\n";
$logEntry .= date('Y-m-d H:i:s') . " - Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$logEntry .= date('Y-m-d H:i:s') . " - Action: $action\n";
$logEntry .= date('Y-m-d H:i:s') . " - Admin: " . ($_SESSION['admin_username'] ?? 'Not Logged In') . " (Role: " . ($_SESSION['admin_role'] ?? 'None') . ")\n";
if (!empty($_POST))
    $logEntry .= date('Y-m-d H:i:s') . " - POST Data: " . print_r($_POST, true) . "\n";
file_put_contents($debugLog, $logEntry, FILE_APPEND);

switch ($action) {
    case 'get_templates':
        $stmt = $pdo->query("SELECT * FROM marketing_templates ORDER BY created_at DESC");
        ob_clean();
        echo json_encode(['success' => true, 'templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'save_template':
        $name = $_POST['name'] ?? 'Untitled Template';
        $blocks = $_POST['blocks'] ?? '[]'; // JSON string of blocks

        // Use the 'body' column to store the blocks JSON
        $stmt = $pdo->prepare("INSERT INTO marketing_templates (name, body) VALUES (?, ?)");
        ob_clean();
        if ($stmt->execute([$name, $blocks])) {
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save template']);
        }
        break;

    case 'get_campaign_list':
        try {
            $stmt = $pdo->query("SELECT id, subject, status, scheduled_at, audience, created_at FROM marketing_campaigns ORDER BY created_at DESC LIMIT 50");
            $camps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'data' => $camps]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_campaign_analytics':
        try {
            $campaign_id = isset($_GET['campaign_id']) ? $_GET['campaign_id'] : null;
            $campaign_ids = isset($_GET['campaign_ids']) ? explode(',', $_GET['campaign_ids']) : [];

            // Map emails to social media sources from inquiries
            $stmt = $pdo->prepare("SELECT email, special_requests FROM bookings WHERE email IS NOT NULL AND email != '' AND payment_method = 'Inquiry Only'");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $emailToSource = [];
            foreach ($rows as $row) {
                $reqText = strtolower($row['special_requests']);
                $source = 'Other';
                if (strpos($reqText, 'facebook') !== false)
                    $source = 'Facebook';
                elseif (strpos($reqText, 'instagram') !== false)
                    $source = 'Instagram';
                elseif (strpos($reqText, 'twitter') !== false)
                    $source = 'Twitter';
                elseif (strpos($reqText, 'tiktok') !== false)
                    $source = 'TikTok';

                $email = strtolower($row['email']);
                if (!isset($emailToSource[$email]))
                    $emailToSource[$email] = $source;
            }

            // Count clicks per source — optionally filtered by campaign(s)
            $sources = ['Facebook', 'Instagram', 'Twitter', 'TikTok', 'Other'];
            $clickStats = array_fill_keys($sources, 0);

            if (!empty($campaign_ids)) {
                $placeholders = implode(',', array_fill(0, count($campaign_ids), '?'));
                $stmtTrack = $pdo->prepare("SELECT t.email FROM marketing_tracking t 
                                        INNER JOIN marketing_campaigns c ON t.campaign_id = c.id 
                                        WHERE t.action = 'click' AND t.campaign_id IN ($placeholders)");
                $stmtTrack->execute($campaign_ids);
            } elseif ($campaign_id && $campaign_id !== 'all') {
                $stmtTrack = $pdo->prepare("SELECT t.email FROM marketing_tracking t 
                                        INNER JOIN marketing_campaigns c ON t.campaign_id = c.id 
                                        WHERE t.action = 'click' AND t.campaign_id = ?");
                $stmtTrack->execute([$campaign_id]);
            } else {
                $stmtTrack = $pdo->query("SELECT t.email FROM marketing_tracking t 
                                        INNER JOIN marketing_campaigns c ON t.campaign_id = c.id 
                                        WHERE t.action = 'click'");
            }

            while ($t = $stmtTrack->fetch()) {
                $email = strtolower($t['email']);
                $source = $emailToSource[$email] ?? 'Other';
                if (isset($clickStats[$source])) {
                    $clickStats[$source]++;
                }
            }

            ob_clean();
            echo json_encode(['success' => true, 'data' => $clickStats]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'upload_promo_image':
        if (!isset($_FILES['promo_image'])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'No image uploaded']);
            break;
        }

        $upload_dir = __DIR__ . '/../../uploads/marketing/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file = $_FILES['promo_image'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'promo_' . time() . '.' . $ext;
        $target = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            ob_clean();
            echo json_encode(['success' => true, 'url' => '../uploads/marketing/' . $filename]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to save image']);
        }
        break;

    case 'schedule_campaign':
        $subject = $_POST['subject'] ?? 'Scheduled Campaign';
        $audience = $_POST['audience'] ?? 'all';
        $scheduled_at = $_POST['scheduled_at'] ?? null;
        $blocks = $_POST['blocks'] ?? '[]';

        if (!$scheduled_at) {
            echo json_encode(['success' => false, 'message' => 'No schedule time provided']);
            break;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO marketing_campaigns (subject, audience, blocks, scheduled_at, status, sent_count) VALUES (?, ?, ?, ?, 'scheduled', 0)");
            $stmt->execute([$subject, $audience, $blocks, $scheduled_at]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_inquiry':
        try {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $booking_number = $_GET['booking_number'] ?? '';
            
            if ($id === 0 && $booking_number !== '') {
                $stmt = $pdo->prepare("SELECT id, full_name, email, phone, travel_date, number_of_travelers, package_name as destination, special_requests, booking_status, created_at, booking_number FROM bookings WHERE booking_number = ?");
                $stmt->execute([$booking_number]);
            } else if ($id > 0) {
                $stmt = $pdo->prepare("SELECT id, full_name, email, phone, travel_date, number_of_travelers, package_name as destination, special_requests, booking_status, created_at, booking_number FROM bookings WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Missing ID or Booking Number']);
                break;
            }
            
            $inq = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($inq) {
                ob_clean();
                echo json_encode(['success' => true, 'data' => $inq]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
            }
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update_inquiry_status':
        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $booking_number = $_POST['booking_number'] ?? '';
            $status = $_POST['status'] ?? 'pending';

            if ($id === 0 && $booking_number !== '') {
                $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE booking_number = ?");
                $stmt->execute([$status, $booking_number]);
                $lookupId = $booking_number; // using booking_number for the email script
            } else if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                $lookupId = $id;
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Missing ID or Booking Number']);
                break;
            }

            // Send email to customer
            require_once __DIR__ . '/../../config/email_functions.php';
            if (function_exists('sendBookingStatusEmail')) {
                sendBookingStatusEmail($lookupId);
            }

            ob_clean();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_inquiry':
        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $booking_number = $_POST['booking_number'] ?? '';

            if ($id === 0 && $booking_number !== '') {
                $stmt = $pdo->prepare("DELETE FROM bookings WHERE booking_number = ?");
                $stmt->execute([$booking_number]);
            } else if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Missing ID or Booking Number']);
                break;
            }

            ob_clean();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'send_campaign':
        // Disable ALL levels of output buffering to ensure real-time streaming
        while (ob_get_level())
            ob_end_clean();
        ob_implicit_flush(true);

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');

        // Send aggressive padding to break all possible proxy/browser buffers (up to 16KB)
        echo str_repeat(' ', 16384) . "\n";
        flush();

        set_time_limit(0); // No time limit for large campaigns
        ignore_user_abort(false);

        $subject = $_POST['subject'] ?? 'New Campaign from HeyDream';
        $blocks = json_decode($_POST['blocks'] ?? '[]', true);
        $audience = $_POST['audience'] ?? 'all';

        // Fetch recipients
        // Fetch recipients (Only those who gave marketing consent)
        if ($audience === 'website')
            $stmt = $pdo->query("SELECT email, full_name FROM users WHERE email IS NOT NULL AND email != '' AND marketing_consent = 1 GROUP BY email");
        elseif ($audience === 'inquiries')
            $stmt = $pdo->query("SELECT email, full_name FROM bookings WHERE email IS NOT NULL AND email != '' AND payment_method = 'Inquiry Only' AND marketing_consent = 1 GROUP BY email");
        else
            $stmt = $pdo->query("SELECT email, full_name FROM users WHERE email IS NOT NULL AND email != '' AND marketing_consent = 1 UNION SELECT email, full_name FROM bookings WHERE email IS NOT NULL AND email != '' AND marketing_consent = 1");

        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCount = count($recipients);

        // LOG START OF PROCESS
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - CAMPAIGN START - Subject: $subject, Audience: $audience, Recipients Found: $totalCount\n", FILE_APPEND);

        if ($totalCount === 0) {
            echo json_encode(['type' => 'error', 'message' => 'No recipients found with marketing consent. Please check your leads/users and ensure they have marketing notifications enabled.']) . "\n";
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - CAMPAIGN ERROR: No recipients found.\n", FILE_APPEND);
            exit;
        }

        require_once __DIR__ . '/../../config/email_config.php';
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) use ($debugLog) {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - PHPMailer Debug: $str\n", FILE_APPEND);
            };
            $mail->Host = $emailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['username'];
            $mail->Password = $emailConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $emailConfig['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPAutoTLS = true;
            $mail->SMTPKeepAlive = true;
            $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->isHTML(true);
            $mail->Subject = $subject; // Set once outside

            // Robust Base URL detection
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            $apiPos = strpos($scriptPath, '/admin/api/');
            $projectPath = ($apiPos !== false) ? substr($scriptPath, 0, $apiPos) : '';
            $baseUrl = $protocol . '://' . $host . rtrim($projectPath, '/') . '/';

            $logStmt = $pdo->prepare("INSERT INTO marketing_campaigns (subject, audience, blocks, sent_count, status) VALUES (?, ?, ?, 0, 'sending')");
            $logStmt->execute([$subject, $audience, json_encode($blocks)]);
            $campaignId = $pdo->lastInsertId();

            // Send initial progress immediately
            echo json_encode(['type' => 'progress', 'current' => 0, 'total' => $totalCount, 'percent' => 0]) . "\n";
            flush();

            // Send initial progress update
            echo json_encode(['type' => 'progress', 'current' => 0, 'total' => $totalCount, 'percent' => 0, 'status' => 'Preparing template...']) . "\n";
            if (ob_get_level())
                ob_flush();
            flush();

            // Template pre-render
            $templateHtml = '';
            $imageCounter = 0;

            // Pre-detect if any block contains tags to ensure consistent tracking links
            $allText = json_encode($blocks);
            $hasTags = (strpos($allText, '{full_name}') !== false || strpos($allText, '{first_name}') !== false || strpos($allText, '{{email}}') !== false);
            foreach ($blocks as $block) {
                $type = $block['type'];
                $text = $block['text'] ?? '';
                $align = $block['align'] ?? 'center';
                $color = $block['color'] ?? '#000000';
                $bg = $block['bg'] ?? '#ffffff';

                if ($type === 'header') {
                    $templateHtml .= "<div style='background: {$bg}; padding: 20px; text-align: {$align};'><h2 style='color: {$color}; margin: 0; font-family: sans-serif;'>{$text}</h2></div>";
                } else if ($type === 'text') {
                    $size = $block['size'] ?? '16';
                    $weight = $block['weight'] ?? '400';
                    $templateHtml .= "<div style='padding: 20px 30px; text-align: {$align};'><p style='color: {$color}; font-size: {$size}px; font-weight: {$weight}; line-height: 1.6; font-family: sans-serif; margin: 0;'>" . nl2br($text) . "</p></div>";
                } else if ($type === 'image') {
                    $imgUrl = $block['url'] ?? '';
                    $src = (strpos($imgUrl, 'http') === 0) ? $imgUrl : ($baseUrl . str_replace('../', '', $imgUrl));
                    $caption = $block['caption'] ?? '';
                    $width = $block['width'] ?? '100';
                    $radius = $block['radius'] ?? '0';

                    // Image embedding for better visibility (Support relative paths AND local absolute URLs)
                    if (!empty($imgUrl)) {
                        $localPath = '';
                        $cleanPath = str_replace('../', '', $imgUrl);

                        // Case 1: Relative Path
                        if (strpos($imgUrl, 'http') !== 0) {
                            $localPathCandidates = [
                                __DIR__ . '/../../' . $cleanPath,
                                __DIR__ . '/../' . $cleanPath,
                                __DIR__ . '/../../admin/' . $cleanPath
                            ];
                            foreach ($localPathCandidates as $p) {
                                if (file_exists($p) && is_file($p)) {
                                    $localPath = $p;
                                    break;
                                }
                            }
                        }
                        // Case 2: Absolute Local URL (e.g., http://localhost/...)
                        else if (strpos($imgUrl, $host) !== false) {
                            // Extract path after host
                            $urlParts = parse_url($imgUrl);
                            $pathInUrl = $urlParts['path'] ?? '';
                            // Remove project root from path to get relative path
                            $projectRootInUrl = trim($projectPath, '/');
                            $relativePath = $pathInUrl;
                            if (!empty($projectRootInUrl)) {
                                $relativePath = str_replace($projectRootInUrl, '', $pathInUrl);
                            }
                            $relativePath = ltrim($relativePath, '/');

                            $localPathCandidates = [
                                __DIR__ . '/../../' . $relativePath,
                                __DIR__ . '/../' . $relativePath,
                                __DIR__ . '/../../admin/' . $relativePath
                            ];
                            foreach ($localPathCandidates as $p) {
                                if (file_exists($p) && is_file($p)) {
                                    $localPath = $p;
                                    break;
                                }
                            }
                        }

                        if (!empty($localPath)) {
                            $cid = 'camp_img_' . $imageCounter++;
                            $mail->addEmbeddedImage($localPath, $cid);
                            $src = "cid:{$cid}";
                        }
                    }

                    $imgHtml = "<img src='{$src}' style='width: 100%; border-radius: {$radius}px; display: block;' alt='Promo'>";

                    if (!empty($caption)) {
                        $capSize = $block['capSize'] ?? '14';
                        $capColor = $block['capColor'] ?? '#64748b';
                        $capWeight = $block['capWeight'] ?? '400';
                        $textHtml = "<div style='color: {$capColor}; font-size: {$capSize}px; font-weight: {$capWeight}; line-height: 1.5; padding: 10px; text-align: " . ($align === 'center' ? 'center' : 'left') . ";'>" . nl2br($caption) . "</div>";

                        if ($align === 'left') {
                            $templateHtml .= "<div style='padding: 20px 30px;'><table width='100%' cellpadding='0' cellspacing='0' role='presentation'><tr><td width='{$width}%' valign='middle'>{$imgHtml}</td><td valign='middle' style='padding-left: 20px;'>{$textHtml}</td></tr></table></div>";
                        } else if ($align === 'right') {
                            $templateHtml .= "<div style='padding: 20px 30px;'><table width='100%' cellpadding='0' cellspacing='0' role='presentation'><tr><td valign='middle' style='padding-right: 20px;'>{$textHtml}</td><td width='{$width}%' valign='middle'>{$imgHtml}</td></tr></table></div>";
                        } else {
                            $templateHtml .= "<div style='padding: 20px 30px; text-align: center;'><div style='width: {$width}%; margin: 0 auto;'>{$imgHtml}</div>{$textHtml}</div>";
                        }
                    } else {
                        $templateHtml .= "<div style='padding: 20px 30px; text-align: {$align};'><div style='width: {$width}%; display: inline-block;'>{$imgHtml}</div></div>";
                    }
                } else if ($type === 'button') {
                    $link = $block['link'] ?? '#';
                    $padding = $block['padding'] ?? '12';
                    // Wrap link in tracker - ALWAYS include email placeholder if possible
                    $trackedLink = "{$baseUrl}admin/api/track_click.php?cid={$campaignId}&url=" . urlencode($link) . "&e={{email}}";

                    $templateHtml .= "<div style='text-align: {$align}; padding: 20px 30px;'><a href='{$trackedLink}' style='display: inline-block; background: {$bg}; color: {$color}; padding: {$padding}px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;'>{$text}</a></div>";
                } else if ($type === 'divider') {
                    $templateHtml .= "<div style='padding: 0 30px;'><hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'></div>";
                } else if ($type === 'footer') {
                    $unsubscribeLink = "{$baseUrl}unsubscribe.php";
                    $websiteLink = "https://heydreamtravel.kesug.com/";

                    // Track website link
                    $trackedWebsite = "{$baseUrl}admin/api/track_click.php?cid={$campaignId}&url=" . urlencode($websiteLink) . "&e={{email}}";

                    $templateHtml .= "<div style='padding: 30px; text-align: {$align}; background: #f8fafc;'>
                    <p style='font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;'>" . nl2br($text) . "</p>
                    <div style='margin-top: 15px; font-size: 12px; font-family: sans-serif;'>
                        <a href='{$unsubscribeLink}' style='color: #003580; text-decoration: none;'>Unsubscribe</a> | 
                        <a href='{$trackedWebsite}' style='color: #003580; text-decoration: none;'>View Website</a>
                    </div>
                </div>";
                }
            }

            $sent_count = 0;
            $processed_count = 0;
            // Final check for tags in the rendered HTML
            if (!$hasTags) {
                $hasTags = (strpos($templateHtml, '{{email}}') !== false);
            }

            // Update status: Connecting
            echo json_encode(['type' => 'progress', 'current' => 0, 'total' => $totalCount, 'percent' => 0, 'status' => 'Connecting to SMTP server...']) . "\n";
            if (ob_get_level())
                ob_flush();
            flush();

            $mail->smtpConnect();

            // Log setup for debugging
            file_put_contents($debugLog, "--- CAMPAIGN START ---\nBaseURL: $baseUrl\nTotal: $totalCount\n", FILE_APPEND);

            foreach ($recipients as $index => $user) {
                if (connection_aborted())
                    break;
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($user['email'], $user['full_name']);

                    $encodedEmail = urlencode($user['email']);
                    $trackingPixel = "<div style='display:none; visibility:hidden; font-size:1px; line-height:1px; max-height:0px; max-width:0px; opacity:0; overflow:hidden;'><img src='{$baseUrl}admin/api/track_open.php?cid={$campaignId}&e={$encodedEmail}' width='1' height='1' border='0' alt=''></div>";
                    $trackingBg = "background-image: url(\"{$baseUrl}admin/api/track_open.php?cid={$campaignId}&e={$encodedEmail}&m=bg\");";

                    if ($hasTags) {
                        $firstName = explode(' ', $user['full_name'])[0];
                        $personalizedBody = str_replace(['{full_name}', '{first_name}', '{{email}}'], [$user['full_name'], $firstName, $encodedEmail], $templateHtml);
                        $mail->Body = "{$trackingPixel}<div style='background: #f1f5f9; padding: 20px; {$trackingBg}'><div style='width: 100%; max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-family: sans-serif;'><meta charset='UTF-8'>$personalizedBody</div></div>";
                    } else {
                        $mail->Body = "{$trackingPixel}<div style='background: #f1f5f9; padding: 20px; {$trackingBg}'><div style='width: 100%; max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-family: sans-serif;'><meta charset='UTF-8'>$templateHtml</div></div>";
                    }

                    $mail->send();
                    $sent_count++;
                } catch (\Throwable $e) {
                    // Individual send failure - log it
                    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - SEND FAILURE to {$user['email']}: " . $e->getMessage() . "\n", FILE_APPEND);
                }

                $processed_count++;
                $current_percent = round(($processed_count / $totalCount) * 100);

                // Report progress for every recipient to ensure movement is visible immediately
                echo json_encode([
                    'type' => 'progress',
                    'current' => $processed_count,
                    'sent' => $sent_count,
                    'total' => $totalCount,
                    'percent' => $current_percent,
                    'status' => "Sending to {$user['email']}..."
                ]) . "\n";
                if (ob_get_level())
                    ob_flush();
                flush();
            }

            $mail->smtpClose();
            $pdo->prepare("UPDATE marketing_campaigns SET sent_count = ?, body = ?, status = 'sent' WHERE id = ?")->execute([$sent_count, $templateHtml, $campaignId]);

            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - CAMPAIGN DONE - Sent: $sent_count of $totalCount\n", FILE_APPEND);
            echo json_encode(['type' => 'done', 'success' => true, 'sent_count' => $sent_count]) . "\n";
        } catch (\Throwable $e) {
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - CAMPAIGN CRITICAL ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['type' => 'error', 'message' => 'Critical Error: ' . $e->getMessage()]) . "\n";
        }
        exit;
        break;


    case 'send_test':
        $blocks = json_decode($_POST['blocks'] ?? '[]', true);
        $subject = "[TEST] " . ($_POST['subject'] ?? 'Email Preview');

        // Use provided email or fallback to admin's email
        require_once __DIR__ . '/../../config/email_config.php';
        $testEmail = $_POST['test_email'] ?? $emailConfig['username'];

        // AUTO-DETECT REAL NAME FROM DATABASE (using users table)
        $testName = "Valued Customer";
        try {
            $nameStmt = $pdo->prepare("SELECT full_name FROM users WHERE email = ? LIMIT 1");
            $nameStmt->execute([$testEmail]);
            $existingUser = $nameStmt->fetch();
            if ($existingUser && !empty($existingUser['full_name'])) {
                $testName = $existingUser['full_name'];
            }
        } catch (Exception $e) {
            // Silently fall back to default name
        }

        // Detect Base URL for absolute image paths
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $projectRoot = dirname(dirname(dirname($scriptPath)));
        if ($projectRoot === DIRECTORY_SEPARATOR || $projectRoot === '\\')
            $projectRoot = '';
        $baseUrl = $protocol . '://' . $host . rtrim($projectRoot, '/') . '/';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) use ($debugLog) {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - PHPMailer Test Debug: $str\n", FILE_APPEND);
            };
            $mail->Host = $emailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['username'];
            $mail->Password = $emailConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $emailConfig['port'];
            $mail->SMTPAutoTLS = true;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->addAddress($testEmail, $testName);
            $mail->isHTML(true);
            $mail->Subject = $subject;

            $blocksHtml = '';
            $imageCounter = 0;
            $replaceTags = function ($str) use ($testName) {
                $str = str_replace('{full_name}', $testName, $str);
                $str = str_replace('{first_name}', explode(' ', $testName)[0], $str);
                return $str;
            };

            foreach ($blocks as $block) {
                $type = $block['type'];
                $text = $replaceTags($block['text'] ?? '');
                $align = $block['align'] ?? 'center';
                $color = $block['color'] ?? '#000000';
                $bg = $block['bg'] ?? '#ffffff';

                if ($type === 'header') {
                    $blocksHtml .= "<div style='background: {$bg}; padding: 20px; text-align: {$align};'><h2 style='color: {$color}; margin: 0; font-family: sans-serif;'>{$text}</h2></div>";
                } else if ($type === 'text') {
                    $size = $block['size'] ?? '16';
                    $weight = $block['weight'] ?? '400';
                    $blocksHtml .= "<div style='padding: 20px 30px; text-align: {$align};'><p style='color: {$color}; font-size: {$size}px; font-weight: {$weight}; line-height: 1.6; font-family: sans-serif; margin: 0;'>" . nl2br($text) . "</p></div>";
                } else if ($type === 'image') {
                    $imgUrl = $block['url'];
                    $src = (strpos($imgUrl, 'http') === 0) ? $imgUrl : ($baseUrl . str_replace('../', '', $imgUrl));
                    $caption = $replaceTags($block['caption'] ?? '');
                    $width = $block['width'] ?? '100';
                    $radius = $block['radius'] ?? '0';

                    // Embedded image support for local uploads (Support relative paths AND local absolute URLs)
                    if (!empty($imgUrl)) {
                        $localPath = '';
                        $cleanPath = str_replace('../', '', $imgUrl);

                        // Case 1: Relative Path
                        if (strpos($imgUrl, 'http') !== 0) {
                            $localPathCandidates = [
                                __DIR__ . '/../../' . $cleanPath,
                                __DIR__ . '/../' . $cleanPath,
                                __DIR__ . '/../../admin/' . $cleanPath
                            ];
                            foreach ($localPathCandidates as $p) {
                                if (file_exists($p) && is_file($p)) {
                                    $localPath = $p;
                                    break;
                                }
                            }
                        }
                        // Case 2: Absolute Local URL (e.g., http://localhost/...)
                        else if (strpos($imgUrl, $host) !== false) {
                            // Extract path after host
                            $urlParts = parse_url($imgUrl);
                            $pathInUrl = $urlParts['path'] ?? '';
                            // Remove project root from path to get relative path
                            $projectRootInUrl = trim($projectRoot, '/');
                            $relativePath = $pathInUrl;
                            if (!empty($projectRootInUrl)) {
                                $relativePath = str_replace($projectRootInUrl, '', $pathInUrl);
                            }
                            $relativePath = ltrim($relativePath, '/');

                            $localPathCandidates = [
                                __DIR__ . '/../../' . $relativePath,
                                __DIR__ . '/../' . $relativePath,
                                __DIR__ . '/../../admin/' . $relativePath
                            ];
                            foreach ($localPathCandidates as $p) {
                                if (file_exists($p) && is_file($p)) {
                                    $localPath = $p;
                                    break;
                                }
                            }
                        }

                        if (!empty($localPath)) {
                            $cid = 'test_img_' . $imageCounter++;
                            $mail->addEmbeddedImage($localPath, $cid);
                            $src = "cid:{$cid}";
                        }
                    }

                    $imgHtml = "<img src='{$src}' style='width: 100%; border-radius: {$radius}px; display: block;' alt='Promo'>";

                    if (!empty($caption)) {
                        $capSize = $block['capSize'] ?? '14';
                        $capColor = $block['capColor'] ?? '#64748b';
                        $capWeight = $block['capWeight'] ?? '400';
                        $textHtml = "<div style='color: {$capColor}; font-size: {$capSize}px; font-weight: {$capWeight}; line-height: 1.5; padding: 10px; font-family: sans-serif; text-align: " . ($align === 'center' ? 'center' : 'left') . ";'>" . nl2br($caption) . "</div>";

                        if ($align === 'left') {
                            $blocksHtml .= "<div style='padding: 20px 30px;'><table width='100%' cellpadding='0' cellspacing='0' role='presentation'><tr><td width='{$width}%' valign='middle'>{$imgHtml}</td><td valign='middle' style='padding-left: 20px;'>{$textHtml}</td></tr></table></div>";
                        } else if ($align === 'right') {
                            $blocksHtml .= "<div style='padding: 20px 30px;'><table width='100%' cellpadding='0' cellspacing='0' role='presentation'><tr><td valign='middle' style='padding-right: 20px;'>{$textHtml}</td><td width='{$width}%' valign='middle'>{$imgHtml}</td></tr></table></div>";
                        } else {
                            $blocksHtml .= "<div style='padding: 20px 30px; text-align: center;'><div style='width: {$width}%; margin: 0 auto;'>{$imgHtml}</div>{$textHtml}</div>";
                        }
                    } else {
                        $blocksHtml .= "<div style='padding: 20px 30px; text-align: {$align};'><div style='width: {$width}%; display: inline-block;'>{$imgHtml}</div></div>";
                    }
                } else if ($type === 'button') {
                    $link = $block['link'] ?? '#';
                    $padding = $block['padding'] ?? '12';
                    $size = $block['size'] ?? '16';
                    $blocksHtml .= "<div style='text-align: {$align}; padding: 20px 30px;'><a href='{$link}' style='display: inline-block; background: {$bg}; color: {$color}; padding: {$padding}px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif; font-size: {$size}px;'>{$text}</a></div>";
                } else if ($type === 'divider') {
                    $blocksHtml .= "<div style='padding: 0 30px;'><hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'></div>";
                } else if ($type === 'footer') {
                    $blocksHtml .= "<div style='padding: 30px; text-align: {$align}; background: #f8fafc;'>
                        <p style='font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;'>" . nl2br($text) . "</p>
                        <div style='margin-top: 15px; font-size: 12px; font-family: sans-serif;'>
                            <a href='{$baseUrl}unsubscribe.php?email=" . urlencode($testEmail) . "' style='color: #003580; text-decoration: none;'>Unsubscribe</a> | 
                            <a href='https://heydreamtravel.kesug.com/' style='color: #003580; text-decoration: none;'>View Website</a>
                        </div>
                    </div>";
                }
            }

            $mail->Body = "<div style='background: #f1f5f9; padding: 20px;'><div style='width: 100%; max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-family: sans-serif;'><meta charset='UTF-8'>$blocksHtml</div></div>";
            $mail->send();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Test email sent to ' . $testEmail . ' as "' . $testName . '"']);
        } catch (\Throwable $e) {
            if (ob_get_length())
                ob_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')']);
        }
        break;

    case 'delete_template':
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM marketing_templates WHERE id = ?");
            $stmt->execute([$id]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Template deleted']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        break;

    case 'delete_inquiry':
        $id = $_POST['id'] ?? 0;
        try {
            // Delete inquiry (which is just a booking with Inquiry Only)
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ? AND payment_method = 'Inquiry Only'");
            $stmt->execute([$id]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Inquiry deleted']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        break;

    case 'get_inquiry_stats':
        try {
            // 1. Current Stats
            $stmt = $pdo->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN booking_status NOT IN ('contacted', 'completed', 'cancelled', 'confirmed') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
                FROM bookings WHERE payment_method = 'Inquiry Only'");
            $inqStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Trend Calculation (Last 7 days vs 7 days before that)
            $stmtTrend = $pdo->query("SELECT 
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as total_curr,
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) as total_prev,
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND booking_status NOT IN ('contacted', 'completed', 'cancelled', 'confirmed') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as pending_curr,
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND booking_status NOT IN ('contacted', 'completed', 'cancelled', 'confirmed') AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) as pending_prev,
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND booking_status = 'confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as confirmed_curr,
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND booking_status = 'confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) as confirmed_prev
            ");
            $trends = $stmtTrend->fetch(PDO::FETCH_ASSOC);

            function calcTrend($curr, $prev)
            {
                if ($prev == 0)
                    return $curr > 0 ? 100 : 0;
                return round((($curr - $prev) / $prev) * 100);
            }

            $totalTrend = calcTrend($trends['total_curr'], $trends['total_prev']);
            $pendingTrend = calcTrend($trends['pending_curr'], $trends['pending_prev']);
            $confirmedTrend = calcTrend($trends['confirmed_curr'], $trends['confirmed_prev']);

            // 3. Template Count
            $stmtTpl = $pdo->query("SELECT COUNT(*) as total FROM marketing_templates");
            $tplCount = $stmtTpl->fetchColumn();

            ob_clean();
            echo json_encode([
                'success' => true,
                'total' => (int) $inqStats['total'],
                'pending' => (int) $inqStats['pending'],
                'confirmed' => (int) $inqStats['confirmed'],
                'templates' => (int) $tplCount,
                'trends' => [
                    'total' => $totalTrend,
                    'pending' => $pendingTrend,
                    'confirmed' => $confirmedTrend
                ]
            ]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_source_analytics':
        try {
            // 1. Map emails to sources from inquiries only
            $stmt = $pdo->prepare("SELECT email, special_requests FROM bookings WHERE email IS NOT NULL AND email != '' AND payment_method = 'Inquiry Only'");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $emailToSource = [];
            $registeredEmails = [];
            $sources = ['Facebook', 'Instagram', 'Twitter', 'TikTok', 'Other'];
            $stats = [];
            foreach ($sources as $s) {
                $stats[$s] = ['inquiries' => 0, 'opens' => 0, 'clicks' => 0];
            }

            // 1. Map registered users
            $stmtReg = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ''");
            while ($regRow = $stmtReg->fetch()) {
                $registeredEmails[strtolower($regRow['email'])] = true;
            }

            // 2. Map emails to sources from bookings
            foreach ($rows as $row) {
                $reqText = strtolower($row['special_requests']);
                $source = 'Other';

                // Main Social Media matching
                if (strpos($reqText, 'facebook') !== false)
                    $source = 'Facebook';
                elseif (strpos($reqText, 'instagram') !== false)
                    $source = 'Instagram';
                elseif (strpos($reqText, 'twitter') !== false)
                    $source = 'Twitter';
                elseif (strpos($reqText, 'tiktok') !== false)
                    $source = 'TikTok';
                else
                    $source = 'Other'; // Gmail, Threads, Website, etc. now go here

                $email = strtolower($row['email']);
                if (!isset($emailToSource[$email])) {
                    $emailToSource[$email] = $source;
                    $stats[$source]['inquiries']++;
                }
            }

            // 3. Aggregate opens and clicks from tracking table
            $stmtTrack = $pdo->query("SELECT email, action FROM marketing_tracking");
            $tracks = $stmtTrack->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tracks as $t) {
                $email = strtolower($t['email']);
                $recorded = false;

                // Requirement: Also record as the specific social media source if they have an inquiry
                if (isset($emailToSource[$email])) {
                    $source = $emailToSource[$email];
                    if ($t['action'] === 'open')
                        $stats[$source]['opens']++;
                    elseif ($t['action'] === 'click')
                        $stats[$source]['clicks']++;
                    $recorded = true;
                }

                // If not from an inquiry source but is a registered user, count as Other engagement
                if (!$recorded && isset($registeredEmails[$email])) {
                    if ($t['action'] === 'open')
                        $stats['Other']['opens']++;
                    elseif ($t['action'] === 'click')
                        $stats['Other']['clicks']++;
                    $recorded = true;
                }

                if (!$recorded) {
                    if ($t['action'] === 'open')
                        $stats['Other']['opens']++;
                    elseif ($t['action'] === 'click')
                        $stats['Other']['clicks']++;
                }
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'request_block_approval':
        $blockType = $_POST['block_type'] ?? 'unknown';
        $adminRole = $_SESSION['admin_role'] ?? 'unknown_role';
        $adminName = $_SESSION['admin_username'] ?? 'Admin User';

        try {
            // Record in database
            $stmt = $pdo->prepare("INSERT INTO block_unlock_requests (admin_username, block_type, status) 
                                 VALUES (?, ?, 'pending') 
                                 ON DUPLICATE KEY UPDATE status = 'pending', created_at = CURRENT_TIMESTAMP");
            $stmt->execute([$adminName, $blockType]);

            $superAdminEmail = 'heydreamtravelandtours@gmail.com';
            require_once __DIR__ . '/../../config/email_config.php';
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $emailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['username'];
            $mail->Password = $emailConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $emailConfig['port'];
            $mail->SMTPAutoTLS = true;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

            $mail->setFrom($emailConfig['from_email'], 'HeyDream Campaign Builder');
            $mail->addAddress($superAdminEmail, 'Super Admin');

            $mail->isHTML(true);
            $mail->Subject = "Action Required: Edit Approval Request for " . ucfirst($blockType);

            $mailBody = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0;'>
                <h2 style='color: #0f172a; margin-top: 0;'>Edit Approval Request</h2>
                <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
                    User <strong>{$adminName}</strong> (Role: <em>{$adminRole}</em>) has requested permission to edit a restricted <strong>" . ucfirst($blockType) . "</strong> block in the Campaign Builder.
                </p>
                <div style='background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #cbd5e1; margin: 25px 0;'>
                    <p style='margin: 0; color: #1e293b;'><strong>Requested By:</strong> {$adminName}</p>
                    <p style='margin: 10px 0 0 0; color: #1e293b;'><strong>Block Type:</strong> " . ucfirst($blockType) . "</p>
                </div>
                <p style='color: #64748b; font-size: 14px;'>
                    Please log in to the admin panel to approve or deny this request.
                </p>
            </div>";
            $mail->Body = $mailBody;
            $mail->send();

            ob_clean();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_unlock_requests':
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Super Admin only']);
            break;
        }
        $stmt = $pdo->query("SELECT * FROM block_unlock_requests ORDER BY created_at DESC");
        ob_clean();
        echo json_encode(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'approve_unlock_request':
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Super Admin only']);
            break;
        }
        $id = $_POST['id'] ?? 0;
        $expiry_time = $_POST['expiry_time'] ?? ''; // Expecting YYYY-MM-DDTHH:MM format from datetime-local
        $status = $_POST['status'] ?? 'approved';

        try {
            if ($status === 'approved' && !empty($expiry_time)) {
                $expires = date('Y-m-d H:i:s', strtotime($expiry_time));
                $stmt = $pdo->prepare("UPDATE block_unlock_requests SET status = 'approved', expires_at = ? WHERE id = ?");
                $stmt->execute([$expires, $id]);

                // SEND CONFIRMATION EMAIL TO REQUESTER
                try {
                    // Fetch requester email and info
                    $userStmt = $pdo->prepare("SELECT u.email, u.full_name, r.block_type FROM admin_users u JOIN block_unlock_requests r ON u.username = r.admin_username WHERE r.id = ?");
                    $userStmt->execute([$id]);
                    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

                    if ($userInfo && !empty($userInfo['email'])) {
                        require_once __DIR__ . '/../../config/email_config.php';
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $emailConfig['host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $emailConfig['username'];
                        $mail->Password = $emailConfig['password'];
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = $emailConfig['port'];
                        $mail->SMTPAutoTLS = true;
                        $mail->CharSet = 'UTF-8';
                        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

                        $mail->setFrom($emailConfig['from_email'], 'HeyDream Travel');
                        $mail->addAddress($userInfo['email'], $userInfo['full_name']);
                        $mail->addAddress($superAdminEmail, 'Super Admin'); // Also notify Super Admin

                        $mail->isHTML(true);
                        $mail->Subject = "Access Granted: " . $userInfo['full_name'] . " can now edit " . ucfirst($userInfo['block_type']);

                        $mail->Body = "
                        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; background: #f0fdf4; padding: 30px; border-radius: 12px; border: 1px solid #bbf7d0;'>
                            <h2 style='color: #166534; margin-top: 0;'>Access Request Approved!</h2>
                            <p style='color: #1e293b; font-size: 16px; line-height: 1.6;'>
                                Hello <strong>{$userInfo['full_name']}</strong>, your request to edit the <strong>" . ucfirst($userInfo['block_type']) . "</strong> has been approved by the Super Admin.
                            </p>
                            <div style='background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #86efac; margin: 25px 0;'>
                                <p style='margin: 0; color: #166534; font-weight: 700;'>Status: UNLOCKED</p>
                                <p style='margin: 10px 0 0 0; color: #1e293b;'><strong>Expires At:</strong> " . date('M j, Y, g:i a', strtotime($expires)) . "</p>
                            </div>
                            <p style='color: #475569; font-size: 14px;'>
                                You can now log in to the Campaign Builder and modify this section. Please ensure all changes are completed before the expiration time.
                            </p>
                        </div>";
                        $mail->send();
                    }
                } catch (Exception $mailEx) {
                    // Log mail error but don't fail the approval
                }

            } else {
                $stmt = $pdo->prepare("UPDATE block_unlock_requests SET status = 'locked', expires_at = NULL WHERE id = ?");
                $stmt->execute([$id]);
            }
            ob_clean();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_my_active_unlocks':
        $user = $_SESSION['admin_username'] ?? '';
        $stmt = $pdo->prepare("SELECT block_type, expires_at FROM block_unlock_requests WHERE admin_username = ? AND status = 'approved' AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$user]);
        $unlocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ob_clean();
        echo json_encode(['success' => true, 'unlocks' => $unlocks]);
        break;

    case 'delete_unlock_request':
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Super Admin only']);
            break;
        }
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM block_unlock_requests WHERE id = ?");
            $stmt->execute([$id]);
            ob_clean();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    case 'send_direct_message':
        $subject = $_POST['subject'] ?? '';
        $body = $_POST['body'] ?? '';
        $blocks = json_decode($_POST['blocks'] ?? '[]', true);
        $rawAudience = $_POST['audience'] ?? '';
        // Normalize audience: only allow valid types, default to 'all'
        $validAudiences = ['website', 'inquiries', 'partners', 'all'];
        $audience = in_array($rawAudience, $validAudiences) ? $rawAudience : 'all';
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Audience Filtering: raw='$rawAudience', normalized='$audience'\n", FILE_APPEND);
        $partnersStr = $_POST['partners'] ?? '';

        if (empty($subject) || (empty($body) && empty($blocks))) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Subject and Message Content are required.']);
            break;
        }

        try {
            // Helper function to validate emails and skip malformed ones
            $isValidEmail = function($email) {
                // Skip obviously malformed emails (e.g., .php extension, .edu.php domain, etc.)
                if (stripos($email, '.php') !== false) return false;
                if (stripos($email, '.exe') !== false) return false;
                if (stripos($email, '.js') !== false) return false;
                // Standard email validation
                return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
            };

            // Determine recipients
            $recipients = [];
            if ($audience === 'website') {
                // ONLY website users
                $stmt = $pdo->query("SELECT DISTINCT email, full_name FROM users WHERE email IS NOT NULL AND email != '' ORDER BY email");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Website audience: " . count($recipients) . " recipients\n", FILE_APPEND);
            } elseif ($audience === 'inquiries') {
                // ONLY inquiry leads (bookings)
                $stmt = $pdo->query("SELECT DISTINCT email, full_name FROM bookings WHERE email IS NOT NULL AND email != '' AND payment_method = 'Inquiry Only' ORDER BY email");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Inquiries audience: " . count($recipients) . " recipients\n", FILE_APPEND);
            } elseif ($audience === 'partners') {
                // ONLY business partners
                $emails = explode(',', $partnersStr);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if ($isValidEmail($email)) {
                        $recipients[] = [
                            'email' => $email,
                            'full_name' => 'Business Partner'
                        ];
                    } else {
                        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Skipping invalid partner email: {$email}\n", FILE_APPEND);
                    }
                }
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Partners audience: " . count($recipients) . " valid recipients\n", FILE_APPEND);
            } else { // 'all' is the fallback
                // ALL users (deduplicated)
                $stmt = $pdo->query("SELECT DISTINCT email, full_name FROM users WHERE email IS NOT NULL AND email != '' UNION DISTINCT SELECT email, full_name FROM bookings WHERE email IS NOT NULL AND email != '' ORDER BY email");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - All audience: " . count($recipients) . " recipients\n", FILE_APPEND);
            }

            // Filter out malformed emails from all recipients (except partners which are already filtered above)
            if ($audience !== 'partners') {
                $originalCount = count($recipients);
                $recipients = array_filter($recipients, function($r) use ($isValidEmail) {
                    return $isValidEmail($r['email']);
                });
                // Re-index array after filtering
                $recipients = array_values($recipients);
                $skipped = $originalCount - count($recipients);
                if ($skipped > 0) {
                    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Skipped {$skipped} malformed emails\n", FILE_APPEND);
                }
            }
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Final recipient count: " . count($recipients) . " valid recipients\n", FILE_APPEND);

            if (empty($recipients)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'No valid recipients found for the selected audience.']);
                break;
            }

            // Detect Base URL for absolute image paths
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = $_SERVER['SCRIPT_NAME'];
            $projectRoot = dirname(dirname(dirname($scriptPath)));
            if ($projectRoot === DIRECTORY_SEPARATOR || $projectRoot === '\\')
                $projectRoot = '';
            $baseUrl = $protocol . '://' . $host . rtrim($projectRoot, '/') . '/';

            require_once __DIR__ . '/../../config/email_config.php';
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $emailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['username'];
            $mail->Password = $emailConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $emailConfig['port'];
            $mail->CharSet = 'UTF-8';
            $mail->SMTPAutoTLS = true;
            $mail->SMTPKeepAlive = true; // keep the connection alive for multiple recipients
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Ensure envelope/sender use the authenticated account
            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            // Set Sender explicitly (envelope-from) to the SMTP username to avoid MAIL FROM rejections
            $mail->Sender = $emailConfig['username'];
            // Useful reply-to
            if (!empty($emailConfig['username'])) {
                $mail->addReplyTo($emailConfig['username'], $emailConfig['from_name']);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;

            $successCount = 0;
            $failCount = 0;

            // Try to establish SMTP connection once for the batch (more efficient and reduces re-auth failures)
            try {
                $mail->smtpConnect();
            } catch (\Throwable $connEx) {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - SMTP CONNECT ERROR: " . $connEx->getMessage() . "\n", FILE_APPEND);
            }

            foreach ($recipients as $recipient) {
                try {
                    // Clear any prior recipients, attachments or custom headers to avoid leakage
                    $mail->clearAllRecipients();
                    $mail->clearAttachments();
                    $mail->clearCustomHeaders();

                    // Basic sanity: skip obviously-bad addresses (e.g. strings containing .php or other script bits)
                    $candidateEmail = trim($recipient['email']);
                    if (stripos($candidateEmail, '.php') !== false) {
                        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - SKIPPING MALFORMED EMAIL: {$candidateEmail}\n", FILE_APPEND);
                        $failCount++;
                        continue;
                    }

                    if (!filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
                        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - INVALID EMAIL SKIPPED: {$candidateEmail}\n", FILE_APPEND);
                        $failCount++;
                        continue;
                    }

                    $mail->addAddress($candidateEmail, $recipient['full_name']);
                    
                    $recipientName = $recipient['full_name'];
                    $replaceTags = function ($str) use ($recipientName) {
                        $str = str_replace('{full_name}', $recipientName, $str);
                        $str = str_replace('{first_name}', explode(' ', $recipientName)[0], $str);
                        return $str;
                    };

                    if (!empty($blocks)) {
                        // Render visually designed blocks
                        $blocksHtml = '';
                        $imageCounter = 0;
                        foreach ($blocks as $block) {
                            $type = $block['type'];
                            $text = $replaceTags($block['text'] ?? '');
                            $align = $block['align'] ?? 'center';
                            $color = $block['color'] ?? '#000000';
                            $bg = $block['bg'] ?? '#ffffff';

                            if ($type === 'header') {
                                $blocksHtml .= "<div style='background: {$bg}; padding: 25px 20px; text-align: {$align};'><h2 style='color: {$color}; margin: 0; font-family: sans-serif; font-size: 24px; font-weight:700;'>{$text}</h2></div>";
                            } else if ($type === 'text') {
                                $size = $block['size'] ?? '16';
                                $weight = $block['weight'] ?? '400';
                                $blocksHtml .= "<div style='padding: 20px 30px; text-align: {$align};'><p style='color: {$color}; font-size: {$size}px; font-weight: {$weight}; line-height: 1.6; font-family: sans-serif; margin: 0;'>" . nl2br($text) . "</p></div>";
                            } else if ($type === 'image') {
                                $imgUrl = $block['url'] ?? '';
                                $src = (strpos($imgUrl, 'http') === 0) ? $imgUrl : ($baseUrl . str_replace('../', '', $imgUrl));
                                $caption = $replaceTags($block['caption'] ?? '');
                                $width = $block['width'] ?? '100';
                                $radius = $block['radius'] ?? '8';

                                if (!empty($imgUrl)) {
                                    $localPath = '';
                                    $cleanPath = str_replace('../', '', $imgUrl);
                                    if (strpos($imgUrl, 'http') !== 0) {
                                        $localPathCandidates = [
                                            __DIR__ . '/../../' . $cleanPath,
                                            __DIR__ . '/../' . $cleanPath,
                                            __DIR__ . '/../../admin/' . $cleanPath
                                        ];
                                        foreach ($localPathCandidates as $p) {
                                            if (file_exists($p) && is_file($p)) {
                                                $localPath = $p;
                                                break;
                                            }
                                        }
                                    } else if (strpos($imgUrl, $host) !== false) {
                                        $urlParts = parse_url($imgUrl);
                                        $pathInUrl = $urlParts['path'] ?? '';
                                        $projectRootInUrl = trim($projectRoot, '/');
                                        $relativePath = $pathInUrl;
                                        if (!empty($projectRootInUrl)) {
                                            $relativePath = str_replace($projectRootInUrl, '', $pathInUrl);
                                        }
                                        $relativePath = ltrim($relativePath, '/');
                                        $localPathCandidates = [
                                            __DIR__ . '/../../' . $relativePath,
                                            __DIR__ . '/../' . $relativePath,
                                            __DIR__ . '/../../admin/' . $relativePath
                                        ];
                                        foreach ($localPathCandidates as $p) {
                                            if (file_exists($p) && is_file($p)) {
                                                $localPath = $p;
                                                break;
                                            }
                                        }
                                    }

                                    if (!empty($localPath)) {
                                        $cid = 'msg_img_' . $imageCounter++;
                                        $mail->addEmbeddedImage($localPath, $cid);
                                        $src = "cid:{$cid}";
                                    }
                                }

                                $imgHtml = "<img src='{$src}' style='width: 100%; border-radius: {$radius}px; display: block;' alt='Promo Image'>";

                                if (!empty($caption)) {
                                    $capSize = $block['capSize'] ?? '14';
                                    $capColor = $block['capColor'] ?? '#64748b';
                                    $capWeight = $block['capWeight'] ?? '400';
                                    $textHtml = "<div style='color: {$capColor}; font-size: {$capSize}px; font-weight: {$capWeight}; line-height: 1.5; padding: 10px; font-family: sans-serif; text-align: " . ($align === 'center' ? 'center' : 'left') . ";'>" . nl2br($caption) . "</div>";

                                    if ($align === 'left') {
                                        $blocksHtml .= "<div style='padding: 20px 30px;'><table width='100%' cellpadding='0' cellspacing='0' role='presentation'><tr><td width='{$width}%' valign='middle'>{$imgHtml}</td><td valign='middle' style='padding-left: 20px;'>{$textHtml}</td></tr></table></div>";
                                    } else if ($align === 'right') {
                                        $blocksHtml .= "<div style='padding: 20px 30px;'><table width='100%' cellpadding='0' cellspacing='0' role='presentation'><tr><td valign='middle' style='padding-right: 20px;'>{$textHtml}</td><td width='{$width}%' valign='middle'>{$imgHtml}</td></tr></table></div>";
                                    } else {
                                        $blocksHtml .= "<div style='padding: 20px 30px; text-align: center;'><div style='width: {$width}%; margin: 0 auto;'>{$imgHtml}</div>{$textHtml}</div>";
                                    }
                                } else {
                                    $blocksHtml .= "<div style='padding: 20px 30px; text-align: {$align};'><div style='width: {$width}%; display: inline-block;'>{$imgHtml}</div></div>";
                                }
                            } else if ($type === 'button') {
                                $link = $block['link'] ?? '#';
                                $padding = $block['padding'] ?? '12';
                                $size = $block['size'] ?? '16';
                                $blocksHtml .= "<div style='text-align: {$align}; padding: 20px 30px;'><a href='{$link}' style='display: inline-block; background: {$bg}; color: {$color}; padding: {$padding}px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif; font-size: {$size}px; font-weight: bold;'>{$text}</a></div>";
                            } else if ($type === 'divider') {
                                $blocksHtml .= "<div style='padding: 0 30px;'><hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'></div>";
                            } else if ($type === 'footer') {
                                $blocksHtml .= "<div style='padding: 30px; text-align: {$align}; background: #f8fafc; border-top: 1px solid #e2e8f0;'>
                                    <p style='font-size: 11px; color: #94a3b8; font-family: sans-serif; margin: 0; line-height: 1.5;'>" . nl2br($text) . "</p>
                                    <div style='margin-top: 15px; font-size: 11px; font-family: sans-serif;'>
                                        <a href='{$baseUrl}unsubscribe.php?email=" . urlencode($recipient['email']) . "' style='color: #003580; text-decoration: none;'>Unsubscribe</a> | 
                                        <a href='https://heydreamtravel.kesug.com/' style='color: #003580; text-decoration: none;'>View Website</a>
                                    </div>
                                </div>";
                            }
                        }
                        $mail->Body = "<div style='background: #f1f5f9; padding: 20px;'><div style='width: 100%; max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-family: sans-serif;'><meta charset='UTF-8'>$blocksHtml</div></div>";
                    } else {
                        // Fallback to simple styled message
                        $formattedContent = nl2br(htmlspecialchars($body));
                        $mail->Body = "
                        <div style='background: #f1f5f9; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
                            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e2e8f0;'>
                                <div style='background: #003580; padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 0.5px;'>HeyDream Travel & Tours</h1>
                                    <p style='color: #fef08a; margin: 5px 0 0 0; font-size: 14px; font-weight: 500;'>Your Journey, Our Dream</p>
                                </div>
                                <div style='padding: 45px 35px; color: #334155; font-size: 16px; line-height: 1.6;'>
                                    " . $formattedContent . "
                                </div>
                                <div style='background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #f1f5f9;'>
                                    <p style='color: #64748b; font-size: 12px; margin: 0;'>&copy; " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                                    <p style='color: #94a3b8; font-size: 12px; margin: 8px 0 0 0;'>You received this message because you are a registered user or partner of HeyDream.</p>
                                    <div style='margin-top: 15px;'>
                                        <a href='https://heydreamtravel.kesug.com/' style='color: #003580; text-decoration: none; font-size: 12px; font-weight: 600;'>Visit Website</a>
                                    </div>
                                </div>
                            </div>
                        </div>";
                    }

                    $mail->send();
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    $errMsg = $e->getMessage();
                    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - DIRECT SEND FAILURE to {$recipient['email']}: " . $errMsg . "\n", FILE_APPEND);
                }
            }

            // Close SMTP connection after batch
            try { $mail->smtpClose(); } catch (\Throwable $closeEx) { /* ignore */ }

            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => "Message successfully sent to {$successCount} recipients." . ($failCount > 0 ? " Failed to send to {$failCount} recipients." : "")
            ]);
        } catch (\Throwable $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_campaign':
        try {
            $id = $_POST['id'] ?? null;
            if ($id) {
                // 1. Fetch campaign details before deletion to check status and subject
                $checkStmt = $pdo->prepare("SELECT subject, status, scheduled_at FROM marketing_campaigns WHERE id = ?");
                $checkStmt->execute([$id]);
                $camp = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($camp) {
                    $wasScheduled = ($camp['status'] === 'scheduled');
                    $subject = $camp['subject'];
                    $schedAt = $camp['scheduled_at'];

                    // 2. Perform Deletion
                    $stmt = $pdo->prepare("DELETE FROM marketing_campaigns WHERE id = ?");
                    $stmt->execute([$id]);

                    // 3. If it was a scheduled campaign, notify admin of cancellation
                    if ($wasScheduled) {
                        require_once __DIR__ . '/../../config/email_config.php';
                        try {
                            $adminMail = new PHPMailer(true);
                            $adminMail->isSMTP();
                            $adminMail->Host = $emailConfig['host'];
                            $adminMail->SMTPAuth = true;
                            $adminMail->Username = $emailConfig['username'];
                            $adminMail->Password = $emailConfig['password'];
                            $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $adminMail->Port = $emailConfig['port'];
                            $adminMail->CharSet = 'UTF-8';
                            $adminMail->setFrom($emailConfig['from_email'], 'HeyDream Automation');
                            $adminMail->addAddress('heydreamtravelandtours@gmail.com', 'HeyDream Admin');
                            $adminMail->isHTML(true);
                            $adminMail->Subject = "Campaign CANCELLED: " . $subject;
                            $adminMail->Body = "
                                <div style='font-family: sans-serif; padding: 20px; color: #334155;'>
                                    <h2 style='color: #ef4444;'>Campaign Cancelled</h2>
                                    <p>A scheduled marketing campaign has been manually cancelled and removed from the queue.</p>
                                    <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                                    <p><strong>Campaign:</strong> {$subject}</p>
                                    <p><strong>Original Schedule:</strong> " . date('M j, Y - g:i A', strtotime($schedAt)) . "</p>
                                    <p><strong>Status:</strong> Retracted / Deleted</p>
                                    <br>
                                    <p style='font-size: 0.8rem; color: #64748b;'>This is an automated security notification from your HeyDream Marketing Hub.</p>
                                </div>
                            ";
                            $adminMail->send();
                        } catch (Exception $e) { /* Notify fail silent */
                        }
                    }
                }

                ob_clean();
                echo json_encode(['success' => true]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Missing ID']);
            }
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;


    case 'get_campaign_details':
        try {
            $id = $_GET['id'] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM marketing_campaigns WHERE id = ?");
                $stmt->execute([$id]);
                $camp = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($camp) {
                    // Fetch all tracking logs for this campaign (increased limit for performance safety but effectively unlimited for most cases)
                    $stmtLogs = $pdo->prepare("SELECT email, action, created_at FROM marketing_tracking WHERE campaign_id = ? ORDER BY created_at DESC LIMIT 500");
                    $stmtLogs->execute([$id]);
                    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode(['success' => true, 'data' => $camp, 'logs' => $logs]);
                } else {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Campaign not found']);
                }
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Missing ID']);
            }
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_dashboard_data':
        try {
            // 1. Stats
            $stmt = $pdo->query("SELECT 
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND booking_status NOT IN ('completed', 'cancelled')) as pending,
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND booking_status NOT IN ('contacted', 'completed', 'cancelled', 'confirmed')) as pending,
                (SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only' AND booking_status = 'confirmed') as confirmed,
                (SELECT COUNT(*) FROM marketing_templates) as templates
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Recent Campaigns
            $stmt = $pdo->query("SELECT id, subject, sent_count, open_count, status, created_at FROM marketing_campaigns ORDER BY created_at DESC LIMIT 10");
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Recent Inquiries (For Dashboard Table)
            $stmt = $pdo->query("SELECT id, full_name, special_requests, package_name as destination, booking_status, created_at FROM bookings WHERE payment_method = 'Inquiry Only' ORDER BY created_at DESC LIMIT 10");
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'campaigns' => $campaigns,
                'inquiries' => $inquiries
            ]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_all_campaigns':
        try {
            $stmt = $pdo->query("SELECT id, subject, sent_count, open_count, status, created_at FROM marketing_campaigns ORDER BY created_at DESC");
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'data' => $campaigns]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_scheduled_count':
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'scheduled' AND (scheduled_at > NOW() OR scheduled_at IS NULL)");
            $count = $stmt->fetchColumn();
            ob_clean();
            echo json_encode(['success' => true, 'count' => (int) $count]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_upcoming_schedules':
        try {
            $stmt = $pdo->query("SELECT id, subject, scheduled_at FROM marketing_campaigns WHERE status = 'scheduled' ORDER BY scheduled_at ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

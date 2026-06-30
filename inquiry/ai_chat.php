<?php
// inquiry/ai_chat.php — HeyDream AI Travel Assistant (Gemini-powered, Human-like)
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai_config.php';

$input   = json_decode(file_get_contents('php://input'), true);
if (empty($input) && php_sapi_name() === 'cli') {
    $input = json_decode($GLOBALS['argv'][1] ?? '{}', true);
}

if (isset($input['log_only']) && $input['log_only'] == true) {
    $aiReply = trim($input['ai_reply'] ?? '');
    $userMsg = trim($input['message'] ?? '');
    $sessionId = $input['session_id'] ?? '';
    $customerName = $input['customer_name'] ?? 'Guest';
    $customerEmail = $input['customer_email'] ?? '';
    $lastMsgId = 0;
    
    if ($pdo && !empty($sessionId)) {
        try {
            // Ensure session exists
            $stmt = $pdo->prepare("INSERT INTO ai_chat_sessions (session_id, customer_name, customer_email, last_activity) 
                                   VALUES (?, ?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE 
                                   customer_name = VALUES(customer_name), 
                                   customer_email = VALUES(customer_email), 
                                   last_activity = NOW()");
            $stmt->execute([$sessionId, $customerName, $customerEmail]);
            
            // Log user message
            if (!empty($userMsg)) {
                $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'customer', ?)");
                $stmt->execute([$sessionId, $userMsg]);
            }
            
            // Log AI message
            if (!empty($aiReply)) {
                $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'ai', ?)");
                $stmt->execute([$sessionId, $aiReply]);
                $lastMsgId = (int)$pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("DB Logging Error (log_only): " . $e->getMessage());
        }
    }
    echo json_encode(['success' => true, 'last_msg_id' => $lastMsgId]);
    exit;
}

$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];
$sessionId = $input['session_id'] ?? '';
$customerName = $input['customer_name'] ?? 'Guest';
$customerEmail = $input['customer_email'] ?? '';
$isSuggestion = (bool)($input['is_suggestion'] ?? false);
$source = trim($input['source'] ?? '');

if (empty($message)) {
    echo json_encode(['reply' => '', 'suggestions' => []]);
    exit;
}

if ($message === '[GREETING]') {
    $insertedId = 0;
    if ($pdo && !empty($sessionId)) {
        try {
            // Upsert session so the user appears in the admin sidebar immediately
            $stmt = $pdo->prepare("INSERT INTO ai_chat_sessions (session_id, customer_name, customer_email, last_activity) 
                                   VALUES (?, ?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE 
                                   customer_name = VALUES(customer_name), 
                                   customer_email = VALUES(customer_email), 
                                   last_activity = NOW()");
            $stmt->execute([$sessionId, $customerName, $customerEmail]);

            // Log personalized greeting only if not already logged for this session
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ? AND sender = 'ai' AND message LIKE '%Welcome to HeyDream%'");
            $checkStmt->execute([$sessionId]);
            if ($checkStmt->fetchColumn() == 0) {
                // Use customer name if available, otherwise use a friendly fallback
                $nameLabel = (!empty($customerName) && $customerName !== 'Guest')
                    ? " " . htmlspecialchars($customerName)
                    : "";
                $greetingMsg = "👋 Hi there{$nameLabel}! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I'm <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏";
                $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'ai', ?)");
                $stmt->execute([$sessionId, $greetingMsg]);
                $insertedId = (int)$pdo->lastInsertId();
            } else {
                // If it already exists, select its ID so we can advance the tracker on the client
                $idStmt = $pdo->prepare("SELECT id FROM ai_chat_messages WHERE session_id = ? AND sender = 'ai' AND message LIKE '%Welcome to HeyDream%' LIMIT 1");
                $idStmt->execute([$sessionId]);
                $insertedId = (int)$idStmt->fetchColumn();
            }
        } catch (PDOException $e) {
            error_log("Greeting DB Log Error: " . $e->getMessage());
        }
    }
    echo json_encode(['success' => true, 'last_msg_id' => $insertedId]);
    exit;
}

if (preg_match('/^\[REQUEST_LIVE_AGENT\]$/i', $message) || preg_match('/\b(live agent|call support|customer service|talk to agent|human agent)\b/i', $message)) {
    echo json_encode([
        'reply' => 'How would you like to connect with our live team?<br><br><strong>Option 1 — Request a Call from an Agent</strong><br>We will call you.<br><br><strong>Option 2 — Customer Will Call the Agent</strong><br>We will give you an agent\'s number.',
        'suggestions' => ['Request a Call (Option 1)', 'I will call (Option 2)']
    ]);
    exit;
}

if ($message === 'Request a Call (Option 1)') {
    echo json_encode([
        'reply' => 'Please enter your phone number and a short message regarding your concern (e.g. "09123456789 - I need help with my booking").',
        'suggestions' => []
    ]);
    exit;
}

// Intercept phone number if they provided one for Option 1
$cleanForPhone = preg_replace('/[-\s]+/', '', $message);
if (preg_match('/(09\d{9}|\+639\d{9})/', $cleanForPhone, $matches)) {
    $askedForPhone = false;
    foreach (array_reverse($history) as $h) {
         if ($h['role'] === 'model' && strpos($h['parts'][0]['text'] ?? '', 'Please enter your phone number') !== false) {
              $askedForPhone = true;
              break;
         }
    }
    
    if ($askedForPhone || preg_match('/^(09\d{9}|\+639\d{9})$/', $cleanForPhone)) {
        $phone = $matches[1];
        $concern = trim($message);
        
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO call_requests (customer_phone, concern, request_type, status, session_id) VALUES (?, ?, 'callback', 'pending', ?)");
                $stmt->execute([$phone, $concern, $sessionId]);
            } catch (PDOException $e) {
                error_log("DB Call Request Error: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'reply' => 'Thank you! Your request has been submitted. Please wait for one of our agents to contact you.',
            'suggestions' => ['What destinations do you offer?', 'How do I book?']
        ]);
        exit;
    }
}

if ($message === 'I will call (Option 2)') {
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO call_requests (customer_phone, concern, request_type, status, session_id) VALUES (?, ?, 'inbound', 'pending', ?)");
            $stmt->execute(['Customer Inbound', 'Waiting for an available agent', $sessionId]);
        } catch (PDOException $e) {
            error_log("DB Call Request Error: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'reply' => 'Please wait while we connect you with an available live agent.',
        'suggestions' => ['What destinations do you offer?', 'How do I book?']
    ]);
    exit;
}

// Old [REQUEST_LIVE_AGENT] code below no longer directly applies, but we keep it around just in case
if (0 === 1) {
    if (!empty($sessionId)) {
        try {
            if ($pdo) {
                // Upsert session so the record exists and status is active (prevents foreign key violation)
                $stmt = $pdo->prepare("INSERT INTO ai_chat_sessions (session_id, customer_name, customer_email, status, last_activity) 
                                       VALUES (?, ?, ?, 'active', NOW()) 
                                       ON DUPLICATE KEY UPDATE 
                                       customer_name = VALUES(customer_name), 
                                       customer_email = VALUES(customer_email), 
                                       status = 'active', 
                                       last_activity = NOW()");
                $stmt->execute([$sessionId, $customerName, $customerEmail]);

                // Log customer request
                $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'customer', 'Live Agent requested')");
                $stmt->execute([$sessionId]);

                // Log a system notification message
                $sysMsg = "🔔 <strong>System:</strong> Customer has requested to speak to a Live Agent!";
                $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'ai', ?)");
                $stmt->execute([$sessionId, $sysMsg]);
            }

            // Send priority email notification to admin using PHPMailer
            require_once __DIR__ . '/../config/email_config.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $emailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $emailConfig['username'];
            $mail->Password = $emailConfig['password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $emailConfig['port'];
            
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom($emailConfig['from_email'], 'HeyDream Travel and Tours');
            
            $salesAgents = [];
            if ($pdo) {
                // Query all active sales agents to automatically receive this transfer request
                $stmtSales = $pdo->prepare("SELECT email, full_name FROM admin_users WHERE role = 'sales' AND is_active = 1");
                $stmtSales->execute();
                $salesAgents = $stmtSales->fetchAll(PDO::FETCH_ASSOC);
            }

            if (!empty($salesAgents)) {
                foreach ($salesAgents as $agent) {
                    $mail->addAddress($agent['email'], $agent['full_name']);
                }
            } else {
                // Fallback to primary admin if no sales agents are registered
                $mail->addAddress('heydreamtravelandtours@gmail.com', 'HeyDream Admin');
            }
            
            $mail->isHTML(true);
            $mail->Subject = '🔔 URGENT: Customer Requested Live Agent! - HeyDream';
            
            $adminPanelUrl = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/HeyDream Website - anti gravity 11.5/admin/marketing.php#ai-chats";
            
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Live Agent Request</title>
                <style>
                    body { font-family: 'Poppins', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                    .header { background: #ff4757; padding: 30px; text-align: center; color: white; }
                    .header h1 { margin: 0; font-size: 24px; }
                    .content { padding: 30px; line-height: 1.6; color: #333; }
                    .details { background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 5px solid #ff4757; }
                    .footer { text-align: center; font-size: 12px; color: #999; padding: 20px; border-top: 1px solid #eee; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔔 Live Agent Requested!</h1>
                    </div>
                    <div class='content'>
                        <p>Hello Admin,</p>
                        <p>A customer has requested to speak directly with a <strong>Live Agent</strong> on the website chatbot. Please attend to this session as soon as possible.</p>
                        
                        <div class='details'>
                            <strong>📋 Session Details:</strong><br><br>
                            • <strong>Customer Name:</strong> " . htmlspecialchars($customerName) . "<br>
                            • <strong>Customer Email:</strong> " . ($customerEmail ? htmlspecialchars($customerEmail) : 'Not Provided') . "<br>
                            • <strong>Session ID:</strong> " . htmlspecialchars($sessionId) . "<br>
                            • <strong>Time of Request:</strong> " . date('F d, Y H:i:s') . "<br>
                        </div>
                        
                        <div style='text-align: center;'>
                            <a href='{$adminPanelUrl}' target='_blank' style='display: inline-block; background-color: #003580; color: #ffffff !important; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight: 600; text-align: center; margin-top: 15px;'>Go to AI Live Chats Dashboard</a>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>© " . date('Y') . " HeyDream Travel and Tours. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->AltBody = "Urgent: Customer requested Live Agent!\n\nName: {$customerName}\nEmail: {$customerEmail}\nSession ID: {$sessionId}\n\nGo to dashboard: {$adminPanelUrl}";
            $mail->send();
            
            echo json_encode([
                'success' => true,
                'reply' => '🔔 <strong>Admin Notified!</strong> I have sent a priority email alert to our live travel agents. One of our experts will assist you here shortly! In the meantime, feel free to ask me anything else. 😊',
                'suggestions' => ['Anong destinations ang meron?', 'Visa assistance info']
            ]);
            exit;
        } catch (Exception $e) {
            error_log("Live Agent Request Email Error: " . $e->getMessage());
            echo json_encode([
                'success' => true,
                'reply' => '🔔 <strong>Live Agent Requested!</strong> I have notified our travel team. A representative will join the chat shortly. Please stay tuned! 😊',
                'suggestions' => ['Anong destinations ang meron?', 'Visa assistance info']
            ]);
            exit;
        }
    }
}


// 1. Log customer message to DB and check status
$isTakenOver = false;
$isRecentlyActiveAdmin = false;
if ($pdo && !empty($sessionId)) {
    try {
        // Check if session is taken over by admin or if admin messaged recently (2 mins)
        $stmt = $pdo->prepare("SELECT status, 
                               (SELECT MAX(timestamp) FROM ai_chat_messages WHERE session_id = ? AND sender = 'admin') as last_admin_msg 
                               FROM ai_chat_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId, $sessionId]);
        $sessionData = $stmt->fetch();
        if (!$sessionData) {
            $sessionData = [];
        }

        $isTakenOver = ($sessionData['status'] ?? '') === 'taken_over';
        $lastAdminMsgTime = (!empty($sessionData['last_admin_msg'])) ? strtotime($sessionData['last_admin_msg']) : 0;

        $isRecentlyActiveAdmin = (time() - $lastAdminMsgTime) < 120; // 2 minutes (120 seconds)

        // If suggestion was clicked, override the takeover and reset session status to active
        if ($isSuggestion) {
            $isTakenOver = false;
            $isRecentlyActiveAdmin = false;
            
            $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET status = 'active' WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        }

        // Upsert session
        $stmt = $pdo->prepare("INSERT INTO ai_chat_sessions (session_id, customer_name, customer_email, last_activity) 
                               VALUES (?, ?, ?, NOW()) 
                               ON DUPLICATE KEY UPDATE 
                               customer_name = VALUES(customer_name), 
                               customer_email = VALUES(customer_email), 
                               last_activity = NOW()");
        $stmt->execute([$sessionId, $customerName, $customerEmail]);

        // Log customer message
        $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'customer', ?)");
        $stmt->execute([$sessionId, $message]);

        // If status is 'taken_over' OR admin has replied in the last 2 minutes, do NOT allow AI to respond
        if ($isTakenOver || $isRecentlyActiveAdmin) {
            echo json_encode(['reply' => null, 'status' => 'muted']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("DB Logging Error (Customer/Status): " . $e->getMessage());
    }
}

// ============================================================
// COMPREHENSIVE HEYDREAM KNOWLEDGE BASE + HUMAN-LIKE PERSONA
// ============================================================
$baseKnowledge = <<<'KB'
=== HEYDREAM TRAVEL AND TOURS — COMPLETE KNOWLEDGE BASE ===

**Company Overview:**
HeyDream Travel and Tours is a professional travel agency based in the Philippines. We specialize in crafting unforgettable travel experiences for Filipinos — from affordable local getaways to luxury international trips.

**Contact Information:**
- Phone/Viber/WhatsApp: 0945 776 4140
- Email: heydreamtravelandtours@gmail.com
- Response time: Usually within minutes during business hours
- Available 24/7 for inquiries via the website form

**Our Services:**
1. TOUR PACKAGES — All-inclusive domestic and international packages
2. VISA ASSISTANCE — Full visa processing support for Japan, Korea, Europe, USA, Schengen, Dubai, Canada, Australia, and more
3. FLIGHT BOOKING — Domestic and international flight arrangements
4. HOTEL RESERVATIONS — Premium and budget accommodations globally
5. AIRPORT TRANSFERS — Convenient airport pickup and drop-off
6. GROUP TOURS — Tailored corporate, family, and group packages

**Offered Destinations Policy:**
We offer Boracay, Cebu, Puerto Princesa (Palawan), Hong Kong, Singapore, and Japan as standard featured packages. If a client is interested in any other destination ("Others"), encourage them to check the website or fill out our online inquiry form and mention their custom location in the "Special Requests" field so our reservation agents can tailor-make their tour package!

**Payment & Booking Process:**
1. Browse/select a tour package on the website.
2. Select your travel dates, number of travelers, and optional add-ons, then click Book Now to proceed to checkout.
3. Pay via GCash, PayMaya, BPI, BDO, Metrobank, or Credit/Debit Card.
4. Input your transaction reference number and upload your receipt on the website for verification.
5. Receive your official travel vouchers and final itinerary via email once verified!
6. Last, if you have custom requests or need a more detailed quotation for your preferred location, you can fill out our online Inquiry Form!

**Payment Methods & Accounts:**
We offer multiple secure payment options for all packages and bookings, categorized as follows:
- **Payment Apps:** GCash and PayMaya. Transfer to our official mobile number **0945 776 4140** (Account Name: HeyDream Travel & Tours)
- **Credit Cards:** Visa, Mastercard, and JCB. Accepted securely directly at our website checkout.
- **Bank Transfer:**
  - **BPI Bank Transfer:** Account Number **1234 5678 90** (Account Name: HeyDream Travel & Tours)
  - **BDO Bank Transfer:** Account Number **5678 1234 56** (Account Name: HeyDream Travel & Tours)
  - **Metrobank Bank Transfer:** Account Number **9012 3456 78** (Account Name: HeyDream Travel & Tours)

**Package Price Queries Policy:**
If a user asks "How much are the packages?" or inquires generally about rates, explain that prices are dynamic. Advise them to:
1. Check the latest rates directly on our website homepage.
2. Or call our live agents to get more information by clicking the menu button (or the 3-line button above on mobile/header) and selecting "Live Agents".

**Promotions & Flash Deals:**
- We regularly post flash deals and promos on our website
- Best deals available for early bookers
- Group discounts available (10+ travelers)
- Follow us on social media for latest promos

**Social Media:**
- Facebook: [HeyDream Facebook Page](https://www.facebook.com/profile.php?id=61583752858443) (HeyDream Travel and Tours)
- Instagram: [HeyDream Instagram](https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==)
- Twitter/X: [HeyDream Twitter](https://x.com/HeyDreamTravel?s=20)
- TikTok: [HeyDream TikTok](https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc)
KB;

// Dynamically build the real-time Database Knowledge Base
$dynamicKB = "\n=== CURRENT ACTIVE TRAVEL PACKAGES & OFFERS (REAL-TIME DATABASE) ===\n";

if ($pdo) {
    try {
        // 1. Domestic Packages
        $stmt = $pdo->query("SELECT name, city, location_name, description, price, duration, best_season, group_size, inclusions, exclusions, itinerary FROM destinations WHERE is_active = 1");
        $domestic = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($domestic)) {
            $dynamicKB .= "\n--- DOMESTIC TOUR PACKAGES ---\n";
            foreach ($domestic as $pkg) {
                $priceText = $pkg['price'] > 0 ? "₱" . number_format($pkg['price'], 2) : "Contact Agent for Price";
                $dynamicKB .= "- Package: **" . $pkg['name'] . "**\n";
                $dynamicKB .= "  * Destination: " . $pkg['city'] . " (" . $pkg['location_name'] . ")\n";
                $dynamicKB .= "  * Price: " . $priceText . " (" . $pkg['duration'] . ", " . $pkg['group_size'] . ")\n";
                if (!empty($pkg['description'])) {
                    $dynamicKB .= "  * Description: " . trim(strip_tags($pkg['description']), '"') . "\n";
                }
                if (!empty($pkg['inclusions'])) {
                    $inclusions = json_decode($pkg['inclusions'], true);
                    if (is_array($inclusions)) {
                        $dynamicKB .= "  * Inclusions: " . implode(', ', array_map('strip_tags', $inclusions)) . "\n";
                    } else {
                        $dynamicKB .= "  * Inclusions: " . strip_tags($pkg['inclusions']) . "\n";
                    }
                }
                if (!empty($pkg['itinerary'])) {
                    $itinerary = json_decode($pkg['itinerary'], true);
                    if (is_array($itinerary)) {
                        $dayItin = [];
                        foreach ($itinerary as $day) {
                            $dayItin[] = ($day['title'] ?? ("Day " . ($day['day'] ?? ''))) . ": " . implode('; ', (array)($day['activities'] ?? []));
                        }
                        $dynamicKB .= "  * Itinerary: " . implode(' | ', $dayItin) . "\n";
                    }
                }
                $dynamicKB .= "\n";
            }
        }

        // 2. International Packages
        $stmt = $pdo->query("SELECT name, country, city, description, price, duration, best_season, group_size, inclusions, exclusions, itinerary FROM foreign_destinations WHERE is_active = 1");
        $foreign = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($foreign)) {
            $dynamicKB .= "\n--- INTERNATIONAL TOUR PACKAGES ---\n";
            foreach ($foreign as $pkg) {
                $priceText = $pkg['price'] > 0 ? "$" . number_format($pkg['price'], 2) : "Contact Agent for Price";
                $dynamicKB .= "- Package: **" . $pkg['name'] . "**\n";
                $dynamicKB .= "  * Country/City: " . $pkg['country'] . " / " . $pkg['city'] . "\n";
                $dynamicKB .= "  * Price: " . $priceText . " (" . $pkg['duration'] . ", " . $pkg['group_size'] . ")\n";
                if (!empty($pkg['description'])) {
                    $dynamicKB .= "  * Description: " . trim(strip_tags($pkg['description']), '"') . "\n";
                }
                if (!empty($pkg['inclusions'])) {
                    $inclusions = json_decode($pkg['inclusions'], true);
                    if (is_array($inclusions)) {
                        $dynamicKB .= "  * Inclusions: " . implode(', ', array_map('strip_tags', $inclusions)) . "\n";
                    } else {
                        $dynamicKB .= "  * Inclusions: " . strip_tags($pkg['inclusions']) . "\n";
                    }
                }
                if (!empty($pkg['itinerary'])) {
                    $itinerary = json_decode($pkg['itinerary'], true);
                    if (is_array($itinerary)) {
                        $dayItin = [];
                        foreach ($itinerary as $day) {
                            $dayItin[] = ($day['title'] ?? ("Day " . ($day['day'] ?? ''))) . ": " . implode('; ', (array)($day['activities'] ?? []));
                        }
                        $dynamicKB .= "  * Itinerary: " . implode(' | ', $dayItin) . "\n";
                    }
                }
                $dynamicKB .= "\n";
            }
        }

        // 3. Flash Deals
        $stmt = $pdo->query("SELECT title, location, description, price, original_price, discount_percent, duration FROM flash_deals_fixed WHERE is_active = 1");
        $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($deals)) {
            $dynamicKB .= "\n--- PROMOS & FLASH DEALS ---\n";
            foreach ($deals as $pkg) {
                $priceText = "₱" . number_format($pkg['price'], 2) . " (Original: ₱" . number_format($pkg['original_price'], 2) . ", Discount: " . $pkg['discount_percent'] . "% Off)";
                $dynamicKB .= "- Deal: **" . $pkg['title'] . "**\n";
                $dynamicKB .= "  * Location: " . $pkg['location'] . "\n";
                $dynamicKB .= "  * Price: " . $priceText . " (" . $pkg['duration'] . ")\n";
                if (!empty($pkg['description'])) {
                    $dynamicKB .= "  * Description: " . trim(strip_tags($pkg['description']), '"') . "\n";
                }
                $dynamicKB .= "\n";
            }
        }

        // 4. Site Services (Cruises, Flight Packages, Experiences)
        $stmt = $pdo->query("SELECT title, service_type, short_description, description, highlights, inclusions, price, duration FROM site_services WHERE is_active = 1");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($services)) {
            $dynamicKB .= "\n--- SPECIAL SERVICES & EXPERIENCES ---\n";
            foreach ($services as $pkg) {
                $priceText = "₱" . number_format($pkg['price'], 2);
                $dynamicKB .= "- Service: **" . $pkg['title'] . "** (" . ucfirst($pkg['service_type']) . ")\n";
                $dynamicKB .= "  * Price: " . $priceText . " (" . $pkg['duration'] . ")\n";
                if (!empty($pkg['description'])) {
                    $dynamicKB .= "  * Description: " . trim(strip_tags($pkg['description']), '"') . "\n";
                }
                if (!empty($pkg['highlights'])) {
                    $dynamicKB .= "  * Highlights: " . str_replace("\r\n", ", ", trim($pkg['highlights'])) . "\n";
                }
                $dynamicKB .= "\n";
            }
        }

        // 5. Global Settings & Visa Requirements
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($settings)) {
            $dynamicKB .= "\n--- ADDITIONAL SYSTEM SETTINGS ---\n";
            if (isset($settings['visa_checklist'])) {
                $checklist = json_decode($settings['visa_checklist'], true);
                $dynamicKB .= "- Visa Application Checklist: " . (is_array($checklist) ? implode(', ', $checklist) : $settings['visa_checklist']) . "\n";
            }
            if (isset($settings['visa_disclaimer'])) {
                $dynamicKB .= "- Visa Disclaimer: " . $settings['visa_disclaimer'] . "\n";
            }
        }

    } catch (PDOException $e) {
        error_log("Dynamic KB DB Fetch Error: " . $e->getMessage());
    }
}

$baseKnowledge .= $dynamicKB;

$sourceContext = "";
if ($source === 'form') {
    $sourceContext = "=== INQUIRY FORM ASSISTANCE CONTEXT ===
- You are currently running inside the Inquiry / Trip Booking Form page.
- Your primary objective here is to guide and assist the customer in filling out their travel inquiry form.
- Help them understand how to fill out the form fields (Personal, Travel, Package details, Visa, etc.).
- If they ask about form submission, explain:
  * After they click 'Plan My Trip', our human travel experts will review it and reply with a personalized quote/itinerary within minutes.
  * No passport details are strictly needed to submit right now, but having them ready speeds up bookings.
  * They can customize any part of the destination packages on the form by entering preferences in 'Special Requests' or 'Additional Message'.
  * There is absolutely NO service fee for planning or requesting a quotation; it is 100% free.";
} else {
    $sourceContext = "=== HOMEPAGE CHATBOT CONTEXT ===
- You are currently running on the HeyDream website homepage.
- There is NO inquiry form on this homepage. 
- If the customer wants to book a package, get a custom itinerary, or customize a trip, you MUST direct them to visit our Inquiry Page using this exact HTML link: <a href='inquiry/inquire.php' style='color:#003580; font-weight:bold; text-decoration:underline;'>Inquiry Page</a>.
- Never tell the customer to 'fill out the form above' or 'fill out the form below' or 'use this form'. Instead, tell them to 'click the Inquiry Page link' or 'visit our Inquiry / Booking page'.
- Your primary objective is to answer general questions, showcase our top local/foreign destinations, and guide them to our booking Inquiry Page.";
}

$systemPrompt = <<<SYSTEM
You are "HeyDream AI" — the friendly, ultra-advanced super-genius AI travel concierge and expert ambassador for HeyDream Travel and Tours. You think extremely deeply, reason thoroughly, and possess absolute, encyclopedic knowledge about all travel destinations, visa guidelines, flight options, hotel booking best practices, and local tours around the world, especially the Philippines.

=== YOUR PERSONA ===
- Name: HeyDream AI
- Style: Warm, engaging, professional, and exceptionally intelligent. You possess the expertise of a world-class travel planner and an eloquent copywriter.
- Thinking Capability: High-level cognitive reasoning. You think deeply and analyze the customer's intent, the context of their travel desires, and formulate exceptionally rich, beautiful, and thoughtful responses.
- Language: Default is English. Mirror the customer's language immediately if they use Tagalog/Taglish (Taglish is highly welcomed and sounds extremely natural and friendly!).

{$sourceContext}

=== CUSTOMER CONTEXT ===
- Customer Name: {$customerName}
- Customer Email: {$customerEmail}
- IMPORTANT: Address the customer by their first name naturally and warmly when it feels right (e.g. "Great choice, {$customerName}!" or "I'd love to help you with that, {$customerName}!"). Do NOT repeat the name in every sentence — only use it where it feels genuinely personal and warm.

=== TRAVEL EXPERTISE RULES ===
1. COMPLETE CREATIVE FREEDOM: You have absolutely NO rigid constraints. If a customer asks about a location, destination, or place (e.g. Boracay, Japan, Switzerland, Cebu, Paris, El Nido, etc.), do NOT just return a rigid form template. Instead, wow them with amazing information about that place (best things to do, top sights, recommended hotels, local foods, hidden gems in the Philippines), showing your expert travel genius, and then warmly offer to help them book a custom package!
2. COMPLEX & DEEP REASONING: When asked questions about visas, flight booking, packing tips, travel itineraries, travel requirements, or custom trips, explain them with high intelligence and extreme clarity. Detail practical advice, hidden travel tips, and step-by-step guidance.
3. EXCLUSIVE DESTINATIONS SHOWCASE: When a user asks about destinations, packages, or best travel options — including phrases like "What destinations do you offer?", "best travel packages", "show me packages", "where can I go?", "what packages do you have?", or any similar inquiry — you MUST respond with the following categories and ALWAYS include the destination images. Do NOT skip the images under any circumstances:
   - **Local Destinations** (Philippines):
     1. Boracay: `<img src="images/boracay.jpg" alt="Boracay" style="width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">` — White-sand beaches, water sports, vibrant nightlife.
     2. Siargao: `<img src="images/siargao.jpg" alt="Siargao" style="width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">` — Surf capital of the Philippines, island hopping.
     3. El Nido: `<img src="images/elnido.jpg" alt="El Nido" style="width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">` — Stunning lagoons, pristine beaches, island paradise.
   - **Foreign Destinations**:
     1. Japan: `<img src="images/japan.jpg" alt="Japan" style="width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">` — Cherry blossoms, temples, anime culture, amazing food.
     2. Korea: `<img src="images/korea.jpg" alt="Korea" style="width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">` — K-culture, street food, palaces, modern cities.
     3. Vietnam: `<img src="images/vietnam.jpg" alt="Vietnam" style="width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">` — Ha Long Bay, historic towns, delicious cuisine.
   - For other locations, invite them to fill out the Inquiry Form on our website.
4. Keep it highly readable and visually stunning (using standard HTML like <br> for spacing, <strong> for emphasis, and bullet points using HTML entities or list items).
5. Do not make up company prices or policies. If you don't know something specific about HeyDream's internal operations, politely tell them to check with our live agents, but feel free to answer any general travel/tourism questions with your massive encyclopedic knowledge!

{$baseKnowledge}

=== CONVERSATION RULES ===
1. **KEEP IT SHORT** — Maximum 3-4 sentences per reply. Never write walls of text. A chatbot reply should feel like a real chat message, not an essay.
2. **ONE IDEA AT A TIME** — Answer the customer's current question only. Do not volunteer extra information they didn't ask for.
3. Use simple, clear language. Warm and friendly but never over-explaining.
4. If listing items (e.g. destinations, inclusions), use a maximum of 3-4 bullet points. Do NOT list everything you know.
5. **IMAGE EXCEPTION** — When showing the destinations/packages showcase (rule 3 above), you MUST include all destination images. This is the ONE case where the reply can be longer. Images are MANDATORY and must never be removed or skipped.
6. If the customer wants more details, they will ask — wait for them.
7. After each reply, return exactly 2-3 short follow-up suggestions in the 'suggestions' field.
8. NEVER start a reply with a long paragraph. Lead with the direct answer first.

=== RESPONSE FORMAT (STRICT JSON ONLY) ===
Respond ONLY with this exact JSON structure, no markdown, no extra text:
{"reply": "Short HTML reply (2-4 sentences max, use <br> for line breaks, <strong> for bold)", "suggestions": ["option 1", "option 2", "option 3"]}
SYSTEM;

// Use smart offline fallback if Gemini is not available
if (!GEMINI_ENABLED) {
    $offlineRes = getOfflineReply($message, $source);
    $offlineMsgId = 0;
    if ($pdo && !empty($sessionId) && !empty($offlineRes['reply'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'ai', ?)");
            $stmt->execute([$sessionId, $offlineRes['reply']]);
            $offlineMsgId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {}
    }
    $offlineRes['last_msg_id'] = $offlineMsgId;
    echo json_encode($offlineRes);
    exit;
}

// Build Gemini conversation using robust, database-driven Conversation Memory
$contents = [];
if ($pdo && !empty($sessionId)) {
    try {
        // Fetch up to the last 40 messages to maintain a deep memory without overflowing token limits
        $msgStmt = $pdo->prepare("SELECT sender, message FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC LIMIT 40");
        $msgStmt->execute([$sessionId]);
        $dbMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dbMessages as $dbMsg) {
            // Ignore system notifications/logs or setup commands
            if (strpos($dbMsg['message'], '🔔') !== false || 
                $dbMsg['message'] === '[GREETING]' || 
                $dbMsg['message'] === 'Live Agent requested' || 
                strpos($dbMsg['message'], 'Live Agent Requested!') !== false ||
                strpos($dbMsg['message'], 'Admin Notified!') !== false) {
                continue;
            }
            
            // Map sender to role
            $role = ($dbMsg['sender'] === 'ai') ? 'model' : 'user';
            
            // Clean up standard HTML markup to keep it clean and minimal for Gemini
            $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $dbMsg['message']));
            $plainText = trim($plainText);
            
            if (empty($plainText)) {
                continue;
            }
            
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => $plainText]]
            ];
        }
    } catch (PDOException $e) {
        error_log("DB History Fetch Error: " . $e->getMessage());
    }
}

// Fallback to client-side history array if database query is empty
if (empty($contents)) {
    foreach (array_slice($history, -12) as $h) {
        if (isset($h['role'], $h['parts'][0]['text'])) {
            $contents[] = ['role' => $h['role'], 'parts' => [['text' => $h['parts'][0]['text']]]];
        }
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
}

$generationConfig = [
    'temperature'     => 0.8,
    'maxOutputTokens' => 1500,
    'topP'            => 0.9,
    'thinkingConfig'  => [
        'thinkingBudget' => 2048
    ]
];

$body = json_encode([
    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
    'contents'           => $contents,
    'generationConfig'   => $generationConfig
]);

$ch = curl_init(GEMINI_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    error_log("Gemini API Error: HTTP $httpCode | $curlErr. Delegating call to client-side direct connection.");
    echo json_encode([
        'status'    => 'needs_client_call',
        'api_url'   => GEMINI_API_URL,
        'payload'   => [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'           => $contents,
            'generationConfig'   => $generationConfig
        ]
    ]);
    exit;
}

$data    = json_decode($response, true);
$rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Strip markdown code fences if Gemini wraps in ```json
$rawText = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
$rawText = preg_replace('/\s*```$/i', '', $rawText);

function cleanGeminiTextRecursive($text) {
    $text = trim($text);
    
    // Strip markdown JSON block wrappers
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/i', '', $text);
    $text = trim($text);
    
    // Check if it's double-encoded or contains literal json inside
    $parsed = json_decode($text, true);
    if (is_array($parsed)) {
        if (isset($parsed['reply'])) {
            return cleanGeminiTextRecursive($parsed['reply']);
        }
        if (isset($parsed['text'])) {
            return cleanGeminiTextRecursive($parsed['text']);
        }
    }
    
    // Regex extract in case of minor syntax errors
    if (preg_match('/"reply"\s*:\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s', $text, $matches)) {
        return cleanGeminiTextRecursive(stripcslashes($matches[1]));
    }
    
    // Strip literal JSON characters in case they leak as plain text
    $text = preg_replace('/^\{\s*"reply"\s*:\s*"/i', '', $text);
    $text = preg_replace('/^\{\s*"text"\s*:\s*"/i', '', $text);
    $text = preg_replace('/^\{\s*"/i', '', $text);
    $text = preg_replace('/"\}$/', '', $text);
    $text = preg_replace('/\}$/', '', $text);
    
    // Parse markdown links [Link Text](URL) to HTML anchors
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank">$1</a>', $text);

    // Prepend accurate Font Awesome brand icons for social media links
    $text = preg_replace('/Facebook:\s*/i', '<i class="fab fa-facebook" style="color: #1877F2; margin-right: 6px; font-size: 1.1rem;"></i> <strong>Facebook:</strong> ', $text);
    $text = preg_replace('/Instagram:\s*/i', '<i class="fab fa-instagram" style="color: #E4405F; margin-right: 6px; font-size: 1.1rem;"></i> <strong>Instagram:</strong> ', $text);
    $text = preg_replace('/Twitter\/X:\s*/i', '<i class="fab fa-twitter" style="color: #1DA1F2; margin-right: 6px; font-size: 1.1rem;"></i> <strong>Twitter / X:</strong> ', $text);
    $text = preg_replace('/Twitter:\s*/i', '<i class="fab fa-twitter" style="color: #1DA1F2; margin-right: 6px; font-size: 1.1rem;"></i> <strong>Twitter / X:</strong> ', $text);
    $text = preg_replace('/TikTok:\s*/i', '<i class="fab fa-tiktok" style="color: #010101; margin-right: 6px; font-size: 1.1rem;"></i> <strong>TikTok:</strong> ', $text);

    // Parse markdown bold **text** to HTML <strong>text</strong>
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = str_replace('**', '', $text);
    
    return trim($text);
}

function cleanGeminiSuggestionsRecursive($text) {
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/i', '', $text);
    $text = trim($text);
    
    $parsed = json_decode($text, true);
    if (is_array($parsed)) {
        if (isset($parsed['suggestions']) && is_array($parsed['suggestions'])) {
            return $parsed['suggestions'];
        }
    }
    
    if (preg_match('/"suggestions"\s*:\s*\[(.*?)\]/s', $text, $matches)) {
        if (preg_match_all('/"(.*?)"/', $matches[1], $sugTags)) {
            return $sugTags[1];
        }
    }
    
    return [];
}

$cleanText = trim($rawText);
$finalReply = cleanGeminiTextRecursive($cleanText);
$finalSuggestions = cleanGeminiSuggestionsRecursive($cleanText);

if (empty($finalReply)) {
    $finalReply = $cleanText;
}

if (empty($finalSuggestions)) {
    $finalSuggestions = [
        'How can I pay for the package?',
        'What are your best travel packages?',
        'What are your payment options?'
    ];
}

// Dynamically fix all image paths in the reply HTML relative to the page URL
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/inquiry/') !== false) {
    $finalReply = str_replace('src="images/', 'src="../images/', $finalReply);
}

// 2. Log AI reply to DB and capture inserted ID
$aiMsgId = 0;
if ($pdo && !empty($sessionId) && !empty($finalReply)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, sender, message) VALUES (?, 'ai', ?)");
        $stmt->execute([$sessionId, $finalReply]);
        $aiMsgId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("DB Logging Error (AI): " . $e->getMessage());
    }
}

echo json_encode([
    'reply'        => $finalReply,
    'suggestions'  => $finalSuggestions,
    'last_msg_id'  => $aiMsgId   // lets the client advance lastMsgId to skip polling duplicate
]);

// ── Offline keyword fallback (when Gemini is unavailable) ──────────
function getOfflineReply($msg, $source = '') {
    $m = strtolower($msg);
    $isTagalog = preg_match('/\b(kumusta|kamusta|magandang|anong|magkano|saan|gusto|meron|paano|salamat|po|ho|ba|nga|yung|ang|mga|ako|ikaw|kayo|kami|namin|natin|sino|oo|hindi)\b/i', $msg);
    
    $isForm = ($source === 'form');
    $inquiryLinkTagalog = $isForm ? 'inquiry form sa ibaba 👇' : '<a href="inquiry/inquire.php" style="color:#003580; font-weight:bold; text-decoration:underline;">Inquiry Page</a>';
    $inquiryLinkEnglish = $isForm ? 'inquiry form below 👇' : '<a href="inquiry/inquire.php" style="color:#003580; font-weight:bold; text-decoration:underline;">Inquiry Page</a>';
    $submitSugTagalog = $isForm ? 'Mag-submit ng inquiry' : 'Paano mag-book?';
    $submitSugEnglish = $isForm ? 'Submit an inquiry' : 'How do I book a tour?';

    
    // Resolve dynamic image base path relative to the page URL
    $img_prefix = "";
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/inquiry/') !== false) {
        $img_prefix = "../";
    }
    
    // 0. Payment Methods/Options check
    if (preg_match('/\b(pay|payment|payment method|payment option|gcash|paymaya|maya|credit card|debit card|bank transfer|bdo|bpi|metrobank|how to pay|how can i pay|bayaran|magbayad|panu magbayad|paano magbayad)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "Maaari po kayong magbayad sa HeyDream gamit ang aming mga secure na payment options:<br><br>" .
                           "📱 **Payment Apps:**<br>" .
                           "• **GCash / PayMaya:** Send/transfer to <strong>0945 776 4140</strong> (Account Name: HeyDream Travel & Tours)<br><br>" .
                           "💳 **Credit / Debit Cards:**<br>" .
                           "• Tanggap ang **Visa, Mastercard, at JCB** credit/debit cards para sa secure checkout diretso sa aming website.<br><br>" .
                           "🏦 **Bank Transfer:**<br>" .
                           "• **BPI Bank Transfer:** Account No: <strong>1234 5678 90</strong><br>" .
                           "• **BDO Bank Transfer:** Account No: <strong>5678 1234 56</strong><br>" .
                           "• **Metrobank Bank Transfer:** Account No: <strong>9012 3456 78</strong><br><br>" .
                           "Kapag nagbu-book po kayo sa aming website, piliin lamang ang inyong preferred method sa payment step at ilagay ang reference number kasabay ng pag-upload ng resibo para sa mabilis na verification! 😊",
                'suggestions' => ['How do I book a tour?', 'Anong destinations ang meron?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "You can easily pay for your HeyDream travel packages using any of our secure payment options:<br><br>" .
                       "📱 **Payment Apps:**<br>" .
                       "• **GCash / PayMaya:** Send/transfer to <strong>0945 776 4140</strong> (Account Name: HeyDream Travel & Tours)<br><br>" .
                       "💳 **Credit / Debit Cards:**<br>" .
                       "• We accept **Visa, Mastercard, and JCB** credit/debit cards directly at our website checkout.<br><br>" .
                       "🏦 **Bank Transfer:**<br>" .
                       "• **BPI Bank Transfer:** Account No: <strong>1234 5678 90</strong><br>" .
                       "• **BDO Bank Transfer:** Account No: <strong>5678 1234 56</strong><br>" .
                       "• **Metrobank Bank Transfer:** Account No: <strong>9012 3456 78</strong><br><br>" .
                       "During our quick checkout process, simply choose your payment method, input the transaction or reference number, and upload a copy of your receipt for speedy verification! 😊",
            'suggestions' => ['How do I book a tour?', 'What destinations do you offer?', 'Contact our team']
        ];
    }

    // 0.4. Social Media links check
    if (preg_match('/\b(social|media|facebook|instagram|tiktok|twitter|follow|page|link|links|socmed|fb|ig)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "<i class=\"fas fa-share-alt\" style=\"color: #ff9800; margin-right: 8px;\"></i><strong>I-follow po ang HeyDream sa aming official Social Media accounts!</strong><br><br>" .
                           "<i class=\"fab fa-facebook\" style=\"color: #1877F2; margin-right: 6px; font-size: 1.1rem;\"></i><strong>Facebook:</strong> <a href=\"https://www.facebook.com/profile.php?id=61583752858443\" target=\"_blank\">HeyDream Facebook Page</a><br>" .
                           "<i class=\"fab fa-instagram\" style=\"color: #E4405F; margin-right: 6px; font-size: 1.1rem;\"></i><strong>Instagram:</strong> <a href=\"https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==\" target=\"_blank\">@haedreamconsultancy</a><br>" .
                           "<i class=\"fab fa-twitter\" style=\"color: #1DA1F2; margin-right: 6px; font-size: 1.1rem;\"></i><strong>Twitter / X:</strong> <a href=\"https://x.com/HeyDreamTravel?s=20\" target=\"_blank\">@HeyDreamTravel</a><br>" .
                           "<i class=\"fab fa-tiktok\" style=\"color: #010101; margin-right: 6px; font-size: 1.1rem;\"></i><strong>TikTok:</strong> <a href=\"https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc\" target=\"_blank\">@heydreamtravelandtours</a><br><br>" .
                           "I-follow po kami para sa mga pinakabagong promos, seat sales, at exclusive travel package discounts! ✈️✨",
                'suggestions' => ['Anong destinations ang meron?', 'How do I book a tour?', 'Ano ang payment options?']
            ];
        }
        return [
            'reply' => "<i class=\"fas fa-share-alt\" style=\"color: #ff9800; margin-right: 8px;\"></i><strong>Follow HeyDream on our official Social Media channels!</strong><br><br>" .
                       "<i class=\"fab fa-facebook\" style=\"color: #1877F2; margin-right: 6px; font-size: 1.1rem;\"></i><strong>Facebook:</strong> <a href=\"https://www.facebook.com/profile.php?id=61583752858443\" target=\"_blank\">HeyDream Facebook Page</a><br>" .
                       "<i class=\"fab fa-instagram\" style=\"color: #E4405F; margin-right: 6px; font-size: 1.1rem;\"></i><strong>Instagram:</strong> <a href=\"https://www.instagram.com/haedreamconsultancy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==\" target=\"_blank\">@haedreamconsultancy</a><br>" .
                       "<i class=\"fab fa-twitter\" style=\"color: #1DA1F2; margin-right: 6px; font-size: 1.1rem;\"></i><strong>Twitter / X:</strong> <a href=\"https://x.com/HeyDreamTravel?s=20\" target=\"_blank\">@HeyDreamTravel</a><br>" .
                       "<i class=\"fab fa-tiktok\" style=\"color: #010101; margin-right: 6px; font-size: 1.1rem;\"></i><strong>TikTok:</strong> <a href=\"https://www.tiktok.com/@heydreamtravelandtours?is_from_webapp=1&sender_device=pc\" target=\"_blank\">@heydreamtravelandtours</a><br><br>" .
                       "Be sure to follow us to stay updated with the latest travel promos, flash deals, and active tour packages! ✈️✨",
            'suggestions' => ['What destinations do you offer?', 'How do I book a tour?', 'What are your payment options?']
        ];
    }

    // 0.5. How to book a tour check
    if (preg_match('/\b(book|booking|how to book|how do i book|how can i book|paano magbook|paano mag-book|paano kumuha|mag-reserve|magreserve|reservation)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "Madali lang pong mag-book ng inyong dream tour sa HeyDream! Paki-sunod lamang ang mga simpleng hakbang na ito sa aming website:<br><br>" .
                           "1️⃣ **Piliin ang Inyong Tour Package:** Mag-browse sa aming homepage at i-click ang package na nais ninyo (gaya ng Boracay, Cebu, Palawan, Singapore, at iba pa).<br>" .
                           "2️⃣ **I-select ang Details & Book:** Piliin ang inyong travel date, dami ng travelers, at mga gustong add-ons, pagkatapos ay i-click ang **Book Now** para pumunta sa checkout.<br>" .
                           "3️⃣ **Secure Payment:** Piliin ang inyong preferred payment method (GCash, PayMaya, BPI, BDO, Metrobank, o Credit/Debit Card) para magbayad.<br>" .
                           "4️⃣ **I-upload ang Resibo:** Ipasok ang inyong transaction/reference number at i-upload ang kopya ng inyong proof of payment para sa mabilis na verification.<br>" .
                           "5️⃣ **Tanggapin ang Vouchers:** Pagkatapos ng verification ng aming team, matatanggap ninyo ang inyong official travel vouchers at final itinerary sa inyong email! 📩😊<br><br>" .
                           "📝 *Last, maaari din po kayong mag-fill up ng **Inquiry Form** sa ibaba ng page para sa mas detalyadong impormasyon o special requests para sa inyong preferred location! 👇*",
                'suggestions' => ['Anong destinations ang meron?', 'Custom trip options?', 'Ano ang payment options?']
            ];
        }
        return [
            'reply' => "Booking your dream vacation directly on the HeyDream website is incredibly simple! Just follow these easy steps:<br><br>" .
                       "1️⃣ **Select Your Tour Package:** Browse our homepage and click on the package you want (such as Boracay, Cebu, Palawan, Singapore, Japan, etc.).<br>" .
                       "2️⃣ **Choose Details & Click Book:** Select your travel dates, number of guests, select any optional add-ons, and click **Book Now** to head to checkout.<br>" .
                       "3️⃣ **Complete Secure Payment:** Select your preferred payment option (GCash, PayMaya, BPI, BDO, Metrobank, or Credit/Debit Card) to pay the deposit or full balance.<br>" .
                       "4️⃣ **Upload Your Receipt:** Enter your transaction/reference number and upload a copy of your proof of payment for speedy verification.<br>" .
                       "5️⃣ **Receive Your Vouchers:** Once verified by our reservations team, your official travel vouchers and complete itinerary will be emailed directly to you! 📩😊<br><br>" .
                       "📝 *Last, you can also fill up the **Inquiry Form** at the below of the page for a more detailed request of your preferred location!*",
            'suggestions' => ['What destinations do you offer?', 'Custom trip option?', 'What are your payment options?']
        ];
    }

    // 0.6. Custom / Tailor Trip check
    if (preg_match('/\b(custom|customize|customized|personalize|personalized|tailor|tailored|sariling itinerary|iba ang kasama|custom trip|custom trip option)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "Yes po! We specialize in **Customized & Personalized Travel Itineraries** para sa inyong barkada, pamilya, o corporate team building! 🌟<br><br>" .
                           "Maaari po nating i-adjust ang:<br>" .
                           "✅ **Travel Dates** at duration (hal. 4D3N o mas mahaba)<br>" .
                           "✅ **Hotel / Resort** standard (Budget, Mid-range, o 5-Star Luxury)<br>" .
                           "✅ **Tours & Activities** (Isama o alisin ang mga specific day tours)<br>" .
                           "✅ **Flights & Transfers** (Mula sa inyong preferred airport o customized land transport)<br><br>" .
                           "Para makagawa kami ng perpektong itinerary para sa inyo, paki-fill up lamang ang **Inquiry Form** sa itaas at ilagay sa **Special Requests/Remarks** ang inyong customized preferences. Pagtutulungan po ito ng aming travel experts! ✈️💖",
                'suggestions' => ['Mag-submit ng inquiry', 'Ano ang payment options?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "Yes, absolutely! We specialize in crafting **Custom & Tailored Travel Packages** to perfectly match your preferences, budget, and travel style! 🌟<br><br>" .
                       "We can fully customize:<br>" .
                       "✅ **Your Travel Dates & Duration** (extend or shorten your stay)<br>" .
                       "✅ **Hotel Standard** (from cozy boutique budget resorts to 5-star luxury beachfront properties)<br>" .
                       "✅ **Itinerary & Activities** (add specific tours, island hoppings, or keep free days)<br>" .
                       "✅ **Flights & Transfers** (add roundtrip flights and private airport land transfers)<br><br>" .
                       "To request a customized trip, simply fill out the **Inquiry Form** at the top of the page, and specify your custom requirements in the **Special Requests** field. Our travel curators will design the perfect quote for you! ✈️💖",
            'suggestions' => ['Submit an inquiry', 'What are your payment options?', 'Contact our team']
        ];
    }

    // 0.7. Boracay specific handler (Above generic places check)
    if (preg_match('/\b(boracay)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "🌴 **Boracay Island — Ang Paraiso ng Puting Buhangin!** ☀️<br><br>" .
                           "Kilala ang Boracay sa buong mundo dahil sa pambihirang pinong-pinong puting buhangin (White Beach), kamangha-manghang sunsets, masasarap na local at international restaurants, at kapana-panabik na water sports activities!<br><br>" .
                           "⭐ **Aming Boracay Packages:**<br>" .
                           "• **Boracay Station 3 Bamboo Beach Resort (3D2N):** Magsisimula sa **₱4,644.00/pax**! Kasama ang daily breakfast, roundtrip Caticlan transfers (pati environmental & port fees), at complimentary travel insurance.<br>" .
                           "• **Boracay Secret Garden Resort (3D2N):** Magsisimula sa **₱4,444.00/pax**! Perfect para sa tahimik at relaxing na bakasyon.<br><br>" .
                           "• **Promo Deal (3D2N Boracay Special):** Limitadong alok na **₱4,999.00** lamang (orihinal na ₱8,999) kasama ang hotel, Caticlan transfers, at island hopping tour! ⛵<br><br>" .
                           "Gusto niyo po bang i-check natin ang availability? Mag-submit lamang ng inquiry gamit ang form sa itaas! 👆",
                'suggestions' => ['How do I book a tour?', 'Ano ang payment options?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "🌴 **Boracay Island — The Ultimate Tropical Paradise!** ☀️<br><br>" .
                       "Boracay is world-famous for its powdery White Beach, breathtaking sunsets, crystal-clear turquoise waters, vibrant nightlife, and exciting activities (helmet diving, parasailing, island hopping, and paddle boarding)!<br><br>" .
                       "⭐ **Our Current Boracay Packages:**<br>" .
                       "• **Boracay Station 3 Bamboo Beach Resort (3D2N):** Starts at **₱4,644.00/pax**! Includes hotel stay, daily breakfast, roundtrip Caticlan airport transfers (with environmental and terminal fees covered), and travel insurance.<br>" .
                       "• **Boracay Secret Garden Resort (3D2N):** Starts at **₱4,444.00/pax** for a cozy, peaceful garden escape.<br><br>" .
                       "• **Promo Deal (3D2N Boracay Special):** Limited flash sale at **₱4,999.00** (originally ₱8,999) featuring resort stay, Caticlan transfers, and a complimentary island-hopping tour! ⛵<br><br>" .
                       "Would you like us to secure this rate for you? Simply fill out the **Inquiry Form** above to check slots! 👆",
            'suggestions' => ['Tell me about Boracay', 'How do I book a tour?', 'What are your payment options?']
        ];
    }

    // 0.8. Japan specific handler (Above generic places check)
    if (preg_match('/\b(japan|tokyo|osaka|kyoto|mt fuji|cherry blossom)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "🗾 **Japan — Ang Lupain ng Pagsikat ng Araw!** 🌸<br><br>" .
                           "Tanyag sa buong mundo ang Japan para sa cherry blossoms (Sakura), Mt. Fuji, anime, modernong teknolohiya, at masasarap na pagkain (ramen, sushi, tempura)!<br><br>" .
                           "⭐ **Aming Japan Tour & Visa Options:**<br>" .
                           "• **Japan Tour Packages (5D4N):** Magsisimula sa **₱38,999/pax**! Kasama ang hotel accommodations, return flights, daily breakfast, at full guided sightseeing tours sa Tokyo, Osaka, o Kyoto! 🏯<br>" .
                           "• **Visa Assistance:** Nag-aalok kami ng mabilis at maaasahang visa assistance para sa Japanese tourist visa! Tutulungan namin kayo sa documents checklist at application process. 🛂<br><br>" .
                           "Para sa inyong personalized quotation, paki-fill up lamang ang inquiry form sa itaas! 👆",
                'suggestions' => ['Japan visa requirements', 'How do I book a tour?', 'Ano ang payment options?']
            ];
        }
        return [
            'reply' => "🗾 **Japan — The Land of the Rising Sun!** 🌸<br><br>" .
                       "Japan is a spectacular mix of ancient traditions and futuristic cities. Famous for stunning cherry blossoms (Sakura), Mt. Fuji, anime culture, and delicious cuisine (sushi, ramen, matcha)!<br><br>" .
                       "⭐ **Our Premium Japan & Visa Packages:**<br>" .
                       "• **5D4N Japan Guided Tours:** Starts at **₱38,999/pax**! Covers comfortable hotel stays, roundtrip flights, daily breakfasts, and fully guided excursions across Tokyo, Osaka, or Kyoto! 🏯<br>" .
                       "• **Visa Assistance Support:** We provide comprehensive tourist visa processing assistance. We checklist your files, double-check forms, and submit to the embassy. 🛂<br><br>" .
                       "Ready for your Japanese adventure? Fill out the **Inquiry Form** at the top of the page for a custom quote! 👆",
            'suggestions' => ['Japan visa requirements', 'How do I book a tour?', 'What are your payment options?']
        ];
    }

    // 0.9. Singapore specific handler (Above generic places check)
    if (preg_match('/\b(singapore|sg|merlion park|gardens by the bay|ibis budget)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "🇸🇬 **Singapore — Ang Modernong Garden City!** 🌆<br><br>" .
                           "Damhin ang ganda ng modernong Singapore! Bisitahin ang Merlion Park, Gardens by the Bay, Universal Studios Singapore (USS), at Sentosa Island.<br><br>" .
                           "⭐ **Aming Singapore Packages (Ibis Budget Singapore):**<br>" .
                           "• **3D2N Singapore Insta Package:** Magsisimula sa **$152.00 USD/pax**! Kasama ang Ibis Budget Emerald lodging, airport transfers, at guided Instagram city tour (Merlion, Thian Hock Keng, Gardens by the Bay).<br>" .
                           "• **3D2N Explore SG F&E:** Magsisimula sa **$164.00 USD/pax** sa Ibis Budget Selegie.<br>" .
                           "• **4D3N Explore SG F&E:** Magsisimula sa **$265.00 USD/pax** para sa mas mahabang bakasyon.<br><br>" .
                           "Mag-submit lamang ng inquiry sa form sa itaas para makakuha ng final flight-inclusive quote! 👆",
                'suggestions' => ['How do I book a tour?', 'Ano ang payment options?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "🇸🇬 **Singapore — The Vibrant Garden City!** 🌆<br><br>" .
                       "Experience the futuristic and clean city of Singapore! Walk through Gardens by the Bay, take photos at Merlion Park, and enjoy Universal Studios Singapore (USS) and Sentosa Island.<br><br>" .
                       "⭐ **Our Singapore Packages (Ibis Budget Hotels):**<br>" .
                       "• **3D2N Singapore Insta Package:** Starts at only **$152.00 USD/pax**! Includes stay at Ibis Budget Emerald, airport transfers, and an Instagram guided tour (covering Merlion Park, Thian Hock Keng temple, and Gardens by the Bay).<br>" .
                       "• **3D2N Explore SG F&E:** Starts at **$164.00 USD/pax** at Ibis Budget Selegie.<br>" .
                       "• **4D3N Explore SG F&E:** Starts at **$265.00 USD/pax** for an extended vacation.<br><br>" .
                       "To customize or book your Singapore trip, complete the **Inquiry Form** above! 👆",
            'suggestions' => ['How do I book a tour?', 'What are your payment options?', 'Contact our team']
        ];
    }

    // 0.95. Hong Kong specific handler (Above generic places check)
    if (preg_match('/\b(hong kong|hk|hkg|disneyland|o hotel|silka tsuen wan)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "🇭🇰 **Hong Kong — Ang Lungsod ng Disneyland at Shopping!** 🎡<br><br>" .
                           "Paboritong bakasyunan ng pamilyang Pilipino! Bisitahin ang Hong Kong Disneyland, Avenue of Stars, West Kowloon Art Park, at kumain ng masasarap na dim sum!<br><br>" .
                           "⭐ **Aming Hong Kong Packages (3D2N Free & Easy):**<br>" .
                           "• **O Hotel Hong Kong:** Magsisimula sa **$105.00 USD/pax**! Kasama ang airport transfers, breakfast coupons, at compulsory City Tour (Avenue of Stars, West Kowloon, Palace Museum outside).<br>" .
                           "• **Silka Tsuen Wan:** Magsisimula sa **$115.00 USD/pax** kasama ang parehong free city tour at transfers.<br>" .
                           "• **4D3N Free & Easy (O Hotel):** Magsisimula sa **$130.00 USD/pax** para sa 4-day trip.<br><br>" .
                           "*Tandaan: May compulsory tour guide tip na HK$50/pax.*<br><br>" .
                           "Mag-inquire na sa form sa itaas para ma-check ang slot at rates! 👆",
                'suggestions' => ['How do I book a tour?', 'Ano ang payment options?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "🇭🇰 **Hong Kong — The Land of Disneyland & Endless Shopping!** 🎡<br><br>" .
                       "A supreme favorite for Filipino families! Explore Hong Kong Disneyland, walk the Avenue of Stars, enjoy the West Kowloon Art Park, and feast on authentic dim sum.<br><br>" .
                       "⭐ **Our Hong Kong Packages (3D2N Free & Easy):**<br>" .
                       "• **O Hotel Hong Kong:** Starts at only **$105.00 USD/pax**! Includes hotel, transfers, outside breakfast coupons, and a compulsory City Tour (Avenue of Stars, West Kowloon, Palace Museum outside).<br>" .
                       "• **Silka Tsuen Wan:** Starts at **$115.00 USD/pax** with same inclusions.<br>" .
                       "• **4D3N Free & Easy (O Hotel):** Starts at **$130.00 USD/pax** for an extended stay.<br><br>" .
                       "*Note: There is a compulsory guide tipping fee of HK$50/pax.*<br><br>" .
                       "Submit an **Inquiry Form** above to book your slots today! 👆",
            'suggestions' => ['How do I book a tour?', 'What are your payment options?', 'Contact our team']
        ];
    }

    // 0.96. Cebu specific handler (Above generic places check)
    if (preg_match('/\b(cebu|mactan|oslob|mabolo royal hotel)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "🏰 **Cebu City — Ang Reyna Lungsod ng Timog!** 🌊<br><br>" .
                           "Ang Cebu ay kilala sa makasaysayang landmarks (Magellan's Cross, Fort San Pedro), masasarap na Cebu Lechon, at kamangha-manghang adventure tours gaya ng whale shark watching sa Oslob at canyoneering sa Badian!<br><br>" .
                           "⭐ **Aming Cebu Package:**<br>" .
                           "• **3D2N Cebu Free & Easy (Mabolo Royal Hotel):** Magsisimula sa **₱3,684.00/pax**! Kasama ang komportableng hotel stay, roundtrip airport transfers via Mactan International Airport, at complimentary travel insurance.<br><br>" .
                           "Gusto niyo po bang mag-request ng quote? Paki-fill up lamang ang inquiry form sa itaas! 👆",
                'suggestions' => ['How do I book a tour?', 'Ano ang payment options?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "🏰 **Cebu City — The Queen City of the South!** 🌊<br><br>" .
                       "Cebu is famous for its rich history (Magellan's Cross, Temple of Leah), world-class diving, mouthwatering Cebu Lechon, and thrill-seeking excursions (Oslob Whale Shark swimming and Kawasan Falls canyoneering)!<br><br>" .
                       "⭐ **Our Featured Cebu Package:**<br>" .
                       "• **3D2N Cebu Free & Easy (Mabolo Royal Hotel):** Starts at only **₱3,684.00/pax**! Includes hotel stay, roundtrip airport transfers from Mactan airport, and travel insurance.<br><br>" .
                       "Ready to plan your trip? Just fill out our simple **Inquiry Form** above! 👆",
            'suggestions' => ['How do I book a tour?', 'What are your payment options?', 'Contact our team']
        ];
    }

    // 0.97. Palawan specific handler (Above generic places check)
    if (preg_match('/\b(palawan|puerto princesa|pps|underground river|el nido|coron|citystate asturias)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "🛶 **Puerto Princesa, Palawan — Ang Tahanan ng Underground River!** 🌴<br><br>" .
                           "Ang Puerto Princesa ay tanyag sa buong mundo dahil sa New 7 Wonders of Nature — ang Puerto Princesa Subterranean River! Perfect din ito para sa Honda Bay island hopping at firefly watching.<br><br>" .
                           "⭐ **Aming Palawan Packages (Citystate Asturias Hotel Palawan):**<br>" .
                           "• **3D2N Free & Easy:** Magsisimula sa **₱2,894.00/pax**! Kasama ang airport transfers, breakfast, at travel insurance.<br>" .
                           "• **4D3N Free & Easy:** Magsisimula sa **₱3,894.00/pax** para sa mas mahabang bakasyon.<br>" .
                           "• **Underground River Promo Deal:** **₱5,499.00** lamang (orihinal na ₱9,999) kasama ang full guided Underground River Tour, buffet lunch, hotel, at transfers! 🛶<br><br>" .
                           "Paki-fill up lamang ang inquiry form sa itaas para mag-book! 👆",
                'suggestions' => ['How do I book a tour?', 'Ano ang payment options?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "🛶 **Puerto Princesa, Palawan — Home of the Underground River!** 🌴<br><br>" .
                       "Puerto Princesa is the gateway to one of the New 7 Wonders of Nature: the spectacular Puerto Princesa Underground River! It is also famous for Honda Bay island hopping, firefly watching, and pristine beaches.<br><br>" .
                       "⭐ **Our Palawan Packages (Citystate Asturias Hotel):**<br>" .
                       "• **3D2N Free & Easy:** Starts at **₱2,894.00/pax**! Includes hotel accommodation, airport transfers, breakfast, and travel insurance.<br>" .
                       "• **4D3N Free & Easy:** Starts at **₱3,894.00/pax** for an extended relaxing stay.<br>" .
                       "• **Underground River Promo Deal:** Only **₱5,499.00** (originally ₱9,999) featuring resort stay, roundtrip airport transfers, full guided Underground River Tour, and a buffet lunch! 🛶<br><br>" .
                       "To check slots and book, simply fill out the **Inquiry Form** above! 👆",
            'suggestions' => ['How do I book a tour?', 'What are your payment options?', 'Contact our team']
        ];
    }

    // 1. Ask about services
    if (preg_match('/\b(service|services|what do you offer|what are your offers|ano ang mga offer|ano ang inyong serbisyo|ano ang mga services|what services)\b/i', $m)) {
        return [
            'reply' => "Tour Packages<br>Flight Booking<br>Visa Assistance<br>Hotel Reservations<br>Airport Transfers<br>Group Tours",
            'suggestions' => ['How can I pay for the package?', 'What are your best travel packages?', 'What are your payment options?']
        ];
    }

    // 2. Ask about visa
    if (preg_match('/\b(visa|visas|passport|requirements for visa|visa application|apply for visa|schengen|korean visa|japan visa)\b/i', $m)) {
        return [
            'reply' => "Thank you for your inquiry about a visa! 😊 Please provide the following details:<br><br>• Number of travelers<br>• Travel dates<br>• Location (if applicable)<br><br>Once I have this information, I can connect you to one of our travel agents for assistance!",
            'suggestions' => ['How can I pay for the package?', 'What are your best travel packages?', 'What are your payment options?']
        ];
    }

    // 3. Ask about generic location or places
    $places = "";
    if (preg_match('/\b(boracay|palawan|el nido|coron|puerto princesa|san vicente|cebu|siargao|bohol|batanes|sagada|baguio|vigan|tagaytay|puerto galera|dumaguete|subic|clark|japan|tokyo|osaka|kyoto|korea|seoul|busan|jeju|thailand|bangkok|phuket|singapore|malaysia|kuala lumpur|dubai|taiwan|taipei|hong kong|macao|bali|vietnam|hanoi|europe|paris|rome|switzerland|usa|new york|california|australia|sydney|melbourne)\b/i', $m, $placeMatches)) {
        $places = ucfirst($placeMatches[1]);
    } elseif (preg_match('/\b(located|location|places|place|where are you|where is your office|office address|address)\b/i', $m)) {
        $places = "our location";
    }

    if (!empty($places)) {
        return [
            'reply' => "Thank you for your inquiry about {$places}! 😊 Please provide the following details:<br><br>• Number of travelers<br>• Travel dates<br>• Location (if applicable)<br>• Preferred package (if any)<br><br>Once I have this information, I can connect you to one of our travel agents for assistance!",
            'suggestions' => ['How can I pay for the package?', 'What are your best travel packages?', 'What are your payment options?']
        ];
    }

    $isTagalog = preg_match('/\b(kumusta|kamusta|magandang|anong|magkano|saan|gusto|meron|paano|salamat|po|ho|ba|nga|yung|ang|mga|ako|ikaw|kayo|kami|namin|natin|sino|oo|hindi)\b/i', $msg);

    if (preg_match('/\b(thank|salamat|thanks|thankyou|thank\s*you|submit\s*an?\s*inquiry|mag-submit\s*ng\s*inquiry)\b/i', $m)) {
        if ($isTagalog) return ['reply'=>"🙏 Maraming salamat din po! Paki-fill up lamang po ang {$inquiryLinkTagalog} at mag-re-reply po ang aming live agents within minutes para tulungan kayo. Have a great day! 😊",'suggestions'=>['Anong destinations ang meron?','Visa assistance info','Makipag-ugnayan']];
        return ['reply'=>"🙏 You're very welcome! Please make sure to fill out the {$inquiryLinkEnglish} and our live travel agents will reply within minutes to assist you. Have a wonderful day! 😊",'suggestions'=>['What destinations do you offer?','Visa assistance info','Contact our team']];
    }

    if (preg_match('/\b(hi|hello|hey|kumusta|kamusta|magandang|good\s*(morning|afternoon|evening))\b/i', $msg)) {
        if ($isTagalog) return ['reply'=>'👋 Hi po! Welcome sa <strong>HeyDream Travel and Tours</strong>!<br>Ako si Dream, ang inyong personal travel assistant. 😊<br>Paano kita matutulungan ngayon? ✈️','suggestions'=>['Anong destinations ang meron?','Visa assistance info',$submitSugTagalog]];
        return ['reply'=>'👋 Hi there! Welcome to <strong>HeyDream Travel and Tours</strong>!<br>I\'m Dream, your personal travel assistant. 😊<br>How can I help you today? ✈️🌏','suggestions'=>['What destinations do you offer?','Visa assistance info',$submitSugEnglish]];
    }
    
    if (preg_match('/\b(destination|destinations|lugar|saan|where|pupunta|punta|travel|place|packages|packages do you offer|destinations do you offer)\b/i', $m)) {
        if ($isTagalog) {
            return [
                'reply' => "🌏 **Mga Destinasyon na Inaalok ng HeyDream!**<br><br>" .
                           "📍 **LOCAL DESTINATIONS (Pilipinas):**<br><br>" .
                           "1️⃣ **Boracay**<br>" .
                           "<img src=\"{$img_prefix}images/boracay.jpg\" alt=\"Boracay\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                           "2️⃣ **Siargao**<br>" .
                           "<img src=\"{$img_prefix}images/siargao.jpg\" alt=\"Siargao\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                           "3️⃣ **El Nido**<br>" .
                           "<img src=\"{$img_prefix}images/elnido.jpg\" alt=\"El Nido\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br><br>" .
                           "✈️ **FOREIGN DESTINATIONS (Banyaga):**<br><br>" .
                           "1️⃣ **Japan**<br>" .
                           "<img src=\"{$img_prefix}images/japan.jpg\" alt=\"Japan\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                           "2️⃣ **Korea**<br>" .
                           "<img src=\"{$img_prefix}images/korea.jpg\" alt=\"Korea\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                           "3️⃣ **Vietnam**<br>" .
                           "<img src=\"{$img_prefix}images/vietnam.jpg\" alt=\"Vietnam\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br><br>" .
                           "⭐ **Others (Iba Pang Lokasyon):**<br>" .
                           "Nais niyo po ba sa ibang destinasyon? Paki-check lamang ang aming website o maaari po kayong pumunta sa aming {$inquiryLinkTagalog} para sa inyong preferred location! 🗺️✨",
                'suggestions' => ['How do I book a tour?', 'Ano ang payment options?', 'Makipag-ugnayan']
            ];
        }
        return [
            'reply' => "🌏 **Destinations Offered by HeyDream Travel & Tours!**<br><br>" .
                       "📍 **LOCAL DESTINATIONS (Philippines):**<br><br>" .
                       "1️⃣ **Boracay**<br>" .
                       "<img src=\"{$img_prefix}images/boracay.jpg\" alt=\"Boracay\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                       "2️⃣ **Siargao**<br>" .
                       "<img src=\"{$img_prefix}images/siargao.jpg\" alt=\"Siargao\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                       "3️⃣ **El Nido**<br>" .
                       "<img src=\"{$img_prefix}images/elnido.jpg\" alt=\"El Nido\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br><br>" .
                       "✈️ **FOREIGN DESTINATIONS (International):**<br><br>" .
                       "1️⃣ **Japan**<br>" .
                       "<img src=\"{$img_prefix}images/japan.jpg\" alt=\"Japan\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                       "2️⃣ **Korea**<br>" .
                       "<img src=\"{$img_prefix}images/korea.jpg\" alt=\"Korea\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>" .
                       "3️⃣ **Vietnam**<br>" .
                       "<img src=\"{$img_prefix}images/vietnam.jpg\" alt=\"Vietnam\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br><br>" .
                       "⭐ **Others (Other Locations):**<br>" .
                       "Looking for other destinations? Please check our website for more exciting locations, or you can visit our {$inquiryLinkEnglish} for your preferred location! 🗺️✨",
            'suggestions' => ['How do I book a tour?', 'What are your payment options?', 'Contact our team']
        ];
    }
    
    if (preg_match('/\b(budget|cost|price|magkano|presyo|bayad|how much|rate|quote)\b/i', $msg)) {
        if ($isTagalog) {
            return [
                'reply' => "💰 Ang aming tour package rates ay highly dynamic at may iba't ibang seasonal promos! Para sa pinakabago at pinakamababang presyo:<br><br>" .
                           "1️⃣ **I-check ang aming website:** Tingnan ang pinakabagong deals at live package prices diretso sa aming homepage.<br>" .
                           "2️⃣ **Makipag-usap sa aming Live Agents:** Maaari ninyong tawagan ang aming live team para sa agarang tulong! I-click lamang ang **menu button o ang 3-line button sa itaas**, pagkatapos ay i-click ang **Live Agents** para maka-contact agad! 📞😊",
                'suggestions' => ['Anong destinations ang meron?', 'How do I book a tour?', 'Ano ang payment options?']
            ];
        }
        return [
            'reply' => "💰 Our tour package rates are highly dynamic with regular active seasonal discounts! To secure the most accurate and lowest prices:<br><br>" .
                       "1️⃣ **Check the latest on the website:** Browse our homepage to view real-time package rates and inclusions.<br>" .
                       "2️⃣ **Contact our Live Agents:** Get detailed custom quotations and instant answers! Simply **click the menu button (or the 3-line button above)**, then click **Live Agents** to connect with a representative immediately! 📞😊",
            'suggestions' => ['What destinations do you offer?', 'How do I book a tour?', 'What are your payment options?']
        ];
    }
    
    if (preg_match('/\b(visa|dokumento|document|requirements)\b/i', $msg)) {
        if ($isTagalog) return ['reply'=>'🛂 Oo, nag-oofeer kami ng <strong>Visa Assistance</strong>!<br>Japan, Korea, Europe, USA, Australia at marami pa.<br>Tulungan kayo namin sa documents — bank cert, employment cert, photos, at iba pa. 😊','suggestions'=>['Japan visa requirements','Gaano katagal ang visa?','Europe Schengen visa']];
        return ['reply'=>'🛂 Yes! We offer <strong>Visa Assistance</strong> for Japan, Korea, Europe, USA, Australia, and more.<br>We help you prepare all documents — bank certificate, employment certificate, photos, and more. 😊','suggestions'=>['Japan visa requirements','How long does processing take?','Europe Schengen visa']];
    }
    
    if (preg_match('/\b(include|kasama|inclus|package|tour)\b/i', $msg)) {
        if ($isTagalog) return ['reply'=>'📦 Ang Tour Packages namin ay kasama ang:<br>✅ Round-trip airfare<br>✅ Hotel accommodation<br>✅ Airport transfers<br>✅ Tour guide<br>✅ Selected meals<br>✅ Entrance fees<br>Depende sa package ang exact inclusions! 😊','suggestions'=>['Custom trip available?','Makipag-ugnayan',$submitSugTagalog]];
        return ['reply'=>'📦 Our Tour Packages typically include:<br>✅ Round-trip airfare<br>✅ Hotel accommodation<br>✅ Airport transfers<br>✅ Tour guide<br>✅ Selected meals<br>✅ Entrance fees to attractions<br>Exact inclusions vary per package! 😊','suggestions'=>['Custom trip option?','Contact our team',$submitSugEnglish]];
    }
    
    if (preg_match('/\b(contact|call|email|reach|tanong|makipag|talk)\b/i', $msg)) {
        if ($isTagalog) return ['reply'=>"📞 Makipag-ugnayan sa amin:<br><strong>0945 776 4140</strong> (Viber/WhatsApp)<br>✉️ <strong>heydreamtravelandtours@gmail.com</strong><br>O mag-fill up sa aming {$inquiryLinkTagalog} at mag-re-reply kami within minutes! ⚡",'suggestions'=>[$submitSugTagalog,'Anong oras kayo?','Social media links']];
        return ['reply'=>"📞 Get in touch with us:<br><strong>0945 776 4140</strong> (Viber/WhatsApp)<br>✉️ <strong>heydreamtravelandtours@gmail.com</strong><br>Or fill out the {$inquiryLinkEnglish} and we'll reply within minutes! ⚡",'suggestions'=>[$submitSugEnglish,'What are your hours?','Social media links']];
    }
    
    if (preg_match('/\b(insurance|seguro|coverage)\b/i', $msg)) {
        return ['reply'=>'🛡️ We also offer <strong>Travel Insurance</strong>!<br>Covers: Medical emergencies, trip cancellation, lost baggage & flight delays.<br>Highly recommended for international travel. Available as add-on to any package! 😊','suggestions'=>['International packages',$submitSugEnglish,'Contact our team']];
    }
    
    if (preg_match('/\b(promo|deal|discount|sale|flash)\b/i', $msg)) {
        return ['reply'=>"🔥 We regularly post <strong>Flash Deals & Promos</strong>!<br>Check our website for the latest deals.<br>Group discounts available for <strong>10+ travelers</strong>. 🎉<br>Please visit our {$inquiryLinkEnglish} to secure active promotional rates!",'suggestions'=>['Group discount details',$submitSugEnglish,'View flash deals']];
    }
    
    if (preg_match('/\b(group|grupo|family|pamilya|barkada)\b/i', $msg)) {
        return ['reply'=>'👨‍👩‍👧‍👦 We\'re perfect for <strong>groups, families & barkadas</strong>!<br>Group discounts available for 10+ travelers.<br>Custom itineraries tailored for your group — just let us know what you need! 🎉','suggestions'=>['Group discount details','Family-friendly destinations','Get a group quote']];
    }
    
    if (preg_match('/\b(hotel|resort|room|stay|accommodation|lodging|booking)\b/i', $msg)) {
        if ($isTagalog) return ['reply'=>"🏨 Para sa mga hotel, resorts, at accommodations, nag-aalok kami ng discounted rates! 🌟<br>Para ma-check ang live availability at makuha ang pinakamababang presyo, <strong>paki-fill up ang detalye sa aming {$inquiryLinkTagalog}</strong> at ang aming bookings team ang mag-aasikaso nito agad!",'suggestions'=>[$submitSugTagalog,'Tingnan ang destinations','Custom trip options']];
        return ['reply'=>"🏨 For hotel bookings, resorts, and accommodations, we offer exclusive discounted agency rates! 🌟<br>To check live room availability and secure the best rates, <strong>please submit your details on our {$inquiryLinkEnglish}</strong> and our reservations team will handle it for you immediately!",'suggestions'=>[$submitSugEnglish,'Check destinations','Custom trip options']];
    }
    
    if ($isTagalog) {
        return ['reply'=>"😊 Salamat sa inyong tanong! Para sa mas detalyadong impormasyon at para masagot namin ito ng tama, paki-fill up ang detalye sa aming {$inquiryLinkTagalog} at mag-re-reply ang aming live team within minutes.<br>O makipag-ugnayan: <strong>0945 776 4140</strong> 📞",'suggestions'=>['Anong destinations ang meron?','Visa assistance info',$submitSugTagalog]];
    }
    return ['reply'=>"😊 Great question! To give you the most accurate answers and check all choices for you, please visit our {$inquiryLinkEnglish} and our live team will assist you immediately.<br>Or reach us at: <strong>0945 776 4140</strong> 📞",'suggestions'=>['What destinations do you offer?','Visa assistance info',$submitSugEnglish]];
}
?>

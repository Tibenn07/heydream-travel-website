<?php
// config.php - Configuration, Data, and Helper Functions

// Initialize mock data in session if not exists
if (!isset($_SESSION['heydream_data'])) {
    $_SESSION['heydream_data'] = [
        // ========== PARTNER APPLICATIONS DATA ==========
        'partner_applications' => [
            [
                'id' => 'app_001',
                'business_name' => 'Sunset Paradise Tours',
                'email' => 'contact@sunsetparadise.com',
                'phone' => '+63 912 345 6789',
                'business_type' => 'Tour Operator',
                'address' => '123 Beach Road, Boracay, Aklan',
                'documents' => [
                    'business_permit' => ['name' => 'business_permit_sunset.pdf', 'url' => '#'],
                    'dti_registration' => ['name' => 'dti_sunset.pdf', 'url' => '#'],
                    'sec_registration' => null,
                    'dot_accreditation' => ['name' => 'dot_sunset.jpg', 'url' => '#']
                ],
                'status' => 'pending',
                'submitted_at' => '2024-05-20 14:30:00',
                'message' => 'We are excited to join HeyDream platform! We offer island hopping tours.'
            ],
            [
                'id' => 'app_002',
                'business_name' => 'Mountain Escape Lodge',
                'email' => 'info@mountainescape.com',
                'phone' => '+63 923 456 7890',
                'business_type' => 'Accommodation',
                'address' => '45 Pine Street, Baguio City',
                'documents' => [
                    'business_permit' => ['name' => 'business_permit_mountain.pdf', 'url' => '#'],
                    'dti_registration' => ['name' => 'dti_mountain.pdf', 'url' => '#'],
                    'sec_registration' => null,
                    'dot_accreditation' => ['name' => 'dot_mountain.pdf', 'url' => '#']
                ],
                'status' => 'pending',
                'submitted_at' => '2024-05-21 09:15:00',
                'message' => 'Looking forward to partnering with HeyDream!'
            ],
            [
                'id' => 'app_003',
                'business_name' => 'Metro Events & Tours',
                'email' => 'hello@metrotours.com',
                'phone' => '+63 934 567 8901',
                'business_type' => 'Event Planning',
                'address' => '78 Corporate Center, Makati City',
                'documents' => [
                    'business_permit' => ['name' => 'business_permit_metro.pdf', 'url' => '#'],
                    'dti_registration' => null,
                    'sec_registration' => ['name' => 'sec_metro.pdf', 'url' => '#'],
                    'dot_accreditation' => null
                ],
                'status' => 'pending',
                'submitted_at' => '2024-05-22 11:45:00',
                'message' => 'We specialize in corporate events and team building.'
            ],
            [
                'id' => 'app_004',
                'business_name' => 'Island Hopper PH',
                'email' => 'book@islandhopper.ph',
                'phone' => '+63 945 678 9012',
                'business_type' => 'Tour Operator',
                'address' => '56 Dapitan St, Puerto Princesa, Palawan',
                'documents' => [
                    'business_permit' => ['name' => 'business_permit_island.pdf', 'url' => '#'],
                    'dti_registration' => ['name' => 'dti_island.pdf', 'url' => '#'],
                    'sec_registration' => null,
                    'dot_accreditation' => ['name' => 'dot_island.png', 'url' => '#']
                ],
                'status' => 'approved',
                'submitted_at' => '2024-05-18 13:20:00',
                'reviewed_at' => '2024-05-20 10:00:00',
                'message' => 'We have 10+ years of experience in Palawan tours.'
            ],
            [
                'id' => 'app_005',
                'business_name' => 'Sky High Travel',
                'email' => 'fly@skyhightravel.com',
                'phone' => '+63 956 789 0123',
                'business_type' => 'Travel Agency',
                'address' => '12 Airport Road, Pasay City',
                'documents' => [
                    'business_permit' => ['name' => 'business_permit_sky.pdf', 'url' => '#'],
                    'dti_registration' => null,
                    'sec_registration' => ['name' => 'sec_sky.pdf', 'url' => '#'],
                    'dot_accreditation' => ['name' => 'dot_sky.pdf', 'url' => '#']
                ],
                'status' => 'rejected',
                'submitted_at' => '2024-05-19 16:45:00',
                'reviewed_at' => '2024-05-21 14:30:00',
                'rejection_reason' => 'Incomplete document submission. Please submit all required permits.',
                'message' => 'We offer international flight packages.'
            ]
        ],
        'customer_reports' => [
            ['id' => 'cust_1', 'category' => 'Scam', 'title' => 'Suspicious payment request', 'description' => 'Partner asked for upfront payment outside platform', 'reportedBy' => 'Jane D.', 'reportedEmail' => 'jane@email.com', 'priority' => 'High', 'status' => 'open', 'createdAt' => '2024-05-23 10:24:00'],
            ['id' => 'cust_2', 'category' => 'Bad Review', 'title' => 'Inappropriate review retaliation', 'description' => 'Customer claims partner harassed them after 1-star review', 'reportedBy' => 'Mike R.', 'reportedEmail' => 'mike@email.com', 'priority' => 'Medium', 'status' => 'in_review', 'createdAt' => '2024-05-22 13:15:00'],
            ['id' => 'cust_3', 'category' => 'Wrong Place', 'title' => 'Wrong event venue address', 'description' => 'Event held at different hall than advertised', 'reportedBy' => 'Sarah L.', 'reportedEmail' => 'sarah@email.com', 'priority' => 'Medium', 'status' => 'open', 'createdAt' => '2024-05-22 11:48:00'],
            ['id' => 'cust_4', 'category' => 'Behavior', 'title' => 'Rude behavior by staff', 'description' => 'Staff was unprofessional and rude to customer', 'reportedBy' => 'David P.', 'reportedEmail' => 'david@email.com', 'priority' => 'Low', 'status' => 'resolved', 'createdAt' => '2024-05-21 09:30:00'],
            ['id' => 'cust_5', 'category' => 'Misleading Info', 'title' => 'Misleading information', 'description' => 'Service description did not match actual offering', 'reportedBy' => 'Emily T.', 'reportedEmail' => 'emily@email.com', 'priority' => 'Low', 'status' => 'resolved', 'createdAt' => '2024-05-20 18:05:00'],
            ['id' => 'cust_6', 'category' => 'Scam', 'title' => 'Fake product listing', 'description' => 'Product never delivered after payment', 'reportedBy' => 'Alex K.', 'reportedEmail' => 'alex@email.com', 'priority' => 'High', 'status' => 'open', 'createdAt' => '2024-05-24 09:15:00'],
        ],
        'partnership_reports' => [
            ['id' => 'part_1', 'category' => 'Aggressive Behavior', 'title' => 'Verbal abuse towards staff', 'description' => 'Customer repeatedly used offensive language', 'reportedBy' => 'Golden Hotel', 'reportedEmail' => 'manager@golden.com', 'priority' => 'High', 'status' => 'open', 'createdAt' => '2024-05-20 10:30:00'],
            ['id' => 'part_2', 'category' => 'No-Show', 'title' => 'Repeated no-show appointments', 'description' => 'Customer booked 5 sessions and never showed', 'reportedBy' => 'Wellness Center', 'reportedEmail' => 'contact@wellness.com', 'priority' => 'Medium', 'status' => 'in_review', 'createdAt' => '2024-05-21 14:15:00'],
            ['id' => 'part_3', 'category' => 'Payment Dispute', 'title' => 'Fraudulent chargeback attempt', 'description' => 'Customer issued chargeback after service delivered', 'reportedBy' => 'Freelance Studio', 'reportedEmail' => 'billing@freelance.com', 'priority' => 'High', 'status' => 'open', 'createdAt' => '2024-05-22 09:45:00'],
            ['id' => 'part_4', 'category' => 'Harassment', 'title' => 'Customer harassment of staff', 'description' => 'Customer sent inappropriate messages', 'reportedBy' => 'Dream Escapes', 'reportedEmail' => 'support@dreamescapes.com', 'priority' => 'High', 'status' => 'in_review', 'createdAt' => '2024-05-23 11:20:00'],
            ['id' => 'part_5', 'category' => 'Fake Review', 'title' => 'Suspicious negative review pattern', 'description' => 'Multiple 1-star reviews from same IP', 'reportedBy' => 'CreativeHub', 'reportedEmail' => 'admin@creativehub.com', 'priority' => 'Medium', 'status' => 'open', 'createdAt' => '2024-05-23 16:00:00'],
        ],
        'system_reports' => [
            ['id' => 'sys_1', 'type' => 'API Timeout', 'title' => 'Payment gateway timeout', 'description' => 'POST /payments/charge: 504 error', 'severity' => 'High', 'timestamp' => '2025-03-22', 'status' => 'open'],
            ['id' => 'sys_2', 'type' => 'Crash', 'title' => 'React Native navigation crash', 'description' => 'Android deep link caused white screen', 'severity' => 'Medium', 'timestamp' => '2025-03-21', 'status' => 'open'],
            ['id' => 'sys_3', 'type' => 'Data Issue', 'title' => 'Partnership package not synced', 'description' => 'Some packages missing from search', 'severity' => 'Low', 'timestamp' => '2025-03-20', 'status' => 'open'],
        ],
        'approvals' => [
            ['id' => 'app_1', 'type' => 'Service Post', 'title' => 'Luxury beachfront wellness retreat', 'partner' => 'Dream Escapes', 'status' => 'pending', 'createdAt' => '2025-03-20', 'details' => 'All-inclusive yoga package'],
            ['id' => 'app_2', 'type' => 'Package', 'title' => 'Startup branding package', 'partner' => 'CreativeHub', 'status' => 'pending', 'createdAt' => '2025-03-21', 'details' => 'Logo + social media'],
            ['id' => 'app_3', 'type' => 'Review', 'title' => 'Review for Cozy Café', 'partner' => 'user_jamie', 'status' => 'pending', 'createdAt' => '2025-03-22', 'details' => '★★★★☆ - Amazing service'],
        ]
    ];
}

// Helper Functions
function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-pending">⏳ Pending</span>',
        'approved' => '<span class="badge badge-approved">✓ Approved</span>',
        'rejected' => '<span class="badge badge-rejected">✗ Rejected</span>',
        'open' => '<span class="badge badge-open">Open</span>',
        'in_review' => '<span class="badge badge-review">In Review</span>',
        'resolved' => '<span class="badge badge-resolved">Resolved</span>'
    ];
    return $badges[$status] ?? '<span class="badge">' . $status . '</span>';
}

function getPriorityBadge($priority) {
    $badges = [
        'High' => '<span class="badge badge-high">High</span>',
        'Medium' => '<span class="badge badge-medium">Medium</span>',
        'Low' => '<span class="badge badge-low">Low</span>'
    ];
    return $badges[$priority] ?? '<span class="badge">' . $priority . '</span>';
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $redirectPage = $_POST['redirect_page'] ?? 'dashboard';
    
    // Partner Application Actions
    if ($action === 'approve_application') {
        foreach ($_SESSION['heydream_data']['partner_applications'] as &$app) {
            if ($app['id'] === $id) {
                $app['status'] = 'approved';
                $app['reviewed_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
    } elseif ($action === 'reject_application') {
        foreach ($_SESSION['heydream_data']['partner_applications'] as &$app) {
            if ($app['id'] === $id) {
                $app['status'] = 'rejected';
                $app['reviewed_at'] = date('Y-m-d H:i:s');
                $app['rejection_reason'] = $_POST['rejection_reason'] ?? 'No reason provided';
                break;
            }
        }
    } elseif ($action === 'delete_application') {
        $_SESSION['heydream_data']['partner_applications'] = array_values(array_filter(
            $_SESSION['heydream_data']['partner_applications'], 
            fn($a) => $a['id'] !== $id
        ));
    }
    // Customer Report Actions
    elseif ($action === 'update_customer_status') {
        $newStatus = $_POST['new_status'] ?? '';
        foreach ($_SESSION['heydream_data']['customer_reports'] as &$report) {
            if ($report['id'] === $id) {
                $report['status'] = $newStatus;
                break;
            }
        }
    } elseif ($action === 'delete_customer') {
        $_SESSION['heydream_data']['customer_reports'] = array_values(array_filter(
            $_SESSION['heydream_data']['customer_reports'], 
            fn($r) => $r['id'] !== $id
        ));
    }
    // Partnership Report Actions
    elseif ($action === 'update_partner_status') {
        $newStatus = $_POST['new_status'] ?? '';
        foreach ($_SESSION['heydream_data']['partnership_reports'] as &$report) {
            if ($report['id'] === $id) {
                $report['status'] = $newStatus;
                break;
            }
        }
    } elseif ($action === 'delete_partner') {
        $_SESSION['heydream_data']['partnership_reports'] = array_values(array_filter(
            $_SESSION['heydream_data']['partnership_reports'], 
            fn($r) => $r['id'] !== $id
        ));
    }
    // System Report Actions
    elseif ($action === 'resolve_system') {
        foreach ($_SESSION['heydream_data']['system_reports'] as &$report) {
            if ($report['id'] === $id) {
                $report['status'] = 'resolved';
                break;
            }
        }
    } elseif ($action === 'archive_system') {
        $_SESSION['heydream_data']['system_reports'] = array_values(array_filter(
            $_SESSION['heydream_data']['system_reports'], 
            fn($r) => $r['id'] !== $id
        ));
    }
    // Approval Actions
    elseif ($action === 'approve_item') {
        foreach ($_SESSION['heydream_data']['approvals'] as &$item) {
            if ($item['id'] === $id) {
                $item['status'] = 'approved';
                break;
            }
        }
    } elseif ($action === 'reject_item') {
        $_SESSION['heydream_data']['approvals'] = array_values(array_filter(
            $_SESSION['heydream_data']['approvals'], 
            fn($a) => $a['id'] !== $id
        ));
    }
    
    header('Location: admin_dashboard.php?page=' . $redirectPage);
    exit;
}
?>
<?php
// db_config.php - Database Configuration

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'heydream_app');

function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        
        // Ensure bookings table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'bookings'");
        if ($checkTable->num_rows === 0) {
            $createBookingsTable = "
                CREATE TABLE bookings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    booking_id VARCHAR(100) NOT NULL UNIQUE,
                    package_title VARCHAR(255) NOT NULL,
                    destination VARCHAR(255) DEFAULT NULL,
                    travel_dates VARCHAR(255) DEFAULT NULL,
                    nights INT DEFAULT 0,
                    travelers INT DEFAULT 1,
                    status VARCHAR(50) DEFAULT 'Confirmed',
                    price VARCHAR(100) DEFAULT NULL,
                    lead_name VARCHAR(255) DEFAULT NULL,
                    lead_email VARCHAR(255) DEFAULT NULL,
                    lead_phone VARCHAR(100) DEFAULT NULL,
                    payment_method VARCHAR(100) DEFAULT NULL,
                    special_requests TEXT DEFAULT NULL,
                    package_type VARCHAR(255) DEFAULT NULL,
                    selected_tier VARCHAR(255) DEFAULT NULL,
                    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_booking_status (status),
                    INDEX idx_submitted_at (submitted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $conn->query($createBookingsTable);
        }
        
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateApplicationId() {
    return 'APP-' . date('Ymd') . '-' . rand(1000, 9999);
}

function logAdminAction($admin_id, $action, $details) {
    // Optional: Create admin_logs table to track actions
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

/**
 * Get notification counts for the admin dashboard
 */
function getNotificationCounts($conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeConn = true;
    }
    
    $counts = [
        'pending_applications' => 0,
        'customer_reports' => 0,
        'partnership_reports' => 0,
        'system_reports' => 0,
        'pending_approvals' => 0,
        'total' => 0
    ];
    
    try {
        // Check if tables exist first
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        // Partner applications
        if (in_array('partner_applications', $tables)) {
            $result = $conn->query("SELECT COUNT(*) as count FROM partner_applications WHERE status = 'pending'");
            if ($result) {
                $counts['pending_applications'] = (int)$result->fetch_assoc()['count'];
            }
        }
        
        // Customer reports
        if (in_array('customer_reports', $tables)) {
            $result = $conn->query("SELECT COUNT(*) as count FROM customer_reports WHERE status IN ('open', 'in_review')");
            if ($result) {
                $counts['customer_reports'] = (int)$result->fetch_assoc()['count'];
            }
        }
        
        // Partnership reports
        if (in_array('partnership_reports', $tables)) {
            $result = $conn->query("SELECT COUNT(*) as count FROM partnership_reports WHERE status IN ('open', 'in_review')");
            if ($result) {
                $counts['partnership_reports'] = (int)$result->fetch_assoc()['count'];
            }
        }
        
        // System reports
        if (in_array('system_reports', $tables)) {
            $result = $conn->query("SELECT COUNT(*) as count FROM system_reports WHERE status IN ('open', 'in_progress')");
            if ($result) {
                $counts['system_reports'] = (int)$result->fetch_assoc()['count'];
            }
        }
        
        // Pending approvals
        if (in_array('approvals', $tables)) {
            $result = $conn->query("SELECT COUNT(*) as count FROM approvals WHERE status = 'pending'");
            if ($result) {
                $counts['pending_approvals'] = (int)$result->fetch_assoc()['count'];
            }
        }
        
    } catch (Exception $e) {
        // Silently fail - just return 0 counts
    }
    
    $counts['total'] = $counts['pending_applications'] + 
                       $counts['customer_reports'] + 
                       $counts['partnership_reports'] + 
                       $counts['system_reports'] + 
                       $counts['pending_approvals'];
    
    if ($closeConn && $conn) {
        $conn->close();
    }
    
    return $counts;
}

/**
 * Get notification items with details
 */
function getNotificationItems($conn = null, $limit = 10) {
    $closeConn = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeConn = true;
    }
    
    $notifications = [];
    
    try {
        // Check if tables exist
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        // Pending applications
        if (in_array('partner_applications', $tables)) {
            $result = $conn->query("SELECT id, business_name as title, 'application' as type, submitted_at as created_at 
                                   FROM partner_applications WHERE status = 'pending' 
                                   ORDER BY submitted_at DESC LIMIT " . $limit);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['icon'] = 'fa-file-alt';
                    $row['color'] = '#3b82f6';
                    $row['link'] = '?page=dashboard';
                    $notifications[] = $row;
                }
            }
        }
        
        // Customer reports
        if (in_array('customer_reports', $tables)) {
            $result = $conn->query("SELECT id, title, 'customer_report' as type, created_at 
                                   FROM customer_reports WHERE status IN ('open', 'in_review') 
                                   ORDER BY created_at DESC LIMIT " . $limit);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['icon'] = 'fa-exclamation-triangle';
                    $row['color'] = '#ef4444';
                    $row['link'] = '?page=customer';
                    $notifications[] = $row;
                }
            }
        }
        
        // Partnership reports
        if (in_array('partnership_reports', $tables)) {
            $result = $conn->query("SELECT id, title, 'partnership_report' as type, created_at 
                                   FROM partnership_reports WHERE status IN ('open', 'in_review') 
                                   ORDER BY created_at DESC LIMIT " . $limit);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['icon'] = 'fa-handshake';
                    $row['color'] = '#f59e0b';
                    $row['link'] = '?page=partnership';
                    $notifications[] = $row;
                }
            }
        }
        
        // System reports
        if (in_array('system_reports', $tables)) {
            $result = $conn->query("SELECT id, title, 'system_report' as type, created_at 
                                   FROM system_reports WHERE status IN ('open', 'in_progress') 
                                   ORDER BY created_at DESC LIMIT " . $limit);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['icon'] = 'fa-server';
                    $row['color'] = '#8b5cf6';
                    $row['link'] = '?page=system';
                    $notifications[] = $row;
                }
            }
        }
        
        // Pending approvals
        if (in_array('approvals', $tables)) {
            $result = $conn->query("SELECT id, title, 'approval' as type, created_at 
                                   FROM approvals WHERE status = 'pending' 
                                   ORDER BY created_at DESC LIMIT " . $limit);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['icon'] = 'fa-check-double';
                    $row['color'] = '#22c55e';
                    $row['link'] = '?page=approval';
                    $notifications[] = $row;
                }
            }
        }
        
        // Sort by created_at descending
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Limit results
        $notifications = array_slice($notifications, 0, $limit);
        
    } catch (Exception $e) {
        // Silently fail
    }
    
    if ($closeConn && $conn) {
        $conn->close();
    }
    
    return $notifications;
}
?>
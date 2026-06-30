<?php
// File: admin/setup.php
// Use password_hash for super admin

require_once __DIR__ . '/../config/database.php';

echo "<h2>Complete Admin System Setup</h2>";

try {
    // 1. Create admin_users table if not exists
   // Update the allowed roles in admin_registration_requests table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_registration_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'editor', 'sales') DEFAULT 'admin',
        request_token VARCHAR(255) NOT NULL UNIQUE,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        processed_by INT NULL,
        rejection_reason TEXT NULL,
        INDEX idx_token (request_token),
        INDEX idx_status (status)


        
    )
");
    echo "✅ admin_users table created!<br>";
    
    // 2. Create admin_registration_requests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_registration_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            full_name VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'editor') DEFAULT 'admin',
            request_token VARCHAR(255) NOT NULL UNIQUE,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            processed_by INT NULL,
            rejection_reason TEXT NULL,
            INDEX idx_token (request_token),
            INDEX idx_status (status)
        )
    ");
    echo "✅ admin_registration_requests table created!<br>";
    
// After creating admin_registration_requests table, add this:

// Update admin_users table to support sales role
try {
    $pdo->exec("ALTER TABLE admin_users MODIFY COLUMN role ENUM('super_admin', 'admin', 'editor', 'sales') DEFAULT 'admin'");
    echo "✅ admin_users table updated to support sales role<br>";
} catch (PDOException $e) {
    echo "Note: " . $e->getMessage() . "<br>";
}


// Update admin_users table to support sales role
try {
    $pdo->exec("ALTER TABLE admin_users MODIFY COLUMN role ENUM('super_admin', 'admin', 'editor', 'sales') DEFAULT 'admin'");
    echo "✅ admin_users table updated to support sales role<br>";
} catch (PDOException $e) {
    // Column might already exist
    echo "Note: " . $e->getMessage() . "<br>";
}

// Update admin_registration_requests table to support sales role
try {
    $pdo->exec("ALTER TABLE admin_registration_requests MODIFY COLUMN role ENUM('admin', 'editor', 'sales') DEFAULT 'admin'");
    echo "✅ admin_registration_requests table updated to support sales role<br>";
} catch (PDOException $e) {
    echo "Note: " . $e->getMessage() . "<br>";
}

    // 3. Generate HASHED password
    $password = 'SuperAdmin123!';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Password hash created (secure):</strong><br>";
    echo "<code style='word-break: break-all;'>" . $hash . "</code>";
    echo "</div>";
    
    // 4. Check if super admin exists and update/insert with hashed password
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = 'superadmin' OR email = 'admin@heydream.com'");
    $stmt->execute();
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing admin to super admin with hashed password
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, role = 'super_admin', is_active = 1, approved = 1, full_name = 'Super Administrator' WHERE id = ?");
        $stmt->execute([$hash, $existing['id']]);
        echo "✅ Super Admin updated with hashed password!<br>";
    } else {
        // Insert new super admin with hashed password
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (username, email, password, full_name, role, is_active, approved) 
            VALUES (?, ?, ?, ?, 'super_admin', 1, 1)
        ");
        $stmt->execute(['superadmin', 'admin@heydream.com', $hash, 'Super Administrator']);
        echo "✅ Super Admin created successfully!<br>";
    }
    
    echo "<hr>";
    echo "<h3>Setup Complete!</h3>";
    echo "<div style='background: #e8f0fe; padding: 15px; border-radius: 8px; margin: 15px 0;'>";
    echo "<strong>Login Credentials:</strong><br>";
    echo "Username: <code>superadmin</code><br>";
    echo "Password: <code>SuperAdmin123!</code><br>";
    echo "Email: <code>admin@heydream.com</code><br>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='login.php' style='display: inline-block; background: #003580; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go to Admin Login →</a>";
    echo "</div>";
    echo "<br>";
    echo "<small style='color: #999;'>⚠️ For security, please delete this file after setup.</small>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border-radius: 8px;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
}
?>

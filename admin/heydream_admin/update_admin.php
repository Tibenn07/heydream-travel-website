<?php
// update_admin.php - Run this once to update admin credentials
require_once 'db_config.php';

$username = 'superadmin';
$password = 'heydream12345';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$email = 'admin@heydream.com';
$full_name = 'Super Admin';

$conn = getDBConnection();

// Check if admin exists
$check = $conn->query("SELECT id FROM admin_users WHERE username = 'admin' OR username = 'superadmin'");

if ($check->num_rows > 0) {
    // Update existing
    $stmt = $conn->prepare("UPDATE admin_users SET username = ?, password = ?, full_name = ? WHERE username = 'admin' OR username = 'superadmin'");
    $stmt->bind_param("sss", $username, $hashedPassword, $full_name);
    $stmt->execute();
    echo "✅ Admin credentials updated successfully!<br>";
} else {
    // Insert new
    $stmt = $conn->prepare("INSERT INTO admin_users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, 'super_admin')");
    $stmt->bind_param("ssss", $username, $hashedPassword, $email, $full_name);
    $stmt->execute();
    echo "✅ Admin user created successfully!<br>";
}

echo "<hr>";
echo "<strong>New Login Credentials:</strong><br>";
echo "🔹 Username: <strong style='color:blue'>superadmin</strong><br>";
echo "🔹 Password: <strong style='color:green'>heydream12345</strong><br>";
echo "<br><a href='admin_login.php'>Go to Login Page →</a>";

$conn->close();
?>
<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("ALTER TABLE admin_users ADD COLUMN email VARCHAR(255)");
} catch (Exception $e) {
}
try {
    $pdo->exec("ALTER TABLE admin_users ADD COLUMN reset_token VARCHAR(255)");
} catch (Exception $e) {
}
try {
    $pdo->exec("ALTER TABLE admin_users ADD COLUMN token_expiry DATETIME");
} catch (Exception $e) {
}
echo "Done migration";

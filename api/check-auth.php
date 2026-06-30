<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    echo json_encode([
        'logged_in' => true,
        'user' => [
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'initials' => strtoupper(substr($user['full_name'], 0, 2)),
            'profile_pic' => $user['profile_pic'] ?? null
        ]
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>

<?php
require_once 'config/database.php';

try {
    // Create booking_documents table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS booking_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_number VARCHAR(50) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (booking_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'booking_documents' created successfully.\n";

    // Create uploads/booking_docs directory
    $upload_dir = 'uploads/booking_docs/';
    if (!file_exists($upload_dir)) {
        if (mkdir($upload_dir, 0777, true)) {
            echo "Directory 'uploads/booking_docs/' created successfully.\n";
        } else {
            echo "Failed to create directory 'uploads/booking_docs/'.\n";
        }
    } else {
        echo "Directory 'uploads/booking_docs/' already exists.\n";
    }

} catch (PDOException $e) {
    die("Error during schema update: " . $e->getMessage());
}
?>

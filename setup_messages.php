<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'heydream_travel');

$db->query("CREATE TABLE IF NOT EXISTS `customer_conversations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `message_type` ENUM('Tour Package Inquiry', 'Flight Booking', 'Visa Assistance', 'General Chat') DEFAULT 'General Chat',
    `status` ENUM('Active', 'Archived', 'Resolved') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$db->query("CREATE TABLE IF NOT EXISTS `customer_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_type` ENUM('Customer', 'Admin', 'Staff') NOT NULL,
    `sender_name` VARCHAR(255),
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`conversation_id`) REFERENCES `customer_conversations`(`id`) ON DELETE CASCADE
)");
echo "Done";

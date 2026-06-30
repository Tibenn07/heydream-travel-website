<?php
require_once __DIR__ . '/config/database.php';

echo "Starting migration for bookings table...\n";

try {
    // Check if columns exist first
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'payment_processed'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN payment_processed BOOLEAN DEFAULT FALSE AFTER payment_reference");
        echo "Column 'payment_processed' added.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'travel_documents'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN travel_documents BOOLEAN DEFAULT FALSE AFTER payment_processed");
        echo "Column 'travel_documents' added.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'ready_for_travel'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN ready_for_travel BOOLEAN DEFAULT FALSE AFTER travel_documents");
        echo "Column 'ready_for_travel' added.\n";
    }

    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

<?php
// File: debug_booking.php
// Run this to test your booking system

require_once __DIR__ . '/config/database.php';

echo "<h2>Booking System Test</h2>";

// Check if bookings table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Bookings table exists<br><br>";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE bookings");
        $columns = $stmt->fetchAll();
        echo "<h3>Bookings Table Columns:</h3>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><strong>{$col['Field']}</strong> - {$col['Type']}</li>";
        }
        echo "</ul>";
        
        // Count existing bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
        $count = $stmt->fetchColumn();
        echo "<p><strong>Total bookings in database: $count</strong></p>";
        
    } else {
        echo "❌ Bookings table does NOT exist!<br>";
        echo "<a href='create_bookings_table.php'>Click here to create the bookings table</a>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test a manual insert
echo "<h3>Testing Manual Insert:</h3>";
try {
    $test_booking = 'TEST-' . date('YmdHis');
    
    $stmt = $pdo->prepare("
        INSERT INTO bookings (
            booking_number, 
            destination_name, 
            package_name, 
            full_name, 
            email, 
            phone, 
            travel_date, 
            number_of_travelers, 
            total_amount, 
            booking_status, 
            payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $test_booking,
        'Test Destination',
        'Test Package',
        'Test User',
        'test@email.com',
        '09123456789',
        date('Y-m-d', strtotime('+7 days')),
        2,
        5000,
        'pending',
        'unpaid'
    ]);
    
    if ($result) {
        echo "✅ Manual insert SUCCESSFUL!<br>";
        echo "Booking Number: $test_booking<br>";
        
        // Verify it was inserted
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_number = ?");
        $stmt->execute([$test_booking]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            echo "✅ Verified: Booking found in database!<br>";
            echo "<pre>";
            print_r($booking);
            echo "</pre>";
        }
    } else {
        echo "❌ Manual insert FAILED<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Go to your local-destination.php page</li>";
echo "<li>Try to book a tour</li>";
echo "<li>Come back and refresh this page to see if the booking appears</li>";
echo "</ol>";
echo "<br><a href='local-destination.php'>Go to Local Destinations</a> | ";
echo "<a href='foreign-destinations.php'>Go to Foreign Destinations</a> | ";
echo "<a href='flash-deals.php'>Go to Flash Deals</a>";
?>

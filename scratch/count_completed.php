<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=heydream_travel', 'root', '');
    $sql = "SELECT COUNT(*) FROM bookings WHERE 
            LOWER(booking_status) = 'completed' AND 
            LOWER(payment_status) = 'paid' AND 
            travel_documents = 1 AND 
            ready_for_travel = 1 AND 
            (UPPER(visa_status) = 'APPROVED' OR UPPER(visa_status) = 'N/A' OR destination_name != 'Visa Assistance')";
    $count = $pdo->query($sql)->fetchColumn();
    echo "Count: $count";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

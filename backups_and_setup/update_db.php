<?php
require_once __DIR__ . '/config/database.php';
try {
  $pdo->exec('ALTER TABLE bookings ADD COLUMN payment_reference VARCHAR(100) AFTER payment_method');
  echo "payment_reference added to bookings\n";
} catch(Exception $e) {
  echo "bookings: " . $e->getMessage() . "\n";
}

try {
  $pdo->exec('ALTER TABLE flash_deal_bookings ADD COLUMN payment_reference VARCHAR(100) AFTER payment_method');
  echo "payment_reference added to flash_deal_bookings\n";
} catch(Exception $e) {
  echo "flash: " . $e->getMessage() . "\n";
}

try {
  $pdo->exec('ALTER TABLE local_bookings ADD COLUMN payment_reference VARCHAR(100) AFTER payment_method');
  echo "payment_reference added to local_bookings\n";
} catch(Exception $e) {
  echo "local: " . $e->getMessage() . "\n";
}

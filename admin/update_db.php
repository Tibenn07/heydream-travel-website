<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("UPDATE visas SET category = 'Asia' WHERE category = 'South East Asia' OR category = 'Asia'");
    $pdo->exec("UPDATE visas SET category = 'Europe' WHERE title LIKE '%Schengen%' OR category LIKE '%Europe%'");
    $pdo->exec("UPDATE visas SET category = 'North America' WHERE title LIKE '%US Visa%' OR title LIKE '%United States%'");
    echo "Database successfully updated for Continents migration.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

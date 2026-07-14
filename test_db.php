<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo->exec("ALTER TABLE visas ADD COLUMN partner_id INT NULL");
    echo "Added partner_id.\n";
} catch (PDOException $e) { 
    echo "partner_id exists or error: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("ALTER TABLE visas ADD COLUMN partner_company VARCHAR(255) NULL");
    echo "Added partner_company.\n";
} catch (PDOException $e) { 
    echo "partner_company exists or error: " . $e->getMessage() . "\n";
}

try {
    $sql = "SELECT v.*, COALESCE(pr.business_display_name, p.company_name, v.partner_company) AS partner_company
            FROM visas v
            LEFT JOIN partner_applications p ON v.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = v.partner_id
            WHERE v.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => 9]);
    $visa = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($visa);
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

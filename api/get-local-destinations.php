<?php
// File: api/get-local-destinations.php
// Get a single local destination by ID

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$identifier = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($identifier) ? intval($identifier) : 0;
$name = !is_numeric($identifier) ? $identifier : '';

// Strip 'local_' prefix if present (added by frontend JS)
if (strpos($name, 'local_') === 0) {
    $name = substr($name, 6);
}

if (!$id && !$name) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID or Name']);
    exit;
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT d.*, COALESCE(pr.business_display_name, p.company_name, d.partner_company) AS partner_company
            FROM destinations d
            LEFT JOIN partner_applications p ON d.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = d.partner_id
            WHERE d.id = :id AND d.type = 'local'
        ");
        $stmt->execute(['id' => $id]);
    } else {
        // More robust lookup: handle direct name match OR underscore-slug match
        $stmt = $pdo->prepare("
            SELECT d.*, COALESCE(pr.business_display_name, p.company_name, d.partner_company) AS partner_company
            FROM destinations d
            LEFT JOIN partner_applications p ON d.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = d.partner_id
            WHERE (d.name = :name OR REPLACE(LOWER(d.name), ' ', '_') = :name OR d.name LIKE :name_like OR :name LIKE CONCAT('%', d.name, '%'))
            AND d.type = 'local'
            LIMIT 1
        ");
        $stmt->execute([
            'name' => $name,
            'name_like' => '%' . $name . '%'
        ]);
    }
    $dest = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dest) {
        // Format for consistent JS consumption
        $dest['price'] = floatval($dest['price']);
        
        // Return success
        echo json_encode([
            'success' => true,
            'destination' => $dest
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Destination not found', 'deleted' => true]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

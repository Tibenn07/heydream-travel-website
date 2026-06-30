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
            SELECT * FROM destinations 
            WHERE id = :id AND type = 'local' AND is_active = 1
        ");
        $stmt->execute(['id' => $id]);
    } else {
        // More robust lookup: handle direct name match OR underscore-slug match
        $stmt = $pdo->prepare("
            SELECT * FROM destinations 
            WHERE (name = :name OR REPLACE(LOWER(name), ' ', '_') = :name OR name LIKE :name_like) 
            AND type = 'local' AND is_active = 1
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
        echo json_encode(['success' => false, 'error' => 'Destination not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT COUNT(*) FROM cruises");
echo "Cruises count: " . $stmt->fetchColumn() . "\n";
$stmt = $pdo->query("SELECT * FROM cruises");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $row['title'] . " (Published: " . $row['is_published'] . ")\n";
}
?>

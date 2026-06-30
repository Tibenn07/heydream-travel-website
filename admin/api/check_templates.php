<?php
require_once __DIR__ . '/../../config/database.php';
$stmt = $pdo->query("SELECT body FROM marketing_templates ORDER BY id DESC LIMIT 10");
while($row = $stmt->fetch()) {
    echo "TEMPLATE BODY:\n";
    echo $row['body'] . "\n\n";
}
?>

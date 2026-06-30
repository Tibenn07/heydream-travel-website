<?php

$dir = __DIR__ . '/../';

$files = [
    'index.php',
    'local-destination.php',
    'foreign-destinations.php',
    'flash-deals.php',
    'saved.php',
    'buttons/flights.php',
    'buttons/hotel.php',
    'buttons/visa.php',
    'buttons/cruises.php',
    'buttons/experiences.php',
    'buttons/insurance.php',
    'User Account/profile.php',
    'User Account/my-profile.php',
    'User Account/login.php',
    'User Account/register.php',
    'User Account/forgot-password.php',
    'User Account/change-password.php',
    'User Account/reset-password.php'
];

foreach ($files as $file) {
    $path = $dir . $file;
    if (!file_exists($path)) {
        echo "File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($path);
    
    // Check if already mapped
    if (strpos($content, 'chatbot_widget.php') !== false) {
        echo "Already added in: $file\n";
        continue;
    }
    
    // Determine depth
    $dirCount = substr_count($file, '/');
    if ($dirCount == 0) {
        $include = "    <?php include_once 'chatbot_widget.php'; ?>\n";
    } else {
        $up = str_repeat('../', $dirCount);
        $include = "    <?php include_once __DIR__ . '/" . $up . "chatbot_widget.php'; ?>\n";
    }
    
    // Add right before </body>
    if (strpos($content, '</body>') !== false) {
        $newContent = str_replace('</body>', $include . '</body>', $content);
        file_put_contents($path, $newContent);
        echo "Updated $file\n";
    } else {
        echo "No </body> found in: $file\n";
    }
}
?>

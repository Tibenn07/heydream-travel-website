<?php
// Script to replace old business and GCash phone numbers
$dir = dirname(__DIR__); // Root folder

$search_replace = [
    '0917 722 0904' => '0945 776 4140',
    '09177220904' => '09457764140',
    '0917-722-0904' => '0945-776-4140',
    '0917-XXX-XXXX' => '0945-XXX-XXXX',
    '+639177220904' => '+639457764140',
    '+63 917 722 0904' => '+63 945 776 4140',
];

function replaceInFile($filePath, $replacements) {
    $content = file_get_contents($filePath);
    $changed = false;
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            $content = str_replace($old, $new, $content);
            $changed = true;
        }
    }
    if ($changed) {
        file_put_contents($filePath, $content);
        echo "Updated: $filePath\n";
    }
}

function scanDirRecursive($dirPath, $replacements) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filePath = $file->getRealPath();
            
            // Skip non-code files, system folders, or backup/scratch folders
            if (strpos($filePath, '.git') !== false ||
                strpos($filePath, '.gemini') !== false ||
                strpos($filePath, 'scratch') !== false ||
                strpos($filePath, 'node_modules') !== false) {
                continue;
            }
            
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            if (in_array($ext, ['php', 'js', 'css', 'html', 'json'])) {
                replaceInFile($filePath, $replacements);
            }
        }
    }
}

echo "Starting search and replace...\n";
scanDirRecursive($dir, $search_replace);
echo "Search and replace finished.\n";
?>

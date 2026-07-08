<?php
$file = file_get_contents(__DIR__ . '/../admin/Partnership/partner-content-manager.php');
$lines = explode("\n", $file);

// Count all save handlers
echo "=== All save handlers ===\n";
foreach ($lines as $i => $line) {
    if (preg_match("/action\s*===\s*'(save_\w+)'/", $line, $m)) {
        echo "Line " . ($i+1) . ": " . $m[1] . "\n";
    }
}

echo "\n";

// For each INSERT/UPDATE, count ? marks and verify
$inQuery = false;
$queryStart = 0;
$queryBuffer = '';
$queryType = '';

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    // Detect INSERT or UPDATE prepare statements  
    if (preg_match('/prepare\("(INSERT|UPDATE)/', $line, $m)) {
        $inQuery = true;
        $queryStart = $i + 1;
        $queryBuffer = $line;
        $queryType = $m[1];
        continue;
    }
    
    if ($inQuery) {
        $queryBuffer .= $line;
        // Check if query ends (closing paren + semicolon)
        if (preg_match('/"\);\s*$/', trim($line))) {
            $qmarks = substr_count($queryBuffer, '?');
            echo "Line $queryStart: $queryType - $qmarks placeholders\n";
            
            // Now count execute values
            $inQuery = false;
            $queryBuffer = '';
            
            // Find the execute call
            for ($j = $i + 1; $j < min($i + 50, count($lines)); $j++) {
                if (strpos($lines[$j], '->execute([') !== false) {
                    $valCount = 0;
                    for ($k = $j + 1; $k < min($j + 50, count($lines)); $k++) {
                        $vline = trim($lines[$k]);
                        if ($vline === ']);') {
                            echo "  Values: $valCount\n";
                            if ($qmarks !== $valCount) {
                                echo "  *** MISMATCH! $qmarks placeholders vs $valCount values ***\n";
                            }
                            break;
                        }
                        if (!empty($vline) && $vline !== ']);') {
                            $valCount++;
                        }
                    }
                    break;
                }
            }
        }
    }
}

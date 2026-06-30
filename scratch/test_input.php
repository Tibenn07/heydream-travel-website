<?php
echo "Arg 1: " . ($argv[1] ?? 'NOT SET') . "\n";
$decoded = json_decode($argv[1] ?? '', true);
var_dump($decoded);
?>

<?php
$apiKey = 'AIzaSyBsAGNKK_6IzVXIMUMRcmCNGg0Tgvwenz4';
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

$systemPrompt = "You are a helpful travel assistant.";
$contents = [
    ['role' => 'user', 'parts' => [['text' => 'What packages do you have for Japan?']]]
];

$generationConfig = [
    'temperature'     => 0.8,
    'maxOutputTokens' => 1500,
    'topP'            => 0.9,
];

// Test 1: Simple payload
echo "Testing simple payload with gemini-2.5-flash...\n";
$body = json_encode([
    'contents'           => $contents,
    'generationConfig'   => $generationConfig
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "\n\n";

// Test 2: Full payload with system instruction
echo "Testing payload with system_instruction and thinkingConfig...\n";
$generationConfig['thinkingConfig'] = ['thinkingBudget' => 2048];
$body = json_encode([
    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
    'contents'           => $contents,
    'generationConfig'   => $generationConfig
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "\n\n";
?>

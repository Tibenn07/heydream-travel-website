<?php
require_once __DIR__ . '/../config/ai_config.php';

echo "Using API URL: " . GEMINI_API_URL . "\n";
echo "Using API Key: " . GEMINI_API_KEY . "\n";

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Hello, respond with a short test sentence.']
            ]
        ]
    ]
];

$ch = curl_init(GEMINI_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response:\n";
    echo $response . "\n";
}
?>

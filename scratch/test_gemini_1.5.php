<?php
$apiKey = 'AIzaSyBsAGNKK_6IzVXIMUMRcmCNGg0Tgvwenz4';
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;

echo "Using API URL: " . $apiUrl . "\n";

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Hello, respond with a short test sentence.']
            ]
        ]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

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

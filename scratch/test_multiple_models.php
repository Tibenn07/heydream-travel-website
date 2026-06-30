<?php
$apiKey = 'AIzaSyBsAGNKK_6IzVXIMUMRcmCNGg0Tgvwenz4';

$models = [
    'gemini-2.0-flash-lite',
    'gemini-2.0-flash',
    'gemini-2.5-flash',
    'gemini-2.5-flash-lite',
    'gemini-flash-lite-latest',
];

foreach ($models as $m) {
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $m . ':generateContent?key=' . $apiKey;
    echo "Testing model: $m...\n";

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
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    $decoded = json_decode($response, true);
    if ($httpCode === 200) {
        echo "Success!\n";
        echo "Reply: " . ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No text') . "\n\n";
    } else {
        echo "Error message: " . ($decoded['error']['message'] ?? 'Unknown error') . "\n\n";
    }
}
?>

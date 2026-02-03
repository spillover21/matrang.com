<?php
// Временный файл для тестирования на Hostinger
// Загрузить в e:\pitbull\public_html\test_debug.php

header('Content-Type: application/json');

$ch = curl_init('http://72.62.114.139:8080/debug_envelope.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: matrang_secret_key_2026'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    'http_code' => $httpCode,
    'response' => json_decode($response, true)
], JSON_PRETTY_PRINT);

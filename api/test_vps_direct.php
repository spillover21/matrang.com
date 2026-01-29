<?php
/**
 * Прямая проверка доступности VPS
 */

header('Content-Type: application/json');

// Тест 1: Просто HTTP запрос к VPS
$ch = curl_init('http://72.62.114.139/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_VERBOSE => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo json_encode([
    'test' => 'VPS root',
    'http_code' => $httpCode,
    'error' => $error,
    'response_length' => strlen($response),
    'response_preview' => substr($response, 0, 200)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

<?php
/**
 * Скрипт для настройки вебхука Documenso
 * Вызывается вручную администратором для настройки вебхуков
 */

header('Content-Type: application/json');

$config = require __DIR__ . '/documenso_config.php';

$webhookUrl = "https://matrang.com/api/webhook_documenso.php";
$webhookSecret = $config['WEBHOOK_SECRET'];

// Пробуем разные варианты API
$attempts = [];

// Вариант 1: Стандартный Documenso API
$ch = curl_init($config['API_URL'] . '/webhooks');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: ' . $config['API_KEY']
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'webhookUrl' => $webhookUrl,
        'eventTriggers' => ['DOCUMENT_COMPLETED', 'DOCUMENT_REJECTED'],
        'secret' => $webhookSecret,
        'enabled' => true
    ])
]);

$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$attempts[] = [
    'method' => 'Documenso API /webhooks',
    'url' => $config['API_URL'] . '/webhooks',
    'httpCode' => $httpCode1,
    'response' => $response1
];

// Вариант 2: Через settings
$ch = curl_init($config['API_URL'] . '/settings/webhooks');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: ' . $config['API_KEY']
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'url' => $webhookUrl,
        'events' => ['document.completed', 'document.rejected'],
        'secret' => $webhookSecret
    ])
]);

$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$attempts[] = [
    'method' => 'Documenso API /settings/webhooks',
    'url' => $config['API_URL'] . '/settings/webhooks',
    'httpCode' => $httpCode2,
    'response' => $response2
];

// Вариант 3: Через Bridge API
$ch = curl_init('http://72.62.114.139:8080/configure_webhook');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: matrang_secret_key_2026'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'webhook_url' => $webhookUrl,
        'webhook_secret' => $webhookSecret,
        'events' => ['DOCUMENT_COMPLETED', 'DOCUMENT_REJECTED']
    ])
]);

$response3 = curl_exec($ch);
$httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$attempts[] = [
    'method' => 'Bridge API /configure_webhook',
    'url' => 'http://72.62.114.139:8080/configure_webhook',
    'httpCode' => $httpCode3,
    'response' => $response3
];

echo json_encode([
    'success' => false,
    'message' => 'Автоматическая настройка не удалась. Настройте вебхук вручную через веб-интерфейс Documenso',
    'webhook_url' => $webhookUrl,
    'webhook_secret' => $webhookSecret,
    'events' => ['DOCUMENT_COMPLETED', 'DOCUMENT_REJECTED'],
    'attempts' => $attempts,
    'manual_instructions' => [
        '1. Откройте http://72.62.114.139:9000/settings/webhooks',
        '2. Нажмите "Add Webhook"',
        '3. Введите URL: ' . $webhookUrl,
        '4. Введите Secret: ' . $webhookSecret,
        '5. Выберите события: DOCUMENT_COMPLETED, DOCUMENT_REJECTED',
        '6. Сохраните'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

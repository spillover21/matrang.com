<?php
// Тестовый вебхук БЕЗ проверки подписи для отладки
header('Content-Type: application/json');

$logFile = __DIR__ . '/../webhook_test.log';
$timestamp = date('Y-m-d H:i:s');

// Логируем все данные запроса
$logData = [
    'timestamp' => $timestamp,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'raw_body' => file_get_contents('php://input'),
    'server' => [
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    ]
];

// Записываем в лог
file_put_contents(
    $logFile, 
    "\n" . str_repeat("=", 80) . "\n" . 
    json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . 
    "\n",
    FILE_APPEND
);

// Отправляем успешный ответ
http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Webhook received and logged',
    'timestamp' => $timestamp
]);

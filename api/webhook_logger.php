<?php
// webhook_logger.php - Добавляем логирование в webhook_documenso.php

// Функция для логирования
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/../data/webhook_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }
    
    $logMessage .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Логируем начало вызова
logWebhook("WEBHOOK CALLED", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    'HEADERS' => getallheaders(),
    'RAW_BODY' => file_get_contents('php://input')
]);

// Подключаем основной обработчик
require_once __DIR__ . '/webhook_documenso.php';

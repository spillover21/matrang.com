<?php
/**
 * Тест bridge API клиента
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/DocumensoBridgeClient.php';

header('Content-Type: application/json');

try {
    $bridge = new DocumensoBridgeClient();
    
    // Тест подключения
    $testResult = $bridge->test();
    
    // Получить список документов
    $envelopes = $bridge->getEnvelopes();
    
    echo json_encode([
        'success' => true,
        'bridge_test' => $testResult,
        'envelopes' => $envelopes
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

<?php
/**
 * API для создания договоров из админки
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/ContractService.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Проверяем обязательные поля
    $required = ['buyerEmail', 'buyerName'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field {$field} is required");
        }
    }
    
    // Создаем сервис и отправляем договор
    $service = new ContractService();
    $result = $service->createAndSendContract($data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Договор создан и отправлен на подпись',
        'envelope_id' => $result['envelope_id'],
        'signing_url' => $result['signing_url'],
        'recipient_id' => $result['recipient_id']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * API для создания договоров из админки
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Debug log to confirm execution start
function debug_log($msg) {
    file_put_contents('/tmp/debug_contracts.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

debug_log("Script started");

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $rawInput = file_get_contents('php://input');
    debug_log("Raw input: " . $rawInput);
    
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        debug_log("JSON decode failed: " . json_last_error_msg());
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
    debug_log("Requiring ContractService...");
    require_once __DIR__ . '/ContractService.php';
    
    debug_log("Creating service instance...");
    $service = new ContractService();
    
    debug_log("Calling createAndSendContract...");
    $result = $service->createAndSendContract($data);
    
    debug_log("Result: " . print_r($result, true));
    
    // Сохраняем договор в локальную базу
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $existingData = ['contracts' => [], 'templates' => []];
    
    if (file_exists($contractsFile)) {
        $fileContent = file_get_contents($contractsFile);
        $existingData = json_decode($fileContent, true);
        
        // Поддержка старого формата
        if (isset($existingData[0])) {
            $existingData = ['contracts' => $existingData, 'templates' => []];
        }
    }
    
    // Создаем запись договора
    $contractRecord = [
        'id' => 'contract-' . time() . '-' . rand(1000, 9999),
        'contractNumber' => 'DOG-' . date('Y') . '-' . str_pad(count($existingData['contracts']) + 1, 4, '0', STR_PAD_LEFT),
        'createdAt' => date('c'),
        'sentAt' => date('c'),
        'status' => 'sent',
        'data' => $data,
        'adobeSignAgreementId' => $result['envelope_id'] ?? null,
        'buyerSigningUrl' => $result['signing_url'] ?? null,
        'sellerSigningUrl' => $result['seller_signing_url'] ?? null,
    ];
    
    $existingData['contracts'][] = $contractRecord;
    
    file_put_contents($contractsFile, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    debug_log("Contract saved to database: " . $contractRecord['id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Договор создан и отправлен на подпись',
        'envelope_id' => $result['envelope_id'],
        'signing_url' => $result['signing_url'],
        'recipient_id' => $result['recipient_id'],
        'contract_id' => $contractRecord['id']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    debug_log("Exception: " . $e->getMessage());
    debug_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    debug_log("Fatal: " . $e->getMessage());
    debug_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
}


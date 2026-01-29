<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== TEST contracts_api.php DEBUG ===\n\n";

echo "1. Checking ContractService.php... ";
if (file_exists(__DIR__ . '/ContractService.php')) {
    echo "OK\n";
    require_once __DIR__ . '/ContractService.php';
    echo "   Loaded successfully\n";
} else {
    echo "FAIL - file not found\n";
    exit(1);
}

echo "\n2. Checking DocumensoBridgeClient.php... ";
if (file_exists(__DIR__ . '/DocumensoBridgeClient.php')) {
    echo "OK\n";
} else {
    echo "FAIL - file not found\n";
    exit(1);
}

echo "\n3. Creating ContractService instance... ";
try {
    $service = new ContractService();
    echo "OK\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n4. Testing Bridge API connection... ";
try {
    $testData = [
        'buyerEmail' => 'test@example.com',
        'buyerName' => 'Test User',
        'dogName' => 'Test Dog',
        'kennelName' => 'Test Kennel',
        'kennelOwner' => 'Test Owner',
        'contractDate' => date('Y-m-d'),
        'contractPlace' => 'Test Place'
    ];
    
    echo "\n   Sending test data to VPS...\n";
    $result = $service->createAndSendContract($testData);
    
    echo "SUCCESS!\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "FAIL\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

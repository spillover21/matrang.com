<?php
require_once '/var/www/html/api/ContractService.php';

$data = [
    "buyerEmail" => "test_buyer@example.com",
    "buyerName" => "Test Buyer",
    "contractNumber" => "TEST-001",
    "price" => "1000",
    "dogName" => "TestDog"
];

try {
    echo "Starting test...\n";
    $service = new ContractService();
    echo "Service created. Calling createAndSendContract...\n";
    $result = $service->createAndSendContract($data);
    print_r($result);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

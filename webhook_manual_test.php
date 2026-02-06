<?php
// webhook_manual_test.php - Ручной тест webhook
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

echo "=== WEBHOOK MANUAL TEST ===\n\n";

// Подключаем необходимые файлы
require_once __DIR__ . '/api/DocumensoService.php';
require_once __DIR__ . '/api/vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Проверяем DocumensoService  
echo "1. Checking DocumensoService...\n";
try {
    $service = new DocumensoService();
    echo "   ✅ DocumensoService created\n";
    
    // Проверяем метод downloadDocument
    if (method_exists($service, 'downloadDocument')) {
        echo "   ✅ Method downloadDocument() EXISTS!\n";
    } else {
        echo "   ❌ Method downloadDocument() NOT FOUND!\n";
        echo "   Available methods:\n";
        $methods = get_class_methods($service);
        foreach ($methods as $method) {
            echo "      - $method\n";
        }
        die("\n❌ CRITICAL ERROR: downloadDocument() method missing!\n");
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    die();
}

// Симуляция webhook данных
echo "\n2. Simulating webhook payload...\n";
$testData = [
    'id' => 148,
    'recipients' => [
        ['email' => 'kharynadenis@gmail.com', 'name' => 'Test Buyer'],
        ['email' => 'noreply@matrang.com', 'name' => 'Kennel']
    ]
];

$documentId = $testData['id'];
echo "   Document ID: $documentId\n";

// Ищем email покупателя
echo "\n3. Finding buyer email...\n";
$buyerEmail = null;
foreach ($testData['recipients'] as $recipient) {
    if ($recipient['email'] !== 'noreply@matrang.com') {
        $buyerEmail = $recipient['email'];
        echo "   ✅ Found: $buyerEmail\n";
        break;
    }
}

if (!$buyerEmail) {
    die("   ❌ Buyer email not found!\n");
}

// Тест загрузки PDF  
echo "\n4. Testing PDF download...\n";
$uploadDir = __DIR__ . '/uploads/contracts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "   Created directory: $uploadDir\n";
}

$filename = "test_contract_{$documentId}.pdf";
$savePath = $uploadDir . $filename;

try {
    echo "   Calling downloadDocument($documentId, $savePath)...\n";
    $result = $service->downloadDocument($documentId, $savePath);
    
    if (file_exists($savePath)) {
        $size = filesize($savePath);
        echo "   ✅ PDF downloaded! Size: $size bytes\n";
        echo "   Location: $savePath\n";
    } else {
        echo "   ❌ File not found after download!\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Download failed: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n5. Testing contracts.json update...\n";
$contractsFile = __DIR__ . '/data/contracts.json';
if (file_exists($contractsFile)) {
    $data = json_decode(file_get_contents($contractsFile), true);
    $count = count($data['contracts'] ?? []);
    echo "   Contracts in database: $count\n";
    
    // Ищем договор с этим email
    $found = false;
    foreach (($data['contracts'] ?? []) as $contract) {
        if (($contract['data']['buyerEmail'] ?? '') === $buyerEmail) {
            echo "   ✅ Found contract: " . $contract['contractNumber'] . "\n";
            echo "      Status: " . $contract['status'] . "\n";
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "   ⚠️ No contract found with email $buyerEmail\n";
    }
} else {
    echo "   ❌ contracts.json not found!\n";
}

echo "\n=== TEST COMPLETE ===\n";

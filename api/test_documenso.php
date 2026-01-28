<?php
// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Тест Documenso Integration ===\n\n";

// Проверка конфига
echo "1. Проверка конфига...\n";
try {
    $config = require __DIR__ . '/documenso_config.php';
    echo "✓ Config загружен\n";
    echo "  API_KEY: " . substr($config['API_KEY'], 0, 10) . "...\n";
    echo "  API_URL: " . $config['API_URL'] . "\n";
    echo "  TEMPLATE_ID: " . $config['TEMPLATE_ID'] . "\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка конфига: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Проверка сервиса
echo "2. Проверка DocumensoService...\n";
try {
    require_once __DIR__ . '/DocumensoService.php';
    echo "✓ DocumensoService загружен\n";
    
    $service = new DocumensoService();
    echo "✓ DocumensoService создан\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка сервиса: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

// Тестовые данные
echo "3. Тест создания документа...\n";
$testData = [
    'buyerEmail' => 'test@example.com',
    'buyerName' => 'Test Buyer',
    'dogName' => 'Test Dog',
    'price' => '50000'
];

try {
    $result = $service->createSigningSession($testData);
    echo "✓ Документ создан!\n";
    echo "  Document ID: " . $result['documentId'] . "\n";
    echo "  Signing URL: " . $result['signingUrl'] . "\n\n";
    echo "=== УСПЕХ! ===\n";
} catch (Exception $e) {
    echo "✗ Ошибка создания: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

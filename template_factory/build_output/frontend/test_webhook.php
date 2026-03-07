<?php
// test_webhook.php - Тестирование webhook обработки
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Webhook Test</h1>";

// Симуляция webhook данных
$testPayload = [
    'event' => 'DOCUMENT_COMPLETED',
    'payload' => [
        'id' => 148,
        'recipients' => [
            [
                'email' => 'kharynadenis@gmail.com',
                'name' => 'Test User'
            ],
            [
                'email' => 'noreply@matrang.com',
                'name' => 'Great Legacy Bully'
            ]
        ]
    ]
];

echo "<h2>1. Проверка DocumensoService</h2>";
try {
    require_once __DIR__ . '/api/DocumensoService.php';
    echo "✅ DocumensoService подключен<br>";
    
    $config = require __DIR__ . '/api/documenso_config.php';
    echo "✅ Config загружен<br>";
    echo "API URL: " . $config['API_URL'] . "<br>";
    
    $service = new DocumensoService();
    echo "✅ DocumensoService создан<br>";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

echo "<h2>2. Проверка обработки payload</h2>";
$event = $testPayload['event'] ?? '';
$data = $testPayload['payload'] ?? [];
echo "Event: $event<br>";
echo "Document ID: " . ($data['id'] ?? 'N/A') . "<br>";

echo "<h2>3. Поиск email покупателя</h2>";
$buyerEmail = null;
$recipients = $data['recipients'] ?? [];
echo "Recipients count: " . count($recipients) . "<br>";
foreach ($recipients as $recipient) {
    echo "- " . $recipient['email'] . "<br>";
    if (!empty($recipient['email']) && $recipient['email'] !== 'noreply@matrang.com') {
        $buyerEmail = $recipient['email'];
        echo "  ✅ Found buyer email: $buyerEmail<br>";
        break;
    }
}

echo "<h2>4. Проверка downloadDocument()</h2>";
if (isset($service)) {
    if (method_exists($service, 'downloadDocument')) {
        echo "✅ Метод downloadDocument() существует<br>";
    } else {
        echo "❌ Метод downloadDocument() НЕ НАЙДЕН!<br>";
        echo "Доступные методы:<br>";
        $methods = get_class_methods($service);
        foreach ($methods as $method) {
            echo "- $method<br>";
        }
    }
}

echo "<h2>5. Проверка contracts.json</h2>";
$contractsFile = __DIR__ . '/data/contracts.json';
if (file_exists($contractsFile)) {
    echo "✅ Файл существует: $contractsFile<br>";
    $data = json_decode(file_get_contents($contractsFile), true);
    echo "Договоров в базе: " . count($data['contracts'] ?? []) . "<br>";
} else {
    echo "❌ Файл НЕ НАЙДЕН: $contractsFile<br>";
}

echo "<h2>ГОТОВО</h2>";

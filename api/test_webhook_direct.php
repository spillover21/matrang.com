<?php
// Простой тест - создаем и подписываем документ 152
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== ТЕСТ WEBHOOK ПРЯМЫМ ВЫЗОВОМ ===\n\n";

// Очищаем кеши
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared\n";
}
clearstatcache(true);
echo "✅ Stat cache cleared\n\n";

// Загружаем webhook
echo "Подключаем webhook_documenso.php...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_DOCUMENSO_SECRET'] = 'pXbQZ8@Y6akBjd5';

// Создаем тестовый payload
$payload = [
    'event' => 'DOCUMENT_COMPLETED',
    'payload' => [
        'id' => 152,
        'recipients' => [
            ['email' => 'kharynadenis@gmail.com', 'name' => 'Test', 'role' => 'SIGNER'],
            ['email' => 'noreply@matrang.com', 'name' => 'Seller', 'role' => 'SIGNER']
        ]
    ]
];

// Сохраняем в глобальную переменную для webhook
file_put_contents('php://input', json_encode($payload));

echo "Вызываем webhook...\n\n";

try {
    ob_start();
    include __DIR__ . '/webhook_documenso.php';
    $output = ob_get_clean();
    
    echo "=== WEBHOOK OUTPUT ===\n";
    echo $output;
    echo "\n\n";
    
    // Читаем последние 30 строк лога
    echo "=== ПОСЛЕДНИЕ ЛОГИ ===\n";
    $logFile = __DIR__ . '/../data/webhook_debug.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $last30 = array_slice($lines, -30);
        echo implode('', $last30);
    } else {
        echo "Лог файл не найден!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>

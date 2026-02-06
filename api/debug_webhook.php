<?php
// debug_webhook.php - Диагностика webhook ошибок
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== WEBHOOK DEBUG ===\n\n";

// 1. Проверка файлов
echo "1. Checking files...\n";
$files = [
    __DIR__ . '/api/webhook_documenso.php',
    __DIR__ . '/api/DocumensoService.php',
    __DIR__ . '/api/documenso_config.php',
    __DIR__ . '/api/vendor/autoload.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ MISSING: $file\n";
    }
}

// 2. Проверка синтаксиса webhook
echo "\n2. Checking webhook syntax...\n";
$webhookFile = __DIR__ . '/api/webhook_documenso.php';
$output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($webhookFile) . " 2>&1", $output, $return_var);
echo "   " . implode("\n   ", $output) . "\n";

// 3. Попытка подключить webhook (без выполнения)
echo "\n3. Testing webhook include...\n";
try {
    ob_start();
    
    // Симулируем переменные окружения для webhook
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_DOCUMENSO_SECRET'] = 'test';
    
    // Используем буфер чтобы поймать любые ошибки
    $error = null;
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error) {
        $error = "Error [$errno]: $errstr in $errfile on line $errline";
        return true;
    });
    
    // НЕ include webhook - он сразу начнет выполняться
    // Вместо этого проверим DocumensoService
    require_once __DIR__ . '/api/DocumensoService.php';
    echo "   ✅ DocumensoService loaded\n";
    
    $service = new DocumensoService();
    echo "   ✅ DocumensoService instantiated\n";
    
    if (method_exists($service, 'downloadDocument')) {
        echo "   ✅ Method downloadDocument() exists\n";
    } else {
        echo "   ❌ Method downloadDocument() NOT FOUND\n";
    }
    
    restore_error_handler();
    $output = ob_get_clean();
    
    if ($error) {
        echo "   ❌ ERROR: $error\n";
    }
    
    if ($output) {
        echo "   Output: " . substr($output, 0, 200) . "\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "   ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
} catch (Error $e) {
    ob_end_clean();
    echo "   ❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// 4. Проверка PHPMailer
echo "\n4. Checking PHPMailer...\n";
if (file_exists(__DIR__ . '/api/vendor/autoload.php')) {
    require_once __DIR__ . '/api/vendor/autoload.php';
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "   ✅ PHPMailer loaded\n";
    } else {
        echo "   ❌ PHPMailer class not found\n";
    }
}

echo "\n=== END DEBUG ===\n";

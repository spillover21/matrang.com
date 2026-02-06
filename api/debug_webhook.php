<?php
// debug_webhook.php - Диагностика webhook ошибок
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== WEBHOOK DEBUG ===\n\n";

// 1. Проверка файлов
echo "1. Checking files...\n";
$files = [
    __DIR__ . '/webhook_documenso.php',
    __DIR__ . '/DocumensoService.php',
    __DIR__ . '/documenso_config.php',
    __DIR__ . '/vendor/autoload.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ MISSING: $file\n";
    }
}

// 2. Проверка синтаксиса webhook (exec отключена на хостинге)
echo "\n2. Syntax check skipped (exec disabled)\n";

// 3. Попытка подключить webhook (без выполнения)
echo "\n3. Testing DocumensoService...\n";
try {
    ob_start();
    
    // Используем буфер чтобы поймать любые ошибки
    $error = null;
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error) {
        $error = "Error [$errno]: $errstr in $errfile on line $errline";
        return true;
    });
    
    // Проверим DocumensoService
    require_once __DIR__ . '/DocumensoService.php';
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
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "   ✅ PHPMailer loaded\n";
    } else {
        echo "   ❌ PHPMailer class not found\n";
    }
}

echo "\n=== END DEBUG ===\n";

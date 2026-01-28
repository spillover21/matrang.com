<?php
// api/test_documenso.php
// Скрипт для диагностики подключения к Documenso
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Documenso Integration Diagnostic ===\n\n";

echo "1. Checking Configuration File...\n";
$configFile = __DIR__ . '/documenso_config.php';
if (!file_exists($configFile)) {
    die("FAIL: Configuration file not found at $configFile\n");
}
echo "OK: Config file exists.\n";

echo "2. Checkinh .env file...\n";
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    echo "OK: .env file found.\n";
} else {
    echo "WARNING: .env file NOT found at $envFile (using defaults?)\n";
}

echo "3. Loading Configuration...\n";
try {
    $config = require $configFile;
    echo "OK: Config loaded.\n";
    echo "API URL: " . ($config['API_URL'] ?? 'MISSING') . "\n";
    echo "Template ID: " . ($config['TEMPLATE_ID'] ?? 'MISSING') . "\n";
    echo "API Key Set: " . (!empty($config['API_KEY']) ? 'YES' : 'NO') . "\n";
} catch (Throwable $e) {
    die("FAIL: Error loading config: " . $e->getMessage() . "\n");
}

echo "\n4. Checking DocumensoService Class...\n";
$serviceFile = __DIR__ . '/DocumensoService.php';
if (!file_exists($serviceFile)) {
    die("FAIL: Service file not found at $serviceFile\n");
}
echo "OK: Service file exists.\n";

try {
    require_once $serviceFile;
    if (class_exists('DocumensoService')) {
        echo "OK: Class DocumensoService exists.\n";
    } else {
        die("FAIL: Class DocumensoService is undefined after include.\n");
    }
} catch (Throwable $e) {
    die("FAIL: Error including service class: " . $e->getMessage() . "\n");
}

echo "\n5. Testing cURL Extension...\n";
if (function_exists('curl_init')) {
    echo "OK: cURL extension is enabled.\n";
} else {
    die("FAIL: cURL is NOT installed/enabled on this server.\n");
}

echo "\n6. Instantiating Service...\n";
try {
    $service = new DocumensoService();
    echo "OK: Service instantiated successfully.\n";
} catch (Throwable $e) {
    die("FAIL: Constructor error: " . $e->getMessage() . "\n");
}

echo "\n=== DIAGNOSTIC COMPLETE: READY FOR REQUESTS ===\n";

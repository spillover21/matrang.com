<?php
// api/debug_documenso.php
// Диагностика подключения и проверка шаблона
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/DocumensoService.php';

echo "<pre>";
echo "Testing Documenso Connection...\n";

$service = new DocumensoService();

// Используем ReflectionClass для доступа к приватным методам (request) для отладки
$reflection = new ReflectionClass('DocumensoService');
$requestMethod = $reflection->getMethod('request');
$requestMethod->setAccessible(true);

$config = require __DIR__ . '/documenso_config.php';
$templateId = $config['TEMPLATE_ID'];
echo "Configured Template ID: " . $templateId . "\n";

// 1. Проверка авторизации (получить список шаблонов, лимит 1)
echo "\n--- 1. Testing Connection (GET /templates) ---\n";
try {
    $templates = $requestMethod->invoke($service, 'GET', '/templates?perPage=5&page=1');
    echo "Success! Connection working.\n";
    echo "Found " . count($templates['data'] ?? []) . " templates.\n";
    print_r($templates);
} catch (Exception $e) {
    echo "Connection Failed: " . $e->getMessage() . "\n";
}

// 2. Проверка конкретного шаблона
echo "\n--- 2. Checking Specific Template ID ($templateId) ---\n";
if ($templateId) {
    try {
        $template = $requestMethod->invoke($service, 'GET', '/templates/' . $templateId);
        echo "Template Found!\n";
        print_r($template);
    } catch (Exception $e) {
        echo "Template Check Failed: " . $e->getMessage() . "\n";
        echo "Possible reasons: ID is wrong, or API Key belongs to a different team/user.\n";
    }
} else {
    echo "No Template ID configured.\n";
}

echo "</pre>";

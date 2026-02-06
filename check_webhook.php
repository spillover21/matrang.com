<?php
// Проверка webhook настроек в Documenso
require_once __DIR__ . '/api/DocumensoService.php';
$config = require __DIR__ . '/api/documenso_config.php';

$service = new DocumensoService($config);

try {
    echo "Проверяем webhooks в Documenso...\n\n";
    $webhooks = $service->listWebhooks();
    
    if (empty($webhooks)) {
        echo "❌ Webhooks не найдены!\n";
    } else {
        echo "✅ Найдено webhooks: " . count($webhooks) . "\n\n";
        foreach ($webhooks as $webhook) {
            echo "ID: " . ($webhook['id'] ?? 'N/A') . "\n";
            echo "URL: " . ($webhook['webhookUrl'] ?? 'N/A') . "\n";
            echo "Events: " . implode(', ', $webhook['eventTriggers'] ?? []) . "\n";
            echo "Enabled: " . (($webhook['enabled'] ?? false) ? 'Да' : 'Нет') . "\n";
            echo "---\n\n";
        }
    }
    
    echo "\n=== ВАЖНО ===\n";
    echo "Webhook URL должен быть: https://matrang.com/api/webhook_documenso.php\n";
    echo "НЕ ДОЛЖЕН быть: http://72.62.114.139/...\n";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
}

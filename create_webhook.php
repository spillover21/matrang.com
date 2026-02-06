<?php
// Создание webhook в Documenso через API
require_once __DIR__ . '/api/DocumensoService.php';
$config = require __DIR__ . '/api/documenso_config.php';

$service = new DocumensoService($config);

try {
    echo "Создаем webhook в Documenso...\n\n";
    
    $result = $service->createWebhook(
        'https://matrang.com/api/webhook_documenso.php',
        ['document.completed', 'document.rejected'],
        $config['WEBHOOK_SECRET']
    );
    
    echo "✅ Webhook создан успешно!\n\n";
    echo "ID: " . ($result['id'] ?? 'N/A') . "\n";
    echo "URL: " . ($result['webhookUrl'] ?? 'N/A') . "\n";
    echo "Events: " . implode(', ', $result['eventTriggers'] ?? []) . "\n";
    echo "Enabled: " . (($result['enabled'] ?? false) ? 'Да' : 'Нет') . "\n";
    
    echo "\n=== ГОТОВО ===\n";
    echo "Теперь при подписании договора webhook будет вызван!\n";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    echo "\nПопробуйте создать webhook вручную через UI:\n";
    echo "http://72.62.114.139:9000/ → Settings → Webhooks\n";
}

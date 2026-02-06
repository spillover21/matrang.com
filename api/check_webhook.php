<?php
// check_webhook.php - Проверка и настройка webhook в Documenso

require_once __DIR__ . '/DocumensoService.php';

try {
    $service = new DocumensoService();
    
    echo "=== Проверка Webhooks в Documenso ===\n\n";
    
    // Получаем список webhooks
    $webhooks = $service->listWebhooks();
    
    if (empty($webhooks)) {
        echo "⚠️  Webhooks не настроены!\n\n";
        echo "Создаем webhook для DOCUMENT_COMPLETED...\n";
        
        // Создаем webhook
        $webhookUrl = 'http://matrang.com/api/webhook_documenso.php';
        $config = require __DIR__ . '/documenso_config.php';
        $secret = $config['WEBHOOK_SECRET'];
        
        $result = $service->createWebhook($webhookUrl, ['DOCUMENT_COMPLETED'], $secret);
        
        if ($result) {
            echo "✅ Webhook создан успешно!\n";
            echo "URL: $webhookUrl\n";
            echo "Events: DOCUMENT_COMPLETED\n";
        } else {
            echo "❌ Ошибка создания webhook\n";
        }
    } else {
        echo "✅ Найдено webhooks: " . count($webhooks) . "\n\n";
        
        foreach ($webhooks as $webhook) {
            echo "ID: " . ($webhook['id'] ?? 'N/A') . "\n";
            echo "URL: " . ($webhook['webhookUrl'] ?? 'N/A') . "\n";
            echo "Events: " . implode(', ', $webhook['eventTriggers'] ?? []) . "\n";
            echo "Active: " . (($webhook['enabled'] ?? false) ? 'Yes' : 'No') . "\n";
            echo "---\n";
        }
    }
    
    echo "\n=== Проверка файлов и папок ===\n\n";
    
    // Проверяем наличие необходимых папок
    $uploadDir = __DIR__ . '/../uploads/contracts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "✅ Создана папка: $uploadDir\n";
    } else {
        echo "✅ Папка существует: $uploadDir\n";
    }
    
    $dataDir = __DIR__ . '/../data/';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
        echo "✅ Создана папка: $dataDir\n";
    } else {
        echo "✅ Папка существует: $dataDir\n";
    }
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    if (!file_exists($contractsFile)) {
        $defaultData = [
            'contracts' => [],
            'templates' => []
        ];
        file_put_contents($contractsFile, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "✅ Создан файл: $contractsFile\n";
    } else {
        echo "✅ Файл существует: $contractsFile\n";
        $data = json_decode(file_get_contents($contractsFile), true);
        $contractCount = is_array($data['contracts'] ?? null) ? count($data['contracts']) : 0;
        echo "   Договоров в базе: $contractCount\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

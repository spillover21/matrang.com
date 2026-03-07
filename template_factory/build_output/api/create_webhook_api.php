<?php
// create_webhook_api.php - API для создания webhook
header('Content-Type: application/json');

require_once __DIR__ . '/DocumensoService.php';

try {
    $service = new DocumensoService();
    $config = require __DIR__ . '/documenso_config.php';
    
    // URL webhook должен быть доступен извне
    // Используем домен сайта
    $webhookUrl = 'http://matrang.com/api/webhook_documenso.php';
    $secret = $config['WEBHOOK_SECRET'];
    
    $webhook = $service->createWebhook(
        $webhookUrl,
        ['DOCUMENT_COMPLETED'],
        $secret
    );
    
    echo json_encode([
        'success' => true,
        'webhook' => $webhook,
        'message' => 'Webhook создан успешно'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

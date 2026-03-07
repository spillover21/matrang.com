<?php
// delete_webhooks_api.php - API для удаления всех webhooks
header('Content-Type: application/json');

require_once __DIR__ . '/DocumensoService.php';

try {
    $service = new DocumensoService();
    
    $webhooks = $service->listWebhooks();
    $deleted = 0;
    
    foreach ($webhooks as $webhook) {
        $id = $webhook['id'] ?? null;
        if ($id) {
            try {
                $service->deleteWebhook($id);
                $deleted++;
            } catch (Exception $e) {
                error_log("Failed to delete webhook $id: " . $e->getMessage());
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Удалено webhooks: $deleted"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

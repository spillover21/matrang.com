<?php
/**
 * Полный тест Bridge API клиента
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/DocumensoBridgeClient.php';

header('Content-Type: application/json');

try {
    $bridge = new DocumensoBridgeClient();
    
    // Получаем список документов
    $envelopes = $bridge->getEnvelopes();
    
    $results = [
        'success' => true,
        'envelopes' => $envelopes
    ];
    
    // Если есть документы, получим детали первого
    if (!empty($envelopes['envelopes'])) {
        $firstEnvelope = $envelopes['envelopes'][0];
        $envelopeId = $firstEnvelope['id'];
        
        // Детали документа
        $results['envelope_details'] = $bridge->getEnvelope($envelopeId);
        
        // Получатели
        $results['recipients'] = $bridge->getRecipients($envelopeId);
        
        // Если есть получатели, получим ссылку для первого
        if (!empty($results['recipients']['recipients'])) {
            $firstRecipient = $results['recipients']['recipients'][0];
            $results['signing_url'] = $bridge->getSigningUrl($envelopeId, $firstRecipient['email']);
        }
    }
    
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

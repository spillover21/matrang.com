<?php
/**
 * Сервис для работы с договорами через Documenso
 * Полный цикл: заполнение PDF → загрузка → отправка на подпись
 */

require_once __DIR__ . '/DocumensoBridgeClient.php';

class ContractService {
    private $bridge;
    
    public function __construct() {
        $this->bridge = new DocumensoBridgeClient();
    }
    
    /**
     * Создать договор и отправить на подпись
     * 
     * @param array $contractData Данные из формы ContractManager
     * @return array Результат с envelope_id и signing_url
     */
    public function createAndSendContract($contractData) {
        // Отправляем данные напрямую на VPS через Bridge API
        // VPS сам заполнит шаблон через Python pypdf, загрузит в Documenso и создаст envelope
        
        $ch = curl_init('http://72.62.114.139:8080/create_envelope.php');


        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($contractData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: matrang_secret_key_2026'
            ],
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Bridge API connection error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('Bridge API returned HTTP ' . $httpCode . ': ' . $response);
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !$result['success']) {
            throw new Exception($result['error'] ?? 'Unknown error from Bridge API');
        }

        // FORCE PORT CORRECTION:
        // The bridge might return port 9000 (internal mapping), but we know port 8080 
        // is the public-facing bridge that works. We rewrite the URL to ensure accessibility.
        if (!empty($result['signing_url'])) {
            $result['signing_url'] = str_replace(':9000', ':8080', $result['signing_url']);
        }
        if (!empty($result['seller_signing_url'])) {
            $result['seller_signing_url'] = str_replace(':9000', ':8080', $result['seller_signing_url']);
        }
        
        return $result;
    }
    
    /**
     * Получить статус подписания договора
     */
    public function getContractStatus($envelopeId) {
        $envelope = $this->bridge->getEnvelope($envelopeId);
        $recipients = $this->bridge->getRecipients($envelopeId);
        
        return [
            'envelope' => $envelope['envelope'],
            'recipients' => $recipients['recipients']
        ];
    }
    
    /**
     * Получить ссылку для подписания
     */
    public function getSigningLink($envelopeId, $recipientEmail) {
        return $this->bridge->getSigningUrl($envelopeId, $recipientEmail);
    }
}

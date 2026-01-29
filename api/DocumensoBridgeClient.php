<?php
/**
 * Клиент для взаимодействия с Bridge API на VPS
 * Используется для работы с Documenso через промежуточный сервер
 */

class DocumensoBridgeClient {
    private $bridgeUrl;
    private $apiKey;
    
    public function __construct() {
        $this->bridgeUrl = 'http://72.62.114.139/documenso-bridge/';
        $this->apiKey = 'matrang_secret_key_2026';
    }
    
    /**
     * Тест соединения с bridge API
     */
    public function test() {
        return $this->request('test');
    }
    
    /**
     * Получить список envelopes (документов)
     */
    public function getEnvelopes() {
        return $this->request('get_envelopes');
    }
    
    /**
     * Получить получателей документа
     */
    public function getRecipients($envelopeId) {
        return $this->request('get_recipients', ['envelopeId' => $envelopeId]);
    }
    
    /**
     * Отправить HTTP запрос к bridge API
     */
    private function request($action, $params = []) {
        $url = $this->bridgeUrl . '?action=' . urlencode($action);
        
        foreach ($params as $key => $value) {
            $url .= '&' . urlencode($key) . '=' . urlencode($value);
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Bridge API connection error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Bridge API returned HTTP $httpCode: $response");
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['success'])) {
            throw new Exception("Invalid response from Bridge API");
        }
        
        if (!$data['success']) {
            throw new Exception($data['error'] ?? 'Unknown error');
        }
        
        return $data;
    }
}

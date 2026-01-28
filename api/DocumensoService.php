<?php

class DocumensoService {
    private $apiKey;
    private $apiUrl;
    private $templateId;

    public function __construct() {
        $config = require __DIR__ . '/documenso_config.php';
        
        $this->apiKey = $config['API_KEY'];
        $this->apiUrl = $config['API_URL'];
        $this->templateId = $config['TEMPLATE_ID'];
        $this->publicUrl = $config['PUBLIC_URL'] ?? 'https://app.documenso.com';
    }

    /**
     * Создает сессию подписания на основе шаблона
     * Реализует полный цикл: Создание -> Добавление получателя -> Отправка -> Получение ссылки
     * 
     * @param string $customerEmail Email клиента
     * @param string $customerName Имя клиента
     * @param string $internalUserId Внутренний ID пользователя для Audit Trail (Legal Compliance)
     * @return string URL для подписания (Direct Link)
     */
    public function createSigningSession($customerEmail, $customerName, $internalUserId) {
        if (empty($this->apiKey) || empty($this->templateId)) {
            throw new Exception("Documenso configuration is missing (API Key or Template ID)");
        }

        // 1. Создаем документ из шаблона
        // LEGAL COMPLIANCE: Мы передаем internalUserId в metadata. 
        // Это связывает подпись с нашей внутренней базой данных пользователей для судов ЕС.
        $documentData = [
            'templateId' => (int)$this->templateId, // Template ID должен быть числом в V2 часто
            'title' => "Contract for " . $customerName,
            'metadata' => [
                'internalUserId' => $internalUserId,
                'source' => 'Matrang CRM',
                'createdAt' => date('c')
            ]
        ];

        $document = $this->request('POST', '/documents', $documentData);
        $documentId = $document['id'];

        // 2. Добавляем получателя (Recipient)
        $recipientData = [
            'email' => $customerEmail,
            'name' => $customerName,
            'role' => 'SIGNER', // Или viewer, approver
            'authOptions' => [
                 // Можно добавить 2FA (SMS/Email pass) для усиления юридической значимости
                 'requireEmailAuth' => true
            ]
        ];
        
        // В V2 получатели часто добавляются через отдельный эндпоинт или сразу при создании
        // Здесь используем подход добавления к созданному черновику
        $recipient = $this->request('POST', "/documents/{$documentId}/recipients", $recipientData);

        // 3. Отправляем документ (переводим из Draft в Pending)
        $this->request('POST', "/documents/{$documentId}/send", ['sendEmail' => false]); // false, так как мы хотим использовать embed

        // 4. Генерируем Direct Link (Recipient Token)
        // Нам нужно найти токен добавленного получателя
        // В реальном API Documenso нужно получить токен получателя для формирования ссылки
        // Ссылка обычно выглядит как: https://app.documenso.com/sign/{token}
        
        $token = $recipient['token']; 
        
        // Формируем ссылку для iframe
        $directLink = rtrim($this->publicUrl, '/') . "/sign/{$token}";

        return [
            'signingUrl' => $directLink,
            'documentId' => $documentId
        ];
    }

    /**
     * Скачивает подписанный PDF
     */
    public function downloadDocument($documentId, $savePath) {
        $pdfContent = $this->request('GET', "/documents/{$documentId}/download", [], true);
        file_put_contents($savePath, $pdfContent);
        return true;
    }

    /**
     * Вспомогательный метод для запросов
     */
    private function request($method, $endpoint, $data = [], $rawOutput = false) {
        $url = rtrim($this->apiUrl, '/') . $endpoint;
        
        $curl = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if (!$rawOutput) {
            $headers[] = 'Content-Type: application/json';
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (!empty($data) && $method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if (curl_errno($curl)) {
            throw new Exception('Documenso API Error: ' . curl_error($curl));
        }
        
        curl_close($curl);

        if ($httpCode >= 400) {
            throw new Exception("API request failed with status $httpCode: $response");
        }

        if ($rawOutput) {
            return $response;
        }

        return json_decode($response, true);
    }
}
?>
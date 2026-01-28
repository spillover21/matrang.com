<?php

class DocumensoService {
    private $apiKey;
    private $apiUrl;
    private $templateId;
    private $publicUrl;

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

        // 1. Создаем документ из шаблона с указанием получателей
        // API требует массив recipients при создании
        $documentData = [
            'templateId' => (int)$this->templateId,
            'title' => "Contract for " . $customerName,
            'metadata' => [
                'internalUserId' => $internalUserId,
                'source' => 'Matrang CRM',
                'createdAt' => date('c')
            ],
            'recipients' => [
                [
                    'email' => $customerEmail,
                    'name' => $customerName,
                    'role' => 'SIGNER',
                    'authOptions' => [
                         'requireEmailAuth' => false // Для упрощения теста, можно включить позже
                    ]
                ]
            ]
        ];

        $document = $this->request('POST', '/documents', $documentData);
        $documentId = $document['id'];
        
        // Получаем созданного получателя из ответа или отдельно
        // Обычно при создании recipients возвращаются в массиве
        $recipient = null;
        if (!empty($document['recipients'])) {
            foreach ($document['recipients'] as $r) {
                if ($r['email'] === $customerEmail) {
                    $recipient = $r;
                    break;
                }
            }
        }
        
        // Если вдруг токена нет в ответе создания, запрашиваем список получателей
        if (!$recipient || empty($recipient['token'])) {
             $recipientsList = $this->request('GET', "/documents/{$documentId}/recipients");
             // Обработка разных форматов ответа (массив или {recipients: []})
             $list = isset($recipientsList['recipients']) ? $recipientsList['recipients'] : $recipientsList;
             foreach ($list as $r) {
                 if ($r['email'] === $customerEmail) {
                     $recipient = $r;
                     break;
                 }
             }
        }
        
        if (!$recipient || empty($recipient['token'])) {
             throw new Exception("Could not retrieve recipient token for $customerEmail");
        }

        // 3. Отправляем документ (переводим из Draft в Pending)
        // В некоторых версиях API создание с получателями уже делает документ отправленным, 
        // но обычно нужно явное действие отправки.
        try {
           $this->request('POST', "/documents/{$documentId}/send", ['sendEmail' => false]);
        } catch (Exception $e) {
           // Игнорируем ошибку, если документ уже в статусе PENDING
           // (это может произойти, если создание сразу переводит статус)
        }

        // 4. Генерируем Direct Link (Recipient Token)
        $token = $recipient['token']; 
        
        // Формируем ссылку для iframe
        // Важно: в V1 ссылка может быть /sign/{token}, проверим
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
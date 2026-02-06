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
     * @param array $contractData Все данные договора из формы
     * @return string URL для подписания (Direct Link)
     */
    public function createSigningSession($contractData) {
        if (empty($this->apiKey) || empty($this->templateId)) {
            throw new Exception("Documenso configuration is missing (API Key or Template ID)");
        }

        $customerEmail = $contractData['buyerEmail'] ?? '';
        $customerName = $contractData['buyerName'] ?? 'Customer';
        $internalUserId = $contractData['internalId'] ?? 'user_'.time();

        if (empty($customerEmail)) {
            throw new Exception("Buyer email is required");
        }

        // 1. Генерируем документ из шаблона (правильный endpoint)
        $generateData = [
            'title' => "Contract for " . $customerName,
            'externalId' => $internalUserId,
            'recipients' => [
                [
                    'email' => $customerEmail,
                    'name' => $customerName,
                    'role' => 'SIGNER'
                ]
            ],
            'data' => $this->buildFieldData($contractData),
            'sendDocument' => false
        ];

        $document = $this->request('POST', "/templates/{$this->templateId}/generate", $generateData);
        // Correctly handle response structure (V1 returns 'documentId')
        $documentId = $document['documentId'] ?? $document['id'];
        
        // Получаем созданного получателя из ответа
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
        // Используем signingUrl из ответа, если есть, иначе формируем
        $directLink = !empty($recipient['signingUrl']) 
            ? $recipient['signingUrl'] 
            : rtrim($this->publicUrl, '/') . "/sign/" . $recipient['token'];

        return [
            'signingUrl' => $directLink,
            'documentId' => $documentId
        ];
    }

    /**
     * Скачивает подписанный PDF
     */
    public function downloadDocument($documentId, $savePath) {
        $debugLog = __DIR__ . '/../data/webhook_debug.log';
        
        try {
            error_log("[DOCUMENSO] Starting download for document $documentId");
            file_put_contents($debugLog, "[DOCUMENSO] Starting download for document $documentId\n", FILE_APPEND);
            
            // Получаем JSON с URL для скачивания
            file_put_contents($debugLog, "[DOCUMENSO] Calling API /documents/$documentId/download\n", FILE_APPEND);
            $response = $this->request('GET', "/documents/{$documentId}/download");
            file_put_contents($debugLog, "[DOCUMENSO] API response received\n", FILE_APPEND);
            
            if (!isset($response['downloadUrl'])) {
                error_log("[DOCUMENSO ERROR] Download URL not found in response");
                file_put_contents($debugLog, "[DOCUMENSO ERROR] Download URL not found in response\n", FILE_APPEND);
                throw new Exception('Download URL not found in response');
            }
            
            $downloadUrl = $response['downloadUrl'];
            error_log("[DOCUMENSO] Original download URL: $downloadUrl");
            $shortUrl = substr($downloadUrl, 0, 100) . '...';
            file_put_contents($debugLog, "[DOCUMENSO] Download URL received (length: " . strlen($downloadUrl) . ")\n", FILE_APPEND);
            
            // Заменяем внутренний адрес minio на публичный адрес VPS
            $downloadUrl = str_replace('http://minio:9000', 'http://72.62.114.139:9002', $downloadUrl);
            error_log("[DOCUMENSO] Public download URL: $downloadUrl");
            file_put_contents($debugLog, "[DOCUMENSO] URL replaced, starting download...\n", FILE_APPEND);
            
            // Скачиваем PDF по полученному URL
            file_put_contents($debugLog, "[DOCUMENSO] Initializing cURL download...\n", FILE_APPEND);
            $curl = curl_init($downloadUrl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            
            $pdfContent = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            if ($curlError) {
                error_log("[DOCUMENSO ERROR] cURL error: $curlError");
                throw new Exception("Failed to download PDF: $curlError");
            }
            
            if ($httpCode !== 200 || empty($pdfContent)) {
                error_log("[DOCUMENSO ERROR] HTTP $httpCode while downloading PDF");
                error_log("[DOCUMENSO ERROR] Response: " . substr($pdfContent, 0, 500));
                throw new Exception("Failed to download PDF from URL: HTTP $httpCode");
            }
            
            // Проверяем что это действительно PDF
            if (substr($pdfContent, 0, 4) !== '%PDF') {
                error_log("[DOCUMENSO ERROR] Invalid PDF format. First bytes: " . substr($pdfContent, 0, 50));
                throw new Exception("Invalid PDF format received");
            }
            
            // Создаем директорию если не существует
            $dir = dirname($savePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Сохраняем PDF
            $bytesWritten = file_put_contents($savePath, $pdfContent);
            
            if ($bytesWritten === false) {
                error_log("[DOCUMENSO ERROR] Failed to write PDF to $savePath");
                throw new Exception("Failed to write PDF file");
            }
            
            error_log("[DOCUMENSO] Successfully downloaded document $documentId ($bytesWritten bytes) to $savePath");
            return true;
            $errorMsg = "[DOCUMENSO ERROR] Download failed: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
            error_log($errorMsg);
            file_put_contents($debugLog, $errorMsg . "\n", FILE_APPEND);
            file_put_contents($debugLog, "[DOCUMENSO TRACE] " . $e->getTraceAsString() . "\n", FILE_APPEND
        } catch (Exception $e) {
            error_log("[DOCUMENSO ERROR] Download failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получает список всех документов
     */
    public function listDocuments($page = 1, $perPage = 100) {
        $response = $this->request('GET', "/documents?page={$page}&perPage={$perPage}");
        return $response['documents'] ?? [];
    }

    /**
     * Получает детали конкретного документа
     */
    public function getDocument($documentId) {
        return $this->request('GET', "/documents/{$documentId}");
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
            $errorMsg = "API request failed with status $httpCode";
            $json = json_decode($response, true);
            // Пытаемся достать человекочитаемое сообщение об ошибке
            if ($json) {
                if (isset($json['message'])) {
                    $errorMsg = $json['message'];
                } elseif (isset($json['code'])) {
                    $errorMsg = "Error: " . $json['code'];
                }
            }
            throw new Exception($errorMsg);
        }

        if ($rawOutput) {
            return $response;
        }

        return json_decode($response, true);
    }

    /**
     * Формирует массив полей для автозаполнения шаблона
     */
    private function buildFieldData($data) {
        return [
            'contractNumber' => $data['contractNumber'] ?? '',
            'contractDate' => $data['contractDate'] ?? date('d.m.Y'),
            'contractPlace' => $data['contractPlace'] ?? '',
            
            'kennelOwner' => $data['kennelOwner'] ?? '',
            'kennelAddress' => $data['kennelAddress'] ?? '',
            'kennelPhone' => $data['kennelPhone'] ?? '',
            'kennelEmail' => $data['kennelEmail'] ?? '',
            'kennelPassportSeries' => $data['kennelPassportSeries'] ?? '',
            'kennelPassportNumber' => $data['kennelPassportNumber'] ?? '',
            'kennelPassportIssuedBy' => $data['kennelPassportIssuedBy'] ?? '',
            'kennelPassportIssuedDate' => $data['kennelPassportIssuedDate'] ?? '',
            
            'buyerName' => $data['buyerName'] ?? '',
            'buyerAddress' => $data['buyerAddress'] ?? '',
            'buyerPhone' => $data['buyerPhone'] ?? '',
            'buyerEmail' => $data['buyerEmail'] ?? '',
            'buyerPassportSeries' => $data['buyerPassportSeries'] ?? '',
            'buyerPassportNumber' => $data['buyerPassportNumber'] ?? '',
            'buyerPassportIssuedBy' => $data['buyerPassportIssuedBy'] ?? '',
            'buyerPassportIssuedDate' => $data['buyerPassportIssuedDate'] ?? '',
            
            'dogFatherName' => $data['dogFatherName'] ?? '',
            'dogFatherRegNumber' => $data['dogFatherRegNumber'] ?? '',
            'dogMotherName' => $data['dogMotherName'] ?? '',
            'dogMotherRegNumber' => $data['dogMotherRegNumber'] ?? '',
            
            'dogName' => $data['dogName'] ?? '',
            'dogBirthDate' => $data['dogBirthDate'] ?? '',
            'dogColor' => $data['dogColor'] ?? '',
            'dogChipNumber' => $data['dogChipNumber'] ?? '',
            'dogPuppyCard' => $data['dogPuppyCard'] ?? '',
            
            'purposeBreeding' => !empty($data['purposeBreeding']) ? 'true' : 'false',
            'purposeCompanion' => !empty($data['purposeCompanion']) ? 'true' : 'false',
            'purposeGeneral' => !empty($data['purposeGeneral']) ? 'true' : 'false',
            
            'price' => $data['price'] ?? '',
            'depositAmount' => $data['depositAmount'] ?? '',
            'depositDate' => $data['depositDate'] ?? '',
            'remainingAmount' => $data['remainingAmount'] ?? '',
            'finalPaymentDate' => $data['finalPaymentDate'] ?? '',
            
            'dewormingDate' => $data['dewormingDate'] ?? '',
            'vaccinationDates' => $data['vaccinationDates'] ?? '',
            'vaccineName' => $data['vaccineName'] ?? '',
            'nextDewormingDate' => $data['nextDewormingDate'] ?? '',
            'nextVaccinationDate' => $data['nextVaccinationDate'] ?? '',
            
            'specialFeatures' => $data['specialFeatures'] ?? '',
            'deliveryTerms' => $data['deliveryTerms'] ?? '',
            'additionalAgreements' => $data['additionalAgreements'] ?? '',
            'recommendedFood' => $data['recommendedFood'] ?? ''
        ];
    }

    /**
     * Получает список webhooks
     */
    public function listWebhooks() {
        try {
            $response = $this->request('GET', '/webhooks');
            return $response['webhooks'] ?? $response ?? [];
        } catch (Exception $e) {
            error_log("Error listing webhooks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Создает новый webhook
     */
    public function createWebhook($webhookUrl, $eventTriggers = ['DOCUMENT_COMPLETED'], $secret = null) {
        $data = [
            'webhookUrl' => $webhookUrl,
            'eventTriggers' => $eventTriggers,
            'secret' => $secret
        ];
        
        try {
            $response = $this->request('POST', '/webhooks', $data);
            return $response;
        } catch (Exception $e) {
            error_log("Error creating webhook: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Удаляет webhook
     */
    public function deleteWebhook($webhookId) {
        try {
            $response = $this->request('DELETE', "/webhooks/{$webhookId}");
            return $response;
        } catch (Exception $e) {
            error_log("Error deleting webhook: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
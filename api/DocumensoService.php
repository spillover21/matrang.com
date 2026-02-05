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
        $pdfContent = $this->request('GET', "/documents/{$documentId}/download", [], true);
        file_put_contents($savePath, $pdfContent);
        return true;
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
            'contractNumber' => 'DOG-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
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
}
?>
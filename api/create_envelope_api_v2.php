<?php
/**
 * Documenso Contract Creation via API V2
 * 
 * Использует официальный Documenso API V2 вместо прямых SQL запросов
 * Документация: https://docs.documenso.com/developers/public-api/reference
 */

header('Content-Type: application/json');
date_default_timezone_set('UTC');

// =====================================
// КОНФИГУРАЦИЯ
// =====================================

// ВАЖНО: Замените на ваш реальный API токен из Documenso
// (Team Settings -> API Tokens -> создайте "PHP Contract Bridge")
const DOCUMENSO_API_TOKEN = 'api_iffjvv698wn27tji'; // Начинается с 'api_'
const DOCUMENSO_BASE_URL = 'http://72.62.114.139:9000';
const DOCUMENSO_API_URL = DOCUMENSO_BASE_URL . '/api/v2';

// API ключ для защиты этого эндпоинта
const PHP_API_KEY = 'matrang_secret_key_2026';

// Пути к скриптам заполнения PDF
const PYTHON_FILL_SCRIPT = '/var/www/documenso-bridge/fill_pdf.py';
const PDF_TEMPLATE_PATH = '/var/www/documenso-bridge/template.pdf';

// =====================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =====================================

/**
 * Проверка API ключа
 */
function checkApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey !== PHP_API_KEY) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
}

/**
 * Логирование для отладки
 */
function logDebug($message, $data = null) {
    $logFile = '/tmp/documenso_api_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= "\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    $logEntry .= "\n---\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Заполнение PDF через Python (сохраняет существующую логику)
 */
function fillPdfWithData($data) {
    // Создаем JSON файл с данными
    $jsonPath = sys_get_temp_dir() . '/data_' . bin2hex(random_bytes(6)) . '.json';
    $filledPdfPath = sys_get_temp_dir() . '/filled_' . bin2hex(random_bytes(6)) . '.pdf';
    
    // Логируем данные (копия для отладки)
    file_put_contents('/tmp/last_contract_data.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // Записываем данные для Python
    if (file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
        throw new Exception('Failed to write JSON data file');
    }
    
    // Выполняем Python скрипт
    $pythonCmd = sprintf(
        "python3 %s %s %s %s 2>&1",
        escapeshellarg(PYTHON_FILL_SCRIPT),
        escapeshellarg(PDF_TEMPLATE_PATH),
        escapeshellarg($filledPdfPath),
        escapeshellarg($jsonPath)
    );
    
    exec($pythonCmd, $output, $returnCode);
    
    // Удаляем временный JSON
    @unlink($jsonPath);
    
    if ($returnCode !== 0) {
        throw new Exception('Python script failed (exit code ' . $returnCode . '): ' . implode("\n", $output));
    }
    
    if (!file_exists($filledPdfPath)) {
        throw new Exception('PDF file was not created. Python output: ' . implode("\n", $output));
    }
    
    return $filledPdfPath;
}

/**
 * API запрос к Documenso V2
 */
function documensoApiRequest($endpoint, $method = 'GET', $data = null, $isMultipart = false) {
    $url = DOCUMENSO_API_URL . $endpoint;
    
    logDebug("API Request: $method $url", ['data' => $data]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . DOCUMENSO_API_TOKEN,
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        
        if ($isMultipart) {
            // Multipart для загрузки файлов
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            // JSON для обычных запросов
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                curl_getopt($ch, CURLOPT_HTTPHEADER) ?: [],
                ['Content-Type: application/json']
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    logDebug("API Response: HTTP $httpCode", ['response' => $response]);
    
    if ($error) {
        throw new Exception("cURL error: $error");
    }
    
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("API error (HTTP $httpCode): " . $response);
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: " . $response);
    }
    
    return $decoded;
}

/**
 * Обновление статуса envelope на PENDING (документ готов к подписанию)
 * Примечание: API V2 создает документы в статусе DRAFT, нужно обновить на PENDING
 */
function updateEnvelopeStatus($envelopeId) {
    $pgHost = '72.62.114.139';
    $pgPort = '5432';
    $pgDatabase = 'documenso';
    $pgUser = 'documenso';
    $pgPassword = 'documenso123';
    
    $conn = pg_connect("host=$pgHost port=$pgPort dbname=$pgDatabase user=$pgUser password=$pgPassword");
    if (!$conn) {
        throw new Exception("PostgreSQL connection failed");
    }
    
    // Обновляем статус envelope на PENDING
    $updateEnvelope = pg_query_params($conn, 
        'UPDATE "Envelope" SET status = $1 WHERE id = $2', 
        ['PENDING', $envelopeId]
    );
    
    // Обновляем sendStatus recipients на SENT
    $updateRecipients = pg_query_params($conn, 
        'UPDATE "Recipient" SET "sendStatus" = $1 WHERE "envelopeId" = $2',
        ['SENT', $envelopeId]
    );
    
    pg_close($conn);
    
    if (!$updateEnvelope || !$updateRecipients) {
        throw new Exception("Failed to update envelope status");
    }
    
    logDebug("Envelope status updated to PENDING", ['envelope_id' => $envelopeId]);
}

// =====================================
// ОСНОВНАЯ ЛОГИКА
// =====================================

try {
    checkApiKey();
    
    // Получение данных
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON');
    }
    
    // Генерируем номер договора если нет
    if (empty($data['contractNumber'])) {
        $data['contractNumber'] = 'MDOG-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    }
    
    logDebug("Starting contract creation", $data);
    
    // =====================================
    // ШАГ 1: Заполнение PDF
    // =====================================
    
    $filledPdfPath = fillPdfWithData($data);
    logDebug("PDF filled successfully", ['path' => $filledPdfPath]);
    
    // =====================================
    // ШАГ 2: Создание Envelope через API V2
    // =====================================
    
    $sellerEmail = $data['sellerEmail'] ?? $data['kennelEmail'] ?? '';
    $sellerName = $data['sellerName'] ?? $data['kennelName'] ?? $data['kennelOwner'] ?? 'Seller';
    $buyerEmail = $data['buyerEmail'] ?? 'buyer@example.com';
    $buyerName = $data['buyerName'] ?? 'Buyer';
    
    // Формируем payload для API
    $envelopePayload = [
        'type' => 'DOCUMENT',
        'title' => $data['contractNumber'] . '.pdf',
        'distributeDocument' => true, // Автоматически отправить email получателям
        'recipients' => []
    ];
    
    // Добавляем продавца если есть email
    if (!empty($sellerEmail)) {
        $envelopePayload['recipients'][] = [
            'email' => $sellerEmail,
            'name' => $sellerName,
            'role' => 'SIGNER',
            'fields' => [
                [
                    'type' => 'SIGNATURE',
                    'identifier' => 0, // Первый файл
                    'page' => 1,
                    'positionX' => 15,
                    'positionY' => 85,
                    'width' => 25,
                    'height' => 8
                ]
            ]
        ];
    }
    
    // Добавляем покупателя
    $envelopePayload['recipients'][] = [
        'email' => $buyerEmail,
        'name' => $buyerName,
        'role' => 'SIGNER',
        'fields' => [
            [
                'type' => 'SIGNATURE',
                'identifier' => 0,
                'page' => 1,
                'positionX' => 55,
                'positionY' => 85,
                'width' => 25,
                'height' => 8
            ]
        ]
    ];
    
    // Подготовка multipart данных
    $boundary = '----WebKitFormBoundary' . bin2hex(random_bytes(16));
    $multipartData = '';
    
    // Добавляем payload
    $multipartData .= "--$boundary\r\n";
    $multipartData .= "Content-Disposition: form-data; name=\"payload\"\r\n\r\n";
    $multipartData .= json_encode($envelopePayload) . "\r\n";
    
    // Добавляем PDF файл
    $pdfContent = file_get_contents($filledPdfPath);
    $multipartData .= "--$boundary\r\n";
    $multipartData .= "Content-Disposition: form-data; name=\"files\"; filename=\"contract.pdf\"\r\n";
    $multipartData .= "Content-Type: application/pdf\r\n\r\n";
    $multipartData .= $pdfContent . "\r\n";
    $multipartData .= "--$boundary--\r\n";
    
    // Отправляем запрос
    $ch = curl_init(DOCUMENSO_API_URL . '/envelope/create');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . DOCUMENSO_API_TOKEN,
            'Content-Type: multipart/form-data; boundary=' . $boundary
        ],
        CURLOPT_POSTFIELDS => $multipartData,
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Удаляем временный PDF
    @unlink($filledPdfPath);
    
    if ($curlError) {
        throw new Exception("API request failed: $curlError");
    }
    
    if ($httpCode < 200 || $httpCode >= 300) {
        logDebug("API Error Response", ['http_code' => $httpCode, 'response' => $response]);
        throw new Exception("API error (HTTP $httpCode): " . $response);
    }
    
    $envelope = json_decode($response, true);
    if (!$envelope || !isset($envelope['id'])) {
        throw new Exception("Invalid API response: " . $response);
    }
    
    logDebug("Envelope created successfully", $envelope);
    
    // =====================================
    // ШАГ 2.5: Обновление статуса на PENDING
    // =====================================
    
    updateEnvelopeStatus($envelope['id']);
    
    // =====================================
    // ШАГ 3: Получение signing URLs
    // =====================================
    
    // Получаем полную информацию о envelope
    $envelopeDetails = documensoApiRequest('/envelope/' . $envelope['id']);
    
    $signingUrls = [];
    foreach ($envelopeDetails['recipients'] as $recipient) {
        $signingUrls[] = [
            'email' => $recipient['email'],
            'name' => $recipient['name'],
            'token' => $recipient['token'],
            'url' => DOCUMENSO_BASE_URL . '/sign/' . $recipient['token']
        ];
    }
    
    // =====================================
    // РЕЗУЛЬТАТ (совместимый с contracts_api.php)
    // =====================================
    
    echo json_encode([
        'success' => true,
        'envelope_id' => $envelope['id'],
        'secondary_id' => $envelopeDetails['secondaryId'] ?? $envelope['id'],
        'title' => $envelope['title'] ?? $data['contractNumber'] . '.pdf',
        'status' => 'PENDING', // Обновлено автоматически
        
        // Совместимость с contracts_api.php
        'signing_url' => end($signingUrls)['url'], // URL покупателя (последний recipient)
        'seller_signing_url' => count($signingUrls) > 1 ? $signingUrls[0]['url'] : null,
        'seller_token' => count($signingUrls) > 1 ? $signingUrls[0]['token'] : null,
        'seller_email' => count($signingUrls) > 1 ? $signingUrls[0]['email'] : null,
        'recipient_id' => end($signingUrls)['email'], // Email покупателя
        
        // Дополнительная информация
        'signing_urls' => $signingUrls,
        'contract_number' => $data['contractNumber'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    logDebug("Contract created successfully", ['envelope_id' => $envelope['id']]);

} catch (Exception $e) {
    logDebug("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>

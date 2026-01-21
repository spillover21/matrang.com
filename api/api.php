<?php
// api/api.php - главный API для админ панели

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = strtolower($_GET['action'] ?? '');
$dataFile = __DIR__ . '/../data/content.json';
$uploadDir = __DIR__ . '/../uploads/';
$requestsLog = __DIR__ . '/../data/requests.log';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

// Если action не пришёл в query, пробуем достать из JSON
if ($action === '' && is_array($jsonInput) && isset($jsonInput['action'])) {
    $action = strtolower($jsonInput['action']);
}

// Фолбэк: если POST без action, но пришли поля формы, считаем это заявкой contact
if ($action === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && is_array($jsonInput)) {
    if (isset($jsonInput['name']) || isset($jsonInput['phone']) || isset($jsonInput['message'])) {
        $action = 'contact';
    }
}

// Проверка аутентификации
function checkAuth() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    
    // Простая проверка токена (в production используйте более безопасный метод)
    $correctHash = password_hash('admin_secret_key_2025', PASSWORD_DEFAULT);
    
    // Для демо просто проверим что токен передан
    return !empty($token) && $token !== '';
}

// Инициализация папки для данных
if (!is_dir(dirname($dataFile))) {
    mkdir(dirname($dataFile), 0755, true);
}

// Инициализация папки для загрузок
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Загрузка контента
if ($action === 'get') {
    if (file_exists($dataFile)) {
        $content = json_decode(file_get_contents($dataFile), true);
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $content]);
    } else {
        // Возвращаем пустую структуру если файла нет
        echo json_encode(['success' => true, 'data' => getDefaultContent()]);
    }
    exit();
}

// Сохранение контента
if ($action === 'save') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit();
    }

    if (file_put_contents($dataFile, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Content saved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save content']);
    }
    exit();
}

// Обработка заявки с контактной формы
if ($action === 'contact') {
    // Пытаемся достать данные из JSON, form-data или raw querystring тела
    $input = [];
    if (is_array($jsonInput)) {
        $input = $jsonInput;
    } else {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
    // Если JSON не раскодировался, пробуем form-urlencoded/POST
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    // Если всё ещё пусто, пробуем парсить raw тело как querystring
    if (empty($input) && !empty($rawInput)) {
        parse_str($rawInput, $parsed);
        if (is_array($parsed)) {
            $input = $parsed;
        }
    }

    $name = isset($input['name']) ? trim((string)$input['name']) : '';
    $phone = isset($input['phone']) ? trim((string)$input['phone']) : '';
    $message = isset($input['message']) ? trim((string)$input['message']) : '';

    if ($name === '' || $phone === '' || $message === '') {
        http_response_code(200);
        echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
        exit();
    }

    // Определяем email получателя из контента
    $recipient = 'info@example.com';
    if (file_exists($dataFile)) {
        $contentData = json_decode(file_get_contents($dataFile), true);
        if (!empty($contentData['contact']['email'])) {
            $recipient = $contentData['contact']['email'];
        }
    }

    $subject = 'Новая заявка с сайта MATRANG';
    $body = "Имя: {$name}\nТелефон: {$phone}\nСообщение: {$message}\nВремя: " . date('Y-m-d H:i:s');
    $headers = "Content-Type: text/plain; charset=utf-8" . "\r\n";

    // Пишем лог вне зависимости от mail()
    if (!is_dir(dirname($requestsLog))) {
        mkdir(dirname($requestsLog), 0755, true);
    }
    file_put_contents($requestsLog, "[" . date('Y-m-d H:i:s') . "] {$name} | {$phone} | {$message}\n", FILE_APPEND);

    $sent = @mail($recipient, $subject, $body, $headers);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $sent ? 'Отправлено' : 'Сохранено. Отправка письма может быть отключена на сервере.'
    ]);
    exit();
}

// Фолбэк: если пришёл POST и не совпадает с известными action, но содержит поля формы
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !in_array($action, ['save', 'upload', 'login', 'contact'], true)
) {
    $input = [];
    if (is_array($jsonInput)) {
        $input = $jsonInput;
    } else {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    if (empty($input) && !empty($rawInput)) {
        parse_str($rawInput, $parsed);
        if (is_array($parsed)) {
            $input = $parsed;
        }
    }

    if (isset($input['name']) || isset($input['phone']) || isset($input['message'])) {
        // Логируем, чтобы понять откуда пришло
        if (!is_dir(dirname($requestsLog))) {
            mkdir(dirname($requestsLog), 0755, true);
        }
        file_put_contents($requestsLog, "[" . date('Y-m-d H:i:s') . "] fallback contact | action={$action} | body=" . $rawInput . "\n", FILE_APPEND);

        // Передаём в обработчик contact, подставив action
        $jsonInput = $input;
        $action = 'contact';
        // и повторно выполняем блок contact
        // (простая рекурсия через include невозможна, поэтому дублируем вызов)
        $name = isset($input['name']) ? trim((string)$input['name']) : '';
        $phone = isset($input['phone']) ? trim((string)$input['phone']) : '';
        $message = isset($input['message']) ? trim((string)$input['message']) : '';

        if ($name === '' || $phone === '' || $message === '') {
            http_response_code(200);
            echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
            exit();
        }

        $recipient = 'info@example.com';
        if (file_exists($dataFile)) {
            $contentData = json_decode(file_get_contents($dataFile), true);
            if (!empty($contentData['contact']['email'])) {
                $recipient = $contentData['contact']['email'];
            }
        }

        $subject = 'Новая заявка с сайта MATRANG (fallback)';
        $body = "Имя: {$name}\nТелефон: {$phone}\nСообщение: {$message}\nВремя: " . date('Y-m-d H:i:s');
        $headers = "Content-Type: text/plain; charset=utf-8" . "\r\n";

        if (!is_dir(dirname($requestsLog))) {
            mkdir(dirname($requestsLog), 0755, true);
        }
        file_put_contents($requestsLog, "[" . date('Y-m-d H:i:s') . "] {$name} | {$phone} | {$message} | fallback\n", FILE_APPEND);

        $sent = @mail($recipient, $subject, $body, $headers);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $sent ? 'Отправлено' : 'Сохранено. Отправка письма может быть отключена на сервере.'
        ]);
        exit();
    }
}

// Загрузка изображения
if ($action === 'upload') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file provided']);
        exit();
    }

    $file = $_FILES['file'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit();
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large']);
        exit();
    }

    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $url = '/uploads/' . $filename;
        http_response_code(200);
        echo json_encode(['success' => true, 'url' => $url, 'filename' => $filename]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
    exit();
}

// Проверка пароля
if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    
    // Проверка пароля (по умолчанию "admin")
    if ($password === 'admin') {
        $token = bin2hex(random_bytes(32));
        
        // Сохраняем токен (в production используйте сессии или JWT)
        $_SESSION['admin_token'] = $token;
        
        http_response_code(200);
        echo json_encode(['success' => true, 'token' => $token]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
    exit();
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);

function getDefaultContent() {
    return [
        'header' => [
            'favicon' => '/favicon.ico',
            'logo' => 'PITBULL ELITE',
            'links' => ['О породе', 'Галерея', 'Контакты']
        ],
        'hero' => [
            'title' => 'ПИТБУЛИ',
            'subtitle' => 'Мощь и благородство',
            'image' => '/assets/hero-pitbull.jpg'
        ],
        'about' => [
            'title' => 'О породе',
            'content' => 'Питбули - это мощные и преданные собаки...',
            'image' => '/assets/pitbull-1.jpg'
        ],
        'gallery' => [
            'title' => 'Галерея',
            'images' => [
                '/assets/pitbull-1.jpg',
                '/assets/pitbull-2.jpg',
                '/assets/pitbull-3.jpg'
            ]
        ],
        'contact' => [
            'title' => 'Контакты',
            'email' => 'info@pitbull.com',
            'phone' => '+7-900-000-00-00',
            'address' => 'Город, улица'
        ],
        'footer' => [
            'copyright' => '© 2025 PITBULL ELITE',
            'links' => ['Главная', 'О нас', 'Контакты']
        ]
    ];
}

// ===== ENDPOINTS ДЛЯ ДОГОВОРОВ =====

if ($action === 'getcontracts') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $templatesFile = __DIR__ . '/../data/contract_templates.json';
    
    $contracts = file_exists($contractsFile) ? json_decode(file_get_contents($contractsFile), true) : [];
    $templates = file_exists($templatesFile) ? json_decode(file_get_contents($templatesFile), true) : [];
    
    echo json_encode([
        'success' => true,
        'contracts' => $contracts,
        'templates' => $templates
    ]);
    exit;
}

if ($action === 'savecontracttemplate') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    $templatesFile = __DIR__ . '/../data/contract_templates.json';
    $templates = file_exists($templatesFile) ? json_decode(file_get_contents($templatesFile), true) : [];
    
    $newTemplate = [
        'id' => time(),
        'name' => $jsonInput['name'] ?? 'Шаблон',
        'data' => $jsonInput['data'] ?? [],
        'createdAt' => date('Y-m-d H:i:s')
    ];
    
    $templates[] = $newTemplate;
    file_put_contents($templatesFile, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => true, 'template' => $newTemplate]);
    exit;
}

if ($action === 'deletecontracttemplate') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    $templatesFile = __DIR__ . '/../data/contract_templates.json';
    $templates = file_exists($templatesFile) ? json_decode(file_get_contents($templatesFile), true) : [];
    
    $id = $jsonInput['id'] ?? 0;
    $templates = array_filter($templates, function($t) use ($id) {
        return $t['id'] != $id;
    });
    
    file_put_contents($templatesFile, json_encode(array_values($templates), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => true]);
    exit;
}

// Загрузка PDF шаблона
if ($action === 'uploadpdftemplate') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No PDF file uploaded']);
        http_response_code(400);
        exit;
    }
    
    $file = $_FILES['pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'pdf') {
        echo json_encode(['success' => false, 'message' => 'Only PDF files allowed']);
        http_response_code(400);
        exit;
    }
    
    // Создаем папку для контрактов
    $contractsDir = $uploadDir . 'contracts/';
    if (!is_dir($contractsDir)) {
        mkdir($contractsDir, 0755, true);
    }
    
    $filename = 'contract_template.pdf';
    $uploadPath = $contractsDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $url = '/uploads/contracts/' . $filename;
        echo json_encode(['success' => true, 'url' => $url]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save PDF']);
        http_response_code(500);
    }
    exit;
}

// Отправка договора через Adobe Sign
if ($action === 'sendcontractpdf') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    // Загружаем конфигурацию Adobe Sign
    $adobeSignConfig = require __DIR__ . '/adobe_sign_config.php';
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $contracts = file_exists($contractsFile) ? json_decode(file_get_contents($contractsFile), true) : [];
    
    $contractNumber = 'DOG-' . date('Y') . '-' . str_pad(count($contracts) + 1, 4, '0', STR_PAD_LEFT);
    
    $data = $jsonInput['data'] ?? [];
    $pdfTemplate = $jsonInput['pdfTemplate'] ?? '';
    
    if (!$pdfTemplate) {
        echo json_encode(['success' => false, 'message' => 'PDF template required']);
        http_response_code(400);
        exit;
    }
    
    // Если Adobe Sign не настроен, используем email-рассылку как fallback
    if (!$adobeSignConfig['enabled'] || empty($adobeSignConfig['access_token'])) {
        $newContract = [
            'id' => time(),
            'contractNumber' => $contractNumber,
            'data' => $data,
            'createdAt' => date('Y-m-d H:i:s'),
            'sentAt' => date('Y-m-d H:i:s'),
            'signedAt' => null,
            'signedDocumentUrl' => null,
            'status' => 'sent_by_email'
        ];
        
        $contracts[] = $newContract;
        file_put_contents($contractsFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Отправка email с PDF (упрощенная версия)
        $buyerEmail = $data['buyerEmail'] ?? '';
        $buyerName = $data['buyerName'] ?? '';
        $dogName = $data['dogName'] ?? '';
        
        if ($buyerEmail) {
            $subject = "Договор купли-продажи щенка - №{$contractNumber}";
            $message = "Здравствуйте, {$buyerName}!\n\n";
            $message .= "Вам направлен договор купли-продажи щенка {$dogName}.\n";
            $message .= "Номер договора: {$contractNumber}\n\n";
            $message .= "Для подписания договора, пожалуйста, ознакомьтесь с документом.\n\n";
            $message .= "С уважением,\nПитомник GREAT LEGACY BULLY";
            
            $headers = "From: noreply@matrang.com\r\n";
            $headers .= "Reply-To: " . ($data['kennelEmail'] ?? 'info@matrang.com') . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            @mail($buyerEmail, $subject, $message, $headers);
        }
        
        echo json_encode(['success' => true, 'contract' => $newContract, 'note' => 'Sent by email (Adobe Sign not configured)']);
        exit;
    }
    
    // Интеграция с Adobe Sign API
    try {
        // Шаг 1: Загрузить документ на Adobe Sign
        $pdfPath = __DIR__ . '/..' . $pdfTemplate;
        
        if (!file_exists($pdfPath)) {
            throw new Exception('PDF template not found');
        }
        
        // Загрузка transient document
        $ch = curl_init($adobeSignConfig['base_url'] . '/transientDocuments');
        $cfile = new CURLFile($pdfPath, 'application/pdf', 'contract.pdf');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['File' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $adobeSignConfig['access_token']
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception('Failed to upload document to Adobe Sign');
        }
        
        $transientDoc = json_decode($response, true);
        $transientDocumentId = $transientDoc['transientDocumentId'];
        
        // Шаг 2: Создать соглашение
        $agreementData = [
            'fileInfos' => [[
                'transientDocumentId' => $transientDocumentId
            ]],
            'name' => "Договор купли-продажи щенка - №{$contractNumber}",
            'participantSetsInfo' => [[
                'order' => 1,
                'role' => 'SIGNER',
                'memberInfos' => [[
                    'email' => $data['buyerEmail'] ?? '',
                    'name' => $data['buyerName'] ?? ''
                ]]
            ]],
            'signatureType' => 'ESIGN',
            'state' => 'IN_PROCESS',
            'mergeFieldInfo' => [
                // Здесь можно добавить данные для заполнения полей PDF
                ['fieldName' => 'contractNumber', 'defaultValue' => $contractNumber],
                ['fieldName' => 'dogName', 'defaultValue' => $data['dogName'] ?? ''],
                ['fieldName' => 'buyerName', 'defaultValue' => $data['buyerName'] ?? ''],
                ['fieldName' => 'buyerPassport', 'defaultValue' => $data['buyerPassport'] ?? ''],
                ['fieldName' => 'buyerAddress', 'defaultValue' => $data['buyerAddress'] ?? ''],
                ['fieldName' => 'price', 'defaultValue' => $data['price'] ?? ''],
                ['fieldName' => 'date', 'defaultValue' => date('d.m.Y')]
            ]
        ];
        
        $ch = curl_init($adobeSignConfig['base_url'] . '/agreements');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($agreementData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $adobeSignConfig['access_token'],
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception('Failed to create agreement: ' . $response);
        }
        
        $agreement = json_decode($response, true);
        $agreementId = $agreement['id'];
        
        // Сохраняем контракт в базе
        $newContract = [
            'id' => time(),
            'contractNumber' => $contractNumber,
            'data' => $data,
            'createdAt' => date('Y-m-d H:i:s'),
            'sentAt' => date('Y-m-d H:i:s'),
            'signedAt' => null,
            'signedDocumentUrl' => null,
            'adobeSignAgreementId' => $agreementId,
            'status' => 'sent'
        ];
        
        $contracts[] = $newContract;
        file_put_contents($contractsFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            'success' => true,
            'contract' => $newContract,
            'agreementId' => $agreementId
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        http_response_code(500);
    }
    
    exit;
}

// Webhook для получения уведомлений от Adobe Sign
if ($action === 'adobesignwebhook') {
    // Логируем все входящие webhook для отладки
    $webhookLog = __DIR__ . '/../data/adobe_sign_webhooks.log';
    file_put_contents($webhookLog, date('Y-m-d H:i:s') . ': ' . $rawInput . "\n", FILE_APPEND);
    
    $event = $jsonInput['event'] ?? '';
    $agreementId = $jsonInput['agreement']['id'] ?? '';
    
    if ($event === 'AGREEMENT_WORKFLOW_COMPLETED' && $agreementId) {
        // Обновляем статус контракта
        $contractsFile = __DIR__ . '/../data/contracts.json';
        $contracts = file_exists($contractsFile) ? json_decode(file_get_contents($contractsFile), true) : [];
        
        foreach ($contracts as &$contract) {
            if (isset($contract['adobeSignAgreementId']) && $contract['adobeSignAgreementId'] === $agreementId) {
                $contract['signedAt'] = date('Y-m-d H:i:s');
                $contract['status'] = 'signed';
                
                // Можно скачать подписанный документ через Adobe Sign API
                // и сохранить URL
                $contract['signedDocumentUrl'] = '/uploads/contracts/signed_' . $contract['contractNumber'] . '.pdf';
                break;
            }
        }
        
        file_put_contents($contractsFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'sendcontract') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $contracts = file_exists($contractsFile) ? json_decode(file_get_contents($contractsFile), true) : [];
    
    $contractNumber = 'DOG-' . date('Y') . '-' . str_pad(count($contracts) + 1, 4, '0', STR_PAD_LEFT);
    
    $newContract = [
        'id' => time(),
        'contractNumber' => $contractNumber,
        'data' => $jsonInput['data'] ?? [],
        'createdAt' => date('Y-m-d H:i:s'),
        'sentAt' => date('Y-m-d H:i:s'),
        'signedAt' => null,
        'signedDocumentUrl' => null
    ];
    
    $contracts[] = $newContract;
    file_put_contents($contractsFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Отправка email
    $data = $jsonInput['data'];
    $buyerEmail = $data['buyerEmail'] ?? '';
    $buyerName = $data['buyerName'] ?? '';
    $dogName = $data['dogName'] ?? '';
    
    if ($buyerEmail) {
        $subject = "Договор купли-продажи щенка - №{$contractNumber}";
        $message = "Здравствуйте, {$buyerName}!\n\n";
        $message .= "Вам направлен договор купли-продажи щенка {$dogName}.\n";
        $message .= "Номер договора: {$contractNumber}\n\n";
        $message .= "Для подписания договора, пожалуйста, ознакомьтесь с документом и подтвердите согласие.\n\n";
        $message .= "С уважением,\nПитомник GREAT LEGACY BULLY";
        
        $headers = "From: noreply@matrang.com\r\n";
        $headers .= "Reply-To: " . ($data['kennelEmail'] ?? 'info@matrang.com') . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        @mail($buyerEmail, $subject, $message, $headers);
    }
    
    echo json_encode(['success' => true, 'contract' => $newContract]);
    exit;
}

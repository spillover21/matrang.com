<?php
// api/api.php - главный API для админ панели

// 0. GLOBAL DEBUG LOG (TEMPORARY)
$globalDebugLog = __DIR__ . '/global_debug.log';
file_put_contents($globalDebugLog, "Request: " . date('H:i:s') . " - Action: " . ($_GET['action'] ?? 'none') . "\n", FILE_APPEND);

// Подключение автозагрузчика (важно для PHPMailer)
// Если папка vendor лежит в api/vendor
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Если папка vendor лежит в корне public_html/vendor
    require_once __DIR__ . '/../vendor/autoload.php';
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// PHPMailer для отправки email
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';
$dataFile = __DIR__ . '/../data/content.json';
$uploadDir = __DIR__ . '/../uploads/';

// SELLER PROFILE ENDPOINTS
if ($action === 'save_seller_profile') {
    $input = json_decode(file_get_contents('php://input'), true);
    $profileFile = __DIR__ . '/../data/seller_profile.json';
    
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    if ($input && is_array($input)) {
        file_put_contents($profileFile, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => 'Profile saved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
    }
    exit();
}

if ($action === 'get_seller_profile') {
    $profileFile = __DIR__ . '/../data/seller_profile.json';
    
    header('Content-Type: application/json; charset=utf-8');
    if (file_exists($profileFile)) {
        echo file_get_contents($profileFile);
    } else {
        echo json_encode([]);
    }
    exit();
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

// Синхронизация статуса договора с Documenso
if ($action === 'syncContractStatus') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $contractId = $_GET['id'] ?? '';
    if (!$contractId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Contract ID required']);
        exit();
    }

    try {
        require_once __DIR__ . '/DocumensoBridgeClient.php';
        
        $contractsFile = __DIR__ . '/../data/contracts.json';
        if (!file_exists($contractsFile)) {
            throw new Exception('Contracts file not found');
        }
        
        $data = json_decode(file_get_contents($contractsFile), true);
        $contracts = $data['contracts'] ?? [];
        $templates = $data['templates'] ?? [];
        
        // Находим договор
        $found = false;
        foreach ($contracts as &$contract) {
            if ($contract['id'] === $contractId && isset($contract['adobeSignAgreementId'])) {
                $documentId = $contract['adobeSignAgreementId'];
                
                // Получаем статус от Documenso
                $bridge = new DocumensoBridgeClient();
                $envelope = $bridge->getEnvelope($documentId);
                
                if ($envelope['success'] && isset($envelope['envelope']['status'])) {
                    $docStatus = $envelope['envelope']['status'];
                    
                    // Маппинг статусов Documenso
                    if ($docStatus === 'COMPLETED') {
                        $contract['status'] = 'signed';
                        $contract['signedAt'] = date('c');
                        
                        // Пытаемся получить ссылку на подписанный документ
                        if (isset($envelope['envelope']['document_url'])) {
                            $contract['signedDocumentUrl'] = $envelope['envelope']['document_url'];
                        }
                    } elseif ($docStatus === 'REJECTED' || $docStatus === 'DECLINED') {
                        $contract['status'] = 'rejected';
                    } elseif ($docStatus === 'SENT' || $docStatus === 'PENDING') {
                        $contract['status'] = 'sent';
                    }
                    
                    $found = true;
                }
                break;
            }
        }
        
        if ($found) {
            $saveData = ['contracts' => $contracts, 'templates' => $templates];
            file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            echo json_encode(['success' => true, 'message' => 'Status synchronized']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contract not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Получение контрактов и шаблонов
// Удаление договора
if ($action === 'deleteContract') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $contractId = $_GET['id'] ?? '';
    if (!$contractId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Contract ID required']);
        exit();
    }

    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    if (file_exists($contractsFile)) {
        $fileContent = file_get_contents($contractsFile);
        $data = json_decode($fileContent, true);
        
        $contracts = [];
        $templates = [];
        
        // Определяем формат
        if (isset($data['contracts']) && isset($data['templates'])) {
            $contracts = $data['contracts'];
            $templates = $data['templates'];
        } else if (is_array($data)) {
            $contracts = $data;
        }
        
        // Удаляем договор
        $contracts = array_filter($contracts, function($c) use ($contractId) {
            return $c['id'] !== $contractId;
        });
        
        // Перенумеровываем массив
        $contracts = array_values($contracts);
        
        // Сохраняем
        $saveData = empty($templates) ? $contracts : ['contracts' => $contracts, 'templates' => $templates];
        file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode(['success' => true, 'message' => 'Contract deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contracts file not found']);
    }
    exit();
}

if ($action === 'getContracts') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $contractsFile = __DIR__ . '/../data/contracts.json';
    $pdfTemplateFile = __DIR__ . '/../uploads/pdf_template.pdf';
    
    // Загружаем контракты
    $contracts = [];
    $templates = [];
    
    if (file_exists($contractsFile)) {
        $fileContent = file_get_contents($contractsFile);
        $data = json_decode($fileContent, true);
        
        // Проверяем формат: старый (массив) или новый (объект с contracts и templates)
        if (isset($data['contracts']) && isset($data['templates'])) {
            // Новый формат
            $contracts = $data['contracts'];
            $templates = $data['templates'];
        } else if (is_array($data) && isset($data[0])) {
            // Старый формат - массив контрактов
            $contracts = $data;
            // Добавляем шаблон по умолчанию
            $templates = [
                [
                    'id' => 'template-1',
                    'name' => 'Стандартный договор',
                    'fields' => [
                        ['id' => 'buyerName', 'label' => 'ФИО покупателя', 'type' => 'text', 'x' => 100, 'y' => 150, 'fontSize' => 12],
                        ['id' => 'sellerName', 'label' => 'ФИО продавца', 'type' => 'text', 'x' => 100, 'y' => 200, 'fontSize' => 12],
                        ['id' => 'dogName', 'label' => 'Кличка собаки', 'type' => 'text', 'x' => 100, 'y' => 250, 'fontSize' => 12],
                        ['id' => 'price', 'label' => 'Цена', 'type' => 'text', 'x' => 100, 'y' => 300, 'fontSize' => 12],
                        ['id' => 'date', 'label' => 'Дата', 'type' => 'date', 'x' => 100, 'y' => 350, 'fontSize' => 12]
                    ]
                ]
            ];
        }
    } else {
        // Файл не существует - создаем шаблон по умолчанию
        $templates = [
            [
                'id' => 'template-1',
                'name' => 'Стандартный договор',
                'fields' => [
                    ['id' => 'buyerName', 'label' => 'ФИО покупателя', 'type' => 'text', 'x' => 100, 'y' => 150, 'fontSize' => 12],
                    ['id' => 'sellerName', 'label' => 'ФИО продавца', 'type' => 'text', 'x' => 100, 'y' => 200, 'fontSize' => 12],
                    ['id' => 'dogName', 'label' => 'Кличка собаки', 'type' => 'text', 'x' => 100, 'y' => 250, 'fontSize' => 12],
                    ['id' => 'price', 'label' => 'Цена', 'type' => 'text', 'x' => 100, 'y' => 300, 'fontSize' => 12],
                    ['id' => 'date', 'label' => 'Дата', 'type' => 'date', 'x' => 100, 'y' => 350, 'fontSize' => 12]
                ]
            ]
        ];
    }
    
    // Проверяем наличие PDF шаблона
    $pdfTemplate = '';
    $pdfTemplateRu = '';
    $pdfTemplateEn = '';
    
    // RU (Default)
    if (file_exists($pdfTemplateFile)) {
        $pdfTemplate = '/uploads/pdf_template.pdf?t=' . time();
        $pdfTemplateRu = $pdfTemplate;
    }
    
    // EN
    $pdfTemplateEnFile = __DIR__ . '/../uploads/pdf_template_en.pdf';
    if (file_exists($pdfTemplateEnFile)) {
        $pdfTemplateEn = '/uploads/pdf_template_en.pdf?t=' . time();
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'contracts' => $contracts,
        'templates' => $templates,
        'pdfTemplate' => $pdfTemplate, // Keep for backward compatibility
        'pdfTemplateRu' => $pdfTemplateRu,
        'pdfTemplateEn' => $pdfTemplateEn
    ]);
    exit();
}

// Удаление шаблона контракта
if ($action === 'deleteContractTemplate') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $templateId = $_GET['id'] ?? '';
    if (!$templateId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        exit();
    }

    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    if (file_exists($contractsFile)) {
        $data = json_decode(file_get_contents($contractsFile), true);
        
        $contracts = [];
        $templates = [];
        
        // Определяем формат
        if (isset($data['contracts']) && isset($data['templates'])) {
            $contracts = $data['contracts'];
            $templates = $data['templates'];
        } else if (is_array($data)) {
            $contracts = $data;
        }
        
        // Удаляем шаблон
        $templates = array_filter($templates, function($t) use ($templateId) {
            return $t['id'] !== $templateId;
        });
        
        // Перенумеровываем массив
        $templates = array_values($templates);
        
        // Сохраняем
        $saveData = ['contracts' => $contracts, 'templates' => $templates];
        file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode(['success' => true, 'message' => 'Template deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contracts file not found']);
    }
    exit();
}

// DOCUMENSO INTEGRATION
if ($action === 'createDocumensoSigning') {
    // Включаем перехват фатальных ошибок
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
            // Очищаем буфер вывода, если там был мусор
            while (ob_get_level()) ob_end_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Critical PHP Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']]);
            exit();
        }
    });

    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Извлекаем все поля договора
    $contractData = [
        'buyerEmail' => $input['buyerEmail'] ?? $input['email'] ?? '',
        'buyerName' => $input['buyerName'] ?? $input['name'] ?? 'Customer',
        'internalId' => $input['internalId'] ?? 'user_'.time(),
        
        // Все остальные поля из формы
        'contractDate' => $input['contractDate'] ?? date('d.m.Y'),
        'contractPlace' => $input['contractPlace'] ?? '',
        
        'kennelOwner' => $input['kennelOwner'] ?? '',
        'kennelAddress' => $input['kennelAddress'] ?? '',
        'kennelPhone' => $input['kennelPhone'] ?? '',
        'kennelEmail' => $input['kennelEmail'] ?? '',
        'kennelPassportSeries' => $input['kennelPassportSeries'] ?? '',
        'kennelPassportNumber' => $input['kennelPassportNumber'] ?? '',
        'kennelPassportIssuedBy' => $input['kennelPassportIssuedBy'] ?? '',
        'kennelPassportIssuedDate' => $input['kennelPassportIssuedDate'] ?? '',
        
        'buyerAddress' => $input['buyerAddress'] ?? '',
        'buyerPhone' => $input['buyerPhone'] ?? '',
        'buyerPassportSeries' => $input['buyerPassportSeries'] ?? '',
        'buyerPassportNumber' => $input['buyerPassportNumber'] ?? '',
        'buyerPassportIssuedBy' => $input['buyerPassportIssuedBy'] ?? '',
        'buyerPassportIssuedDate' => $input['buyerPassportIssuedDate'] ?? '',
        
        'dogFatherName' => $input['dogFatherName'] ?? '',
        'dogFatherRegNumber' => $input['dogFatherRegNumber'] ?? '',
        'dogMotherName' => $input['dogMotherName'] ?? '',
        'dogMotherRegNumber' => $input['dogMotherRegNumber'] ?? '',
        
        'dogName' => $input['dogName'] ?? '',
        'dogBirthDate' => $input['dogBirthDate'] ?? '',
        'dogColor' => $input['dogColor'] ?? '',
        'dogChipNumber' => $input['dogChipNumber'] ?? '',
        'dogPuppyCard' => $input['dogPuppyCard'] ?? '',
        
        'purposeBreeding' => $input['purposeBreeding'] ?? false,
        'purposeCompanion' => $input['purposeCompanion'] ?? false,
        'purposeGeneral' => $input['purposeGeneral'] ?? false,
        
        'price' => $input['price'] ?? '',
        'depositAmount' => $input['depositAmount'] ?? '',
        'depositDate' => $input['depositDate'] ?? '',
        'remainingAmount' => $input['remainingAmount'] ?? '',
        'finalPaymentDate' => $input['finalPaymentDate'] ?? '',
        
        'dewormingDate' => $input['dewormingDate'] ?? '',
        'vaccinationDates' => $input['vaccinationDates'] ?? '',
        'vaccineName' => $input['vaccineName'] ?? '',
        'nextDewormingDate' => $input['nextDewormingDate'] ?? '',
        'nextVaccinationDate' => $input['nextVaccinationDate'] ?? '',
        
        'specialFeatures' => $input['specialFeatures'] ?? '',
        'deliveryTerms' => $input['deliveryTerms'] ?? '',
        'additionalAgreements' => $input['additionalAgreements'] ?? '',
        'recommendedFood' => $input['recommendedFood'] ?? ''
    ];

    if (empty($contractData['buyerEmail'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Buyer email is required']);
        exit();
    }

    try {
        if (!file_exists(__DIR__ . '/DocumensoService.php')) {
            throw new Exception("DocumensoService.php not found");
        }
        require_once __DIR__ . '/DocumensoService.php';
        
        $service = new DocumensoService();
        $result = $service->createSigningSession($contractData);
        
        echo json_encode([
            'success' => true,
            'url' => $result['signingUrl'],
            'documentId' => $result['documentId']
        ]);
    } catch (Exception $e) {
        http_response_code(500); 
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Сохранение контрактов и шаблонов
if ($action === 'saveContracts') {
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

    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    // Загружаем существующие данные
    $existingData = [];
    if (file_exists($contractsFile)) {
        $existingData = json_decode(file_get_contents($contractsFile), true);
        
        // Если старый формат (массив), конвертируем в новый
        if (is_array($existingData) && !isset($existingData['contracts'])) {
            $existingData = [
                'contracts' => $existingData,
                'templates' => []
            ];
        }
    } else {
        $existingData = [
            'contracts' => [],
            'templates' => []
        ];
    }
    
    // Обновляем только переданные поля
    if (isset($input['contracts'])) {
        $existingData['contracts'] = $input['contracts'];
    }
    if (isset($input['templates'])) {
        $existingData['templates'] = $input['templates'];
    }
    
    if (file_put_contents($contractsFile, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Contracts saved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save contracts']);
    }
    exit();
}

// Сохранение шаблона контракта
if ($action === 'saveContractTemplate') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['name']) || !isset($input['data'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    // Загружаем существующие данные
    $existingData = [];
    if (file_exists($contractsFile)) {
        $existingData = json_decode(file_get_contents($contractsFile), true);
        
        // Если старый формат (массив), конвертируем в новый
        if (is_array($existingData) && !isset($existingData['contracts'])) {
            $existingData = [
                'contracts' => $existingData,
                'templates' => []
            ];
        }
    } else {
        $existingData = [
            'contracts' => [],
            'templates' => []
        ];
    }
    
    // Добавляем новый шаблон
    $newTemplate = [
        'id' => 'template-' . time(),
        'name' => $input['name'],
        'data' => $input['data']
    ];
    
    $existingData['templates'][] = $newTemplate;
    
    if (file_put_contents($contractsFile, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Template saved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save template']);
    }
    exit();
}

// Загрузка PDF шаблона
if ($action === 'uploadPdfTemplate') {
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
    
    if ($file['type'] !== 'application/pdf') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit();
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10MB
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large']);
        exit();
    }

    $filepath = $uploadDir . 'pdf_template.pdf';

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $url = '/uploads/pdf_template.pdf';
        http_response_code(200);
        echo json_encode(['success' => true, 'url' => $url]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
    exit();
}

// Загрузка заполненного PDF контракта
if ($action === 'uploadcontract') {
    // TODO: Временно отключена проверка токена для отладки
    // Проверка токена из query параметра (для FormData запросов)
    // $token = $_GET['token'] ?? '';
    // if (empty($token)) {
    //     http_response_code(401);
    //     echo json_encode(['success' => false, 'message' => 'Unauthorized - no token']);
    //     exit();
    // }

    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file provided']);
        exit();
    }

    $file = $_FILES['file'];
    
    if ($file['type'] !== 'application/pdf') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit();
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10MB
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large']);
        exit();
    }

    // Сохраняем с уникальным именем
    $filename = 'contract_' . time() . '.pdf';
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

// Отправка контракта в PDF по email + создание envelope в Documenso
if ($action === 'sendContractPdf') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Новый формат: просто данные договора (data)
    if (!$input || !isset($input['data'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid request - missing data',
            'debug' => [
                'hasInput' => !empty($input),
                'receivedKeys' => $input ? array_keys($input) : []
            ]
        ]);
        exit();
    }
    
    // Подготавливаем данные для VPS
    $contractData = $input['data'];

    // Inject template language/filename for VPS
    if (isset($input['templateLang'])) {
        $contractData['templateLang'] = $input['templateLang'];
        // Также определяем имя файла, чтобы VPS знал что искать
        $contractData['templateFilename'] = ($input['templateLang'] === 'en') ? 'pdf_template_en.pdf' : 'pdf_template.pdf';
    }

    // --- FIX: Generate Sequential Contract Number ---
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $nextNum = 1;
    $currentYear = date('Y');
    
    if (file_exists($contractsFile)) {
        $jsonContent = file_get_contents($contractsFile);
        $jsonData = json_decode($jsonContent, true);
        
        $existingContracts = [];
        if (isset($jsonData['contracts'])) {
            $existingContracts = $jsonData['contracts'];
        } elseif (is_array($jsonData)) {
            $existingContracts = $jsonData;
        }

        foreach ($existingContracts as $c) {
            // Check for contractNumber in top level OR in data
            $cNum = $c['contractNumber'] ?? ($c['data']['contractNumber'] ?? '');
            
            if ($cNum && preg_match('/DOG-' . $currentYear . '-(\d+)/', $cNum, $matches)) {
                $num = intval($matches[1]);
                if ($num >= $nextNum) {
                    $nextNum = $num + 1;
                }
            }
        }
    }
    
    // Generate new ID
    $newContractNumber = 'DOG-' . $currentYear . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    
    // Add to payload
    $contractData['contractNumber'] = $newContractNumber;
    // --- END FIX ---
    
    // DEBUG LOG
    file_put_contents(__DIR__ . '/debug_api.log', date('Y-m-d H:i:s') . " - Processing sendContractPdf with " . $newContractNumber . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_api.log', "Data: " . json_encode($contractData) . "\n", FILE_APPEND);
    
    // Отправляем на VPS для создания envelope в Documenso
    try {
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
        
        file_put_contents(__DIR__ . '/debug_api.log', "CURL Response (" . $httpCode . "): " . $response . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/debug_api.log', "CURL Error: " . $curlError . "\n", FILE_APPEND);
        
        if ($curlError) {
            throw new Exception('VPS connection error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('VPS returned HTTP ' . $httpCode . ': ' . $response);
        }
        
        $vpsResult = json_decode($response, true);
        
        if (!$vpsResult || !$vpsResult['success']) {
            throw new Exception($vpsResult['error'] ?? 'Unknown error from VPS');
        }
        
        // Сохраняем договор в локальную БД
        // $contractsFile уже определен выше
        $existingData = [];
        
        if (file_exists($contractsFile)) {
            $existingData = json_decode(file_get_contents($contractsFile), true);
            
            if (is_array($existingData) && !isset($existingData['contracts'])) {
                $existingData = [
                    'contracts' => $existingData,
                    'templates' => []
                ];
            }
        } else {
            $existingData = [
                'contracts' => [],
                'templates' => []
            ];
        }
        
        // Добавляем новый договор
        $newContract = [
            'id' => $vpsResult['envelope_id'],
            'contractNumber' => $newContractNumber, // <--- SAVED HERE
            'data' => $contractData,
            'createdAt' => date('c'),
            'sentAt' => date('c'),
            'documensoUrl' => $vpsResult['document_url'],
            's3Path' => $vpsResult['s3_path'],
            'status' => 'sent'
        ];
        
        $existingData['contracts'][] = $newContract;
        
        file_put_contents($contractsFile, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'contract' => $newContract,
            'emailSent' => false, // Email пока отключен, используем Documenso
            'message' => 'Договор создан в Documenso',
            'vpsResponse' => $vpsResult
        ]);
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create contract: ' . $e->getMessage()
        ]);
        exit();
    }
    
    /* СТАРЫЙ КОД ОТПРАВКИ EMAIL - ВРЕМЕННО ОТКЛЮЧЕН
    if (!isset($input['email']) || !isset($input['pdfData'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid request',
            'debug' => [
                'hasInput' => !empty($input),
                'hasEmail' => isset($input['email']),
                'hasPdfData' => isset($input['pdfData']),
                'receivedKeys' => $input ? array_keys($input) : []
            ]
        ]);
        exit();
    }
    */

    // Реальная отправка email через PHPMailer (SMTP)
    require_once __DIR__ . '/vendor/autoload.php';
    
    $email = $input['email'];
    $pdfUrl = $input['pdfData'];
    $smtpConfig = require __DIR__ . '/smtp_config.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP настройки
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = $smtpConfig['auth'];
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = $smtpConfig['encryption'];
        $mail->Port = $smtpConfig['port'];
        $mail->CharSet = 'UTF-8';
        
        // От кого
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addReplyTo($smtpConfig['reply_to'], $smtpConfig['from_name']);
        
        // Кому
        $mail->addAddress($email);
        
        // Содержимое письма
        $mail->isHTML(true);
        $mail->Subject = 'Договор купли-продажи щенка';
        $mail->Body = '
            <html>
            <body style="font-family: Arial, sans-serif;">
                <h2>Договор купли-продажи щенка</h2>
                <p>Здравствуйте!</p>
                <p>Во вложении находится ваш договор купли-продажы щенка.</p>
                <p><a href="https://matrang.com' . htmlspecialchars($pdfUrl) . '" style="background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Скачать договор (PDF)</a></p>
                <p>С уважением,<br><strong>Great Legacy Bully</strong></p>
            </body>
            </html>
        ';
        $mail->AltBody = "Здравствуйте!\n\nВаш договор купли-продажи щенка доступен по ссылке:\nhttps://matrang.com" . $pdfUrl . "\n\nС уважением,\nGreat Legacy Bully";
        
        $mail->send();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Contract sent successfully to ' . $email
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email: ' . $mail->ErrorInfo
        ]);
    }
    exit();
}

// -------------------------------------------------------------
// СИСТЕМА ЭЛЕКТРОННОЙ ПОДПИСИ (eIDAS)
// -------------------------------------------------------------

require_once __DIR__ . '/signature_system.php';

// 1. Создание запроса на подписание
if ($action === 'createSigningRequest') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Проверка входных данных
    if (!$input || empty($input['buyer_email']) || empty($input['buyer_phone']) || empty($input['pdf_url'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields (email, phone, pdf_url)']);
        exit();
    }
    
    try {
        $eidas = new eIDASSignatureSystem();
        $result = $eidas->createSigningRequest(
            $input['contract_id'] ?? 'DOG-'.date('Y-md-H'), 
            $input['buyer_email'],
            $input['buyer_phone'],
            $input['pdf_url']
        );
        
        http_response_code(200);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// 2. Получение информации о запросе (для страницы подписания)
if ($action === 'getSigningRequest') {
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token required']);
        exit();
    }
    
    try {
        $eidas = new eIDASSignatureSystem();
        $request = $eidas->getSigningRequest($token);
        
        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Request not found or expired']);
            exit();
        }
        
        echo json_encode(['success' => true, 'request' => $request]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// 3. Подписание контракта (SMS код)
if ($action === 'signContract') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['token']) || empty($input['sms_code']) || empty($input['signature_data'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    try {
        $eidas = new eIDASSignatureSystem();
        $result = $eidas->signContract(
            $input['token'],
            $input['sms_code'],
            $input['signature_data'],
            $input['client_metadata'] ?? []
        );
        
        echo json_encode($result);
    } catch (Exception $e) {
        // SMS invalid code usually throws exception
        http_response_code(400); // 400 for bad logic (e.g. wrong code)
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// -------------------------------------------------------------
// SELLER PROFILE SAVE/LOAD
// -------------------------------------------------------------
if ($action === 'save_seller_profile') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $profileFile = __DIR__ . '/../data/seller_profile.json';
    
    // Создаем папку если её нет
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    if ($input && is_array($input)) {
        $result = file_put_contents($profileFile, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($result !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Saved ' . $result . ' bytes']);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'File write failed', 'path' => $profileFile]);
        }
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Invalid input', 'received' => $input]);
    }
    exit();
}

if ($action === 'get_seller_profile') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    
    $profileFile = __DIR__ . '/../data/seller_profile.json';
    
    header('Content-Type: application/json; charset=utf-8');
    if (file_exists($profileFile)) {
        $data = file_get_contents($profileFile);
        echo $data;
    } else {
        echo json_encode(['message' => 'No profile found', 'path' => $profileFile]);
    }
    exit();
}

if ($action === 'sendSigningLink') {
    // 1. Initial Logging & Headers
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    $debugLog = __DIR__ . '/email_debug.log';
    
    // Explicitly write to log immediately to prove execution starts
    file_put_contents($debugLog, "\n\n[" . date('Y-m-d H:i:s') . "] ACTION: sendSigningLink STARTED\n", FILE_APPEND);

    // 2. Auth Check
    if (!checkAuth()) {
        file_put_contents($debugLog, "Auth failed\n", FILE_APPEND);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // 3. Autoloading (Defensive)
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            file_put_contents($debugLog, "Autoload loaded (local)\n", FILE_APPEND);
        } elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            file_put_contents($debugLog, "Autoload loaded (parent)\n", FILE_APPEND);
        } else {
            throw new \Exception("Vendor autoload not found");
        }
    } catch (\Throwable $e) {
        file_put_contents($debugLog, "CRITICAL: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        exit();
    }

    // 4. Input Parsing
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Fix for PHP 8 Crash on null input
    if (!is_array($input)) {
        $input = [];
        file_put_contents($debugLog, "Wait! Input is not array: " . var_export($rawInput, true) . "\n", FILE_APPEND);
    }

    $email = $input['email'] ?? '';
    $link = $input['link'] ?? '';
    $contractNumber = $input['contractNumber'] ?? 'Unknown';
    $name = $input['name'] ?? 'Покупатель';
    
    $sellerEmail = isset($input['sellerEmail']) ? trim($input['sellerEmail']) : '';
    $sellerName = isset($input['sellerName']) ? trim($input['sellerName']) : '';
    
    file_put_contents($debugLog, "Inputs: To=$email, Seller=$sellerEmail, Link=$link\n", FILE_APPEND);

    if (!$email || !$link) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Missing email or link']);
        exit();
    }

    // 5. Config Loading
    if (!file_exists(__DIR__ . '/smtp_config.php')) {
        file_put_contents($debugLog, "Config missing\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Config missing']);
        exit();
    }
    $smtpConfig = require __DIR__ . '/smtp_config.php';
    
    // 6. Execution Logic (No Closures, Linear Code)
    $logs = [];
    $logs[] = "Starting mail process...";

    try {
        // --- PREPARE MAILER 1 (BUYER) ---
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = $smtpConfig['auth'];
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = $smtpConfig['encryption'];
        $mail->Port = $smtpConfig['port'];
        $mail->CharSet = 'UTF-8';
        
        $fromName = $sellerName ?: $smtpConfig['from_name'];
        $mail->setFrom($smtpConfig['from_email'], $fromName);
        
        $replyTo = ($sellerEmail && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) ? $sellerEmail : $smtpConfig['reply_to'];
        $mail->addReplyTo($replyTo, $fromName);
        
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Подписание договора на щенка ({$contractNumber})";
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
            <h2 style='color: #2563eb;'>Договор готов к подписанию</h2>
            <p>Здравствуйте, <strong>{$name}</strong>!</p>
            <p>Ваш договор купли-продажи щенка (№{$contractNumber}) сформирован и ожидает вашей подписи.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$link}' style='background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;'>Посмотреть и подписать договор</a>
            </div>
            <p style='color: #666; font-size: 14px;'>Если кнопка не работает, скопируйте ссылку в браузер:</p>
            <p style='background: #f5f5f5; padding: 10px; font-size: 12px; word-break: break-all;'>{$link}</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='color: #888; font-size: 12px;'>С уважением,<br>{$fromName}</p>
        </div>";
        
        $mail->send();
        $logs[] = "Buyer email sent to $email";
        file_put_contents($debugLog, "Buyer sent OK\n", FILE_APPEND);

        // --- PREPARE MAILER 2 (SELLER COPY) ---
        if ($sellerEmail && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL) && strtolower($sellerEmail) !== strtolower($email)) {
            $mail2 = new \PHPMailer\PHPMailer\PHPMailer(true); // Completely new object
            $mail2->isSMTP();
            $mail2->Host = $smtpConfig['host'];
            $mail2->SMTPAuth = $smtpConfig['auth'];
            $mail2->Username = $smtpConfig['username'];
            $mail2->Password = $smtpConfig['password'];
            $mail2->SMTPSecure = $smtpConfig['encryption'];
            $mail2->Port = $smtpConfig['port'];
            $mail2->CharSet = 'UTF-8';

            $mail2->setFrom($smtpConfig['from_email'], $fromName);
            $mail2->addReplyTo($smtpConfig['reply_to'], $fromName); // Reply to Admin/Default
            $mail2->addAddress($sellerEmail);
            $mail2->isHTML(true);
            $mail2->Subject = "[КОПИЯ] Договор на {$name}";
            $mail2->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #f8fafc;'>
                <h2 style='color: #475569;'>Копия отправленного договора</h2>
                <p>Вы отправили договор покупателю <strong>{$name}</strong>.</p>
                <p><strong>Номер договора:</strong> {$contractNumber}</p>
            </div>";

            $mail2->send();
            $logs[] = "Seller copy sent to $sellerEmail";
            file_put_contents($debugLog, "Seller sent OK\n", FILE_APPEND);
        }

        echo json_encode(['success' => true, 'message' => 'All emails sent', 'logs' => $logs]);

    } catch (\Throwable $e) {
        $msg = "Error: " . $e->getMessage();
        file_put_contents($debugLog, $msg . "\n", FILE_APPEND);
        // Important: Return 200 to show error in frontend instead of crashing
        echo json_encode(['success' => false, 'message' => $msg, 'logs' => $logs]);
    }
    exit();
}

// -------------------------------------------------------------

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);

function getDefaultContent() {
    return [
        'header' => [
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
?>

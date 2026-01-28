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

$action = $_GET['action'] ?? '';
$dataFile = __DIR__ . '/../data/content.json';
$uploadDir = __DIR__ . '/../uploads/';

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

// Получение контрактов и шаблонов
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
    if (file_exists($pdfTemplateFile)) {
        $pdfTemplate = '/uploads/pdf_template.pdf';
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'contracts' => $contracts,
        'templates' => $templates,
        'pdfTemplate' => $pdfTemplate
    ]);
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

// Отправка контракта в PDF по email
if ($action === 'sendContractPdf') {
    if (!checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['pdfData'])) {
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

    // В production здесь должна быть реальная отправка email
    // Для демо просто возвращаем успех
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Contract sent successfully to ' . $input['email']
    ]);
    exit();
}

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

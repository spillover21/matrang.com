<?php
// api/api.php - главный API для админ панели

// Отключаем вывод ошибок в JSON ответ
error_reporting(0);
ini_set('display_errors', '0');

// Очищаем любой предыдущий вывод
if (ob_get_level()) ob_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');
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

// ===== ENDPOINTS ДЛЯ ДОГОВОРОВ =====

if ($action === 'getcontracts') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $templatesFile = __DIR__ . '/../data/contract_templates.json';
    $pdfTemplateFile = $uploadDir . 'contracts/contract_template.pdf';
    
    $contracts = file_exists($contractsFile) ? json_decode(file_get_contents($contractsFile), true) : [];
    $templates = file_exists($templatesFile) ? json_decode(file_get_contents($templatesFile), true) : [];
    $pdfTemplate = file_exists($pdfTemplateFile) ? '/uploads/contracts/contract_template.pdf' : '';
    
    echo json_encode([
        'success' => true,
        'contracts' => $contracts,
        'templates' => $templates,
        'pdfTemplate' => $pdfTemplate
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
    // Временно логируем данные для отладки
    $debugLog = __DIR__ . '/../data/upload_debug.log';
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Headers: " . json_encode(getallheaders()) . "\n", FILE_APPEND);
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - FILES: " . json_encode($_FILES) . "\n", FILE_APPEND);
    
    if (!checkAuth()) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Auth failed\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    if (!isset($_FILES['pdf'])) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - No pdf in FILES\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'No PDF file uploaded', 'debug' => 'FILES: ' . json_encode(array_keys($_FILES))]);
        http_response_code(400);
        exit;
    }
    
    if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Upload error: ' . $_FILES['pdf']['error'];
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - {$errorMsg}\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
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

// Генерация заполненного PDF (для preview)
if ($action === 'generatefilledpdf') {
    if (!checkAuth()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    $data = $jsonInput['data'] ?? [];
    $pdfTemplate = $jsonInput['pdfTemplate'] ?? '';
    
    if (!$pdfTemplate) {
        echo json_encode(['success' => false, 'message' => 'PDF template required']);
        http_response_code(400);
        exit;
    }
    
    // Путь к шаблону
    $templatePath = __DIR__ . '/..' . $pdfTemplate;
    
    if (!file_exists($templatePath)) {
        echo json_encode(['success' => false, 'message' => 'Template not found']);
        http_response_code(404);
        exit;
    }
    
    // Генерируем HTML с данными договора для создания PDF
    // В production используйте библиотеку для заполнения PDF полей (FPDI, PDFtk)
    // Для демо создаем HTML-версию
    
    $contractNumber = 'DOG-' . date('Y') . '-PREVIEW';
    $contractDate = $data['contractDate'] ?? date('d.m.Y');
    $contractPlace = $data['contractPlace'] ?? 'г. Каяани, Финляндия';
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Times New Roman", serif; padding: 40px; max-width: 800px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 10px; }
        h2 { text-align: center; margin: 5px 0; font-size: 16px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin: 20px 0; }
        .section h3 { margin-bottom: 10px; }
        .field { margin: 5px 0; }
        .label { font-weight: bold; }
        .checkbox { margin-right: 5px; }
    </style>
</head>
<body>
    <h1>GREAT LEGACY BULLY</h1>
    <h2>ДОГОВОР КУПЛИ-ПРОДАЖИ ЩЕНКА American Bully</h2>
    
    <div class="header">
        <p>№ ' . htmlspecialchars($contractNumber) . ' от ' . htmlspecialchars($contractDate) . '</p>
        <p>' . htmlspecialchars($contractPlace) . '</p>
    </div>
    
    <div class="section">
        <h3>1. ЗАВОДЧИК-ПРОДАВЕЦ</h3>
        <div class="field"><span class="label">ФИО:</span> ' . htmlspecialchars($data['kennelOwner'] ?? '') . '</div>
        <div class="field"><span class="label">Адрес:</span> ' . htmlspecialchars($data['kennelAddress'] ?? '') . '</div>
        <div class="field"><span class="label">Телефон:</span> ' . htmlspecialchars($data['kennelPhone'] ?? '') . '</div>
        <div class="field"><span class="label">Email:</span> ' . htmlspecialchars($data['kennelEmail'] ?? '') . '</div>
        <div class="field"><span class="label">Паспорт:</span> ' . 
            htmlspecialchars($data['kennelPassportSeries'] ?? '') . ' ' . 
            htmlspecialchars($data['kennelPassportNumber'] ?? '') . 
            ', выдан ' . htmlspecialchars($data['kennelPassportIssuedBy'] ?? '') . 
            ' ' . htmlspecialchars($data['kennelPassportIssuedDate'] ?? '') . '</div>
    </div>
    
    <div class="section">
        <h3>2. ПОКУПАТЕЛЬ-ВЛАДЕЛЕЦ</h3>
        <div class="field"><span class="label">ФИО:</span> ' . htmlspecialchars($data['buyerName'] ?? '') . '</div>
        <div class="field"><span class="label">Адрес:</span> ' . htmlspecialchars($data['buyerAddress'] ?? '') . '</div>
        <div class="field"><span class="label">Телефон:</span> ' . htmlspecialchars($data['buyerPhone'] ?? '') . '</div>
        <div class="field"><span class="label">Email:</span> ' . htmlspecialchars($data['buyerEmail'] ?? '') . '</div>
        <div class="field"><span class="label">Паспорт:</span> ' . 
            htmlspecialchars($data['buyerPassportSeries'] ?? '') . ' ' . 
            htmlspecialchars($data['buyerPassportNumber'] ?? '') . 
            ', выдан ' . htmlspecialchars($data['buyerPassportIssuedBy'] ?? '') . 
            ' ' . htmlspecialchars($data['buyerPassportIssuedDate'] ?? '') . '</div>
    </div>
    
    <div class="section">
        <h3>3. ПРЕДМЕТ ДОГОВОРА - ЩЕНОК</h3>
        <div class="field"><span class="label">Кличка:</span> ' . htmlspecialchars($data['dogName'] ?? '') . '</div>
        <div class="field"><span class="label">Порода:</span> ' . htmlspecialchars($data['dogBreed'] ?? 'Американский булли') . '</div>
        <div class="field"><span class="label">Дата рождения:</span> ' . htmlspecialchars($data['dogBirthDate'] ?? '') . '</div>
        <div class="field"><span class="label">Пол:</span> ' . htmlspecialchars($data['dogGender'] ?? '') . '</div>
        <div class="field"><span class="label">Окрас:</span> ' . htmlspecialchars($data['dogColor'] ?? '') . '</div>
        <div class="field"><span class="label">Номер чипа:</span> ' . htmlspecialchars($data['dogChipNumber'] ?? '') . '</div>
        <div class="field"><span class="label">Щенячья карточка:</span> ' . htmlspecialchars($data['dogPuppyCard'] ?? '') . '</div>
        
        <p style="margin-top: 15px;"><span class="label">Родители:</span></p>
        <div class="field">Отец: ' . htmlspecialchars($data['dogFatherName'] ?? '') . ' (рег. № ' . htmlspecialchars($data['dogFatherRegNumber'] ?? '') . ')</div>
        <div class="field">Мать: ' . htmlspecialchars($data['dogMotherName'] ?? '') . ' (рег. № ' . htmlspecialchars($data['dogMotherRegNumber'] ?? '') . ')</div>
        
        <p style="margin-top: 15px;"><span class="label">Цель приобретения:</span></p>
        <div class="field">
            <input type="checkbox" class="checkbox" ' . (!empty($data['purposeBreeding']) ? 'checked' : '') . '> Для племенной работы (разведение)<br>
            <input type="checkbox" class="checkbox" ' . (!empty($data['purposeCompanion']) ? 'checked' : '') . '> Компаньон (без разведения)<br>
            <input type="checkbox" class="checkbox" ' . (!empty($data['purposeGeneral']) ? 'checked' : '') . '> Общение, не исключающее разведения
        </div>
    </div>
    
    <div class="section">
        <h3>5. ФИНАНСОВЫЕ УСЛОВИЯ</h3>
        <div class="field"><span class="label">Полная стоимость:</span> ' . htmlspecialchars($data['price'] ?? '0') . ' руб.</div>
        <div class="field"><span class="label">Сумма задатка:</span> ' . htmlspecialchars($data['depositAmount'] ?? '0') . ' руб. (внесена ' . htmlspecialchars($data['depositDate'] ?? '') . ')</div>
        <div class="field"><span class="label">Остаток к оплате:</span> ' . htmlspecialchars($data['remainingAmount'] ?? '0') . ' руб.</div>
        <div class="field"><span class="label">Срок окончательного расчета:</span> не позднее ' . htmlspecialchars($data['finalPaymentDate'] ?? '') . '</div>
    </div>
    
    ' . (!empty($data['dewormingDate']) || !empty($data['vaccinationDates']) ? '
    <div class="section">
        <h3>ВАКЦИНАЦИЯ</h3>
        <div class="field"><span class="label">Выгонка глистов:</span> ' . htmlspecialchars($data['dewormingDate'] ?? '') . '</div>
        <div class="field"><span class="label">Прививки:</span> ' . htmlspecialchars($data['vaccinationDates'] ?? '') . '</div>
        <div class="field"><span class="label">Вакцина:</span> ' . htmlspecialchars($data['vaccineName'] ?? '') . '</div>
        <div class="field"><span class="label">Следующая обработка от глистов:</span> ' . htmlspecialchars($data['nextDewormingDate'] ?? '') . '</div>
        <div class="field"><span class="label">Следующая вакцинация:</span> ' . htmlspecialchars($data['nextVaccinationDate'] ?? '') . '</div>
    </div>
    ' : '') . '
    
    ' . (!empty($data['specialFeatures']) || !empty($data['deliveryTerms']) ? '
    <div class="section">
        <h3>ДОПОЛНИТЕЛЬНЫЕ УСЛОВИЯ</h3>
        ' . (!empty($data['specialFeatures']) ? '<div class="field"><span class="label">Индивидуальные особенности:</span><br>' . nl2br(htmlspecialchars($data['specialFeatures'])) . '</div>' : '') . '
        ' . (!empty($data['deliveryTerms']) ? '<div class="field"><span class="label">Условия доставки:</span><br>' . nl2br(htmlspecialchars($data['deliveryTerms'])) . '</div>' : '') . '
        ' . (!empty($data['recommendedFood']) ? '<div class="field"><span class="label">Рекомендуемый корм:</span><br>' . nl2br(htmlspecialchars($data['recommendedFood'])) . '</div>' : '') . '
        ' . (!empty($data['additionalAgreements']) ? '<div class="field"><span class="label">Дополнительные соглашения:</span><br>' . nl2br(htmlspecialchars($data['additionalAgreements'])) . '</div>' : '') . '
    </div>
    ' : '') . '
    
    <div style="margin-top: 50px;">
        <table width="100%">
            <tr>
                <td width="50%">
                    <div style="border-top: 1px solid #000; width: 200px; display: inline-block;"></div><br>
                    <small>ЗАВОДЧИК-ПРОДАВЕЦ</small>
                </td>
                <td width="50%" style="text-align: right;">
                    <div style="border-top: 1px solid #000; width: 200px; display: inline-block;"></div><br>
                    <small>ПОКУПАТЕЛЬ</small>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>';
    
    // Сохраняем HTML как временный файл
    $previewDir = $uploadDir . 'previews/';
    if (!is_dir($previewDir)) {
        mkdir($previewDir, 0755, true);
    }
    
    $previewFilename = 'contract_preview_' . time() . '.html';
    $previewPath = $previewDir . $previewFilename;
    file_put_contents($previewPath, $html);
    
    $previewUrl = '/uploads/previews/' . $previewFilename;
    
    echo json_encode(['success' => true, 'url' => $previewUrl, 'type' => 'html']);
    exit;
}

// Отправка договора через Adobe Sign
if ($action === 'sendcontractpdf') {
    // Отладка
    $debugLog = __DIR__ . '/../data/sendcontract_debug.log';
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Starting sendContractPdf\n", FILE_APPEND);
    
    if (!checkAuth()) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Auth failed\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
    
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Auth OK\n", FILE_APPEND);
    
    // Получаем входные данные
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - JSON input keys: " . implode(', ', array_keys($jsonInput ?? [])) . "\n", FILE_APPEND);
    if (!empty($jsonInput['filledPdfBase64'])) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - filledPdfBase64 length: " . strlen($jsonInput['filledPdfBase64']) . "\n", FILE_APPEND);
    }
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Full JSON: " . substr(json_encode($jsonInput, JSON_UNESCAPED_UNICODE), 0, 500) . "...\n", FILE_APPEND);
    
    // Загружаем конфигурацию Adobe Sign
    $adobeSignConfigPath = __DIR__ . '/adobe_sign_config.php';
    
    if (file_exists($adobeSignConfigPath)) {
        try {
            $adobeSignConfig = require $adobeSignConfigPath;
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Config loaded\n", FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Config load error: " . $e->getMessage() . "\n", FILE_APPEND);
            // Используем дефолтные настройки
            $adobeSignConfig = ['enabled' => false, 'access_token' => ''];
        }
    } else {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Config not found, using defaults\n", FILE_APPEND);
        // Используем дефолтные настройки - отправка через email
        $adobeSignConfig = ['enabled' => false, 'access_token' => ''];
    }
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $contracts = file_exists($contractsFile) ? json_decode(file_get_contents($contractsFile), true) : [];
    
    $contractNumber = 'DOG-' . date('Y') . '-' . str_pad(count($contracts) + 1, 4, '0', STR_PAD_LEFT);
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Generated contract number: $contractNumber\n", FILE_APPEND);
    
    $data = $jsonInput['data'] ?? [];
    $pdfTemplate = $jsonInput['pdfTemplate'] ?? '';
    
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - PDF template: $pdfTemplate\n", FILE_APPEND);
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Buyer email: " . ($data['buyerEmail'] ?? 'NOT SET') . "\n", FILE_APPEND);
    
    if (!$pdfTemplate) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - ERROR: No PDF template\n", FILE_APPEND);
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
        
        // Генерируем PDF с данными
        require_once __DIR__ . '/generate_contract_pdf.php';
        
        $templatePath = __DIR__ . '/..' . $pdfTemplate;
        $outputDir = $uploadDir . 'contracts/filled/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $outputFilename = 'contract_' . $contractNumber . '.pdf';
        $outputPath = $outputDir . $outputFilename;
        
        // Если клиент прислал уже заполненный PDF (base64)
        $filledPdfBase64 = $jsonInput['filledPdfBase64'] ?? '';
        if (!empty($filledPdfBase64)) {
            $decoded = base64_decode($filledPdfBase64, true);
            if ($decoded === false) {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - ERROR: Invalid filledPdfBase64\n", FILE_APPEND);
            } else {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Received filledPdfBase64, size: " . strlen($decoded) . " bytes\n", FILE_APPEND);
                file_put_contents($outputPath, $decoded);
            }
        }
        
        // Добавляем номер договора в данные
        $data['contractNumber'] = $contractNumber;
        $data['contractDate'] = date('d.m.Y');
        
        // Генерируем PDF (пытаемся)
        $pdfGenerated = false;
        if (file_exists($outputPath) && filesize($outputPath) > 0) {
            $pdfGenerated = true;
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Using client-filled PDF\n", FILE_APPEND);
        } elseif (file_exists($templatePath)) {
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Template exists, generating PDF...\n", FILE_APPEND);
            try {
                // ПОПЫТКА 1: Попробуем с импортом шаблона (FPDI)
                require_once __DIR__ . '/generate_contract_pdf.php';
                $pdfGenerated = generateContractPdf($templatePath, $data, $outputPath);
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - FPDI PDF generated: " . ($pdfGenerated ? 'YES' : 'NO') . "\n", FILE_APPEND);
            } catch (Exception $e) {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - FPDI failed: " . $e->getMessage() . "\n", FILE_APPEND);
            }
            
            // ПОПЫТКА 2: Если FPDI не сработал - создаём простой PDF с данными
            if (!$pdfGenerated) {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Trying simple PDF generation...\n", FILE_APPEND);
                require_once __DIR__ . '/generate_contract_pdf_simple.php';
                $pdfGenerated = generateContractPdfSimple($data, $outputPath);
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Simple PDF generated: " . ($pdfGenerated ? 'YES' : 'NO') . "\n", FILE_APPEND);
            }
            
            if ($pdfGenerated && file_exists($outputPath)) {
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - PDF file size: " . filesize($outputPath) . " bytes\n", FILE_APPEND);
            }
        } else {
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - ERROR: Template not found at $templatePath\n", FILE_APPEND);
        }
        
        // Отправка email с PDF
        require_once __DIR__ . '/send_email.php';
        
        $buyerEmail = $data['buyerEmail'] ?? '';
        $buyerName = $data['buyerName'] ?? '';
        $dogName = $data['dogName'] ?? '';
        $kennelEmail = $data['kennelEmail'] ?? 'info@matrang.com';
        
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Buyer email: $buyerEmail\n", FILE_APPEND);
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Kennel email: $kennelEmail\n", FILE_APPEND);
        
        if ($buyerEmail) {
            $subject = "Договор купли-продажи щенка - №{$contractNumber}";
            
            // HTML содержимое письма
            $htmlBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6;'>
    <h2>GREAT LEGACY BULLY</h2>
    <h3>Договор купли-продажи щенка American Bully</h3>
    
    <p><strong>Здравствуйте, " . htmlspecialchars($buyerName) . "!</strong></p>
    
    <p>Вам направлен договор купли-продажи щенка <strong>" . htmlspecialchars($dogName) . "</strong>.</p>
    <p>Номер договора: <strong>{$contractNumber}</strong></p>
    
    <p><strong>Во вложении находится PDF договор.</strong></p>
    
    <p>Пожалуйста:</p>
    <ol>
        <li>Откройте прикрепленный PDF файл</li>
        <li>Ознакомьтесь с условиями договора</li>
        <li>Распечатайте и подпишите договор</li>
        <li>Отсканируйте подписанный договор и отправьте на {$kennelEmail}</li>
    </ol>
    
    <hr>
    <p><strong>Основная информация:</strong></p>
    <ul>
        <li><strong>Щенок:</strong> " . htmlspecialchars($dogName) . " (" . htmlspecialchars($data['dogBreed'] ?? 'Американский булли') . ")</li>
        <li><strong>Стоимость:</strong> " . htmlspecialchars($data['price'] ?? '0') . " руб.</li>
        <li><strong>Покупатель:</strong> " . htmlspecialchars($buyerName) . "</li>
    </ul>
    
    <p>С уважением,<br><strong>Питомник GREAT LEGACY BULLY</strong></p>
    <p style='font-size: 12px; color: #666;'>Телефон: " . htmlspecialchars($data['kennelPhone'] ?? '') . "<br>Email: {$kennelEmail}</p>
</body>
</html>";
            
            // Подготовка вложений
            $attachments = [];
            if ($pdfGenerated && file_exists($outputPath)) {
                $attachments[] = [
                    'path' => $outputPath,
                    'name' => $outputFilename
                ];
            } elseif (file_exists($templatePath)) {
                // Если PDF не сгенерирован - отправляем оригинальный шаблон
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Sending original template as fallback\n", FILE_APPEND);
                
                $attachments[] = [
                    'path' => $templatePath,
                    'name' => 'contract_template.pdf'
                ];
                
                // Добавляем предупреждение в письмо
                $htmlBody = str_replace(
                    '<p><strong>Во вложении находится PDF договор.</strong></p>',
                    '<p style="background:#fff3cd;padding:10px;border-left:4px solid #ffc107;"><strong>⚠️ ВНИМАНИЕ:</strong> Во вложении пустой шаблон договора. Пожалуйста, заполните его вручную следующими данными:</p>
                    <table style="border-collapse:collapse;width:100%;margin:10px 0;">
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Номер договора:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($contractNumber) . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Дата:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['contractDate']) . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Владелец питомника:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['kennelOwner'] ?? '') . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Покупатель:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($buyerName) . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Адрес покупателя:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['buyerAddress'] ?? '') . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Телефон покупателя:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['buyerPhone'] ?? '') . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Кличка щенка:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($dogName) . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Порода:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['dogBreed'] ?? '') . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Окрас:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['dogColor'] ?? '') . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Дата рождения:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['dogBirthDate'] ?? '') . '</td></tr>
                        <tr><td style="padding:5px;border:1px solid #ddd;"><strong>Стоимость:</strong></td><td style="padding:5px;border:1px solid #ddd;">' . htmlspecialchars($data['price'] ?? '') . ' руб.</td></tr>
                    </table>
                    <p><strong>Во вложении находится PDF шаблон договора.</strong></p>',
                    $htmlBody
                );
            }
            
            // Отправка через SMTP
            $mailSent = sendEmailSMTP($buyerEmail, $subject, $htmlBody, $attachments, $kennelEmail);
            
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Mail sent to buyer (SMTP): " . ($mailSent ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
            
            // Отправляем копию продавцу через SMTP
            if ($mailSent && $kennelEmail) {
                $sellerSubject = "Копия договора {$contractNumber}";
                $sellerBody = str_replace($buyerName, $buyerName . " (копия для питомника)", $htmlBody);
                $sellerSent = sendEmailSMTP($kennelEmail, $sellerSubject, $sellerBody, $attachments);
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Mail sent to seller (SMTP): " . ($sellerSent ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
            }
        } else {
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - WARNING: No buyer email\n", FILE_APPEND);
        }
        
        echo json_encode([
            'success' => true, 
            'contract' => $newContract, 
            'note' => 'Договор отправлен на email: ' . $buyerEmail . ($pdfGenerated ? ' (с PDF вложением)' : ' (без PDF)'),
            'emailSent' => !empty($buyerEmail),
            'pdfGenerated' => $pdfGenerated,
            'pdfUrl' => $pdfGenerated ? '/uploads/contracts/filled/' . $outputFilename : ''
        ]);
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
// Если не нашли подходящий action
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
exit;

// Вспомогательные функции
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
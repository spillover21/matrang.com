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

// ---------------------------------------------------------
// CONTRACTS API EXTENSION
// ---------------------------------------------------------

// 1. Get Contracts / Template Path
if ($action === 'getContracts') {
    // Return path to template if exists
    $templatePath = '/uploads/contract_template.pdf';
    $fullPath = $uploadDir . 'contract_template.pdf';
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'contracts' => [], // Legacy compat
        'pdfTemplate' => file_exists($fullPath) ? $templatePath : null
    ]);
    exit();
}

// 2. Upload Template
if ($action === 'uploadPdfTemplate') {
    // Check for 'file' OR 'pdf' key, as frontend might send 'pdf'
    $uploadedFile = $_FILES['file'] ?? $_FILES['pdf'] ?? null;
    
    if (!$uploadedFile) {
        http_response_code(400); echo json_encode(['success'=>false, 'message'=>'No file provided (checked keys: file, pdf)']); exit();
    }
    
    $res = move_uploaded_file($uploadedFile['tmp_name'], $uploadDir . 'contract_template.pdf');
    if ($res) echo json_encode(['success'=>true, 'path'=>'/uploads/contract_template.pdf', 'url'=>'/uploads/contract_template.pdf']);
    else { http_response_code(500); echo json_encode(['success'=>false, 'message'=>'Upload failed']); }
    exit();
}

// 3. Save/Get Seller Profile
$sellerFile = __DIR__ . '/../data/seller_profile.json';
if ($action === 'save_seller_profile') {
    $input = file_get_contents('php://input');
    file_put_contents($sellerFile, $input);
    echo json_encode(['success'=>true]);
    exit();
}
if ($action === 'get_seller_profile') {
    if (file_exists($sellerFile)) {
        echo file_get_contents($sellerFile);
    } else {
        echo json_encode(['success'=>false]);
    }
    exit();
}

// 4. Upload Filled Contract (for signing)
if ($action === 'uploadcontract' || $action === 'upload_pdf_for_signing') {
     if (!is_dir($uploadDir . 'contracts/')) mkdir($uploadDir . 'contracts/', 0755, true);
     
     // Handle Multipart (File) or JSON base64
     if (isset($_FILES['file'])) {
         $name = 'contract_' . time() . '.pdf';
         move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . 'contracts/' . $name);
         echo json_encode(['success'=>true, 'path'=>'/uploads/contracts/'.$name, 'link'=>'https://'.$_SERVER['HTTP_HOST'].'/uploads/contracts/'.$name]);
         exit();
     }
     
     // Handle Base64
     $input = json_decode(file_get_contents('php://input'), true);
     if (isset($input['pdfBase64'])) {
         $data = base64_decode(explode(',', $input['pdfBase64'])[1] ?? $input['pdfBase64']);
         $name = 'contract_' . time() . '.pdf';
         file_put_contents($uploadDir . 'contracts/' . $name, $data);
         echo json_encode(['success'=>true, 'link'=>'https://'.$_SERVER['HTTP_HOST'].'/uploads/contracts/'.$name]);
         exit();
     }
     
     http_response_code(400); echo json_encode(['success'=>false, 'message'=>'No data']); exit();
}

// 5. Send Email
if ($action === 'sendContractPdf' || $action === 'send_signing_email') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? $input['data']['buyerEmail'] ?? '';
    $link = $input['link'] ?? $input['pdfTemplate'] ?? ''; // Handle various scalar inputs
    
    if (!$email) {
        http_response_code(400); echo json_encode(['success'=>false, 'message'=>'No email']); exit();
    }
    
    $subject = "Ваш договор от питомника (MATRANG)";
    $message = "Здравствуйте! \n\nВаш договор подготовлен. \nСкачать и подписать можно по ссылке:\n" . $link;
    $headers = "From: info@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "Reply-To: info@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    // Try native mail first
    $sent = mail($email, $subject, $message, $headers);
    
    echo json_encode(['success'=>$sent, 'message'=>$sent?'Sent':'Mail failed']);
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

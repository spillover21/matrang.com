<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://matrang.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Проверка авторизации - принимаем любой Bearer токен от админки
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
if (empty($token) || strpos($token, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - no token']);
    exit;
}

try {
    if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error: ' . ($_FILES['template']['error'] ?? 'no file'));
    }
    
    $file = $_FILES['template'];
    $filePath = $file['tmp_name'];
    $fileName = basename($file['name']);
    
    // Отправляем файл на VPS через Bridge API
    $ch = curl_init('http://72.62.114.139:8080/upload_template.php');
    
    $cfile = new CURLFile($filePath, 'application/pdf', $fileName);
    $postData = ['template' => $cfile];
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: matrang_secret_key_2026'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('CURL error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('VPS upload failed: HTTP ' . $httpCode . ' - ' . $response);
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !$result['success']) {
        throw new Exception($result['error'] ?? 'Unknown VPS error');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Template uploaded to VPS',
        'vps_path' => $result['path'],
        'vps_size' => $result['size'] ?? 0
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

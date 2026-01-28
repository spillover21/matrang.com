<?php
// api/webhook_documenso.php
// Эндпоинт для приема вебхуков от Documenso

require_once __DIR__ . '/DocumensoService.php';
$config = require __DIR__ . '/documenso_config.php';

// Конфигурация (в реальном проекте загружать из .env)
$webhookSecret = $config['WEBHOOK_SECRET'];

// 1. Получаем сырое тело запроса и заголовки
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_DOCUMENSO_SIGNATURE'] ?? '';

// 2. Проверка подписи (HMAC-SHA256)
// LEGAL COMPLIANCE: Критически важно проверять подлинность вебхука, 
// чтобы никто не мог подделать факт подписания договора.
if (empty($signature)) {
    http_response_code(401);
    die('Signature missing');
}

$calculatedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

// Сравнение подписей (timing-attack safe)
if (!hash_equals($calculatedSignature, $signature)) {
    http_response_code(403);
    die('Invalid signature');
}

$payload = json_decode($rawBody, true);
$event = $payload['type'] ?? '';
$data = $payload['data'] ?? [];

// 3. Обработка событий
switch ($event) {
    case 'DOCUMENT_COMPLETED': // Документ подписан всеми сторонами
        $documentId = $data['id'];
        $internalUserId = $data['metadata']['internalUserId'] ?? 'unknown'; // Достаем наш ID
        
        try {
            $service = new DocumensoService();
            
            // Формируем путь для сохранения
            // Сохраняем в защищенную папку (не в публичный доступ), доступ через скрипт
            $uploadDir = __DIR__ . '/../uploads/contracts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $filename = "contract_{$internalUserId}_{$documentId}.pdf";
            $savePath = $uploadDir . $filename;
            
            // Скачиваем PDF
            $service->downloadDocument($documentId, $savePath);
            
            // Здесь можно обновить статус в базе данных
            // DB::update('contracts', ['status' => 'signed', 'file_path' => $filename], "documenso_id = '$documentId'");
            
            error_log("Document $documentId signed and saved to $savePath");
            
        } catch (Exception $e) {
            error_log("Failed to download signed document: " . $e->getMessage());
            http_response_code(500);
            die('Error processsing document');
        }
        break;

        // Другие события: DOCUMENT_VIEWED, RECIPIENT_SIGNED и т.д.
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
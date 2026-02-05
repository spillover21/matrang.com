<?php
// api/webhook_documenso.php
// Эндпоинт для приема вебхуков от Documenso

require_once __DIR__ . '/DocumensoService.php';
$config = require __DIR__ . '/documenso_config.php';
require_once __DIR__ . '/vendor/autoload.php'; // Подключаем PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    case 'document.completed': // На случай если формат изменится
        handleDocumentCompleted($data);
        break;

    case 'DOCUMENT_REJECTED': // Документ отклонен получателем
    case 'document.rejected':
    case 'RECIPIENT_REJECTED': 
        handleDocumentRejected($data);
        break;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);

// --- Helper Functions ---

function handleDocumentCompleted($data) {
    $documentId = $data['id'];
    $internalUserId = $data['metadata']['internalUserId'] ?? 'unknown';
    
    try {
        $service = new DocumensoService();
        
        // Save to public HTML folder so it can be downloaded
        //$uploadDir = __DIR__ . '/../uploads/contracts/'; 
        $uploadDir = __DIR__ . '/../html/uploads/contracts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $filename = "contract_{$internalUserId}_{$documentId}.pdf";
        $savePath = $uploadDir . $filename;
        
        // Скачиваем PDF
        $service->downloadDocument($documentId, $savePath);
        
        error_log("WEBHOOK: Document $documentId signed and saved to $savePath");

        // Обновляем статус в основной системе (Hostinger)
        $fileUrl = 'http://72.62.114.139/uploads/contracts/' . $filename;
        $mainSiteUrl = 'https://matrang.com/api/api.php?action=updateContractStatus';
        
        $ch = curl_init($mainSiteUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'documentId' => $documentId,
                'status' => 'signed',
                'signedUrl' => $fileUrl
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: matrang_secret_key_2026'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            error_log("WEBHOOK WARNING: Failed to update main site status. Error: $error, HTTP: $httpCode, Response: $response");
        } else {
            error_log("WEBHOOK: Updated main site status successfully.");
        }

        // Отправка клиенту финального письма с вложением
        $recipients = $data['recipients'] ?? [];
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email'])) {
                sendClientWithAttachment($recipient['email'], $savePath);
            }
        }
        
    } catch (Exception $e) {
        error_log("WEBHOOK ERROR: Failed to download signed document: " . $e->getMessage());
        // Не прерываем выполнение (200 OK), но логируем ошибку
    }
}

function handleDocumentRejected($data) {
    $documentId = $data['id'];
    $documentTitle = $data['title'] ?? 'Неизвестный документ';
    $internalUserId = $data['metadata']['internalUserId'] ?? 'unknown';
    
    // Пытаемся найти, кто отклонил (если есть в массиве recipients)
    $rejectReason = "Клиент отказался подписывать документ";
    
    // Отправка уведомления менеджеру
    sendManagerEmail(
        "ОТКАЗ ОТ ПОДПИСИ: $documentTitle",
        "Клиент (ID: $internalUserId) отклонил подписание документа #$documentId.<br>Пожалуйста, свяжитесь с клиентом."
    );
    
    error_log("WEBHOOK: Document $documentId rejected by user $internalUserId");
}

function sendManagerEmail($subject, $body) {
    $smtp = require __DIR__ . '/smtp_config.php';
    
    $mail = new PHPMailer(true);
    try {
        // SMTP Config
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = $smtp['auth'];
        $mail->Username   = $smtp['username'];
        $mail->Password   = $smtp['password'];
        $mail->SMTPSecure = $smtp['encryption'];
        $mail->Port       = $smtp['port'];
        $mail->CharSet    = 'UTF-8';

        // Sender & Recipient
        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        
        // Отправляем на email менеджера (можно вынести в конфиг)
        $managerEmail = 'greatlegacybully@gmail.com'; 
        $mail->addAddress($managerEmail, 'Manager');

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("WEBHOOK EMAIL ERROR: Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

function sendClientWithAttachment($email, $filePath) {
    $smtp = require __DIR__ . '/smtp_config.php';
    
    $mail = new PHPMailer(true);
    try {
        // SMTP Config
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = $smtp['auth'];
        $mail->Username   = $smtp['username'];
        $mail->Password   = $smtp['password'];
        $mail->SMTPSecure = $smtp['encryption'];
        $mail->Port       = $smtp['port'];
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        
        // Recipient
        $mail->addAddress($email);

        // Attachment
        if (file_exists($filePath)) {
            $mail->addAttachment($filePath, 'Contract_Signed.pdf');
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Ваш подписанный договор с питомником Matrang';
        $mail->Body    = '
            <p>Здравствуйте!</p>
            <p>Процесс подписания успешно завершен. К письму прикреплен ваш экземпляр договора.</p>
            <p>Данный файл содержит Лист Аудита, подтверждающий подлинность сделки.</p>
            <p>Мы рекомендуем сохранить этот файл.</p>
            <p><strong>Поздравляем с приобретением будущего члена семьи!</strong></p>
            <br>
            <p>С уважением,<br>Команда Matrang & Great Legacy Bully</p>
        ';

        $mail->send();
        error_log("WEBHOOK: Final email sent to $email");
    } catch (Exception $e) {
        error_log("WEBHOOK EMAIL ERROR: Message could not be sent to client $email. Mailer Error: {$mail->ErrorInfo}");
    }
}
?>
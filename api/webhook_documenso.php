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
$documensoSecret = $_SERVER['HTTP_X_DOCUMENSO_SECRET'] ?? '';

// 2. Проверка подписи
// LEGAL COMPLIANCE: Критически важно проверять подлинность вебхука
// Documenso отправляет X-Documenso-Secret с секретом вебхука
if (empty($documensoSecret)) {
    error_log('[WEBHOOK ERROR] X-Documenso-Secret header missing');
    http_response_code(401);
    die('Unauthorized: Missing secret');
}

if ($documensoSecret !== $webhookSecret) {
    error_log('[WEBHOOK ERROR] Invalid secret. Expected: ' . $webhookSecret . ', Got: ' . $documensoSecret);
    http_response_code(403);
    die('Forbidden: Invalid secret');
}

error_log('[WEBHOOK] Secret validated successfully');

$payload = json_decode($rawBody, true);

// Documenso может отправлять в двух форматах:
// 1. {"type":"DOCUMENT_COMPLETED","data":{...}} - старый формат
// 2. {"event":"DOCUMENT_COMPLETED","payload":{...}} - новый формат
$event = $payload['event'] ?? $payload['type'] ?? '';
$data = $payload['payload'] ?? $payload['data'] ?? [];

error_log('[WEBHOOK] Event: ' . $event . ', Data: ' . json_encode($data));

// 3. Обработка событий
try {
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
        
        default:
            error_log('[WEBHOOK] Unknown event type: ' . $event);
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'event' => $event]);
} catch (Exception $e) {
    error_log('[WEBHOOK ERROR] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// --- Helper Functions ---

function handleDocumentCompleted($data) {
    $documentId = $data['id'] ?? null;
    
    if (!$documentId) {
        error_log('[WEBHOOK ERROR] No document ID in data: ' . json_encode($data));
        return;
    }
    
    $internalUserId = $data['metadata']['internalUserId'] ?? 'unknown';
    
    error_log("[WEBHOOK] Processing document ID: $documentId");
    
    try {
        $service = new DocumensoService();
        
        // Получаем полный документ от Documenso для доступа к envelopeId
        error_log("[WEBHOOK] Fetching document $documentId from Documenso");
        $fullDocument = $service->getDocument($documentId);
        
        if (!$fullDocument) {
            error_log("[WEBHOOK ERROR] Document $documentId not found in Documenso");
            return;
        }
        
        $envelopeId = null;
        
        // Ищем envelopeId в полях документа
        if (isset($fullDocument['fields']) && is_array($fullDocument['fields'])) {
            foreach ($fullDocument['fields'] as $field) {
                if (isset($field['envelopeId'])) {
                    $envelopeId = $field['envelopeId'];
                    break;
                }
            }
        }
        
        // Получаем email покупателя из recipients
        $buyerEmail = null;
        $recipients = $fullDocument['recipients'] ?? $data['recipients'] ?? [];
        
        error_log("[WEBHOOK DEBUG] Recipients count: " . count($recipients));
        
        // Ищем покупателя (не noreply@matrang.com)
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email']) && $recipient['email'] !== 'noreply@matrang.com') {
                $buyerEmail = $recipient['email'];
                error_log("[WEBHOOK DEBUG] Found buyer email: $buyerEmail");
                break;
            }
        }
        
        error_log("[WEBHOOK DEBUG] EnvelopeId: " . ($envelopeId ?: 'NULL') . ", BuyerEmail: " . ($buyerEmail ?: 'NULL'));
        
        $uploadDir = __DIR__ . '/../uploads/contracts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $filename = "contract_{$internalUserId}_{$documentId}.pdf";
        $savePath = $uploadDir . $filename;
        
        // Скачиваем PDF
        $service->downloadDocument($documentId, $savePath);
        error_log("[WEBHOOK DEBUG] PDF downloaded to: $savePath");
        
        // Обновляем статус договора в базе (ищем по envelopeId или email)
        if ($envelopeId) {
            error_log("[WEBHOOK] Updating contract by envelopeId: $envelopeId");
            updateContractStatusByEnvelopeId($envelopeId, 'signed', '/uploads/contracts/' . $filename);
        } else if ($buyerEmail) {
            error_log("[WEBHOOK] Updating contract by email: $buyerEmail");
            updateContractStatusByEmail($buyerEmail, 'signed', '/uploads/contracts/' . $filename);
        } else {
            error_log("[WEBHOOK ERROR] No envelopeId or buyerEmail found for document $documentId");
        }
        
        error_log("WEBHOOK: Document $documentId (envelope: $envelopeId) signed and saved to $savePath");

        // Отправка клиенту финального письма с вложением
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
    
    // Получаем email покупателя
    $buyerEmail = null;
    $recipients = $data['recipients'] ?? [];
    foreach ($recipients as $recipient) {
        if (!empty($recipient['email'])) {
            $buyerEmail = $recipient['email'];
            break;
        }
    }
    
    // Обновляем статус в базе
    updateContractStatusByEmail($buyerEmail, 'rejected');
    
    // Отправка уведомления менеджеру
    sendManagerEmail(
        "ОТКАЗ ОТ ПОДПИСИ: $documentTitle",
        "Клиент ($buyerEmail, ID: $internalUserId) отклонил подписание документа #$documentId.<br>Пожалуйста, свяжитесь с клиентом."
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

/**
 * Обновить статус договора в базе данных по envelopeId
 */
function updateContractStatusByEnvelopeId($envelopeId, $status, $signedDocumentUrl = null) {
    if (!$envelopeId) {
        error_log("WEBHOOK: No envelopeId provided");
        return;
    }
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    if (!file_exists($contractsFile)) {
        error_log("WEBHOOK: contracts.json not found");
        return;
    }
    
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
    
    // Ищем договор по envelopeId
    $updated = false;
    foreach ($contracts as &$contract) {
        $contractEnvelopeId = $contract['adobeSignAgreementId'] ?? '';
        if ($contractEnvelopeId === $envelopeId) {
            $contract['status'] = $status;
            $contract['signedAt'] = date('c');
            
            if ($signedDocumentUrl) {
                $contract['signedDocumentUrl'] = $signedDocumentUrl;
            }
            
            $updated = true;
            error_log("WEBHOOK: Updated contract " . $contract['id'] . " (envelope: $envelopeId) to status: $status");
            break;
        }
    }
    
    if ($updated) {
        $saveData = ['contracts' => $contracts, 'templates' => $templates];
        file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        error_log("WEBHOOK: Contract with envelopeId $envelopeId not found in database");
    }
}

/**
 * Обновить статус договора в базе данных по email покупателя
 */
function updateContractStatusByEmail($buyerEmail, $status, $signedDocumentUrl = null) {
    if (!$buyerEmail) {
        error_log("WEBHOOK: No buyer email provided");
        return;
    }
    
    error_log("[WEBHOOK] Searching contract by email: $buyerEmail");
    
    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    if (!file_exists($contractsFile)) {
        error_log("WEBHOOK: contracts.json not found");
        return;
    }
    
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
    
    error_log("[WEBHOOK] Total contracts in database: " . count($contracts));
    
    // Ищем договор по email покупателя (последний с этим email и статусом sent)
    $updated = false;
    for ($i = count($contracts) - 1; $i >= 0; $i--) {
        $contract = &$contracts[$i];
        $contractEmail = $contract['data']['buyerEmail'] ?? '';
        $contractStatus = $contract['status'] ?? '';
        
        error_log("[WEBHOOK DEBUG] Checking contract " . $contract['id'] . " - email: $contractEmail, status: $contractStatus");
        
        if ($contractEmail === $buyerEmail && $contractStatus === 'sent') {
            $contract['status'] = $status;
            $contract['signedAt'] = date('c');
            
            if ($signedDocumentUrl) {
                $contract['signedDocumentUrl'] = $signedDocumentUrl;
            }
            
            $updated = true;
            error_log("WEBHOOK: Updated contract " . $contract['id'] . " (email: $buyerEmail) to status: $status");
            break;
        }
    }
    
    if ($updated) {
        $saveData = ['contracts' => $contracts, 'templates' => $templates];
        file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        error_log("[WEBHOOK] Database file updated successfully");
    } else {
        error_log("WEBHOOK: Contract with email $buyerEmail (status 'sent') not found in database");
    }
}
            $contract['signedAt'] = date('c');
            
            if ($signedDocumentUrl) {
                $contract['signedDocumentUrl'] = $signedDocumentUrl;
            }
            
            $updated = true;
            error_log("WEBHOOK: Updated contract " . $contract['id'] . " for email $buyerEmail to status: $status");
            break;
        }
    }
    
    if ($updated) {
        $saveData = ['contracts' => $contracts, 'templates' => $templates];
        file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        error_log("WEBHOOK: Contract with buyer email $buyerEmail not found in database");
    }
}

/**
 * Обновить статус договора в базе данных
 */
function updateContractStatus($documentId, $status, $signedDocumentUrl = null) {
    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    if (!file_exists($contractsFile)) {
        error_log("WEBHOOK: contracts.json not found");
        return;
    }
    
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
    
    // Ищем договор по documentId (adobeSignAgreementId)
    $updated = false;
    foreach ($contracts as &$contract) {
        if (isset($contract['adobeSignAgreementId']) && $contract['adobeSignAgreementId'] === $documentId) {
            $contract['status'] = $status;
            $contract['signedAt'] = date('c');
            
            if ($signedDocumentUrl) {
                $contract['signedDocumentUrl'] = $signedDocumentUrl;
            }
            
            $updated = true;
            error_log("WEBHOOK: Updated contract " . $contract['id'] . " to status: $status");
            break;
        }
    }
    
    if ($updated) {
        $saveData = ['contracts' => $contracts, 'templates' => $templates];
        file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        error_log("WEBHOOK: Contract with documentId $documentId not found in database");
    }
}
?>
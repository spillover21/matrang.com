<?php
// api/send_contract_email.php
// Dedicated micro-service for sending contract emails
// Handles bilingual (En/Ru) content and robust error logging

// 1. Setup Environment
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$logFile = __DIR__ . '/email_service.log';
function writeLog($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

// 2. Load PHPMailer
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'
];

$loaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    writeLog("CRITICAL: Vendor autoload not found in any known path.");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Configuration Error: PHPMailer not found']);
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. Load Configuration
$configFile = __DIR__ . '/smtp_config.php';
if (!file_exists($configFile)) {
    writeLog("CRITICAL: smtp_config.php missing.");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'SMTP Config missing']);
    exit();
}

$smtpConfig = require $configFile;

// 4. Parse Input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input) {
    writeLog("Error: Invalid JSON input.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$email = $input['email'] ?? '';
$link = $input['link'] ?? '';
$contractNumber = $input['contractNumber'] ?? 'Unknown';
$name = $input['name'] ?? 'Buyer';
$sellerEmail = isset($input['sellerEmail']) ? trim($input['sellerEmail']) : '';
$sellerName = isset($input['sellerName']) ? trim($input['sellerName']) : '';

if (!$email || !$link) {
    writeLog("Error: Missing required fields (email or link).");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing email or link']);
    exit();
}

writeLog("Processing email for $email (Contract: $contractNumber)");

// 5. Send Emails
try {
    // --- EMAIL 1: BUYER (Bilingual) ---
    $mail = new PHPMailer(true);
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
    
    // Reply-To logic
    if ($sellerEmail && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($sellerEmail, $sellerName ?: 'Seller');
    } else {
        $mail->addReplyTo($smtpConfig['reply_to'], $fromName);
    }

    $mail->addAddress($email, $name);
    
    // Bilingual Subject
    $mail->Subject = "Action Required: Sign Contract #{$contractNumber} / Подпишите договор №{$contractNumber}";

    // Bilingual HTML Body
    $bodyHtml = "
    <div style='font-family: Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; color: #333333; line-height: 1.6; border: 1px solid #e0e0e0; border-radius: 8px;'>
        
        <!-- HEADER -->
        <div style='text-align: center; padding: 20px 0; border-bottom: 2px solid #f0f0f0;'>
            <h2 style='margin: 0; color: #2563eb;'>{$fromName}</h2>
        </div>

        <!-- ENGLISH SECTION -->
        <div style='padding: 30px 30px 20px 30px;'>
            <h3 style='margin-top: 0; color: #1e293b;'>Hello {$name},</h3>
            <p>Your puppy purchase contract <strong>#{$contractNumber}</strong> has been generated and is ready for your signature.</p>
            <p>Please review and sign the document electronically using the link below:</p>
            
            <div style='text-align: center; margin: 25px 0;'>
                <a href='{$link}' style='background-color: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>Review and Sign Contract</a>
            </div>
            
            <p style='font-size: 14px; color: #64748b;'>If the button doesn't work, copy this link into your browser:</p>
            <div style='background: #f1f5f9; padding: 10px; font-size: 12px; color: #475569; word-break: break-all; border-radius: 4px;'>
                {$link}
            </div>
        </div>

        <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 0 30px;'>

        <!-- RUSSIAN SECTION -->
        <div style='padding: 20px 30px 30px 30px;'>
            <h3 style='margin-top: 0; color: #1e293b;'>Здравствуйте, {$name}!</h3>
            <p>Ваш договор купли-продажи щенка <strong>№{$contractNumber}</strong> сформирован и готов к подписанию.</p>
            <p>Пожалуйста, ознакомьтесь с документом и подпишите его, нажав на кнопку ниже:</p>
            
            <div style='text-align: center; margin: 25px 0;'>
                <a href='{$link}' style='background-color: #10b981; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>Посмотреть и подписать</a>
            </div>
            
             <p style='font-size: 14px; color: #64748b;'>Если кнопка выше не работает, используйте эту ссылку:</p>
             <div style='background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; font-size: 12px; color: #475569; word-break: break-all; border-radius: 4px;'>
                {$link}
            </div>
        </div>

        <!-- FOOTER -->
        <div style='text-align: center; padding: 20px; font-size: 12px; color: #94a3b8; border-top: 2px solid #f0f0f0; background-color: #f8fafc; border-radius: 0 0 8px 8px;'>
            <p>Contract ID: {$contractNumber}<br>
            Sent via Pitbull Elite System</p>
        </div>
    </div>
    ";

    $mail->isHTML(true);
    $mail->Body = $bodyHtml;
    // Alternative plain text body
    $mail->AltBody = "Hello $name,\n\nPlease sign your contract #$contractNumber here: $link\n\nЗдравствуйте $name,\n\nПожалуйста, подпишите ваш договор #$contractNumber здесь: $link";

    $mail->send();
    writeLog("Buyer email sent successfully.");

    // --- EMAIL 2: SELLER COPY ---
    // Only send if seller email is provided and different from buyer
    if ($sellerEmail && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL) && strtolower(trim($sellerEmail)) !== strtolower(trim($email))) {
        
        $mail2 = new PHPMailer(true);
        $mail2->isSMTP();
        $mail2->Host = $smtpConfig['host'];
        $mail2->SMTPAuth = $smtpConfig['auth'];
        $mail2->Username = $smtpConfig['username'];
        $mail2->Password = $smtpConfig['password'];
        $mail2->SMTPSecure = $smtpConfig['encryption'];
        $mail2->Port = $smtpConfig['port'];
        $mail2->CharSet = 'UTF-8';

        $mail2->setFrom($smtpConfig['from_email'], $fromName);
        $mail2->addReplyTo($smtpConfig['reply_to'], $fromName);
        $mail2->addAddress($sellerEmail);
        
        $mail2->Subject = "[COPY/КОПИЯ] Contract sent to {$name} (#{$contractNumber})";
        
        $sellerBodyHtml = "
        <div style='font-family: Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; color: #333333; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            <h2 style='color: #475569; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0;'>Transaction Confirmation / Подтверждение</h2>
            
            <p>You have successfully sent a contract invitation to <strong>{$name}</strong>.</p>
            
            <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                <p style='margin: 5px 0;'><strong>Buyer Email:</strong> {$email}</p>
                <p style='margin: 5px 0;'><strong>Contract Number:</strong> {$contractNumber}</p>
                <p style='margin: 5px 0;'><strong>Date Sent:</strong> " . date('Y-m-d H:i') . "</p>
            </div>

            <p>You can view the document status using this link (same as buyer):</p>
            <p><a href='{$link}' style='color: #2563eb; text-decoration: underline;'>{$link}</a></p>

            <hr style='margin: 25px 0; border: 0; border-top: 1px solid #eee;'>
            
            <h3 style='color: #475569; margin-top: 0;'>Информация для продавца</h3>
            <p>Вы успешно отправили приглашение к подписанию договора пользователю <strong>{$name}</strong>.</p>
             <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                <p style='margin: 5px 0;'><strong>Email покупателя:</strong> {$email}</p>
                <p style='margin: 5px 0;'><strong>Номер договора:</strong> {$contractNumber}</p>
            </div>
            <p>Ссылка на документ (та же, что у покупателя):</p>
            <p><a href='{$link}' style='color: #2563eb; text-decoration: underline;'>Открыть документ</a></p>
        </div>
        ";

        $mail2->isHTML(true);
        $mail2->Body = $sellerBodyHtml;
        $mail2->send();
        writeLog("Seller copy sent to $sellerEmail.");
    }

    echo json_encode(['success' => true, 'message' => 'Emails sent successfully']);

} catch (Exception $e) {
    writeLog("Mailer Error: " . $mail->ErrorInfo);
    // Return success=false but with 200 OK so frontend handles it gracefully
    echo json_encode(['success' => false, 'message' => 'Email sending failed: ' . $mail->ErrorInfo]);
} catch (\Throwable $e) {
    writeLog("General Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}

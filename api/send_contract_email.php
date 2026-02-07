<?php
// api/send_contract_email.php - Dedicated email sender
// Isolated from main API to prevent global breakage

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Logging
$logFile = __DIR__ . '/email_service.log';
function logMail($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

logMail("--- Request Start ---");

// 1. Input Parsing
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    logMail("Invalid JSON");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$email = $input['email'] ?? '';
$link = $input['link'] ?? '';
$name = $input['name'] ?? 'Buyer';
$contractNumber = $input['contractNumber'] ?? 'Unknown';
$sellerEmail = $input['sellerEmail'] ?? '';
$sellerName = $input['sellerName'] ?? '';

logMail("To: $email, Seller: $sellerEmail");

// 2. Load Dependencies
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    } else {
        throw new Exception("Vendor autoload not found");
    }
} catch (Throwable $e) {
    logMail("Autoload fail: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server Error: Dependencies']);
    exit();
}

// 3. Load Config
if (!file_exists(__DIR__ . '/smtp_config.php')) {
    logMail("Config missing");
    echo json_encode(['success' => false, 'message' => 'Server Error: Config']);
    exit();
}
$smtpConfig = require __DIR__ . '/smtp_config.php';

// 4. Send Function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendTheMail($to, $subject, $body, $config, $fromName, $replyTo) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = $config['auth'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['from_email'], $fromName);
        $mail->addReplyTo($replyTo ? $replyTo : $config['reply_to'], $fromName);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        logMail("Sent OK to $to");
        return true;
    } catch (Exception $e) {
        logMail("Send FAIL to $to: " . $mail->ErrorInfo);
        return false;
    }
}

// 5. Execution
$success = true;
$logs = [];

// Buyer Email
$fromName = $sellerName ?: $smtpConfig['from_name'];
$buyerReplyTo = ($sellerEmail && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) ? $sellerEmail : null;

$buyerBody = "
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

if (sendTheMail($email, "Подписание договора ({$contractNumber})", $buyerBody, $smtpConfig, $fromName, $buyerReplyTo)) {
    $logs[] = "Buyer email sent";
} else {
    $success = false;
    $logs[] = "Buyer email failed";
}

// Seller Copy
if ($sellerEmail && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL) && strtolower($sellerEmail) !== strtolower($email)) {
    $sellerBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #f8fafc;'>
        <h2 style='color: #475569;'>Копия отправленного договора</h2>
        <p>Вы отправили договор покупателю <strong>{$name}</strong>.</p>
        <p><strong>Номер договора:</strong> {$contractNumber}</p>
    </div>";
    
    if (sendTheMail($sellerEmail, "[КОПИЯ] Договор на {$name}", $sellerBody, $smtpConfig, $fromName, null)) {
        $logs[] = "Seller copy sent";
    }
}

echo json_encode(['success' => $success, 'logs' => $logs]);

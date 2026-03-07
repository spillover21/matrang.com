<?php
header('Content-Type: text/html; charset=utf-8');
// Debug SMTP
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>SMTP Debug Utility</h1>";

// 1. Check Autoloader
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("<h2 style='color:red'>Critical: Autoload not found at $autoloadPath</h2>");
} else {
    require_once $autoloadPath;
    echo "<p style='color:green'>Autoload found.</p>";
}

// 2. Load Config
$configPath = __DIR__ . '/smtp_config.php';
if (!file_exists($configPath)) {
    die("<h2 style='color:red'>Critical: Config not found at $configPath</h2>");
}
$smtpConfig = require $configPath;
echo "<p>Config loaded for host: {$smtpConfig['host']} : {$smtpConfig['port']}</p>";

// 3. Try PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;  // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = $smtpConfig['host'];
    $mail->SMTPAuth   = $smtpConfig['auth'];
    $mail->Username   = $smtpConfig['username'];
    $mail->Password   = $smtpConfig['password'];
    $mail->SMTPSecure = $smtpConfig['encryption'];
    $mail->Port       = $smtpConfig['port'];
    $mail->CharSet    = 'UTF-8';

    // Recipients
    $mail->setFrom($smtpConfig['from_email'], 'Test script');
    
    // Send to SELF (the sender) to verify basic connectivity
    $targetEmail = $smtpConfig['username'];
    if (isset($_GET['to'])) {
        $targetEmail = $_GET['to'];
    }
    
    echo "<h3>Attempting to send email to: $targetEmail</h3>";
    echo "<pre style='background:#f0f0f0; padding:10px; border:1px solid #ccc;'>";
    
    $mail->addAddress($targetEmail);
    $mail->Subject = 'Test Email from Debugger ' . date('H:i:s');
    $mail->Body    = 'This is a test email <b>in bold</b>!';

    $mail->send();
    echo "</pre>";
    echo "<h2 style='color:green'>Message has been sent successfully</h2>";
} catch (Exception $e) {
    echo "</pre>";
    echo "<h2 style='color:red'>Message could not be sent. Mailer Error: {$mail->ErrorInfo}</h2>";
}
?>
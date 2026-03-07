<?php
// api/test_direct_send.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Direct Mail Test</h1>";

// 1. Autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "Autoload: OK<br>";
} else {
    die("Autoload: MISSING");
}

// 2. Config
$smtpConfig = require __DIR__ . '/smtp_config.php';
echo "Config: Loaded " . $smtpConfig['host'] . "<br>";

// 3. Logic copied from api.php
try {
    echo "Attempting to create PHPMailer...<br>";
    
    // Explicit global namespace test
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    echo "PHPMailer Created.<br>";
    
    $mail->isSMTP();
    $mail->Host = $smtpConfig['host'];
    $mail->SMTPAuth = $smtpConfig['auth'];
    $mail->Username = $smtpConfig['username'];
    $mail->Password = $smtpConfig['password'];
    $mail->SMTPSecure = $smtpConfig['encryption'];
    $mail->Port = $smtpConfig['port'];
    $mail->CharSet = 'UTF-8';
    
    $mail->setFrom($smtpConfig['from_email'], "Test Script");
    // Change this to your email for testing
    $to = "test@example.com"; 
    
    echo "Configured for host: {$mail->Host}<br>";
    
    // DRY RUN - Don't actually send unless we want to spam, but connection check is good.
    // Actually, let's try to send to the noreply address itself just to see if it connects.
    $mail->addAddress($smtpConfig['from_email']); 
    $mail->Subject = "Direct Test " . date('H:i:s');
    $mail->Body = "Body content";
    
    $mail->send();
    echo "<h2 style='color:green'>Sent successfully!</h2>";

} catch (\Throwable $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

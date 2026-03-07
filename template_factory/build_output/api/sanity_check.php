<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html');

echo "<h1>Sanity Check</h1>";

echo "<h2>1. Check Vendor</h2>";
$vendor = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendor)) {
    echo "Vendor found at $vendor<br>";
    try {
        require_once $vendor;
        echo "Vendor require success<br>";
    } catch (Throwable $e) {
        echo "Vendor require failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Vendor NOT found at $vendor<br>";
    // Check parent
    $vendor2 = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($vendor2)) {
        echo "Parent vendor found at $vendor2<br>";
    }
}

echo "<h2>2. Check Config</h2>";
$conf = __DIR__ . '/smtp_config.php';
if (file_exists($conf)) {
    echo "Config found<br>";
    try {
        $c = require $conf;
        echo "Config loaded. Host: " . ($c['host'] ?? 'MISSING') . "<br>";
    } catch (Throwable $e) {
        echo "Config load failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Config NOT found<br>";
}

echo "<h2>3. Check PHPMailer Class</h2>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "PHPMailer class exists.<br>";
} else {
    echo "PHPMailer class missing.<br>";
}

echo "<h2>4. File Write Test</h2>";
$log = __DIR__ . '/test_write.log';
if (file_put_contents($log, "test")) {
    echo "Write OK to $log<br>";
} else {
    echo "Write FAILED to $log<br>";
}

echo "<h1>Done</h1>";

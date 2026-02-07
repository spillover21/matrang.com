<?php
// api/show_logs.php
header('Content-Type: text/plain; charset=utf-8');

echo "=== SERVER INFO ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";

echo "\n=== GLOBAL DEBUG LOG ===\n";
if (file_exists('global_debug.log')) {
    echo file_get_contents('global_debug.log');
} else {
    echo "(File not found)\n";
}

echo "\n=== EMAIL DEBUG LOG ===\n";
if (file_exists('email_debug.log')) {
    echo file_get_contents('email_debug.log');
} else {
    echo "(File not found)\n";
}

echo "\n=== PHP ERROR LOG (if accessible) ===\n";
$errLog = ini_get('error_log');
echo "Log path: $errLog\n";
if (file_exists($errLog) && is_readable($errLog)) {
    // Show last 20 lines
    $lines = file($errLog);
    echo implode("", array_slice($lines, -20));
} else {
    echo "(Not accessible or empty)\n";
}

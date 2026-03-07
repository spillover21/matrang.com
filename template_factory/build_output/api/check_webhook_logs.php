<?php
// Проверка логов вебхука
header('Content-Type: text/plain; charset=utf-8');

// Проверяем последние 50 строк error_log
$logFile = ini_get('error_log');

if (!$logFile || $logFile === 'syslog') {
    // Пробуем стандартные пути
    $possiblePaths = [
        '/var/log/apache2/error.log',
        '/var/log/httpd/error_log',
        __DIR__ . '/../error_log',
        __DIR__ . '/../../error_log',
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $logFile = $path;
            break;
        }
    }
}

echo "Log file: $logFile\n";
echo str_repeat("=", 80) . "\n\n";

if ($logFile && file_exists($logFile)) {
    $lines = file($logFile);
    $webhookLines = array_filter($lines, function($line) {
        return stripos($line, 'WEBHOOK') !== false;
    });
    
    $recent = array_slice($webhookLines, -50);
    
    if (empty($recent)) {
        echo "No webhook logs found in last entries\n";
        echo "\nShowing last 20 lines of error log:\n";
        echo str_repeat("-", 80) . "\n";
        echo implode("", array_slice($lines, -20));
    } else {
        echo "Last " . count($recent) . " webhook log entries:\n";
        echo str_repeat("-", 80) . "\n";
        echo implode("", $recent);
    }
} else {
    echo "Error log file not found or not accessible\n";
    echo "Trying to trigger an error to locate log file...\n\n";
    
    error_log("WEBHOOK_TEST: This is a test log entry from check_webhook_logs.php");
    
    echo "Test log entry written. Check your hosting control panel for error logs.\n";
    echo "Common locations:\n";
    echo "- cPanel: Error Log viewer in cPanel\n";
    echo "- Plesk: Logs & Statistics > Error Log\n";
    echo "- Direct: /var/log/apache2/ or /var/log/httpd/\n";
}

echo "\n\n" . str_repeat("=", 80) . "\n";
echo "Recent contracts from database:\n";
echo str_repeat("-", 80) . "\n";

$contractsFile = __DIR__ . '/../data/contracts.json';
if (file_exists($contractsFile)) {
    $data = json_decode(file_get_contents($contractsFile), true);
    $contracts = $data['contracts'] ?? [];
    
    usort($contracts, function($a, $b) {
        return strtotime($b['createdAt'] ?? '0') - strtotime($a['createdAt'] ?? '0');
    });
    
    foreach (array_slice($contracts, 0, 5) as $contract) {
        echo "\nContract: " . $contract['contractNumber'] . "\n";
        echo "  Created: " . ($contract['createdAt'] ?? 'N/A') . "\n";
        echo "  Status: " . $contract['status'] . "\n";
        echo "  Envelope ID: " . ($contract['adobeSignAgreementId'] ?? 'N/A') . "\n";
        echo "  Signed At: " . ($contract['signedAt'] ?? 'Not signed') . "\n";
        echo "  Signed URL: " . ($contract['signedDocumentUrl'] ?? 'No URL') . "\n";
        echo "  Buyer: " . ($contract['data']['buyerEmail'] ?? 'N/A') . "\n";
    }
} else {
    echo "Contracts file not found\n";
}

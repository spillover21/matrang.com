<?php
// Очистка кеша и проверка версии файлов API
header('Content-Type: text/plain; charset=utf-8');

echo "=== CACHE CLEAR ===\n\n";

// Очистка OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared\n";
} else {
    echo "❌ OPcache not available\n";
}

// Очистка реалпат кеша
clearstatcache(true);
echo "✅ Stat cache cleared\n\n";

// Проверка версии files
$files = [
    'contracts_api.php' => [
        'check' => 'seller_signing_url',
        'not_contain' => 'seller_debug'
    ],
    'ContractService.php' => [
        'check' => 'seller_signing_url',
        'not_contain' => 'str_replace(\':9000\''
    ]
];

echo "=== FILE VERSION CHECK ===\n\n";

foreach ($files as $filename => $checks) {
    $filePath = __DIR__ . '/' . $filename;
    echo "File: $filename\n";
    echo "  Full path: $filePath\n";
    
    if (!file_exists($filePath)) {
        echo "  ❌ NOT FOUND\n\n";
        continue;
    }
    
    echo "  Modified: " . date('Y-m-d H:i:s', filemtime($filePath)) . "\n";
    echo "  Size: " . filesize($filePath) . " bytes\n";
    
    $content = file_get_contents($filePath);
    
    $hasNew = strpos($content, $checks['check']) !== false;
    $hasOld = strpos($content, $checks['not_contain']) !== false;
    
    echo "  Checking for: '{$checks['check']}' - " . ($hasNew ? 'FOUND' : 'NOT FOUND') . "\n";
    echo "  Checking old: '{$checks['not_contain']}' - " . ($hasOld ? 'FOUND' : 'NOT FOUND') . "\n";
    
    if ($hasNew && !$hasOld) {
        echo "  ✅ NEW VERSION\n";
    } elseif (!$hasNew) {
        echo "  ❌ OLD VERSION (missing: {$checks['check']})\n";
    } elseif ($hasOld) {
        echo "  ⚠️ MIXED VERSION (has old code: {$checks['not_contain']})\n";
    }
    
    echo "\n";
}

echo "Done! Refresh your admin page now.\n";
?>

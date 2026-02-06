<?php
// Очистка кеша и проверка версии файла
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

// Проверка версии DocumensoService.php
echo "=== FILE CHECK ===\n\n";
$filePath = __DIR__ . '/DocumensoService.php';
echo "File: $filePath\n";
echo "Exists: " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";
echo "Modified: " . date('Y-m-d H:i:s', filemtime($filePath)) . "\n";
echo "Size: " . filesize($filePath) . " bytes\n\n";

// Проверяем contains  нужную строку
$content = file_get_contents($filePath);
if (strpos($content, 'URL received (length:') !== false) {
    echo "✅ NEW VERSION detected!\n";
} else {
    echo "❌ OLD VERSION still present\n";
}

echo "\nSearching for logging lines:\n";
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (stripos($line, 'download url received') !== false || 
        stripos($line, 'url replaced') !== false) {
        echo "Line " . ($num + 1) . ": " . trim($line) . "\n";
    }
}
?>

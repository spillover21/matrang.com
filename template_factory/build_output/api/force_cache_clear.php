<?php
// Принудительная очистка ВСЕХ кешей и проверка файлов

header('Content-Type: text/plain; charset=utf-8');
echo "=== ОЧИСТКА КЕШЕЙ ===\n\n";

// 1. OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared\n";
}

// 2. Stat cache
clearstatcache(true);
echo "✅ Stat cache cleared\n";

// 3. Реалпаф кеш
if (function_exists('realpath_cache_size')) {
    echo "Realpath cache size: " . realpath_cache_size() . " bytes\n";
}

echo "\n=== ПРОВЕРКА ФАЙЛОВ ===\n\n";

// Проверяем DocumensoServiceNew.php
$newFile = __DIR__ . '/DocumensoServiceNew.php';
if (file_exists($newFile)) {
    echo "✅ DocumensoServiceNew.php EXISTS\n";
    echo "   Size: " . filesize($newFile) . " bytes\n";
    echo "   Modified: " . date('Y-m-d H:i:s', filemtime($newFile)) . "\n";
    
    // Проверяем содержимое
    $content = file_get_contents($newFile);
    if (strpos($content, 'class DocumensoServiceNew') !== false) {
        echo "   ✅ Contains 'class DocumensoServiceNew'\n";
    } else {
        echo "   ❌ Does NOT contain 'class DocumensoServiceNew'\n";
    }
} else {
    echo "❌ DocumensoServiceNew.php NOT FOUND!\n";
}

echo "\n";

// Проверяем webhook_documenso.php
$webhookFile = __DIR__ . '/webhook_documenso.php';
if (file_exists($webhookFile)) {
    echo "✅ webhook_documenso.php EXISTS\n";
    echo "   Modified: " . date('Y-m-d H:i:s', filemtime($webhookFile)) . "\n";
    
    $content = file_get_contents($webhookFile);
    if (strpos($content, 'DocumensoServiceNew') !== false) {
        echo "   ✅ Uses 'DocumensoServiceNew'\n";
    } else {
        echo "   ❌ Still uses old 'DocumensoService'\n";
    }
}

echo "\n=== ГОТОВО ===\n";
echo "Теперь вызовите webhook для теста.\n";
?>

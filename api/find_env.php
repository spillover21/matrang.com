<?php
// Диагностика: где находится .env файл?

$paths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/.env',
    '/home/u654127295/.env',
    '/home/u654127295/domains/matrang.com/.env',
    '/home/u654127295/domains/matrang.com/public_html/.env',
];

echo "=== Поиск .env файла ===\n\n";

foreach ($paths as $path) {
    echo "Проверка: $path\n";
    if (file_exists($path)) {
        echo "✅ НАЙДЕН!\n";
        echo "Содержимое:\n";
        echo file_get_contents($path);
        echo "\n\n";
    } else {
        echo "❌ Не найден\n\n";
    }
}

echo "=== Текущая директория ===\n";
echo __DIR__ . "\n\n";

echo "=== Проверка documenso_config.php ===\n";
$configFile = __DIR__ . '/documenso_config.php';
if (file_exists($configFile)) {
    echo "✅ documenso_config.php найден\n";
    include $configFile;
    echo "API_KEY из конфига: " . ($config['API_KEY'] ?? 'НЕ УСТАНОВЛЕН') . "\n";
} else {
    echo "❌ documenso_config.php не найден\n";
}
?>

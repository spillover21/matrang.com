<?php
// debug_config.php - Показывает текущую конфигурацию
$config = require __DIR__ . '/api/documenso_config.php';

echo "=== DOCUMENSO CONFIGURATION ===\n\n";
echo "DOCUMENSO_URL: " . ($config['DOCUMENSO_URL'] ?? 'NOT SET') . "\n";
echo "DOCUMENSO_API_TOKEN: " . (isset($config['DOCUMENSO_API_TOKEN']) ? substr($config['DOCUMENSO_API_TOKEN'], 0, 10) . '...' : 'NOT SET') . "\n";
echo "WEBHOOK_SECRET: " . (isset($config['WEBHOOK_SECRET']) ? substr($config['WEBHOOK_SECRET'], 0, 10) . '...' : 'NOT SET') . "\n\n";

echo "=== ПРОБЛЕМА ===\n";
echo "Если DOCUMENSO_URL = http://localhost:9000 или http://127.0.0.1:9000\n";
echo "То webhook НЕ СМОЖЕТ подключиться к Documenso с ХОСТИНГА!\n\n";

echo "=== РЕШЕНИЕ ===\n";
echo "DOCUMENSO_URL должен быть: http://72.62.114.139:9000\n";
echo "(IP адрес VPS где запущен Documenso)\n";

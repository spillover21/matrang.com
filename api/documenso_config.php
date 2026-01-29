<?php
// documenso_config.php
// Конфигурация для Documenso API
// Автоматически читает данные из файла ../.env

// Простая функция для парсинга .env файла вручную
if (!function_exists('loadEnvConfig')) {
    function loadEnvConfig($path) {
        if (!file_exists($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Удаляем кавычки
                $value = trim($value, '"\'');
                // Удаляем inline комментарии
                $p = strpos($value, ' #');
                if ($p !== false) {
                    $value = substr($value, 0, $p);
                }
                $env[$name] = trim($value);
            }
        }
        return $env;
    }
}

// Загружаем переменные из .env в корне (на уровень выше api/)
$envPath = __DIR__ . '/../.env';
$env = loadEnvConfig($envPath);

// Возвращаем конфигурацию с фоллбэками на жестко прописанные значения
// Это позволяет работать на сервере без .env файла, если пользователь не может его создать
return [
    'API_KEY' => $env['DOCUMENSO_API_KEY'] ?? 'api_vei99lrwtlm6xfs4', 
    'API_URL' => $env['DOCUMENSO_API_URL'] ?? 'http://72.62.114.139:9000/api/v1',
    'WEBHOOK_SECRET' => $env['DOCUMENSO_WEBHOOK_SECRET'] ?? 'pXbQZ8@Y6akBjd5', 
    'TEMPLATE_ID' => $env['DOCUMENSO_TEMPLATE_ID'] ?? '1', 
    'PUBLIC_URL' => 'http://72.62.114.139:9000' 
];

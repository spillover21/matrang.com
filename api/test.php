<?php
/**
 * Тестовая страница для диагностики
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Диагностика системы договоров</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";

// 1. Проверка PHP
echo "<h2>1. PHP</h2>";
echo "Версия PHP: <span class='ok'>" . phpversion() . "</span><br>";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post max size: " . ini_get('post_max_size') . "<br>";

// 2. Проверка папок
echo "<h2>2. Папки</h2>";
$dirs = [
    __DIR__ . '/../uploads' => 'uploads',
    __DIR__ . '/../uploads/contracts' => 'uploads/contracts',
    __DIR__ . '/../uploads/contracts/filled' => 'uploads/contracts/filled',
    __DIR__ . '/../data' => 'data'
];

foreach ($dirs as $path => $name) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    $color = $writable ? 'ok' : ($exists ? 'warning' : 'error');
    echo "$name: <span class='$color'>" . ($exists ? ($writable ? 'OK (writable)' : 'Exists but NOT writable') : 'NOT EXISTS') . "</span><br>";
    
    if (!$exists) {
        @mkdir($path, 0755, true);
        if (is_dir($path)) {
            echo " → Created!<br>";
        }
    }
}

// 3. Проверка PDF шаблона
echo "<h2>3. PDF Шаблон</h2>";
$templatePath = __DIR__ . '/../uploads/contracts/contract_template.pdf';
if (file_exists($templatePath)) {
    $size = filesize($templatePath);
    echo "Файл: <span class='ok'>Найден</span><br>";
    echo "Размер: " . round($size / 1024, 2) . " KB<br>";
    echo "Путь: $templatePath<br>";
    echo "URL: <a href='/uploads/contracts/contract_template.pdf' target='_blank'>Открыть PDF</a><br>";
} else {
    echo "Файл: <span class='error'>НЕ НАЙДЕН</span><br>";
    echo "Ожидаемый путь: $templatePath<br>";
}

// 4. Проверка Composer библиотек
echo "<h2>4. Библиотеки</h2>";
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    echo "Composer autoload: <span class='ok'>OK</span><br>";
    require_once $autoload;
    
    if (class_exists('setasign\Fpdi\Fpdi')) {
        echo "FPDI: <span class='ok'>Установлена</span><br>";
    } else {
        echo "FPDI: <span class='error'>НЕ НАЙДЕНА</span><br>";
    }
} else {
    echo "Composer autoload: <span class='error'>НЕ НАЙДЕН</span><br>";
    echo "Путь: $autoload<br>";
}

// 5. Проверка шрифта
echo "<h2>5. Шрифт</h2>";
$fontPath = __DIR__ . '/DejaVuSansCondensed.ttf';
if (file_exists($fontPath)) {
    echo "DejaVu шрифт: <span class='ok'>OK</span> (" . round(filesize($fontPath) / 1024, 2) . " KB)<br>";
} else {
    echo "DejaVu шрифт: <span class='error'>НЕ НАЙДЕН</span><br>";
    echo "Путь: $fontPath<br>";
}

// 6. Проверка API
echo "<h2>6. API Endpoints</h2>";
echo "<a href='/api/api.php?action=test' target='_blank'>Тест API</a><br>";

// 7. Логи
echo "<h2>7. Логи</h2>";
$logs = [
    __DIR__ . '/../data/upload_debug.log' => 'Upload Debug',
    __DIR__ . '/../data/sendcontract_debug.log' => 'Send Contract Debug',
    __DIR__ . '/../data/pdf_generation.log' => 'PDF Generation',
    __DIR__ . '/../data/mail.log' => 'Mail Log'
];

foreach ($logs as $path => $name) {
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $lines = substr_count($content, "\n");
        echo "<strong>$name</strong> ($lines строк): ";
        echo "<a href='?showlog=" . urlencode($path) . "'>Показать</a> | ";
        echo "<a href='?clearlog=" . urlencode($path) . "'>Очистить</a><br>";
    } else {
        echo "<strong>$name</strong>: <span class='warning'>Пусто</span><br>";
    }
}

// Показать лог если запрошен
if (isset($_GET['showlog']) && file_exists($_GET['showlog'])) {
    echo "<h3>Содержимое лога:</h3>";
    echo "<pre style='background:#f0f0f0;padding:10px;overflow:auto;max-height:400px;'>";
    echo htmlspecialchars(file_get_contents($_GET['showlog']));
    echo "</pre>";
}

// Очистить лог если запрошен
if (isset($_GET['clearlog']) && file_exists($_GET['clearlog'])) {
    file_put_contents($_GET['clearlog'], '');
    echo "<p class='ok'>Лог очищен!</p>";
    echo "<script>setTimeout(() => location.href = 'test.php', 1000);</script>";
}

// 8. Тест отправки email
echo "<h2>8. Тест Email</h2>";
if (isset($_POST['test_email'])) {
    $to = $_POST['email'];
    $subject = "Тест отправки с matrang.com";
    $message = "Это тестовое письмо. Время: " . date('Y-m-d H:i:s');
    $headers = "From: noreply@matrang.com\r\nContent-Type: text/plain; charset=UTF-8";
    
    $sent = @mail($to, $subject, $message, $headers);
    echo "<p class='" . ($sent ? 'ok' : 'error') . "'>";
    echo $sent ? "Email отправлен на $to" : "ОШИБКА отправки на $to";
    echo "</p>";
}

echo "<form method='post'>
    <input type='email' name='email' placeholder='Email для теста' required>
    <button type='submit' name='test_email'>Отправить тестовое письмо</button>
</form>";

// 9. Информация о сервере
echo "<h2>9. Сервер</h2>";
echo "Сервер: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";

?>

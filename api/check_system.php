#!/usr/bin/env php
<?php
/**
 * Консольная диагностика системы договоров
 * Запуск: php check_system.php
 */

echo "\n===== ДИАГНОСТИКА СИСТЕМЫ ДОГОВОРОВ =====\n\n";

// 1. Проверка PHP
echo "1. PHP\n";
echo "   Версия: " . phpversion() . "\n";
echo "   Upload max: " . ini_get('upload_max_filesize') . "\n";
echo "   Post max: " . ini_get('post_max_size') . "\n\n";

// 2. Проверка папок
echo "2. Папки\n";
$baseDir = __DIR__ . '/..';
$dirs = [
    $baseDir . '/uploads',
    $baseDir . '/uploads/contracts',
    $baseDir . '/uploads/contracts/filled',
    $baseDir . '/data'
];

foreach ($dirs as $dir) {
    $name = str_replace($baseDir . '/', '', $dir);
    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);
    
    echo "   " . str_pad($name, 30);
    if (!$exists) {
        echo "❌ НЕ СУЩЕСТВУЕТ";
        @mkdir($dir, 0755, true);
        if (is_dir($dir)) {
            echo " → Создана ✅";
        }
    } elseif (!$writable) {
        echo "⚠️  Нет прав на запись";
    } else {
        echo "✅ OK";
    }
    echo "\n";
}
echo "\n";

// 3. Проверка файлов
echo "3. Файлы\n";
$files = [
    $baseDir . '/uploads/contracts/contract_template.pdf' => 'PDF шаблон',
    __DIR__ . '/vendor/autoload.php' => 'Composer autoload',
    __DIR__ . '/DejaVuSansCondensed.ttf' => 'Шрифт DejaVu',
    __DIR__ . '/generate_contract_pdf.php' => 'PDF генератор',
    $baseDir . '/data/contracts.json' => 'База договоров'
];

foreach ($files as $path => $name) {
    echo "   " . str_pad($name, 30);
    if (file_exists($path)) {
        $size = filesize($path);
        if ($size > 0) {
            echo "✅ " . round($size / 1024, 2) . " KB";
        } else {
            echo "⚠️  Файл пустой";
        }
    } else {
        echo "❌ НЕ НАЙДЕН";
    }
    echo "\n";
}
echo "\n";

// 4. Проверка библиотек
echo "4. Библиотеки\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    echo "   FPDI: ";
    if (class_exists('setasign\\Fpdi\\Fpdi')) {
        echo "✅ Установлена\n";
    } else {
        echo "❌ НЕ НАЙДЕНА\n";
    }
    
    echo "   FPDF: ";
    if (class_exists('setasign\\Fpdi\\Fpdf\\Fpdf')) {
        echo "✅ Установлена\n";
    } else {
        echo "❌ НЕ НАЙДЕНА\n";
    }
} else {
    echo "   ❌ Composer autoload не найден\n";
    echo "   Выполните: cd api && composer install\n";
}
echo "\n";

// 5. Проверка логов
echo "5. Последние логи\n";
$logs = [
    $baseDir . '/data/upload_debug.log' => 'Upload',
    $baseDir . '/data/sendcontract_debug.log' => 'Send Contract',
    $baseDir . '/data/mail.log' => 'Mail'
];

foreach ($logs as $path => $name) {
    echo "   " . str_pad($name, 20);
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = count($lines);
        echo "📝 $count записей";
        if ($count > 0) {
            echo "\n      Последняя: " . substr(end($lines), 0, 60) . "...";
        }
    } else {
        echo "📄 Пусто";
    }
    echo "\n";
}
echo "\n";

// 6. Тест генерации PDF
echo "6. Тест генерации PDF\n";
$templatePath = $baseDir . '/uploads/contracts/contract_template.pdf';
if (file_exists($templatePath) && file_exists(__DIR__ . '/generate_contract_pdf.php')) {
    require_once __DIR__ . '/generate_contract_pdf.php';
    
    $testData = [
        'contractNumber' => 'TEST-001',
        'contractDate' => date('d.m.Y'),
        'kennelOwner' => 'Тестовый владелец',
        'buyerName' => 'Тестовый покупатель',
        'dogName' => 'Тестовая собака',
        'price' => '50000'
    ];
    
    $testOutput = $baseDir . '/uploads/contracts/filled/test_contract.pdf';
    
    try {
        $result = generateContractPdf($templatePath, $testData, $testOutput);
        if ($result && file_exists($testOutput)) {
            echo "   ✅ PDF успешно сгенерирован\n";
            echo "   Размер: " . round(filesize($testOutput) / 1024, 2) . " KB\n";
            echo "   Путь: $testOutput\n";
            
            // Удаляем тестовый файл
            @unlink($testOutput);
        } else {
            echo "   ❌ Ошибка генерации PDF\n";
        }
    } catch (Exception $e) {
        echo "   ❌ ИСКЛЮЧЕНИЕ: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ⏭️  Пропущено (нет шаблона или генератора)\n";
}
echo "\n";

// 7. Итоговый статус
echo "===== ИТОГО =====\n\n";

$issues = [];

// Проверяем критичные компоненты
if (!file_exists($templatePath)) {
    $issues[] = "❌ Не загружен PDF шаблон договора";
}

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    $issues[] = "❌ Не установлены Composer зависимости (composer install)";
}

if (!class_exists('setasign\\Fpdi\\Fpdi')) {
    $issues[] = "❌ Библиотека FPDI не найдена";
}

if (!is_writable($baseDir . '/uploads/contracts')) {
    $issues[] = "❌ Нет прав на запись в uploads/contracts";
}

if (empty($issues)) {
    echo "🎉 ВСЁ ОТЛИЧНО! Система готова к работе.\n\n";
    echo "Следующий шаг:\n";
    echo "1. Откройте админку и загрузите PDF шаблон\n";
    echo "2. Заполните договор и отправьте на тестовый email\n";
    echo "3. Проверьте почту и логи\n";
} else {
    echo "⚠️  ОБНАРУЖЕНЫ ПРОБЛЕМЫ:\n\n";
    foreach ($issues as $issue) {
        echo "$issue\n";
    }
    echo "\nИсправьте их и запустите проверку снова.\n";
}

echo "\n";

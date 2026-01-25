<?php
// Проверка наличия Ghostscript на сервере
header('Content-Type: text/plain');

echo "=== GHOSTSCRIPT CHECK ===\n\n";

// Проверка через exec
$output = [];
$return = 0;

@exec('gs --version 2>&1', $output, $return);
if ($return === 0 && !empty($output)) {
    echo "✅ Ghostscript НАЙДЕН!\n";
    echo "Версия: " . implode("\n", $output) . "\n\n";
    echo "Можно использовать автоматическую конвертацию PDF!\n";
} else {
    echo "❌ Ghostscript НЕ НАЙДЕН\n\n";
    echo "Нужно:\n";
    echo "1. Пересохранить PDF вручную в совместимом формате\n";
    echo "2. Или попросить хостинг установить Ghostscript\n";
}

echo "\n=== АЛЬТЕРНАТИВЫ ===\n\n";

// Проверка qpdf
@exec('qpdf --version 2>&1', $output2, $return2);
if ($return2 === 0) {
    echo "✅ qpdf найден: " . implode("\n", $output2) . "\n";
} else {
    echo "❌ qpdf не найден\n";
}

// Проверка pdftk
@exec('pdftk --version 2>&1', $output3, $return3);
if ($return3 === 0) {
    echo "✅ pdftk найден\n";
} else {
    echo "❌ pdftk не найден\n";
}

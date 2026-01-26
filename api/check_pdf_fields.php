<?php
// Тестовый скрипт для проверки заполнения PDF полей

require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

$pdfPath = __DIR__ . '/../uploads/contracts/filled/contract_DOG-2026-0001.pdf';

if (!file_exists($pdfPath)) {
    die("PDF not found at: $pdfPath\n");
}

echo "Checking PDF: $pdfPath\n";
echo "File size: " . filesize($pdfPath) . " bytes\n\n";

// Используем pdf-parser для проверки полей
$content = file_get_contents($pdfPath);

// Ищем AcroForm в PDF
if (strpos($content, '/AcroForm') !== false) {
    echo "✓ AcroForm found in PDF\n";
    
    // Ищем объекты полей
    preg_match_all('/\/T\s*\((.*?)\)/', $content, $matches);
    
    if (!empty($matches[1])) {
        echo "\nFields found (" . count($matches[1]) . "):\n";
        foreach ($matches[1] as $fieldName) {
            echo "  - $fieldName\n";
        }
    } else {
        echo "✗ No field names found\n";
    }
    
    // Ищем заполненные значения
    preg_match_all('/\/V\s*\((.*?)\)/', $content, $values);
    
    if (!empty($values[1])) {
        echo "\nValues found (" . count($values[1]) . "):\n";
        foreach (array_slice($values[1], 0, 10) as $value) {
            $decoded = str_replace(['\\(', '\\)'], ['(', ')'], $value);
            echo "  - " . substr($decoded, 0, 50) . "\n";
        }
    } else {
        echo "✗ No values found - fields are EMPTY!\n";
    }
    
} else {
    echo "✗ No AcroForm found in PDF - this is not a fillable form\n";
}

echo "\n";

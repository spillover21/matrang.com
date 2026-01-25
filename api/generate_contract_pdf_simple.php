<?php
/**
 * Генерация PDF договора БЕЗ импорта шаблона
 * Создаёт PDF с нуля с данными договора
 */

require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdf\Fpdf;

function generateContractPdfSimple($data, $outputPath) {
    $logFile = __DIR__ . '/../data/pdf_generation_simple.log';
    file_put_contents($logFile, "\n=== SIMPLE PDF GENERATION === " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    
    try {
        $pdf = new Fpdf();
        $pdf->AddPage();
        
        // Добавляем шрифт для русского текста
        $fontPath = __DIR__ . '/DejaVuSansCondensed.ttf';
        $pdf->AddFont('DejaVu', '', $fontPath, true);
        $pdf->SetFont('DejaVu', '', 12);
        
        file_put_contents($logFile, "PDF instance created\n", FILE_APPEND);
        
        // Заголовок
        $pdf->SetFont('DejaVu', '', 16);
        $pdf->Cell(0, 10, iconv('UTF-8', 'windows-1251', 'ДОГОВОР КУПЛИ-ПРОДАЖИ ЩЕНКА'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Номер и дата
        $pdf->SetFont('DejaVu', '', 12);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', 'Договор № ' . ($data['contractNumber'] ?? '')), 0, 1);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', 'от ' . ($data['contractDate'] ?? date('d.m.Y'))), 0, 1);
        $pdf->Ln(5);
        
        // Заводчик
        $pdf->SetFont('DejaVu', '', 14);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', 'ПРОДАВЕЦ (Заводчик):'), 0, 1);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->MultiCell(0, 6, iconv('UTF-8', 'windows-1251', 
            'ФИО: ' . ($data['kennelOwner'] ?? '') . "\n" .
            'Адрес: ' . ($data['kennelAddress'] ?? '') . "\n" .
            'Телефон: ' . ($data['kennelPhone'] ?? '') . "\n" .
            'Email: ' . ($data['kennelEmail'] ?? '') . "\n" .
            'Паспорт: ' . ($data['kennelPassportSeries'] ?? '') . ' ' . ($data['kennelPassportNumber'] ?? '') . "\n" .
            'Выдан: ' . ($data['kennelPassportIssuedBy'] ?? '') . ', ' . ($data['kennelPassportIssuedDate'] ?? '')
        ));
        $pdf->Ln(3);
        
        // Покупатель
        $pdf->SetFont('DejaVu', '', 14);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', 'ПОКУПАТЕЛЬ:'), 0, 1);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->MultiCell(0, 6, iconv('UTF-8', 'windows-1251',
            'ФИО: ' . ($data['buyerName'] ?? '') . "\n" .
            'Адрес: ' . ($data['buyerAddress'] ?? '') . "\n" .
            'Телефон: ' . ($data['buyerPhone'] ?? '') . "\n" .
            'Email: ' . ($data['buyerEmail'] ?? '') . "\n" .
            'Паспорт: ' . ($data['buyerPassportSeries'] ?? '') . ' ' . ($data['buyerPassportNumber'] ?? '') . "\n" .
            'Выдан: ' . ($data['buyerPassportIssuedBy'] ?? '') . ', ' . ($data['buyerPassportIssuedDate'] ?? '')
        ));
        $pdf->Ln(3);
        
        // Родители
        $pdf->SetFont('DejaVu', '', 14);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', 'РОДИТЕЛИ ЩЕНКА:'), 0, 1);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->MultiCell(0, 6, iconv('UTF-8', 'windows-1251',
            'Отец (Sire): ' . ($data['sireName'] ?? '') . "\n" .
            'Мать (Dam): ' . ($data['damName'] ?? '')
        ));
        $pdf->Ln(3);
        
        // Щенок
        $pdf->SetFont('DejaVu', '', 14);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', 'ПРЕДМЕТ ДОГОВОРА - ЩЕНОК:'), 0, 1);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->MultiCell(0, 6, iconv('UTF-8', 'windows-1251',
            'Кличка: ' . ($data['dogName'] ?? '') . "\n" .
            'Порода: ' . ($data['dogBreed'] ?? 'American Bully') . "\n" .
            'Пол: ' . ($data['dogGender'] ?? '') . "\n" .
            'Окрас: ' . ($data['dogColor'] ?? '') . "\n" .
            'Дата рождения: ' . ($data['dogBirthDate'] ?? '') . "\n" .
            'Клеймо/Чип: ' . ($data['dogMicrochip'] ?? '')
        ));
        $pdf->Ln(3);
        
        // Финансы
        $pdf->SetFont('DejaVu', '', 14);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', 'СТОИМОСТЬ:'), 0, 1);
        $pdf->SetFont('DejaVu', '', 12);
        $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1251', ($data['price'] ?? '0') . ' рублей'), 0, 1);
        $pdf->Ln(5);
        
        // Подписи
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Ln(10);
        $pdf->Cell(90, 8, iconv('UTF-8', 'windows-1251', 'Продавец: _______________'), 0, 0);
        $pdf->Cell(90, 8, iconv('UTF-8', 'windows-1251', 'Покупатель: _______________'), 0, 1);
        
        // Сохраняем
        file_put_contents($logFile, "Saving to: $outputPath\n", FILE_APPEND);
        $pdf->Output('F', $outputPath);
        
        $exists = file_exists($outputPath);
        $size = $exists ? filesize($outputPath) : 0;
        file_put_contents($logFile, "Saved: " . ($exists ? 'YES' : 'NO') . " | Size: $size\n", FILE_APPEND);
        
        return $exists;
        
    } catch (Exception $e) {
        file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

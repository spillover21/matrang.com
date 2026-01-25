<?php
/**
 * Генерация заполненного PDF договора
 * Накладывает данные поверх PDF шаблона
 */

require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

function generateContractPdf($templatePath, $data, $outputPath) {
    try {
        $pdf = new Fpdi();
        
        // Импортируем страницы из шаблона
        $pageCount = $pdf->setSourceFile($templatePath);
        
        // Настройки шрифта
        $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
        $pdf->SetFont('DejaVu', '', 10);
        
        // Обрабатываем каждую страницу
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pdf->AddPage();
            
            // Импортируем страницу из шаблона
            $tplId = $pdf->importPage($pageNo);
            $pdf->useTemplate($tplId, 0, 0);
            
            // Накладываем текст на первую страницу
            if ($pageNo === 1) {
                // Номер договора (координаты примерные - нужно настроить под ваш шаблон)
                $pdf->SetXY(95, 123); // Позиция для номера договора
                $pdf->Write(0, $data['contractNumber'] ?? '');
                
                // Дата
                $pdf->SetXY(95, 126);
                $pdf->Write(0, $data['contractDate'] ?? '');
                
                // ЗАВОДЧИК
                // ФИО
                $pdf->SetXY(25, 212);
                $pdf->Write(0, $data['kennelOwner'] ?? '');
                
                // Адрес
                $pdf->SetXY(25, 237);
                $pdf->MultiCell(170, 5, $data['kennelAddress'] ?? '', 0, 'L');
                
                // Телефон
                $pdf->SetXY(45, 261);
                $pdf->Write(0, $data['kennelPhone'] ?? '');
                
                // Email
                $pdf->SetXY(35, 285);
                $pdf->Write(0, $data['kennelEmail'] ?? '');
                
                // ПОКУПАТЕЛЬ
                // ФИО
                $pdf->SetXY(25, 352);
                $pdf->Write(0, $data['buyerName'] ?? '');
                
                // Адрес
                $pdf->SetXY(25, 376);
                $pdf->MultiCell(170, 5, $data['buyerAddress'] ?? '', 0, 'L');
                
                // Телефон
                $pdf->SetXY(45, 401);
                $pdf->Write(0, $data['buyerPhone'] ?? '');
                
                // Email
                $pdf->SetXY(35, 425);
                $pdf->Write(0, $data['buyerEmail'] ?? '');
                
                // ЩЕНОК
                // Кличка
                $pdf->SetXY(55, 522);
                $pdf->Write(0, $data['dogName'] ?? '');
                
                // Порода
                $pdf->SetXY(50, 546);
                $pdf->Write(0, $data['dogBreed'] ?? 'Американский булли');
                
                // Дата рождения
                $pdf->SetXY(85, 570);
                $pdf->Write(0, $data['dogBirthDate'] ?? '');
                
                // Пол
                $pdf->SetXY(35, 594);
                $pdf->Write(0, $data['dogGender'] ?? '');
                
                // Окрас
                $pdf->SetXY(45, 618);
                $pdf->Write(0, $data['dogColor'] ?? '');
                
                // ФИНАНСЫ
                // Стоимость
                $pdf->SetXY(90, 700);
                $pdf->Write(0, $data['price'] ?? '');
            }
        }
        
        // Сохраняем PDF
        $pdf->Output('F', $outputPath);
        return true;
        
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return false;
    }
}

// Генерация номера договора
function generateContractNumber($contracts) {
    return 'DOG-' . date('Y') . '-' . str_pad(count($contracts) + 1, 4, '0', STR_PAD_LEFT);
}

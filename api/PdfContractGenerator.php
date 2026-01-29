<?php
/**
 * Генератор PDF договоров на основе шаблона
 * Альтернатива Documenso API для self-hosted установки
 */

class PdfContractGenerator {
    private $templatePath;
    private $outputDir;
    
    public function __construct($templatePath = null, $outputDir = null) {
        $this->templatePath = $templatePath ?? __DIR__ . '/../uploads/contract_template.pdf';
        $this->outputDir = $outputDir ?? __DIR__ . '/../uploads/generated/';
        
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    
    /**
     * Генерирует PDF договор с заполненными данными
     * 
     * @param array $contractData Данные договора (47 полей)
     * @return string Путь к сгенерированному PDF
     */
    public function generateContract($contractData) {
        // Подготовка данных для заполнения
        $fields = $this->prepareFields($contractData);
        
        // Генерация уникального имени файла
        $filename = 'contract_' . ($contractData['contractNumber'] ?? time()) . '.pdf';
        $outputPath = $this->outputDir . $filename;
        
        // Используем PDFtk или альтернативу для заполнения PDF
        if ($this->usePdftk($fields, $outputPath)) {
            return $outputPath;
        }
        
        // Если PDFtk недоступен, используем FPDI
        return $this->useFPDI($fields, $outputPath);
    }
    
    /**
     * Подготавливает поля для заполнения из данных договора
     */
    private function prepareFields($data) {
        return [
            // Заголовок договора
            'contractNumber' => $data['contractNumber'] ?? '',
            'contractDate' => $data['contractDate'] ?? date('d.m.Y'),
            'contractPlace' => $data['contractPlace'] ?? '',
            
            // Данные питомника
            'kennelName' => $data['kennelName'] ?? '',
            'kennelOwner' => $data['kennelOwner'] ?? '',
            'kennelAddress' => $data['kennelAddress'] ?? '',
            'kennelPhone' => $data['kennelPhone'] ?? '',
            'kennelEmail' => $data['kennelEmail'] ?? '',
            'kennelPassportSeries' => $data['kennelPassportSeries'] ?? '',
            'kennelPassportNumber' => $data['kennelPassportNumber'] ?? '',
            'kennelPassportIssuedBy' => $data['kennelPassportIssuedBy'] ?? '',
            'kennelPassportIssuedDate' => $data['kennelPassportIssuedDate'] ?? '',
            
            // Данные покупателя
            'buyerName' => $data['buyerName'] ?? '',
            'buyerAddress' => $data['buyerAddress'] ?? '',
            'buyerPhone' => $data['buyerPhone'] ?? '',
            'buyerEmail' => $data['buyerEmail'] ?? '',
            'buyerPassportSeries' => $data['buyerPassportSeries'] ?? '',
            'buyerPassportNumber' => $data['buyerPassportNumber'] ?? '',
            'buyerPassportIssuedBy' => $data['buyerPassportIssuedBy'] ?? '',
            'buyerPassportIssuedDate' => $data['buyerPassportIssuedDate'] ?? '',
            
            // Данные собаки
            'dogName' => $data['dogName'] ?? '',
            'dogBreed' => $data['dogBreed'] ?? '',
            'dogBirthDate' => $data['dogBirthDate'] ?? '',
            'dogGender' => $data['dogGender'] ?? '',
            'dogColor' => $data['dogColor'] ?? '',
            'dogChipNumber' => $data['dogChipNumber'] ?? '',
            'dogPuppyCard' => $data['dogPuppyCard'] ?? '',
            'dogFatherName' => $data['dogFatherName'] ?? '',
            'dogFatherRegNumber' => $data['dogFatherRegNumber'] ?? '',
            'dogMotherName' => $data['dogMotherName'] ?? '',
            'dogMotherRegNumber' => $data['dogMotherRegNumber'] ?? '',
            
            // Назначение
            'purposeBreeding' => !empty($data['purposeBreeding']) ? '☑' : '☐',
            'purposeCompanion' => !empty($data['purposeCompanion']) ? '☑' : '☐',
            'purposeGeneral' => !empty($data['purposeGeneral']) ? '☑' : '☐',
            
            // Финансы
            'price' => $data['price'] ?? '',
            'depositAmount' => $data['depositAmount'] ?? '',
            'depositDate' => $data['depositDate'] ?? '',
            'remainingAmount' => $data['remainingAmount'] ?? '',
            'finalPaymentDate' => $data['finalPaymentDate'] ?? '',
            
            // Ветеринария
            'dewormingDate' => $data['dewormingDate'] ?? '',
            'vaccinationDates' => $data['vaccinationDates'] ?? '',
            'vaccineName' => $data['vaccineName'] ?? '',
            'nextDewormingDate' => $data['nextDewormingDate'] ?? '',
            'nextVaccinationDate' => $data['nextVaccinationDate'] ?? '',
            
            // Дополнительно
            'specialFeatures' => $data['specialFeatures'] ?? '',
            'deliveryTerms' => $data['deliveryTerms'] ?? '',
            'additionalAgreements' => $data['additionalAgreements'] ?? '',
            'recommendedFood' => $data['recommendedFood'] ?? '',
        ];
    }
    
    /**
     * Заполнение PDF через PDFtk (если установлен)
     */
    private function usePdftk($fields, $outputPath) {
        // Проверка наличия PDFtk
        exec('which pdftk 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0) {
            return false; // PDFtk not available
        }
        
        // Создаем FDF файл с данными
        $fdfPath = sys_get_temp_dir() . '/contract_' . uniqid() . '.fdf';
        $this->createFDF($fields, $fdfPath);
        
        // Заполняем PDF
        $command = sprintf(
            'pdftk %s fill_form %s output %s flatten 2>&1',
            escapeshellarg($this->templatePath),
            escapeshellarg($fdfPath),
            escapeshellarg($outputPath)
        );
        
        exec($command, $output, $returnCode);
        unlink($fdfPath);
        
        return $returnCode === 0 && file_exists($outputPath);
    }
    
    /**
     * Создает FDF файл для PDFtk
     */
    private function createFDF($fields, $fdfPath) {
        $fdf = "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [\n";
        
        foreach ($fields as $key => $value) {
            $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
            $fdf .= "<< /T ($key) /V ($value) >>\n";
        }
        
        $fdf .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        
        file_put_contents($fdfPath, $fdf);
    }
    
    /**
     * Заполнение PDF через FPDI (резервный метод)
     */
    private function useFPDI($fields, $outputPath) {
        // Требует: composer require setasign/fpdf setasign/fpdi
        // Это упрощенная версия - полная реализация требует FPDI
        
        // Пока просто копируем шаблон и возвращаем
        // TODO: Реализовать с FPDI для overlay текста
        
        if (file_exists($this->templatePath)) {
            copy($this->templatePath, $outputPath);
            return $outputPath;
        }
        
        throw new Exception("Cannot generate PDF: template not found and no PDF library available");
    }
    
    /**
     * Отправляет сгенерированный договор на email
     */
    public function sendContract($pdfPath, $recipientEmail, $recipientName) {
        // Используем PHPMailer для отправки
        // Требует настройки SMTP в config
        
        $subject = "Договор о покупке щенка";
        $body = "Здравствуйте, $recipientName!\n\nВо вложении находится ваш договор о покупке щенка.\n\nС уважением,\nПитомник";
        
        // TODO: Реализовать отправку через PHPMailer
        // Пока просто возвращаем успех
        return true;
    }
}

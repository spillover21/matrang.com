<?php
/**
 * Сервис для работы с договорами через Documenso
 * Полный цикл: заполнение PDF → загрузка → отправка на подпись
 */

require_once __DIR__ . '/DocumensoBridgeClient.php';

class ContractService {
    private $bridge;
    private $templatePath;
    
    public function __construct() {
        $this->bridge = new DocumensoBridgeClient();
        $this->templatePath = __DIR__ . '/../templates/contract_template.pdf';
    }
    
    /**
     * Создать договор и отправить на подпись
     * 
     * @param array $contractData Данные из формы ContractManager
     * @return array Результат с envelope_id и signing_url
     */
    public function createAndSendContract($contractData) {
        // 1. Заполняем PDF шаблон данными
        $filledPdf = $this->fillPdfTemplate($contractData);
        
        // 2. Загружаем в Documenso и создаем envelope
        $result = $this->bridge->createEnvelope(
            $filledPdf,
            "Договор #{$contractData['contractNumber']} от {$contractData['contractDate']}",
            $contractData['buyerEmail'],
            $contractData['buyerName']
        );
        
        // 3. Удаляем временный файл
        if (file_exists($filledPdf)) {
            unlink($filledPdf);
        }
        
        return $result;
    }
    
    /**
     * Заполнить PDF шаблон данными из формы
     * 
     * @param array $data Данные контракта
     * @return string Путь к заполненному PDF
     */
    private function fillPdfTemplate($data) {
        // Временный файл для заполненного PDF
        $outputPdf = sys_get_temp_dir() . '/contract_' . time() . '.pdf';
        
        // Создаем FDF файл с данными
        $fdfData = $this->generateFdfData($data);
        $fdfFile = sys_get_temp_dir() . '/data_' . time() . '.fdf';
        file_put_contents($fdfFile, $fdfData);
        
        // Используем pdftk для заполнения
        $cmd = sprintf(
            'pdftk %s fill_form %s output %s flatten 2>&1',
            escapeshellarg($this->templatePath),
            escapeshellarg($fdfFile),
            escapeshellarg($outputPdf)
        );
        
        exec($cmd, $output, $returnCode);
        
        // Удаляем FDF файл
        if (file_exists($fdfFile)) {
            unlink($fdfFile);
        }
        
        if ($returnCode !== 0) {
            throw new Exception('PDF fill failed: ' . implode("\n", $output));
        }
        
        if (!file_exists($outputPdf)) {
            throw new Exception('PDF output file not created');
        }
        
        return $outputPdf;
    }
    
    /**
     * Генерировать FDF данные для PDFtk
     */
    private function generateFdfData($data) {
        $fields = [];
        
        // Данные питомника
        $fields[] = sprintf('<< /T (kennelName) /V (%s) >>', $this->escapeFdf($data['kennelName'] ?? ''));
        $fields[] = sprintf('<< /T (kennelOwner) /V (%s) >>', $this->escapeFdf($data['kennelOwner'] ?? ''));
        $fields[] = sprintf('<< /T (kennelPassportSeries) /V (%s) >>', $this->escapeFdf($data['kennelPassportSeries'] ?? ''));
        $fields[] = sprintf('<< /T (kennelPassportNumber) /V (%s) >>', $this->escapeFdf($data['kennelPassportNumber'] ?? ''));
        
        // Данные покупателя
        $fields[] = sprintf('<< /T (buyerName) /V (%s) >>', $this->escapeFdf($data['buyerName'] ?? ''));
        $fields[] = sprintf('<< /T (buyerPassportSeries) /V (%s) >>', $this->escapeFdf($data['buyerPassportSeries'] ?? ''));
        $fields[] = sprintf('<< /T (buyerPassportNumber) /V (%s) >>', $this->escapeFdf($data['buyerPassportNumber'] ?? ''));
        
        // Данные щенка
        $fields[] = sprintf('<< /T (dogName) /V (%s) >>', $this->escapeFdf($data['dogName'] ?? ''));
        $fields[] = sprintf('<< /T (dogBreed) /V (%s) >>', $this->escapeFdf($data['dogBreed'] ?? ''));
        $fields[] = sprintf('<< /T (dogBirthDate) /V (%s) >>', $this->escapeFdf($data['dogBirthDate'] ?? ''));
        $fields[] = sprintf('<< /T (dogGender) /V (%s) >>', $this->escapeFdf($data['dogGender'] ?? ''));
        $fields[] = sprintf('<< /T (dogColor) /V (%s) >>', $this->escapeFdf($data['dogColor'] ?? ''));
        $fields[] = sprintf('<< /T (dogChipNumber) /V (%s) >>', $this->escapeFdf($data['dogChipNumber'] ?? ''));
        
        // Финансовые условия
        $fields[] = sprintf('<< /T (price) /V (%s) >>', $this->escapeFdf($data['price'] ?? ''));
        $fields[] = sprintf('<< /T (depositAmount) /V (%s) >>', $this->escapeFdf($data['depositAmount'] ?? ''));
        $fields[] = sprintf('<< /T (contractDate) /V (%s) >>', $this->escapeFdf($data['contractDate'] ?? ''));
        $fields[] = sprintf('<< /T (contractPlace) /V (%s) >>', $this->escapeFdf($data['contractPlace'] ?? ''));
        
        // Добавьте остальные 47 полей по аналогии...
        
        $fdf = "%FDF-1.2\n";
        $fdf .= "1 0 obj\n<< /FDF << /Fields [\n";
        $fdf .= implode("\n", $fields);
        $fdf .= "\n] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        
        return $fdf;
    }
    
    /**
     * Экранировать спецсимволы для FDF
     */
    private function escapeFdf($text) {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return $text;
    }
    
    /**
     * Получить статус подписания договора
     */
    public function getContractStatus($envelopeId) {
        $envelope = $this->bridge->getEnvelope($envelopeId);
        $recipients = $this->bridge->getRecipients($envelopeId);
        
        return [
            'envelope' => $envelope['envelope'],
            'recipients' => $recipients['recipients']
        ];
    }
    
    /**
     * Получить ссылку для подписания
     */
    public function getSigningLink($envelopeId, $recipientEmail) {
        return $this->bridge->getSigningUrl($envelopeId, $recipientEmail);
    }
}

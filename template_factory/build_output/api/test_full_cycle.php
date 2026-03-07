<?php
/**
 * Тестовый endpoint для полного цикла создания договора
 */

require_once __DIR__ . '/PdfContractGenerator.php';
require_once __DIR__ . '/DocumensoDbService.php';

header('Content-Type: application/json');

try {
    // Тестовые данные договора
    $contractData = [
        'contractNumber' => 'TEST-' . time(),
        'contractDate' => date('d.m.Y'),
        'contractPlace' => 'Москва',
        
        'kennelName' => 'Тестовый питомник',
        'kennelOwner' => 'Иванов Иван Иванович',
        'kennelEmail' => 'kennel@test.com',
        
        'buyerName' => 'Петров Петр Петрович',
        'buyerEmail' => 'buyer@test.com',
        'buyerPhone' => '+7 999 123-45-67',
        
        'dogName' => 'Рекс',
        'dogBreed' => 'Немецкая овчарка',
        
        'price' => '50000'
    ];
    
    // 1. Генерируем PDF
    $pdfGenerator = new PdfContractGenerator();
    $pdfPath = $pdfGenerator->generateContract($contractData);
    
    echo json_encode([
        'success' => true,
        'step' => 'PDF Generated',
        'pdfPath' => $pdfPath
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // 2. Загружаем в Documenso через БД
    // (требует открытый порт PostgreSQL 5432)
    
    /* РАСКОММЕНТИРУЙ ПОСЛЕ ОТКРЫТИЯ ПОРТА:
    $documensoDb = new DocumensoDbService();
    
    $result = $documensoDb->createDocumentFromPdf(
        $pdfPath,
        'Договор ' . $contractData['contractNumber'],
        [
            'email' => $contractData['buyerEmail'],
            'name' => $contractData['buyerName']
        ]
    );
    
    // 3. Отправляем на подпись
    $documensoDb->sendDocument($result['documentId']);
    
    echo json_encode([
        'success' => true,
        'documentId' => $result['documentId'],
        'signingUrl' => $result['signingUrl'],
        'message' => 'Документ создан и отправлен на подпись'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    */
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

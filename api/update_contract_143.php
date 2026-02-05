<?php
/**
 * Вручную обновляет договор DOG-2026-0010 (document 143)
 */

require_once __DIR__ . '/DocumensoService.php';

$contractsFile = __DIR__ . '/../data/contracts.json';
$data = json_decode(file_get_contents($contractsFile), true);

// Ищем DOG-2026-0010
$found = false;
foreach ($data['contracts'] as &$contract) {
    if ($contract['contractNumber'] === 'DOG-2026-0010') {
        echo "Found contract: " . $contract['id'] . "\n";
        echo "Current status: " . $contract['status'] . "\n";
        
        // Скачиваем PDF для document 143
        try {
            $service = new DocumensoService();
            $uploadDir = __DIR__ . '/../uploads/contracts/';
            $filename = "contract_unknown_143.pdf";
            $savePath = $uploadDir . $filename;
            
            echo "Downloading PDF to: $savePath\n";
            $service->downloadDocument(143, $savePath);
            
            // Обновляем статус
            $contract['status'] = 'signed';
            $contract['signedAt'] = date('c');
            $contract['signedDocumentUrl'] = '/uploads/contracts/' . $filename;
            
            echo "Status updated to: signed\n";
            echo "PDF URL: /uploads/contracts/$filename\n";
            
            $found = true;
            break;
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

if (!$found) {
    echo "Contract DOG-2026-0010 not found!\n";
    exit(1);
}

// Сохраняем
file_put_contents($contractsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Database updated successfully!\n";

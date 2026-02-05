<?php
/**
 * Автоматическая синхронизация договоров с Documenso
 * Скачивает PDF и обновляет статусы
 */

$silent = isset($_GET['silent']) || (php_sapi_name() === 'cli');

require_once __DIR__ . '/DocumensoService.php';
$config = require __DIR__ . '/documenso_config.php';

if (!$silent) {
    header('Content-Type: application/json; charset=utf-8');
}

function updateContractWithPDF($buyerEmail, $docId, $completedAt) {
    $contractsFile = __DIR__ . '/../data/contracts.json';
    $contracts = json_decode(file_get_contents($contractsFile), true);
    
    foreach ($contracts as &$contract) {
        if (strtolower($contract['buyerEmail']) === strtolower($buyerEmail) && 
            $contract['status'] === 'sent') {
            
            $contract['status'] = 'signed';
            $contract['signedAt'] = $completedAt;
            
            // Скачиваем PDF с Documenso
            $pdfUrl = "http://localhost:9000/api/v1/documents/{$docId}/download";
            $ch = curl_init($pdfUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer q13EXlPOGzS0SKGx9aD+QGBz6HoIo5nq'
            ]);
            $pdfContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $pdfContent) {
                $uploadsDir = __DIR__ . '/../uploads/contracts';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                
                $fileName = "contract_{$contract['contractNumber']}_{$docId}.pdf";
                $filePath = $uploadsDir . '/' . $fileName;
                
                if (file_put_contents($filePath, $pdfContent)) {
                    $contract['signedDocumentUrl'] = "/uploads/contracts/" . $fileName;
                }
            }
            
            file_put_contents($contractsFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            return [
                'success' => true,
                'message' => 'Contract updated with PDF',
                'contractNumber' => $contract['contractNumber'],
                'pdfSaved' => !empty($contract['signedDocumentUrl'])
            ];
        }
    }
    
    return ['success' => false, 'message' => 'Contract not found'];
}

try {
    $service = new DocumensoService($config['documensoUrl'], $config['apiToken']);
    
    $page = 1;
    $perPage = 100;
    $allDocuments = [];
    
    do {
        $response = $service->getDocuments($page, $perPage);
        $documents = $response['data'] ?? [];
        $allDocuments = array_merge($allDocuments, $documents);
        $page++;
    } while (!empty($documents) && count($documents) === $perPage);
    
    $updatedCount = 0;
    $details = [];
    
    foreach ($allDocuments as $doc) {
        if ($doc['status'] !== 'COMPLETED') {
            continue;
        }
        
        $recipients = $doc['recipients'] ?? [];
        $buyerEmail = null;
        
        foreach ($recipients as $recipient) {
            if ($recipient['email'] !== 'noreply@matrang.com') {
                $buyerEmail = $recipient['email'];
                break;
            }
        }
        
        if ($buyerEmail) {
            $result = updateContractWithPDF(
                $buyerEmail, 
                $doc['id'], 
                $doc['completedAt']
            );
            
            if ($result['success']) {
                $updatedCount++;
                $details[] = [
                    'contractNumber' => $result['contractNumber'],
                    'pdfSaved' => $result['pdfSaved']
                ];
            }
        }
    }
    
    $response = [
        'success' => true,
        'updated' => $updatedCount,
        'processed' => count($allDocuments)
    ];
    
    if (!$silent) {
        $response['details'] = array_slice($details, 0, 5);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

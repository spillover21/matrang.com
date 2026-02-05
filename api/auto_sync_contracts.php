<?php
/**
 * Автоматическая синхронизация статусов договоров из Documenso
 * Вызывайте этот скрипт по расписанию (каждые 5-10 минут)
 */

require_once __DIR__ . '/DocumensoService.php';
$config = require __DIR__ . '/documenso_config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $documenso = new DocumensoService();
    $documents = $documenso->listDocuments(1, 100);
    
    $updated = 0;
    $errors = [];
    
    foreach ($documents as $doc) {
        if ($doc['status'] === 'COMPLETED') {
            try {
                // Получаем полный документ
                $fullDoc = $documenso->getDocument($doc['id']);
                
                // Извлекаем envelopeId
                $envelopeId = null;
                if (isset($fullDoc['fields']) && is_array($fullDoc['fields'])) {
                    foreach ($fullDoc['fields'] as $field) {
                        if (isset($field['envelopeId'])) {
                            $envelopeId = $field['envelopeId'];
                            break;
                        }
                    }
                }
                
                if ($envelopeId) {
                    // Проверяем, нужно ли обновлять
                    $contractsFile = __DIR__ . '/../data/contracts.json';
                    if (file_exists($contractsFile)) {
                        $data = json_decode(file_get_contents($contractsFile), true);
                        $contracts = $data['contracts'] ?? [];
                        
                        foreach ($contracts as $contract) {
                            if (($contract['adobeSignAgreementId'] ?? '') === $envelopeId) {
                                // Проверяем, нужно ли обновление
                                if ($contract['status'] !== 'signed' || empty($contract['signedDocumentUrl'])) {
                                    // Скачиваем PDF
                                    $uploadDir = __DIR__ . '/../uploads/contracts/';
                                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                                    
                                    $filename = "contract_{$contract['id']}_{$doc['id']}.pdf";
                                    $savePath = $uploadDir . $filename;
                                    
                                    if (!file_exists($savePath)) {
                                        $documenso->downloadDocument($doc['id'], $savePath);
                                    }
                                    
                                    // Обновляем статус
                                    updateContractStatusByEnvelopeId($envelopeId, 'signed', '/uploads/contracts/' . $filename);
                                    $updated++;
                                }
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = [
                    'documentId' => $doc['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'totalDocuments' => count($documents),
        'updatedContracts' => $updated,
        'errors' => $errors
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Вспомогательная функция
function updateContractStatusByEnvelopeId($envelopeId, $status, $signedDocumentUrl = null) {
    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    if (!file_exists($contractsFile)) {
        return false;
    }
    
    $data = json_decode(file_get_contents($contractsFile), true);
    $contracts = $data['contracts'] ?? [];
    $templates = $data['templates'] ?? [];
    
    $updated = false;
    foreach ($contracts as &$contract) {
        if (($contract['adobeSignAgreementId'] ?? '') === $envelopeId) {
            $contract['status'] = $status;
            $contract['signedAt'] = date('c');
            
            if ($signedDocumentUrl) {
                $contract['signedDocumentUrl'] = $signedDocumentUrl;
            }
            
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        $saveData = ['contracts' => $contracts, 'templates' => $templates];
        file_put_contents($contractsFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    return $updated;
}

<?php
// Тестовый эндпоинт для ручного обновления статусов договоров из Documenso

require_once __DIR__ . '/DocumensoService.php';
$config = require __DIR__ . '/documenso_config.php';

header('Content-Type: application/json; charset=utf-8');

// Функция обновления статуса по email
function updateContractStatusByEmail($buyerEmail, $status, $signedDocumentUrl = null) {
    $contractsFile = __DIR__ . '/../data/contracts.json';
    
    if (!file_exists($contractsFile)) {
        return ['success' => false, 'error' => 'Contracts file not found'];
    }
    
    $jsonData = file_get_contents($contractsFile);
    $allData = json_decode($jsonData, true);
    
    if (!isset($allData['contracts'])) {
        return ['success' => false, 'error' => 'Invalid contracts file structure'];
    }
    
    $found = false;
    foreach ($allData['contracts'] as &$contract) {
        // Ищем по email покупателя
        if (isset($contract['data']['buyerEmail']) && $contract['data']['buyerEmail'] === $buyerEmail) {
            $contract['status'] = $status;
            $contract['signedAt'] = date('Y-m-d\TH:i:sP');
            
            if ($signedDocumentUrl) {
                $contract['signedDocumentUrl'] = $signedDocumentUrl;
            }
            
            $found = true;
            break; // Обновляем только первый найденный договор
        }
    }
    
    if (!$found) {
        return ['success' => false, 'error' => 'Contract not found for email: ' . $buyerEmail];
    }
    
    // Сохраняем обновленные данные
    file_put_contents($contractsFile, json_encode($allData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return ['success' => true, 'message' => 'Contract status updated to ' . $status];
}

// Получаем все документы из Documenso
$documenso = new DocumensoService($config);
try {
    $documents = $documenso->listDocuments();
} catch (Exception $e) {
    die(json_encode(['error' => 'Failed to fetch documents from Documenso: ' . $e->getMessage()]));
}

$results = [];

// Для каждого COMPLETED документа обновляем статус в базе
foreach ($documents as $doc) {
    if ($doc['status'] === 'COMPLETED') {
        // Получаем email получателя
        $buyerEmail = null;
        if (isset($doc['recipients'])) {
            foreach ($doc['recipients'] as $recipient) {
                if (!empty($recipient['email'])) {
                    $buyerEmail = $recipient['email'];
                    break;
                }
            }
        }
        
        if ($buyerEmail) {
            $result = updateContractStatusByEmail($buyerEmail, 'signed');
            $results[] = [
                'docId' => $doc['id'],
                'email' => $buyerEmail,
                'title' => $doc['title'] ?? 'N/A',
                'result' => $result
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'totalDocuments' => count($documents),
    'updatedContracts' => count($results),
    'details' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

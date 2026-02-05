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
$completedDocs = [];
$fullDocumentSample = null;

// Для каждого COMPLETED документа обновляем статус в базе
foreach ($documents as $doc) {
    if ($doc['status'] === 'COMPLETED') {
        // Получаем полную информацию о документе
        try {
            $fullDoc = $documenso->getDocument($doc['id']);
            
            // Сохраняем первый полный документ для отладки
            if ($fullDocumentSample === null) {
                $fullDocumentSample = $fullDoc;
            }
            
            // Получаем email получателя из полного документа
            $buyerEmail = null;
            if (isset($fullDoc['recipients'])) {
                foreach ($fullDoc['recipients'] as $recipient) {
                    if (!empty($recipient['email'])) {
                        $buyerEmail = $recipient['email'];
                        break;
                    }
                }
            }
            
            $completedDocs[] = [
                'id' => $doc['id'],
                'title' => $doc['title'] ?? 'N/A',
                'email' => $buyerEmail,
                'completedAt' => $doc['completedAt'] ?? null
            ];
            
            if ($buyerEmail) {
                $result = updateContractStatusByEmail($buyerEmail, 'signed');
                $results[] = [
                    'docId' => $doc['id'],
                    'email' => $buyerEmail,
                    'title' => $doc['title'] ?? 'N/A',
                    'result' => $result
                ];
            }
        } catch (Exception $e) {
            $completedDocs[] = [
                'id' => $doc['id'],
                'error' => $e->getMessage()
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'totalDocuments' => count($documents),
    'completedDocuments' => count($completedDocs),
    'completedDetails' => array_slice($completedDocs, 0, 5), // Только первые 5
    'updatedContracts' => count($results),
    'updateDetails' => array_slice($results, 0, 5), // Только первые 5
    'fullDocumentSample' => $fullDocumentSample // Полный документ для отладки
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

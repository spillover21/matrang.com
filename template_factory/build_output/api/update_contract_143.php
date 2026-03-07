<?php
/**
 * Вручную обновляет договор DOG-2026-0010 (document 143)
 * БЕЗ скачивания PDF (так как URL истек)
 */

$contractsFile = __DIR__ . '/../data/contracts.json';
$data = json_decode(file_get_contents($contractsFile), true);

// Ищем DOG-2026-0010
$found = false;
foreach ($data['contracts'] as &$contract) {
    if ($contract['contractNumber'] === 'DOG-2026-0010') {
        echo "Found contract: " . $contract['id'] . "\n";
        echo "Current status: " . $contract['status'] . "\n";
        
        // Обновляем статус БЕЗ PDF (пользователь уже получил PDF в письме)
        $contract['status'] = 'signed';
        $contract['signedAt'] = date('c');
        // Не добавляем signedDocumentUrl, так как PDF получен только в письме
        
        echo "Status updated to: signed\n";
        echo "Note: PDF was delivered via email but not saved on server\n";
        
        $found = true;
        break;
    }
}

if (!$found) {
    echo "Contract DOG-2026-0010 not found!\n";
    exit(1);
}

// Сохраняем
file_put_contents($contractsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Database updated successfully!\n";
echo "Please refresh the admin panel to see the changes.\n";

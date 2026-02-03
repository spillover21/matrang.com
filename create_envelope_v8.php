<?php
header('Content-Type: application/json');
date_default_timezone_set('UTC');

// API Key проверка
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'matrang_secret_key_2026') {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON');
    }
    
    // Генерация ID
    function generateCuid() {
        $timestamp = (string)(microtime(true) * 1000);
        $random = bin2hex(random_bytes(8));
        return 'cml' . substr($timestamp, -6) . $random;
    }
    
    function generateEnvelopeId() {
        return 'envelope_' . bin2hex(random_bytes(12));
    }
    
    function generateRecipientToken() {
        $part1 = base64_encode(random_bytes(6));
        $part2 = base64_encode(random_bytes(10));
        return rtrim(strtr($part1, '+/', '-_'), '=') . '-' . rtrim(strtr($part2, '+/', '-_'), '=');
    }
    
    $envelopeId = generateEnvelopeId();
    $documentDataId = generateCuid();
    $envelopeItemId = 'envelope_item_' . bin2hex(random_bytes(12));
    $documentMetaId = generateCuid();
    $recipientToken = generateRecipientToken();
    $now = date('Y-m-d H:i:s');
    
    // PostgreSQL подключение
    $pgConn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');
    if (!$pgConn) {
        throw new Exception('PostgreSQL connection failed');
    }
    
    // Генерация secondaryId
    $result = pg_query($pgConn, 'SELECT MAX(CAST(SUBSTRING("secondaryId" FROM \'document_([0-9]+)\') AS INTEGER)) AS max_id FROM "Envelope"');
    if (!$result) {
        throw new Exception('secondaryId query failed: ' . pg_last_error($pgConn));
    }
    $row = pg_fetch_assoc($result);
    $nextId = ($row['max_id'] ?? 0) + 1;
    $secondaryId = "document_{$nextId}";
    
    // Создание FDF файла
    $fdfPath = sys_get_temp_dir() . '/data_' . bin2hex(random_bytes(6)) . '.fdf';
    $fdfContent = "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [\n";
    foreach ($data as $key => $value) {
        $fdfContent .= "<< /T ({$key}) /V (" . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value) . ") >>\n";
    }
    $fdfContent .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    file_put_contents($fdfPath, $fdfContent);
    
    // PDFtk заполнение
    $filledPdfPath = sys_get_temp_dir() . '/filled_' . bin2hex(random_bytes(6)) . '.pdf';
    exec("pdftk /var/www/documenso-bridge/template.pdf fill_form {$fdfPath} output {$filledPdfPath} flatten 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0 || !file_exists($filledPdfPath)) {
        throw new Exception('PDFtk failed: ' . implode("\n", $output));
    }
    
    // Загрузка в MinIO через mc
    $uploadFilename = 'upload_' . bin2hex(random_bytes(6)) . '.pdf';
    $containerPath = "/tmp/{$uploadFilename}";
    $s3Path = "documents/{$envelopeId}/contract.pdf";
    
    exec("docker cp {$filledPdfPath} documenso-minio:{$containerPath} 2>&1", $output1, $ret1);
    if ($ret1 !== 0) {
        throw new Exception('docker cp failed: ' . implode("\n", $output1));
    }
    
    exec("docker exec documenso-minio mc alias set local http://localhost:9000 minioadmin minioadmin123 2>&1", $output2, $ret2);
    if ($ret2 !== 0) {
        throw new Exception('mc alias failed: ' . implode("\n", $output2));
    }
    
    exec("docker exec documenso-minio mc cp {$containerPath} local/documenso/{$s3Path} 2>&1", $output3, $ret3);
    if ($ret3 !== 0) {
        throw new Exception('mc cp failed: ' . implode("\n", $output3));
    }
    
    // DocumentMeta INSERT
    $result = pg_query_params($pgConn, '
        INSERT INTO "DocumentMeta" (id, subject, message, timezone, "dateFormat", "redirectUrl", "typedSignatureEnabled")
        VALUES ($1, NULL, NULL, NULL, NULL, NULL, true)
    ', [$documentMetaId]);
    
    if (!$result) {
        throw new Exception('DocumentMeta insert failed: ' . pg_last_error($pgConn));
    }
    
    // Envelope INSERT
    $result = pg_query_params($pgConn, '
        INSERT INTO "Envelope" (
            id, "secondaryId", type, title, status, source, 
            "internalVersion", "useLegacyFieldInsertion", "authOptions", 
            visibility, "templateType", "userId", "teamId", "documentMetaId", 
            "createdAt", "updatedAt"
        )
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16)
    ', [
        $envelopeId,
        $secondaryId,
        'DOCUMENT',
        'Contract.pdf',
        'DRAFT',
        'DOCUMENT',
        2,
        'false',
        json_encode(['globalAccessAuth' => [], 'globalActionAuth' => []]),
        'EVERYONE',
        'PRIVATE',
        3,
        3,
        $documentMetaId,
        $now,
        $now
    ]);
    
    if (!$result) {
        throw new Exception('Envelope insert failed: ' . pg_last_error($pgConn));
    }
    
    // DocumentData INSERT - ИСПРАВЛЕНО: NULL вместо JSON
    $result = pg_query_params($pgConn, '
        INSERT INTO "DocumentData" (id, type, data, "initialData")
        VALUES ($1, $2, NULL, NULL)
    ', [
        $documentDataId,
        'S3_PATH'
    ]);
    
    if (!$result) {
        throw new Exception('DocumentData insert failed: ' . pg_last_error($pgConn));
    }
    
    // EnvelopeItem INSERT
    $result = pg_query_params($pgConn, '
        INSERT INTO "EnvelopeItem" (id, title, "documentDataId", "envelopeId", "order")
        VALUES ($1, $2, $3, $4, $5)
    ', [
        $envelopeItemId,
        'Contract.pdf',
        $documentDataId,
        $envelopeId,
        1
    ]);
    
    if (!$result) {
        throw new Exception('EnvelopeItem insert failed: ' . pg_last_error($pgConn));
    }
    
    // Recipient INSERT
    $result = pg_query_params($pgConn, '
        INSERT INTO "Recipient" (
            id, email, name, token, role, "readStatus", "signingStatus", 
            "sendStatus", "signingOrder", "authOptions", "envelopeId"
        )
        SELECT 
            COALESCE(MAX(id), 0) + 1, $1, $2, $3, $4, $5, $6, $7, $8, $9, $10
        FROM "Recipient"
    ', [
        $data['buyer_email'] ?? 'buyer@test.com',
        $data['buyer_name'] ?? 'Test Buyer',
        $recipientToken,
        'SIGNER',
        'NOT_OPENED',
        'NOT_SIGNED',
        'NOT_SENT',
        1,
        json_encode(['accessAuth' => [], 'actionAuth' => []]),
        $envelopeId
    ]);

    if (!$result) {
        throw new Exception('Recipient insert failed: ' . pg_last_error($pgConn));
    }

    pg_close($pgConn);

    // Очищаем временные файлы
    @unlink($fdfPath);
    @unlink($filledPdfPath);

    echo json_encode([
        'success' => true,
        'envelope_id' => $envelopeId,
        'secondary_id' => $secondaryId,
        'document_url' => "http://72.62.114.139:9000/documents/{$envelopeId}",
        's3_path' => $s3Path
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

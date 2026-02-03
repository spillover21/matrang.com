<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Проверка API ключа Bridge
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'matrang_secret_key_2026') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Получаем данные
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid JSON',
        'received_input' => $input,
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

try {
    // Путь к шаблону
    $templatePath = '/var/www/documenso-bridge/templates/contract_template.pdf';
    if (!file_exists($templatePath)) {
        throw new Exception("PDF template not found at {$templatePath}");
    }

    // Генерируем уникальные ID
    $uniqueId = bin2hex(random_bytes(10));
    
    // Генерируем ID в формате cuid (совместимо с Documenso)
    function generateCuid() {
        $timestamp = (string)(microtime(true) * 1000);
        $random = bin2hex(random_bytes(8));
        return 'cml' . substr($timestamp, -6) . $random;
    }
    
    function generateEnvelopeId() {
        return 'envelope_' . bin2hex(random_bytes(12));
    }
    
    function generateRecipientToken() {
        // Генерируем токен формата как в Documenso: n6OWj4vW-pdsifjRC_wE5
        $part1 = base64_encode(random_bytes(6));
        $part2 = base64_encode(random_bytes(10));
        return rtrim(strtr($part1, '+/', '-_'), '=') . '-' . rtrim(strtr($part2, '+/', '-_'), '=');
    }
    
    function generateQrToken() {
        return 'qr_' . bin2hex(random_bytes(10));
    }

    // Создаем FDF файл для заполнения PDF
    $fdfPath = "/tmp/contract_data_{$uniqueId}.fdf";
    $fdfContent = "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [\n";

    foreach ($data as $key => $value) {
        $escapedKey = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $key);
        $escapedValue = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
        $fdfContent .= "<< /T ({$escapedKey}) /V ({$escapedValue}) >>\n";
    }

    $fdfContent .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    file_put_contents($fdfPath, $fdfContent);

    // Заполняем PDF через PDFtk
    $filledPdfPath = "/tmp/contract_filled_{$uniqueId}.pdf";
    $pdftklCmd = sprintf(
        'pdftk %s fill_form %s output %s flatten 2>&1',
        escapeshellarg($templatePath),
        escapeshellarg($fdfPath),
        escapeshellarg($filledPdfPath)
    );

    exec($pdftklCmd, $pdftkOutput, $pdftkReturn);

    if ($pdftkReturn !== 0 || !file_exists($filledPdfPath)) {
        throw new Exception("PDFtk failed: " . implode("\n", $pdftkOutput));
    }

    // Генерируем ID
    $envelopeId = generateEnvelopeId();
    $documentDataId = generateCuid();
    $envelopeItemId = 'envelope_item_' . bin2hex(random_bytes(12));
    $documentMetaId = generateCuid();
    
    // PostgreSQL connection
    $pgConfig = "host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123";
    $pgConn = pg_connect($pgConfig);
    
    if (!$pgConn) {
        throw new Exception("PostgreSQL connection failed");
    }
    
    // Получаем максимальный secondaryId (требуется для NOT NULL constraint)
    $result = pg_query($pgConn, "SELECT MAX(CAST(SUBSTRING(\"secondaryId\" FROM 'document_([0-9]+)') AS INTEGER)) AS max_id FROM \"Envelope\" WHERE \"userId\" = 3");
    $row = pg_fetch_assoc($result);
    $nextId = ($row['max_id'] ?? 0) + 1;
    $secondaryId = "document_{$nextId}";
    
    // Путь в S3
    $s3Path = "documents/{$envelopeId}/contract.pdf";
    
    // Загружаем PDF в MinIO через S3 API
    // Сначала копируем файл во временную директорию MinIO контейнера
    $tempPath = "/tmp/upload_{$uniqueId}.pdf";
    $copyCmd = sprintf(
        'docker cp %s documenso-minio:%s',
        escapeshellarg($filledPdfPath),
        escapeshellarg($tempPath)
    );
    exec($copyCmd, $copyOutput, $copyReturn);
    
    if ($copyReturn !== 0) {
        throw new Exception("Copy to MinIO container failed: " . implode("\n", $copyOutput));
    }
    
    // Настраиваем MinIO client алиас
    $aliasCmd = 'docker exec documenso-minio mc alias set local http://localhost:9000 minioadmin minioadmin123 2>&1';
    exec($aliasCmd, $aliasOutput, $aliasReturn);
    
    // Загружаем файл через mc (MinIO Client) в S3
    $uploadCmd = sprintf(
        'docker exec documenso-minio mc cp %s local/documenso/documents/%s/contract.pdf 2>&1',
        escapeshellarg($tempPath),
        escapeshellarg($envelopeId)
    );
    exec($uploadCmd, $uploadOutput, $uploadReturn);
    
    if ($uploadReturn !== 0) {
        throw new Exception("MinIO upload failed: " . implode("\n", $uploadOutput));
    }
    
    // Удаляем временный файл из контейнера
    exec("docker exec documenso-minio rm -f {$tempPath}", $rmOutput, $rmReturn);
    
    // Вставляем данные в PostgreSQL
    $now = date('Y-m-d H:i:s.v');
    $title = 'Contract.pdf';
    
    // 1. DocumentMeta
    $result = pg_query_params($pgConn, '
        INSERT INTO "DocumentMeta" (id, subject, message, timezone, "dateFormat", "redirectUrl", "typedSignatureEnabled")
        VALUES ($1, NULL, NULL, NULL, NULL, NULL, true)
    ', [$documentMetaId]);
    
    if (!$result) {
        throw new Exception("DocumentMeta insert failed: " . pg_last_error($pgConn));
    }
    
    // 2. Envelope (secondaryId required - NOT NULL constraint!)
    $result = pg_query_params($pgConn, '
        INSERT INTO "Envelope" (id, "secondaryId", type, title, status, source, "internalVersion", "useLegacyFieldInsertion", "authOptions", visibility, "templateType", "userId", "teamId", "documentMetaId", "createdAt", "updatedAt")
        VALUES ($1, $2, $3, $4, $5, $6, $7, false, $8, $9, $10, $11, $12, $13, $14, $15)
    ', [
        $envelopeId,
        $secondaryId,
        'DOCUMENT',
        $title,
        'DRAFT',
        'DOCUMENT',
        2,
        '{"globalAccessAuth": [], "globalActionAuth": []}',
        'EVERYONE',
        'PRIVATE',
        3,
        3,
        $documentMetaId,
        $now,
        $now
    ]);
    
    if (!$result) {
        throw new Exception("Envelope insert failed: " . pg_last_error($pgConn));
    }
    
    // 3. DocumentData
    $result = pg_query_params($pgConn, '
        INSERT INTO "DocumentData" (id, type, data, "initialData")
        VALUES ($1, $2, $3, $3)
    ', [
        $documentDataId,
        'S3_PATH',
        json_encode(['path' => $s3Path])
    ]);
    
    if (!$result) {
        throw new Exception("DocumentData insert failed: " . pg_last_error($pgConn));
    }
    
    // 4. EnvelopeItem
    $result = pg_query_params($pgConn, '
        INSERT INTO "EnvelopeItem" (id, title, "documentDataId", "envelopeId", "order")
        VALUES ($1, $2, $3, $4, $5)
    ', [
        $envelopeItemId,
        $title,
        $documentDataId,
        $envelopeId,
        1
    ]);
    
    if (!$result) {
        throw new Exception("EnvelopeItem insert failed: " . pg_last_error($pgConn));
    }
    
    // 5. Recipient (получатель для подписи)
    $recipientEmail = $data['buyerEmail'] ?? '';
    $recipientName = $data['buyerName'] ?? '';
    $recipientToken = generateRecipientToken();
    
    // Получаем следующий ID для Recipient
    $result = pg_query($pgConn, 'SELECT COALESCE(MAX(id), 0) as max_id FROM "Recipient"');
    $row = pg_fetch_assoc($result);
    $recipientId = ($row['max_id'] ?? 0) + 1;
    
    $result = pg_query_params($pgConn, '
        INSERT INTO "Recipient" (id, email, name, token, "readStatus", "signingStatus", "sendStatus", role, "authOptions", "signingOrder", "envelopeId")
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
    ', [
        $recipientId,
        $recipientEmail,
        $recipientName,
        $recipientToken,
        'NOT_OPENED',
        'NOT_SIGNED',
        'NOT_SENT',
        'SIGNER',
        '{"accessAuth": [], "actionAuth": []}',
        1,
        $envelopeId
    ]);
    
    if (!$result) {
        throw new Exception("Recipient insert failed: " . pg_last_error($pgConn));
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

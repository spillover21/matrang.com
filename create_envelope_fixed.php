<?php
header('Content-Type: application/json');
date_default_timezone_set('UTC');

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
        return rtrim(strtr($part1, '+/', '-_'), '=') . '-' . rtrim(strtr($part2,
 '+/', '-_'), '=');                                                                 }

    function getDocumensoBaseUrl() {
        $envPath = '/var/www/documenso/.env';
        $baseUrl = null;
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') === false || $line[0] === '#') {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));     
                $value = trim($value, '"');
                if ($key === 'NEXT_PUBLIC_WEBAPP_URL') {
                    $baseUrl = $value;
                    break;
                }
                if ($key === 'NEXTAUTH_URL' && !$baseUrl) {
                    $baseUrl = $value;
                }
            }
        }
        return $baseUrl ?: 'http://72.62.114.139:9000';
    }

    $envelopeId = generateEnvelopeId();
    $documentDataId = generateCuid();
    $envelopeItemId = 'envelope_item_' . bin2hex(random_bytes(12));
    $documentMetaId = generateCuid();
    $recipientToken = generateRecipientToken();
    $now = date('Y-m-d H:i:s');

    $pgConn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');                                                         if (!$pgConn) {
        throw new Exception('PostgreSQL connection failed');
    }

    // ╨У╨╡╨╜╨╡╤А╨░╤Ж╨╕╤П secondaryId
    $result = pg_query($pgConn, 'SELECT MAX(CAST(SUBSTRING("secondaryId" FROM \'
document_([0-9]+)\') AS INTEGER)) AS max_id FROM "Envelope"');                      if (!$result) {
        throw new Exception('secondaryId query failed: ' . pg_last_error($pgConn
));                                                                                 }
    $row = pg_fetch_assoc($result);
    $nextId = ($row['max_id'] ?? 0) + 1;
    $secondaryId = "document_{$nextId}";

    // ╨У╨╡╨╜╨╡╤А╨╕╤А╤Г╨╡╨╝ ╨╜╨╛╨╝╨╡╤А ╨┤╨╛╨│╨╛╨▓╨╛╤А╨░ ╨╡╤Б╨╗╨╕ ╨╡╨│╨╛ ╨╜╨╡╤В
    if (empty($data['contractNumber'])) {
        $data['contractNumber'] = 'MDOG-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);                                                                                 }

    // ╨б╨╛╨╖╨┤╨░╨╡╨╝ JSON ╤Д╨░╨╣╨╗ ╤Б ╨┤╨░╨╜╨╜╤Л╨╝╨╕ ╨┤╨╗╤П Python ╤Б╨║╤А╨╕╨┐╤В╨░
    $jsonPath = sys_get_temp_dir() . '/data_' . bin2hex(random_bytes(6)) . '.json';                                                                             
    // ╨Ы╨╛╨│╨╕╤А╤Г╨╡╨╝ ╨┤╨░╨╜╨╜╤Л╨╡ ╨┤╨╗╤П ╨╛╤В╨╗╨░╨┤╨║╨╕ (╤Б╨╛╤Е╤А╨░╨╜╤П╨╡╨╝ ╨║╨╛╨┐╨╕╤О)
    $debugPath = '/tmp/last_contract_data.json';
    file_put_contents($debugPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));                                                              
    $jsonWritten = file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));                                                
    if ($jsonWritten === false) {
        throw new Exception('Failed to write JSON data file');
    }

    // ╨Ч╨░╨┐╨╛╨╗╨╜╨╡╨╜╨╕╨╡ PDF ╤З╨╡╤А╨╡╨╖ Python (pypdf ╨┐╨╛╨┤╨┤╨╡╤А╨╢╨╕╨▓╨░╨╡╤В ╨║╨╕╤А╨╕╨╗╨╗╨╕╤Ж╤Г)
    $filledPdfPath = sys_get_temp_dir() . '/filled_' . bin2hex(random_bytes(6)) 
. '.pdf';                                                                           $pythonCmd = "python3 /var/www/documenso-bridge/fill_pdf.py /var/www/documenso-bridge/template.pdf {$filledPdfPath} {$jsonPath} 2>&1";                      
    exec($pythonCmd, $output, $returnCode);

    // ╨г╨┤╨░╨╗╤П╨╡╨╝ ╨▓╤А╨╡╨╝╨╡╨╜╨╜╤Л╨╣ JSON ╤Д╨░╨╣╨╗
    @unlink($jsonPath);

    if ($returnCode !== 0) {
        throw new Exception('Python script failed (exit code ' . $returnCode . '
): ' . implode("\n", $output));                                                     }

    if (!file_exists($filledPdfPath)) {
        throw new Exception('PDF file was not created. Python output: ' . implode("\n", $output));                                                                  }

    // ╨Ъ╨╛╨╜╨▓╨╡╤А╤В╨░╤Ж╨╕╤П ╨▓ BYTES_64 (base64 string)
    $pdfContent = file_get_contents($filledPdfPath);

    if ($pdfContent === false) {
        throw new Exception('Failed to read filled PDF file');
    }

    $pdfBase64 = base64_encode($pdfContent);

    // ╨г╨┤╨░╨╗╤П╨╡╨╝ ╨▓╤А╨╡╨╝╨╡╨╜╨╜╤Л╨╣ PDF ╤Д╨░╨╣╨╗
    @unlink($filledPdfPath);

    // DocumentMeta INSERT
    $result = pg_query_params($pgConn, '
        INSERT INTO "DocumentMeta" (id, subject, message, timezone, "dateFormat"
, "redirectUrl", "typedSignatureEnabled")                                               VALUES ($1, NULL, NULL, NULL, NULL, NULL, true)
    ', [$documentMetaId]);

    if (!$result) {
        throw new Exception('DocumentMeta insert failed: ' . pg_last_error($pgConn));                                                                               }

    // Envelope INSERT ╤Б ╨Т╨б╨Х╨Ь╨Ш ╨╛╨▒╤П╨╖╨░╤В╨╡╨╗╤М╨╜╤Л╨╝╨╕ ╨┐╨╛╨╗╤П╨╝╨╕
    $result = pg_query_params($pgConn, '
        INSERT INTO "Envelope" (
            id, "secondaryId", source, type, "internalVersion",
            title, status, "useLegacyFieldInsertion", visibility, "templateType"
,                                                                                           "publicTitle", "publicDescription", "userId", "teamId", "documentMetaId",                                                                                       "createdAt", "updatedAt"
        )
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15
, $16, $17)                                                                         ', [
        $envelopeId,             // id
        $secondaryId,            // secondaryId
        'DOCUMENT',              // source
        'DOCUMENT',              // type
        2,                       // internalVersion
        'Contract.pdf',          // title
        'PENDING',                 // status
        'false',                 // useLegacyFieldInsertion
        'EVERYONE',              // visibility
        'PRIVATE',               // templateType
        '',                      // publicTitle
        '',                      // publicDescription
        3,                       // userId
        3,                       // teamId
        $documentMetaId,         // documentMetaId
        $now,                    // createdAt
        $now                     // updatedAt
    ]);

    if (!$result) {
        throw new Exception('Envelope insert failed: ' . pg_last_error($pgConn));                                                                                   }

    // DocumentData INSERT ╤Б BYTES_64
    $result = pg_query_params($pgConn, '
        INSERT INTO "DocumentData" (id, type, data, "initialData")
        VALUES ($1, $2, $3, $4)
    ', [
        $documentDataId,
        'BYTES_64',
        $pdfBase64,
        $pdfBase64
    ]);

    if (!$result) {
        throw new Exception('DocumentData insert failed: ' . pg_last_error($pgConn));                                                                               }

    // EnvelopeItem INSERT
    $result = pg_query_params($pgConn, '
        INSERT INTO "EnvelopeItem" (id, title, "documentDataId", "envelopeId", "order")                                                                                 VALUES ($1, $2, $3, $4, $5)
    ', [
        $envelopeItemId,
        'Contract.pdf',
        $documentDataId,
        $envelopeId,
        1
    ]);

    if (!$result) {
        throw new Exception('EnvelopeItem insert failed: ' . pg_last_error($pgConn));                                                                               }

    // Recipient Tokens
    $buyerToken = generateRecipientToken();
    $sellerToken = null;
    $sellerEmail = null;

    // 1. INSERT SELLER (if provided)
    // Fix: Frontend sends 'kennelEmail', ensure we catch it
    $sellerEmailInput = $data['sellerEmail'] ?? $data['kennelEmail'] ?? '';     

    if (!empty($sellerEmailInput)) {
        $sellerToken = generateRecipientToken();
        $sellerEmail = $sellerEmailInput;
        $sellerName = $data['sellerName'] ?? $data['kennelName'] ?? $data['kennelOwner'] ?? 'Seller';                                                           
        $resSeller = pg_query_params($pgConn, '
            INSERT INTO "Recipient" (email, name, token, role, "readStatus", "signingStatus", "sendStatus", "envelopeId")                                                   VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
        ', [
            $sellerEmailInput,
            $sellerName,
            $sellerToken,
            'SIGNER',
            'NOT_OPENED',
            'NOT_SIGNED',
            'SENT',
            $envelopeId
        ]);
        if (!$resSeller) error_log("Seller insert failed: " . pg_last_error($pgConn));                                                                              }

    // 2. INSERT BUYER (Default)
    $result = pg_query_params($pgConn, '
        INSERT INTO "Recipient" (email, name, token, role, "readStatus", "signingStatus", "sendStatus", "envelopeId")                                                   VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
    ', [
        $data['buyerEmail'] ?? 'buyer@example.com',
        $data['buyerName'] ?? 'Buyer',
        $buyerToken,
        'SIGNER',
        'NOT_OPENED',
        'NOT_SIGNED',
        'SENT',
        $envelopeId
    ]);

    if (!$result) {
        throw new Exception('Recipient insert failed: ' . pg_last_error($pgConn)
);                                                                                  }

    @unlink($filledPdfPath);
    pg_close($pgConn);

    $baseUrl = rtrim(getDocumensoBaseUrl(), '/');
    $buyerSigningUrl = $baseUrl . "/sign/{$buyerToken}";
    $sellerSigningUrl = $sellerToken ? ($baseUrl . "/sign/{$sellerToken}") : null;

    // ╨Ю╤В╨┐╤А╨░╨▓╨╗╤П╨╡╨╝ envelope ╤З╨╡╤А╨╡╨╖ API Documenso ╨┤╨╗╤П ╨╛╤В╨┐╤А╨░╨▓╨║╨╕ email
    $apiUrl = $baseUrl . "/api/documents/{$secondaryId}/send";

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            // Documenso API ╤В╤А╨╡╨▒╤Г╨╡╤В ╨░╨▓╤В╨╛╤А╨╕╨╖╨░╤Ж╨╕╤О, ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╨╝ internal ╨╖╨░╨┐╤А╨╛╤Б    
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'sendEmail' => true
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $emailSent = ($httpCode === 200);

    echo json_encode([
        'success' => true,
        'envelope_id' => $envelopeId,
        'secondary_id' => $secondaryId,
        'recipient_token' => $buyerToken,
        'signing_url' => $buyerSigningUrl,
        'seller_token' => $sellerToken,
        'seller_signing_url' => $sellerSigningUrl,
        'seller_email' => $sellerEmail,
        'storage_type' => 'BYTES_64',
        'email_sent' => $emailSent,
        'recipient_email' => $data['buyerEmail'] ?? 'buyer@example.com',        
        'status' => 'PENDING'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

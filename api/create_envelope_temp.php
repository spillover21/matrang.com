<?php
header('Content-Type: application/json');
date_default_timezone_set('UTC');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendError($code, $message) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

set_exception_handler(function($e) {
    sendError(500, $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'matrang_secret_key_2026') {
    sendError(401, 'Unauthorized');
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid JSON payload');
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
        return rtrim(strtr($part1, '+/', '-_'), '=') . '-' . rtrim(strtr($part2, '+/', '-_'), '=');
    }

    function getDocumensoBaseUrl() {
        return 'http://72.62.114.139:9000';
    }

    function sendSmtpEmail($to, $subject, $body) {
        $host = 'smtp.hostinger.com';
        $port = 587;
        $username = 'noreply@matrang.com';
        $password = 'Gibson2104)))';
        $from = 'noreply@matrang.com';
        $fromName = 'Great Legacy Bully';

        $socket = fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            throw new Exception("SMTP Connection failed: $errstr ($errno)");    
        }

        $read = function() use ($socket) {
            $response = '';
            while (substr($response, 3, 1) != ' ') {
                if (!($response = fgets($socket, 256))) { return false; }       
            }
            return substr($response, 0, 3);
        };

        $check = function($code) use ($read) {
            $res = $read();
            return $res == $code;
        };

        $read(); // banner
        fwrite($socket, "EHLO $host\r\n"); $read();
        fwrite($socket, "STARTTLS\r\n"); $read();
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        fwrite($socket, "EHLO $host\r\n"); $read();
        fwrite($socket, "AUTH LOGIN\r\n"); $read();
        fwrite($socket, base64_encode($username) . "\r\n"); $read();
        fwrite($socket, base64_encode($password) . "\r\n");
        if (!$check('235')) {
             throw new Exception("SMTP Auth failed");
        }

        fwrite($socket, "MAIL FROM: <$from>\r\n"); $read();
        fwrite($socket, "RCPT TO: <$to>\r\n"); $read();
        fwrite($socket, "DATA\r\n"); $read();

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: $fromName <$from>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n\r\n";
        $headers .= "$body\r\n.\r\n";

        fwrite($socket, $headers);
        $read();
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    }

    $envelopeId = generateEnvelopeId();
    $documentDataId = generateCuid();
    $envelopeItemId = 'envelope_item_' . bin2hex(random_bytes(12));
    $documentMetaId = generateCuid();
    $now = date('Y-m-d H:i:s');

    $pgConn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');
    if (!$pgConn) {
        throw new Exception('PostgreSQL connection failed');
    }

    function execQuery($conn, $sql, $params = []) {
        $result = @pg_query_params($conn, $sql, $params);
        if ($result === false) {
            throw new Exception('DB Query Failed: ' . pg_last_error($conn) . " SQL: " . substr($sql, 0, 50) . "...");
        }
        return $result;
    }

    $result = pg_query($pgConn, 'SELECT MAX(CAST(SUBSTRING("secondaryId" FROM \'document_([0-9]+)\') AS INTEGER)) AS max_id FROM "Envelope"');
    if (!$result) {
        $nextId = 1;
    } else {
        $row = pg_fetch_assoc($result);
        $nextId = ($row['max_id'] ?? 0) + 1;
    }
    $secondaryId = "document_{$nextId}";

    if (empty($data['contractNumber'])) {
        $data['contractNumber'] = 'MDOG-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }

    $jsonPath = sys_get_temp_dir() . '/data_' . bin2hex(random_bytes(6)) . '.json';
    file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $filledPdfPath = sys_get_temp_dir() . '/filled_' . bin2hex(random_bytes(6)) . '.pdf';
    $pythonCmd = "python3 /var/www/documenso-bridge/fill_pdf.py /var/www/documenso-bridge/template.pdf {$filledPdfPath} {$jsonPath} 2>&1";
    exec($pythonCmd, $output, $returnCode);
    @unlink($jsonPath);

    if ($returnCode !== 0 || !file_exists($filledPdfPath)) {
        throw new Exception('PDF creation failed. Python: ' . implode("\n", $output));
    }

    // UPLOAD TO S3 Logic
    $s3Key = "documents/{$envelopeId}/contract.pdf";
    $uploadCmd = "python3 /var/www/documenso-bridge/upload_s3.py {$filledPdfPath} {$s3Key} 2>&1";
    exec($uploadCmd, $uploadOutput, $uploadReturn);
    @unlink($filledPdfPath);

    if ($uploadReturn !== 0) {
        throw new Exception("S3 Upload Failed: " . implode("\n", $uploadOutput));
    }

    // Check if output contains SUCCESS
    $uploadedKey = '';
    foreach ($uploadOutput as $line) {
        if (strpos($line, 'SUCCESS:') === 0) {
            $uploadedKey = substr($line, 8);
            break;
        }
    }
    if (!$uploadedKey) {
         throw new Exception("S3 Upload did not return success key. Output: " . implode("\n", $uploadOutput));
    }

    $s3DataValue = $uploadedKey;

    execQuery($pgConn, '
        INSERT INTO "DocumentMeta" (id, subject, message, timezone, "dateFormat", "redirectUrl", "typedSignatureEnabled", "signingOrder", language, "distributionMethod", "emailSettings", "drawSignatureEnabled", "uploadSignatureEnabled", "allowDictateNextSigner")
        VALUES ($1, NULL, NULL, $2, $3, NULL, true, $4, $5, $6, $7::jsonb, true, true, false)',
        [$documentMetaId, 'Etc/UTC', 'yyyy-MM-dd hh:mm a', 'PARALLEL', 'en', 'EMAIL', '{"documentDeleted": true, "documentPending": true, "recipientSigned": true, "recipientRemoved": true, "documentCompleted": true, "ownerDocumentCompleted": true, "recipientSigningRequest": true}']);

    execQuery($pgConn, '
        INSERT INTO "Envelope" (
            id, "secondaryId", source, type, "internalVersion",
            title, status, "useLegacyFieldInsertion", visibility, "templateType", "publicTitle", "publicDescription", "userId", "teamId", "documentMetaId", "createdAt", "updatedAt", "authOptions"
        )
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18::jsonb)',
        [
        $envelopeId, $secondaryId, 'DOCUMENT', 'DOCUMENT', 1, 'Contract',
        'PENDING',
        'false', 'EVERYONE', 'PRIVATE', '', '', 3, 3, $documentMetaId, $now, $now, '{"globalAccessAuth": [], "globalActionAuth": []}'
    ]);

    execQuery($pgConn, '
        INSERT INTO "DocumentData" (id, type, data, "initialData") VALUES ($1, $2, $3, $4)',
        [$documentDataId, 'S3_PATH', $s3DataValue, $s3DataValue]);

    execQuery($pgConn, '
        INSERT INTO "EnvelopeItem" (id, title, "documentDataId", "envelopeId", "order")
        VALUES ($1, $2, $3, $4, $5)
    ', [$envelopeItemId, 'Contract', $documentDataId, $envelopeId, 1]);

    // --- RECIPIENT 1: BUYER ---
    $buyerToken = generateRecipientToken();
    $buyerName = $data['buyerName'] ?? 'Buyer';
    $buyerEmail = $data['buyerEmail'] ?? 'buyer@example.com';

    $resBuyer = execQuery($pgConn, '
        INSERT INTO "Recipient" (email, name, token, role, "readStatus", "signingStatus", "sendStatus", "envelopeId", "authOptions", "signingOrder")
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9::jsonb, $10)
        RETURNING id
    ', [
        $buyerEmail,
        $buyerName,
        $buyerToken,
        'SIGNER',
        'NOT_OPENED',
        'NOT_SIGNED',
        'SENT',
        $envelopeId,
        '{"accessAuth": [], "actionAuth": []}',
        1
    ]);
    // --- RECIPIENT 1: BUYER ---
    $rowBuyer = pg_fetch_assoc($resBuyer);
    $buyerId = $rowBuyer['id'];

    // Buyer Field 1: Page 9 (Bottom - 12%)
    // Y = 100 - 12 (margin) - 5 (height) = 83.0
    $buyerField1 = generateCuid();
    execQuery($pgConn, '
        INSERT INTO "Field" ("recipientId", type, page, "positionX", "positionY", "customText", inserted, height, width, "secondaryId", "fieldMeta", "envelopeId", "envelopeItemId")
        VALUES ($1, $2, $3, $4, $5, $6, $7::boolean, $8, $9, $10, $11::jsonb, $12, $13)',
        [$buyerId, 'SIGNATURE', 9, 60.0, 83.0, '', 'f', 5.0, 20.0, $buyerField1, '{"type": "signature", "required": true}', $envelopeId, $envelopeItemId]);

    // Buyer Field 2: Page 12 (Bottom - 10%)
    // Y = 100 - 10 (margin) - 5 (height) = 85.0
    $buyerField2 = generateCuid();
    execQuery($pgConn, '
        INSERT INTO "Field" ("recipientId", type, page, "positionX", "positionY", "customText", inserted, height, width, "secondaryId", "fieldMeta", "envelopeId", "envelopeItemId")
        VALUES ($1, $2, $3, $4, $5, $6, $7::boolean, $8, $9, $10, $11::jsonb, $12, $13)',
        [$buyerId, 'SIGNATURE', 12, 60.0, 85.0, '', 'f', 5.0, 20.0, $buyerField2, '{"type": "signature", "required": true}', $envelopeId, $envelopeItemId]);

    // --- RECIPIENT 2: SELLER (YOU) ---
    $sellerToken = generateRecipientToken();
    $sellerName = $data['sellerName'] ?? 'Great Legacy Bully';
    $sellerEmail = $data['sellerEmail'] ?? 'noreply@matrang.com'; // Default adjusted

    // Only add seller if not same as buyer
    if ($sellerEmail !== $buyerEmail) {
        $resSeller = execQuery($pgConn, '
            INSERT INTO "Recipient" (email, name, token, role, "readStatus", "signingStatus", "sendStatus", "envelopeId", "authOptions", "signingOrder")
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9::jsonb, $10)
            RETURNING id
        ', [
            $sellerEmail,
            $sellerName,
            $sellerToken,
            'SIGNER',
            'NOT_OPENED',
            'NOT_SIGNED',
            'SENT',
            $envelopeId,
            '{"accessAuth": [], "actionAuth": []}',
            1
        ]);
        $rowSeller = pg_fetch_assoc($resSeller);
        $sellerId = $rowSeller['id'];

        // Seller Field 1: Page 9 (Bottom - 30%)
        // Y = 100 - 30 (margin) - 5 (height) = 65.0
        $sellerField1 = generateCuid();
        execQuery($pgConn, '
            INSERT INTO "Field" ("recipientId", type, page, "positionX", "positionY", "customText", inserted, height, width, "secondaryId", "fieldMeta", "envelopeId", "envelopeItemId")
            VALUES ($1, $2, $3, $4, $5, $6, $7::boolean, $8, $9, $10, $11::jsonb, $12, $13)',
            [$sellerId, 'SIGNATURE', 9, 60.0, 65.0, '', 'f', 5.0, 20.0, $sellerField1, '{"type": "signature", "required": true}', $envelopeId, $envelopeItemId]);

        // Seller Field 2: Page 12 (Bottom - 20%)
        // Y = 100 - 20 (margin) - 5 (height) = 75.0
        $sellerField2 = generateCuid();
        execQuery($pgConn, '
            INSERT INTO "Field" ("recipientId", type, page, "positionX", "positionY", "customText", inserted, height, width, "secondaryId", "fieldMeta", "envelopeId", "envelopeItemId")
            VALUES ($1, $2, $3, $4, $5, $6, $7::boolean, $8, $9, $10, $11::jsonb, $12, $13)',
            [$sellerId, 'SIGNATURE', 12, 60.0, 75.0, '', 'f', 5.0, 20.0, $sellerField2, '{"type": "signature", "required": true}', $envelopeId, $envelopeItemId]);
    }

    pg_close($pgConn);

    $baseUrl = getDocumensoBaseUrl();
    $documentUrl = $baseUrl . "/sign/{$buyerToken}";

    // 1. Send Email to Buyer
    $emailSubject = "Please sign your document: Contract.pdf";
    $emailBody = "
        <h2>Document Signing Request</h2>
        <p>You have been invited to sign a document.</p>
        <p>Click here to sign: <a href=\"{$documentUrl}\">{$documentUrl}</a></p>
    ";
    
    $emailSent = false;
    try {
        $emailSent = sendSmtpEmail($buyerEmail, $emailSubject, $emailBody);
    } catch (Throwable $e) {
        // Suppress email errors to allow successful response
    }

    // 2. Send Email to Seller (if exists)
    $emailSentSeller = false;
    if (isset($sellerToken) && $sellerEmail) {
        $sellerUrl = $baseUrl . "/sign/{$sellerToken}";
        $sellerSubject = "Please sign contract as Seller";
        $sellerBody = "
            <h2>Document Signing Request (Seller)</h2>
            <p>You have been invited to sign a document as the Seller.</p>
            <p>Click here to sign: <a href=\"{$sellerUrl}\">{$sellerUrl}</a></p>
        ";
        try {
            $emailSentSeller = sendSmtpEmail($sellerEmail, $sellerSubject, $sellerBody);
        } catch (Exception $e) {
            // Ignore seller email error to not break the response, but maybe log it
            // error_log("Seller email failed: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'envelope_id' => $envelopeId,
        'signing_url' => $documentUrl,
        'seller_signing_url' => isset($sellerToken) ? $baseUrl . "/sign/{$sellerToken}" : null,
        'email_sent' => $emailSent,
        'email_sent_seller' => $emailSentSeller,
        'status' => 'SENT',
        'debug_info' => 'Fixed Version + Seller Added + Email Safe'
    ]);

} catch (Throwable $e) {
    sendError(500, $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
}
?>
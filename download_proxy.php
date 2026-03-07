<?php
// Proxy endpoint для безопасного скачивания PDF из Documenso
// Размещается на VPS (72.62.114.139), работает через Docker network

// Логирование
$logFile = '/tmp/download_proxy.log';
function logMessage($msg) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

header("Access-Control-Allow-Origin: *");
logMessage("=== NEW REQUEST ===");

// Получаем ID документа
$documentId = $_GET["id"] ?? null;
if (!$documentId) {
    http_response_code(400);
    logMessage("ERROR: No document ID provided");
    die(json_encode(['error' => 'No document ID provided']));
}

logMessage("Document ID: $documentId");

// Documenso API токен
$apiToken = "api_5dj281rj6qj7t541";

// Получаем download URL из Documenso API (localhost работает внутри Docker network)
$ch = curl_init("http://localhost:9000/api/v1/documents/$documentId/download");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiToken", "Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

logMessage("Calling Documenso API...");
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    logMessage("CURL ERROR: $curlError");
    die(json_encode(['error' => "CURL error: $curlError"]));
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    logMessage("API ERROR: HTTP $httpCode, Response: $response");
    die(json_encode(['error' => "Documenso API returned HTTP $httpCode"]));
}

$data = json_decode($response, true);
if (!isset($data['downloadUrl'])) {
    http_response_code(404);
    logMessage("ERROR: downloadUrl not found in API response");
    die(json_encode(['error' => 'Download URL not found in response']));
}

$downloadUrl = $data['downloadUrl'];
logMessage("Got download URL: " . substr($downloadUrl, 0, 100) . "...");

// Скачиваем PDF из MinIO (внутренний URL будет работать внутри Docker network)
$pdfCh = curl_init($downloadUrl);
curl_setopt($pdfCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($pdfCh, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($pdfCh, CURLOPT_TIMEOUT, 30);
curl_setopt($pdfCh, CURLOPT_HTTPHEADER, ["Accept: application/pdf"]);

logMessage("Downloading PDF from MinIO...");
$pdfContent = curl_exec($pdfCh);
$pdfHttpCode = curl_getinfo($pdfCh, CURLINFO_HTTP_CODE);
$pdfCurlError = curl_error($pdfCh);
curl_close($pdfCh);

if ($pdfCurlError) {
    http_response_code(500);
    logMessage("PDF DOWNLOAD ERROR: $pdfCurlError");
    die(json_encode(['error' => "Failed to download PDF: $pdfCurlError"]));
}

if ($pdfHttpCode !== 200 || empty($pdfContent)) {
    http_response_code(500);
    logMessage("PDF HTTP ERROR: HTTP $pdfHttpCode, Content length: " . strlen($pdfContent));
    die(json_encode(['error' => "Failed to download PDF: HTTP $pdfHttpCode"]));
}

// Проверяем что это PDF
if (substr($pdfContent, 0, 4) !== '%PDF') {
    http_response_code(500);
    $preview = substr($pdfContent, 0, 100);
    logMessage("INVALID PDF: First bytes: $preview");
    die(json_encode(['error' => 'Invalid PDF format']));
}

$pdfSize = strlen($pdfContent);
logMessage("SUCCESS: PDF downloaded, size: $pdfSize bytes");

// Отправляем PDF клиенту
header("Content-Type: application/pdf");
header("Content-Length: $pdfSize");
header("Content-Disposition: attachment; filename=\"contract_$documentId.pdf\"");
header("Cache-Control: no-cache");

echo $pdfContent;
logMessage("PDF sent to client");

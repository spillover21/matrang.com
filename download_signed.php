<?php
/**
 * Download Signed Envelope PDF
 * 
 * Serves the PDF directly from the database for a given Envelope ID.
 */

$host = '72.62.114.139';
$db   = 'documenso';
$user = 'documenso';
$pass = 'documenso123';
$port = "5432";

$envelopeId = $_GET['id'] ?? '';

if (!$envelopeId) {
    die("No envelope ID provided.");
}

// Simple security check: Ensure ID contains only alphanumeric characters
if (!preg_match('/^[a-zA-Z0-9_]+$/', $envelopeId)) {
    die("Invalid envelope ID.");
}

$conn = pg_connect("host=$host port=$port dbname=$db user=$user password=$pass");
if (!$conn) {
    die("Database connection failed.");
}

// Fetch the document data associated with the envelope
// We assume the first document in the envelope is the main contract
$query = '
    SELECT dd.data 
    FROM "Envelope" e
    JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
    JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
    WHERE e.id = $1
    LIMIT 1
';

$result = pg_query_params($conn, $query, [$envelopeId]);

if (!$result || pg_num_rows($result) === 0) {
    die("Document not found.");
}

$row = pg_fetch_assoc($result);
$pdfBase64 = $row['data'];

// Decode PDF
$pdfData = base64_decode($pdfBase64);

if (substr($pdfData, 0, 4) !== '%PDF') {
    die("Error: Data is not a valid PDF.");
}

// Serve PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="signed_contract_' . $envelopeId . '.pdf"');
header('Content-Length: ' . strlen($pdfData));

echo $pdfData;
pg_close($conn);
?>

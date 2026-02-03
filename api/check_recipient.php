<?php
$conn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

$envelopeId = 'envelope_12d4a052bfd7af384abd4c77';

$res = pg_query($conn, "SELECT email, name, role, \"signingStatus\", token FROM \"Recipient\" WHERE \"envelopeId\" = '{$envelopeId}'");
$recipient = pg_fetch_assoc($res);

echo "=== RECIPIENT ===\n";
echo json_encode($recipient, JSON_PRETTY_PRINT);

pg_close($conn);

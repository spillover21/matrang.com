<?php
$conn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

// Найдем последний envelope
$res = pg_query($conn, 'SELECT id, "documentMetaId" FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 1');
$envelope = pg_fetch_assoc($res);

echo "=== ENVELOPE ===\n";
echo "ID: " . $envelope['id'] . "\n\n";

// Найдем EnvelopeItem
$res = pg_query($conn, "SELECT id, \"documentDataId\" FROM \"EnvelopeItem\" WHERE \"envelopeId\" = '{$envelope['id']}'");
$item = pg_fetch_assoc($res);

echo "=== ENVELOPE ITEM ===\n";
echo "ID: " . $item['id'] . "\n";
echo "DocumentDataId: " . $item['documentDataId'] . "\n\n";

// Найдем DocumentData
$res = pg_query($conn, "SELECT id, type, data, \"initialData\" FROM \"DocumentData\" WHERE id = '{$item['documentDataId']}'");
$data = pg_fetch_assoc($res);

echo "=== DOCUMENT DATA ===\n";
echo "ID: " . $data['id'] . "\n";
echo "Type: " . $data['type'] . "\n";
echo "Data: " . $data['data'] . "\n";
echo "InitialData: " . $data['initialData'] . "\n";

pg_close($conn);

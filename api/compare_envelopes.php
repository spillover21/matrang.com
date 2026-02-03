<?php
$conn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

// Найдем envelope договор..pdf (созданный через UI)
$res = pg_query($conn, 'SELECT id, "userId", status, title FROM "Envelope" WHERE title LIKE \'%договор%\' LIMIT 1');
$envelope = pg_fetch_assoc($res);

echo "=== UI ENVELOPE (договор..pdf) ===\n";
echo json_encode($envelope, JSON_PRETTY_PRINT) . "\n\n";

// Найдем EnvelopeItem
$res = pg_query($conn, "SELECT * FROM \"EnvelopeItem\" WHERE \"envelopeId\" = '{$envelope['id']}'");
$items = [];
while ($row = pg_fetch_assoc($res)) {
    $items[] = $row;
}

echo "=== ENVELOPE ITEMS ===\n";
echo json_encode($items, JSON_PRETTY_PRINT) . "\n\n";

// Найдем Recipients
$res = pg_query($conn, "SELECT * FROM \"Recipient\" WHERE \"envelopeId\" = '{$envelope['id']}'");
$recipients = [];
while ($row = pg_fetch_assoc($res)) {
    $recipients[] = $row;
}

echo "=== RECIPIENTS ===\n";
echo json_encode($recipients, JSON_PRETTY_PRINT) . "\n\n";

// Сравним с нашим созданным envelope
$res = pg_query($conn, 'SELECT id FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 1');
$ourEnv = pg_fetch_assoc($res);

echo "=== OUR LATEST ENVELOPE ===\n";
echo "ID: " . $ourEnv['id'] . "\n";

$res = pg_query($conn, "SELECT * FROM \"Recipient\" WHERE \"envelopeId\" = '{$ourEnv['id']}'");
$ourRecipients = [];
while ($row = pg_fetch_assoc($res)) {
    $ourRecipients[] = $row;
}
echo "Recipients count: " . count($ourRecipients) . "\n";

pg_close($conn);

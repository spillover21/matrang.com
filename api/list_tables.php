<?php
$conn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

echo "=== TABLES ===\n";
$res = pg_query($conn, "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
while ($row = pg_fetch_assoc($res)) {
    echo $row['tablename'] . "\n";
}

echo "\n=== ENVELOPE COUNT ===\n";
$res = pg_query($conn, 'SELECT COUNT(*) as count FROM "Envelope"');
$count = pg_fetch_assoc($res);
echo "Total envelopes: " . $count['count'] . "\n";

echo "\n=== ALL ENVELOPES ===\n";
$res = pg_query($conn, 'SELECT id, "userId", status, title, "createdAt" FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 5');
while ($row = pg_fetch_assoc($res)) {
    echo json_encode($row) . "\n";
}

pg_close($conn);

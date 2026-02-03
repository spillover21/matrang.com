<?php
$pgConn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');
$result = pg_query($pgConn, 'SELECT e.id, e."createdAt", e.title, r.name as recipient FROM "Envelope" e LEFT JOIN "Recipient" r ON e.id = r."envelopeId" WHERE e."userId" = 3 ORDER BY e."createdAt" DESC LIMIT 3');

echo "Последние 3 envelope:\n\n";
while ($row = pg_fetch_assoc($result)) {
    echo "ID: " . $row['id'] . "\n";
    echo "Created: " . $row['createdAt'] . "\n";
    echo "Title: " . $row['title'] . "\n";
    echo "Recipient: " . $row['recipient'] . "\n";
    echo "---\n";
}

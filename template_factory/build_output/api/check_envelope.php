<?php
$pgConn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password={{DB_PASSWORD}}');
$result = pg_query_params($pgConn, 'SELECT id, "secondaryId", title, status FROM "Envelope" WHERE id = $1', ['envelope_03c91534dbd9e531abccb689']);
$row = pg_fetch_assoc($result);
echo json_encode($row, JSON_PRETTY_PRINT) . PHP_EOL;

echo "\nПравильный URL для просмотра документа в Documenso:\n";
echo "http://{{SERVER_IP}}:9000/documents/" . $row['id'] . "\n";

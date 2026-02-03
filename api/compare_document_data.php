<?php
$pgConn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

// Работающий документ
echo "=== WORKING DOCUMENT (договор) ===\n";
$result = pg_query($pgConn, "
    SELECT dd.* 
    FROM \"DocumentData\" dd
    JOIN \"EnvelopeItem\" ei ON dd.id = ei.\"documentDataId\"
    JOIN \"Envelope\" e ON ei.\"envelopeId\" = e.id
    WHERE e.title LIKE '%договор%'
    LIMIT 1
");
$working = pg_fetch_assoc($result);
print_r($working);

echo "\n\n=== OUR DOCUMENT (Contract.pdf) ===\n";
$result = pg_query($pgConn, "
    SELECT dd.* 
    FROM \"DocumentData\" dd
    JOIN \"EnvelopeItem\" ei ON dd.id = ei.\"documentDataId\"
    JOIN \"Envelope\" e ON ei.\"envelopeId\" = e.id
    WHERE e.id = 'envelope_03c91534dbd9e531abccb689'
");
$our = pg_fetch_assoc($result);
print_r($our);

echo "\n\n=== COMPARISON ===\n";
echo "Working data: " . $working['data'] . "\n";
echo "Our data: " . $our['data'] . "\n";
echo "\nWorking initialData: " . $working['initialData'] . "\n";
echo "Our initialData: " . $our['initialData'] . "\n";

<?php
$conn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

echo "Searching for envelope_82c15fc5c4ba1f5d82a03dde...\n\n";

$res = pg_query($conn, "SELECT id, \"userId\", status, title FROM \"Envelope\" WHERE id = 'envelope_82c15fc5c4ba1f5d82a03dde'");
$row = pg_fetch_assoc($res);

if ($row) {
    echo "FOUND!\n";
    echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "NOT FOUND\n\n";
    echo "Checking if similar IDs exist:\n";
    $res2 = pg_query($conn, "SELECT id FROM \"Envelope\" WHERE id LIKE '%82c15fc5%'");
    while ($r = pg_fetch_assoc($res2)) {
        echo "  - " . $r['id'] . "\n";
    }
}

pg_close($conn);

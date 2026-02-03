<?php
$conn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

echo "=== DocumentMeta columns ===\n";
$res = pg_query($conn, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'DocumentMeta' ORDER BY ordinal_position");
while ($row = pg_fetch_assoc($res)) {
    echo $row['column_name'] . " (" . $row['data_type'] . ")\n";
}

echo "\n=== Envelope columns ===\n";
$res = pg_query($conn, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'Envelope' ORDER BY ordinal_position");
while ($row = pg_fetch_assoc($res)) {
    echo $row['column_name'] . " (" . $row['data_type'] . ")\n";
}

pg_close($conn);

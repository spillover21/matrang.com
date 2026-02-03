<?php
$conn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123');

echo "=== WORKING ENVELOPE (договор..pdf) ===\n\n";

// Envelope
$res = pg_query($conn, "SELECT * FROM \"Envelope\" WHERE id = 'envelope_vdxmarbsdihayumw'");
$workingEnv = pg_fetch_assoc($res);
echo "ENVELOPE:\n";
foreach ($workingEnv as $key => $val) {
    if ($val !== null && $val !== '') {
        echo "  $key: " . (strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val) . "\n";
    }
}

// EnvelopeItem
echo "\nENVELOPE ITEM:\n";
$res = pg_query($conn, "SELECT * FROM \"EnvelopeItem\" WHERE \"envelopeId\" = 'envelope_vdxmarbsdihayumw'");
$item = pg_fetch_assoc($res);
foreach ($item as $key => $val) {
    if ($val !== null && $val !== '') {
        echo "  $key: $val\n";
    }
}

// DocumentData
echo "\nDOCUMENT DATA:\n";
$res = pg_query($conn, "SELECT * FROM \"DocumentData\" WHERE id = '{$item['documentDataId']}'");
$docData = pg_fetch_assoc($res);
foreach ($docData as $key => $val) {
    if ($val !== null && $val !== '') {
        echo "  $key: $val\n";
    }
}

echo "\n\n=== OUR ENVELOPE (latest) ===\n\n";

// Our Envelope
$res = pg_query($conn, 'SELECT * FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 1');
$ourEnv = pg_fetch_assoc($res);
echo "ENVELOPE:\n";
foreach ($ourEnv as $key => $val) {
    if ($val !== null && $val !== '') {
        echo "  $key: " . (strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val) . "\n";
    }
}

// EnvelopeItem
echo "\nENVELOPE ITEM:\n";
$res = pg_query($conn, "SELECT * FROM \"EnvelopeItem\" WHERE \"envelopeId\" = '{$ourEnv['id']}'");
$ourItem = pg_fetch_assoc($res);
foreach ($ourItem as $key => $val) {
    if ($val !== null && $val !== '') {
        echo "  $key: $val\n";
    }
}

// DocumentData
echo "\nDOCUMENT DATA:\n";
$res = pg_query($conn, "SELECT * FROM \"DocumentData\" WHERE id = '{$ourItem['documentDataId']}'");
$ourDocData = pg_fetch_assoc($res);
foreach ($ourDocData as $key => $val) {
    if ($val !== null && $val !== '') {
        echo "  $key: $val\n";
    }
}

echo "\n\n=== DIFFERENCES ===\n";
echo "Working envelope fields: " . count(array_filter($workingEnv, fn($v) => $v !== null && $v !== '')) . "\n";
echo "Our envelope fields: " . count(array_filter($ourEnv, fn($v) => $v !== null && $v !== '')) . "\n";

pg_close($conn);

<?php
$data = array("buyer_full_name" => "Test User", "buyer_email" => "test@test.com");

try {
    $templatePath = "/var/www/documenso-bridge/templates/contract_template.pdf";
    if (!file_exists($templatePath)) {
        echo json_encode(array("success" => false, "error" => "Template not found"));
        exit;
    }

    $pgConfig = "host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123";
    $pgConn = pg_connect($pgConfig);
    
    if (!$pgConn) {
        echo json_encode(array("success" => false, "error" => "DB connection failed"));
        exit;
    }
    
    $result = pg_query($pgConn, "SELECT COUNT(*) FROM \"Envelope\" WHERE \"userId\" = 3");
    $row = pg_fetch_row($result);
    
    echo json_encode(array("success" => true, "message" => "Connection OK", "envelope_count" => $row[0], "template_exists" => true));
    
    pg_close($pgConn);
    
} catch (Exception $e) {
    echo json_encode(array("success" => false, "error" => $e->getMessage()));
}

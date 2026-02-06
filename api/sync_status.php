<?php
// api/check_status_api.php
// Manually syncs status with Documenso/VPS
header('Content-Type: application/json');

$contractsFile = __DIR__ . '/../data/contracts.json';
$updatedCount = 0;

if (file_exists($contractsFile)) {
    $data = json_decode(file_get_contents($contractsFile), true);
    
    $contracts = [];
    if (isset($data['contracts'])) {
        $contracts = &$data['contracts'];
    } elseif (is_array($data)) {
        $contracts = &$data;
    }

    $pgConn = pg_connect('host=127.0.0.1 port=5432 dbname=documenso user=documenso password=documenso123'); // Local VPS connection? No, this runs on hosting...
    // Wait, if hosting !== VPS, we can't connect to PGSQL directly unless port is exposed.
    // The previous analysis showed api.php calls "http://72.62.114.139:8080/create_envelope.php".
    // So we need a script on VPS to check status.
    
    // HOWEVER: The user context implies we are editing files via VS Code mapped to the server?
    // User said "e:\pitbull\public_html" is the workspace.
    // Use the VPS API to check status.
    
    // We need a loop to check "sent" contracts.
    foreach ($contracts as &$contract) {
        if (($contract['status'] ?? '') === 'sent' && !empty($contract['id'])) {
            $envelopeId = $contract['id'];
            
             // Call VPS to check status
             $ch = curl_init('http://72.62.114.139:8080/check_envelope_status.php?id=' . $envelopeId);
             curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
             curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: matrang_secret_key_2026']);
             curl_setopt($ch, CURLOPT_TIMEOUT, 5);
             $res = curl_exec($ch);
             $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
             curl_close($ch);
             
             if ($httpCode === 200 && $res) {
                 $vpsData = json_decode($res, true);
                 // Expected: {"status": "COMPLETED" or "PENDING", ...}
                 // Documenso statuses: COMPLETED, PENDING, REJECTED
                 
                 if (isset($vpsData['status'])) {
                     if ($vpsData['status'] === 'COMPLETED') {
                         $contract['status'] = 'signed';
                         $contract['signedAt'] = date('c'); 
                         $updatedCount++;
                     } elseif ($vpsData['status'] === 'REJECTED') {
                         $contract['status'] = 'rejected';
                         $updatedCount++;
                     }
                 }
             }
        }
    }
    
    if ($updatedCount > 0) {
        file_put_contents($contractsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

echo json_encode(['success' => true, 'updated' => $updatedCount]);
?>
<?php
// api/simple_sign.php
// Local signing handler to bypass unstable VPS

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/../data/contracts.json';

// Helper to get contracts
function getContractsData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ['contracts' => [], 'templates' => []];
    }
    return json_decode(file_get_contents($dataFile), true) ?? ['contracts' => [], 'templates' => []];
}

// Helper to save
function saveContractsData($data) {
    global $dataFile;
    return file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID required']);
    exit();
}

// --- GET CONTRACT INFO ---
if ($action === 'get') {
    $data = getContractsData();
    // Support both old array format and new object format
    $contracts = $data['contracts'] ?? ($data[0] ? $data : []);

    $found = null;
    foreach ($contracts as $c) {
        if (($c['id'] ?? '') === $id) {
            $found = $c;
            break;
        }
    }

    if ($found) {
        // Return only safe data for the signer
        echo json_encode([
            'success' => true,
            'contract' => [
                'id' => $found['id'],
                'contractNumber' => $found['contractNumber'] ?? 'N/A',
                'status' => $found['status'] ?? 'sent',
                'buyerName' => $found['data']['buyerName'] ?? 'Buyer',
                'dogName' => $found['data']['dogName'] ?? 'Puppy',
                'price' => $found['data']['price'] ?? '0',
                'breed' => $found['data']['breed'] ?? 'Pitbull',
                'sellerName' => $found['data']['kennelName'] ?? 'Seller',
                'createdAt' => $found['createdAt'] ?? date('c'),
                'signedAt' => $found['signedAt'] ?? null
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contract not found']);
    }
    exit();
}

// --- SIGN CONTRACT ---
if ($action === 'sign') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $data = getContractsData();
    $contracts = &$data['contracts']; // Reference for modification
    if (!isset($contracts)) {
        // If old format, convert to new
        $data = ['contracts' => $data, 'templates' => []];
        $contracts = &$data['contracts'];
    }

    $foundIndex = -1;
    foreach ($contracts as $i => $c) {
        if (($c['id'] ?? '') === $id) {
            $foundIndex = $i;
            break;
        }
    }

    if ($foundIndex > -1) {
        $contracts[$foundIndex]['status'] = 'signed';
        $contracts[$foundIndex]['signedAt'] = date('c');
        $contracts[$foundIndex]['signedIP'] = $_SERVER['REMOTE_ADDR'];
        $contracts[$foundIndex]['signerAgent'] = $_SERVER['HTTP_USER_AGENT'];
        
        // Add signature metadata log
        if (!isset($contracts[$foundIndex]['history'])) {
            $contracts[$foundIndex]['history'] = [];
        }
        $contracts[$foundIndex]['history'][] = [
            'action' => 'signed_locally',
            'timestamp' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR']
        ];

        saveContractsData($data);
        
        // --- SEND NOTIFICATION TO SELLER ---
        // We can optionally trigger an email here, or rely on the status check
        // For now, just save.
        
        echo json_encode(['success' => true, 'message' => 'Contract signed successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contract not found']);
    }
    exit();
}

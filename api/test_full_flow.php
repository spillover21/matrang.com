<?php
// api/test_full_flow.php
// Полный тест цикла: Создание -> Проверка токена -> Проверка URL для подписания
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";

// 1. Load Config
echo "1. Loading Config...\n";
$config = require __DIR__ . '/documenso_config.php';
$apiKey = $config['API_KEY'];
$templateId = $config['TEMPLATE_ID'];
$apiUrl = $config['API_URL'];

echo "API URL: $apiUrl\n";
echo "Template ID: $templateId\n";

if (!$apiKey || !$templateId) die("Missing Config");

// 2. Create Document
echo "\n2. Creating Test Document...\n";
$ch = curl_init($apiUrl . '/documents');
$data = [
    'templateId' => (int)$templateId,
    'title' => 'TEST FLOW DOC',
    'metadata' => ['test' => true],
    'recipients' => [
        [
            'email' => 'test_flow@example.com',
            'name' => 'Flow Tester',
            'role' => 'SIGNER',
            'authOptions' => ['requireEmailAuth' => false]
        ]
    ]
];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "...\n";

$json = json_decode($response, true);
if (!$json || !isset($json['id'])) die("Failed to create document");

$docId = $json['id'];
echo "Document ID: $docId\n";

// 3. Find Token
echo "\n3. Extracting Token...\n";
$token = '';
if (!empty($json['recipients'])) {
    foreach ($json['recipients'] as $r) {
        if ($r['email'] == 'test_flow@example.com') {
            $token = $r['token'];
            break;
        }
    }
}

if (!$token) {
    echo "Token not found in response, fetching recipients manually...\n";
    // Fetch recipients
} else {
    echo "Token Found: $token\n";
}

// 4. Test Signing URL Paths
echo "\n4. Testing Signing URLs...\n";
$paths = ['/sign/', '/t/', '/d/'];
$baseUrl = 'https://app.documenso.com'; // or whatever public url

foreach ($paths as $path) {
    $url = $baseUrl . $path . $token;
    echo "Checking $url ... ";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $code\n";
    if ($code == 200) {
        echo ">>> VALID URL FOUND: $url <<<\n";
    }
}
echo "</pre>";

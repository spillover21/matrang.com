<?php
// api/debug_endpoints.php
// Скрипт для поиска правильного API URL
error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiKey = 'api_yv7nozp1x55ozcfz'; // Берем из логов/конфига пользователя для теста
$baseConfigUrl = 'https://app.documenso.com';

$endpointsToTest = [
    '/api/v2/templates',
    '/api/v1/templates',
    '/api/templates',
];

echo "<pre>";
echo "Testing Endpoints against $baseConfigUrl\n\n";

foreach ($endpointsToTest as $path) {
    $url = $baseConfigUrl . $path;
    echo "Trying: $url ... ";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $httpCode\n";
    if ($httpCode == 200) {
        echo "SUCCESS! Response sample: " . substr($response, 0, 200) . "\n";
    } else {
        echo "Response: " . substr($response, 0, 100) . "\n";
    }
    echo "----------------------------------------\n";
}
echo "</pre>";

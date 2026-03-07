<?php
// Mock input
$contractsFile = __DIR__ . '/data/contracts.json';
$nextNum = 1;
$currentYear = date('Y');

echo "Checking: $contractsFile\n";

if (file_exists($contractsFile)) {
    $jsonContent = file_get_contents($contractsFile);
    $jsonData = json_decode($jsonContent, true);
    
    $existingContracts = [];
    if (isset($jsonData['contracts'])) {
        $existingContracts = $jsonData['contracts'];
    } elseif (is_array($jsonData)) {
        $existingContracts = $jsonData;
    }

    echo "Found " . count($existingContracts) . " existing contracts.\n";

    foreach ($existingContracts as $c) {
        $cNum = $c['contractNumber'] ?? ($c['data']['contractNumber'] ?? '');
        echo " - Found ID: $cNum\n";
        
        if ($cNum && preg_match('/DOG-' . $currentYear . '-(\d+)/', $cNum, $matches)) {
            $num = intval($matches[1]);
            echo "   -> Parsed number: $num\n";
            if ($num >= $nextNum) {
                $nextNum = $num + 1;
            }
        }
    }
} else {
    echo "Contracts file not found!\n";
}

// Generate new ID
$newContractNumber = 'DOG-' . $currentYear . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

echo "\nGenerated Next Number: $newContractNumber\n";
?>
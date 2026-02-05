<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting health check...\n";

try {
    require_once __DIR__ . '/signature_system.php';
    echo "signature_system.php included.\n";
    
    if (class_exists('eIDASSignatureSystem')) {
        echo "Class eIDASSignatureSystem exists.\n";
        $sys = new eIDASSignatureSystem();
        echo "Class instantiated successfully.\n";
    } else {
        echo "ERROR: Class eIDASSignatureSystem NOT found.\n";
        exit(1);
    }
    
} catch (Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}

echo "Health check PASSED.\n";
?>
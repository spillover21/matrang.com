<?php
// Проверка наличия драйверов PostgreSQL
header('Content-Type: application/json');

$result = [
    'php_version' => phpversion(),
    'pdo_available' => extension_loaded('pdo'),
    'pdo_pgsql' => extension_loaded('pdo_pgsql'),
    'pgsql' => extension_loaded('pgsql'),
    'pdo_drivers' => PDO::getAvailableDrivers()
];

echo json_encode($result, JSON_PRETTY_PRINT);

<?php
// Debug runner
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_GET['action'] = 'sendSigningLink';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token';

echo "Starting include...\n";
require_once 'api.php';
echo "\nFinished include.\n";

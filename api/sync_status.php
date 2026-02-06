<?php
// api/sync_status.php - Mock file to prevent 500/404 errors
header('Content-Type: application/json');
echo json_encode(['updated' => 0, 'success' => true]);

<?php
/**
 * Serves favicon/logo as OG image via PHP to bypass Hostinger WAF.
 * Minimal logic — just readfile() to avoid rate limits.
 */
$logo = __DIR__ . '/favicon.png';
if (!file_exists($logo)) {
    $logo = __DIR__ . '/og-image.jpg';
}
if (!file_exists($logo)) {
    http_response_code(404);
    exit;
}
http_response_code(200);
$ext = pathinfo($logo, PATHINFO_EXTENSION);
header('Content-Type: image/' . ($ext === 'png' ? 'png' : 'jpeg'));
header('Content-Length: ' . filesize($logo));
header('Cache-Control: public, max-age=604800, immutable');
header('X-Robots-Tag: all');
readfile($logo);


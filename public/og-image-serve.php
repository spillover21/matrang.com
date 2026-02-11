<?php
/**
 * Serves OG image via PHP to bypass Hostinger WAF blocking crawlers on static files.
 */
$imagePath = __DIR__ . '/og-image.jpg';

if (!file_exists($imagePath)) {
    http_response_code(404);
    exit('Image not found');
}

http_response_code(200);
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($imagePath));
header('Cache-Control: public, max-age=86400');
header('X-Robots-Tag: all');
readfile($imagePath);
exit;

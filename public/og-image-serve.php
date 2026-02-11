<?php
/**
 * Generates OG image: logo centered on dark background (1200x630).
 * Bypasses Hostinger WAF for Facebook/WhatsApp crawlers.
 */

$cachePath = __DIR__ . '/og-image-cached.jpg';

// Force regenerate if ?refresh parameter or cache doesn't exist
$forceRefresh = isset($_GET['refresh']);

// Serve cached version if exists and fresh (< 1 hour)
if (!$forceRefresh && file_exists($cachePath) && (time() - filemtime($cachePath)) < 3600) {
    http_response_code(200);
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($cachePath));
    header('Cache-Control: public, max-age=86400');
    readfile($cachePath);
    exit;
}

// Try to generate with GD
if (!function_exists('imagecreatetruecolor')) {
    // Fallback: serve og-image.jpg if GD not available
    $fallback = __DIR__ . '/og-image.jpg';
    if (file_exists($fallback)) {
        http_response_code(200);
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($fallback));
        readfile($fallback);
    } else {
        http_response_code(404);
    }
    exit;
}

// Create 1200x630 canvas with dark background
$width = 1200;
$height = 630;
$canvas = imagecreatetruecolor($width, $height);

// Dark background (#1a1a2e)
$bgColor = imagecolorallocate($canvas, 26, 26, 46);
imagefill($canvas, 0, 0, $bgColor);

// Load logo
$logoPath = __DIR__ . '/favicon.png';
if (!file_exists($logoPath)) {
    // Try uploads logo
    $logoPath = __DIR__ . '/uploads/1767891439_85337ea8.png';
}

if (file_exists($logoPath)) {
    $logoInfo = getimagesize($logoPath);
    if ($logoInfo) {
        $mime = $logoInfo['mime'];
        if ($mime === 'image/png') {
            $logo = imagecreatefrompng($logoPath);
        } elseif ($mime === 'image/jpeg') {
            $logo = imagecreatefromjpeg($logoPath);
        } else {
            $logo = null;
        }
        
        if ($logo) {
            $logoW = imagesx($logo);
            $logoH = imagesy($logo);
            
            // Scale logo to fit ~350px height, centered
            $maxH = 350;
            $maxW = 600;
            $scale = min($maxW / $logoW, $maxH / $logoH);
            $newW = (int)($logoW * $scale);
            $newH = (int)($logoH * $scale);
            
            $x = (int)(($width - $newW) / 2);
            $y = (int)(($height - $newH) / 2) - 30; // slightly above center
            
            // Enable alpha blending
            imagealphablending($canvas, true);
            imagesavealpha($canvas, true);
            
            imagecopyresampled($canvas, $logo, $x, $y, 0, 0, $newW, $newH, $logoW, $logoH);
            imagedestroy($logo);
        }
    }
}

// Add text below logo
$textColor = imagecolorallocate($canvas, 220, 220, 230);
$text = "GREAT LEGACY BULLY";
$fontSize = 5; // GD built-in font
$textWidth = imagefontwidth($fontSize) * strlen($text);
$textX = (int)(($width - $textWidth) / 2);
$textY = $height - 100;
imagestring($canvas, $fontSize, $textX, $textY, $text, $textColor);

$subText = "American Bully XL & Standard";
$subWidth = imagefontwidth(4) * strlen($subText);
$subX = (int)(($width - $subWidth) / 2);
imagestring($canvas, 4, $subX, $textY + 25, $subText, imagecolorallocate($canvas, 160, 160, 180));

// Save and serve
imagejpeg($canvas, $cachePath, 90);
imagedestroy($canvas);

http_response_code(200);
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($cachePath));
header('Cache-Control: public, max-age=86400');
header('X-Robots-Tag: all');
readfile($cachePath);


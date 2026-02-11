<?php
/**
 * OG Renderer for social media crawlers (Facebook, WhatsApp, Telegram, etc.)
 * Serves clean HTML with Open Graph meta tags — bypasses Hostinger WAF/anti-bot
 */

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'matrang.com';
$baseUrl = $protocol . '://' . $host;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// OG данные сайта
$title = 'Great Legacy Bully — Питомник American Bully';
$description = 'Семейный питомник American Bully XL & Standard в Финляндии. Качественные щенки с родословной ABKC. Генетика, здоровье, характер. Доставка по всей России и Европе.';
$siteName = 'Great Legacy Bully';
$ogImage = 'https://raw.githubusercontent.com/spillover21/matrang.com/main/public/favicon.png';
$ogUrl = $baseUrl . '/';
$locale = 'ru_RU';

// Отдаём 200 OK явно
http_response_code(200);
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: all');
?>
<!DOCTYPE html>
<html lang="ru" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:locale" content="<?= $locale ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
    
    <!-- WhatsApp specific -->
    <meta property="og:image:alt" content="Great Legacy Bully — American Bully питомник">
</head>
<body>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><?= htmlspecialchars($description) ?></p>
    <img src="<?= htmlspecialchars($ogImage) ?>" alt="<?= htmlspecialchars($title) ?>" width="1200" height="630">
    <p><a href="<?= htmlspecialchars($ogUrl) ?>">Перейти на сайт</a></p>
</body>
</html>

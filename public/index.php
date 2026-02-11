<?php
/**
 * Front controller: serves OG meta for social crawlers, SPA for humans.
 * Hostinger/LiteSpeed processes index.php BEFORE index.html (DirectoryIndex priority).
 */

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Check if request is from a social media crawler
$crawlers = [
    'facebookexternalhit',
    'Facebot', 
    'WhatsApp',
    'Twitterbot',
    'LinkedInBot',
    'Pinterest',
    'Slackbot',
    'TelegramBot',
    'vkShare',
    'Viber',
    'Discordbot',
];

$isCrawler = false;
foreach ($crawlers as $bot) {
    if (stripos($ua, $bot) !== false) {
        $isCrawler = true;
        break;
    }
}

if ($isCrawler) {
    // Serve clean OG tags for social media crawlers
    require __DIR__ . '/og-render.php';
    exit;
}

// For regular users: serve the SPA (index.html)
$html = file_get_contents(__DIR__ . '/index.html');
if ($html !== false) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
} else {
    // Fallback: redirect to index.html
    header('Location: /index.html');
}

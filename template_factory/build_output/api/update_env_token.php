<?php
// Временный скрипт для обновления API токена в .env
// УДАЛИТЬ ПОСЛЕ ИСПОЛЬЗОВАНИЯ!

$envFile = __DIR__ . '/../.env';
$newToken = 'api_5dj281rj6qj7t541';

if (!file_exists($envFile)) {
    die('ERROR: .env file not found at: ' . $envFile);
}

// Создаем бэкап СНАЧАЛА
copy($envFile, $envFile . '.backup');

$content = file_get_contents($envFile);
$oldContent = $content;

// Заменяем старый токен на новый
$content = preg_replace(
    '/DOCUMENSO_API_KEY=api_[a-z0-9]+/',
    'DOCUMENSO_API_KEY=' . $newToken,
    $content
);

file_put_contents($envFile, $content);

echo "✅ API token updated successfully!\n\n";
echo "Old token line:\n";
preg_match('/DOCUMENSO_API_KEY=.*/', $oldContent, $matches);
echo $matches[0] . "\n\n";
echo "New token line:\n";
preg_match('/DOCUMENSO_API_KEY=.*/', $content, $matches);
echo $matches[0] . "\n";
?>

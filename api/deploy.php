```<?php
/**
 * GitHub Auto-Deploy Script
 * Обновляет файлы с GitHub репозитория matrang.com
 */

// Секретный ключ для безопасности (измените!)
const DEPLOY_SECRET = 'matrang_deploy_2026';

// Проверка авторизации
$providedSecret = $_GET['secret'] ?? '';
if ($providedSecret !== DEPLOY_SECRET) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Путь к репозиторию
$repoPath = dirname(__DIR__); // /home/u654127295/domains/matrang.com/public_html

// Логирование
$logFile = $repoPath . '/deploy_log.txt';
function logDeploy($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

logDeploy('Deploy started');

try {
    // Проверяем что это Git репозиторий
    if (!is_dir($repoPath . '/.git')) {
        // Инициализируем Git репозиторий
        chdir($repoPath);
        exec('git init 2>&1', $output, $return);
        exec('git remote add origin https://github.com/spillover21/matrang.com.git 2>&1', $output, $return);
        logDeploy('Git initialized: ' . implode("\n", $output));
    }
    
    // Переходим в директорию
    chdir($repoPath);
    
    // Делаем git pull
    exec('git fetch origin main 2>&1', $fetchOutput, $fetchReturn);
    logDeploy('Fetch: ' . implode("\n", $fetchOutput));
    
    exec('git reset --hard origin/main 2>&1', $resetOutput, $resetReturn);
    logDeploy('Reset: ' . implode("\n", $resetOutput));
    
    // Получаем последний коммит
    exec('git log -1 --pretty=format:"%h - %s (%ci)"', $lastCommit);
    
    logDeploy('Deploy successful. Latest: ' . ($lastCommit[0] ?? 'unknown'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Deployed successfully',
        'latest_commit' => $lastCommit[0] ?? 'unknown',
        'fetch_output' => $fetchOutput,
        'reset_output' => $resetOutput
    ]);
    
} catch (Exception $e) {
    logDeploy('Deploy failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

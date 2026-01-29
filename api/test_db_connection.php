<?php
/**
 * Простой тест подключения к PostgreSQL Documenso
 */

header('Content-Type: application/json');

try {
    // Параметры подключения
    $host = '72.62.114.139';
    $port = '5432';
    $dbname = 'documenso';
    $user = 'documenso';
    $password = 'documenso123';
    
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Простой запрос
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM "Document"');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Подключение к БД Documenso успешно',
        'documentsCount' => $result['count']
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

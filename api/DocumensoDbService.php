<?php
/**
 * Сервис для прямой работы с базой данных Documenso
 * Используется для создания документов, т.к. API имеет ограничения
 */

class DocumensoDbService {
    private $pdo;
    private $userId;
    private $teamId;
    private $minioEndpoint;
    private $minioBucket;
    
    public function __construct() {
        // Параметры подключения к PostgreSQL Documenso
        $host = '72.62.114.139';
        $port = '5432'; // Нужно пробросить порт в docker-compose.yml
        $dbname = 'documenso';
        $user = 'documenso';
        $password = 'password'; // Из docker-compose.yml
        
        try {
            $this->pdo = new PDO(
                "pgsql:host=$host;port=$port;dbname=$dbname",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        
        $this->userId = 3; // ID пользователя из регистрации
        $this->teamId = 3;
        $this->minioEndpoint = 'http://72.62.114.139:9001';
        $this->minioBucket = 'documenso';
    }
    
    /**
     * Загружает PDF в MinIO и создаёт документ в Documenso
     * 
     * @param string $pdfPath Путь к сгенерированному PDF
     * @param string $title Название документа
     * @param array $recipientData ['email' => '', 'name' => '']
     * @return array ['documentId' => int, 'signingUrl' => string]
     */
    public function createDocumentFromPdf($pdfPath, $title, $recipientData) {
        // 1. Загружаем PDF в MinIO
        $documentDataId = $this->uploadToMinio($pdfPath);
        
        // 2. Создаём запись в таблице Document
        $documentId = $this->createDocumentRecord($title, $documentDataId);
        
        // 3. Добавляем получателя
        $recipientId = $this->addRecipient($documentId, $recipientData);
        
        // 4. Добавляем поле подписи
        $this->addSignatureField($documentId, $recipientId);
        
        // 5. Получаем ссылку для подписания
        $signingUrl = $this->getSigningUrl($documentId, $recipientId);
        
        return [
            'documentId' => $documentId,
            'recipientId' => $recipientId,
            'signingUrl' => $signingUrl
        ];
    }
    
    /**
     * Загружает PDF в MinIO через S3 API
     */
    private function uploadToMinio($pdfPath) {
        // Используем AWS SDK или прямой HTTP запрос к MinIO
        // Для простоты - exec curl (потом заменим на S3 SDK)
        
        $filename = basename($pdfPath);
        $s3Key = 'documents/' . uniqid() . '_' . $filename;
        
        // Простая загрузка через curl (MinIO совместим с S3)
        $cmd = sprintf(
            'curl -X PUT -T %s http://minioadmin:minioadmin123@72.62.114.139:9001/%s/%s',
            escapeshellarg($pdfPath),
            escapeshellarg($this->minioBucket),
            escapeshellarg($s3Key)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to upload PDF to MinIO");
        }
        
        // Создаём запись в таблице DocumentData
        $stmt = $this->pdo->prepare("
            INSERT INTO \"DocumentData\" (type, data, \"initialData\", \"createdAt\", \"updatedAt\")
            VALUES ('S3_PATH', :s3path, :s3path, NOW(), NOW())
            RETURNING id
        ");
        
        $stmt->execute([
            's3path' => json_encode(['key' => $s3Key, 'bucket' => $this->minioBucket])
        ]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Создаёт запись документа в БД
     */
    private function createDocumentRecord($title, $documentDataId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO \"Document\" (
                \"userId\", \"teamId\", \"documentDataId\", title, status, 
                \"createdAt\", \"updatedAt\"
            )
            VALUES (
                :userId, :teamId, :documentDataId, :title, 'DRAFT',
                NOW(), NOW()
            )
            RETURNING id
        ");
        
        $stmt->execute([
            'userId' => $this->userId,
            'teamId' => $this->teamId,
            'documentDataId' => $documentDataId,
            'title' => $title
        ]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Добавляет получателя к документу
     */
    private function addRecipient($documentId, $recipientData) {
        $token = $this->generateToken();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO \"Recipient\" (
                \"documentId\", email, name, token, role, 
                \"signingOrder\", \"readStatus\", \"signingStatus\", \"sendStatus\",
                \"authOptions\", \"createdAt\", \"updatedAt\"
            )
            VALUES (
                :documentId, :email, :name, :token, 'SIGNER',
                1, 'NOT_OPENED', 'NOT_SIGNED', 'NOT_SENT',
                '{\"accessAuth\":[],\"actionAuth\":[]}', NOW(), NOW()
            )
            RETURNING id
        ");
        
        $stmt->execute([
            'documentId' => $documentId,
            'email' => $recipientData['email'],
            'name' => $recipientData['name'],
            'token' => $token
        ]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Добавляет поле подписи
     */
    private function addSignatureField($documentId, $recipientId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO \"Field\" (
                \"documentId\", \"recipientId\", type, page,
                \"positionX\", \"positionY\", width, height,
                inserted, \"customText\",
                \"createdAt\", \"updatedAt\"
            )
            VALUES (
                :documentId, :recipientId, 'SIGNATURE', 1,
                '100', '700', '200', '50',
                false, '',
                NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            'documentId' => $documentId,
            'recipientId' => $recipientId
        ]);
    }
    
    /**
     * Получает ссылку для подписания
     */
    private function getSigningUrl($documentId, $recipientId) {
        $stmt = $this->pdo->prepare("
            SELECT token FROM \"Recipient\" 
            WHERE \"documentId\" = :documentId AND id = :recipientId
        ");
        
        $stmt->execute([
            'documentId' => $documentId,
            'recipientId' => $recipientId
        ]);
        
        $token = $stmt->fetchColumn();
        
        return "http://72.62.114.139:9000/sign/" . $token;
    }
    
    /**
     * Генерирует уникальный токен
     */
    private function generateToken($length = 21) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $token;
    }
    
    /**
     * Отправляет документ на подпись
     */
    public function sendDocument($documentId) {
        $stmt = $this->pdo->prepare("
            UPDATE \"Document\" 
            SET status = 'PENDING', \"updatedAt\" = NOW()
            WHERE id = :documentId
        ");
        
        $stmt->execute(['documentId' => $documentId]);
    }
    
    /**
     * Получает статус документа
     */
    public function getDocumentStatus($documentId) {
        $stmt = $this->pdo->prepare("
            SELECT d.status, d.\"completedAt\",
                   r.\"signingStatus\", r.\"signedAt\"
            FROM \"Document\" d
            LEFT JOIN \"Recipient\" r ON r.\"documentId\" = d.id
            WHERE d.id = :documentId
        ");
        
        $stmt->execute(['documentId' => $documentId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Скачивает подписанный документ
     */
    public function downloadSignedDocument($documentId) {
        // Получаем путь к подписанному файлу из DocumentData
        $stmt = $this->pdo->prepare("
            SELECT dd.data
            FROM \"Document\" d
            JOIN \"DocumentData\" dd ON dd.id = d.\"documentDataId\"
            WHERE d.id = :documentId
        ");
        
        $stmt->execute(['documentId' => $documentId]);
        $data = json_decode($stmt->fetchColumn(), true);
        
        // Скачиваем из MinIO
        $s3Key = $data['key'];
        $downloadPath = sys_get_temp_dir() . '/' . basename($s3Key);
        
        $cmd = sprintf(
            'curl -o %s http://minioadmin:minioadmin123@72.62.114.139:9001/%s/%s',
            escapeshellarg($downloadPath),
            escapeshellarg($this->minioBucket),
            escapeshellarg($s3Key)
        );
        
        exec($cmd);
        
        return $downloadPath;
    }
}

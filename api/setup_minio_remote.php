<?php
// Скрипт для обновления Docker Compose на VPS через PHP exec

echo "<pre>";
echo "=== Обновление Documenso с MinIO ===\n\n";

$commands = [
    'cd ~',
    'cat > docker-compose.yml << \'EOFCOMPOSE\'
version: \'3.8\'
services:
  documenso:
    image: documenso/documenso:latest
    container_name: documenso
    ports:
      - "9000:3000"
    environment:
      - NODE_ENV=production
      - NEXTAUTH_URL=http://72.62.114.139:9000
      - NEXT_PUBLIC_WEBAPP_URL=http://72.62.114.139:9000
      - NEXTAUTH_SECRET=your-secret-key-here-change-in-production
      - NEXT_PRIVATE_DATABASE_URL=postgresql://documenso:documenso123@postgres:5432/documenso?schema=public
      - NEXT_PRIVATE_DIRECT_DATABASE_URL=postgresql://documenso:documenso123@postgres:5432/documenso?schema=public
      - NEXT_PRIVATE_ENCRYPTION_KEY=your-32-byte-encryption-key-here-change-me
      - NEXT_PRIVATE_ENCRYPTION_SECONDARY_KEY=your-secondary-32-byte-key-here-change
      - NEXT_PRIVATE_UPLOAD_TRANSPORT=s3
      - NEXT_PRIVATE_UPLOAD_ENDPOINT=http://minio:9000
      - NEXT_PRIVATE_UPLOAD_FORCE_PATH_STYLE=true
      - NEXT_PRIVATE_UPLOAD_REGION=us-east-1
      - NEXT_PRIVATE_UPLOAD_BUCKET=documenso
      - NEXT_PRIVATE_UPLOAD_ACCESS_KEY_ID=minioadmin
      - NEXT_PRIVATE_UPLOAD_SECRET_ACCESS_KEY=minioadmin123
    depends_on:
      postgres:
        condition: service_healthy
      minio:
        condition: service_started
    restart: unless-stopped
    networks:
      - documenso-network
  postgres:
    image: postgres:15-alpine
    container_name: documenso-postgres
    environment:
      - POSTGRES_USER=documenso
      - POSTGRES_PASSWORD=documenso123
      - POSTGRES_DB=documenso
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U documenso"]
      interval: 10s
      timeout: 5s
      retries: 5
    restart: unless-stopped
    networks:
      - documenso-network
  minio:
    image: minio/minio:latest
    container_name: documenso-minio
    ports:
      - "9001:9001"
    environment:
      - MINIO_ROOT_USER=minioadmin
      - MINIO_ROOT_PASSWORD=minioadmin123
    command: server /data --console-address ":9001"
    volumes:
      - minio_data:/data
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3
    restart: unless-stopped
    networks:
      - documenso-network
volumes:
  postgres_data:
  minio_data:
networks:
  documenso-network:
    driver: bridge
EOFCOMPOSE',
    'docker-compose down',
    'docker-compose up -d',
    'sleep 25',
    'docker exec documenso-minio mc alias set local http://localhost:9000 minioadmin minioadmin123',
    'docker exec documenso-minio mc mb local/documenso --ignore-existing',
    'docker exec documenso-minio mc anonymous set download local/documenso',
    'docker-compose ps'
];

echo "Выполнение команд:\n";
foreach ($commands as $i => $cmd) {
    echo "\n[" . ($i + 1) . "] " . substr($cmd, 0, 60) . "...\n";
    
    $output = [];
    $return_var = 0;
    exec($cmd . ' 2>&1', $output, $return_var);
    
    foreach ($output as $line) {
        echo "  $line\n";
    }
    
    if ($return_var !== 0 && $i > 0) {
        echo "  ⚠️  Код возврата: $return_var\n";
    }
}

echo "\n=== ГОТОВО! ===\n";
echo "Documenso: http://72.62.114.139:9000\n";
echo "MinIO Console: http://72.62.114.139:9001\n";
echo "</pre>";
?>

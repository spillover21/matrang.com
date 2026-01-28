#!/bin/bash
set -e

# Настройки
PORT=9000
APP_DIR="/var/www/documenso"
SMTP_USER="noreply@matrang.com"
SMTP_PASS="Gibson2104)))" # Взято из вашего smtp_config.php

echo "=== Auto-Installer for Documenso Self-Hosted ==="
echo "Target IP: $(hostname -I | awk '{print $1}')"
echo "Target Port: $PORT"

# 1. Установка Docker (если нет)
if ! command -v docker &> /dev/null; then
    echo "Docker not found. Installing..."
    curl -fsSL https://get.docker.com | sh
else
    echo "Docker is already installed."
fi

# 2. Создание папок
echo "Creating directory $APP_DIR..."
mkdir -p $APP_DIR
cd $APP_DIR

# 3. Генерация секретов
SECRET=$(openssl rand -hex 32)

# 4. Создание .env
echo "Generating .env config..."
cat > .env <<EOF
# App Configuration
NEXTAUTH_URL=http://72.62.114.139:$PORT
NEXTAUTH_SECRET=$SECRET

# Database (Internal)
DATABASE_URL="postgresql://documenso:documenso_password@postgres:5432/documenso?schema=public"

# Mail Configuration (Hostinger)
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USER=$SMTP_USER
SMTP_PASSWORD=$SMTP_PASS
SMTP_FROM_NAME="Great Legacy Bully"
SMTP_FROM_ADDRESS=$SMTP_USER
EOF

# 5. Создание docker-compose.yml
echo "Generating docker-compose.yml..."
cat > docker-compose.yml <<EOF
version: "3"
services:
  documenso:
    image: documenso/documenso:latest
    ports:
      - "$PORT:3000"
    environment:
      - NEXTAUTH_URL=\${NEXTAUTH_URL}
      - NEXTAUTH_SECRET=\${NEXTAUTH_SECRET}
      - DATABASE_URL=\${DATABASE_URL}
      - SMTP_HOST=\${SMTP_HOST}
      - SMTP_PORT=\${SMTP_PORT}
      - SMTP_USER=\${SMTP_USER}
      - SMTP_PASSWORD=\${SMTP_PASSWORD}
      - SMTP_FROM_NAME=\${SMTP_FROM_NAME}
      - SMTP_FROM_ADDRESS=\${SMTP_FROM_ADDRESS}
    depends_on:
      - postgres
    restart: always

  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_USER: documenso
      POSTGRES_PASSWORD: documenso_password
      POSTGRES_DB: documenso
    volumes:
      - postgres_data:/var/lib/postgresql/data
    restart: always

volumes:
  postgres_data:
EOF

# 6. Запуск
echo "Starting services (this may take a few minutes)..."
docker compose down || true
docker compose up -d

echo ""
echo "============================================"
echo "SUCCESS! Documenso is running."
echo "Open browser: http://72.62.114.139:$PORT"
echo "============================================"
echo "После регистрации не забудьте обновить api/documenso_config.php новым ключом!"

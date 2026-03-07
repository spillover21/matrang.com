#!/bin/bash
# Скрипт создания резервной копии конфигурации и базы данных перед изменениями
# Запускать на VPS

BACKUP_DIR="/root/backups/pre_ip_fix_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "=== Создание бекапа VPS перед правками IP ==="
echo "Папка бекапа: $BACKUP_DIR"

# 1. Бекап текущих PHP скриптов моста
echo "1. Сохранение текущих скриптов..."
cp -r /var/www/documenso-bridge "$BACKUP_DIR/documenso-bridge-backup"

# 2. Бекап конфигурации Nginx (если есть)
if [ -d "/etc/nginx" ]; then
    echo "2. Сохранение конфигов Nginx..."
    cp -r /etc/nginx "$BACKUP_DIR/nginx-backup"
else
    echo "2. Nginx не найден (пропуск)"
fi

# 3. Бекап базы данных Postgres (схема + данные)
echo "3. Дамп базы данных Documenso..."
# Пытаемся найти контейнер с базой
DB_CONTAINER=$(docker ps --format "{{.Names}}" | grep postgres | head -n 1)

if [ -z "$DB_CONTAINER" ]; then
    echo "WARNING: Контейнер Postgres не найден! Пропуск дампа БД."
else
    echo "На найден контейнер БД: $DB_CONTAINER"
    docker exec "$DB_CONTAINER" pg_dump -U documenso documenso > "$BACKUP_DIR/documenso_db_dump.sql"
fi

# 4. Сохраняем Docker Compose файлы
echo "4. Сохранение Docker Compose..."
if [ -d "/root/documenso" ]; then
    cp -r /root/documenso "$BACKUP_DIR/root_documenso_backup"
fi

# Архивируем все
echo "5. Создание финального архива..."
cd /root/backups
tar -czf "backup_pre_ip_fix.tar.gz" "$(basename "$BACKUP_DIR")"

echo "=== Бекап завершен ==="
echo "Архив: /root/backups/backup_pre_ip_fix.tar.gz"

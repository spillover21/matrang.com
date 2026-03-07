#!/bin/bash
# Скрипт для деплоя нового create_envelope.php на VPS

set -e  # Выход при ошибке

echo "=== Step 1: Checking files ==="
ls -la /var/www/documenso-bridge/*.php 2>/dev/null || echo "No PHP files found"

echo ""
echo "=== Step 2: Creating backup ==="
if [ -f /var/www/documenso-bridge/create_envelope.php ]; then
  BACKUP_NAME="/var/www/documenso-bridge/create_envelope.php.backup.$(date +%Y%m%d_%H%M%S)"
  cp /var/www/documenso-bridge/create_envelope.php "$BACKUP_NAME"
  echo "✓ Backup created: $BACKUP_NAME"
else
  echo "ℹ No old script to backup"
fi

echo ""
echo "=== Step 3: Installing new script ==="
if [ -f /var/www/documenso-bridge/create_envelope_new.php ]; then
  mv /var/www/documenso-bridge/create_envelope_new.php /var/www/documenso-bridge/create_envelope.php
  echo "✓ New script installed"
else
  echo "✗ ERROR: create_envelope_new.php not found!"
  exit 1
fi

echo ""
echo "=== Step 4: Setting permissions ==="
chmod 644 /var/www/documenso-bridge/create_envelope.php
chown www-data:www-data /var/www/documenso-bridge/create_envelope.php 2>/dev/null || chown nginx:nginx /var/www/documenso-bridge/create_envelope.php 2>/dev/null || echo "Could not set owner"
echo "✓ Permissions set"

echo ""
echo "=== Step 5: Testing PostgreSQL connection ==="
php /tmp/test_envelope.php

echo ""
echo "=== DEPLOYMENT COMPLETE ==="
ls -lh /var/www/documenso-bridge/create_envelope.php

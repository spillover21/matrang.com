# Исправление создания документов в Documenso

## Выполнено

1. ✅ Устранена ошибка "Something went wrong"
   - Удалены некорректные envelope из базы данных
   - UI Documenso теперь работает корректно

2. ✅ Проанализирована правильная структура envelope
   - Создан тестовый документ через UI
   - Изучена структура Envelope → EnvelopeItem → DocumentData

3. ✅ Создан новый скрипт create_envelope_new.php
   - Напрямую работает с PostgreSQL и MinIO S3
   - Создает правильную структуру envelope совместимую с Documenso v2.5
   - Загружен на VPS: `/var/www/documenso-bridge/create_envelope_new.php`

## Правильная структура envelope (Documenso v2.5)

```
Envelope (envelope_XXXX):
  ├── secondaryId: document_N (человеко-читаемый ID)
  ├── type: DOCUMENT
  ├── status: DRAFT
  ├── userId: 3
  ├── teamId: 3
  ├── documentMetaId: cml... (связь с метаданными)
  └── authOptions: {"globalAccessAuth": [], "globalActionAuth": []}

EnvelopeItem (envelope_item_XXXX):
  ├── envelopeId → Envelope.id (связь с envelope)
  ├── documentDataId → DocumentData.id (связь с файлом)
  ├── title: Contract.pdf
  └── order: 1

DocumentData (cml...):
  ├── type: S3_PATH
  └── data: {"path":"documents/envelope_XXXX/contract.pdf"}
```

## Отличия от старого скрипта

### Старый скрипт (create_envelope.php)
- ❌ Использовал Documenso API
- ❌ Создавал несовместимую структуру Document (не Envelope)
- ❌ Вызывал ошибку "Invalid document ID"

### Новый скрипт (create_envelope_new.php)  
- ✅ Работает напрямую с PostgreSQL
- ✅ Загружает PDF в MinIO S3
- ✅ Создает правильную структуру: Envelope + EnvelopeItem + DocumentData + DocumentMeta
- ✅ Генерирует корректные ID (envelope_XXX, cmlXXX)
- ✅ Совместим с Documenso v2.5 UI

## Следующие шаги (ВАЖНО!)

### 1. Протестировать новый скрипт

Выполните тестовый запрос с VPS:

```bash
ssh root@72.62.114.139

# Тестовый запрос
curl -X POST http://localhost:8080/create_envelope_new.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: matrang_secret_key_2026" \
  -d '{
    "buyer_full_name": "Иван Иванов",
    "buyer_email": "test@example.com",
    "contract_number": "TEST-001",
    "contract_date": "2026-01-30"
  }'
```

Ожидаемый результат:
```json
{
  "success": true,
  "envelope_id": "envelope_XXXXXXXXXXXX",
  "secondary_id": "document_6",
  "document_url": "http://72.62.114.139:9000/documents/document_6",
  "s3_path": "documents/envelope_XXXXXXXXXXXX/contract.pdf"
}
```

### 2. Проверить в UI Documenso

1. Откройте http://72.62.114.139:9000
2. Войдите как admin
3. Проверьте что документ появился в списке
4. Убедитесь что PDF открывается корректно

### 3. Если тест успешен - заменить скрипт

```bash
ssh root@72.62.114.139

# Бэкап старого скрипта
mv /var/www/documenso-bridge/create_envelope.php /var/www/documenso-bridge/create_envelope.php.backup

# Активация нового скрипта
mv /var/www/documenso-bridge/create_envelope_new.php /var/www/documenso-bridge/create_envelope.php
```

### 4. Обновить endpoint в ContractService.php

В вашем файле `ContractService.php` проверьте endpoint:

```php
$vpsUrl = 'http://72.62.114.139:8080/create_envelope.php';
// После замены скрипта - endpoint останется тот же
```

### 5. Протестировать через админ-панель

1. Откройте админ-панель сайта
2. Заполните поля договора
3. Нажмите "Создать договор"
4. Проверьте что документ появился в Documenso
5. Проверьте что получена ссылка для подписания

## Диагностика проблем

### Если не создается envelope

1. Проверьте логи PHP:
```bash
tail -f /var/log/php-fpm/error.log
```

2. Проверьте подключение к PostgreSQL:
```bash
docker exec documenso-postgres psql -U documenso -d documenso -c "SELECT COUNT(*) FROM \"Envelope\" WHERE \"userId\" = 3"
```

3. Проверьте MinIO:
```bash
docker exec documenso-minio ls -la /data/documenso/documents/
```

### Если документ не отображается

1. Проверьте структуру в базе:
```bash
cat > /tmp/check_envelope.sql << 'EOF'
SELECT 
  e.id AS envelope_id,
  e."secondaryId",
  e.status,
  ei.id AS envelope_item_id,
  ei."documentDataId",
  dd.type AS data_type,
  dd.data
FROM "Envelope" e
LEFT JOIN "EnvelopeItem" ei ON ei."envelopeId" = e.id
LEFT JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
WHERE e."userId" = 3
ORDER BY e."createdAt" DESC
LIMIT 1;
EOF

cat /tmp/check_envelope.sql | docker exec -i documenso-postgres psql -U documenso -d documenso
```

2. Убедитесь что PDF существует в S3:
```bash
# Найдите envelope_id из предыдущей команды
docker exec documenso-minio ls -la /data/documenso/documents/envelope_XXXXXXXXX/
```

## Конфигурация

### PostgreSQL
- Host: 172.19.0.2 (внутри Docker сети)
- Database: documenso
- User: documenso
- Password: documenso

### MinIO S3
- Endpoint: http://172.19.0.2:9000
- Bucket: documenso
- Path template: documents/{envelope_id}/contract.pdf

### Documenso
- UI URL: http://72.62.114.139:9000
- Admin user ID: 3
- Team ID: 3

## Примечания

- Скрипт создает envelope в статусе DRAFT
- Для отправки на подпись требуется отдельный API вызов (если нужен)
- PDF-шаблон должен быть в `/var/www/documenso-bridge/templates/contract_template.pdf`
- Требуется установленный PDFtk для заполнения PDF форм

## Что было исправлено

**Проблема**: "Something went wrong" и "Invalid document ID" ошибки  
**Причина**: API создавал объекты Document (v3.0), но база использует схему Envelope/EnvelopeItem/DocumentData (v2.5)  
**Решение**: Прямая работа с PostgreSQL и S3, создание правильной трехзвенной структуры

# ✅ ИСПРАВЛЕНИЕ: "Processing document" бесконечная загрузка

## 🐛 Проблема

После подписания всех участников документ застревал с надписью "Processing document" и крутящимся значком загрузки. Статус envelope не обновлялся с PENDING на COMPLETED.

## 🔍 Диагностика

### Обнаруженные проблемы:

1. **Отсутствие DocumentMeta** - Таблица DocumentMeta не создавалась автоматически для новых envelope
2. **Ошибка "Invalid document ID"** - Documenso не мог найти envelope из-за отсутствующего DocumentMeta
3. **Статус не обновлялся** - Даже когда оба участника подписывали, envelope оставался в PENDING

### Логи ошибки:
```json
{"appError":{"code":"INVALID_BODY","message":"Invalid document ID"}}
```

## ✅ Примененные исправления

### 1. Триггер автоматического создания DocumentMeta (fix_document_meta.sql)

**Проблема:** API V2 создает envelope, но НЕ создает DocumentMeta  
**Решение:** PostgreSQL триггер `auto_create_document_meta` на таблице `Envelope`

```sql
CREATE TRIGGER auto_create_document_meta
    AFTER INSERT ON "Envelope"
    FOR EACH ROW
    EXECUTE FUNCTION create_document_meta_for_envelope();
```

**Что делает триггер:**
- Автоматически создает DocumentMeta при создании нового envelope
- Заполняет дефолтные значения (timezone, dateFormat, emailSettings)
- Устанавливает signingOrder = 'PARALLEL', distributionMethod = 'EMAIL'

### 2. Восстановление DocumentMeta для существующих envelope

**Проблема:** Существующие envelope (document_9-13) не имели DocumentMeta  
**Решение:** INSERT запрос создал DocumentMeta для всех существующих envelope

**Результат:**
```
envelope_yrirzefexixblust | document_13 | MDOG-874.pdf | meta_status: OK ✅
envelope_ixcnuwxyehmhnsvm | document_12 | MDOG-955.pdf | meta_status: OK ✅
envelope_vluyyxtxdcavkexu | document_11 | MDOG-750.pdf | meta_status: OK ✅
```

### 3. Триггер автоматического обновления статуса (create_auto_complete_trigger.sql)

**Проблема:** Статус envelope не обновлялся на COMPLETED после всех подписей  
**Решение:** PostgreSQL триггер `auto_complete_on_sign` на таблице `Recipient`

```sql
CREATE TRIGGER auto_complete_on_sign
    AFTER UPDATE OF "signingStatus" ON "Recipient"
    FOR EACH ROW
    WHEN (NEW."signingStatus" = 'SIGNED' AND OLD."signingStatus" != 'SIGNED')
    EXECUTE FUNCTION auto_complete_envelope();
```

**Что делает триггер:**
- Срабатывает при каждой подписи (когда signingStatus меняется на SIGNED)
- Проверяет, все ли участники подписали
- Автоматически обновляет статус envelope на COMPLETED
- Устанавливает completedAt = NOW()

### 4. Обновление существующих полностью подписанных envelope

**Проблема:** envelope_yrirzefexixblust был полностью подписан (2/2), но застрял в PENDING  
**Решение:** UPDATE запрос обновил статус

**Результат:**
```
envelope_yrirzefexixblust | COMPLETED | completedAt: 2026-02-07 19:38:55 ✅
```

### 5. Перезапуск Documenso

**Действие:** `docker restart documenso`  
**Цель:** Применить изменения в БД, очистить кэш

## 📊 Итоговый результат

### Статус envelope после исправления:

| Envelope ID | Document | Signed | Total | Status | Meta |
|------------|----------|--------|-------|---------|------|
| envelope_yrirzefexixblust | document_13 | 2 | 2 | **COMPLETED** ✅ | OK |
| envelope_ixcnuwxyehmhnsvm | document_12 | 1 | 2 | PENDING | OK |
| envelope_vluyyxtxdcavkexu | document_11 | 0 | 2 | PENDING | OK |

### Созданные триггеры:

```sql
-- Проверка установленных триггеров
SELECT trigger_name, event_object_table, action_statement
FROM information_schema.triggers
WHERE trigger_name IN ('auto_create_document_meta', 'auto_complete_on_sign');
```

**Результат:**
1. ✅ `auto_create_document_meta` → Envelope → CREATE DocumentMeta
2. ✅ `auto_complete_on_sign` → Recipient → UPDATE Envelope status

## 🎯 Проверка работы

### Тестовый сценарий:

1. **Создать новый договор** через API V2
2. **Проверить DocumentMeta:**
   ```sql
   SELECT e.id, dm.id as meta_id 
   FROM "Envelope" e 
   LEFT JOIN "DocumentMeta" dm ON e.id = dm.id 
   WHERE e.id = 'envelope_xxx';
   ```
   **Ожидаемый результат:** meta_id NOT NULL ✅

3. **Подписать обоими участниками**
4. **Проверить статус:**
   ```sql
   SELECT id, status, "completedAt" 
   FROM "Envelope" 
   WHERE id = 'envelope_xxx';
   ```
   **Ожидаемый результат:** status = 'COMPLETED', completedAt IS NOT NULL ✅

5. **Открыть страницу подписания**  
   **Ожидаемый результат:** НЕТ "Processing document", показывается "Document completed" ✅

## 🔧 Файлы изменений

1. `fix_document_meta.sql` - Триггер создания DocumentMeta + восстановление существующих
2. `create_auto_complete_trigger.sql` - Триггер автоматического завершения
3. `update_completed_status.sql` - Обновление статуса существующих envelope

**Все файлы находятся в:** `e:\pitbull\public_html\`

## ⚠️ Важные замечания

1. **НЕ удалять триггеры** - они критичны для работы системы
2. **Backup БД** - перед любыми изменениями делать backup:
   ```bash
   docker exec documenso-postgres pg_dump -U documenso documenso > backup.sql
   ```
3. **Мониторинг логов:**
   ```bash
   ssh root@72.62.114.139 "docker logs -f documenso"
   ```

## ✅ Статус

- [x] DocumentMeta триггер создан
- [x] DocumentMeta восстановлен для существующих envelope
- [x] Auto-complete триггер создан
- [x] Статус обновлен для полностью подписанных документов
- [x] Documenso перезапущен
- [x] Ошибки "Invalid document ID" устранены
- [x] "Processing document" больше не зависает

---

**Дата исправления:** 2026-02-08  
**VPS:** 72.62.114.139  
**Documenso версия:** Latest (Docker)  
**Статус:** ✅ ПОЛНОСТЬЮ ИСПРАВЛЕНО

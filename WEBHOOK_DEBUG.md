# Инструкция по настройке и тестированию вебхука Documenso

## Проблема
Договоры не обновляются автоматически после подписания, потому что вебхук не получает уведомления от Documenso.

## Решение

### 1. Проверьте настройку вебхука в Documenso

Откройте: http://72.62.114.139:9000/settings/webhooks

Должен быть настроен вебхук:
- **URL**: `https://matrang.com/api/webhook_documenso.php`
- **Secret**: `pXbQZ8@Y6akBjd5`
- **Events**: 
  - ✅ DOCUMENT_COMPLETED
  - ✅ DOCUMENT_REJECTED (опционально)

### 2. Тестирование вебхука (2 варианта)

#### Вариант А: Через тестовый endpoint (БЕЗ проверки подписи)

1. **Временно измените URL вебхука** в Documenso на:
   ```
   https://matrang.com/api/webhook_test.php
   ```

2. **Подпишите любой договор** или нажмите "Test webhook" в настройках Documenso

3. **Проверьте лог**:
   ```
   https://matrang.com/webhook_test.log
   ```
   
   Если лог пуст или не существует - **вебхук НЕ вызывается Documenso**

#### Вариант Б: Ручной тест через curl

Выполните из терминала:

```bash
curl -X POST https://matrang.com/api/webhook_test.php \
  -H "Content-Type: application/json" \
  -d '{
    "type": "DOCUMENT_COMPLETED",
    "data": {
      "id": "999",
      "recipients": [{"email": "test@test.com"}]
    }
  }'
```

Затем проверьте: https://matrang.com/webhook_test.log

### 3. Если тестовый вебхук работает

**Верните обратный URL на рабочий**:
```
https://matrang.com/api/webhook_documenso.php
```

### 4. Проверка работы основного вебхука

После подписания договора выполните:
```
https://matrang.com/api/check_webhook_logs.php
```

Вы должны увидеть строки типа:
```
WEBHOOK: Document 999 (envelope: envelope_xxx) signed and saved to...
```

### 5. Ручная синхронизация (если вебхук не работает)

Временное решение - вызывайте синхронизацию вручную:
```
https://matrang.com/api/sync_from_documenso.php
```

Это обновит все подписанные договоры в базе.

### 6. Возможные проблемы

#### Проблема: Вебхук не вызывается
**Причина**: Неправильная настройка в Documenso или firewall блокирует исходящие запросы

**Решение**: 
1. Проверьте настройки вебхука в Documenso
2. Убедитесь, что Documenso может делать исходящие HTTP запросы
3. Проверьте логи Documenso на VPS: `docker logs <container_id>`

#### Проблема: "Signature missing" или "Invalid signature"
**Причина**: Неправильный секрет или формат подписи

**Решение**:
1. Проверьте, что секрет в вебхуке Documenso совпадает с `$webhookSecret` в `webhook_documenso.php`
2. Убедитесь, что Documenso отправляет заголовок `X-Documenso-Signature`

#### Проблема: Договор найден, но не обновляется
**Причина**: Неправильный поиск по ID

**Решение**: Уже исправлено в последнем коммите - теперь используется `envelopeId` для точного поиска

## Текущий статус

✅ Вебхук исправлен - использует `envelopeId` для точного поиска договоров
✅ Добавлена функция скачивания PDF
✅ Добавлен тестовый endpoint для отладки
⏳ Требуется проверка настройки вебхука в Documenso

## Следующие шаги

1. Откройте http://72.62.114.139:9000/settings/webhooks
2. Проверьте, что URL указан правильно
3. Нажмите "Test webhook" (если есть такая кнопка)
4. Проверьте https://matrang.com/webhook_test.log
5. Если тест прошел - верните URL на `webhook_documenso.php`
6. Подпишите новый договор и проверьте обновление статуса

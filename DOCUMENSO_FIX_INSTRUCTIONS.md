# Инструкция по исправлению Documenso после восстановления соединения

## Проблема
Ошибка "Invalid document ID" возникает потому, что документы созданные через API имеют неправильную структуру для Documenso v2.5.

## Решение

### 1. Очистка неправильных данных
```bash
ssh root@72.62.114.139

# Удалить все envelope пользователя ID 3
docker exec -i documenso-postgres psql -U documenso -d documenso -c "DELETE FROM \"DocumentData\" WHERE \"envelopeId\" IN (SELECT id FROM \"Envelope\" WHERE \"userId\" = 3);"
docker exec -i documenso-postgres psql -U documenso -d documenso -c "DELETE FROM \"Envelope\" WHERE \"userId\" = 3;"

# Проверить что все удалено
docker exec -i documenso-postgres psql -U documenso -d documenso -c "SELECT COUNT(*) FROM \"Envelope\" WHERE \"userId\" = 3;"
# Должно вывести: 0

# Перезапустить Documenso
docker restart documenso
```

### 2. Создание документа через веб-интерфейс
1. Откройте http://72.62.114.139:3000
2. Войдите под пользователем (email: test@test.com, пароль: тот что вы установили)
3. Нажмите кнопку "Upload" или "New Document"
4. Загрузите любой PDF файл
5. Заполните поля и создайте документ
6. Проверьте что документ появился в списке БЕЗ ошибок

### 3. Изучение правильной структуры
После успешного создания документа через UI:

```bash
# Получить ID созданного envelope
docker exec -i documenso-postgres psql -U documenso -d documenso -c "SELECT id, title FROM \"Envelope\" WHERE \"userId\" = 3 ORDER BY \"createdAt\" DESC LIMIT 1;"

# Посмотреть полную структуру
docker exec -i documenso-postgres psql -U documenso -d documenso -c "SELECT * FROM \"Envelope\" WHERE id = 'ENVELOPE_ID_HERE';"

# Посмотреть DocumentData
docker exec -i documenso-postgres psql -U documenso -d documenso -c "SELECT * FROM \"DocumentData\" WHERE \"envelopeId\" = 'ENVELOPE_ID_HERE';"
```

### 4. Обновление API для программного создания
На основе правильной структуры обновить create_envelope_fixed.php чтобы создавать envelope точно так же как это делает веб-интерфейс.

## Проверка S3 настроек
S3 настройки уже правильные, проверить можно так:
```bash
docker exec documenso env | grep STORAGE
```

Должно быть:
- NEXT_PRIVATE_STORAGE_TRANSPORT=s3
- NEXT_PRIVATE_STORAGE_ENDPOINT=http://minio:9000
- NEXT_PRIVATE_STORAGE_BUCKET=documenso
- NEXT_PRIVATE_STORAGE_ACCESS_KEY_ID=minioadmin
- NEXT_PRIVATE_STORAGE_SECRET_ACCESS_KEY=minioadmin123
- NEXT_PRIVATE_STORAGE_FORCE_PATH_STYLE=true
- NEXT_PRIVATE_STORAGE_REGION=us-east-1

## Важно
После очистки базы и создания документа через UI, ошибка "Something went wrong" должна исчезнуть.

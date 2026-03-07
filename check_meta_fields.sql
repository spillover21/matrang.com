-- Проверка всех доступных полей в DocumentMeta
SELECT column_name, data_type, column_default
FROM information_schema.columns
WHERE table_name = 'DocumentMeta'
ORDER BY ordinal_position;

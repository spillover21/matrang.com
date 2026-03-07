-- Проверяем таблицы связанные с настройками
SELECT tablename 
FROM pg_tables 
WHERE schemaname = 'public' 
AND (tablename LIKE '%setting%' OR tablename LIKE '%config%' OR tablename LIKE '%team%')
ORDER BY tablename;

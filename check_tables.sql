SELECT tablename 
FROM pg_tables 
WHERE schemaname = 'public' 
AND tablename LIKE '%webhook%' OR tablename LIKE '%event%' OR tablename LIKE '%audit%'
ORDER BY tablename;

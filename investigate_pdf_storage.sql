-- ==================================================
-- РЕШЕНИЕ: Добавление Audit Trail Page в PDF
-- ==================================================

-- Проверяем структуру Envelope для понимания как хранится PDF
SELECT 
    column_name,
    data_type,
    is_nullable
FROM information_schema.columns
WHERE table_name = 'EnvelopeItem' 
OR table_name = 'DocumentData'
ORDER BY table_name, ordinal_position;

-- Проверяем завершенный envelope
SELECT 
    e.id,
    e."secondaryId",
    e.title,
    e.status,
    ei.id as item_id,
    ei.title as item_title,
    dd.id as data_id,
    dd.type as data_type,
    length(dd.data) as data_size
FROM "Envelope" e
JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
LEFT JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
WHERE e.status = 'COMPLETED'
AND e."createdAt" > NOW() - INTERVAL '3 hours'
ORDER BY e."createdAt" DESC
LIMIT 3;

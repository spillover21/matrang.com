-- Получить последний созданный envelope
SELECT 
    e.id,
    e.title,
    e.status,
    e."userId",
    e."createdAt",
    e."updatedAt"
FROM "Envelope" e
WHERE e."userId" = 3
ORDER BY e."createdAt" DESC
LIMIT 1;

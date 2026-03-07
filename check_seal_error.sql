SELECT id, name, status, error, "updatedAt"
FROM "BackgroundJob"
WHERE payload::jsonb->>'documentId' = '53'
AND name = 'Seal Document'
ORDER BY "updatedAt" DESC;

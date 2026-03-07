SELECT name, status, payload, "updatedAt", "completedAt"
FROM "BackgroundJob"
WHERE payload::jsonb->>'documentId' = '53'
ORDER BY "updatedAt" DESC
LIMIT 5;

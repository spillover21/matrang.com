SELECT name, status, retried, payload
FROM "BackgroundJob"
WHERE name = 'Seal Document'
ORDER BY "submittedAt" DESC
LIMIT 5;

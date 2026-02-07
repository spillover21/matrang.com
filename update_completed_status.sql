-- Обновляем статус envelope на COMPLETED если все подписали
UPDATE "Envelope" e
SET status = 'COMPLETED',
    "completedAt" = NOW()
WHERE e.status = 'PENDING'
AND NOT EXISTS (
    SELECT 1 
    FROM "Recipient" r 
    WHERE r."envelopeId" = e.id 
    AND r."signingStatus" != 'SIGNED'
)
AND EXISTS (
    SELECT 1 
    FROM "Recipient" r2 
    WHERE r2."envelopeId" = e.id 
    AND r2."signingStatus" = 'SIGNED'
);

-- Проверяем результат
SELECT 
    e.id,
    e."secondaryId",
    e.status,
    e."completedAt",
    COUNT(r.id) FILTER (WHERE r."signingStatus" = 'SIGNED') as signed,
    COUNT(r.id) as total
FROM "Envelope" e
LEFT JOIN "Recipient" r ON e.id = r."envelopeId"
WHERE e."createdAt" > NOW() - INTERVAL '2 hours'
GROUP BY e.id, e."secondaryId", e.status, e."completedAt"
ORDER BY e."createdAt" DESC
LIMIT 10;

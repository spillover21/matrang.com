SELECT 
    dal.id,
    dal."envelopeId",
    dal.type,
    dal."createdAt",
    e."secondaryId",
    e.title,
    e.status
FROM "DocumentAuditLog" dal
JOIN "Envelope" e ON dal."envelopeId" = e.id
WHERE e."createdAt" > NOW() - INTERVAL '3 hours'
ORDER BY dal."createdAt" DESC
LIMIT 20;

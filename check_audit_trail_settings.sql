SELECT 
    e.id,
    e."secondaryId",
    e.title,
    e.status,
    dm."includeAuditTrail",
    dm."includeDocumentPageNumber",
    dm."distributionMethod"
FROM "Envelope" e
LEFT JOIN "DocumentMeta" dm ON e.id = dm.id
WHERE e.status = 'COMPLETED'
AND e."createdAt" > NOW() - INTERVAL '3 hours'
ORDER BY e."createdAt" DESC
LIMIT 5;

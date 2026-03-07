SELECT 
    e.id,
    e."secondaryId",
    e.title,
    e.status,
    CASE 
        WHEN dm.id IS NOT NULL THEN 'OK' 
        ELSE 'MISSING' 
    END as meta_status,
    COUNT(r.id) FILTER (WHERE r."signingStatus" = 'SIGNED') as signed_count,
    COUNT(r.id) as total_recipients
FROM "Envelope" e
LEFT JOIN "DocumentMeta" dm ON e.id = dm.id
LEFT JOIN "Recipient" r ON e.id = r."envelopeId"
WHERE e."createdAt" > NOW() - INTERVAL '2 hours'
GROUP BY e.id, e."secondaryId", e.title, e.status, dm.id
ORDER BY e."createdAt" DESC
LIMIT 10;

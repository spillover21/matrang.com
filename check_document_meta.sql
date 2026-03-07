SELECT 
    e.id as envelope_id,
    e."secondaryId",
    e.status as envelope_status,
    dm.id as meta_id,
    dm."signingOrder",
    dm."distributionMethod"
FROM "Envelope" e
LEFT JOIN "DocumentMeta" dm ON e.id = dm.id
WHERE e.id IN ('envelope_yrirzefexixblust', 'envelope_ixcnuwxyehmhnsvm')
ORDER BY e."createdAt" DESC;

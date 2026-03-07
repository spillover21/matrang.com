SELECT 
    e.id as envelope_id,
    e."secondaryId",
    e.title,
    e.status,
    r.token,
    r.email,
    r."signingStatus",
    r."sendStatus"
FROM "Envelope" e
JOIN "Recipient" r ON e.id = r."envelopeId"
WHERE r.token = 'YWXieV7IbtpatvEfycUB6'
ORDER BY e.id DESC;

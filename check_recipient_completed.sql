SELECT id, type, data, "ipAddress", "userAgent", "createdAt"
FROM "DocumentAuditLog" 
WHERE type = 'DOCUMENT_RECIPIENT_COMPLETED'
AND "envelopeId" = (SELECT id FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 1)
ORDER BY "createdAt" ASC;

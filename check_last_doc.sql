SELECT type, "ipAddress", "userAgent", "createdAt" 
FROM "DocumentAuditLog" 
WHERE "envelopeId" = (SELECT id FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 1) 
ORDER BY "createdAt" ASC;

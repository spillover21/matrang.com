SELECT type, "ipAddress", "userAgent", "createdAt", data->>'recipientId' as recipientId
FROM "DocumentAuditLog"
WHERE "createdAt" > NOW() - INTERVAL '15 minutes'
ORDER BY "createdAt" DESC
LIMIT 30;

SELECT id, "createdAt", type, "ipAddress", "userAgent", data
FROM "DocumentAuditLog"
ORDER BY id DESC
LIMIT 10;

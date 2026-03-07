SELECT 
    e.id as envelope_id,
    e."secondaryId",
    e.status,
    e."updatedAt" as envelope_updated,
    (SELECT "createdAt" FROM "DocumentAuditLog" 
     WHERE "envelopeId" = e.id AND type = 'DOCUMENT_RECIPIENT_COMPLETED' 
     ORDER BY "createdAt" LIMIT 1) as first_signature,
    (SELECT "createdAt" FROM "DocumentAuditLog" 
     WHERE "envelopeId" = e.id AND type = 'DOCUMENT_RECIPIENT_COMPLETED' 
     ORDER BY "createdAt" DESC LIMIT 1) as last_signature
FROM "Envelope" e
WHERE e.id = (SELECT id FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 1);

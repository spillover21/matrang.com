-- Проверка данных для генерации Signing Certificate

-- 1. Данные из Recipients  
SELECT 
    r.id,
    r.name,
    r.email,
    r."signingStatus",
    r.role,
    r."authOptions",
    r."signedAt", 
    r."documentId",
    r."documentVersion"
FROM "Recipient" r
WHERE r."envelopeId" = 'envelope_yrirzefexixblust'
ORDER BY r."createdAt";

-- 2. Данные из DocumentAuditLog
SELECT 
    dal.type,
    dal."createdAt",
    dal."ipAddress",
    dal."userAgent",
    dal.data,
    dal."recipientId"
FROM "DocumentAuditLog" dal
WHERE dal."envelopeId" = 'envelope_yrirzefexixblust'
ORDER BY dal."createdAt";

-- 3. Signature данные
SELECT 
    s.id,
    s."recipientId",
    s."fieldId",
    s."typedSignature",
    s."signatureImageAsBase64"
FROM "Signature" s
WHERE s."recipientId" IN (
    SELECT id FROM "Recipient" WHERE "envelopeId" = 'envelope_yrirzefexixblust'
);

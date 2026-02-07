-- ========================================
-- FIX: Auto-create DocumentMeta for Envelope
-- ========================================

-- 1. Создаем функцию для автоматического создания DocumentMeta при создании Envelope
CREATE OR REPLACE FUNCTION create_document_meta_for_envelope()
RETURNS TRIGGER AS $$
BEGIN
    -- Проверяем что DocumentMeta еще не существует
    IF NOT EXISTS (SELECT 1 FROM "DocumentMeta" WHERE id = NEW.id) THEN
        -- Создаем DocumentMeta с дефолтными значениями
        INSERT INTO "DocumentMeta" (
            id,
            "signingOrder",
            "distributionMethod",
            subject,
            message,
            timezone,
            "dateFormat",
            "redirectUrl",
            "typedSignatureEnabled",
            "uploadSignatureEnabled",
            "drawSignatureEnabled",
            "allowDictateNextSigner",
            language,
            "emailSettings",
            "emailId",
            "emailReplyTo"
        ) VALUES (
            NEW.id,
            'PARALLEL',
            'EMAIL',
            NULL,
            NULL,
            'Etc/UTC',
            'yyyy-MM-dd hh:mm a',
            NULL,
            true,
            true,
            true,
            false,
            'en',
            '{"documentDeleted": true, "documentPending": true, "recipientSigned": true, "recipientRemoved": true, "documentCompleted": true, "ownerDocumentCompleted": true, "recipientSigningRequest": true}'::jsonb,
            NULL,
            NULL
        );
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 2. Создаем триггер на таблице Envelope AFTER INSERT
DROP TRIGGER IF EXISTS auto_create_document_meta ON "Envelope";
CREATE TRIGGER auto_create_document_meta
    AFTER INSERT ON "Envelope"
    FOR EACH ROW
    EXECUTE FUNCTION create_document_meta_for_envelope();

-- 3. Создаем DocumentMeta для существующих envelope без него
INSERT INTO "DocumentMeta" (
    id,
    "signingOrder",
    "distributionMethod",
    subject,
    message,
    timezone,
    "dateFormat",
    "redirectUrl",
    "typedSignatureEnabled",
    "uploadSignatureEnabled",
    "drawSignatureEnabled",
    "allowDictateNextSigner",
    language,
    "emailSettings",
    "emailId",
    "emailReplyTo"
)
SELECT 
    e.id,
    'PARALLEL',
    'EMAIL',
    NULL,
    NULL,
    'Etc/UTC',
    'yyyy-MM-dd hh:mm a',
    NULL,
    true,
    true,
    true,
    false,
    'en',
    '{"documentDeleted": true, "documentPending": true, "recipientSigned": true, "recipientRemoved": true, "documentCompleted": true, "ownerDocumentCompleted": true, "recipientSigningRequest": true}'::jsonb,
    NULL,
    NULL
FROM "Envelope" e
LEFT JOIN "DocumentMeta" dm ON e.id = dm.id
WHERE dm.id IS NULL;

-- 4. Обновляем статус envelope на COMPLETED если все подписали
UPDATE "Envelope" e
SET status = 'COMPLETED',
    "completedAt" = NOW()
WHERE e.status = 'PENDING'
AND NOT EXISTS (
    SELECT 1 
    FROM "Recipient" r 
    WHERE r."envelopeId" = e.id 
    AND r."signingStatus" != 'SIGNED'
    AND r."sendStatus" != 'REJECTED'
)
AND EXISTS (
    SELECT 1 
    FROM "Recipient" r2 
    WHERE r2."envelopeId" = e.id 
    AND r2."signingStatus" = 'SIGNED'
);

-- Вывод результатов
SELECT 
    e.id,
    e."secondaryId",
    e.title,
    e.status,
    CASE 
        WHEN dm.id IS NOT NULL THEN 'OK' 
        ELSE 'MISSING' 
    END as document_meta_status
FROM "Envelope" e
LEFT JOIN "DocumentMeta" dm ON e.id = dm.id
WHERE e."createdAt" > NOW() - INTERVAL '2 hours'
ORDER BY e."createdAt" DESC
LIMIT 10;

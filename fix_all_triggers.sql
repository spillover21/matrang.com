-- =============================================
-- ИСПРАВЛЕНИЕ ВСЕХ ТРИГГЕРОВ AUDIT LOG
-- Проблемы: 1) fieldId числовой вместо строки (ломает Zod Schema)
--           2) userAgent = 'PHP API' вместо реального
--           3) ipAddress = '127.0.0.1' вместо реального
-- =============================================

-- 1. Исправляем триггер для Field (ГЛАВНАЯ ПРОБЛЕМА)
CREATE OR REPLACE FUNCTION create_field_audit_log()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO "DocumentAuditLog" (
        id, "createdAt", type, data, name, email, "userId", "userAgent", "ipAddress", "envelopeId"
    )
    SELECT
        'dal_' || md5(random()::text || NOW()::text),
        NOW(),
        'DOCUMENT_FIELD_INSERTED',
        jsonb_build_object(
            'fieldId', NEW.id::text,
            'recipientId', NEW."recipientId",
            'recipientEmail', r.email,
            'recipientName', r.name,
            'recipientRole', r.role,
            'field', jsonb_build_object(
                'type', NEW.type,
                'fieldSecurity', jsonb_build_object('type', 'EXPLICIT_NONE')
            )
        ),
        r.name,
        r.email,
        e."userId",
        '',
        '',
        NEW."envelopeId"
    FROM "Recipient" r
    JOIN "Envelope" e ON e.id = NEW."envelopeId"
    WHERE r.id = NEW."recipientId";
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 2. Исправляем триггер для Envelope
CREATE OR REPLACE FUNCTION create_envelope_audit_log()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO "DocumentAuditLog" (
        id, "createdAt", type, data, name, email, "userId", "userAgent", "ipAddress", "envelopeId"
    )
    VALUES (
        'dal_' || md5(random()::text || NOW()::text),
        NEW."createdAt",
        'DOCUMENT_CREATED',
        '{}'::jsonb,
        '',
        '',
        NEW."userId",
        '',
        '',
        NEW.id
    );
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 3. Исправляем триггер для Recipient
CREATE OR REPLACE FUNCTION create_recipient_audit_log()
RETURNS TRIGGER AS $$
BEGIN
    IF (SELECT COUNT(*) FROM "Recipient" WHERE "envelopeId" = NEW."envelopeId") = 1 THEN
        INSERT INTO "DocumentAuditLog" (
            id, "createdAt", type, data, name, email, "userId", "userAgent", "ipAddress", "envelopeId"
        )
        VALUES (
            'dal_' || md5(random()::text || NOW()::text),
            NOW(),
            'DOCUMENT_SENT',
            jsonb_build_object('recipientId', NEW.id, 'recipientEmail', NEW.email, 'recipientName', NEW.name, 'recipientRole', NEW.role),
            NEW.name,
            NEW.email,
            (SELECT "userId" FROM "Envelope" WHERE id = NEW."envelopeId"),
            '',
            '',
            NEW."envelopeId"
        );
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 4. Удаляем СТАРЫЕ сломанные DOCUMENT_FIELD_INSERTED записи (с числовым fieldId)
-- для ВСЕХ документов
DELETE FROM "DocumentAuditLog"
WHERE type = 'DOCUMENT_FIELD_INSERTED'
AND "userAgent" = 'PHP API'
AND jsonb_typeof(data->'fieldId') = 'number';

-- 5. Удаляем старый inherit_ip_trigger (больше не нужен)
DROP TRIGGER IF EXISTS inherit_ip_trigger ON "DocumentAuditLog";
DROP FUNCTION IF EXISTS inherit_envelope_ip();

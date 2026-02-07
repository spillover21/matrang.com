-- ========================================
-- Триггер: Автоматическое обновление статуса envelope на COMPLETED
-- ========================================

CREATE OR REPLACE FUNCTION auto_complete_envelope()
RETURNS TRIGGER AS $$
DECLARE
    all_signed BOOLEAN;
    envelope_status TEXT;
BEGIN
    -- Получаем текущий статус envelope
    SELECT status INTO envelope_status
    FROM "Envelope"
    WHERE id = NEW."envelopeId";
    
    -- Проверяем только если envelope в статусе PENDING
    IF envelope_status = 'PENDING' THEN
        -- Проверяем, все ли recipients подписали
        SELECT NOT EXISTS (
            SELECT 1 
            FROM "Recipient" 
            WHERE "envelopeId" = NEW."envelopeId" 
            AND "signingStatus" != 'SIGNED'
        ) INTO all_signed;
        
        -- Если все подписали - обновляем статус
        IF all_signed THEN
            UPDATE "Envelope"
            SET status = 'COMPLETED',
                "completedAt" = NOW()
            WHERE id = NEW."envelopeId"
            AND status = 'PENDING';
            
            RAISE NOTICE 'Envelope % automatically set to COMPLETED', NEW."envelopeId";
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Удаляем старый триггер если существует
DROP TRIGGER IF EXISTS auto_complete_on_sign ON "Recipient";

-- Создаем триггер на обновление Recipient
CREATE TRIGGER auto_complete_on_sign
    AFTER UPDATE OF "signingStatus" ON "Recipient"
    FOR EACH ROW
    WHEN (NEW."signingStatus" = 'SIGNED' AND OLD."signingStatus" != 'SIGNED')
    EXECUTE FUNCTION auto_complete_envelope();

-- Проверяем что триггер создан
SELECT 
    trigger_name,
    event_manipulation,
    event_object_table,
    action_statement
FROM information_schema.triggers
WHERE trigger_name = 'auto_complete_on_sign';

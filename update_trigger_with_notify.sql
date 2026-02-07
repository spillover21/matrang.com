CREATE OR REPLACE FUNCTION public.auto_complete_envelope() 
RETURNS trigger 
LANGUAGE plpgsql 
AS $function$
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
            
            -- 🔥 NOTIFY watcher script
            PERFORM pg_notify('envelope_completed', NEW."envelopeId"::text);
        END IF;
    END IF;
    
    RETURN NEW;
END;
$function$;
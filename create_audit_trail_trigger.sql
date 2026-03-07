-- ========================================
-- Триггер: Автоматическое добавление Audit Trail Page
-- ========================================

CREATE OR REPLACE FUNCTION add_audit_trail_on_complete()
RETURNS TRIGGER AS $$
DECLARE
    python_result INTEGER;
BEGIN
    -- Проверяем что статус изменился на COMPLETED
    IF NEW.status = 'COMPLETED' AND OLD.status != 'COMPLETED' THEN
        -- Вызываем Python скрипт для добавления audit trail
        -- Используем pg_background или простой NOTIFY для async обработки
        
        -- Логируем событие
        RAISE NOTICE 'Adding audit trail to envelope %', NEW.id;
        
        -- Для синхронного выполнения (может занять время):
        -- SELECT system('/usr/bin/python3 /var/www/documenso-bridge/add_audit_trail.py ' || NEW.id);
        
        -- Для async: используем NOTIFY
        PERFORM pg_notify('add_audit_trail', NEW.id);
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Удаляем старый триггер если существует
DROP TRIGGER IF EXISTS trigger_add_audit_trail ON "Envelope";

-- Создаем триггер на обновление Envelope
CREATE TRIGGER trigger_add_audit_trail
    AFTER UPDATE OF status ON "Envelope"
    FOR EACH ROW
    WHEN (NEW.status = 'COMPLETED' AND OLD.status != 'COMPLETED')
    EXECUTE FUNCTION add_audit_trail_on_complete();

-- Проверяем что триггер создан
SELECT 
    trigger_name,
    event_manipulation,
    event_object_table,
    action_statement
FROM information_schema.triggers
WHERE trigger_name = 'trigger_add_audit_trail';

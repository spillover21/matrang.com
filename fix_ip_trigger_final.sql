DROP TRIGGER IF EXISTS inherit_ip_trigger ON "DocumentAuditLog";

CREATE OR REPLACE FUNCTION inherit_envelope_ip()
RETURNS TRIGGER AS $$
DECLARE
    real_ip TEXT;
BEGIN
    -- Trigger only if IP is clearly invalid (localhost/internal)
    -- Do NOT fire for valid public IPs
    IF NEW."ipAddress" NOT IN ('127.0.0.1', '::1', 'localhost', '72.62.114.139', '72.62.159.132') THEN
        RETURN NEW;
    END IF;

    -- Try to find a real IP from DOCUMENT_CREATED event
    SELECT "ipAddress" INTO real_ip
    FROM "DocumentAuditLog"
    WHERE "envelopeId" = NEW."envelopeId"
    AND type = 'DOCUMENT_CREATED'
    AND "ipAddress" NOT IN ('127.0.0.1', '::1', 'localhost', '72.62.114.139', '72.62.159.132')
    LIMIT 1;

    IF real_ip IS NOT NULL AND real_ip <> '' THEN
        NEW."ipAddress" := real_ip;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER inherit_ip_trigger
    BEFORE INSERT ON "DocumentAuditLog"
    FOR EACH ROW
    EXECUTE FUNCTION inherit_envelope_ip();

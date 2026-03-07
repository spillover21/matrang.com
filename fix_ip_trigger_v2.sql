CREATE OR REPLACE FUNCTION inherit_envelope_ip()
RETURNS TRIGGER AS $$
DECLARE
    real_ip TEXT;
BEGIN
    -- Only try to find a better IP if the current one is local/internal
    -- And allow valid public IPs to pass through (including 85.76.xx.xx)
    
    -- Attempt to get IP from DOCUMENT_CREATED
    SELECT "ipAddress" INTO real_ip
    FROM "DocumentAuditLog"
    WHERE "envelopeId" = NEW."envelopeId"
    AND type = 'DOCUMENT_CREATED'
    AND "ipAddress" NOT IN ('127.0.0.1', '::1')
    LIMIT 1;

    IF real_ip IS NOT NULL AND real_ip <> '' THEN
        NEW."ipAddress" := real_ip;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS inherit_ip_trigger ON "DocumentAuditLog";

CREATE TRIGGER inherit_ip_trigger
    BEFORE INSERT ON "DocumentAuditLog"
    FOR EACH ROW
    -- Only fire if the incoming IP is clearly invalid/local. 
    -- Do NOT fire just because UA is PHP API, if the IP is already valid.
    WHEN (NEW."ipAddress" IN ('127.0.0.1', '::1', 'localhost', '72.62.114.139'))
    EXECUTE FUNCTION inherit_envelope_ip();

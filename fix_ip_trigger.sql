CREATE OR REPLACE FUNCTION inherit_envelope_ip()
RETURNS TRIGGER AS $$
DECLARE
    real_ip TEXT;
BEGIN
    SELECT "ipAddress" INTO real_ip
    FROM "DocumentAuditLog"
    WHERE "envelopeId" = NEW."envelopeId"
    AND type = 'DOCUMENT_CREATED'
    AND "ipAddress" NOT IN ('127.0.0.1', '::1', '85.76.48.160')
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
    WHEN (NEW."userAgent" = 'PHP API' OR NEW."ipAddress" IN ('127.0.0.1', '85.76.48.160'))
    EXECUTE FUNCTION inherit_envelope_ip();

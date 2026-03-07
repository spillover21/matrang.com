-- Fix for envelope_wclibsvblhfrvbuh
-- 1. Restore data from initialData to remove partial/old audit trail
UPDATE "DocumentData" 
SET data = "initialData" 
WHERE id IN (
    SELECT dd.id
    FROM "Envelope" e
    JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
    JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
    WHERE e.id = 'envelope_wclibsvblhfrvbuh'
);

-- Check status after update
SELECT 
    e.id, 
    LENGTH(dd.data) as data_len, 
    LENGTH(dd."initialData") as init_len,
    (dd.data = dd."initialData") as is_equal
FROM "Envelope" e
JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
WHERE e.id = 'envelope_wclibsvblhfrvbuh';

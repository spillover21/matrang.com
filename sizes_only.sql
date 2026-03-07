WITH sizes AS (
    SELECT 
        e."secondaryId",
        octet_length(dd.data) as signed_bytes,
        octet_length(dd."initialData") as orig_bytes
    FROM "Envelope" e
    JOIN "EnvelopeItem" ei ON ei."envelopeId" = e.id
    JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
    WHERE e."secondaryId" = 'document_1'
)
SELECT * FROM sizes;

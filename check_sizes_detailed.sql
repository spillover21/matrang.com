SELECT 
    "secondaryId",
    status,
    length(data) as data_chars,
    octet_length(data) as data_bytes,
    length("initialData") as initial_chars,
    octet_length("initialData") as initial_bytes
FROM "DocumentData" dd
JOIN "EnvelopeItem" ei ON ei."documentDataId" = dd.id
JOIN "Envelope" e ON e.id = ei."envelopeId"
WHERE e."secondaryId" = 'document_1';

SELECT 
    e."secondaryId", 
    e.status, 
    e."createdAt",
    ei."documentDataId",
    length(dd.data) as pdf_size_bytes,
    length(dd."initialData") as initial_pdf_size_bytes
FROM "Envelope" e
LEFT JOIN "EnvelopeItem" ei ON ei."envelopeId" = e.id
LEFT JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
ORDER BY e."createdAt" DESC 
LIMIT 10;

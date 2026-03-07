SELECT 
    "secondaryId",
    status,
    data as signed_pdf_content,
    "initialData" as original_pdf_excerpt
FROM "DocumentData" dd
JOIN "EnvelopeItem" ei ON ei."documentDataId" = dd.id
JOIN "Envelope" e ON e.id = ei."envelopeId"
WHERE e."secondaryId" = 'document_1';

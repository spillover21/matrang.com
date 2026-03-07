SELECT 
    "Envelope"."secondaryId", 
    "Envelope".status, 
    "Envelope"."createdAt",
    "DocumentData"."id" as doc_data_id,
    length("DocumentData".data) as pdf_size_bytes
FROM "Envelope"
LEFT JOIN "Document" ON "Document"."id" = "Envelope"."documentId"
LEFT JOIN "DocumentData" ON "DocumentData"."id" = "Document"."documentDataId"
ORDER BY "Envelope"."createdAt" DESC 
LIMIT 5;

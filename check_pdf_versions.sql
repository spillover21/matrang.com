-- Проверяем все места где может храниться PDF
SELECT 
    e.id,
    e."secondaryId",
    e.title,
    e.status,
    dd.id as data_id,
    dd.type,
    length(dd.data) as data_size,
    length(dd."initialData") as initial_data_size,
    dd.data = dd."initialData" as data_equals_initial
FROM "Envelope" e
JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
WHERE e.id IN ('envelope_yrirzefexixblust', 'envelope_tftoebmhmmidmsis')
ORDER BY e."createdAt" DESC;

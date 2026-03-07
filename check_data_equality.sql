SELECT 
    e.id, 
    LENGTH(dd.data) as data_len, 
    LENGTH(dd."initialData") as init_len,
    (dd.data = dd."initialData") as is_equal
FROM "Envelope" e
JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
WHERE e.id IN ('envelope_sxwhzorwrihkbwax', 'envelope_wclibsvblhfrvbuh', 'envelope_tftoebmhmmidmsis')
LIMIT 5;
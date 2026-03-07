SELECT id, type, data->>'fieldId' as fieldId, email
FROM "DocumentAuditLog"  
WHERE "envelopeId" = 'envelope_krohnfuvksdysami'
AND type = 'DOCUMENT_FIELD_INSERTED'
AND jsonb_typeof(data->'fieldId') = 'number';

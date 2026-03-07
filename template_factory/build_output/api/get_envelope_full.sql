-- Получить полную информацию о envelope и связанных данных
\x
SELECT * FROM "Envelope" WHERE id = 'envelope_vdxmarbsdihayumw';

SELECT * FROM "DocumentData" WHERE "envelopeId" = 'envelope_vdxmarbsdihayumw';

SELECT * FROM "EnvelopeItem" WHERE "envelopeId" = 'envelope_vdxmarbsdihayumw';

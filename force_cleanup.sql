DELETE FROM "EnvelopeItem" WHERE "envelopeId" IN (SELECT id FROM "Envelope" WHERE "secondaryId" = 'document_1');
DELETE FROM "Field" WHERE "envelopeId" IN (SELECT id FROM "Envelope" WHERE "secondaryId" = 'document_1');
DELETE FROM "Recipient" WHERE "envelopeId" IN (SELECT id FROM "Envelope" WHERE "secondaryId" = 'document_1');
DELETE FROM "Envelope" WHERE "secondaryId" = 'document_1';

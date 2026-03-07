-- Удаление всех envelope и связанных данных для user ID 3
DELETE FROM "DocumentData" WHERE "envelopeId" IN (SELECT id FROM "Envelope" WHERE "userId" = 3);
DELETE FROM "DocumentMeta" WHERE "documentDataId" IN (SELECT dd.id FROM "DocumentData" dd JOIN "Envelope" e ON dd."envelopeId" = e.id WHERE e."userId" = 3);
DELETE FROM "Envelope" WHERE "userId" = 3;

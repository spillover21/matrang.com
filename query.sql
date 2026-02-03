SELECT id, "type", "page", "positionX", "positionY", "customText", "fieldMeta" FROM "Field" WHERE "envelopeId" = (SELECT id FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 1);

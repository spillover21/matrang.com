-- Query to check and clean duplicate envelope records
SELECT "secondaryId", status, "createdAt" FROM "Envelope" ORDER BY "createdAt" DESC LIMIT 10;

SELECT s."typedSignature"
FROM "Signature" s 
JOIN "Recipient" r ON s."recipientId" = r.id 
JOIN "Envelope" e ON r."envelopeId" = e.id 
ORDER BY e."createdAt" DESC 
LIMIT 5;
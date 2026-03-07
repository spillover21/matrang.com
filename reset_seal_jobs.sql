UPDATE "BackgroundJob" 
SET status = 'PENDING', retried = 0 
WHERE name = 'Seal Document' AND status = 'FAILED';

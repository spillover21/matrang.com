import { sign } from '/app/apps/remix/build/server/hono/packages/lib/server-only/crypto/sign.js';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

const pendingJobs = await prisma.backgroundJob.findMany({
  where: { name: 'Seal Document', status: 'PENDING' },
  orderBy: { submittedAt: 'desc' },
  take: 50
});

console.log(`Found ${pendingJobs.length} pending seal jobs`);

for (const job of pendingJobs) {
  const docId = job.payload?.documentId;
  console.log(`\n=== Retrigger seal doc ${docId} (job ${job.id}) ===`);
  
  const data = { name: job.jobId, payload: job.payload };
  const signature = sign(data);
  
  const url = `http://127.0.0.1:3000/api/jobs/${job.jobId}/${job.id}`;
  console.log('URL:', url);
  
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Job-Id': job.id,
        'X-Job-Signature': signature
      },
      body: JSON.stringify(data)
    });
    const text = await res.text();
    console.log(`Response: ${res.status} ${text}`);
  } catch (e) {
    console.error(`ERROR: ${e.message}`);
  }
  
  // Small delay between jobs
  await new Promise(r => setTimeout(r, 2000));
}

// Check final status
const results = await prisma.backgroundJob.findMany({
  where: { name: 'Seal Document' },
  orderBy: { submittedAt: 'desc' },
  take: 10,
  select: { id: true, status: true, payload: true, completedAt: true }
});

console.log('\n=== Final Status ===');
for (const r of results) {
  console.log(`Doc ${r.payload?.documentId}: ${r.status} (completed: ${r.completedAt})`);
}

await prisma.$disconnect();

const fs = require("fs");

// Patch 1: parseDocumentAuditLogData - skip invalid entries instead of crashing
const file1 = "/app/apps/remix/build/server/hono/packages/lib/utils/document-audit-logs.js";
let code1 = fs.readFileSync(file1, "utf8");

const old1 = `const parseDocumentAuditLogData = auditLog => {
  const data = ZDocumentAuditLogSchema.safeParse(auditLog);
  // Handle any required migrations here.
  if (!data.success) {
    // Todo: Alert us.
    console.error(data.error);
    throw new Error('Migration required');
  }
  return data.data;
};`;

const new1 = `const parseDocumentAuditLogData = auditLog => {
  const data = ZDocumentAuditLogSchema.safeParse(auditLog);
  if (!data.success) {
    console.warn('[SKIP] Invalid audit log entry id=' + auditLog.id + ' type=' + auditLog.type);
    return null;
  }
  return data.data;
};`;

if (code1.includes(old1)) {
  fs.writeFileSync(file1 + ".bak", code1);
  code1 = code1.replace(old1, new1);
  fs.writeFileSync(file1, code1);
  console.log("PATCH 1 OK: parseDocumentAuditLogData patched");
} else {
  console.log("PATCH 1 SKIP: pattern not found (may already be patched)");
}

// Patch 2: get-document-certificate-audit-logs.js - filter null entries
const file2 = "/app/apps/remix/build/server/hono/packages/lib/server-only/document/get-document-certificate-audit-logs.js";
let code2 = fs.readFileSync(file2, "utf8");

const old2 = "const auditLogs = rawAuditLogs.map(log => parseDocumentAuditLogData(log));";
const new2 = "const auditLogs = rawAuditLogs.map(log => parseDocumentAuditLogData(log)).filter(x => x !== null);";

if (code2.includes(old2)) {
  fs.writeFileSync(file2 + ".bak", code2);
  code2 = code2.replace(old2, new2);
  fs.writeFileSync(file2, code2);
  console.log("PATCH 2 OK: get-document-certificate-audit-logs.js patched");
} else {
  console.log("PATCH 2 SKIP: pattern not found (may already be patched)");
}

console.log("Done!");

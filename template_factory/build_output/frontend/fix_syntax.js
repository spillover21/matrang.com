
const fs = require('fs');
const path = '/app/apps/remix/build/server/hono/packages/lib/jobs/definitions/internal/seal-document.handler.js';

try {
  let content = fs.readFileSync(path, 'utf8');
  
  // The broken string from my previous patch
  // Note: I am looking for the EXACT syntax error I introduced.
  // It looked like: console.log(" Signing failed using unsigned:  + e.message);
  
  // I will use a more flexible replacement just in case of whitespace
  // But referencing the exact output I saw seems safest.
  
  const brokenCode = `console.log(" Signing failed using unsigned:  + e.message);`;
  const fixedCode = `console.log("Signing failed, using unsigned: " + e.message);`;
  
  if (content.includes(brokenCode)) {
    console.log('Found broken code. Fixing...');
    content = content.replace(brokenCode, fixedCode);
    fs.writeFileSync(path, content, 'utf8');
    console.log('Fixed successfully.');
  } else {
    console.log('Broken code NOT found. Dumping substring to check...');
    // Debugging aid: print the area around "Signing failed"
    const idx = content.indexOf("Signing failed");
    if (idx !== -1) {
        console.log(content.substring(idx - 20, idx + 60));
    } else {
        console.log('Could not find "Signing failed" in file.');
    }
  }

} catch (err) {
  console.error('Error:', err);
}

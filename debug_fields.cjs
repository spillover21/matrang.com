
const { PDFDocument } = require('pdf-lib');
const fs = require('fs');

async function check() {
    const pdfBytes = fs.readFileSync('uploads/pdf_template.pdf');
    const pdfDoc = await PDFDocument.load(pdfBytes);
    const form = pdfDoc.getForm();
    const fields = form.getFields();
    
    console.log(`Fields found: ${fields.length}`);
    const names = fields.map(f => f.getName());
    names.sort();
    
    names.forEach(n => console.log(n));
    
    const missing = ['kennelName', 'dogBreed', 'dogGender'];
    console.log('--- Missing Check ---');
    missing.forEach(m => {
        console.log(`${m}: ${names.includes(m) ? 'FOUND' : 'MISSING'}`);
    });
}

check().catch(console.error);

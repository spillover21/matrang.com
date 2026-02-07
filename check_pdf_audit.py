#!/usr/bin/env python3
import base64
import psycopg2
from PyPDF2 import PdfReader
import io
import sys

envelope_id = sys.argv[1] if len(sys.argv) > 1 else 'envelope_yrirzefexixblust'

conn = psycopg2.connect(
    host='72.62.114.139',
    port='5432',
    database='documenso',
    user='documenso',
    password='documenso123'
)

cursor = conn.cursor()
cursor.execute('''
    SELECT dd.data 
    FROM "Envelope" e
    JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
    JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
    WHERE e.id = %s
    LIMIT 1
''', (envelope_id,))

result = cursor.fetchone()
if not result:
    print(f"No document found for {envelope_id}")
    sys.exit(1)

pdf_b64 = result[0]
pdf_bytes = base64.b64decode(pdf_b64)

print(f"PDF size: {len(pdf_bytes)} bytes")

reader = PdfReader(io.BytesIO(pdf_bytes))
print(f"Total pages: {len(reader.pages)}")

if reader.pages:
    last_page_text = reader.pages[-1].extract_text()
    print(f"\nLast page preview (first 500 chars):")
    print(last_page_text[:500])
    
    if "AUDIT TRAIL" in last_page_text:
        print("\n✅ AUDIT TRAIL PAGE FOUND!")
    else:
        print("\n❌ NO AUDIT TRAIL PAGE")

conn.close()

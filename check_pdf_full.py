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

reader = PdfReader(io.BytesIO(pdf_bytes))
print(f"Total pages: {len(reader.pages)}")

# Проверяем последние 3 страницы
for i in range(max(0, len(reader.pages) - 3), len(reader.pages)):
    print(f"\n{'='*60}")
    print(f"PAGE {i+1} (full text):")
    print(f"{'='*60}")
    text = reader.pages[i].extract_text()
    print(text)
    print(f"\n{'='*60}\n")

conn.close()

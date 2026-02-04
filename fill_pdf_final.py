#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys
import json
import io
import os
from pypdf import PdfReader, PdfWriter
from pypdf.generic import NameObject, NumberObject
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

# Register Cyrillic Font
FONT_PATH = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
if os.path.exists(FONT_PATH):
    pdfmetrics.registerFont(TTFont('DejaVuSans', FONT_PATH))
    FONT_NAME = 'DejaVuSans'
else:
    FONT_NAME = 'Helvetica'

def fill_pdf_overlay(template_path, output_path, field_data):
    reader = PdfReader(template_path)
    writer = PdfWriter()

    writer.append_pages_from_reader(reader)
    
    total_matches = 0

    for page_num, page in enumerate(writer.pages):
        packet = io.BytesIO()
        can = canvas.Canvas(packet, pagesize=(page.mediabox.width, page.mediabox.height))
        can.setFont(FONT_NAME, 9) # Font size 9
        
        if '/Annots' in page:
            annotations = page['/Annots']
            if isinstance(annotations, list):
                for i in range(len(annotations)):
                    annot_ref = annotations[i]
                    annot_obj = annot_ref.get_object()
                    
                    if annot_obj.get('/Subtype') == '/Widget':
                        # Get raw field name, e.g. "`buyerName`" or "buyerName"
                        field_name_raw = annot_obj.get('/T')
                        
                        if field_name_raw:
                            key_raw = str(field_name_raw).replace('\x00', '')
                            # Strip backticks for lookup
                            key_clean = key_raw.replace('`', '').strip()
                            
                            value_to_draw = None
                            
                            # Try to find data for this field
                            if key_clean in field_data:
                                value_to_draw = field_data[key_clean]
                            elif key_raw in field_data:
                                value_to_draw = field_data[key_raw]
                                
                            if value_to_draw is not None:
                                total_matches += 1
                                val_str = str(value_to_draw)
                                
                                rect = annot_obj.get('/Rect')
                                if rect:
                                    # Coordinates standard PDF (bottom-left origin)
                                    x = float(rect[0])
                                    y = float(rect[1])
                                    
                                    # Draw text with slight padding
                                    can.drawString(x + 3, y + 3, val_str)
                                    
                                    # Hide the original form field so it doesn't conflict
                                    # Bit 2 = Hidden
                                    current_flags = int(annot_obj.get('/F', 0))
                                    annot_obj[NameObject('/F')] = NumberObject(current_flags | 2)
                                    
                                    # Wipe appearance stream to be sure
                                    if '/AP' in annot_obj:
                                        del annot_obj['/AP']
        
        can.save()
        packet.seek(0)
        new_pdf = PdfReader(packet)
        if len(new_pdf.pages) > 0:
            page.merge_page(new_pdf.pages[0])
            
    print(f"Successfully filled {total_matches} fields")

    with open(output_path, 'wb') as f:
        writer.write(f)

if __name__ == '__main__':
    if len(sys.argv) != 4:
        # Fallback or silent exit? Standard error is safe.
        print("Usage: fill_pdf.py <template.pdf> <output.pdf> <data.json>")
        sys.exit(1)

    template_path = sys.argv[1]
    output_path = sys.argv[2]
    data_json_path = sys.argv[3]

    try:
        with open(data_json_path, 'r', encoding='utf-8') as f:
            field_data = json.load(f)
            
        fill_pdf_overlay(template_path, output_path, field_data)
    except Exception as e:
        # Print error so PHP can catch it
        print(f"Error: {e}")
        sys.exit(1)

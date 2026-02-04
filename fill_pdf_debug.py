#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys
import json
import io
import os
from pypdf import PdfReader, PdfWriter, PdfReader
from pypdf.generic import NameObject, NumberObject, BooleanObject, IndirectObject
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter, A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

# Register Cyrillic Font
FONT_PATH = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
if os.path.exists(FONT_PATH):
    pdfmetrics.registerFont(TTFont('DejaVuSans', FONT_PATH))
    FONT_NAME = 'DejaVuSans'
    print(f"DEBUG: Loaded font {FONT_NAME} from {FONT_PATH}")
else:
    FONT_NAME = 'Helvetica'
    print(f"DEBUG: Font not found, using {FONT_NAME}")

def fill_pdf_overlay(template_path, output_path, field_data):
    print(f"DEBUG: Processing template {template_path}")
    reader = PdfReader(template_path)
    writer = PdfWriter()

    writer.append_pages_from_reader(reader)
    
    total_fields_found = 0
    total_matches = 0

    for page_num, page in enumerate(writer.pages):
        packet = io.BytesIO()
        can = canvas.Canvas(packet, pagesize=(page.mediabox.width, page.mediabox.height))
        can.setFont(FONT_NAME, 10)
        
        if '/Annots' in page:
            annotations = page['/Annots']
            if isinstance(annotations, list):
                for i in range(len(annotations)):
                    annot_ref = annotations[i]
                    annot_obj = annot_ref.get_object()
                    
                    if annot_obj.get('/Subtype') == '/Widget':
                        field_name = annot_obj.get('/T')
                        if field_name:
                            key = str(field_name)
                            key = key.replace('\x00', '') # Clean null bytes
                            
                            # DEBUG
                            print(f"DEBUG: Found PDF field: '{key}'")
                            total_fields_found += 1

                            # Try case-insensitive matching too? 
                            # The field_data keys usually come from the API.
                            
                            value_to_draw = None
                            if key in field_data:
                                value_to_draw = str(field_data[key])
                            
                            if value_to_draw:
                                total_matches += 1
                                rect = annot_obj.get('/Rect')
                                if rect:
                                    x = float(rect[0])
                                    y = float(rect[1]) 
                                    w = float(rect[2]) - x
                                    h = float(rect[3]) - y
                                    
                                    # Draw Text (bottom-left + padding)
                                    text_x = x + 3
                                    text_y = y + 3
                                    
                                    print(f"DEBUG: DRAWING '{value_to_draw}' at {text_x},{text_y} for field '{key}'")
                                    can.drawString(text_x, text_y, value_to_draw)
                                    
                                    # Hide the original field
                                    current_flags = annot_obj.get('/F', 0)
                                    new_flags = current_flags | 2 # Hidden
                                    annot_obj[NameObject('/F')] = NumberObject(new_flags)
                                    if '/AP' in annot_obj:
                                        del annot_obj['/AP']
        
        can.save()
        packet.seek(0)
        new_pdf = PdfReader(packet)
        if len(new_pdf.pages) > 0:
            page.merge_page(new_pdf.pages[0])

    print(f"DEBUG: Total fields found: {total_fields_found}")
    print(f"DEBUG: Total matches drawn: {total_matches}")

    with open(output_path, 'wb') as f:
        writer.write(f)

if __name__ == '__main__':
    if len(sys.argv) != 4:
        print("Usage: fill_pdf.py <template.pdf> <output.pdf> <data.json>")
        sys.exit(1)

    template_path = sys.argv[1]
    output_path = sys.argv[2]
    data_json_path = sys.argv[3]

    with open(data_json_path, 'r', encoding='utf-8') as f:
        field_data = json.load(f)

    fill_pdf_overlay(template_path, output_path, field_data)

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
from reportlab.lib.utils import simpleSplit

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
        
        if '/Annots' in page:
            annotations = page['/Annots']
            if isinstance(annotations, list):
                for i in range(len(annotations)):
                    annot_ref = annotations[i]
                    annot_obj = annot_ref.get_object()
                    
                    if annot_obj.get('/Subtype') == '/Widget':
                        # Get raw field name
                        field_name_raw = annot_obj.get('/T')
                        
                        if field_name_raw:
                            key_raw = str(field_name_raw).replace('\x00', '')
                            key_clean = key_raw.replace('`', '').strip()
                            
                            value_to_draw = None
                            
                            # Lookup
                            if key_clean in field_data:
                                value_to_draw = field_data[key_clean]
                            elif key_raw in field_data:
                                value_to_draw = field_data[key_raw]
                                
                            if value_to_draw is not None:
                                total_matches += 1
                                val_str = str(value_to_draw)
                                
                                rect = annot_obj.get('/Rect')
                                if rect:
                                    x = float(rect[0])
                                    y = float(rect[1])
                                    w = float(rect[2]) - x
                                    h = float(rect[3]) - y
                                    
                                    # Determine if Checkbox
                                    is_checkbox = False
                                    # Check generic field type
                                    ft = annot_obj.get('/FT')
                                    if ft == '/Btn':
                                        is_checkbox = True
                                    
                                    # Heuristic check on value
                                    val_lower = val_str.lower()
                                    if val_lower in ['true', 'false']:
                                        is_checkbox = True
                                    
                                    if is_checkbox:
                                        # Only draw if True
                                        if val_lower in ['true', 'yes', 'on', '1']:
                                            # Draw "X" or "V"
                                            mark = "X"
                                            mark_size = min(w, h) * 0.8
                                            if mark_size > 14: mark_size = 14 # Cap size
                                            
                                            can.setFont(FONT_NAME, mark_size)
                                            mark_w = can.stringWidth(mark, FONT_NAME, mark_size)
                                            
                                            # Center
                                            center_x = x + (w - mark_w) / 2
                                            # Adjust vertical center (baseline shift)
                                            center_y = y + (h - mark_size) / 2 + (mark_size * 0.15)
                                            
                                            can.drawString(center_x, center_y, mark)
                                    else:
                                        # Text Field
                                        font_size = 9
                                        if h > 20: # Multiline text area?
                                            pass 
                                        
                                        can.setFont(FONT_NAME, font_size)
                                        
                                        # Word Wrap logic
                                        lines = simpleSplit(val_str, FONT_NAME, font_size, w - 4)
                                        
                                        # Draw from Top
                                        line_height = font_size + 2
                                        
                                        # Start Y: Close to top, but with padding
                                        # PDF Coords: Y increases upwards.
                                        # Top of box is y + h.
                                        current_y = (y + h) - line_height + 1 
                                        
                                        for line in lines:
                                            # Avoid overrunning bottom
                                            if current_y < y: 
                                                break
                                            can.drawString(x + 3, current_y, line)
                                            current_y -= line_height

                                    # Hide Original Field
                                    current_flags = int(annot_obj.get('/F', 0))
                                    annot_obj[NameObject('/F')] = NumberObject(current_flags | 2 | 4) # Hidden + Print? No just Hidden (2)
                                    
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
        print(f"Error: {e}")
        sys.exit(1)

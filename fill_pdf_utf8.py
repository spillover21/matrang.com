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
else:
    # Fallback to standard (won't support Cyrillic well, but better than crash)
    FONT_NAME = 'Helvetica'

def fill_pdf_overlay(template_path, output_path, field_data):
    """
    Fills a PDF by drawing text over the form fields using a Cyrillic-compatible font,
    then flattening (removing) the original form fields.
    """
    reader = PdfReader(template_path)
    writer = PdfWriter()

    # Copy all pages from reader to writer
    writer.append_pages_from_reader(reader)
    
    # We need to process page by page to find fields and draw text
    for page_num, page in enumerate(writer.pages):
        
        # Buffer for ReportLab drawing
        packet = io.BytesIO()
        # Create a new PDF with ReportLab
        can = canvas.Canvas(packet, pagesize=(page.mediabox.width, page.mediabox.height))
        can.setFont(FONT_NAME, 10) # Default size
        
        # Get standard page height for coordinate conversion (PDF is bottom-left origin)
        page_height = float(page.mediabox.height)
        
        # Find annotations (fields)
        if '/Annots' in page:
            annotations = page['/Annots']
            # We will iterate and modify, so we might need a list copy logic if we were deleting
            # But here we just want to read location and draw
            
            # Since annotations can be indirect objects, validity check
            if isinstance(annotations, list):
                # We need to collect valid fields for this page
                # Unfortunately, pypdf doesn't give a simple "get_fields_by_page" that gives coordinates easily.
                # We have to iterate the raw standard /Annots list.
                
                for i in range(len(annotations)):
                    annot_ref = annotations[i]
                    annot_obj = annot_ref.get_object()
                    
                    if annot_obj.get('/Subtype') == '/Widget':
                        # It's a form field
                        field_name = annot_obj.get('/T')
                        if field_name:
                            # pypdf returns text string or byte string
                            key = str(field_name) 
                            
                            # Clean up key if needed (sometimes it has Null bytes)
                            key = key.replace('\x00', '')

                            if key in field_data:
                                value = str(field_data[key])
                                
                                # Get coordinates
                                rect = annot_obj.get('/Rect') # [x_ll, y_ll, x_ur, y_ur]
                                if rect:
                                    x = float(rect[0])
                                    y = float(rect[1]) 
                                    w = float(rect[2]) - x
                                    h = float(rect[3]) - y
                                    
                                    # Draw Text (simple customization: bottom-left aligned with padding)
                                    text_x = x + 2
                                    text_y = y + 2 # slight padding
                                    
                                    # Optional: Auto-font size based on height?
                                    # For now, stick to 10pt as it's standard for forms.
                                    
                                    can.drawString(text_x, text_y, value)
                                    
                                    # MARK FIELD AS READ-ONLY and HIDDEN?
                                    # Or just remove the widget?
                                    # Removing the widget from the page is the cleanest "flattening"
                                    # But editing the list while iterating is tricky. 
                                    # Instead, we set the F (Flags) to Hidden (bit 2) + Print (bit 3)? 
                                    # Or Invisible (bit 1)?
                                    
                                    # Bit 1 (Invisible) -> 1
                                    # Bit 2 (Hidden) -> 2
                                    # Bit 3 (Print) -> 4
                                    # We want it GONE from view, but maybe keep it for reference?
                                    # Actually, making it Hidden (2) is best.
                                    
                                    current_flags = annot_obj.get('/F', 0)
                                    # Set Hidden bit (2)
                                    new_flags = current_flags | 2 
                                    annot_obj[NameObject('/F')] = NumberObject(new_flags)
                                    
                                    # Also remove the /AP (Appearance) if it exists, to ensure no cached drawing shows up
                                    if '/AP' in annot_obj:
                                        del annot_obj['/AP']
        
        can.save()
        
        # Move to the beginning of the StringIO buffer
        packet.seek(0)
        new_pdf = PdfReader(packet)
        
        # Merge content if we drew anything
        if len(new_pdf.pages) > 0:
            overlay_page = new_pdf.pages[0]
            page.merge_page(overlay_page)

    # Finally, remove the global AcroForm dictionary to "flatten" further?
    # Not strictly necessary if we hid the widgets, but good practice.
    if '/AcroForm' in writer.root_object:
        # We might want to keep it if there are signatures, 
        # but for simple data fields we are replacing them with text.
        # Signatures are usually added later or require specific handling.
        # Our script runs BEFORE the signature process?
        # Yes, this script produces the PDF that receives signatures later.
        pass

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

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys
import json
import io
import os
import datetime
from pypdf import PdfReader, PdfWriter
from pypdf.generic import NameObject, NumberObject
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import simpleSplit

# LOGGING SETUP
LOG_FILE = "/tmp/fill_pdf_debug.log"

def log(msg):
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        f.write(f"[{timestamp}] {msg}\n")

# Register Cyrillic Font
FONT_PATH = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
if os.path.exists(FONT_PATH):
    pdfmetrics.registerFont(TTFont('DejaVuSans', FONT_PATH))
    FONT_NAME = 'DejaVuSans'
    log(f"Font loaded: {FONT_NAME}")
else:
    FONT_NAME = 'Helvetica'
    log(f"Font NOT found, fallback to {FONT_NAME}")

def build_widget_map(reader):
    """
    Builds a map of { indirect_id: field_name } by traversing the AcroForm Fields MANUALLY.
    Avoids pypdf get_fields() issues with complex structures.
    """
    widget_map = {}
    
    try:
        if '/AcroForm' not in reader.root_object:
            return widget_map
        
        acroform = reader.root_object['/AcroForm']
        if '/Fields' not in acroform:
            return widget_map
            
        fields = acroform['/Fields']
        
        # Queue for traversal
        # Each item is (field_object, parent_full_name)
        queue = []
        
        for f in fields:
            queue.append((f.get_object(), None))
            
        while queue:
            obj, parent_name = queue.pop(0)
            
            # Determine name
            current_name = parent_name
            local_name = obj.get('/T')
            
            if local_name:
                # Clean name: remove backticks
                clean_local = local_name.replace('`', '').strip()
                # Debug logging
                if clean_local == 'dogName':
                    log(f"DEBUG: Found dogName object. ID={obj.indirect_reference.idnum if hasattr(obj, 'indirect_reference') else '?'}. Parent={parent_name}")
                    
                if parent_name:
                    current_name = f"{parent_name}.{clean_local}"
                else:
                    current_name = clean_local
            
            # If this object is a Widget (has Rect), map it
            if '/Rect' in obj and current_name:
                try:
                    ref = obj.indirect_reference
                    if ref:
                        widget_map[ref.idnum] = current_name
                        if 'dogName' in current_name:
                             log(f"DEBUG: Mapped dogName widget. ID={ref.idnum} Name={current_name}")
                    else:
                        if 'dogName' in current_name:
                             log(f"DEBUG: dogName has Rect but no IndirectReference?")
                except:
                    pass
            
            # Process Kids
            if '/Kids' in obj:
                kids = obj['/Kids']
                if 'dogName' in str(current_name):
                    log(f"DEBUG: Processing kids for dogName. Count={len(kids)}")
                    
                if isinstance(kids, list):
                    for k in kids:
                        queue.append((k.get_object(), current_name))
                        
    except Exception as e:
        log(f"Error building widget map: {e}")
        
    return widget_map

def fill_pdf_overlay(template_path, output_path, field_data):
    log(f"Starting fill_pdf_overlay v11 (Reader Page Iteration). Template: {template_path}")
    
    reader = PdfReader(template_path)
    writer = PdfWriter()

    # Build Map from READER (Original IDs)
    log("Building Widget ID Map...")
    widget_map = build_widget_map(reader)
    log(f"Mapped {len(widget_map)} widget IDs to names.")
    
    total_matches = 0
    found_keys = []

    # Iterate READER pages (to preserve ID matching)
    for page_num, page in enumerate(reader.pages):
        packet = io.BytesIO()
        # Ensure we use float for dimensions
        w = float(page.mediabox.width)
        h = float(page.mediabox.height)
        can = canvas.Canvas(packet, pagesize=(w, h))
        
        if '/Annots' in page:
            annotations = page['/Annots']
            if isinstance(annotations, list):
                for i in range(len(annotations)):
                    try:
                        annot_ref = annotations[i]
                        # annot_ref is IndirectObject. Get ID.
                        annot_id = annot_ref.idnum
                        annot_obj = annot_ref.get_object()
                        
                        if annot_obj.get('/Subtype') == '/Widget':
                            # Try to find name in Map
                            key_clean = widget_map.get(annot_id)

                            # Fallback to direct T if not in map (should contain backticks if present)
                            if not key_clean and '/T' in annot_obj:
                                key_clean = str(annot_obj['/T']).replace('\x00', '').replace('`', '').strip()

                            if key_clean:
                                if key_clean not in found_keys:
                                    found_keys.append(key_clean)
                                
                                value_to_draw = None
                                
                                # Lookup in JSON Data
                                if key_clean in field_data:
                                    value_to_draw = field_data[key_clean]
                                    log(f"MATCH: '{key_clean}' found on Page {page_num+1}. Value: '{str(value_to_draw)[:20]}...'")
                                
                                if value_to_draw is not None and str(value_to_draw).strip() != "":
                                    val_str = str(value_to_draw)
                                    
                                    rect = annot_obj.get('/Rect')
                                    if rect:
                                        total_matches += 1
                                        x = float(rect[0])
                                        y = float(rect[1])
                                        w = float(rect[2]) - x
                                        h = float(rect[3]) - y
                                        
                                        # Determine if Checkbox
                                        is_checkbox = False
                                        ft = annot_obj.get('/FT')
                                        # Inherited FT check
                                        if not ft and '/Parent' in annot_obj:
                                            parent = annot_obj['/Parent'].get_object()
                                            if '/FT' in parent:
                                                ft = parent['/FT']

                                        if ft == '/Btn':
                                            is_checkbox = True
                                        
                                        val_lower = val_str.lower()
                                        if val_lower in ['true', 'false']:
                                            is_checkbox = True

                                        if is_checkbox:
                                            # Draw Checkmark
                                            if val_lower in ['true', 'yes', 'on', '1']:
                                                mark = "X"
                                                mark_size = min(w, h) * 0.8
                                                if mark_size > 14: mark_size = 14
                                                
                                                can.setFont(FONT_NAME, mark_size)
                                                mark_w = can.stringWidth(mark, FONT_NAME, mark_size)
                                                center_x = x + (w - mark_w) / 2
                                                center_y = y + (h - mark_size) / 2 + (mark_size * 0.2)
                                                can.drawString(center_x, center_y, mark)
                                        else:
                                            # Text Field
                                            # Calculate smart font size based on box height
                                            if h < 15:
                                                font_size = 8
                                            elif h < 20:
                                                font_size = 10
                                            else:
                                                font_size = 11
                                                
                                            can.setFont(FONT_NAME, font_size)
                                            line_height = font_size * 1.2
                                            
                                            # If height is small (single line)
                                            if h < line_height * 2.5:
                                                # Vertical align center (approximate for baseline)
                                                # y is bottom of rect. font is drawn from baseline.
                                                # Adjust +1 or +2 usually centers it visually in the box
                                                text_y = y + (h - font_size) / 2 + 1.5
                                                
                                                # Clip text if too long
                                                text_width = can.stringWidth(val_str, FONT_NAME, font_size)
                                                available_width = w - 6 # 3px padding on each side
                                                
                                                if text_width > available_width:
                                                    # Try to shrink font slightly before clipping
                                                    can.setFont(FONT_NAME, font_size - 1)
                                                    text_width = can.stringWidth(val_str, FONT_NAME, font_size - 1)
                                                    if text_width > available_width:
                                                         # Still too big, clip it
                                                         avg_char_w = text_width / max(1, len(val_str))
                                                         max_chars = int(available_width / avg_char_w)
                                                         draw_text = val_str[:max_chars]
                                                    else:
                                                         draw_text = val_str
                                                         # Keep smaller font for drawing
                                                         font_size -= 1
                                                else:
                                                    draw_text = val_str
                                                
                                                # Left padding x + 3
                                                can.drawString(x + 3, text_y, draw_text)
                                            else:
                                                # Multiline
                                                # Reset font in case it was changed above (though scoping handles it, good to be safe)
                                                can.setFont(FONT_NAME, font_size)
                                                
                                                lines = simpleSplit(val_str, FONT_NAME, font_size, w - 6)
                                                # Start from top, padding 3px
                                                current_y = (y + h) - line_height - 3
                                                
                                                for line in lines:
                                                    if current_y < y: 
                                                        log(f"Field '{key_clean}' text clipped at bottom")
                                                        break
                                                    can.drawString(x + 3, current_y, line)
                                                    current_y -= line_height

                                        # Hide Original Widget
                                        current_flags = int(annot_obj.get('/F', 0))
                                        annot_obj[NameObject('/F')] = NumberObject(current_flags | 2) # Hidden flag
                                        if '/AP' in annot_obj:
                                            del annot_obj['/AP']
                                    else:
                                        log(f"WARNING: Widget for '{key_clean}' has no Rect.")
                    except Exception as loop_e:
                        log(f"Error processing item on pg {page_num}: {loop_e}")

        can.save()
        packet.seek(0)
        
        try:
            new_pdf = PdfReader(packet)
            if len(new_pdf.pages) > 0:
                page.merge_page(new_pdf.pages[0])
        except Exception as e:
            log(f"Error merging overlay: {e}")
            
        # IMPORTANT: Add to writer AFTER merging
        writer.add_page(page)
            
    log(f"All found PDF keys in this run: {sorted(list(set(found_keys)))}")
    # Verify User Concerns
    missing_concerns = [k for k in ['kennelName', 'dogBreed', 'dogGender'] if k not in found_keys]
    if missing_concerns:
        log(f"CONFIRMED MISSING FIELDS (Not in PDF structure): {missing_concerns}")
    
    log(f"Finished. Total fields filled: {total_matches}")

    with open(output_path, 'wb') as f:
        writer.write(f)

if __name__ == '__main__':
    try:
        log("--- Execution Start ---")
        if len(sys.argv) != 4:
            log("Invalid args")
            sys.exit(1)

        template_path = sys.argv[1]
        output_path = sys.argv[2]
        data_json_path = sys.argv[3]

        with open(data_json_path, 'r', encoding='utf-8') as f:
            field_data = json.load(f)
            
        fill_pdf_overlay(template_path, output_path, field_data)
    except Exception as e:
        log(f"CRITICAL ERROR: {e}")
        import traceback
        log(traceback.format_exc())
        sys.exit(1)

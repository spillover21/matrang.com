from pypdf import PdfReader
import sys

def inspect_pdf(path):
    print(f"--- Inspecting: {path} ---")
    try:
        reader = PdfReader(path)
        if reader.is_encrypted:
            print("PDF is encrypted! Attempting to decrypt with empty password...")
            try:
                reader.decrypt('')
            except:
                print("Failed to decrypt.")
        
        acroform = reader.root_object.get('/AcroForm')
        if not acroform:
            print("No /AcroForm found in root structure.")
        else:
            print("Found /AcroForm.")
            fields = acroform.get('/Fields')
            print(f"Top-level field count: {len(fields) if fields else 0}")
            
        print("\n--- Listing All Fields (recursively) ---")
        fields = reader.get_fields()
        if fields:
            for key, value in fields.items():
                print(f"Field: '{key}'")
                # Try to get more info if possible
                if value and isinstance(value, dict):
                    print(f"  - Type: {value.get('/FT')}")
                    # rect = value.get('/Rect')
                    # print(f"  - Rect: {rect}")
        else:
            print("reader.get_fields() returned None or empty.")

        print("\n--- Iterating Pages for Widget Annotations ---")
        for i, page in enumerate(reader.pages):
            if '/Annots' in page:
                for annot in page['/Annots']:
                    obj = annot.get_object()
                    if obj.get('/Subtype') == '/Widget':
                        t = obj.get('/T')
                        print(f"Page {i+1}: Widget T='{t}'") 

    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        inspect_pdf(sys.argv[1])
    else:
        print("Usage: python3 inspect_pdf.py <pdf_file>")

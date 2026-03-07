import base64

with open('e:/pitbull/public_html/server_verif.b64', 'r', encoding='utf-16') as f:
    # PowerShell > might include BOM. Read as utf-16 or try utf-8
    try:
        content = f.read()
    except:
        pass

# If utf-16 failed, re-open utf-8 (standard python fallback if just file content)
# Actually, ssh output > file in powershell is tricky. 
# It likely contains the previous command output too if the terminal was messy.
# I should clean it.

with open('e:/pitbull/public_html/server_verif.b64', 'rb') as f:
    raw = f.read()

# Try to find start of base64 lines (headers usually like "<?php" encoded is PD9...)
# But we just want to decode as much as possible.
# Actually, let's just inspect the raw text first to see if it's cleaner.

try:
    decoded = base64.b64decode(raw, validate=False)
    print("Decoded length:", len(decoded))
    print("Decoded sample:")
    text = decoded.decode('utf-8', errors='ignore')
    print(text[0:500])
    
    if "STR_PAD_LEFT" in text:
        print("FOUND STR_PAD_LEFT")
    if "STR_PAD_LEF\n" in text:
        print("FOUND STR_PAD_LEF SPLIT")
        
    if '"documentMetaId"' in text:
        print("FOUND documentMetaId")
    if '"documentMet\naId"' in text or '"documentMet\r\naId"' in text:
        print("FOUND documentMetaId SPLIT")
        
except Exception as e:
    print("Error:", e)

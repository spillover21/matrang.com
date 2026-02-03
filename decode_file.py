import base64
import os

try:
    with open('encoded.txt', 'r') as f:
        content = f.read()
        # Clean up the content
        b64_str = content.strip().replace('\n', '').replace('\r', '').replace(' ', '')
    
    # Fix padding
    padding = len(b64_str) % 4
    if padding:
        b64_str += '=' * (4 - padding)
        
    decoded_bytes = base64.b64decode(b64_str)
    
    with open('decoded_verified.js', 'wb') as f:
        f.write(decoded_bytes)
    print("Success")
except Exception as e:
    print(f"Error: {e}")

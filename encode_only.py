import base64
import subprocess

local_file = 'e:/pitbull/public_html/create_envelope_fixed.php'
remote_path = '/var/www/documenso-bridge/create_envelope.php'

# Read and encode
with open(local_file, 'rb') as f:
    content = f.read()

b64 = base64.b64encode(content).decode('ascii')

# Write to temp file
with open('e:/pitbull/public_html/temp_upload.b64', 'w') as f:
    f.write(b64)

print(f"Encoded {len(content)} bytes to base64")
print("Now upload with: type temp_upload.b64 | ssh root@72.62.114.139 \"base64 -d > /var/www/documenso-bridge/create_envelope.php\"")

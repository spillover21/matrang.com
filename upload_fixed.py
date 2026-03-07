import base64
import subprocess
import os

local_file = 'e:/pitbull/public_html/create_envelope_fixed.php'
remote_path = '/var/www/documenso-bridge/create_envelope.php'
temp_remote_path = '/tmp/create_envelope_b64'

print(f"Reading {local_file}")
with open(local_file, 'rb') as f:
    content = f.read()

print(f"Size: {len(content)} bytes")
b64_content = base64.b64encode(content)

# create command to upload b64
# We will use subprocess to pipe the b64 data to ssh
ssh_cmd = ['ssh', 'root@72.62.114.139', f'cat > {temp_remote_path}']

print("Uploading base64...")
p = subprocess.run(ssh_cmd, input=b64_content, check=True)

print("Decoding on server...")
decode_cmd = f"base64 -d {temp_remote_path} > {remote_path}"
subprocess.run(['ssh', 'root@72.62.114.139', decode_cmd], check=True)

print("Verifying syntax...")
check_cmd = f"php -l {remote_path}"
subprocess.run(['ssh', 'root@72.62.114.139', check_cmd], check=True)

print("Verifying MD5 hash...")
import hashlib
with open(local_file, 'rb') as f:
    local_md5 = hashlib.md5(f.read()).hexdigest()
print(f"Local MD5: {local_md5}")

hash_cmd = f"md5sum {remote_path}"
res = subprocess.run(['ssh', 'root@72.62.114.139', hash_cmd], capture_output=True, text=True)
remote_md5 = res.stdout.split()[0]
print(f"Remote MD5: {remote_md5}")

if local_md5 == remote_md5:
    print("SUCCESS: MD5 Hashes match.")
else:
    print("FAILURE: MD5 mismatch!")

print("Done.")

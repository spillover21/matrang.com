import subprocess
import os

local_file = 'e:/pitbull/public_html/create_envelope_fixed.php'
remote_path = '/var/www/documenso-bridge/create_envelope.php'
temp_remote = '/tmp/create_envelope_temp.php'
host = 'root@72.62.114.139'

# Copy file to temp location
print("Uploading to temp location...")
result = subprocess.run(['scp', local_file, f'{host}:{temp_remote}'], capture_output=True, text=True)
if result.returncode != 0:
    print(f"SCP failed: {result.stderr}")
    exit(1)

print("Moving to final location...")
result = subprocess.run(['ssh', host, f'mv {temp_remote} {remote_path}'], capture_output=True, text=True)
if result.returncode != 0:
    print(f"Move failed: {result.stderr}")
    exit(1)

print("Checking syntax...")
result = subprocess.run(['ssh', host, f'php -l {remote_path}'], capture_output=True, text=True)
print(result.stdout)

print("Done!")

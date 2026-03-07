import paramiko
import os

local_file = 'e:/pitbull/public_html/create_envelope_fixed.php'
remote_path = '/var/www/documenso-bridge/create_envelope.php'
host = '72.62.114.139'

# Read file
with open(local_file, 'rb') as f:
    content = f.read()

print(f"File size: {len(content)} bytes")

# Connect via SSH
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

# Use key authentication (assuming key is in default location)
ssh.connect(host, username='root')

# Upload via SFTP
sftp = ssh.open_sftp()
sftp.putfo(open(local_file, 'rb'), remote_path)
sftp.close()

# Verify
stdin, stdout, stderr = ssh.exec_command(f'wc -c {remote_path}')
size = stdout.read().decode().strip()
print(f"Remote file: {size}")

stdin, stdout, stderr = ssh.exec_command(f'php -l {remote_path}')
result = stdout.read().decode().strip()
print(f"Syntax check: {result}")

ssh.close()
print("Upload complete!")

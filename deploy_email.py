import subprocess
import sys

host = 'root@72.62.114.139'
remote_dir = '/var/www/documenso-bridge'
files_to_upload = [
    ('e:/pitbull/public_html/audit_trail_watcher.py', f'{remote_dir}/audit_trail_watcher.py'),
    ('e:/pitbull/public_html/send_final_email.py', f'{remote_dir}/send_final_email.py'),
    ('e:/pitbull/public_html/download_signed.php', f'{remote_dir}/download_signed.php')
]

def run_command(cmd, shell=False):
    print(f"Running: {' '.join(cmd) if isinstance(cmd, list) else cmd}")
    res = subprocess.run(cmd, capture_output=True, text=True, shell=shell)
    if res.returncode != 0:
        print(f"Error: {res.stderr}")
        return False
    print(res.stdout)
    return True

print("--- Uploading Email & Download Files ---")
for local, remote in files_to_upload:
    print(f"Uploading {local} to {remote}...")
    if not run_command(['scp', local, f'{host}:{remote}']):
        sys.exit(1)

print("--- Restarting Service ---")
cmd = f"ssh {host} systemctl restart documenso-audit-watcher"
if not run_command(cmd, shell=True):
    sys.exit(1)

print("--- Done ---")

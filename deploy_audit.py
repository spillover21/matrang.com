import subprocess
import sys

host = 'root@72.62.114.139'
remote_dir = '/var/www/documenso-bridge'
files_to_upload = [
    ('e:/pitbull/public_html/add_audit_trail.py', f'{remote_dir}/add_audit_trail.py'),
    ('e:/pitbull/public_html/audit_trail_watcher.py', f'{remote_dir}/audit_trail_watcher.py'),
    ('e:/pitbull/public_html/check_pdf_audit.py', f'{remote_dir}/check_pdf_audit.py')
]

def run_command(cmd, shell=False):
    print(f"Running: {' '.join(cmd) if isinstance(cmd, list) else cmd}")
    res = subprocess.run(cmd, capture_output=True, text=True, shell=shell)
    if res.returncode != 0:
        print(f"Error: {res.stderr}")
        return False
    print(res.stdout)
    return True

print("--- Uploading Files ---")
for local, remote in files_to_upload:
    print(f"Uploading {local} to {remote}...")
    # scp local host:remote
    if not run_command(['scp', local, f'{host}:{remote}']):
        sys.exit(1)

print("--- Restarting Service ---")
# Restart service remotely
cmd = f"ssh {host} systemctl restart documenso-audit-watcher"
if not run_command(cmd, shell=True): # shell=True often helps with ssh command strings in windows
    sys.exit(1)

print("--- Verifying Status ---")
cmd = f"ssh {host} systemctl status documenso-audit-watcher --no-pager"
run_command(cmd, shell=True)

print("--- Done ---")

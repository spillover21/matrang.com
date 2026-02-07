import subprocess

host = 'root@72.62.114.139'
# We want to run: docker exec -i documenso-postgres psql -U documenso -d documenso -c "NOTIFY envelope_completed, 'envelope_wclibsvblhfrvbuh';"
psql_cmd = "NOTIFY envelope_completed, 'envelope_wclibsvblhfrvbuh';"
remote_cmd = f'docker exec -i documenso-postgres psql -U documenso -d documenso -c "{psql_cmd}"'

print(f"Executing: {remote_cmd}")
res = subprocess.run(['ssh', host, remote_cmd], capture_output=True, text=True)
print("STDOUT:", res.stdout)
print("STDERR:", res.stderr)

# Now check logs
print("\nChecking logs...")
res = subprocess.run(['ssh', host, 'journalctl -u documenso-audit-watcher -n 20 --no-pager'], capture_output=True, text=True)
print(res.stdout)

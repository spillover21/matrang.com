import subprocess

query = 'SELECT id, "jobId", status FROM "BackgroundJob" WHERE status IN (\'PENDING\', \'PROCESSING\');'
# Use PGPASSWORD to avoid password prompt if needed, though env var in docker usually handles it for the user 'documenso' trust auth?
# The docker-compose says POSTGRES_PASSWORD=documenso123
# internal connection usually is trusted or via password.
# docker exec -e PGPASSWORD=documenso123 ...

cmd = [
    'docker', 'exec', '-e', 'PGPASSWORD=documenso123', 'documenso-postgres', 
    'psql', '-U', 'documenso', '-d', 'documenso', '-c', query
]

subprocess.run(cmd)

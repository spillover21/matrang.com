import psycopg2
import sys

DB_CONFIG = {
    'host': '72.62.114.139',
    'port': '5432',
    'database': 'documenso',
    'user': 'documenso',
    'password': 'documenso123'
}

try:
    conn = psycopg2.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM \"Signature\" LIMIT 1")
    colnames = [desc[0] for desc in cursor.description]
    print("Columns in Signature table:", colnames)
except Exception as e:
    print(f"Error: {e}")

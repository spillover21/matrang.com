import psycopg2

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
    cursor.execute('SELECT * FROM "Signature" LIMIT 1')
    colnames = [desc[0] for desc in cursor.description]
    print("Columns:", colnames)
    
    # Also lets get one row to see if we have image data
    row = cursor.fetchone()
    print("Row data types:", [type(x) for x in row] if row else "No rows")
    
    # Check if there is a 'signatureImageId'
    if 'signatureImageId' in colnames:
        pass # Good
        
except Exception as e:
    print(e)

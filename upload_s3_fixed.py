import boto3
from botocore.client import Config
import sys
import os

# Configuration
MINIO_URL = 'http://127.0.0.1:9002'
ACCESS_KEY = 'minioadmin'
SECRET_KEY = 'minioadmin123'
BUCKET_NAME = 'documenso'

def upload_file(file_path, object_name):
    s3 = boto3.client('s3',
                      endpoint_url=MINIO_URL,
                      aws_access_key_id=ACCESS_KEY,
                      aws_secret_access_key=SECRET_KEY,
                      config=Config(signature_version='s3v4'),
                      region_name='us-east-1')

    try:
        s3.upload_file(file_path, BUCKET_NAME, object_name, ExtraArgs={'ContentType': "application/pdf"})
        print(f"SUCCESS:{object_name}")
    except Exception as e:
        print(f"ERROR:{str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python3 upload_s3.py <file_path> <object_name>")
        sys.exit(1)

    file_path = sys.argv[1]
    object_name = sys.argv[2]

    upload_file(file_path, object_name)

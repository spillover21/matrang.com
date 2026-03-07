import os
import shutil
import re
import glob

# =================================================================
# BUILD CONFIGURATION
# =================================================================

SOURCE_ROOT = "../../"  # Root of the workspace
OUTPUT_DIR = "build_output"
ASSETS_DIR = "assets"

# What to copy
DIRS_TO_COPY = {
    "api": ["api"],                 # Source -> Dest relative to root
    "public_html": ["frontend"],    # Assuming current dir has frontend build
}

# Sensitive strings to replace
REPLACEMENTS = {
    "72.62.114.139": "{{SERVER_IP}}",
    "documenso.matrang.com": "{{DOMAIN}}",
    "documenso123": "{{DB_PASSWORD}}",
    "matrang_secret_key_2026": "{{BRIDGE_SECRET}}",
    "api_iffjvv698wn27tji": "{{DOCUMENSO_API_TOKEN}}"
}

# Files to exclude
IGNORE_PATTERNS = [
    "*.log", ".git*", "node_modules", "vendor", 
    "*.tmp", "backup*", ".env", ".DS_Store",
    "template_factory"
]

def clean_build_dir():
    if os.path.exists(OUTPUT_DIR):
        shutil.rmtree(OUTPUT_DIR)
    os.makedirs(OUTPUT_DIR)
    print(f"[OK] Cleaned {OUTPUT_DIR}")

def copy_files():
    # 1. Copy API (Backend)
    src_api = os.path.join(os.getcwd(), "../api")
    dest_api = os.path.join(OUTPUT_DIR, "api")
    shutil.copytree(src_api, dest_api, ignore=shutil.ignore_patterns(*IGNORE_PATTERNS))
    
    # ---------------------------------------------------------
    # COPY CRITICAL PYTHON COMPONENTS (SIGNING SYSTEM)
    # ---------------------------------------------------------
    print("[...] Gathering Python Signing System files...")
    
    # Map local files to server filenames
    # (Source path relative to root ../, Destination name in api/)
    python_files = {
        "fill_pdf_final.py": "fill_pdf.py",
        "send_final_email.py": "send_final_email.py",
    }
    
    for src_name, dest_name in python_files.items():
        src_path = os.path.join(os.getcwd(), "..", src_name)
        if os.path.exists(src_path):
            shutil.copy(src_path, os.path.join(dest_api, dest_name))
            print(f"    - Included {dest_name} (from {src_name})")
        else:
            print(f"    [WARNING] Missing {src_name} locally!")

    # Copy PDF Templates
    template_src = os.path.join(os.getcwd(), "../uploads/pdf_template.pdf")
    if os.path.exists(template_src):
        shutil.copy(template_src, os.path.join(dest_api, "pdf_template.pdf"))
        print("    - Included pdf_template.pdf")
    else:
        print("    [WARNING] PDF Template not found in uploads/!")
        
    # Copy English Template if exists
    template_en_src = os.path.join(os.getcwd(), "../uploads/pdf_template_en.pdf")
    if os.path.exists(template_en_src):
        shutil.copy(template_en_src, os.path.join(dest_api, "pdf_template_en.pdf"))
        print("    - Included pdf_template_en.pdf")

    # Generate requirements.txt
    with open(os.path.join(dest_api, "requirements.txt"), "w") as f:
        f.write("pypdf>=3.0.0\nreportlab>=4.0.0\nrequests>=2.0.0\n")
    print("    - Generated requirements.txt")

    # ---------------------------------------------------------

    # 2. Copy Frontend (Root files)
    dest_front = os.path.join(OUTPUT_DIR, "frontend")
    os.makedirs(dest_front)

    
    # Manually copy specific frontend files to avoid grabbing huge junk
    exts = ['*.html', '*.js', '*.css', '*.json', '*.php']
    for ext in exts:
        for file in glob.glob(os.path.join(os.getcwd(), "..", ext)):
            shutil.copy(file, dest_front)
            
    # Copy Assets folder
    if os.path.exists("../assets"):
        shutil.copytree("../assets", os.path.join(dest_front, "assets"))

    print(f"[OK] Files copied to {OUTPUT_DIR}")

def paramerterize_code():
    print("[...] Parameterizing code...")
    count = 0
    for root, dirs, files in os.walk(OUTPUT_DIR):
        for file in files:
            if file.endswith(('.php', '.js', '.yml', '.conf', '.json')):
                path = os.path.join(root, file)
                try:
                    with open(path, 'r', encoding='utf-8') as f:
                        content = f.read()
                    
                    modified = False
                    for old, new in REPLACEMENTS.items():
                        if old in content:
                            content = content.replace(old, new)
                            modified = True
                    
                    if modified:
                        with open(path, 'w', encoding='utf-8') as f:
                            f.write(content)
                        count += 1
                        print(f"    - Patched {file}")
                except Exception as e:
                    print(f"Skipping {file}: {e}")
    print(f"[OK] Patched {count} files.")

def add_assets():
    # Copy install script
    shutil.copy(os.path.join(ASSETS_DIR, "install.sh"), OUTPUT_DIR)
    
    # ---------------------------------------------------------
    # DATABASE SCHEMA (Added from server)
    # ---------------------------------------------------------
    db_dir = os.path.join(OUTPUT_DIR, "database")
    os.makedirs(db_dir, exist_ok=True)
    
    if os.path.exists("schema.sql"):
        shutil.copy("schema.sql", os.path.join(db_dir, "schema.sql"))
        print(f"    - Included real schema.sql from server")
    else:
        # Create placeholder if missing
        with open(os.path.join(OUTPUT_DIR, "create_db_dump_here.txt"), "w") as f:
            f.write("Run this on source server: docker exec -t documenso-postgres pg_dump -U documenso --schema-only documenso > schema.sql")
            print(f"    [WARNING] schema.sql not found locally. Placeholder created.")

    # Copy Nginx placeholder
    with open(os.path.join(OUTPUT_DIR, "nginx.conf.template"), "w") as f:

        f.write("""
server {
    listen 80;
    server_name {{DOMAIN}};

    location / {
        proxy_pass http://127.0.0.1:9000;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Host $host;
    }
}
""")
    
    # Copy docker-compose placeholder
    with open(os.path.join(OUTPUT_DIR, "docker-compose.yml"), "w") as f:
        f.write("""
version: '3.8'

services:
  documenso:
    image: documenso/documenso:latest
    container_name: documenso
    ports:
      - "9000:3000"
    environment:
      - NODE_ENV=production
      - NEXTAUTH_URL=http://{{DOMAIN}}:9000
      - NEXT_PUBLIC_WEBAPP_URL=http://{{DOMAIN}}:9000
      - NEXTAUTH_SECRET={{BRIDGE_SECRET}}
      
      # Encryption Keys (Auto-generated for new install, but placeholders here)
      - NEXT_PRIVATE_ENCRYPTION_KEY={{ENCRYPTION_KEY}}
      - NEXT_PRIVATE_ENCRYPTION_SECONDARY_KEY={{ENCRYPTION_KEY_2}}  

      # Database
      - NEXT_PRIVATE_DATABASE_URL=postgresql://documenso:{{DB_PASSWORD}}@postgres:5432/documenso?schema=public
      - NEXT_PRIVATE_DIRECT_DATABASE_URL=postgresql://documenso:{{DB_PASSWORD}}@postgres:5432/documenso?schema=public

      # SMTP Settings (Adjust manually if needed)
      - NEXT_PRIVATE_SMTP_HOST=smtp.gmail.com
      - NEXT_PRIVATE_SMTP_PORT=587
      - NEXT_PRIVATE_SMTP_USERNAME=user@example.com
      - NEXT_PRIVATE_SMTP_PASSWORD=password
      - NEXT_PRIVATE_SMTP_SECURE=false
      - NEXT_PRIVATE_SMTP_TRANSPORT=smtp-auth
      - NEXT_PRIVATE_SMTP_FROM_ADDRESS=noreply@{{DOMAIN}}
      - NEXT_PRIVATE_SMTP_FROM_NAME=Documenso

      # Storage (Minio)
      - NEXT_PRIVATE_STORAGE_TRANSPORT=s3
      - NEXT_PRIVATE_STORAGE_ENDPOINT=http://minio:9000
      - NEXT_PRIVATE_STORAGE_FORCE_PATH_STYLE=true
      - NEXT_PRIVATE_STORAGE_REGION=us-east-1
      - NEXT_PRIVATE_STORAGE_BUCKET=documenso
      - NEXT_PRIVATE_STORAGE_ACCESS_KEY_ID=minioadmin
      - NEXT_PRIVATE_STORAGE_SECRET_ACCESS_KEY=minioadmin123
      - NEXT_PRIVATE_UPLOAD_TRANSPORT=s3
      - NEXT_PUBLIC_UPLOAD_TRANSPORT=s3
      - NEXT_PRIVATE_UPLOAD_ENDPOINT=http://minio:9000
      - NEXT_PRIVATE_UPLOAD_FORCE_PATH_STYLE=true
      - NEXT_PRIVATE_UPLOAD_REGION=us-east-1
      - NEXT_PRIVATE_UPLOAD_BUCKET=documenso
      - NEXT_PRIVATE_UPLOAD_ACCESS_KEY_ID=minioadmin
      - NEXT_PRIVATE_UPLOAD_SECRET_ACCESS_KEY=minioadmin123

    depends_on:
      postgres:
        condition: service_healthy
      minio:
        condition: service_started
    restart: unless-stopped
    networks:
      - documenso-network

  postgres:
    image: postgres:15-alpine
    container_name: documenso-postgres
    environment:
      - POSTGRES_USER=documenso
      - POSTGRES_PASSWORD={{DB_PASSWORD}}
      - POSTGRES_DB=documenso
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U documenso"]
      interval: 10s
      timeout: 5s
      retries: 5
    restart: unless-stopped
    networks:
      - documenso-network

  minio:
    image: minio/minio:latest
    container_name: documenso-minio
    ports:
      - "9001:9001"
      - "9002:9000"
    environment:
      - MINIO_ROOT_USER=minioadmin
      - MINIO_ROOT_PASSWORD=minioadmin123
    command: server /data --console-address ":9001"
    volumes:
      - minio_data:/data
    restart: unless-stopped
    networks:
      - documenso-network

networks:
  documenso-network:
    driver: bridge

volumes:
  postgres_data:
  minio_data:
""")

def create_archive():
    shutil.make_archive("matrang_template", 'zip', OUTPUT_DIR)
    print(f"[SUCCESS] Archive created: matrang_template.zip")

if __name__ == "__main__":
    clean_build_dir()
    copy_files()
    paramerterize_code()
    add_assets()
    create_archive()

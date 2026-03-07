#!/bin/bash

# =================================================================
# UNIVERSAL DEPLOYMENT SCRIPT (RUN THIS ON THE NEW VPS)
# =================================================================

# Colors
GREEN='\033[0;32m'
NC='\033[0m'

echo -e "${GREEN}=== Matrang Project Auto-Deployer ===${NC}"
echo "This script will configure the project for a new domain."
echo ""

# 1. Gather Variables
read -p "Enter new Domain Name (e.g., contracts.mysite.com): " NEW_DOMAIN
read -p "Enter Server Public IP: " NEW_IP
read -p "Enter Database Password (for Documenso): " DB_PASS
read -p "Enter a Secret API Key for the Bridge (random string): " BRIDGE_SECRET

# Defaults
APP_DIR="/var/www/documenso-bridge"
WEB_DIR="/var/www/html"
NGINX_CONF="/etc/nginx/sites-available/$NEW_DOMAIN"

echo -e "\n${GREEN}=== Configuring Files ===${NC}"

# 2. Process PHP Files
find ./api -name "*.php" -type f -exec sed -i "s|{{DOMAIN}}|$NEW_DOMAIN|g" {} +
find ./api -name "*.php" -type f -exec sed -i "s|{{SERVER_IP}}|$NEW_IP|g" {} +
find ./api -name "*.php" -type f -exec sed -i "s|{{DB_PASSWORD}}|$DB_PASS|g" {} +
find ./api -name "*.php" -type f -exec sed -i "s|{{BRIDGE_SECRET}}|$BRIDGE_SECRET|g" {} +

# 3. Process Nginx Config
sed -i "s|{{DOMAIN}}|$NEW_DOMAIN|g" nginx.conf.template
sed -i "s|{{SERVER_IP}}|$NEW_IP|g" nginx.conf.template

# 4. Process Docker Compose
# Generate random encryption keys
ENC_KEY=$(openssl rand -base64 32)
ENC_KEY_2=$(openssl rand -base64 32)
sed -i "s|{{DOMAIN}}|$NEW_DOMAIN|g" docker-compose.yml
sed -i "s|{{SERVER_IP}}|$NEW_IP|g" docker-compose.yml
sed -i "s|{{DB_PASSWORD}}|$DB_PASS|g" docker-compose.yml
sed -i "s|{{BRIDGE_SECRET}}|$BRIDGE_SECRET|g" docker-compose.yml
sed -i "s|{{ENCRYPTION_KEY}}|$ENC_KEY|g" docker-compose.yml
sed -i "s|{{ENCRYPTION_KEY_2}}|$ENC_KEY_2|g" docker-compose.yml

# 5. Move Files
echo -e "\n${GREEN}=== Installing Files ===${NC}"
mkdir -p $APP_DIR
mkdir -p $WEB_DIR

# Install System Dependencies (Fonts, Python, Docker)
echo "Installing System Dependencies (Docker, Python, Fonts)..."
apt-get update
apt-get install -y python3-pip python3-venv fonts-dejavu curl git unzip

# Install Docker if missing
if ! command -v docker &> /dev/null; then
    echo "Docker not found. Installing..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
fi

if ! command -v docker-compose &> /dev/null; then
    # Modern docker includes compose plugin
    if ! docker compose version &> /dev/null; then
         echo "Installing Docker Compose..."
         apt-get install -y docker-compose-plugin
    fi
fi

# Copy backend bridge
cp -r ./api/* $APP_DIR/

# Setup Python Environment
echo "Setting up Python Virtual Environment..."
cd $APP_DIR
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
deactivate
cd -

# Copy frontend
cp -r ./frontend/* $WEB_DIR/


# Copy Nginx
cp nginx.conf.template $NGINX_CONF
ln -s $NGINX_CONF /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default 2>/dev/null

# 6. Database Import
echo -e "\n${GREEN}=== Setting up Database ===${NC}"
if [ -f "database/schema.sql" ]; then
    echo "Starting Docker containers..."
    docker-compose up -d postgres
    sleep 10
    echo "Importing schema..."
    cat database/schema.sql | docker exec -i documenso-postgres psql -U documenso -d documenso
fi

# 7. Final Permissions
chown -R www-data:www-data $APP_DIR
chown -R www-data:www-data $WEB_DIR
chmod +x $APP_DIR/*.py

# 8. Restart Services
systemctl reload nginx
docker-compose up -d

echo -e "\n${GREEN}=== DEPLOYMENT COMPLETE ===${NC}"
echo "Domain: http://$NEW_DOMAIN"
echo "Bridge API Key: $BRIDGE_SECRET"

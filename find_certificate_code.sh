#!/bin/bash

# Поиск кода генерации Signing Certificate в Documenso

echo "=== Searching for Signing Certificate in Documenso code ==="

docker exec documenso grep -r "Signing Certificate" /opt/apps/web/.next/ 2>/dev/null | head -10

echo ""
echo "=== Checking PDF generation code ==="

docker exec documenso grep -r "certificate" /opt/apps/web/.next/server/ 2>/dev/null | grep -i "pdf" | head -10

echo ""
echo "=== Checking for seal/signature generation ==="

docker exec documenso find /opt/apps -name "*seal*" -o -name "*certificate*" 2>/dev/null | head -10

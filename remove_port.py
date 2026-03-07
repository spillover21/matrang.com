#!/usr/bin/env python3
import sys

with open(sys.argv[1], 'r') as f:
    content = f.read()

# Удаляем секцию ports для documenso контейнера
content = content.replace('    ports:\n      - "9000:3000"\n', '')

with open(sys.argv[1],'w') as f:
    f.write(content)

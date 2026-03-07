#!/usr/bin/env python3
import sys

with open(sys.argv[1], 'r') as f:
    content = f.read()

# Добавляем порт 127.0.0.1:9000 после container_name: documenso
old = '    container_name: documenso\n    environment:'
new = '    container_name: documenso\n    ports:\n      - "127.0.0.1:9000:3000"\n    environment:'
content = content.replace(old, new)

with open(sys.argv[1], 'w') as f:
    f.write(content)

print("Done: added 127.0.0.1:9000:3000 port mapping")

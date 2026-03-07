import re

input_file = 'e:/pitbull/public_html/local_broken.php'
output_file = 'e:/pitbull/public_html/create_envelope_fixed.php'

try:
    with open(input_file, 'r', encoding='utf-16') as f:
        content = f.read()
except UnicodeError:
    with open(input_file, 'r', encoding='utf-8') as f:
        content = f.read()

# Replacements for split tokens
# We use regex to match the token split by newline and optional whitespace
replacements = [
    (r'STR_PAD_LEF\s*T', 'STR_PAD_LEFT'),
    (r'"documentMet\s*aId"', '"documentMetaId"'),
    (r"'kenne\s*lOwner'", "'kennelOwner'"),
    (r'"read\s*Status"', '"readStatus"'),
    (r'"signin\s*gStatus"', '"signingStatus"'),
    (r'"send\s*Status"', '"sendStatus"'),
    (r'"initial\s*Data"', '"initialData"'),
    (r'"document\s*DataId"', '"documentDataId"'),
    (r'"enve\s*lopeId"', '"envelopeId"'),
    (r'"temp\s*lateType"', '"templateType"'),
    (r'"internal\s*Version"', '"internalVersion"'),
    (r'"useLegacyField\s*Insertion"', '"useLegacyFieldInsertion"'),
    (r'pg_last\s*_error', 'pg_last_error'),
    (r'JSON_PRETTY_\s*PRINT', 'JSON_PRETTY_PRINT'),
    (r'pg_query_\s*params', 'pg_query_params'),
    (r'"secondary\s*Id"', '"secondaryId"'),
    (r'fail\s*ed', 'failed'), 
    (r'"\s*order"', '"order"'),
    (r'"\s*redirectUrl"', '"redirectUrl"'),
    (r'"\s*dateFormat"', '"dateFormat"'),
    (r'"\s*typedSignatureEnabled"', '"typedSignatureEnabled"'),
    (r'"\s*publicTitle"', '"publicTitle"'),
    (r'"\s*publicDescription"', '"publicDescription"'),
    (r'"\s*userId"', '"userId"'),
    (r'"\s*teamId"', '"teamId"'),
    (r'"\s*createdAt"', '"createdAt"'),
    (r'"\s*updatedAt"', '"updatedAt"'),
    # Generic fix for any SQL column that got split like " colName" or "\ncolName"
    # Matches " followed by whitespace/newline, then word characters, then "
    (r'"\s+([a-zA-Z0-9_]+)"', r'"\1"'), 
]

fixed_content = content
for pattern, replacement in replacements:
    fixed_content = re.sub(pattern, replacement, fixed_content)

# Fix specific layout issues that might be syntax errors
# e.g. $returnCode . '\n): '
# But we can't easily guess everything. The Critical ones are constants and DB columns.

with open(output_file, 'w', encoding='utf-8') as f:
    f.write(fixed_content)

print("File fixed and saved to " + output_file)

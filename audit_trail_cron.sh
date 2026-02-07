#!/bin/bash
# Автоматическое добавление Audit Trail Page к новым завершенным документам
# Запускается каждые 5 минут через cron

# Путь к скрипту
SCRIPT_DIR="/var/www/documenso-bridge"
PYTHON_SCRIPT="$SCRIPT_DIR/add_audit_trail.py"
MARKER_FILE="$SCRIPT_DIR/.last_audit_trail_run"

# Получаем список envelope которые:
# 1. Завершены (COMPLETED)
# 2. Созданы после последнего запуска
# 3. Еще не обработаны

# Если marker file не существует, создаем его
if [ ! -f "$MARKER_FILE" ]; then
    echo "$(date -u +%Y-%m-%d\ %H:%M:%S)" > "$MARKER_FILE"
fi

# Читаем время последнего запуска
LAST_RUN=$(cat "$MARKER_FILE")

# Получаем новые завершенные envelope
NEW_ENVELOPES=$(docker exec -i documenso-postgres psql -U documenso -d documenso -t -c "
SELECT id 
FROM \"Envelope\" 
WHERE status = 'COMPLETED' 
AND \"completedAt\" > '$LAST_RUN'::timestamp
AND id NOT IN (
    SELECT DISTINCT split_part(data::text, 'audit_trail_added_at', 1)
    FROM \"DocumentData\" dd
    JOIN \"EnvelopeItem\" ei ON dd.id = ei.\"documentDataId\"
    WHERE data::text LIKE '%AUDIT TRAIL%'
)
ORDER BY \"completedAt\" ASC;
")

# Обрабатываем каждый envelope
for ENVELOPE_ID in $NEW_ENVELOPES; do
    echo "Processing envelope: $ENVELOPE_ID"
    python3 "$PYTHON_SCRIPT" "$ENVELOPE_ID"
    
    if [ $? -eq 0 ]; then
        echo "✅ Audit trail added to $ENVELOPE_ID"
    else
        echo "❌ Failed to add audit trail to $ENVELOPE_ID"
    fi
done

# Обновляем marker file
echo "$(date -u +%Y-%m-%d\ %H:%M:%S)" > "$MARKER_FILE"

echo "Audit trail sync completed at $(date)"

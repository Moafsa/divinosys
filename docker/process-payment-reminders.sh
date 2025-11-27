#!/bin/bash
# Script para processar lembretes de pagamento
# Pode ser executado via cron ou manualmente

# URL do endpoint (ajuste conforme necessário)
ENDPOINT_URL="${ENDPOINT_URL:-http://localhost:8080/mvc/ajax/process_payment_reminders.php}"

# Fazer requisição
curl -s "$ENDPOINT_URL" > /dev/null 2>&1

# Log
echo "$(date): Processamento de lembretes executado" >> /var/log/payment_reminders.log


#!/bin/bash
# 🔄 RESTORE RÁPIDO - BD LOCAL PARA ONLINE

echo "=== RESTORE RÁPIDO ONLINE ==="
echo "Substituindo BD online com dados que funcionam localmente"
echo ""

# Definir variáveis
BACKUP_FILE="/app/backup_local_2025_09_26_15_36_48.sql"
DB_NAME="divino_lanches"
USER="postgres"

echo "📂 Arquivo de backup: $BACKUP_FILE"

# Verificar se arquivo existe
if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Arquivo de backup não encontrado!"
    echo "   Certifique-se de subir o arquivo para /app/"
    exit 1
fi

echo "✅ Arquivo encontrado"

# 1. Fazer backup de segurança (caso necessário rollback)
echo "🗄️ Fazendo backup de segurança..."
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
pg_dump -U $USER $DB_NAME > "backup_anterior_${TIMESTAMP}.sql"
echo "   Backup de segurança criado: backup_anterior_${TIMESTAMP}.sql"

# 2. Terminar conexões ativas
echo "🔌 Terminando conexões ativas..."
psql -U $USER -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();" > /dev/null 2>&1

# 3. Dropar banco atual
echo "🗑️ Removendo banco atual..."
psql -U $USER -c "DROP DATABASE IF EXISTS $DB_NAME;" > /dev/null 2>&1

# 4. Criar banco novo
echo "🏗️ Criando banco novo..."
psql -U $USER -c "CREATE DATABASE $DB_NAME OWNER $USER;" > /dev/null 2>&1

# 5. Restaurar dados locais
echo "📤 Importando dados locais..."
psql -U $USER -d $DB_NAME < "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ SUCESSO! BD substituído com dados locais"
    echo "📱 Agora instâncias devem funcionar como local!"
    echo ""
    echo "🔍 Verificações:"
    psql -U $USER -d $DB_NAME -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';"
    psql -U $USER -d $DB_NAME -c "SELECT COUNT(*) FROM usuarios;"
    psql -U $USER -d $DB_NAME -c "SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%whatsapp%';" 2>/dev/null || echo "Tabelas opcionais..."
else
    echo ""
    echo "❌ ERRO na importação!"
    echo "   Restaurando backup anterior..."
    psql -U $USER -d $DB_NAME < "backup_anterior_${TIMESTAMP}.sql"
fi

echo ""
echo "=== CONCLUÍDO ==="
echo "BD substituído! Reinicie sua aplicação."

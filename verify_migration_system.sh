#!/bin/bash

# Verification script for the consolidated migration system
# This script verifies that all migrations, seeds, and inits are working correctly

echo "========================================="
echo "MIGRATION SYSTEM VERIFICATION"
echo "========================================="
echo ""

# Check if containers are running
echo "1. Checking containers..."
docker ps --filter "name=divino-lanches" --format "{{.Names}}: {{.Status}}"
echo ""

# Check database connection
echo "2. Checking database connection..."
docker exec divino-lanches-db pg_isready -U divino_user -d divino_db
echo ""

# Check total tables
echo "3. Checking total tables created..."
docker exec divino-lanches-db psql -U divino_user -d divino_db -c \
  "SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'public';"
echo ""

# Check essential tables
echo "4. Checking essential tables..."
docker exec divino-lanches-db psql -U divino_user -d divino_db -c \
  "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('pedido', 'usuarios', 'produtos', 'categorias', 'mesas', 'database_migrations', 'tenants', 'filiais') ORDER BY table_name;"
echo ""

# Check migrations executed
echo "5. Checking migrations executed..."
docker exec divino-lanches-db psql -U divino_user -d divino_db -c \
  "SELECT COUNT(*) as total_migrations, COUNT(CASE WHEN success = true THEN 1 END) as successful, COUNT(CASE WHEN success = false THEN 1 END) as failed FROM database_migrations;"
echo ""

# Check users
echo "6. Checking users created..."
docker exec divino-lanches-db psql -U divino_user -d divino_db -c \
  "SELECT login, nivel FROM usuarios ORDER BY id;"
echo ""

# Check sequences
echo "7. Checking critical sequences..."
docker exec divino-lanches-db psql -U divino_user -d divino_db -c \
  "SELECT sequencename, last_value FROM pg_sequences WHERE sequencename IN ('pedido_idpedido_seq', 'usuarios_id_seq', 'produtos_id_seq', 'categorias_id_seq') ORDER BY sequencename;"
echo ""

# Test application response
echo "8. Testing application response..."
HTTP_STATUS=$(docker exec divino-lanches-app curl -s -o /dev/null -w "%{http_code}" http://localhost)
if [ "$HTTP_STATUS" == "200" ]; then
    echo "✅ Application is responding (HTTP $HTTP_STATUS)"
else
    echo "❌ Application error (HTTP $HTTP_STATUS)"
fi
echo ""

echo "========================================="
echo "✅ VERIFICATION COMPLETED"
echo "========================================="




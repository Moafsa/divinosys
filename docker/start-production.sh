#!/bin/bash

echo "=== DIVINO LANCHES PRODUCTION STARTUP SCRIPT ==="

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL to be ready..."
until pg_isready -h ${DB_HOST:-postgres} -p ${DB_PORT:-5432} -U ${DB_USER:-divino_user}; do
  echo "PostgreSQL is unavailable - sleeping"
  sleep 2
done

echo "PostgreSQL is ready!"

# Wait a bit more to ensure PostgreSQL has finished creating tables
echo "Waiting for PostgreSQL to finish table creation..."
sleep 5

# Run database migration automatically
echo "Running database migration..."
php migrate.php

# Run database schema fix
echo "Running database schema fix..."
php fix_database_schema.php

# Auto-fix sequences (CRITICAL - prevents duplicate key errors)
echo "Auto-fixing database sequences..."
php deploy_auto_fix.php

# Start Apache
echo "Starting Apache..."
exec apache2-foreground

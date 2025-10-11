#!/bin/bash

echo "=== DIVINO LANCHES STARTUP SCRIPT ==="

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL to be ready..."
until pg_isready -h postgres -p 5432 -U postgres; do
  echo "PostgreSQL is unavailable - sleeping"
  sleep 2
done

echo "PostgreSQL is ready!"

# Wait for Redis to be ready
echo "Waiting for Redis to be ready..."
until redis-cli -h redis -p 6379 ping; do
  echo "Redis is unavailable - sleeping"
  sleep 2
done

echo "Redis is ready!"

# Wait a bit more to ensure PostgreSQL has finished creating tables
echo "Waiting for PostgreSQL to finish table creation..."
sleep 10

# Run database migration automatically
echo "Running database migration..."
php migrate.php

# Run database schema fix
echo "Running database schema fix..."
php fix_database_schema.php

# Auto-fix sequences (prevents duplicate key errors)
echo "Auto-fixing sequences..."
php auto_fix_sequences.php

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
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

# Run database migration automatically
echo "Running database migration..."
php migrate.php

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
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

# Run consolidated database migration
# This script handles: init scripts, migrations, seeds, and sequence fixes
echo "Running consolidated database migration..."
php database_migrate.php

if [ $? -ne 0 ]; then
  echo "⚠️  Warning: Database migration completed with errors"
  echo "⚠️  The application will start anyway, but please check the logs"
fi

# Setup cron job for payment reminders (runs every 2 minutes)
echo "Setting up cron job for payment reminders..."
echo "*/2 * * * * curl -s http://localhost/mvc/ajax/process_payment_reminders.php > /dev/null 2>&1" | crontab -
service cron start

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
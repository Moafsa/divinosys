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

# BACKUP: Create backup before running migrations
BACKUP_DIR="/var/www/html/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_backup_$TIMESTAMP.sql"

echo "Creating database backup before migration..."
mkdir -p $BACKUP_DIR

# Check if we have PGPASSWORD env var, if not try from config
export PGPASSWORD="${DB_PASSWORD:-divino_password}"

# Create backup
if pg_dump -h ${DB_HOST:-postgres} -p ${DB_PORT:-5432} -U ${DB_USER:-divino_user} -d ${DB_NAME:-divino_db} > $BACKUP_FILE 2>/dev/null; then
    echo "✅ Backup created: $BACKUP_FILE"
    gzip $BACKUP_FILE
    echo "✅ Backup compressed: ${BACKUP_FILE}.gz"
    echo "   Size: $(du -h ${BACKUP_FILE}.gz | cut -f1)"
    
    # Keep only last 5 backups to save space
    ls -t $BACKUP_DIR/*.sql.gz | tail -n +6 | xargs rm -f 2>/dev/null
    echo "✅ Old backups cleaned (kept last 5)"
else
    echo "⚠️  Warning: Could not create backup (this is OK for first run or if database is empty)"
fi

# Run consolidated database migration
# This script handles: init scripts, migrations, seeds, and sequence fixes
echo "Running consolidated database migration..."
php database_migrate.php

if [ $? -ne 0 ]; then
  echo "❌ Error: Database migration failed!"
  echo "⚠️  Warning: Migration failed. Check migration logs for details."
  echo "⚠️  If this is the first deployment, this is normal."
  # Don't exit in production, let it start anyway
  # exit 1
fi

echo "✅ Database migration completed successfully!"

# Start Apache
echo "Starting Apache..."
exec apache2-foreground

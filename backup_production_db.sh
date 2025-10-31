#!/bin/bash

# Backup script for production database before migration
# Usage: ./backup_production_db.sh

BACKUP_DIR="./backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_backup_$TIMESTAMP.sql"

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

echo "Starting database backup..."
echo "File: $BACKUP_FILE"

# Connect to production database and create backup
docker exec divino-lanches-db pg_dump -U divino_user divino_db > $BACKUP_FILE

if [ $? -eq 0 ]; then
    echo "✅ Backup created successfully: $BACKUP_FILE"
    echo "Size: $(du -h $BACKUP_FILE | cut -f1)"
else
    echo "❌ Backup failed!"
    exit 1
fi

# Optional: Compress backup
echo "Compressing backup..."
gzip $BACKUP_FILE
echo "✅ Compressed backup: ${BACKUP_FILE}.gz"

echo "Backup completed successfully!"



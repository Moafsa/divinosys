#!/bin/bash

# Restore database from backup
# Usage: ./restore_backup.sh <backup_file>

if [ -z "$1" ]; then
    echo "Usage: ./restore_backup.sh <backup_file>"
    echo "Example: ./restore_backup.sh ./backups/db_backup_20251029_143022.sql.gz"
    exit 1
fi

BACKUP_FILE=$1

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Backup file not found: $BACKUP_FILE"
    exit 1
fi

echo "⚠️  WARNING: This will restore the database from backup!"
echo "⚠️  All current data will be replaced!"
echo ""
read -p "Are you sure you want to proceed? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Restore cancelled."
    exit 0
fi

# Extract if compressed
if [[ $BACKUP_FILE == *.gz ]]; then
    echo "Extracting backup file..."
    EXTRACTED_FILE="${BACKUP_FILE%.gz}"
    gunzip -c $BACKUP_FILE > $EXTRACTED_FILE
    BACKUP_FILE=$EXTRACTED_FILE
fi

echo "Restoring database from $BACKUP_FILE..."

# Stop application to prevent data corruption
docker stop divino-lanches-app

# Restore database
docker exec -i divino-lanches-db psql -U divino_user -d divino_db < $BACKUP_FILE

if [ $? -eq 0 ]; then
    echo "✅ Database restored successfully!"
    
    # Restart application
    docker start divino-lanches-app
    
    echo "✅ Application restarted"
else
    echo "❌ Restore failed!"
    docker start divino-lanches-app
    exit 1
fi

echo "Restore completed!"



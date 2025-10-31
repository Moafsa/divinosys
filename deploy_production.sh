#!/bin/bash

# Safe production deployment script
# This script performs database backup before running migrations

echo "🚀 Starting production deployment..."

# Step 1: Backup existing database
echo ""
echo "Step 1: Creating database backup..."
./backup_production_db.sh

if [ $? -ne 0 ]; then
    echo "❌ Backup failed! Deployment aborted."
    exit 1
fi

# Step 2: Run migrations
echo ""
echo "Step 2: Running database migrations..."
docker exec divino-lanches-app php database_migrate.php

if [ $? -ne 0 ]; then
    echo "❌ Migration failed!"
    echo "⚠️  You may need to restore from backup:"
    echo "   ./restore_backup.sh $BACKUP_FILE"
    exit 1
fi

# Step 3: Restart application
echo ""
echo "Step 3: Restarting application..."
docker restart divino-lanches-app

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Production deployment completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Test the application thoroughly"
    echo "2. Check application logs: docker logs divino-lanches-app"
    echo "3. Verify database structure"
else
    echo "❌ Failed to restart application!"
    exit 1
fi



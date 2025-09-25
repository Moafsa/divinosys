#!/bin/bash

echo "Starting evolution database setup..."

# Wait for PostgreSQL to be ready
sleep 15

echo "Creating evolution_db database..."

# Create the database
PGPASSWORD=$POSTGRES_PASSWORD psql -h postgres -U $POSTGRES_USER -d postgres -c "CREATE DATABASE evolution_db;" || echo "Database already exists"

echo "Evolution database setup complete"

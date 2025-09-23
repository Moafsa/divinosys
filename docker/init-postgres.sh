#!/bin/bash

echo "=== POSTGRESQL INITIALIZATION SCRIPT ==="

# Get environment variables
DB_PASSWORD=${DB_PASSWORD:-divino_password}
DB_NAME=${DB_NAME:-divino_lanches}
DB_USER=${DB_USER:-postgres}

echo "Using DB_PASSWORD: $DB_PASSWORD"
echo "Using DB_NAME: $DB_NAME"
echo "Using DB_USER: $DB_USER"

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL to be ready..."
until pg_isready -h postgres -p 5432 -U postgres; do
  echo "PostgreSQL is unavailable - sleeping"
  sleep 2
done

echo "PostgreSQL is ready!"

# Wait a bit more
sleep 5

# FORCE STOP POSTGRESQL
echo "Force stopping PostgreSQL..."
pkill -f postgres || true
sleep 3

# FORCE CLEAN DATA DIRECTORY
echo "Force cleaning PostgreSQL data directory..."
rm -rf /var/lib/postgresql/data/*
echo "PostgreSQL data directory cleaned!"

# INITIALIZE POSTGRESQL DATABASE
echo "Initializing PostgreSQL database..."
initdb -D /var/lib/postgresql/data -U postgres --auth-local=trust --auth-host=md5

# START POSTGRESQL IN BACKGROUND
echo "Starting PostgreSQL..."
postgres -D /var/lib/postgresql/data &
sleep 5

# CREATE USER AND DATABASE
echo "Creating user and database..."
psql -U postgres -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASSWORD' SUPERUSER CREATEDB CREATEROLE;"
psql -U postgres -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"

echo "PostgreSQL initialization completed!"

# STOP POSTGRESQL
echo "Stopping PostgreSQL..."
pkill -f postgres
sleep 3

echo "PostgreSQL ready for normal startup!"

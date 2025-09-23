#!/bin/bash

echo "=== POSTGRESQL INITIALIZATION SCRIPT ==="

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
psql -U postgres -c "CREATE USER postgres WITH PASSWORD 'divino_password' SUPERUSER CREATEDB CREATEROLE;"
psql -U postgres -c "CREATE DATABASE divino_lanches OWNER postgres;"

echo "PostgreSQL initialization completed!"

# STOP POSTGRESQL
echo "Stopping PostgreSQL..."
pkill -f postgres
sleep 3

echo "PostgreSQL ready for normal startup!"

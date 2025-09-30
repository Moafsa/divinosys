-- Create WuzAPI user and database
-- This script runs after PostgreSQL initialization

\echo '=== CREATING WUZAPI USER AND DATABASE ==='

-- Create wuzapi user
CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';

-- Create wuzapi database
CREATE DATABASE wuzapi OWNER wuzapi;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;

\echo '✅ WuzAPI user and database created successfully!'

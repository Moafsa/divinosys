-- Force WuzAPI user and database creation
-- This script ALWAYS runs, even with persistent volumes

\echo '=== FORCING WUZAPI SETUP ==='
\echo 'Starting WuzAPI user and database creation...'

-- Drop and recreate wuzapi user (force recreation)
\echo 'Dropping existing wuzapi user...'
DROP ROLE IF EXISTS wuzapi;

\echo 'Creating wuzapi user...'
CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';

\echo 'WuzAPI user created successfully!'

-- Drop and recreate wuzapi database (force recreation)
\echo 'Dropping existing wuzapi database...'
DROP DATABASE IF EXISTS wuzapi;

\echo 'Creating wuzapi database...'
CREATE DATABASE wuzapi OWNER wuzapi;

-- Grant privileges
\echo 'Granting privileges...'
GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;

\echo '✅ WuzAPI setup completed successfully!'
\echo 'WuzAPI user: wuzapi'
\echo 'WuzAPI database: wuzapi'
\echo 'WuzAPI password: wuzapi'

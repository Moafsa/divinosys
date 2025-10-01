-- Force WuzAPI user and database creation
-- This script ALWAYS runs, even with persistent volumes

\echo '=== FORCING WUZAPI SETUP ==='

-- Drop and recreate wuzapi user (force recreation)
DROP ROLE IF EXISTS wuzapi;
CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
\echo 'WuzAPI user created/recreated successfully';

-- Drop and recreate wuzapi database (force recreation)
DROP DATABASE IF EXISTS wuzapi;
CREATE DATABASE wuzapi OWNER wuzapi;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;

\echo '✅ WuzAPI setup completed!'

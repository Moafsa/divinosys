-- Force WuzAPI user and database creation
-- This script ALWAYS runs, even with persistent volumes

\echo '=== FORCING WUZAPI SETUP ==='

-- Create wuzapi user if not exists
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
        RAISE NOTICE 'WuzAPI user created successfully';
    ELSE
        RAISE NOTICE 'WuzAPI user already exists';
    END IF;
END
$$;

-- Create wuzapi database if not exists
SELECT 'CREATE DATABASE wuzapi OWNER wuzapi'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi')\gexec

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;

\echo '✅ WuzAPI setup completed!'

-- Create WuzAPI user and database
-- This script runs after PostgreSQL initialization

\echo '=== CREATING WUZAPI USER AND DATABASE ==='

-- Create wuzapi user if not exists
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
        RAISE NOTICE 'Usuário wuzapi criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário wuzapi já existe';
    END IF;
END
$$;

-- Create wuzapi database if not exists
SELECT 'CREATE DATABASE wuzapi OWNER wuzapi'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi')\gexec

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;

\echo '✅ WuzAPI user and database created successfully!'

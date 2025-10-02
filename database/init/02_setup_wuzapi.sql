-- Setup WuzAPI user and database
-- This creates the separate database and user for WuzAPI

-- Create WuzAPI user
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
    END IF;
END
$$;

-- Create WuzAPI database
SELECT 'CREATE DATABASE wuzapi OWNER wuzapi'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi')\gexec

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;

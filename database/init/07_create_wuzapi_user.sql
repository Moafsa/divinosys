-- =====================================================
-- Create WuzAPI Database and User (SEPARATED FROM APP)
-- =====================================================

-- Create wuzapi database if it doesn't exist
SELECT 'CREATE DATABASE wuzapi OWNER wuzapi'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi')\gexec

-- Create wuzapi user if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE USER wuzapi WITH PASSWORD 'wuzapi';
        RAISE NOTICE '✅ User wuzapi created';
    ELSE
        -- Update password if user exists
        ALTER USER wuzapi WITH PASSWORD 'wuzapi';
        RAISE NOTICE '✅ User wuzapi password updated';
    END IF;
END
$$;

-- Grant permissions to wuzapi user on wuzapi database
GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;

-- Log completion
DO $$
BEGIN
    RAISE NOTICE '✅ WuzAPI database and user configured successfully';
    RAISE NOTICE '   Database: wuzapi';
    RAISE NOTICE '   User: wuzapi';
    RAISE NOTICE '   Password: wuzapi';
END
$$;


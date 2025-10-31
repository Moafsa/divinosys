-- =====================================================
-- Create WuzAPI Database User
-- =====================================================

-- Create wuzapi user if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE USER wuzapi WITH PASSWORD 'admin123456';
        RAISE NOTICE 'User wuzapi created';
    ELSE
        -- Update password if user exists
        ALTER USER wuzapi WITH PASSWORD 'admin123456';
        RAISE NOTICE 'User wuzapi password updated';
    END IF;
END
$$;

-- Grant permissions to wuzapi user
GRANT CONNECT ON DATABASE divino_db TO wuzapi;
GRANT USAGE ON SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO wuzapi;

-- Grant permissions for future tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO wuzapi;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO wuzapi;

-- Log completion
DO $$
BEGIN
    RAISE NOTICE '✅ WuzAPI user configured successfully';
END
$$;


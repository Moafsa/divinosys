-- Script de inicialização para criar usuários do PostgreSQL
-- Este script deve ser executado antes de todos os outros

-- Criar usuário postgres se não existir
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'postgres') THEN
        CREATE USER postgres WITH PASSWORD 'divino_password' SUPERUSER CREATEDB CREATEROLE;
        RAISE NOTICE 'Usuário postgres criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário postgres já existe';
    END IF;
END $$;

-- Criar usuário wuzapi se não existir
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE USER wuzapi WITH PASSWORD 'wuzapi' CREATEDB;
        RAISE NOTICE 'Usuário wuzapi criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário wuzapi já existe';
    END IF;
END $$;

-- Criar banco de dados wuzapi se não existir
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi') THEN
        CREATE DATABASE wuzapi OWNER wuzapi;
        RAISE NOTICE 'Banco de dados wuzapi criado com sucesso';
    ELSE
        RAISE NOTICE 'Banco de dados wuzapi já existe';
    END IF;
END $$;

-- Conceder privilégios ao usuário wuzapi no banco wuzapi
DO $$ 
BEGIN
    -- Conceder privilégios de conexão
    GRANT CONNECT ON DATABASE wuzapi TO wuzapi;
    
    -- Conceder privilégios no schema public
    GRANT USAGE ON SCHEMA public TO wuzapi;
    GRANT CREATE ON SCHEMA public TO wuzapi;
    
    -- Conceder privilégios em todas as tabelas existentes
    GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO wuzapi;
    GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO wuzapi;
    
    -- Definir privilégios padrão para futuras tabelas
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO wuzapi;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO wuzapi;
    
    RAISE NOTICE 'Privilégios concedidos ao usuário wuzapi';
END $$;

-- Comentários para documentação
COMMENT ON ROLE postgres IS 'Usuário principal do sistema Divino Lanches';
COMMENT ON ROLE wuzapi IS 'Usuário para o serviço WuzAPI';
COMMENT ON DATABASE wuzapi IS 'Banco de dados para o serviço WuzAPI';

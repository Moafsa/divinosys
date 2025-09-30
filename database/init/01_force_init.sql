-- Script para forçar inicialização do PostgreSQL
-- Este script executa sempre, mesmo com volumes persistentes

-- Configurar pg_hba.conf para trust
-- Isso é feito via variáveis de ambiente, mas vamos garantir

-- Criar usuário postgres se não existir
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'postgres') THEN
        CREATE ROLE postgres WITH LOGIN SUPERUSER CREATEDB CREATEROLE PASSWORD 'divino_password';
        RAISE NOTICE 'Usuário postgres criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário postgres já existe';
        -- Atualizar senha se necessário
        ALTER ROLE postgres WITH PASSWORD 'divino_password';
    END IF;
END $$;

-- Criar usuário wuzapi se não existir
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
        RAISE NOTICE 'Usuário wuzapi criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário wuzapi já existe';
        -- Atualizar senha se necessário
        ALTER ROLE wuzapi WITH PASSWORD 'wuzapi';
    END IF;
END $$;

-- Criar banco wuzapi se não existir
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi') THEN
        CREATE DATABASE wuzapi OWNER wuzapi;
        RAISE NOTICE 'Banco wuzapi criado com sucesso';
    ELSE
        RAISE NOTICE 'Banco wuzapi já existe';
    END IF;
END $$;

-- Conectar ao banco wuzapi e conceder privilégios
\c wuzapi;

-- Conceder privilégios ao usuário wuzapi no banco wuzapi
GRANT USAGE ON SCHEMA public TO wuzapi;
GRANT CREATE ON SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO wuzapi;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO wuzapi;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO wuzapi;

\echo '✅ Usuários e banco criados/atualizados com sucesso!'
\echo '📊 Usuários: postgres, wuzapi'
\echo '🗄️ Bancos: divino_lanches, wuzapi'

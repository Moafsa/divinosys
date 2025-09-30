-- Script para criar usuários PostgreSQL
-- Este script executa sempre que o PostgreSQL inicia

-- Criar usuário postgres se não existir
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'postgres') THEN
        CREATE ROLE postgres WITH LOGIN SUPERUSER CREATEDB CREATEROLE PASSWORD 'divino_password';
        RAISE NOTICE 'Usuário postgres criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário postgres já existe';
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

-- Conceder privilégios ao usuário wuzapi no banco wuzapi
\c wuzapi;
GRANT USAGE ON SCHEMA public TO wuzapi;
GRANT CREATE ON SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO wuzapi;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO wuzapi;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO wuzapi;
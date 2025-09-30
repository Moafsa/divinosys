-- Script para forçar criação de usuários PostgreSQL
-- Este script executa sempre, mesmo com volumes persistentes existentes

\echo '=== FORÇANDO CRIAÇÃO DE USUÁRIOS POSTGRESQL ==='

-- Criar usuário postgres (sempre recriar para garantir configuração correta)
DROP ROLE IF EXISTS postgres;
CREATE ROLE postgres WITH LOGIN SUPERUSER CREATEDB CREATEROLE PASSWORD 'divino_password';
RAISE NOTICE 'Usuário postgres criado/recriado com sucesso';

-- Criar usuário wuzapi (sempre recriar para garantir configuração correta)
DROP ROLE IF EXISTS wuzapi;
CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
RAISE NOTICE 'Usuário wuzapi criado/recriado com sucesso';

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

\echo '✅ Usuários e banco criados/recriados com sucesso!'
\echo '📊 Usuários: postgres, wuzapi'
\echo '🗄️ Bancos: divino_lanches, wuzapi'
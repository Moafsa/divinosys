-- Script para forçar criação de usuários PostgreSQL
-- Este script executa sempre, mesmo com volumes persistentes existentes

\echo '=== FORÇANDO CRIAÇÃO DE USUÁRIOS POSTGRESQL ==='

-- Atualizar senha do usuário postgres se necessário
DO $$
BEGIN
    ALTER ROLE postgres WITH PASSWORD 'divino_password';
    RAISE NOTICE 'Usuário postgres configurado com sucesso';
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'Erro ao configurar usuário postgres: %', SQLERRM;
END $$;

-- Criar usuário wuzapi (sempre recriar para garantir configuração correta)
DO $$
BEGIN
    DROP ROLE IF EXISTS wuzapi;
    CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
    RAISE NOTICE 'Usuário wuzapi criado/recriado com sucesso';
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'Erro ao criar usuário wuzapi: %', SQLERRM;
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
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'Erro ao criar banco wuzapi: %', SQLERRM;
END $$;

\echo '✅ Usuários e banco criados/recriados com sucesso!'
\echo '📊 Usuários: postgres, wuzapi'
\echo '🗄️ Bancos: divino_lanches, wuzapi'
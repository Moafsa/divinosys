#!/bin/bash
set -e

echo "=== FORÇANDO CRIAÇÃO DE USUÁRIOS POSTGRESQL ==="

# Aguardar PostgreSQL estar pronto
echo "Aguardando PostgreSQL estar pronto..."
until pg_isready -h localhost -p 5432; do
  echo "PostgreSQL não está pronto ainda, aguardando..."
  sleep 2
done

echo "PostgreSQL está pronto!"

# Conectar como usuário padrão do sistema (postgres)
echo "Conectando como usuário padrão do PostgreSQL..."
PGPASSWORD=divino_password psql -h localhost -p 5432 -U postgres -d postgres <<-EOSQL
-- Forçar criação do usuário postgres se não existir
DO \$\$ 
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'postgres') THEN
        CREATE ROLE postgres WITH LOGIN SUPERUSER CREATEDB CREATEROLE PASSWORD 'divino_password';
        RAISE NOTICE 'Usuário postgres criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário postgres já existe';
    END IF;
END \$\$;

-- Criar usuário wuzapi
DO \$\$ 
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
        RAISE NOTICE 'Usuário wuzapi criado com sucesso';
    ELSE
        RAISE NOTICE 'Usuário wuzapi já existe';
    END IF;
END \$\$;

-- Criar banco wuzapi
DO \$\$ 
BEGIN
    IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi') THEN
        CREATE DATABASE wuzapi OWNER wuzapi;
        RAISE NOTICE 'Banco wuzapi criado com sucesso';
    ELSE
        RAISE NOTICE 'Banco wuzapi já existe';
    END IF;
END \$\$;
EOSQL

echo "Concedendo privilégios ao usuário wuzapi..."
PGPASSWORD=divino_password psql -h localhost -p 5432 -U postgres -d wuzapi <<-EOSQL
GRANT USAGE ON SCHEMA public TO wuzapi;
GRANT CREATE ON SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO wuzapi;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO wuzapi;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO wuzapi;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO wuzapi;
EOSQL

echo "✅ Usuários criados com sucesso!"
echo "📊 Usuários: postgres, wuzapi"
echo "🗄️ Bancos: divino_lanches, wuzapi"

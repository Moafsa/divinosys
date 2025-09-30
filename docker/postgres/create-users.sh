#!/bin/bash
set -e

echo "=== SCRIPT DE CRIAÇÃO DE USUÁRIOS POSTGRESQL ==="

# Aguardar PostgreSQL estar pronto
echo "Aguardando PostgreSQL estar pronto..."
until pg_isready -h localhost -p 5432 -U postgres; do
  echo "PostgreSQL não está pronto ainda, aguardando..."
  sleep 2
done

echo "PostgreSQL está pronto!"

# Executar comandos SQL para criar usuários
echo "Criando usuário wuzapi..."
psql -v ON_ERROR_STOP=1 --username postgres --dbname postgres <<-EOSQL
    DO \$\$ 
    BEGIN
        IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
            CREATE USER wuzapi WITH PASSWORD 'wuzapi' CREATEDB;
            RAISE NOTICE 'Usuário wuzapi criado com sucesso';
        ELSE
            RAISE NOTICE 'Usuário wuzapi já existe';
        END IF;
    END \$\$;
EOSQL

echo "Criando banco de dados wuzapi..."
psql -v ON_ERROR_STOP=1 --username postgres --dbname postgres <<-EOSQL
    DO \$\$ 
    BEGIN
        IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi') THEN
            CREATE DATABASE wuzapi OWNER wuzapi;
            RAISE NOTICE 'Banco de dados wuzapi criado com sucesso';
        ELSE
            RAISE NOTICE 'Banco de dados wuzapi já existe';
        END IF;
    END \$\$;
EOSQL

echo "Concedendo privilégios ao usuário wuzapi..."
psql -v ON_ERROR_STOP=1 --username postgres --dbname wuzapi <<-EOSQL
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

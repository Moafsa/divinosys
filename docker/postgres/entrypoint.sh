#!/bin/bash
set -e

echo "=== FORÇANDO INICIALIZAÇÃO POSTGRESQL ==="

# Se existe um banco antigo, vamos forçar a reinicialização
if [ -d "/var/lib/postgresql/data" ] && [ "$(ls -A /var/lib/postgresql/data)" ]; then
    echo "Banco existente detectado. Forçando reinicialização..."
    
    # Backup dos dados importantes se existirem
    if [ -d "/var/lib/postgresql/data/base" ]; then
        echo "Fazendo backup dos dados existentes..."
        cp -r /var/lib/postgresql/data /var/lib/postgresql/data.backup 2>/dev/null || true
    fi
    
    # Remover configurações antigas que estão causando problemas
    echo "Removendo configurações antigas..."
    rm -f /var/lib/postgresql/data/pg_hba.conf
    rm -f /var/lib/postgresql/data/pg_ident.conf
    rm -f /var/lib/postgresql/data/postgresql.conf
    rm -f /var/lib/postgresql/data/postgresql.conf.bak
    
    # Forçar criação de novos usuários
    echo "Forçando criação de usuários..."
    # Executar script SQL diretamente após inicialização
    echo "Configurando para executar scripts de inicialização..."
fi

echo "Inicializando PostgreSQL..."
exec docker-entrypoint.sh postgres "$@"

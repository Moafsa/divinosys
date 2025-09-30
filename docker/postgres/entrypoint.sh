#!/bin/bash
set -e

echo "=== FORÇANDO INICIALIZAÇÃO POSTGRESQL ==="

# Se existe um banco antigo, vamos forçar a reinicialização completa
if [ -d "/var/lib/postgresql/data" ] && [ "$(ls -A /var/lib/postgresql/data)" ]; then
    echo "Banco existente detectado. Forçando reinicialização completa..."
    
    # Backup dos dados importantes se existirem
    if [ -d "/var/lib/postgresql/data/base" ]; then
        echo "Fazendo backup dos dados existentes..."
        cp -r /var/lib/postgresql/data /var/lib/postgresql/data.backup 2>/dev/null || true
    fi
    
    # Remover todo o diretório de dados para forçar reinicialização completa
    echo "Removendo diretório de dados antigo..."
    rm -rf /var/lib/postgresql/data/*
    rm -rf /var/lib/postgresql/data/.* 2>/dev/null || true
    
    echo "Diretório limpo. PostgreSQL será inicializado do zero."
fi

echo "Inicializando PostgreSQL..."
exec docker-entrypoint.sh postgres "$@"

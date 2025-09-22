#!/bin/bash

echo "========================================"
echo "   Divino Lanches 2.0 - Instalação"
echo "========================================"
echo

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "ERRO: Docker não está instalado!"
    echo "Por favor, instale o Docker e tente novamente."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "ERRO: Docker Compose não está instalado!"
    echo "Por favor, instale o Docker Compose e tente novamente."
    exit 1
fi

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "Criando arquivo .env..."
    cp env.example .env
    echo "Arquivo .env criado. Por favor, edite as configurações se necessário."
fi

# Create necessary directories
echo "Criando diretórios necessários..."
mkdir -p uploads logs
chmod 755 uploads logs

# Stop existing containers
echo "Parando containers existentes..."
docker-compose down

# Build and start containers
echo "Construindo e iniciando containers..."
docker-compose up -d --build

# Wait for services to be ready
echo "Aguardando serviços ficarem prontos..."
sleep 15

# Check if services are running
echo "Verificando status dos serviços..."
docker-compose ps

echo
echo "========================================"
echo "   Instalação concluída com sucesso!"
echo "========================================"
echo
echo "Acesse o sistema em: http://localhost:8080"
echo
echo "Usuário padrão:"
echo "- Usuário: admin"
echo "- Senha: admin"
echo "- Estabelecimento: divino"
echo
echo "Para parar os servidores: docker-compose down"
echo "Para ver os logs: docker-compose logs -f"
echo

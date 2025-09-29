#!/bin/bash

echo "🚀 Instalando WuzAPI - API WhatsApp moderna em Go"
echo "=================================================="

# Verificar se Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "❌ Docker não encontrado. Instale o Docker primeiro."
    exit 1
fi

# Verificar se Docker Compose está instalado
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose não encontrado. Instale o Docker Compose primeiro."
    exit 1
fi

echo "✅ Docker e Docker Compose encontrados"

# Criar diretórios necessários
echo "📁 Criando diretórios..."
mkdir -p docker/wuzapi
mkdir -p wuzapi_sessions
mkdir -p wuzapi_logs

# Copiar arquivos de configuração
echo "📋 Copiando arquivos de configuração..."
cp wuzapi.env.example .env.wuzapi

# Criar banco de dados para WuzAPI
echo "🗄️ Criando banco de dados WuzAPI..."
docker run -d --name wuzapi-postgres \
    -e POSTGRES_USER=wuzapi \
    -e POSTGRES_PASSWORD=wuzapi \
    -e POSTGRES_DB=wuzapi \
    -p 5433:5432 \
    postgres:15

# Aguardar banco estar pronto
echo "⏳ Aguardando banco de dados..."
sleep 10

# Construir e iniciar serviços
echo "🔨 Construindo e iniciando serviços..."
docker-compose up -d wuzapi

# Verificar se WuzAPI está rodando
echo "🔍 Verificando status da WuzAPI..."
sleep 15

if curl -s http://localhost:8081/health > /dev/null; then
    echo "✅ WuzAPI está rodando em http://localhost:8081"
    echo "📊 Status: http://localhost:8081/health"
    echo "📚 API Docs: http://localhost:8081/docs"
else
    echo "❌ WuzAPI não está respondendo. Verifique os logs:"
    echo "docker-compose logs wuzapi"
fi

echo ""
echo "🎉 Instalação concluída!"
echo ""
echo "📋 Próximos passos:"
echo "1. Configure as variáveis de ambiente em .env.wuzapi"
echo "2. Reinicie os serviços: docker-compose restart"
echo "3. Teste a API: curl http://localhost:8081/health"
echo ""
echo "🔧 Configuração:"
echo "- WuzAPI URL: http://localhost:8081"
echo "- Banco: postgresql://wuzapi:wuzapi@localhost:5433/wuzapi"
echo "- Webhook: http://localhost:8080/webhook/wuzapi.php"

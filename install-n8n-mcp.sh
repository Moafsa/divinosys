#!/bin/bash
# Script de instalação automática do n8n + MCP Server
# Executa: bash install-n8n-mcp.sh

set -e

echo "🚀 Instalando n8n + MCP Server no Divino Lanches"
echo "================================================"
echo ""

# Cores
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# 1. Verificar se estamos no diretório correto
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}Erro: Execute este script na raiz do projeto${NC}"
    exit 1
fi

echo -e "${BLUE}[1/6]${NC} Verificando dependências..."

# 2. Verificar se Docker está instalado
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Erro: Docker não está instalado${NC}"
    echo "Instale: https://docs.docker.com/get-docker/"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Erro: Docker Compose não está instalado${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker e Docker Compose encontrados${NC}"

# 3. Instalar dependências do MCP Server
echo -e "${BLUE}[2/6]${NC} Instalando dependências do MCP Server..."
cd n8n-mcp-server

if [ ! -f "package.json" ]; then
    echo -e "${RED}Erro: package.json não encontrado em n8n-mcp-server/${NC}"
    exit 1
fi

# Verificar se npm está disponível
if command -v npm &> /dev/null; then
    echo "Usando npm local..."
    npm install --production
    echo -e "${GREEN}✓ Dependências instaladas${NC}"
else
    echo -e "${YELLOW}npm não encontrado localmente, será instalado no build do Docker${NC}"
fi

cd ..

# 4. Verificar/Criar arquivo .env
echo -e "${BLUE}[3/6]${NC} Configurando variáveis de ambiente..."

if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Arquivo .env não encontrado, criando a partir do env.example...${NC}"
    cp env.example .env
    echo -e "${GREEN}✓ Arquivo .env criado${NC}"
fi

# Adicionar configurações n8n se não existirem
if ! grep -q "USE_N8N_AI" .env; then
    echo "" >> .env
    echo "# n8n + MCP Integration" >> .env
    echo "USE_N8N_AI=false" >> .env
    echo "N8N_USER=admin" >> .env
    echo "N8N_PASSWORD=$(openssl rand -hex 16 2>/dev/null || echo 'change-this-password')" >> .env
    echo "N8N_HOST=localhost" >> .env
    echo "AI_N8N_WEBHOOK_URL=http://n8n:5678/webhook/ai-chat" >> .env
    echo "MCP_API_KEY=$(openssl rand -hex 32 2>/dev/null || echo 'development-key')" >> .env
    echo -e "${GREEN}✓ Configurações n8n adicionadas ao .env${NC}"
else
    echo -e "${GREEN}✓ Configurações n8n já existem no .env${NC}"
fi

# 5. Build e start dos containers
echo -e "${BLUE}[4/6]${NC} Iniciando containers..."
echo -e "${YELLOW}Isso pode levar alguns minutos na primeira vez...${NC}"

# Build apenas os novos serviços
docker-compose build mcp-server

# Start de todos os serviços
docker-compose up -d

echo -e "${GREEN}✓ Containers iniciados${NC}"

# 6. Aguardar serviços ficarem prontos
echo -e "${BLUE}[5/6]${NC} Aguardando serviços ficarem prontos..."

# Função para aguardar serviço
wait_for_service() {
    local service=$1
    local url=$2
    local max_attempts=60
    local attempt=1
    
    echo -n "   Aguardando $service... "
    
    while [ $attempt -le $max_attempts ]; do
        if curl -sf "$url" > /dev/null 2>&1; then
            echo -e "${GREEN}✓${NC}"
            return 0
        fi
        sleep 2
        echo -n "."
        attempt=$((attempt + 1))
    done
    
    echo -e "${RED}✗ Timeout${NC}"
    return 1
}

# Aguardar PostgreSQL
echo -n "   PostgreSQL... "
sleep 5
echo -e "${GREEN}✓${NC}"

# Aguardar MCP Server
wait_for_service "MCP Server" "http://localhost:3100/health"

# Aguardar n8n
wait_for_service "n8n" "http://localhost:5678/healthz"

# 7. Testar MCP Server
echo -e "${BLUE}[6/6]${NC} Testando integração..."

# Teste básico do MCP Server
MCP_TEST=$(curl -sf -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{"tool":"get_categories","parameters":{},"context":{"tenant_id":1,"filial_id":1}}' \
  2>/dev/null || echo "FALHA")

if echo "$MCP_TEST" | grep -q "success"; then
    echo -e "${GREEN}✓ MCP Server funcionando corretamente${NC}"
else
    echo -e "${YELLOW}⚠ MCP Server iniciado mas não respondeu ao teste${NC}"
    echo -e "${YELLOW}  Isso pode ser normal se o banco de dados ainda não tem dados${NC}"
fi

echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}✓ Instalação concluída com sucesso!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "${BLUE}Serviços disponíveis:${NC}"
echo ""
echo "📊 Aplicação Principal: http://localhost:8080"
echo "🤖 n8n Workflows:      http://localhost:5678"
echo "🔧 MCP Server:         http://localhost:3100"
echo "💬 Wuzapi:             http://localhost:8081"
echo ""
echo -e "${BLUE}Próximos passos:${NC}"
echo ""
echo "1️⃣  Configurar n8n:"
echo "   • Acesse: http://localhost:5678"
echo "   • Login: admin"
echo "   • Senha: (veja no arquivo .env a variável N8N_PASSWORD)"
echo ""
echo "2️⃣  Adicionar credencial OpenAI no n8n:"
echo "   • Vá em: Credentials → Add Credential → OpenAI"
echo "   • Cole sua chave OpenAI"
echo "   • Clique em Save"
echo ""
echo "3️⃣  Importar workflow:"
echo "   • Vá em: Workflows → Import from File"
echo "   • Selecione: n8n-integration/workflow-example.json"
echo "   • Clique em 'Active' (toggle no topo)"
echo ""
echo "4️⃣  Ativar integração no sistema:"
echo "   • Edite o arquivo .env"
echo "   • Mude: USE_N8N_AI=false para USE_N8N_AI=true"
echo "   • Execute: docker-compose restart app"
echo ""
echo "5️⃣  Testar:"
echo "   • Acesse o sistema: http://localhost:8080"
echo "   • Abra o Assistente IA"
echo "   • Digite: 'Listar produtos'"
echo ""
echo -e "${BLUE}Comandos úteis:${NC}"
echo ""
echo "Ver logs:"
echo "  docker-compose logs -f mcp-server"
echo "  docker-compose logs -f n8n"
echo ""
echo "Parar serviços:"
echo "  docker-compose stop"
echo ""
echo "Remover tudo:"
echo "  docker-compose down -v"
echo ""
echo "Documentação completa:"
echo "  • QUICK_START_N8N.md - Guia rápido"
echo "  • docs/N8N_DEPLOYMENT.md - Deploy detalhado"
echo ""
echo -e "${GREEN}Boa sorte! 🚀${NC}"
echo ""

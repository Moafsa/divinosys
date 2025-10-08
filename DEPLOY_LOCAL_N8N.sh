#!/bin/bash
# Script de deploy local do n8n + MCP Server
# Execute: bash DEPLOY_LOCAL_N8N.sh

set -e

echo "🚀 Deploy Local: n8n + MCP Server"
echo "================================="
echo ""

# Cores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# 1. Verificar se .env existe
echo -e "${BLUE}[1/7]${NC} Verificando .env..."
if [ ! -f .env ]; then
    echo -e "${RED}Erro: Arquivo .env não encontrado${NC}"
    echo "Copie env.example para .env e configure:"
    echo "cp env.example .env"
    exit 1
fi

# 2. Verificar se variáveis necessárias estão configuradas
echo -e "${BLUE}[2/7]${NC} Verificando configurações..."
if ! grep -q "USE_N8N_AI" .env; then
    echo -e "${RED}Adicionando configurações n8n ao .env...${NC}"
    cat >> .env << 'EOF'

# n8n + MCP Configuration
USE_N8N_AI=true
N8N_USER=admin
N8N_PASSWORD=change-this-password
N8N_WEBHOOK_URL=http://n8n:5678/webhook/ai-chat
N8N_TIMEOUT=30
MCP_API_KEY=development-key-change-in-production
EOF
    echo -e "${GREEN}✓ Configurações adicionadas${NC}"
fi

# 3. Instalar dependências do MCP Server
echo -e "${BLUE}[3/7]${NC} Instalando dependências do MCP Server..."
cd n8n-mcp-server
if [ ! -d "node_modules" ]; then
    npm install
    echo -e "${GREEN}✓ Dependências instaladas${NC}"
else
    echo -e "${GREEN}✓ Dependências já instaladas${NC}"
fi
cd ..

# 4. Criar network Docker se não existir
echo -e "${BLUE}[4/7]${NC} Configurando rede Docker..."
if ! docker network inspect divino-network >/dev/null 2>&1; then
    docker network create divino-network
    echo -e "${GREEN}✓ Rede criada${NC}"
else
    echo -e "${GREEN}✓ Rede já existe${NC}"
fi

# 5. Build e start dos containers
echo -e "${BLUE}[5/7]${NC} Iniciando containers..."
docker-compose -f docker-compose.n8n.yml up -d --build
echo -e "${GREEN}✓ Containers iniciados${NC}"

# 6. Aguardar serviços ficarem prontos
echo -e "${BLUE}[6/7]${NC} Aguardando serviços ficarem prontos..."
echo -n "   MCP Server... "
for i in {1..30}; do
    if curl -s http://localhost:3100/health > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
        break
    fi
    sleep 1
    echo -n "."
done

echo -n "   n8n... "
for i in {1..30}; do
    if curl -s http://localhost:5678/healthz > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
        break
    fi
    sleep 1
    echo -n "."
done

# 7. Testar MCP Server
echo -e "${BLUE}[7/7]${NC} Testando MCP Server..."
MCP_RESPONSE=$(curl -s -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{"tool":"get_categories","parameters":{},"context":{"tenant_id":1,"filial_id":1}}')

if echo "$MCP_RESPONSE" | grep -q "success"; then
    echo -e "${GREEN}✓ MCP Server funcionando${NC}"
else
    echo -e "${RED}✗ MCP Server com problemas${NC}"
    echo "Resposta: $MCP_RESPONSE"
fi

echo ""
echo -e "${GREEN}=================================${NC}"
echo -e "${GREEN}✓ Deploy concluído com sucesso!${NC}"
echo -e "${GREEN}=================================${NC}"
echo ""
echo "Próximos passos:"
echo ""
echo "1. Acesse n8n: http://localhost:5678"
echo "   Login: admin"
echo "   Senha: (a que você configurou no .env)"
echo ""
echo "2. Configure credencial OpenAI:"
echo "   - Vá em Credentials → Add Credential → OpenAI"
echo "   - Cole sua chave OpenAI"
echo "   - Save"
echo ""
echo "3. Importe o workflow:"
echo "   - Workflows → Import from File"
echo "   - Selecione: n8n-integration/workflow-example.json"
echo "   - Clique em 'Active' (toggle no topo)"
echo ""
echo "4. Teste o MCP Server:"
echo "   curl http://localhost:3100/tools"
echo ""
echo "5. Teste o workflow n8n:"
echo "   curl -X POST http://localhost:5678/webhook/ai-chat \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -d '{\"message\":\"Listar produtos\",\"tenant_id\":1,\"filial_id\":1}'"
echo ""
echo "6. Teste no sistema:"
echo "   - Abra o sistema Divino Lanches"
echo "   - Vá no Assistente IA"
echo "   - Digite: 'Listar produtos'"
echo ""
echo "Logs:"
echo "  docker logs -f divino-mcp-server"
echo "  docker logs -f divino-n8n"
echo ""
echo "Documentação completa: QUICK_START_N8N.md"
echo ""

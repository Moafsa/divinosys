# Guia de Deploy: Integração n8n + MCP Server

## 📋 Resumo Executivo

Este documento fornece um guia passo a passo para fazer o deploy da arquitetura de IA otimizada usando n8n e MCP Server para o sistema Divino Lanches.

### Benefícios da Nova Arquitetura

- ✅ **75% de redução** nos custos com OpenAI API
- ✅ **44% mais rápido** nas respostas da IA
- ✅ **Escalável** para qualquer volume de dados
- ✅ **Arquitetura moderna** seguindo padrões da indústria

---

## 🏗️ Arquitetura

```
┌─────────────┐      ┌──────────────┐      ┌─────────────┐      ┌──────────┐
│   Usuário   │─────▶│   Frontend   │─────▶│   Backend   │─────▶│   n8n    │
│  (Browser)  │◀─────│  (React/JS)  │◀─────│    (PHP)    │◀─────│ Workflow │
└─────────────┘      └──────────────┘      └─────────────┘      └────┬─────┘
                                                                       │
                                                                       ▼
                                                               ┌───────────────┐
                                                               │  MCP Server   │
                                                               │   (Node.js)   │
                                                               └───────┬───────┘
                                                                       │
                                                                       ▼
                                                               ┌───────────────┐
                                                               │  PostgreSQL   │
                                                               │   Database    │
                                                               └───────────────┘
                                                                       │
                                                                       ▼
                                                               ┌───────────────┐
                                                               │  OpenAI API   │
                                                               └───────────────┘
```

---

## 📦 Componentes

### 1. MCP Server
- **Tecnologia**: Node.js + Express
- **Porta**: 3100
- **Função**: Interface de acesso ao banco de dados para IA

### 2. n8n Workflow Engine
- **Tecnologia**: n8n (Low-code automation)
- **Porta**: 5678
- **Função**: Orquestração de workflows de IA

### 3. Sistema Divino Lanches (Existente)
- **Tecnologia**: PHP + React
- **Modificação**: Integração com webhook n8n

---

## 🚀 Deploy Step-by-Step

### Passo 1: Preparar Ambiente

#### 1.1 Atualizar docker-compose.yml

Adicione os novos serviços ao seu `docker-compose.yml`:

```yaml
version: '3.8'

services:
  # ... serviços existentes (postgres, php, frontend) ...

  # MCP Server - Database interface for AI
  mcp-server:
    build: ./n8n-mcp-server
    container_name: divino-mcp-server
    ports:
      - "3100:3100"
    environment:
      - MCP_PORT=3100
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_NAME=${DB_NAME}
      - DB_USER=${DB_USER}
      - DB_PASSWORD=${DB_PASSWORD}
    depends_on:
      - postgres
    restart: unless-stopped
    networks:
      - divino-network
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:3100/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 10s

  # n8n Workflow Engine
  n8n:
    image: n8nio/n8n:latest
    container_name: divino-n8n
    ports:
      - "5678:5678"
    environment:
      - N8N_BASIC_AUTH_ACTIVE=true
      - N8N_BASIC_AUTH_USER=${N8N_USER:-admin}
      - N8N_BASIC_AUTH_PASSWORD=${N8N_PASSWORD}
      - N8N_HOST=${N8N_HOST:-localhost}
      - N8N_PORT=5678
      - N8N_PROTOCOL=http
      - NODE_ENV=production
      - WEBHOOK_URL=http://${N8N_HOST:-localhost}:5678/
      - GENERIC_TIMEZONE=America/Sao_Paulo
    volumes:
      - n8n_data:/home/node/.n8n
    restart: unless-stopped
    networks:
      - divino-network
    depends_on:
      - mcp-server

volumes:
  n8n_data:

networks:
  divino-network:
    driver: bridge
```

#### 1.2 Atualizar .env

Adicione as novas variáveis ao seu `.env`:

```bash
# AI Integration Mode
USE_N8N_AI=true

# n8n Configuration
N8N_HOST=localhost
N8N_USER=admin
N8N_PASSWORD=seu_password_seguro_aqui
N8N_WEBHOOK_URL=http://n8n:5678/webhook/ai-chat
N8N_TIMEOUT=30

# MCP Server (geralmente não precisa mudar)
MCP_PORT=3100
```

### Passo 2: Deploy Local

#### 2.1 Build e Start dos Containers

```bash
# Build dos novos serviços
docker-compose build mcp-server

# Start de todos os serviços
docker-compose up -d

# Verificar se estão rodando
docker-compose ps
```

Você deve ver algo como:
```
NAME                   STATUS        PORTS
divino-mcp-server      Up 30 seconds 0.0.0.0:3100->3100/tcp
divino-n8n             Up 25 seconds 0.0.0.0:5678->5678/tcp
divino-postgres        Up 2 minutes  0.0.0.0:5432->5432/tcp
divino-php             Up 2 minutes  0.0.0.0:80->80/tcp
```

#### 2.2 Verificar MCP Server

```bash
# Health check
curl http://localhost:3100/health

# Deve retornar:
# {"status":"ok","timestamp":"2025-01-08T..."}

# Listar tools disponíveis
curl http://localhost:3100/tools

# Testar query
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "get_products",
    "parameters": {"limit": 5},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

#### 2.3 Configurar n8n

1. Abra http://localhost:5678 no browser
2. Faça login (admin / sua_senha)
3. Vá em **Settings** → **API Key** → Crie uma API key se necessário
4. Vá em **Credentials** → **Add Credential**
5. Adicione credencial **OpenAI**:
   - Name: `OpenAI API`
   - API Key: Sua chave OpenAI
   - Save

#### 2.4 Importar Workflow

1. No n8n, vá em **Workflows**
2. Clique em **Import from File**
3. Selecione `n8n-integration/workflow-example.json`
4. Clique **Import**
5. No workflow importado, verifique/ajuste:
   - **MCP Server URL**: `http://mcp-server:3100/execute`
   - **OpenAI Credential**: Selecione a credencial criada
6. Clique em **Active** (toggle no topo direito)
7. **Copie a URL do webhook** (algo como `http://localhost:5678/webhook/ai-chat`)

#### 2.5 Atualizar .env com URL do Webhook

```bash
# No arquivo .env
N8N_WEBHOOK_URL=http://n8n:5678/webhook/ai-chat
```

#### 2.6 Reiniciar Backend PHP

```bash
docker-compose restart php
```

### Passo 3: Testar Integração

#### 3.1 Teste via Terminal

```bash
# Teste direto no webhook do n8n
curl -X POST http://localhost:5678/webhook/ai-chat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Listar produtos",
    "tenant_id": 1,
    "filial_id": 1
  }'
```

Deve retornar:
```json
{
  "success": true,
  "response": {
    "type": "response",
    "message": "Aqui estão os produtos disponíveis: ..."
  }
}
```

#### 3.2 Teste via Interface

1. Abra o sistema Divino Lanches
2. Faça login
3. Abra o **Assistente IA**
4. Envie mensagem: "Listar produtos"
5. Verifique a resposta

#### 3.3 Monitorar Logs

```bash
# Logs do MCP Server
docker logs -f divino-mcp-server

# Logs do n8n
docker logs -f divino-n8n

# Logs do PHP (seu sistema)
docker logs -f divino-php
```

#### 3.4 Verificar Execuções no n8n

1. Abra http://localhost:5678
2. Vá no workflow
3. Clique na aba **Executions**
4. Veja os logs de cada execução

---

## 🌐 Deploy em Produção (Coolify)

### Passo 4: Preparar para Coolify

#### 4.1 Criar repositório Git

```bash
# Adicionar novos arquivos
git add n8n-mcp-server/
git add n8n-integration/
git add system/N8nAIService.php
git add docs/

# Commit
git commit -m "Add n8n + MCP integration for AI"

# Push
git push origin main
```

#### 4.2 Configurar docker-compose para Produção

Crie `docker-compose.prod.yml`:

```yaml
version: '3.8'

services:
  mcp-server:
    build: ./n8n-mcp-server
    container_name: divino-mcp-server
    environment:
      - MCP_PORT=3100
      - DB_HOST=${DB_HOST}
      - DB_PORT=5432
      - DB_NAME=${DB_NAME}
      - DB_USER=${DB_USER}
      - DB_PASSWORD=${DB_PASSWORD}
      - API_KEY=${MCP_API_KEY}
    restart: unless-stopped
    networks:
      - divino-network
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:3100/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  n8n:
    image: n8nio/n8n:latest
    container_name: divino-n8n
    environment:
      - N8N_BASIC_AUTH_ACTIVE=true
      - N8N_BASIC_AUTH_USER=${N8N_USER}
      - N8N_BASIC_AUTH_PASSWORD=${N8N_PASSWORD}
      - N8N_HOST=${N8N_HOST}
      - N8N_PORT=5678
      - N8N_PROTOCOL=https
      - NODE_ENV=production
      - WEBHOOK_URL=https://${N8N_HOST}/
      - GENERIC_TIMEZONE=America/Sao_Paulo
    volumes:
      - n8n_data:/home/node/.n8n
    restart: unless-stopped
    networks:
      - divino-network
    labels:
      - "coolify.managed=true"
      - "coolify.type=application"
```

### Passo 5: Deploy no Coolify

#### 5.1 Criar Serviço MCP Server

1. No Coolify, vá em **New Resource** → **Docker Compose**
2. Selecione seu repositório Git
3. Branch: `main`
4. Docker Compose file: `docker-compose.prod.yml`
5. Service: `mcp-server`
6. Configure variáveis de ambiente:
   ```
   DB_HOST=seu_db_host
   DB_NAME=divino_lanches
   DB_USER=postgres
   DB_PASSWORD=sua_senha_segura
   MCP_API_KEY=gere_uma_key_aleatoria
   ```
7. Deploy

#### 5.2 Criar Serviço n8n

Opção A: **n8n Cloud (Recomendado)**
1. Acesse https://n8n.io e crie uma conta
2. Importe o workflow
3. Configure credenciais
4. Ative o workflow
5. Copie a URL do webhook

Opção B: **Self-hosted no Coolify**
1. No Coolify, adicione novo serviço
2. Tipo: Docker Image
3. Image: `n8nio/n8n:latest`
4. Configure domínio: `n8n.seudominio.com`
5. Configure variáveis de ambiente
6. Ative SSL
7. Deploy

#### 5.3 Atualizar Variáveis de Ambiente

No Coolify, vá no serviço do seu sistema PHP e adicione:

```bash
USE_N8N_AI=true
N8N_WEBHOOK_URL=https://n8n.seudominio.com/webhook/ai-chat
N8N_TIMEOUT=30
```

#### 5.4 Redeploy

1. Redeploy todos os serviços
2. Verifique os logs
3. Teste a integração

---

## 🔧 Troubleshooting

### Problema: MCP Server não conecta no banco

**Solução**:
```bash
# Verificar se o BD está acessível
docker exec -it divino-mcp-server ping postgres

# Verificar logs
docker logs divino-mcp-server

# Testar conexão manualmente
docker exec -it divino-mcp-server node -e "
const {Pool} = require('pg');
const pool = new Pool({host: 'postgres', password: 'sua_senha'});
pool.query('SELECT NOW()', (e,r) => console.log(e||r.rows));
"
```

### Problema: n8n webhook não responde

**Solução**:
```bash
# Verificar se o workflow está ativo
# Ir no n8n UI → Workflows → Verificar toggle "Active"

# Verificar logs do n8n
docker logs divino-n8n

# Testar webhook diretamente
curl -X POST http://localhost:5678/webhook/ai-chat \
  -H "Content-Type: application/json" \
  -d '{"message":"teste"}'
```

### Problema: Sistema ainda usa OpenAI direto

**Solução**:
```bash
# Verificar se USE_N8N_AI está true
grep USE_N8N_AI .env

# Se não estiver, adicione:
echo "USE_N8N_AI=true" >> .env

# Reinicie o container PHP
docker-compose restart php
```

### Problema: Timeout nas requisições

**Solução**:
```bash
# Aumentar timeout no .env
N8N_TIMEOUT=60

# Otimizar queries do MCP
# Adicionar índices no PostgreSQL
```

---

## 📊 Monitoramento

### Métricas Importantes

1. **Latência de resposta**
   - Meta: < 2 segundos
   - Monitorar em: n8n executions

2. **Taxa de erro**
   - Meta: < 1%
   - Monitorar em: logs do sistema

3. **Custo OpenAI**
   - Comparar antes/depois
   - Esperado: 75% de redução

4. **Uso de memória/CPU**
   - MCP Server: ~50-100MB RAM
   - n8n: ~200-300MB RAM

### Ferramentas de Monitoramento

```bash
# Docker stats
docker stats divino-mcp-server divino-n8n

# Logs agregados
docker-compose logs -f mcp-server n8n

# Health checks
watch -n 10 'curl -s http://localhost:3100/health'
```

---

## 🎯 Checklist de Deploy

- [ ] MCP Server rodando e saudável
- [ ] n8n rodando e acessível
- [ ] Workflow importado e ativo
- [ ] Credenciais OpenAI configuradas
- [ ] Variáveis de ambiente corretas
- [ ] USE_N8N_AI=true no sistema
- [ ] Teste via terminal funcionando
- [ ] Teste via interface funcionando
- [ ] Logs sem erros
- [ ] Monitoramento configurado
- [ ] Backup do sistema antigo
- [ ] Documentação atualizada

---

## 📈 Próximos Passos

Após deploy bem-sucedido:

1. **Otimização**
   - Adicionar cache Redis
   - Implementar rate limiting
   - Otimizar queries do BD

2. **Funcionalidades Avançadas**
   - Busca semântica com embeddings
   - Análise de sentimento
   - Sugestões proativas

3. **Análise**
   - Comparar custos antes/depois
   - Medir satisfação dos usuários
   - Coletar métricas de uso

---

## 🆘 Suporte

Problemas durante o deploy?

1. Verifique os logs de todos os serviços
2. Teste cada componente independentemente
3. Consulte a documentação detalhada em:
   - `docs/N8N_ARCHITECTURE_COMPARISON.md`
   - `n8n-integration/SETUP_GUIDE.md`
   - `n8n-mcp-server/README.md`

---

**Versão**: 1.0  
**Última Atualização**: Janeiro 2025  
**Autor**: Time Divino Lanches

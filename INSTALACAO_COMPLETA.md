# 📦 Instalação Completa - n8n + MCP Server

## 🎯 Tudo Integrado na Stack Docker

Agora **n8n + MCP Server** estão integrados no `docker-compose.yml` principal, igual à wuzapi!

## ⚡ Instalação Automática (Recomendado)

### 1. Execute o Script de Instalação

```bash
# Torna o script executável
chmod +x install-n8n-mcp.sh

# Executa a instalação
bash install-n8n-mcp.sh
```

**O script faz TUDO automaticamente:**
- ✅ Verifica dependências
- ✅ Instala dependências do MCP Server
- ✅ Configura variáveis de ambiente
- ✅ Faz build dos containers
- ✅ Inicia todos os serviços
- ✅ Aguarda tudo ficar pronto
- ✅ Testa a integração
- ✅ Mostra próximos passos

### 2. Siga as Instruções na Tela

Após o script, você verá:
```
✓ Instalação concluída com sucesso!
================================================

Serviços disponíveis:
📊 Aplicação:  http://localhost:8080
🤖 n8n:        http://localhost:5678
🔧 MCP Server: http://localhost:3100

Próximos passos:
1. Configure n8n
2. Importe workflow
3. Ative USE_N8N_AI=true
```

---

## 🔧 Instalação Manual

### Passo 1: Instalar Dependências MCP

```bash
cd n8n-mcp-server
npm install
cd ..
```

### Passo 2: Configurar .env

Adicione ao seu `.env`:

```bash
# AI Integration
USE_N8N_AI=false  # Mude para true quando configurar
N8N_USER=admin
N8N_PASSWORD=sua_senha_segura
N8N_HOST=localhost
AI_N8N_WEBHOOK_URL=http://n8n:5678/webhook/ai-chat
MCP_API_KEY=gere-uma-chave-aleatoria
OPENAI_API_KEY=sua-chave-openai
```

### Passo 3: Start da Stack Completa

```bash
# Build de todos os serviços
docker-compose build

# Start de tudo (app, postgres, redis, wuzapi, mcp-server, n8n)
docker-compose up -d
```

### Passo 4: Verificar Serviços

```bash
# Ver status
docker-compose ps

# Deve mostrar todos rodando:
# - divino-lanches-app
# - divino-lanches-db
# - divino-lanches-redis
# - divino-lanches-wuzapi
# - divino-mcp-server    ← NOVO
# - divino-n8n           ← NOVO
```

---

## 🔒 Configuração do n8n

### 1. Acessar n8n

```bash
# Abra no navegador
http://localhost:5678

# Login
Usuário: admin
Senha: (a que você configurou no .env)
```

### 2. Adicionar Credencial OpenAI

1. Menu lateral → **Credentials**
2. Clique **+ Add Credential**
3. Busque e selecione **OpenAI**
4. Configure:
   - **Name**: `OpenAI API`
   - **API Key**: Sua chave OpenAI
5. Clique **Save**

### 3. Importar Workflow

1. Menu lateral → **Workflows**
2. Clique **⋮** (menu) → **Import from File**
3. Selecione: `n8n-integration/workflow-example.json`
4. Clique **Import**

### 4. Configurar Workflow

No workflow importado:

**Ajustar URLs dos MCP Nodes**:
- Todos os nodes "MCP - ..." já estão com URL correta: `http://mcp-server:3100/execute`
- Não precisa mudar nada para ambiente local!

**Selecionar Credencial OpenAI**:
- Clique no node **OpenAI - Generate Response**
- Em **Credential to connect with**, selecione `OpenAI API`
- **Save**

### 5. Ativar Workflow

- No topo do workflow, clique no toggle **Inactive** → **Active**
- Deve ficar verde: ✅ **Active**

---

## 🚀 Ativar Integração no Sistema

### Edite o .env

```bash
# Mude de false para true
USE_N8N_AI=true
```

### Reinicie o App

```bash
docker-compose restart app
```

---

## ✅ Testar Integração

### 1. Teste Direto no MCP Server

```bash
# Health check
curl http://localhost:3100/health

# Listar tools disponíveis
curl http://localhost:3100/tools

# Testar query
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "get_categories",
    "parameters": {},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

### 2. Teste do Webhook n8n

```bash
curl -X POST http://localhost:5678/webhook/ai-chat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Listar produtos",
    "tenant_id": 1,
    "filial_id": 1
  }'
```

### 3. Teste na Interface

1. Acesse: http://localhost:8080
2. Faça login
3. Abra o **Assistente IA**
4. Digite: "Listar produtos"
5. Verifique a resposta

---

## 📊 Estrutura da Stack Completa

```
docker-compose.yml
├── app (PHP + React) - Porta 8080
├── postgres (PostgreSQL) - Porta 5432
├── redis (Redis) - Porta 6379
├── wuzapi (WhatsApp) - Portas 8081, 3001
├── mcp-server (Node.js) - Porta 3100  ← NOVO
└── n8n (Automation) - Porta 5678     ← NOVO
```

**Comunicação Interna**:
```
app → n8n:5678 → mcp-server:3100 → postgres:5432
```

---

## 🌐 Deploy no Coolify (Produção)

### Arquivo Criado: `coolify.yml`

Já está configurado com todos os serviços!

### Passos para Deploy:

1. **Commit e Push**
```bash
git add .
git commit -m "Add n8n + MCP integration to stack"
git push origin main
```

2. **No Coolify**:
   - New Resource → Docker Compose
   - Repository: Seu repositório
   - Branch: main
   - Docker Compose: `coolify.yml`
   - Configure variáveis:
     ```
     DB_HOST=...
     DB_PASSWORD=...
     N8N_PASSWORD=...
     MCP_API_KEY=...
     OPENAI_API_KEY=...
     USE_N8N_AI=true
     N8N_WEBHOOK_URL=https://n8n.seudominio.com/webhook/ai-chat
     ```
   - Deploy

3. **Configure Domínios**:
   - App principal: `app.seudominio.com`
   - n8n: `n8n.seudominio.com`
   - MCP (interno): não precisa domínio público

4. **Configure n8n online** (mesmos passos da configuração local)

---

## 🔍 Monitoramento

### Ver Logs

```bash
# Logs do MCP Server
docker logs -f divino-mcp-server

# Logs do n8n
docker logs -f divino-n8n

# Logs do app
docker logs -f divino-lanches-app

# Logs de tudo
docker-compose logs -f
```

### Health Checks

```bash
# Verificar saúde de todos os serviços
docker-compose ps

# Health checks individuais
curl http://localhost:3100/health  # MCP
curl http://localhost:5678/healthz  # n8n
curl http://localhost:8080          # App
```

---

## 🆘 Troubleshooting

### MCP Server não inicia

```bash
# Ver logs
docker logs divino-mcp-server

# Verificar se dependências foram instaladas
ls n8n-mcp-server/node_modules

# Reinstalar
cd n8n-mcp-server && rm -rf node_modules && npm install
docker-compose build mcp-server
docker-compose up -d mcp-server
```

### n8n não responde

```bash
# Verificar se está rodando
docker ps | grep n8n

# Ver logs
docker logs divino-n8n

# Reiniciar
docker-compose restart n8n
```

### App não conecta ao n8n

```bash
# Verificar variável de ambiente
docker exec divino-lanches-app env | grep N8N_WEBHOOK_URL

# Deve mostrar:
# N8N_WEBHOOK_URL=http://n8n:5678/webhook/ai-chat

# Se estiver errado, corrija no .env e:
docker-compose restart app
```

### Workflow n8n não ativa

1. Verifique se credencial OpenAI está configurada
2. Verifique se todos os nodes estão sem erro (ícone vermelho)
3. Teste manualmente clicando em "Test workflow"
4. Veja logs de execução em **Executions**

---

## 📈 Comandos Úteis

```bash
# Start de tudo
docker-compose up -d

# Start apenas n8n + MCP (se outros já estão rodando)
docker-compose up -d mcp-server n8n

# Parar tudo
docker-compose stop

# Reiniciar apenas um serviço
docker-compose restart mcp-server

# Ver uso de recursos
docker stats

# Limpar tudo (CUIDADO: apaga volumes!)
docker-compose down -v

# Rebuild completo
docker-compose build --no-cache
docker-compose up -d
```

---

## 📚 Documentação

- **Este arquivo**: Instalação completa
- **QUICK_START_N8N.md**: Guia rápido de 3 minutos
- **docs/N8N_DEPLOYMENT.md**: Deploy detalhado
- **docs/N8N_ARCHITECTURE_COMPARISON.md**: Análise técnica
- **n8n-mcp-server/README.md**: Documentação do MCP Server
- **n8n-integration/SETUP_GUIDE.md**: Setup técnico do n8n

---

## ✅ Checklist de Instalação

### Local
- [ ] Script `install-n8n-mcp.sh` executado
- [ ] MCP Server respondendo em :3100
- [ ] n8n acessível em :5678
- [ ] Workflow importado e ativo
- [ ] Credencial OpenAI configurada
- [ ] USE_N8N_AI=true no .env
- [ ] App reiniciado
- [ ] Teste via interface funcionando

### Produção (Coolify)
- [ ] Código commitado e pushed
- [ ] `coolify.yml` configurado
- [ ] Variáveis de ambiente configuradas
- [ ] Todos os serviços deployados
- [ ] Domínios configurados e SSL ativo
- [ ] n8n configurado online
- [ ] Workflow importado e ativo
- [ ] Teste via interface funcionando
- [ ] Monitoramento configurado

---

## 🎉 Pronto!

Agora você tem:
- ✅ n8n + MCP Server integrados na stack
- ✅ Instalação automática com 1 comando
- ✅ Deploy em produção simplificado
- ✅ Igual à wuzapi - tudo numa stack só

**Próximo passo**: Execute `bash install-n8n-mcp.sh` e divirta-se! 🚀

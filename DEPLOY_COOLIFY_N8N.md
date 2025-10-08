# 🌐 Deploy no Coolify - n8n + MCP Server

## Visão Geral

Este guia mostra como fazer deploy da arquitetura n8n + MCP Server no Coolify (servidor online).

## Arquitetura no Coolify

```
┌─────────────────────────────────────────────────────────────┐
│                      COOLIFY SERVER                          │
│                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │   Sistema    │    │ MCP Server   │    │  PostgreSQL   │  │
│  │   PHP/React  │───▶│   (Node.js)  │───▶│   Database    │  │
│  └──────┬───────┘    └──────────────┘    └──────────────┘  │
│         │                                                    │
│         │                                                    │
│         ▼                                                    │
│  ┌──────────────┐                                           │
│  │     n8n      │────────────────────────▶ OpenAI API       │
│  │   (Cloud ou  │                                           │
│  │  Self-hosted)│                                           │
│  └──────────────┘                                           │
└─────────────────────────────────────────────────────────────┘
```

## Opções de Deploy

### Opção 1: n8n Cloud + MCP Self-hosted (RECOMENDADO) ⭐

**Vantagens**:
- ✅ n8n gerenciado pela n8n.io (sem manutenção)
- ✅ Sempre atualizado
- ✅ Backup automático
- ✅ SSL gratuito
- ✅ Suporte técnico
- ✅ Mais simples de configurar

**Desvantagens**:
- ⚠️ Custo do n8n Cloud ($20-50/mês)
- ⚠️ Dados do workflow na nuvem n8n

### Opção 2: Tudo Self-hosted no Coolify

**Vantagens**:
- ✅ Controle total
- ✅ Sem custos mensais de n8n
- ✅ Dados 100% no seu servidor

**Desvantagens**:
- ⚠️ Você gerencia tudo
- ⚠️ Precisa configurar SSL
- ⚠️ Precisa fazer backups

---

## 🚀 Deploy Opção 1: n8n Cloud + MCP Coolify

### Passo 1: Preparar Código

```bash
# 1. Commit tudo
git add .
git commit -m "Add n8n + MCP integration"
git push origin main
```

### Passo 2: Deploy MCP Server no Coolify

#### 2.1 Criar novo Resource

1. Acesse seu Coolify
2. Vá em **+ New Resource**
3. Escolha **Docker Compose**
4. Configure:
   - **Repository**: Seu repositório Git
   - **Branch**: main
   - **Docker Compose Path**: `docker-compose.n8n.yml`
   - **Service**: `mcp-server`

#### 2.2 Configurar Variáveis de Ambiente

No Coolify, adicione as variáveis:

```bash
# Database
DB_HOST=postgres  # ou o host do seu banco
DB_PORT=5432
DB_NAME=divino_lanches
DB_USER=postgres
DB_PASSWORD=sua_senha_segura_aqui

# MCP Server
MCP_PORT=3100
API_KEY=gere-uma-chave-aleatoria-segura-aqui

# Node
NODE_ENV=production
```

Para gerar API_KEY segura:
```bash
openssl rand -hex 32
```

#### 2.3 Deploy

1. Clique em **Deploy**
2. Aguarde o build completar
3. Verifique os logs

#### 2.4 Testar MCP Server

```bash
# Substitua SEU_DOMINIO.com pelo domínio configurado no Coolify
curl https://mcp.seudominio.com/health

# Deve retornar:
# {"status":"ok","timestamp":"..."}
```

### Passo 3: Configurar n8n Cloud

#### 3.1 Criar Conta n8n

1. Acesse https://n8n.io
2. Clique em **Start Free**
3. Crie sua conta
4. Escolha região (US ou EU)

#### 3.2 Importar Workflow

1. No n8n Cloud, vá em **Workflows**
2. Clique em **New Workflow**
3. Vá em **⋮** (menu) → **Import from File**
4. Selecione: `n8n-integration/workflow-example.json`
5. Clique **Import**

#### 3.3 Ajustar URLs no Workflow

No workflow importado, ajuste os nodes:

**Node "MCP - Get Products"** e similares:
- URL: Mude de `http://mcp-server:3100/execute` para:
  - `https://mcp.seudominio.com/execute`

**Node "MCP - Get Orders"** e similares:
- Mesma coisa, ajuste para seu domínio

#### 3.4 Configurar Credenciais OpenAI

1. Vá em **Credentials** (menu lateral)
2. Clique **+ Add Credential**
3. Busque **OpenAI**
4. Configure:
   - **Name**: `OpenAI API`
   - **API Key**: Sua chave OpenAI
   - Clique **Save**

#### 3.5 Selecionar Credencial no Workflow

1. Volte no workflow
2. Clique no node **OpenAI - Generate Response**
3. Em **Credential to connect with**, selecione `OpenAI API`
4. Clique **Save**

#### 3.6 Ativar Workflow

1. No topo do workflow, clique no toggle **Inactive** → **Active**
2. Copie a **Production URL** do webhook
   - Será algo como: `https://seu-workspace.app.n8n.cloud/webhook/ai-chat`

### Passo 4: Configurar Sistema no Coolify

#### 4.1 Atualizar Variáveis do Sistema

No Coolify, vá no resource do seu sistema PHP e adicione/atualize:

```bash
# Ativar integração n8n
USE_N8N_AI=true

# URL do webhook n8n Cloud
N8N_WEBHOOK_URL=https://seu-workspace.app.n8n.cloud/webhook/ai-chat

# Timeout
N8N_TIMEOUT=30
```

#### 4.2 Redeploy

1. Clique em **Redeploy**
2. Aguarde completar

### Passo 5: Testar Integração

#### 5.1 Teste Direto no Webhook

```bash
curl -X POST https://seu-workspace.app.n8n.cloud/webhook/ai-chat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Listar categorias",
    "tenant_id": 1,
    "filial_id": 1
  }'
```

Deve retornar algo como:
```json
{
  "success": true,
  "response": {
    "type": "response",
    "message": "Temos as seguintes categorias: ..."
  }
}
```

#### 5.2 Teste no Sistema

1. Acesse seu sistema online
2. Faça login
3. Abra o **Assistente IA**
4. Digite: "Listar produtos"
5. Verifique a resposta

### Passo 6: Monitorar

#### Logs do MCP Server
```bash
# No Coolify, vá no MCP Server → Logs
# Ou via CLI:
ssh seu-servidor
docker logs -f nome-container-mcp
```

#### Logs do n8n
- No n8n Cloud, vá em **Executions** (no workflow)
- Veja todas as execuções e possíveis erros

---

## 🚀 Deploy Opção 2: Tudo Self-hosted no Coolify

### Diferenças da Opção 1

Ao invés de usar n8n Cloud, você vai hospedar o n8n no seu próprio servidor Coolify.

### Passo 1 e 2: Igual à Opção 1

(Deploy do MCP Server)

### Passo 3: Deploy n8n no Coolify

#### 3.1 Criar novo Resource para n8n

1. No Coolify, **+ New Resource**
2. Escolha **Docker Image**
3. Configure:
   - **Image**: `n8nio/n8n:latest`
   - **Name**: `divino-n8n`
   - **Port**: 5678

#### 3.2 Configurar Variáveis

```bash
# Authentication
N8N_BASIC_AUTH_ACTIVE=true
N8N_BASIC_AUTH_USER=admin
N8N_BASIC_AUTH_PASSWORD=sua_senha_segura_aqui

# Host (configure o domínio no Coolify antes)
N8N_HOST=n8n.seudominio.com
N8N_PORT=5678
N8N_PROTOCOL=https

# Environment
NODE_ENV=production
WEBHOOK_URL=https://n8n.seudominio.com/

# Timezone
GENERIC_TIMEZONE=America/Sao_Paulo

# Executions
EXECUTIONS_PROCESS=main
EXECUTIONS_DATA_SAVE_ON_ERROR=all
EXECUTIONS_DATA_SAVE_ON_SUCCESS=all

# Logging
N8N_LOG_LEVEL=info
```

#### 3.3 Configurar Storage

1. Em **Volumes**, adicione:
   - **Source**: Crie um volume chamado `n8n_data`
   - **Destination**: `/home/node/.n8n`

#### 3.4 Configurar Domínio e SSL

1. Em **Domains**, adicione: `n8n.seudominio.com`
2. Ative **SSL** (Coolify gera automaticamente com Let's Encrypt)

#### 3.5 Deploy

1. Clique **Deploy**
2. Aguarde completar
3. Acesse https://n8n.seudominio.com
4. Login: admin / sua_senha

#### 3.6 Importar Workflow

Mesmos passos da Opção 1, seção 3.2 a 3.6

### Passo 4, 5, 6: Igual à Opção 1

(Configurar sistema, testar, monitorar)

---

## 🔒 Segurança

### MCP Server

1. **API Key**: Sempre use uma chave forte
```bash
# No MCP Server, adicione validação (já está no código):
# system/N8nAIService.php já suporta API key
```

2. **Firewall**: Limite acesso ao MCP
```bash
# No Coolify, configure para apenas n8n acessar MCP
# Ou use rede interna Docker
```

3. **Rate Limiting**: Evite abuso
```bash
# Adicione nginx com rate limiting
# Ou use Cloudflare
```

### n8n

1. **Senha Forte**: Use senha complexa
2. **Backups**: Configure backup automático dos workflows
3. **Atualizações**: Mantenha n8n atualizado

---

## 💰 Custos Estimados

### Opção 1: n8n Cloud
- n8n Cloud: $20/mês (Starter) a $50/mês (Pro)
- OpenAI API: ~$360/mês (com MCP)
- Servidor Coolify: ~$20-50/mês
- **Total**: ~$400-460/mês

### Opção 2: Self-hosted
- OpenAI API: ~$360/mês (com MCP)
- Servidor Coolify: ~$20-50/mês (pode precisar upgrade)
- **Total**: ~$380-410/mês

**Economia vs OpenAI direto**: ~$1.000/mês

---

## 📊 Checklist de Deploy

### Pré-Deploy
- [ ] Código commitado e pushed para Git
- [ ] Variáveis de ambiente revisadas
- [ ] Backup do sistema atual
- [ ] Chave OpenAI válida

### Deploy MCP
- [ ] MCP Server deployado no Coolify
- [ ] Health check respondendo
- [ ] Teste de query funcionando
- [ ] Logs sem erros

### Deploy n8n
- [ ] n8n rodando (Cloud ou self-hosted)
- [ ] Workflow importado
- [ ] Credencial OpenAI configurada
- [ ] URLs ajustadas para produção
- [ ] Workflow ativado
- [ ] Webhook URL copiada

### Integração Sistema
- [ ] USE_N8N_AI=true configurado
- [ ] N8N_WEBHOOK_URL correto
- [ ] Sistema redeployado
- [ ] Teste via interface funcionando
- [ ] Logs sem erros

### Pós-Deploy
- [ ] Monitoramento configurado
- [ ] Documentação atualizada
- [ ] Backup dos workflows
- [ ] Testes de carga realizados

---

## 🆘 Troubleshooting

### Erro: MCP Server não conecta no banco

```bash
# Verifique variáveis
docker exec nome-container-mcp env | grep DB_

# Teste conexão
docker exec nome-container-mcp node -e "
const {Pool} = require('pg');
const pool = new Pool();
pool.query('SELECT NOW()', (e,r) => console.log(e||r.rows));
"
```

### Erro: n8n não consegue chamar MCP

```bash
# Verifique se URL está correta no workflow
# Se self-hosted, verifique se estão na mesma rede Docker
# Se n8n Cloud, verifique se MCP está acessível publicamente
```

### Erro: Sistema não recebe resposta

```bash
# Verifique webhook URL
grep N8N_WEBHOOK_URL .env

# Teste webhook diretamente
curl -X POST $N8N_WEBHOOK_URL -d '{"message":"teste"}'

# Verifique logs
docker logs nome-container-php
```

---

## 📈 Próximos Passos

Após deploy bem-sucedido:

1. **Monitorar custos** OpenAI (deve cair 65-75%)
2. **Configurar alertas** para erros
3. **Adicionar cache** Redis para queries frequentes
4. **Implementar rate limiting**
5. **Coletar métricas** de uso
6. **Otimizar prompts** baseado em analytics

---

## 📚 Documentação Relacionada

- `QUICK_START_N8N.md` - Guia rápido
- `docs/N8N_DEPLOYMENT.md` - Deploy detalhado
- `docs/N8N_ARCHITECTURE_COMPARISON.md` - Análise técnica
- `n8n-integration/SETUP_GUIDE.md` - Setup técnico

---

**Dúvidas?** Consulte a documentação ou verifique os logs de cada serviço.

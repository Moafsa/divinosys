# 🌐 Configurar n8n Externo

## ✅ Status Atual

O MCP Server está **rodando e funcionando**! 🎉

```
✅ divino-mcp-server: Up and healthy (porta 3100)
✅ divino-lanches-db: Up (porta 5432)
```

## 🔧 Configuração para n8n Externo

Como você já tem um n8n em outro servidor, basta configurar o webhook dele.

### 1️⃣ Configurar .env no Sistema

Edite seu arquivo `.env` e adicione:

```bash
# Ativar integração n8n
USE_N8N_AI=true

# URL do webhook do seu n8n externo para AI/MCP
# Substitua pela URL real do seu servidor n8n
AI_N8N_WEBHOOK_URL=https://seu-n8n-servidor.com/webhook/ai-chat

# Timeout (opcional)
AI_N8N_TIMEOUT=30

# Chave OpenAI (se ainda não tiver)
OPENAI_API_KEY=sua-chave-openai
```

**Exemplo real**:
```bash
AI_N8N_WEBHOOK_URL=https://n8n.divinolanches.com/webhook/ai-chat
# ou
AI_N8N_WEBHOOK_URL=http://192.168.1.100:5678/webhook/ai-chat
```

**Nota**: Esta variável é específica para a integração AI/MCP. Se você usa n8n para wuzapi ou outros webhooks, use variáveis separadas para cada um.

### 2️⃣ Configurar n8n no Servidor Externo

#### A) Importar Workflow

1. Acesse seu n8n: `https://seu-n8n.com`
2. Vá em **Workflows** → **Import from File**
3. Importe o arquivo: `n8n-integration/workflow-example.json` deste projeto
4. O workflow será importado

#### B) Ajustar URL do MCP Server no Workflow

No workflow importado, você precisa ajustar as URLs do MCP Server para apontar para este servidor.

**Encontre todos os nodes "MCP - ..." e ajuste a URL**:

Se o MCP Server está **na mesma máquina** do sistema:
```
http://localhost:3100/execute
```

Se o MCP Server está em **máquina diferente** do n8n:
```
http://IP_DO_SERVIDOR:3100/execute
```

Por exemplo, se seu sistema está em `192.168.1.50`:
```
http://192.168.1.50:3100/execute
```

**Nodes para ajustar**:
- MCP - Get Products
- MCP - Get Orders
- MCP - Get Ingredients
- MCP - Get Categories
- (todos os nodes HTTP Request que chamam MCP)

#### C) Configurar Credencial OpenAI

1. No n8n, vá em **Credentials** → **Add Credential**
2. Selecione **OpenAI**
3. Configure:
   - **Name**: `OpenAI API`
   - **API Key**: Sua chave OpenAI
4. **Save**

#### D) Selecionar Credencial no Workflow

1. Clique no node **OpenAI - Generate Response**
2. Em **Credential to connect with**, selecione `OpenAI API`
3. **Save**

#### E) Ativar Workflow

1. No topo do workflow, clique no toggle **Inactive** → **Active**
2. Copie a **Production URL** do webhook
3. Será algo como: `https://seu-n8n.com/webhook/ai-chat`
4. Use essa URL no `.env` do sistema

### 3️⃣ Configurar Acesso ao MCP Server

O MCP Server precisa ser acessível pelo seu n8n externo.

#### Se n8n está em REDE LOCAL:
```bash
# Firewall do Windows - permitir porta 3100
netsh advfirewall firewall add rule name="MCP Server" dir=in action=allow protocol=TCP localport=3100

# Ou abra manualmente:
# Painel de Controle → Firewall → Regras de entrada → Nova regra
# Tipo: Porta
# Porta: 3100
# Ação: Permitir
```

#### Se n8n está na INTERNET:
Você tem 2 opções:

**Opção A: Expor MCP publicamente** (menos seguro)
- Configure port forwarding no roteador: porta 3100
- Use domínio ou IP público
- **IMPORTANTE**: Configure autenticação (API_KEY)

**Opção B: VPN/Tunnel** (mais seguro) - RECOMENDADO
- Use Tailscale, WireGuard ou outro VPN
- Ou use Cloudflare Tunnel
- n8n acessa MCP via rede privada

### 4️⃣ Testar Conexão n8n → MCP

No seu servidor n8n, teste a conexão:

```bash
# Teste básico
curl http://IP_DO_MCP_SERVER:3100/health

# Teste de query
curl -X POST http://IP_DO_MCP_SERVER:3100/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "get_categories",
    "parameters": {},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

Se retornar dados, está funcionando! ✅

### 5️⃣ Iniciar Sistema

```bash
# Iniciar todos os serviços (incluindo o app)
docker-compose up -d

# Ver logs
docker-compose logs -f app

# Verificar se app pegou a variável
docker exec divino-lanches-app env | grep AI_N8N_WEBHOOK_URL
```

### 6️⃣ Testar Integração Completa

1. Acesse o sistema: http://localhost:8080
2. Faça login
3. Abra o **Assistente IA**
4. Digite: "Listar produtos"
5. Verifique a resposta

**Monitorar**:
- No n8n: Vá em **Executions** para ver logs do workflow
- Sistema: `docker logs -f divino-lanches-app`
- MCP: `docker logs -f divino-mcp-server`

---

## 📊 Fluxo de Dados

```
Sistema (localhost) → n8n (servidor externo) → MCP Server (localhost) → PostgreSQL
        ↓                       ↓                        ↓
     :8080              seu-n8n.com:5678            localhost:3100
```

---

## 🔒 Segurança para MCP Público

Se você precisa expor o MCP Server publicamente, configure autenticação:

### Adicione no `.env`:
```bash
MCP_API_KEY=gere-uma-chave-muito-segura-aqui
```

### No n8n, adicione header nos nodes HTTP Request:
```
Headers:
  x-api-key: sua-chave-mcp-aqui
```

### Para gerar chave segura:
```bash
# Windows PowerShell
-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | % {[char]$_})

# Ou online: https://randomkeygen.com/
```

---

## 🆘 Troubleshooting

### MCP Server não responde do n8n

1. **Verifique firewall**:
```bash
# Teste local primeiro
curl http://localhost:3100/health

# Teste do IP da máquina
curl http://SEU_IP:3100/health
```

2. **Verifique se porta está aberta**:
```bash
# No servidor do sistema
netstat -ano | findstr :3100
```

3. **Tente conectar do servidor n8n**:
```bash
# No servidor n8n, teste
curl http://IP_DO_SISTEMA:3100/health
```

### n8n não recebe resposta do sistema

1. Verifique URL do webhook no `.env`
2. Teste webhook diretamente:
```bash
curl -X POST https://seu-n8n.com/webhook/ai-chat \
  -H "Content-Type: application/json" \
  -d '{"message":"teste","tenant_id":1,"filial_id":1}'
```

### Workflow n8n dá timeout

- Aumente timeout no node HTTP Request (MCP)
- Verifique latência de rede
- Otimize queries no MCP se necessário

---

## 📚 Arquivos Úteis

- **Workflow n8n**: `n8n-integration/workflow-example.json`
- **Documentação MCP**: `n8n-mcp-server/README.md`
- **Documentação completa**: `docs/N8N_DEPLOYMENT.md`

---

## ✅ Checklist

- [ ] MCP Server rodando e healthy
- [ ] Firewall/rede configurado
- [ ] Workflow importado no n8n externo
- [ ] URLs do MCP ajustadas no workflow
- [ ] Credencial OpenAI configurada
- [ ] Workflow ativado
- [ ] N8N_WEBHOOK_URL configurado no .env
- [ ] USE_N8N_AI=true no .env
- [ ] Sistema reiniciado
- [ ] Teste completo funcionando

---

**Pronto!** Seu sistema local agora se integra com seu n8n externo! 🚀

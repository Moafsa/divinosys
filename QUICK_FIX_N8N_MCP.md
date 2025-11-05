# ⚡ Fix Rápido: Erro n8n MCP "Could not connect"

**Tempo estimado:** 5 minutos  
**Dificuldade:** ⭐ Fácil

---

## 🎯 Problema

```
❌ Could not connect to your MCP server
```

## ✅ Solução Rápida (3 Passos)

### Passo 1: Escolha o Endpoint Correto

No node **"MCP Client - Divino System"**, configure:

**OPÇÃO A (Recomendada):**
```
Endpoint: https://mcp.conext.click/execute
Server Transport: HTTP
```

**OU OPÇÃO B:**
```
Endpoint: https://mcp.conext.click/sse
Server Transport: Server Sent Events (Deprecated)
```

⚠️ **IMPORTANTE:** Endpoint e Transport devem combinar!

### Passo 2: Configure a Credencial

```
Credential: MCP DivinoSys
Type: Header Auth
Header Name: x-api-key
Header Value: mcp_divinosys_2024_secret_key
```

### Passo 3: Teste

Clique em **"Execute step"** → Deve conectar sem erros!

---

## 🔧 Se Ainda Não Funcionar

### 1. Verifique o Servidor

```bash
curl https://mcp.conext.click/health
```

Se retornar `{"status":"ok",...}` → Servidor OK ✅

### 2. Teste o Endpoint Diretamente

```bash
curl -X POST https://mcp.conext.click/execute \
  -H "Content-Type: application/json" \
  -d '{"tool":"get_categories","parameters":{},"context":{"tenant_id":1,"filial_id":1}}'
```

Se retornar dados → Endpoint OK ✅

### 3. Verifique a Configuração n8n

- [ ] Endpoint termina com `/execute` ou `/sse`
- [ ] Server Transport é `HTTP` (para /execute) ou `SSE` (para /sse)
- [ ] Credencial tem `x-api-key` corretamente
- [ ] Header Value é `mcp_divinosys_2024_secret_key`

---

## 📋 Configurações Corretas

### ✅ Configuração 1 (HTTP REST - Recomendada)

```
Node: MCP Client - Divino System
├─ Endpoint: https://mcp.conext.click/execute
├─ Server Transport: HTTP (ou REST)
├─ Authentication: Header Auth
└─ Credential: MCP DivinoSys
    ├─ Header Name: x-api-key
    └─ Header Value: mcp_divinosys_2024_secret_key
```

### ✅ Configuração 2 (SSE)

```
Node: MCP Client - Divino System
├─ Endpoint: https://mcp.conext.click/sse
├─ Server Transport: Server Sent Events (Deprecated)
├─ Authentication: Header Auth
└─ Credential: MCP DivinoSys
    ├─ Header Name: x-api-key
    └─ Header Value: mcp_divinosys_2024_secret_key
```

---

## ❌ Configurações Erradas (NÃO FAÇA)

### ❌ Endpoint sem caminho
```
Endpoint: https://mcp.conext.click  ← ERRADO! Falta /execute ou /sse
```

### ❌ Transport incompatível
```
Endpoint: https://mcp.conext.click/execute
Server Transport: SSE  ← ERRADO! Use HTTP para /execute
```

### ❌ Header incorreto
```
Header Name: Authorization  ← ERRADO! Use x-api-key
Header Value: Bearer xyz    ← ERRADO! Use mcp_divinosys_2024_secret_key
```

---

## 🚀 Deploy (Se Necessário)

Se o servidor ainda não tem SSE:

```bash
# 1. Pull do código atualizado
git pull

# 2. Rebuild do container MCP
docker compose -f docker-compose.production.yml build mcp-server

# 3. Restart
docker compose -f docker-compose.production.yml up -d mcp-server

# 4. Verificar
curl https://mcp.conext.click/health
```

---

## 📚 Documentação Completa

Para mais detalhes, consulte:

- **Configuração n8n:** `MCP_N8N_CONNECTION_GUIDE.md`
- **Deploy:** `DEPLOY_MCP_SSE.md`
- **Resumo:** `RESUMO_IMPLEMENTACAO_SSE.md`

---

## ✅ Checklist Final

- [ ] Endpoint correto (`/execute` ou `/sse`)
- [ ] Transport correto (`HTTP` ou `SSE`)
- [ ] Credencial configurada
- [ ] Header Name: `x-api-key`
- [ ] Header Value: `mcp_divinosys_2024_secret_key`
- [ ] Servidor respondendo (teste curl)
- [ ] Node executa sem erros

---

**Última atualização:** 05/11/2025


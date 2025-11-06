# 🔧 Configuração Correta do MCP Client no n8n

## ⚠️ IMPORTANTE: Configuração do Endpoint

O n8n MCP Client tem **comportamento específico** dependendo do "Server Transport" escolhido:

---

## ✅ Configuração 1: Server Sent Events (SSE)

### **Parâmetros:**

```yaml
Endpoint: https://mcp.conext.click/sse
Server Transport: Server Sent Events (Deprecated)
Authentication: Header Auth
Credential: MCP DivinoSys
  Header Name: x-api-key
  Header Value: mcp_divinosys_2024_secret_key
Tools to Include: All
Timeout: 120000
```

### **Como Funciona:**

1. **GET /sse** → n8n faz GET para estabelecer conexão SSE
2. **POST /sse** → n8n faz POST para executar ferramentas

**✅ Ambos os métodos estão implementados!**

---

## ✅ Configuração 2: HTTP Streamable

### **Parâmetros:**

```yaml
Endpoint: https://mcp.conext.click/execute
Server Transport: HTTP Streamable
Authentication: Header Auth
Credential: MCP DivinoSys
  Header Name: x-api-key
  Header Value: mcp_divinosys_2024_secret_key
Tools to Include: All
Timeout: 120000
```

### **Como Funciona:**

1. **POST /execute** → n8n faz POST direto para executar ferramentas

**✅ Método mais simples e direto!**

---

## ❌ Erros Comuns

### **Erro 1: "Could not connect to your MCP server"**

**Causas:**
- Endpoint incorreto
- Credencial não configurada
- Servidor offline
- Timeout muito curto

**Solução:**
1. Verifique endpoint: deve ser `/sse` ou `/execute` (com https://)
2. Configure credencial: `x-api-key: mcp_divinosys_2024_secret_key`
3. Aumente timeout: 120000 (2 minutos)
4. Teste servidor: `curl https://mcp.conext.click/health`

### **Erro 2: "Cannot POST /sse"**

**Causa:** Código antigo no servidor (sem suporte POST em /sse)

**Solução:**
1. Faça deploy do código novo
2. Rebuild sem cache: `docker compose build --no-cache mcp-server`
3. Verifique logs: deve mostrar "SSE endpoint"

### **Erro 3: "Cannot GET /sse/execute"**

**Causa:** Tentando fazer GET em endpoint que só aceita POST

**Solução:**
- Use GET em `/sse` (para conexão)
- Use POST em `/sse` ou `/sse/execute` (para executar)

---

## 🧪 Teste de Validação

### **Teste 1: Health Check**

```bash
curl https://mcp.conext.click/health
```

**Esperado:**
```json
{"status":"ok","security":"enabled","write_operations_protected":true}
```

### **Teste 2: GET /sse (SSE Connection)**

```bash
curl -N https://mcp.conext.click/sse
```

**Esperado:**
```
event: connected
data: {"status":"connected",...}

event: tools
data: {"tools":[...]}

event: ready
data: {"status":"ready",...}
```

### **Teste 3: POST /sse (Tool Execution)**

```bash
curl -X POST https://mcp.conext.click/sse \
  -H "Content-Type: application/json" \
  -H "x-api-key: mcp_divinosys_2024_secret_key" \
  -d '{"tool":"get_categories","parameters":{},"context":{"tenant_id":4,"filial_id":1}}'
```

**Esperado:**
```json
{
  "success": true,
  "tool": "get_categories",
  "result": {
    "count": 4,
    "categories": [...]
  }
}
```

### **Teste 4: POST /execute (HTTP REST)**

```bash
curl -X POST https://mcp.conext.click/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: mcp_divinosys_2024_secret_key" \
  -d '{"tool":"get_categories","parameters":{},"context":{"tenant_id":4,"filial_id":1}}'
```

**Esperado:**
```json
{
  "success": true,
  "tool": "get_categories",
  "result": {...}
}
```

---

## 📋 Checklist de Configuração

### **Antes de Testar no n8n:**

- [ ] Servidor MCP está rodando (teste `/health`)
- [ ] GET `/sse` funciona (retorna eventos SSE)
- [ ] POST `/sse` funciona (executa ferramentas)
- [ ] POST `/execute` funciona (executa ferramentas)
- [ ] Credencial configurada no n8n
- [ ] Endpoint correto no n8n
- [ ] Server Transport corresponde ao endpoint

### **No n8n MCP Client:**

- [ ] Endpoint: `https://mcp.conext.click/sse` (SSE) OU `/execute` (HTTP)
- [ ] Server Transport: `Server Sent Events` (SSE) OU `HTTP Streamable` (HTTP)
- [ ] Authentication: `Header Auth`
- [ ] Credential: `MCP DivinoSys` configurada
- [ ] Header Name: `x-api-key`
- [ ] Header Value: `mcp_divinosys_2024_secret_key`
- [ ] Tools to Include: `All`
- [ ] Timeout: `120000` ou mais

---

## 🎯 Recomendação

**Para máxima compatibilidade, use:**

```yaml
Endpoint: https://mcp.conext.click/execute
Server Transport: HTTP Streamable
Authentication: Header Auth
Credential: MCP DivinoSys
```

**Por quê?**
- ✅ Mais simples
- ✅ Menos pontos de falha
- ✅ Funciona sempre
- ✅ Não depende de SSE

**Use SSE apenas se:**
- n8n especificamente exigir
- Você precisar de streaming em tempo real
- HTTP Streamable não funcionar

---

## 🐛 Debug Avançado

### **Ver Requisições do n8n:**

1. Abra **F12 → Network**
2. Execute o MCP Client node
3. Filtre por `mcp.conext.click`
4. Veja:
   - Qual URL foi chamada?
   - Qual método (GET/POST)?
   - Qual status code?
   - Qual resposta?

### **Logs do Servidor:**

```bash
docker logs -f $(docker ps | grep mcp-server | awk '{print $1}')
```

**Procure por:**
- Requisições chegando
- Erros de autenticação
- Erros de SQL
- Timeouts

---

## ✅ Status Atual dos Endpoints

| Endpoint | GET | POST | Status |
|----------|-----|------|--------|
| `/health` | ✅ | ❌ | OK |
| `/tools` | ✅ | ❌ | OK |
| `/execute` | ❌ | ✅ | OK |
| `/sse` | ✅ | ✅ | OK |
| `/sse/execute` | ✅ (erro 405) | ✅ | OK |

**Todos os endpoints necessários estão funcionando!** ✅


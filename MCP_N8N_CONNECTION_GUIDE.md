# 🔧 Guia Completo: Conectando n8n ao Servidor MCP Divinosys

## ✅ Servidor MCP Atualizado

O servidor MCP Divinosys agora suporta **DOIS** métodos de transporte:

- ✅ **HTTP REST** (endpoint `/execute`)
- ✅ **Server Sent Events - SSE** (endpoints `/sse` e `/sse/execute`)

Você pode usar **qualquer um** dos métodos no n8n!

---

## 📋 Configuração no n8n

### Opção 1: HTTP REST (Recomendado para Iniciantes)

#### Configuração do Node "MCP Client - Divino System"

1. **Endpoint:**
   ```
   https://mcp.conext.click/execute
   ```

2. **Server Transport:**
   ```
   HTTP
   ```
   ou
   ```
   REST
   ```

3. **Authentication:**
   ```
   Header Auth
   ```

4. **Credential for Header Auth:** `MCP DivinoSys`
   - Header Name: `x-api-key`
   - Header Value: `mcp_divinosys_2024_secret_key`

5. **Tools to Include:**
   ```
   All
   ```

---

### Opção 2: Server Sent Events (SSE)

#### Configuração do Node "MCP Client - Divino System"

1. **Endpoint:**
   ```
   https://mcp.conext.click/sse
   ```

2. **Server Transport:**
   ```
   Server Sent Events (Deprecated)
   ```
   ou
   ```
   SSE
   ```

3. **Authentication:**
   ```
   Header Auth
   ```

4. **Credential for Header Auth:** `MCP DivinoSys`
   - Header Name: `x-api-key`
   - Header Value: `mcp_divinosys_2024_secret_key`

5. **Tools to Include:**
   ```
   All
   ```

---

## 🔐 Configuração da Credencial

### Criar/Editar Credencial "MCP DivinoSys"

1. No n8n, vá para **Settings → Credentials**
2. Clique em **"+ Add Credential"** ou edite a existente
3. Selecione o tipo **"Header Auth"**
4. Configure:

```
Name: MCP DivinoSys
Type: Header Auth
Header Name: x-api-key
Header Value: mcp_divinosys_2024_secret_key
```

5. Clique em **"Save"**

---

## 🧪 Teste de Conexão

### Método 1: Testar no n8n

1. Abra o node **"MCP Client - Divino System"**
2. Clique no botão **"Execute step"** no canto superior direito
3. **Resultado esperado:**
   - Nenhum erro
   - Lista de ferramentas disponíveis no OUTPUT

### Método 2: Testar via HTTP Request Node

Adicione um node **"HTTP Request"** com:

**Para HTTP REST:**
```
Method: POST
URL: https://mcp.conext.click/execute
Authentication: Header Auth
  - Header Name: x-api-key
  - Header Value: mcp_divinosys_2024_secret_key
Body Content Type: JSON
Body:
{
  "tool": "get_products",
  "parameters": {
    "limit": 5
  },
  "context": {
    "tenant_id": 1,
    "filial_id": 1
  }
}
```

**Para SSE:**
```
Method: POST
URL: https://mcp.conext.click/sse/execute
Authentication: Header Auth
  - Header Name: x-api-key
  - Header Value: mcp_divinosys_2024_secret_key
Body Content Type: JSON
Body:
{
  "tool": "get_products",
  "parameters": {
    "limit": 5
  },
  "context": {
    "tenant_id": 1,
    "filial_id": 1
  }
}
```

### Método 3: Testar via Health Check

```bash
curl https://mcp.conext.click/health
```

**Resultado esperado:**
```json
{
  "status": "ok",
  "timestamp": "2025-11-05T...",
  "security": "enabled",
  "write_operations_protected": true
}
```

---

## 🔍 Verificação de Endpoints

### Endpoints Disponíveis

| Endpoint | Método | Descrição | Transporte |
|----------|--------|-----------|------------|
| `/health` | GET | Health check | Ambos |
| `/tools` | GET | Lista ferramentas | Ambos |
| `/execute` | POST | Executa ferramenta | HTTP REST |
| `/sse` | GET | Conecta stream SSE | SSE |
| `/sse/execute` | POST | Executa via SSE | SSE |

### Teste Rápido de Todos os Endpoints

```bash
# Health check
curl https://mcp.conext.click/health

# List tools
curl https://mcp.conext.click/tools

# Test HTTP REST
curl -X POST https://mcp.conext.click/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: mcp_divinosys_2024_secret_key" \
  -d '{"tool":"get_categories","parameters":{},"context":{"tenant_id":1,"filial_id":1}}'

# Test SSE connection
curl -N https://mcp.conext.click/sse

# Test SSE execute
curl -X POST https://mcp.conext.click/sse/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: mcp_divinosys_2024_secret_key" \
  -d '{"tool":"get_categories","parameters":{},"context":{"tenant_id":1,"filial_id":1}}'
```

---

## ❌ Troubleshooting

### Erro: "Could not connect to your MCP server"

**Causas possíveis:**

1. **Endpoint incorreto**
   - ✅ HTTP REST: `https://mcp.conext.click/execute`
   - ✅ SSE: `https://mcp.conext.click/sse`
   - ❌ Não use apenas: `https://mcp.conext.click`

2. **Server Transport incompatível**
   - Se usar endpoint `/execute`, use transport `HTTP` ou `REST`
   - Se usar endpoint `/sse`, use transport `SSE` ou `Server Sent Events`

3. **Credencial incorreta**
   - Verifique se Header Name é `x-api-key`
   - Verifique se Header Value é `mcp_divinosys_2024_secret_key`

4. **Servidor offline**
   - Teste com: `curl https://mcp.conext.click/health`
   - Se retornar erro, o servidor está offline

### Erro: "Unauthorized - API key required"

**Solução:**
- Certifique-se de que a credencial está configurada corretamente
- Para operações de leitura (get_products, get_orders), a API key não é obrigatória
- Para operações de escrita (create_product, update_order), a API key é **obrigatória**

### Erro: "tenant_id and filial_id are required"

**Solução:**
- Todas as ferramentas MCP requerem `context` com:
  ```json
  "context": {
    "tenant_id": 1,
    "filial_id": 1
  }
  ```
- Esses valores vêm da sessão do usuário logado no sistema PHP

### Timeout / Conexão lenta

**Solução:**
1. Vá para a aba **"Settings"** do node MCP Client
2. Aumente o **"Request Timeout"** para `60000` (60 segundos)
3. Ative **"Retry on Failure"**

---

## 📊 Comparação de Métodos

| Característica | HTTP REST | SSE |
|----------------|-----------|-----|
| **Simplicidade** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Latência** | Normal | Baixa |
| **Conexão** | Request/Response | Persistente |
| **Compatibilidade** | Todas versões n8n | n8n recente |
| **Real-time** | Não | Sim |
| **Recomendado para** | Casos gerais | Monitoramento real-time |

### Nossa Recomendação

- **Iniciantes**: Use **HTTP REST** (`/execute`)
- **Produção**: Use **HTTP REST** (`/execute`)
- **Casos especiais**: Use **SSE** (`/sse`) se precisar de streaming ou se o n8n exigir

---

## 🚀 Ferramentas Disponíveis

### Ferramentas de Leitura (Sem autenticação)

1. **get_products** - Lista produtos
2. **get_ingredients** - Lista ingredientes
3. **get_categories** - Lista categorias
4. **get_orders** - Lista pedidos
5. **get_tables** - Lista mesas
6. **search_products** - Busca produtos
7. **get_product_details** - Detalhes de um produto
8. **get_order_details** - Detalhes de um pedido
9. **get_customers** - Lista clientes
10. **get_fiado_customers** - Clientes com fiado

### Ferramentas de Escrita (Requerem autenticação)

11. **create_product** - Criar produto
12. **update_product** - Atualizar produto
13. **delete_product** - Deletar produto
14. **create_ingredient** - Criar ingrediente
15. **update_ingredient** - Atualizar ingrediente
16. **delete_ingredient** - Deletar ingrediente
17. **create_category** - Criar categoria
18. **update_category** - Atualizar categoria
19. **delete_category** - Deletar categoria
20. **create_order** - Criar pedido
21. **update_order_status** - Atualizar status do pedido
22. **create_payment** - Registrar pagamento
23. **create_financial_entry** - Lançamento financeiro
24. **create_customer** - Criar cliente
25. **update_customer** - Atualizar cliente
26. **delete_customer** - Deletar cliente

---

## 📝 Exemplo de Uso no Workflow n8n

### Workflow Simples

```
1. Webhook Trigger
   ↓
2. Code Node (extrair dados da requisição)
   ↓
3. MCP Client - Divino System
   - Tool: get_products
   - Parameters: { "limit": 10 }
   - Context: { "tenant_id": 1, "filial_id": 1 }
   ↓
4. OpenAI Chat
   - Use dados do MCP para responder
   ↓
5. Respond to Webhook
```

### Exemplo de Código (Code Node)

```javascript
// Extract context from PHP session
const tenantId = $json.session?.tenant_id || 1;
const filialId = $json.session?.filial_id || 1;

// Prepare MCP parameters
return {
  json: {
    tool: 'get_products',
    parameters: {
      query: $json.user_query,
      limit: 10
    },
    context: {
      tenant_id: tenantId,
      filial_id: filialId
    }
  }
};
```

---

## 🔄 Deploy e Atualização

### Rebuild do Container MCP

Se você fez alterações no código do servidor MCP:

```bash
# Parar o container
docker stop divinosys_divinosys_mcp-server.1.*

# Rebuild
docker compose -f docker-compose.production.yml build mcp-server

# Restart
docker compose -f docker-compose.production.yml up -d mcp-server

# Verificar logs
docker logs -f divinosys_divinosys_mcp-server.1.* --tail 100
```

### Verificar se SSE está funcionando

```bash
# Logs do servidor devem mostrar:
# ✅ Server supports both HTTP REST and Server Sent Events (SSE)
# ⚡ SSE endpoint: GET http://localhost:3100/sse
# ⚡ SSE Execute endpoint: POST http://localhost:3100/sse/execute

docker logs divinosys_divinosys_mcp-server.1.* --tail 20
```

---

## ✅ Checklist Final

- [ ] Servidor MCP está rodando (teste `/health`)
- [ ] Credencial "MCP DivinoSys" criada no n8n
- [ ] Endpoint correto configurado:
  - `/execute` para HTTP REST
  - `/sse` para SSE
- [ ] Server Transport correto:
  - `HTTP` ou `REST` para `/execute`
  - `SSE` ou `Server Sent Events` para `/sse`
- [ ] Authentication configurada como `Header Auth`
- [ ] Header Name: `x-api-key`
- [ ] Header Value: `mcp_divinosys_2024_secret_key`
- [ ] Teste executado com sucesso no n8n

---

## 📞 Suporte

Se o problema persistir:

1. **Verifique logs do container:**
   ```bash
   docker logs divinosys_divinosys_mcp-server.1.* --tail 100
   ```

2. **Teste conexão direta:**
   ```bash
   curl https://mcp.conext.click/health
   ```

3. **Verifique variáveis de ambiente:**
   ```bash
   docker exec divinosys_divinosys_mcp-server.1.* env | grep MCP
   ```

---

**Última atualização:** 05/11/2025 - Adicionado suporte SSE ao servidor MCP


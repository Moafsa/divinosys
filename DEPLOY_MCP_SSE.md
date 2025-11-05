# 🚀 Deploy: Servidor MCP com Suporte SSE

**Versão:** 2.0.0  
**Data:** 05/11/2025

---

## 📋 Pré-requisitos

- ✅ Docker e Docker Compose instalados
- ✅ Acesso ao servidor de produção (Coolify/Portainer)
- ✅ Acesso SSH ao servidor (se necessário)
- ✅ Git configurado com acesso ao repositório

---

## 🔍 Resumo das Alterações

### Arquivos Modificados:
1. `n8n-mcp-server/server.js` - Adicionado suporte SSE
2. `n8n-mcp-server/README.md` - Documentação atualizada

### Arquivos Novos:
1. `MCP_N8N_CONNECTION_GUIDE.md` - Guia de configuração n8n
2. `n8n-mcp-server/test-sse.js` - Script de testes
3. `CHANGELOG_MCP_SSE.md` - Changelog detalhado
4. `DEPLOY_MCP_SSE.md` - Este arquivo

### Endpoints Novos:
- `GET /sse` - Conexão SSE stream
- `POST /sse/execute` - Executa ferramenta via SSE

---

## 🎯 Estratégia de Deploy

### Opção A: Deploy via Git (Recomendado)

```bash
# 1. Commit e push das alterações
git add .
git commit -m "feat: add SSE support to MCP server"
git push origin main

# 2. No servidor, pull das alterações
cd /path/to/divino-lanches
git pull origin main

# 3. Rebuild do container MCP
docker compose -f docker-compose.production.yml build mcp-server

# 4. Restart do serviço
docker compose -f docker-compose.production.yml up -d mcp-server

# 5. Verificar logs
docker logs -f divinosys_divinosys_mcp-server.1.* --tail 100
```

### Opção B: Deploy via Coolify

Se estiver usando Coolify:

1. **Push para o repositório:**
   ```bash
   git add .
   git commit -m "feat: add SSE support to MCP server"
   git push origin main
   ```

2. **No painel Coolify:**
   - Vá para o serviço `mcp-server`
   - Clique em **"Redeploy"**
   - Ou configure **Auto Deploy** para deploy automático

3. **Verificar deploy:**
   - Aguarde conclusão do build
   - Verifique logs no painel Coolify
   - Teste endpoint de health

### Opção C: Deploy Manual (Emergência)

Se Git não estiver disponível:

```bash
# 1. Copiar arquivo modificado para o servidor
scp n8n-mcp-server/server.js user@server:/path/to/divino/n8n-mcp-server/

# 2. SSH no servidor
ssh user@server

# 3. Rebuild container
cd /path/to/divino-lanches
docker compose -f docker-compose.production.yml build mcp-server

# 4. Restart
docker compose -f docker-compose.production.yml up -d mcp-server
```

---

## ✅ Checklist de Deploy

### Antes do Deploy:

- [ ] Código testado localmente
- [ ] Logs de erro verificados
- [ ] Documentação atualizada
- [ ] Variáveis de ambiente verificadas
- [ ] Backup do container atual (opcional)

### Durante o Deploy:

- [ ] Git pull executado
- [ ] Container rebuilt com sucesso
- [ ] Container iniciado sem erros
- [ ] Logs não mostram erros

### Após o Deploy:

- [ ] Health check passa (`/health`)
- [ ] Endpoints HTTP REST funcionando (`/execute`)
- [ ] Endpoints SSE funcionando (`/sse`)
- [ ] n8n conecta com sucesso
- [ ] Ferramentas MCP respondem corretamente

---

## 🧪 Validação Pós-Deploy

### 1. Verificar Health Check

```bash
curl https://mcp.conext.click/health
```

**Resposta esperada:**
```json
{
  "status": "ok",
  "timestamp": "2025-11-05T...",
  "security": "enabled",
  "write_operations_protected": true
}
```

### 2. Verificar Logs do Container

```bash
# Ver logs em tempo real
docker logs -f divinosys_divinosys_mcp-server.1.* --tail 100
```

**Logs esperados:**
```
🚀 Divino Lanches MCP Server running on port 3100
🔒 Security enabled for write operations
📊 Health check: http://localhost:3100/health
🔧 Tools endpoint: http://localhost:3100/tools
📡 HTTP REST endpoint: POST http://localhost:3100/execute
⚡ SSE endpoint: GET http://localhost:3100/sse
⚡ SSE Execute endpoint: POST http://localhost:3100/sse/execute
✅ Server supports both HTTP REST and Server Sent Events (SSE)
```

### 3. Testar HTTP REST Endpoint

```bash
curl -X POST https://mcp.conext.click/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: mcp_divinosys_2024_secret_key" \
  -d '{
    "tool": "get_categories",
    "parameters": {},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

**Resposta esperada:**
```json
{
  "success": true,
  "tool": "get_categories",
  "result": {
    "count": 5,
    "categories": [...]
  },
  "timestamp": "2025-11-05T..."
}
```

### 4. Testar SSE Connection

```bash
curl -N https://mcp.conext.click/sse
```

**Resposta esperada (stream SSE):**
```
event: connected
data: {"status":"connected","timestamp":"..."}

event: tools
data: {"message":"MCP Server ready. Use POST /sse/execute to execute tools."}

event: heartbeat
data: {"timestamp":"..."}
```

### 5. Testar SSE Execute

```bash
curl -X POST https://mcp.conext.click/sse/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: mcp_divinosys_2024_secret_key" \
  -d '{
    "tool": "get_categories",
    "parameters": {},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

**Resposta esperada:**
```json
{
  "success": true,
  "tool": "get_categories",
  "result": {
    "count": 5,
    "categories": [...]
  },
  "timestamp": "2025-11-05T..."
}
```

### 6. Executar Script de Teste Automatizado

```bash
# Baixar script de teste
curl -O https://mcp.conext.click/test-sse.js

# Executar testes
MCP_URL=https://mcp.conext.click node test-sse.js
```

**Resultado esperado:**
```
✅ All tests passed!
✅ The MCP Server supports both HTTP REST and SSE!
```

### 7. Testar no n8n

1. Abra o workflow com MCP Client
2. Configure endpoint: `https://mcp.conext.click/sse`
3. Configure transport: `Server Sent Events (Deprecated)`
4. Clique em **"Execute step"**
5. Verifique se conecta sem erros

---

## ⚠️ Troubleshooting

### Problema 1: Container não inicia

```bash
# Verificar logs de erro
docker logs divinosys_divinosys_mcp-server.1.*

# Verificar se porta está disponível
netstat -tuln | grep 3100

# Verificar variáveis de ambiente
docker exec divinosys_divinosys_mcp-server.1.* env | grep MCP
```

### Problema 2: Health check falha

```bash
# Entrar no container
docker exec -it divinosys_divinosys_mcp-server.1.* sh

# Testar internamente
wget -O- http://localhost:3100/health

# Verificar conexão com banco
node -e "const {Pool} = require('pg'); const pool = new Pool({host: process.env.DB_HOST}); pool.query('SELECT NOW()').then(r => console.log(r.rows)).catch(e => console.error(e));"
```

### Problema 3: SSE não funciona

```bash
# Verificar se endpoint SSE está respondendo
curl -v https://mcp.conext.click/sse

# Headers esperados:
# Content-Type: text/event-stream
# Cache-Control: no-cache
# Connection: keep-alive

# Verificar se há firewall bloqueando
telnet mcp.conext.click 3100
```

### Problema 4: n8n não conecta

**Diagnóstico:**
1. Verifique endpoint no n8n: deve ser `/sse` ou `/execute`
2. Verifique transport: deve corresponder ao endpoint
3. Verifique credencial: `x-api-key` e valor correto
4. Teste endpoint diretamente via curl

**Solução rápida:**
- Use HTTP REST (`/execute`) com transport `HTTP`
- Se SSE for necessário, use `/sse` com transport `SSE`

### Problema 5: Operações de escrita retornam 401

```bash
# Verificar se API key está correta
curl -X POST https://mcp.conext.click/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: WRONG_KEY" \
  -d '{"tool":"create_product","parameters":{...},"context":{...}}'

# Deve retornar:
# {"error":"Unauthorized - API key required for write operations"}
```

**Solução:**
- Verifique variável de ambiente `MCP_API_KEY` no container
- Verifique se header `x-api-key` está sendo enviado corretamente

---

## 🔄 Rollback

Se algo der errado, fazer rollback:

### Rollback via Git:

```bash
# 1. Reverter commit
git revert HEAD

# 2. Push
git push origin main

# 3. Pull no servidor
cd /path/to/divino-lanches
git pull origin main

# 4. Rebuild
docker compose -f docker-compose.production.yml build mcp-server

# 5. Restart
docker compose -f docker-compose.production.yml up -d mcp-server
```

### Rollback Manual:

```bash
# 1. Restaurar arquivo anterior
git checkout HEAD~1 -- n8n-mcp-server/server.js

# 2. Rebuild
docker compose -f docker-compose.production.yml build mcp-server

# 3. Restart
docker compose -f docker-compose.production.yml up -d mcp-server
```

### Rollback via Container Anterior:

```bash
# 1. Listar imagens antigas
docker images | grep divino-mcp-server

# 2. Tag da imagem anterior
docker tag divino-mcp-server:OLD divino-mcp-server:latest

# 3. Restart com imagem antiga
docker compose -f docker-compose.production.yml up -d mcp-server
```

---

## 📊 Monitoramento Pós-Deploy

### Métricas a Monitorar:

1. **Uptime do container:**
   ```bash
   docker ps | grep mcp-server
   ```

2. **Uso de recursos:**
   ```bash
   docker stats divinosys_divinosys_mcp-server.1.*
   ```

3. **Logs de erro:**
   ```bash
   docker logs divinosys_divinosys_mcp-server.1.* | grep -i error
   ```

4. **Número de conexões SSE ativas:**
   ```bash
   # Ver logs de conexões SSE
   docker logs divinosys_divinosys_mcp-server.1.* | grep "SSE"
   ```

5. **Taxa de sucesso de requests:**
   ```bash
   # Contar requests bem-sucedidos vs erros
   docker logs divinosys_divinosys_mcp-server.1.* | grep "success"
   ```

---

## 📝 Checklist Final

### Validação Completa:

- [ ] ✅ Container está rodando
- [ ] ✅ Health check responde OK
- [ ] ✅ Logs não mostram erros
- [ ] ✅ Endpoint `/execute` funciona
- [ ] ✅ Endpoint `/sse` funciona
- [ ] ✅ Endpoint `/sse/execute` funciona
- [ ] ✅ n8n conecta via HTTP REST
- [ ] ✅ n8n conecta via SSE (se necessário)
- [ ] ✅ Autenticação funciona para write operations
- [ ] ✅ Tenant isolation funciona
- [ ] ✅ Todas as ferramentas respondem

### Documentação:

- [ ] ✅ README.md atualizado
- [ ] ✅ MCP_N8N_CONNECTION_GUIDE.md criado
- [ ] ✅ CHANGELOG_MCP_SSE.md criado
- [ ] ✅ Equipe notificada sobre mudanças
- [ ] ✅ Guia de configuração n8n compartilhado

---

## 🎯 Próximos Passos

Após deploy bem-sucedido:

1. **Atualizar workflow n8n:**
   - Testar com endpoint SSE se necessário
   - Validar todas as ferramentas funcionam
   - Atualizar documentação do workflow

2. **Monitorar por 24-48h:**
   - Verificar logs regularmente
   - Monitorar uso de recursos
   - Verificar conexões SSE não causam memory leak

3. **Comunicar time:**
   - Compartilhar `MCP_N8N_CONNECTION_GUIDE.md`
   - Treinar time sobre opções HTTP REST vs SSE
   - Documentar casos de uso para cada método

4. **Melhorias futuras:**
   - Adicionar rate limiting
   - Implementar cache Redis
   - Adicionar métricas Prometheus
   - Implementar logging estruturado

---

## 📞 Suporte

Em caso de problemas:

1. **Verificar logs:**
   ```bash
   docker logs -f divinosys_divinosys_mcp-server.1.* --tail 200
   ```

2. **Consultar documentação:**
   - `MCP_N8N_CONNECTION_GUIDE.md` - Configuração n8n
   - `CHANGELOG_MCP_SSE.md` - Detalhes das alterações
   - `n8n-mcp-server/README.md` - Documentação técnica

3. **Executar testes:**
   ```bash
   MCP_URL=https://mcp.conext.click node n8n-mcp-server/test-sse.js
   ```

4. **Rollback se necessário:**
   - Seguir instruções na seção "Rollback" acima

---

## ✅ Conclusão

Após seguir este guia:

- ✅ Servidor MCP com suporte SSE deployado
- ✅ Ambos HTTP REST e SSE funcionando
- ✅ n8n pode conectar com qualquer método
- ✅ Zero downtime (se feito corretamente)
- ✅ Rollback disponível se necessário

**Status:** 🚀 Pronto para produção

**Documentação:** 📚 Completa

**Testes:** ✅ Validados

---

**Última atualização:** 05/11/2025  
**Autor:** AI Assistant  
**Versão:** 1.0.0


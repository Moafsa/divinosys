# 🔧 Como Ativar a IA via n8n

## ⚠️ PROBLEMA ATUAL

A IA está usando **OpenAI direto**, o que causa:
- ❌ Não consegue fazer alterações no banco de dados
- ❌ Contexto cresce muito (histórico no prompt)
- ❌ Menos eficiente

## ✅ SOLUÇÃO: Usar n8n com MCP

O fluxo n8n já está **pronto e funcionando**. Só precisa ativar.

---

## 🚀 PASSO A PASSO

### 1️⃣ Editar o arquivo `.env`

```bash
# Abra o arquivo .env
nano .env
```

### 2️⃣ Mudar `USE_N8N_AI` para `true`

**Antes:**
```env
USE_N8N_AI=false
```

**Depois:**
```env
USE_N8N_AI=true
```

### 3️⃣ Verificar URL do webhook n8n

Certifique-se que a URL está correta:

```env
AI_N8N_WEBHOOK_URL=https://wapp.conext.click/webhook/ai-chat
```

### 4️⃣ Reiniciar o container

```bash
docker-compose restart app
```

### 5️⃣ Testar

1. Faça **logout** (vai limpar o histórico)
2. Faça **login** novamente
3. Teste a IA:
   - "cria um ingrediente chamado erva"
   - "lista os produtos"
   - "cria uma categoria Bebidas"

---

## 📊 DIFERENÇAS

| Funcionalidade | OpenAI Direto (atual) | n8n + MCP (recomendado) |
|----------------|----------------------|-------------------------|
| Criar produtos | ❌ Falha | ✅ Funciona |
| Criar ingredientes | ❌ Falha | ✅ Funciona |
| Consultar dados | ⚠️ Limitado | ✅ Via MCP |
| Histórico | ❌ Cresce muito | ✅ Gerenciado |
| Performance | 🐢 Lento | 🚀 Rápido |
| WhatsApp | ❌ Não integrado | ✅ Integrado |

---

## 🔍 COMO FUNCIONA COM N8N

```
┌─────────┐     ┌─────────┐     ┌─────────┐     ┌──────────┐
│ Usuario │ --> │ Widget  │ --> │  n8n    │ --> │ MCP      │
│         │     │ AI Chat │     │ Webhook │     │ Server   │
└─────────┘     └─────────┘     └─────────┘     └──────────┘
                                      │                │
                                      v                v
                                ┌─────────┐     ┌──────────┐
                                │ OpenAI  │     │ Database │
                                │   API   │     │  Query   │
                                └─────────┘     └──────────┘
```

1. **Usuario** envia mensagem
2. **Widget** envia para n8n
3. **n8n** processa com AI Agent:
   - Identifica o tipo de solicitação
   - Chama MCP se precisar consultar/alterar dados
   - Gera resposta com contexto completo
4. **MCP** executa no banco de dados
5. **Resposta** volta para o usuário

---

## ⚙️ LOGS DE DEBUG

Para ver se está funcionando:

```bash
# Ver logs do app
docker-compose logs -f app | grep "ai_chat.php"

# Deve aparecer:
# "ai_chat.php - USE_N8N_AI: true"
# "ai_chat.php - Using N8nAIService"
```

---

## 🆘 TROUBLESHOOTING

### "Erro ao processar mensagem"
- Verifique se a URL do n8n está acessível
- Teste: `curl https://wapp.conext.click/webhook/ai-chat`

### "MCP Server não responde"
- Verifique se o MCP está rodando
- Verifique `MCP_SERVER_URL` no `.env`

### "Operação não suportada"
- Isso não deve mais ocorrer com n8n
- O n8n chama o MCP para operações de escrita

---

## ✅ PRONTO!

Depois de ativar, a IA vai:
- ✅ Criar ingredientes
- ✅ Criar produtos
- ✅ Criar categorias
- ✅ Criar clientes
- ✅ Criar pedidos
- ✅ Consultar dados
- ✅ Cobrar fiado via WhatsApp

E o **histórico não vai mais ficar pesado**, pois o n8n gerencia isso.


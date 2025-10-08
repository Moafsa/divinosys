# 📋 Variáveis de Ambiente - Integração n8n

## 🎯 Separação de Webhooks n8n

Este sistema usa **múltiplos webhooks n8n** para diferentes funcionalidades. Para evitar confusão, cada um tem sua própria variável:

### Webhooks n8n no Sistema

```bash
# 1. Webhook n8n para AI/MCP (Assistente IA)
AI_N8N_WEBHOOK_URL=https://n8n.seudominio.com/webhook/ai-chat
AI_N8N_TIMEOUT=30

# 2. Webhook n8n para Wuzapi (WhatsApp)
WUZAPI_N8N_WEBHOOK_URL=https://n8n.seudominio.com/webhook/wuzapi
# (se você usa n8n para processar mensagens do WhatsApp)

# 3. Outros webhooks n8n (se houver)
# PEDIDOS_N8N_WEBHOOK_URL=...
# NOTIFICACOES_N8N_WEBHOOK_URL=...
```

## 🔧 Variáveis da Integração AI/MCP

### Obrigatórias

```bash
# Ativar integração n8n para IA
USE_N8N_AI=true

# URL do webhook n8n específico para IA/MCP
AI_N8N_WEBHOOK_URL=https://n8n.seudominio.com/webhook/ai-chat

# Chave OpenAI (necessária no n8n ou direto no sistema)
OPENAI_API_KEY=sk-...
```

### Opcionais

```bash
# Timeout para chamadas ao n8n (padrão: 30 segundos)
AI_N8N_TIMEOUT=30

# Chave de API do MCP Server (se exposto publicamente)
MCP_API_KEY=sua-chave-segura-aqui
```

## 📝 Exemplos de Configuração

### Desenvolvimento Local

```bash
# .env
USE_N8N_AI=true
AI_N8N_WEBHOOK_URL=http://localhost:5678/webhook/ai-chat
AI_N8N_TIMEOUT=30
OPENAI_API_KEY=sk-proj-...

# MCP Server usa localhost
MCP_PORT=3100
```

### Produção com n8n Externo

```bash
# .env
USE_N8N_AI=true
AI_N8N_WEBHOOK_URL=https://n8n.divinolanches.com/webhook/ai-chat
AI_N8N_TIMEOUT=30
OPENAI_API_KEY=sk-proj-...
MCP_API_KEY=chave-muito-segura-aqui
```

### Produção com n8n Cloud

```bash
# .env
USE_N8N_AI=true
AI_N8N_WEBHOOK_URL=https://seu-workspace.app.n8n.cloud/webhook/ai-chat
AI_N8N_TIMEOUT=30
OPENAI_API_KEY=sk-proj-...
```

## 🔍 Como Verificar se Está Configurado

### Verificar no Container

```bash
# Ver todas as variáveis AI/MCP
docker exec divino-lanches-app env | grep AI_

# Ver variável específica
docker exec divino-lanches-app env | grep AI_N8N_WEBHOOK_URL
```

### Verificar no Código

```php
// Em qualquer arquivo PHP do sistema
$config = \System\Config::getInstance();
$webhookUrl = $config->getEnv('AI_N8N_WEBHOOK_URL');
echo "Webhook IA: " . ($webhookUrl ?: 'NÃO CONFIGURADO');
```

## ⚠️ Erros Comuns

### Erro: "AI_N8N_WEBHOOK_URL not configured"

**Causa**: Variável não está no `.env` ou está vazia

**Solução**:
```bash
# Adicione ao .env
echo "AI_N8N_WEBHOOK_URL=https://seu-n8n.com/webhook/ai-chat" >> .env

# Reinicie o container
docker-compose restart app
```

### Erro: "Connection timeout to n8n"

**Causa**: URL incorreta ou n8n não está acessível

**Solução**:
```bash
# Teste a URL manualmente
curl https://seu-n8n.com/webhook/ai-chat

# Se não responder, verifique:
# 1. n8n está rodando?
# 2. Workflow está ativo?
# 3. URL está correta?
```

### Erro: Confusão entre webhooks

**Problema**: Sistema enviando mensagem IA para webhook do WhatsApp

**Causa**: Variáveis mal nomeadas ou confusas

**Solução**: Use nomes específicos como este guia:
- `AI_N8N_WEBHOOK_URL` → Para IA/MCP
- `WUZAPI_N8N_WEBHOOK_URL` → Para WhatsApp
- `PEDIDOS_N8N_WEBHOOK_URL` → Para pedidos
- etc.

## 🎨 Convenção de Nomes

Para manter organizado, siga este padrão:

```
{FUNCIONALIDADE}_N8N_{PROPRIEDADE}

Exemplos:
✅ AI_N8N_WEBHOOK_URL      (IA - webhook URL)
✅ AI_N8N_TIMEOUT          (IA - timeout)
✅ WUZAPI_N8N_WEBHOOK_URL  (WhatsApp - webhook URL)
✅ PEDIDOS_N8N_WEBHOOK_URL (Pedidos - webhook URL)

Evite:
❌ N8N_WEBHOOK_URL         (qual webhook?)
❌ WEBHOOK_URL             (qual serviço?)
❌ N8N_URL                 (qual funcionalidade?)
```

## 🔄 Migração de Configuração Antiga

Se você tinha configuração antiga com `N8N_WEBHOOK_URL`, migre assim:

```bash
# 1. Renomeie no .env
# De:
N8N_WEBHOOK_URL=https://n8n.com/webhook/ai-chat

# Para:
AI_N8N_WEBHOOK_URL=https://n8n.com/webhook/ai-chat

# 2. Se tinha para wuzapi
WUZAPI_N8N_WEBHOOK_URL=https://n8n.com/webhook/wuzapi

# 3. Reinicie
docker-compose restart app
```

## 📚 Referências

- **Integração AI/MCP**: `CONFIGURAR_N8N_EXTERNO.md`
- **Deploy**: `INSTALACAO_COMPLETA.md`
- **Troubleshooting**: `docs/N8N_DEPLOYMENT.md`

---

**Dica**: Sempre use prefixos específicos nas variáveis para evitar confusão quando tiver múltiplas integrações n8n! 🎯

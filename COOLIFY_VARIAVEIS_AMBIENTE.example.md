# Variáveis de Ambiente para Deploy no Coolify

⚠️ **IMPORTANTE**: Este é um arquivo de EXEMPLO. Não contém credenciais reais.

## 📋 Como Usar

1. Copie este conteúdo
2. No Coolify, vá em **Environment Variables**
3. Cole e **substitua** os valores de exemplo pelos reais
4. Nunca commite as credenciais reais no Git!

---

## 🔐 Template Completo para Coolify

```bash
# ===== DATABASE =====
DB_HOST=postgres
DB_PORT=5432
DB_NAME=divino_db
DB_USER=divino_user
DB_PASSWORD=MUDE_ESTA_SENHA_123!@#

# ===== APPLICATION =====
APP_ENV=production
APP_URL=https://seu-dominio.com

# ===== WUZAPI (WhatsApp) =====
WUZAPI_URL=http://wuzapi:8080
WUZAPI_API_KEY=GERE_TOKEN_ALEATORIO_AQUI
WUZAPI_DB_PASSWORD=SENHA_SEGURA_WUZAPI
WUZAPI_ADMIN_TOKEN=TOKEN_ADMIN_SEGURO

# ===== ASAAS (Payment Gateway) =====

# SANDBOX (Testes):
ASAAS_API_KEY=$aact_SEU_TOKEN_SANDBOX_AQUI
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_URL=https://seu-dominio.com/webhook/asaas.php

# PRODUÇÃO (Após configurar):
# ASAAS_API_KEY=$aact_SEU_TOKEN_DE_PRODUCAO_AQUI
# ASAAS_API_URL=https://api.asaas.com/v3

# ===== N8N (AI Integration - Opcional) =====
USE_N8N_AI=false
AI_N8N_WEBHOOK_URL=https://seu-n8n.com/webhook/ai
AI_N8N_TIMEOUT=30

# ===== REDIS =====
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# ===== OPENAI (Opcional) =====
OPENAI_API_KEY=sk-SEU_TOKEN_OPENAI_AQUI
```

---

## 🔑 Onde Obter as Credenciais

### 1. Asaas (Gateway de Pagamento)
- Acesse: https://www.asaas.com
- Vá em **Configurações** → **Integrações** → **API Key**
- Copie a chave (começa com `$aact_`)

### 2. WuzAPI (WhatsApp)
- Use o token padrão: `1234ABCD` (desenvolvimento)
- Para produção: gere um token aleatório seguro

### 3. OpenAI (Opcional)
- Acesse: https://platform.openai.com/api-keys
- Crie uma nova chave
- Copie o token (começa com `sk-`)

---

## ⚠️ AVISOS DE SEGURANÇA

### ❌ NUNCA faça:
- ❌ Commite arquivos `.env` no Git
- ❌ Exponha API keys em código
- ❌ Use credenciais de produção em desenvolvimento
- ❌ Compartilhe senhas em chats/emails

### ✅ SEMPRE faça:
- ✅ Use variáveis de ambiente
- ✅ Mantenha credenciais no Coolify/servidor
- ✅ Troque senhas periodicamente
- ✅ Use `.env.example` para documentar estrutura
- ✅ Adicione `.env` ao `.gitignore`

---

## 🚀 Deploy no Coolify

1. **Configure o repositório Git**
2. **Cole estas variáveis** (com valores reais) em **Environment Variables**
3. **Deploy!**

O sistema irá:
- ✅ Conectar no banco automaticamente
- ✅ Configurar Asaas para pagamentos
- ✅ Ativar WhatsApp via WuzAPI
- ✅ Funcionar em produção

---

## 📞 Suporte

Se precisar de ajuda para configurar:
- Verifique `DEPLOYMENT.example.md`
- Consulte a documentação do Coolify
- Entre em contato com o suporte


# 🚀 Variáveis de Ambiente para Deploy no Coolify

## 📋 Variáveis Obrigatórias

Configure estas variáveis no **Coolify** antes do deploy:

### 🔐 Banco de Dados
```bash
DB_HOST=postgres                    # Nome do serviço (interno)
DB_PORT=5432                        # Porta padrão PostgreSQL
DB_NAME=divino_db                   # Nome do banco
DB_USER=divino_user                 # Usuário do banco
DB_PASSWORD=SUA_SENHA_SEGURA_AQUI   # ⚠️  MUDAR para senha forte!
```

### 🌐 Aplicação
```bash
APP_ENV=production                  # Ambiente de produção
APP_URL=https://seu-dominio.com     # URL pública do sistema
```

### 📱 WhatsApp (WuzAPI)
```bash
WUZAPI_API_KEY=1234ABCD            # Token de autenticação WuzAPI
WUZAPI_DB_PASSWORD=SUA_SENHA_AQUI  # Senha do banco WuzAPI
WUZAPI_ADMIN_TOKEN=TOKEN_ADMIN     # Token admin WuzAPI
```

### 💳 Asaas (Gateway de Pagamento)
```bash
# SANDBOX (Testes):
ASAAS_API_KEY=$aact_YTU5YTE0M2M2N2I4MTliNzk0YTI5N2U5MzdjNWZmNDQ6OjAwMDAwMDAwMDAwMDAwNTUxNDA6OiRhYWNoXzFlZjMwZmUyLTNmZTktNGU3MC1iOTJkLWNjODkzYWU0MTI0Zg==
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_URL=https://seu-dominio.com/webhook/asaas.php

# PRODUÇÃO (Após configurar):
# ASAAS_API_KEY=$aact_SEU_TOKEN_DE_PRODUCAO_AQUI
# ASAAS_API_URL=https://api.asaas.com/v3
```

---

## 📋 Variáveis Opcionais

### 🤖 N8N (Workflow/IA)
```bash
N8N_USER=admin
N8N_PASSWORD=SUA_SENHA_N8N
N8N_HOST=n8n.seu-dominio.com
```

### 🧠 IA (OpenAI)
```bash
USE_N8N_AI=false                   # true se usar IA
OPENAI_API_KEY=sk-...              # Apenas se usar IA
AI_N8N_WEBHOOK_URL=https://...     # Webhook do N8N
AI_N8N_TIMEOUT=30
```

### 🔧 MCP Server
```bash
MCP_API_KEY=SEU_TOKEN_MCP
```

---

## 🎯 Template Completo para Coolify

Copie e cole no Coolify (Environment Variables):

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
WUZAPI_API_KEY=1234ABCD
WUZAPI_DB_PASSWORD=SENHA_WUZAPI_123
WUZAPI_ADMIN_TOKEN=admin_token_123

# ===== ASAAS (Payment Gateway) =====
ASAAS_API_KEY=$aact_YTU5YTE0M2M2N2I4MTliNzk0YTI5N2U5MzdjNWZmNDQ6OjAwMDAwMDAwMDAwMDAwNTUxNDA6OiRhYWNoXzFlZjMwZmUyLTNmZTktNGU3MC1iOTJkLWNjODkzYWU0MTI0Zg==
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_URL=https://seu-dominio.com/webhook/asaas.php

# ===== N8N (Opcional) =====
N8N_USER=admin
N8N_PASSWORD=SENHA_N8N_123
N8N_HOST=n8n.seu-dominio.com

# ===== IA (Opcional) =====
USE_N8N_AI=false
OPENAI_API_KEY=
AI_N8N_WEBHOOK_URL=
AI_N8N_TIMEOUT=30

# ===== MCP (Opcional) =====
MCP_API_KEY=token_mcp_123
```

---

## ⚠️ IMPORTANTE - Segurança

### Senhas a MUDAR:
- 🔴 `DB_PASSWORD` - **CRÍTICO!** Use senha forte
- 🔴 `WUZAPI_DB_PASSWORD` - Senha do banco WuzAPI
- 🔴 `N8N_PASSWORD` - Se usar N8N
- 🟡 `WUZAPI_API_KEY` - Token de acesso WuzAPI
- 🟡 `WUZAPI_ADMIN_TOKEN` - Token admin WuzAPI

### Senhas podem manter em produção:
- ✅ `ASAAS_API_KEY` - Vem do Asaas (depois troca para produção)

---

## 📊 Diferenças: docker-compose.yml vs coolify.yml

### docker-compose.yml (Desenvolvimento):
- ✅ Usa valores hardcoded (DB_PASSWORD=divino_password)
- ✅ Expõe portas (8080, 5433, 6379)
- ✅ Monta código direto (volume bind)

### coolify.yml (Produção):
- ✅ Usa variáveis do Coolify (${DB_PASSWORD})
- ✅ NÃO expõe portas (proxy reverso do Coolify)
- ✅ Código dentro da imagem (sem bind mount)
- ✅ Volumes nomeados persistentes

---

## 🎯 Checklist de Deploy no Coolify

### Antes do Deploy:
- [ ] Configurar TODAS as variáveis de ambiente acima
- [ ] Mudar DB_PASSWORD para senha forte
- [ ] Configurar APP_URL com domínio real
- [ ] Obter ASAAS_API_KEY (sandbox ou produção)
- [ ] Configurar N8N_HOST se usar IA

### Durante o Deploy:
- [ ] Coolify faz build automático
- [ ] Init scripts criam banco
- [ ] Migrations rodam automaticamente
- [ ] Backups são criados antes de cada migration

### Após o Deploy:
- [ ] Testar acesso ao sistema
- [ ] Registrar primeiro estabelecimento
- [ ] Configurar WhatsApp (WuzAPI)
- [ ] Testar Asaas (pagamentos)

---

## 🔗 Recursos

- **Asaas API:** https://docs.asaas.com
- **WuzAPI Docs:** https://github.com/wuzapi/wuzapi
- **N8N Docs:** https://docs.n8n.io

---

**📌 Mantenha este arquivo como referência para deploys futuros!**


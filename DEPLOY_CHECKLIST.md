# ✅ Checklist de Deploy - Divino Lanches

## 🎯 DEPLOY NO COOLIFY - PASSO A PASSO

### **ANTES DE FAZER PUSH:**

- [ ] **Verificar variáveis de ambiente sensíveis**
  ```bash
  # NÃO commitear arquivos com credenciais reais:
  git status | grep -E "\.env$|CREDENCIAIS|senha|password"
  ```

- [ ] **Verificar se `frontend.env` está no Git:**
  ```bash
  git ls-files docker/wuzapi/frontend.env
  # Deve retornar: docker/wuzapi/frontend.env
  ```

- [ ] **Verificar conteúdo do `frontend.env`:**
  ```bash
  cat docker/wuzapi/frontend.env
  # Deve ter: REACT_APP_API_URL=http://localhost:8081
  ```

---

### **APÓS PUSH PARA GITHUB:**

- [ ] **Coolify faz redeploy automático** (aguardar 3-5 min)

- [ ] **Verificar logs de cada serviço:**

#### **PostgreSQL:**
```
✅ database system is ready to accept connections
```

#### **Redis:**
```
✅ Ready to accept connections tcp
```

#### **MCP Server:**
```
✅ Divino Lanches MCP Server running on port 3100
```

#### **App (PHP):**
```
✅ MIGRATION COMPLETED SUCCESSFULLY
✅ Apache/2.4.65 configured -- resuming normal operations
```

#### **WuzAPI:**
```
✅ INFO Servidor iniciado... port=8080
✅ INFO  Accepting connections at http://localhost:3000
```

#### **n8n:**
```
✅ n8n ready on ::, port 5678
```

---

### **TESTAR O SISTEMA:**

- [ ] **Aplicação Principal:**
  ```
  http://SEU-DOMINIO.sslip.io/
  ```

- [ ] **WuzAPI Frontend:**
  ```
  http://WUZAPI-DOMINIO.sslip.io/login
  Token: admin123456 (ou seu token)
  ```

- [ ] **n8n:**
  ```
  https://N8N-DOMINIO.sslip.io/
  User: admin
  Password: (conforme configurado)
  ```

---

## 🔧 SE O DEPLOY FALHAR:

### **Erro: "SSH connection failed"**
- ✅ **Ação:** Tente novamente (pode ser temporário)
- ✅ **Verifique:** Sources → GitHub → Test Connection

### **Erro: "Port already allocated"**
- ✅ **Ação:** Remover `ports:` do `coolify.yml`
- ✅ Coolify gerencia portas automaticamente

### **Erro: "No such file or directory: frontend.env"**
- ✅ **Verificar:**
  ```bash
  git ls-files docker/wuzapi/frontend.env
  ```
- ✅ **Adicionar se necessário:**
  ```bash
  git add -f docker/wuzapi/frontend.env
  git commit -m "Add frontend.env"
  git push
  ```

### **Erro: "Token inválido" no WuzAPI**
- ✅ **Abrir console do navegador (F12)**
- ✅ **Verificar:** `API URL: http://localhost:????`
- ✅ **Se porta errada:** Force Rebuild (No Cache) no Coolify

---

## 📊 VARIÁVEIS DE AMBIENTE OBRIGATÓRIAS

### **Banco de Dados:**
```bash
DB_HOST=postgres
DB_PORT=5432
DB_NAME=divino_db
DB_USER=divino_user
DB_PASSWORD=SENHA_FORTE_AQUI  # ⚠️ Trocar!
```

### **WuzAPI:**
```bash
WUZAPI_DB_HOST=postgres
WUZAPI_DB_PORT=5432
WUZAPI_DB_NAME=wuzapi
WUZAPI_DB_USER=wuzapi  # ⚠️ NÃO divino_user!
WUZAPI_DB_PASSWORD=wuzapi  # ⚠️ Trocar em produção!
WUZAPI_ADMIN_TOKEN=admin123456  # ⚠️ Trocar em produção!
WUZAPI_API_KEY=admin123456  # ⚠️ Trocar em produção!
```

### **Asaas (Pagamentos):**
```bash
ASAAS_API_KEY=SUA_CHAVE_REAL
ASAAS_API_URL=https://sandbox.asaas.com/api/v3  # Sandbox
# ASAAS_API_URL=https://api.asaas.com/v3  # Produção
```

### **OpenAI (IA):**
```bash
OPENAI_API_KEY=SUA_CHAVE_REAL
```

---

## 🔐 SEGURANÇA - ANTES DE IR PARA PRODUÇÃO

**⚠️ TROCAR OBRIGATORIAMENTE:**

```bash
# Gere senhas fortes:
DB_PASSWORD=$(openssl rand -base64 32)
WUZAPI_DB_PASSWORD=$(openssl rand -base64 32)
WUZAPI_ADMIN_TOKEN=$(openssl rand -base64 32)
WUZAPI_API_KEY=$(openssl rand -base64 32)
MCP_API_KEY=$(openssl rand -base64 32)
```

---

## 📁 ARQUIVOS CRÍTICOS (SEMPRE NO GIT)

```
✅ docker/wuzapi/frontend.env (localhost:8081 para Coolify)
✅ database/init/*.sql (estrutura do banco)
✅ coolify.yml (configuração de serviços)
✅ Dockerfile (build da aplicação)
✅ docker/wuzapi/Dockerfile (build do WuzAPI)
```

---

## ❌ ARQUIVOS QUE NUNCA DEVEM IR PRO GIT

```
❌ .env (root do projeto)
❌ docker-compose.yml (senhas hardcoded)
❌ docker-compose.production.yml
❌ CREDENCIAIS_ACESSO.md
❌ COOLIFY_VARIAVEIS_AMBIENTE.md
❌ DEPLOYMENT.md
❌ backup_*.sql (backups de produção)
```

---

## 🚀 DEPLOY FUTURO - RESUMO

1. **Fazer mudanças no código**
2. **Commit e push:**
   ```bash
   git add .
   git commit -m "Descrição da mudança"
   git push divinosys main
   ```
3. **Coolify detecta e faz redeploy automático**
4. **Aguardar logs:**
   - PostgreSQL ✅
   - Redis ✅
   - App ✅
   - WuzAPI ✅
   - n8n ✅
5. **Testar a aplicação**

---

**Sistema consolidado e pronto para deploys futuros!** ✅


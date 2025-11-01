# 🔧 WuzAPI no Coolify - Solução Definitiva

## ✅ PROBLEMA RESOLVIDO

O WuzAPI frontend não conectava ao backend no Coolify devido à configuração incorreta da URL da API.

---

## 📋 SOLUÇÃO IMPLEMENTADA

### **1. Arquivo `docker/wuzapi/frontend.env`**

```bash
REACT_APP_API_URL=http://localhost:8081
```

**Importante:** Este arquivo DEVE estar commitado no Git para ser incluído no contexto de build do Docker!

### **2. Verificar se está no Git:**

```bash
git ls-files docker/wuzapi/frontend.env
```

**Deve retornar:** `docker/wuzapi/frontend.env`

Se retornar vazio, o arquivo NÃO está no Git!

### **3. Se não estiver, adicionar com `-f`:**

```bash
git add -f docker/wuzapi/frontend.env
git commit -m "Add frontend.env to Git"
git push
```

---

## 🎯 ARQUITETURA

```
Usuário → Coolify Proxy → WuzAPI Container
                              ↓
                    Frontend (porta 3000)
                    Backend (porta 8080)
                              ↓
Frontend faz: fetch('http://localhost:8081/api/...')
              ↓
Coolify Proxy Reverso traduz:
  localhost:8081 → wuzapi:8080 (interno)
              ↓
✅ Backend responde
```

---

## ✅ CHECKLIST DE VERIFICAÇÃO

### **No Git:**
- [ ] `docker/wuzapi/frontend.env` existe e tem `REACT_APP_API_URL=http://localhost:8081`
- [ ] Arquivo está commitado (não no `.gitignore`)

### **No Coolify:**
- [ ] `WUZAPI_ADMIN_TOKEN=admin123456` (ou seu token)
- [ ] `WUZAPI_API_KEY=admin123456` (ou sua chave)
- [ ] `WUZAPI_DB_USER=wuzapi` (**NÃO** `divino_user`!)
- [ ] `WUZAPI_DB_PASSWORD=wuzapi`
- [ ] `WUZAPI_DB_NAME=wuzapi`
- [ ] `WUZAPI_DB_HOST=postgres`

### **No Dockerfile:**
```dockerfile
COPY frontend.env /app/frontend/.env
RUN cd /app/frontend && npm install && npm run build
```

---

## 🔍 TROUBLESHOOTING

### **Se aparecer "Token inválido":**

1. **Veja o console do navegador (F12)**:
   ```
   API URL: http://localhost:????
   ```

2. **Se a porta estiver ERRADA:**
   - O `frontend.env` não foi copiado no build
   - Ou não está no Git
   - Ou o build usou cache antigo

3. **Solução:**
   ```bash
   # Verificar se está no Git:
   git ls-files docker/wuzapi/frontend.env
   
   # Se vazio, adicionar:
   git add -f docker/wuzapi/frontend.env
   git commit -m "Fix frontend.env"
   git push
   
   # No Coolify: Force Rebuild (sem cache)
   ```

### **Se aparecer "ERR_CONNECTION_REFUSED":**

1. **Backend WuzAPI não está rodando**
   - Veja logs do container `wuzapi`
   - Deve aparecer: `INFO Servidor iniciado... port=8080`

2. **Porta 8080 não exposta no Coolify**
   - Verifique no `coolify.yml` se tem `EXPOSE 8080` no Dockerfile
   - Backend deve estar na porta 8080

### **Se aparecer "ERR_NAME_NOT_RESOLVED":**

1. **URL usa nome Docker interno (ex: `http://wuzapi:8080`)**
   - Frontend DEVE usar: `http://localhost:8081`
   - Não use nomes de serviços Docker no frontend!

---

## 📊 HISTÓRICO DE TENTATIVAS

| # | Tentativa | Resultado |
|---|-----------|-----------|
| 1 | `http://wuzapi:8080` | ❌ Navegador não resolve nome Docker |
| 2 | `http://dominio:8080` | ❌ Porta 8080 não exposta |
| 3 | `http://dominio` (sem porta) | ❌ Proxy não configurado |
| 4 | URL vazia | ❌ React usa porta errada |
| 5 | **`http://localhost:8081`** | ✅ **FUNCIONA!** |

---

## 🚀 DEPLOY CORRETO

1. **Commit `frontend.env` no Git**
2. **Push para o repositório**
3. **Coolify faz redeploy automático**
4. **Aguarde build terminar** (~3-5 minutos)
5. **Acesse a URL do WuzAPI**
6. **Login com token configurado**
7. ✅ **SUCESSO!**

---

## 🔐 SEGURANÇA

**ATENÇÃO:** Antes de ir para produção, **TROQUE**:

```bash
WUZAPI_ADMIN_TOKEN=GERE_UMA_SENHA_FORTE_AQUI
WUZAPI_API_KEY=GERE_UMA_CHAVE_UNICA_AQUI
WUZAPI_DB_PASSWORD=SENHA_FORTE_DO_BANCO
```

**Nunca use `admin123456` em produção!** ⚠️

---

**Sistema configurado conforme https://wuzapidiv.conext.click/ (sistema funcionando)** ✅


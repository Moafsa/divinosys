# 🔧 Como Forçar Rebuild no Coolify (SEM Cache)

## 🔥 PROBLEMA

O Coolify está usando **build em cache** com o `frontend.env` ANTIGO!

Console mostra:
```
API URL: http://localhost:5555  ← ERRADO! (cache antigo)
```

Deveria mostrar:
```
API URL: http://localhost:8081  ← CORRETO! (frontend.env atual)
```

---

## ✅ SOLUÇÃO: FORCE REBUILD

### **PASSO 1: No Coolify, vá até o serviço WuzAPI**

1. Acesse: `https://coolify.conext.click/`
2. Vá em **Projects**
3. Selecione seu projeto
4. Clique no serviço **WuzAPI**

### **PASSO 2: Force Rebuild (sem cache)**

1. Procure pelo botão **"Deploy"** ou **"Redeploy"**
2. **ANTES de clicar**, procure por **opções avançadas** ou **"⋮" (três pontos)**
3. Marque a opção:
   - **"Force Rebuild"** ou
   - **"No Cache"** ou
   - **"Clear Build Cache"**
4. Clique em **Deploy**

---

## 🎯 ALTERNATIVA: Via Terminal do Coolify

Se o Coolify tiver um **Terminal** ou **SSH**:

```bash
# Parar o container WuzAPI
docker stop wuzapi-<hash>

# Remover a imagem antiga
docker rmi divino-wuzapi:latest

# Forçar rebuild sem cache
docker compose build --no-cache wuzapi

# Reiniciar
docker compose up -d wuzapi
```

---

## 🔍 COMO VERIFICAR SE FUNCIONOU

### **1. Veja os logs do WuzAPI:**

Deve aparecer:
```
 INFO  Accepting connections at http://localhost:3000
 HTTP  GET /login → Returned 200
 HTTP  GET /static/js/main.XXXXXXXX.js → Returned 200
```

**O hash do `main.XXXXXXXX.js` deve MUDAR!**

Se o hash for o mesmo do build anterior, ainda está usando cache!

### **2. Acesse o frontend e abra o Console (F12):**

Deve mostrar:
```
API URL: http://localhost:8081  ← CORRETO!
```

Se mostrar `localhost:5555` ou outra porta, ainda está usando build antigo!

---

## 📊 COMPARAÇÃO

| Console mostra | Status | Ação |
|----------------|--------|------|
| `localhost:5555` | ❌ Build antigo (cache) | Force Rebuild! |
| `localhost:8080` | ⚠️ Tentativa anterior | Force Rebuild! |
| `localhost:8081` | ✅ **CORRETO!** | Deve funcionar! |

---

## 🚨 SE AINDA NÃO FUNCIONAR

### **Verifique se o `frontend.env` está realmente sendo copiado:**

No Coolify, veja o **Build Log** e procure por:

```
Step X/Y : COPY frontend.env /app/frontend/.env
 ---> Using cache  ← PROBLEMA! Está usando cache!
```

**Deve aparecer:**
```
Step X/Y : COPY frontend.env /app/frontend/.env
 ---> abcd1234      ← Nova imagem! Não é cache!
```

---

## 📋 RESUMO

1. ✅ `frontend.env` está no Git com `localhost:8081`
2. ✅ Dockerfile copia `frontend.env` para `.env`
3. ❌ **Coolify está usando build em CACHE!**
4. 🔧 **SOLUÇÃO:** Force Rebuild (No Cache)

---

**Após o Force Rebuild, o sistema funcionará!** 🎉


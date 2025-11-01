# Configuração do Frontend WuzAPI no Coolify

## 🔥 PROBLEMA RESOLVIDO:

**Frontend WuzAPI dava "Token inválido" no Coolify, mas funcionava localmente.**

### Causa:
O frontend React estava **hardcoded** para `http://localhost:8080`, que funciona local, mas **não funciona no Coolify**!

---

## ✅ SOLUÇÃO IMPLEMENTADA:

### 1️⃣ **Variável de Ambiente para Frontend**

Agora o frontend aceita a URL do backend como variável de ambiente:

```env
# docker/wuzapi/frontend.env.example
REACT_APP_API_URL=${WUZAPI_API_URL:-http://localhost:8080}
```

### 2️⃣ **Dockerfile Atualizado**

O Dockerfile agora aceita a URL como argumento de build:

```dockerfile
ARG WUZAPI_API_URL=http://localhost:8080
RUN sed -i "s|\${WUZAPI_API_URL:-http://localhost:8080}|${WUZAPI_API_URL}|g" /app/frontend/.env
```

### 3️⃣ **Coolify.yml Configurado**

O `coolify.yml` passa a URL pública para o build:

```yaml
wuzapi:
  build:
    args:
      - WUZAPI_API_URL=${WUZAPI_PUBLIC_URL:-http://localhost:8080}
```

---

## 🚀 CONFIGURAÇÃO NO COOLIFY:

### **Adicione esta variável de ambiente:**

```bash
WUZAPI_PUBLIC_URL=http://SEU-DOMINIO.sslip.io:8080
```

**Exemplo:**
```bash
WUZAPI_PUBLIC_URL=http://gwwogckcs40488804804g8so.65.109.224.186.sslip.io:8080
```

---

## 📊 COMO FUNCIONA AGORA:

| Ambiente | Variável | Frontend acessa | Backend está em | Status |
|----------|----------|----------------|------------------|--------|
| **Local** | (padrão) | `http://localhost:8080` | `http://localhost:8080` | ✅ |
| **Coolify** | `WUZAPI_PUBLIC_URL=http://dominio:8080` | `http://dominio:8080` | `http://wuzapi:8080` (interno) | ✅ |

---

## 🔧 PASSO A PASSO NO COOLIFY:

1. **Acesse seu projeto no Coolify**
2. Vá em **Environment Variables**
3. Adicione:
   ```
   Nome: WUZAPI_PUBLIC_URL
   Valor: http://SEU-DOMINIO-WUZAPI.sslip.io:8080
   ```
4. **Redeploy** do projeto
5. O frontend agora vai acessar a URL pública correta! ✅

---

## 🧪 TESTE:

1. Acesse: `http://SEU-DOMINIO-WUZAPI:8080/login`
2. Token: `admin123456` (ou o valor de `WUZAPI_ADMIN_TOKEN`)
3. Clique em **Entrar**
4. ✅ Deve logar com sucesso!

---

## ⚠️ IMPORTANTE:

- **NÃO USE HTTPS** se o backend não tiver certificado SSL
- Se estiver atrás de um proxy/nginx, configure CORS no backend
- A porta **8080** precisa estar exposta no Coolify

---

**Agora o frontend funciona tanto local quanto no Coolify!** 🎉


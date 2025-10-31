# Guia de Deploy - Divino Lanches 2.0

⚠️ **IMPORTANTE**: Este arquivo contém apenas **EXEMPLOS**. Substitua pelos valores reais.

---

## 🚀 Deploy no Coolify (Recomendado)

### 1. Preparação

```bash
# Clone o repositório
git clone https://github.com/Moafsa/divinosys.git
cd divinosys
```

### 2. Configuração no Coolify

#### Variáveis Obrigatórias:

```bash
# Database
DB_HOST=postgres
DB_PORT=5432
DB_NAME=divinosys
DB_USER=divino_user
DB_PASSWORD=CRIE_UMA_SENHA_SUPER_SEGURA_AQUI_123!@#

# Application
APP_URL=https://seu-dominio.com.br
APP_KEY=base64:$(openssl rand -base64 32)  # Gere via comando ou online

# Asaas (Payment Gateway)
ASAAS_API_KEY=$aact_SEU_TOKEN_SANDBOX_OU_PRODUCAO_AQUI
ASAAS_API_URL=https://sandbox.asaas.com/api/v3  # Sandbox
# ASAAS_API_URL=https://api.asaas.com/v3  # Produção
ASAAS_WEBHOOK_URL=https://seu-dominio.com.br/webhook/asaas.php

# WuzAPI (WhatsApp)
WUZAPI_URL=http://wuzapi:8080
WUZAPI_API_KEY=1234ABCD  # Token padrão (troque em produção!)
```

#### Variáveis Opcionais:

```bash
# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=  # Deixe vazio se não usar senha

# Email (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=sua-senha-de-app-do-gmail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seu-dominio.com
MAIL_FROM_NAME=Divino Lanches

# Multi-tenant
ENABLE_MULTI_TENANT=true
DEFAULT_TENANT_ID=1

# AI Integration (Opcional)
USE_N8N_AI=false
AI_N8N_WEBHOOK_URL=
OPENAI_API_KEY=
```

### 3. Deploy

1. No Coolify, vá em **New Resource** → **Git Repository**
2. Cole a URL: `https://github.com/Moafsa/divinosys`
3. Configure as **Environment Variables** acima
4. Clique em **Deploy**

### 4. Primeiro Acesso

Após o deploy:

```bash
# Acesse o sistema
https://seu-dominio.com.br

# Login SuperAdmin (padrão - MUDE!)
Usuário: admin
Senha: admin123
```

⚠️ **IMPORTANTE**: Troque a senha imediatamente após o primeiro login!

---

## 🐳 Deploy Local (Docker)

### 1. Clone o repositório

```bash
git clone https://github.com/Moafsa/divinosys.git
cd divinosys
```

### 2. Configure o `.env`

```bash
# Copie o exemplo
cp env.example .env

# Edite com suas credenciais
nano .env  # ou use seu editor favorito
```

### 3. Suba os containers

```bash
docker-compose up -d
```

### 4. Acesse

```
http://localhost:8080
```

---

## 🔑 Obtendo Credenciais

### Asaas API Key

1. Acesse https://www.asaas.com
2. Faça login
3. Vá em **Configurações** → **Integrações** → **API Key**
4. Copie a chave (formato: `$aact_...`)

**Sandbox vs Produção:**
- **Sandbox**: Use para testes (URL: `https://sandbox.asaas.com/api/v3`)
- **Produção**: Use após validar tudo (URL: `https://api.asaas.com/v3`)

### OpenAI API Key (Opcional)

1. Acesse https://platform.openai.com/api-keys
2. Clique em **Create new secret key**
3. Copie o token (formato: `sk-...`)

### Gmail App Password (Para Email)

1. Acesse https://myaccount.google.com/security
2. Ative **2-Step Verification**
3. Vá em **App passwords**
4. Gere uma senha para "Mail"
5. Use essa senha em `MAIL_PASSWORD`

---

## 🔒 Segurança

### ⚠️ CHECKLIST Obrigatório:

- [ ] Troquei todas as senhas padrão
- [ ] `APP_KEY` é único e seguro
- [ ] Nunca commitei `.env` no Git
- [ ] Revisei o `.gitignore`
- [ ] Configurei SSL/HTTPS no domínio
- [ ] Troquei senha do SuperAdmin após primeiro login
- [ ] Desabilitei debug em produção (`APP_ENV=production`)

### 📁 Arquivos que NUNCA devem ir pro Git:

```
.env
.env.local
.env.production
*.sql
backup_*.php
backup_*.sql
CREDENCIAIS_*.md
```

Estes já estão no `.gitignore`, mas **sempre verifique antes de fazer commit!**

---

## 🆘 Troubleshooting

### Erro: "Could not connect to database"

```bash
# Verifique se o PostgreSQL está rodando
docker ps | grep postgres

# Verifique as credenciais no .env
cat .env | grep DB_
```

### Erro: "Asaas API authentication failed"

```bash
# Verifique se a API key está correta (deve começar com $aact_)
# Confirme se está usando a URL correta (sandbox vs produção)
```

### Sistema lento

```bash
# Limpe o cache Redis
docker exec -it divino-lanches-redis redis-cli FLUSHALL

# Reinicie os containers
docker-compose restart
```

---

## 📞 Suporte

- **Documentação**: Veja os outros arquivos `.md` na raiz
- **Issues**: https://github.com/Moafsa/divinosys/issues
- **Email**: suporte@seudominio.com

---

## ✅ Pronto!

Seu sistema está no ar! 🎉

Próximos passos:
1. Cadastre os primeiros produtos
2. Configure as mesas
3. Teste um pedido completo
4. Integre o WhatsApp
5. Configure os relatórios

Bom trabalho! 🚀


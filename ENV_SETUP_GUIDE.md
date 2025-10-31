# Configuração de Variáveis de Ambiente - Asaas

## Como configurar a API Key do Asaas

### 1. Obter API Key do Asaas Sandbox

1. Acesse: https://sandbox.asaas.com
2. Crie uma conta ou faça login
3. Vá em Configurações > API
4. Copie sua `API Key` (formato: `$aact_XXXXXXXXX`)

### 2. Configurar no Docker

**Opção A: Criar arquivo `.env` na raiz do projeto**

Crie um arquivo `.env` na raiz do projeto com:

```env
# Asaas Payment Gateway (SANDBOX)
ASAAS_API_KEY=$aact_SUA_API_KEY_AQUI
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_URL=https://seu-dominio.com/webhook/asaas.php
```

**Opção B: Definir variáveis de ambiente do sistema**

No Windows PowerShell:
```powershell
$env:ASAAS_API_KEY="$aact_SUA_API_KEY_AQUI"
$env:ASAAS_API_URL="https://sandbox.asaas.com/api/v3"
```

### 3. Recriar os containers

Depois de configurar, recrie os containers:

```powershell
docker-compose down
docker-compose up -d
```

### 4. Verificar se funcionou

```powershell
docker exec divino-lanches-app env | findstr ASAAS
```

Deve mostrar:
```
ASAAS_API_KEY=$aact_...
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
```

### 5. Testar geração de fatura

1. Acesse: http://localhost:8080/index.php?view=register
2. Cadastre um novo estabelecimento
3. Verifique os logs:
```powershell
docker logs divino-lanches-app --tail 20 | Select-String "Asaas"
```

Se funcionar, deve aparecer:
- `OnboardingController - Asaas habilitado`
- `Criando cliente no Asaas`
- `Criando cobrança PIX no Asaas`

## Migração para Produção

Quando estiver pronto para produção:

1. Obtenha API Key de produção em: https://www.asaas.com
2. No `.env` ou variáveis de ambiente:
```env
ASAAS_API_KEY=$aact_PROD_API_KEY
ASAAS_API_URL=https://www.asaas.com/api/v3
```
3. Recrie os containers

**⚠️ IMPORTANTE:**
- **NUNCA** commite o arquivo `.env` com API keys reais no Git!
- Use `.env.example` para documentar as variáveis necessárias
- Na produção, use variáveis de ambiente do servidor ou secrets do Docker


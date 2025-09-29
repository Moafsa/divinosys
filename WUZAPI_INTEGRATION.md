# 🚀 Integração WuzAPI - API WhatsApp Moderna

## 📋 Visão Geral

A **WuzAPI** é uma API moderna em Go para WhatsApp que oferece:
- ✅ **QR Code nativo** para conexão
- ✅ **Webhooks** para eventos em tempo real
- ✅ **API REST** completa
- ✅ **Docker** para fácil deploy
- ✅ **Banco PostgreSQL** para persistência

## 🏗️ Arquitetura

```
Sistema Divino Lanches
├── BaileysManager.php (atualizado)
├── WuzAPIManager.php (novo)
├── webhook/wuzapi.php (novo)
└── docker/wuzapi/ (novo)
    ├── Dockerfile
    └── .env.example
```

## 🔧 Instalação

### Opção 1: Local (Recomendada)
```bash
# Executar script de instalação
chmod +x install_wuzapi.sh
./install_wuzapi.sh
```

### Opção 2: Manual
```bash
# 1. Criar banco PostgreSQL
docker run -d --name wuzapi-postgres \
    -e POSTGRES_USER=wuzapi \
    -e POSTGRES_PASSWORD=wuzapi \
    -e POSTGRES_DB=wuzapi \
    -p 5433:5432 \
    postgres:15

# 2. Construir e iniciar WuzAPI
docker-compose up -d wuzapi

# 3. Verificar status
curl http://localhost:8081/health
```

## 📊 Endpoints WuzAPI

### Criar Instância
```http
POST /api/instance/create
{
    "instance_name": "divas",
    "phone_number": "5554997092223",
    "webhook_url": "http://app:80/webhook/wuzapi.php"
}
```

### Gerar QR Code
```http
GET /api/instance/{instance_id}/qrcode
```

### Verificar Status
```http
GET /api/instance/{instance_id}/status
```

### Enviar Mensagem
```http
POST /api/instance/{instance_id}/send
{
    "number": "5554997092223",
    "message": "Olá!",
    "type": "text"
}
```

## 🔄 Fluxo de Integração

1. **Usuário clica "Conectar"** → Sistema chama `WuzAPIManager`
2. **WuzAPI cria instância** → Retorna `instance_id`
3. **Sistema solicita QR** → `GET /api/instance/{id}/qrcode`
4. **WuzAPI gera QR** → Retorna base64 do QR code
5. **Frontend exibe QR** → Usuário escaneia
6. **Webhook recebe status** → Atualiza banco local

## 🎯 Vantagens da WuzAPI

### ✅ Comparado ao Baileys:
- **Mais estável** - Menos problemas de conexão
- **API REST** - Mais fácil de integrar
- **Webhooks nativos** - Eventos em tempo real
- **Banco persistente** - Sessões duradouras

### ✅ Comparado ao Chatwoot:
- **QR Code direto** - Sem dependência externa
- **Controle total** - API própria
- **Performance** - Go é mais rápido que Node.js
- **Manutenção** - Menos dependências

## 🔧 Configuração

### Variáveis de Ambiente
```env
# WuzAPI - Comunicação interna entre containers
WUZAPI_URL=http://wuzapi:8080  # Interno: wuzapi:8080
WUZAPI_API_KEY=your_api_key_here

# Banco
DB_HOST=postgres
DB_PORT=5432
DB_NAME=wuzapi
DB_USER=wuzapi
DB_PASSWORD=wuzapi

# Webhook
WEBHOOK_URL=http://app:80/webhook/wuzapi.php
```

### Mapeamento de Portas
```
Docker Interno          Docker Externo (Coolify)
├── app:80              → 8080 (sistema)
├── wuzapi:8080         → 8081 (WuzAPI)
├── postgres:5432       → 5432 (banco)
└── redis:6379          → 6379 (cache)
```

### Docker Compose
```yaml
wuzapi:
  build: ./docker/wuzapi
  ports:
    - "8081:8080"
  environment:
    - DB_HOST=postgres
    - WEBHOOK_URL=http://app:80/webhook/wuzapi.php
  volumes:
    - wuzapi_sessions:/app/sessions
```

## 🧪 Testes

### Testar API
```bash
# Health check
curl http://localhost:8081/health

# Criar instância
curl -X POST http://localhost:8081/api/instance/create \
  -H "Content-Type: application/json" \
  -d '{"instance_name":"teste","phone_number":"5554997092223"}'

# Gerar QR
curl http://localhost:8081/api/instance/1/qrcode
```

### Testar Webhook
```bash
# Simular evento
curl -X POST http://localhost:8080/webhook/wuzapi.php \
  -H "Content-Type: application/json" \
  -d '{"event":"qr","instance_id":"1","qrcode":"base64..."}'
```

## 📈 Monitoramento

### Logs
```bash
# Logs da WuzAPI
docker-compose logs wuzapi

# Logs do webhook
tail -f logs/security.log
```

### Status
- **WuzAPI**: http://localhost:8081/health
- **API Docs**: http://localhost:8081/docs
- **Banco**: postgresql://wuzapi:wuzapi@localhost:5433/wuzapi

## 🚀 Deploy em Produção

### Coolify/Portainer
1. **Criar novo serviço** com Docker Compose
2. **Configurar variáveis** de ambiente
3. **Expor porta** 8081
4. **Configurar webhook** para seu domínio

### VPS/Dedicado
1. **Instalar Docker** e Docker Compose
2. **Clonar repositório** com WuzAPI
3. **Configurar .env** com suas credenciais
4. **Executar** `docker-compose up -d`

## 🔄 Migração

### Do Baileys para WuzAPI
1. **Manter BaileysManager** como wrapper
2. **Adicionar WuzAPIManager** como nova opção
3. **Fallback automático** se WuzAPI não estiver disponível
4. **Migração gradual** das instâncias

### Do Chatwoot para WuzAPI
1. **Manter ChatwootManager** para compatibilidade
2. **Priorizar WuzAPI** para novas instâncias
3. **Migrar gradualmente** instâncias existentes

## 📚 Recursos

- **GitHub**: https://github.com/pedroherpeto/wuzapi
- **Documentação**: http://localhost:8081/docs
- **Health Check**: http://localhost:8081/health
- **Logs**: `docker-compose logs wuzapi`

## 🎯 Próximos Passos

1. **Testar instalação** local
2. **Configurar webhook** no sistema
3. **Testar criação** de instância
4. **Testar geração** de QR code
5. **Deploy em produção** (Coolify/Portainer)
6. **Migrar instâncias** existentes
7. **Monitorar performance** e logs

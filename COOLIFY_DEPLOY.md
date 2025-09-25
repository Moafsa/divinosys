# Deploy no Coolify - Divino Lanches

## 📋 Pré-requisitos

1. **Conta no Coolify** configurada
2. **Domínio** configurado no Coolify
3. **Variáveis de ambiente** configuradas

## 🚀 Deploy

### 1. Configurar Variáveis de Ambiente

No Coolify, configure as seguintes variáveis:

```bash
# Database
POSTGRES_DB=divino_lanches
POSTGRES_USER=postgres
POSTGRES_PASSWORD=senha_super_segura_aqui

# Evolution API
EVOLUTION_API_KEY=f6aDAgzTzwbYh2Bxwz2JYaKH
EVOLUTION_SERVER_URL=https://seu-dominio.com

# Application
APP_URL=https://seu-dominio.com
APP_ENV=production
APP_DEBUG=false

# n8n Webhook
N8N_WEBHOOK_URL=https://whook.conext.click/webhook/divinosyslgpd
```

### 2. Deploy

1. **Conecte o repositório** no Coolify
2. **Selecione o arquivo** `coolify.yml`
3. **Configure as variáveis** de ambiente
4. **Faça o deploy**

## 🔧 Serviços Incluídos

### PostgreSQL
- **Porta:** 5432 (interno)
- **Database:** divino_lanches
- **Volume:** postgres_data

### Redis
- **Porta:** 6379 (interno)
- **Volume:** redis_data

### Evolution API
- **Porta:** 8080 (interno)
- **Webhook:** /webhook/evolution
- **Volumes:** evolution_data, evolution_logs

### Divino Lanches App
- **Porta:** 80 (externa)
- **Health Check:** /health-check.php
- **Volumes:** app_uploads, app_logs

## 🌐 URLs

Após o deploy:

- **Aplicação:** https://seu-dominio.com
- **Evolution API:** https://seu-dominio.com:8080 (interno)
- **Health Check:** https://seu-dominio.com/health-check.php

## 📊 Monitoramento

### Health Checks
- **PostgreSQL:** Verifica conexão
- **Redis:** Verifica ping
- **Evolution:** Verifica API
- **App:** Verifica database connection

### Logs
- **App:** /var/www/html/logs
- **Evolution:** /evolution/logs

## 🔐 Segurança

### Variáveis Sensíveis
- `POSTGRES_PASSWORD`: Senha forte para o banco
- `EVOLUTION_API_KEY`: Chave da Evolution API
- `N8N_WEBHOOK_URL`: URL do webhook n8n

### Separação de Bancos
- **App Database**: `divino_lanches` (configurado via `POSTGRES_DB`)
- **Evolution Database**: `evolution_db` (criado automaticamente pela Evolution API)

### Volumes
- Todos os dados são persistidos em volumes
- Backup automático via Coolify

## 🚨 Troubleshooting

### Problemas Comuns

1. **Database connection failed**
   - Verifique `POSTGRES_PASSWORD`
   - Verifique se o PostgreSQL está healthy

2. **Evolution API não conecta**
   - Verifique `EVOLUTION_API_KEY`
   - Verifique `EVOLUTION_SERVER_URL`

3. **Webhook não funciona**
   - Verifique `N8N_WEBHOOK_URL`
   - Verifique se o n8n está configurado

### Logs
```bash
# Ver logs da aplicação
coolify logs app

# Ver logs da Evolution
coolify logs evolution

# Ver logs do PostgreSQL
coolify logs postgres
```

## 📈 Escalabilidade

### Recursos Recomendados
- **CPU:** 2 cores
- **RAM:** 4GB
- **Storage:** 20GB

### Auto-scaling
- Configure no Coolify conforme necessário
- Monitor via health checks

## 🔄 Backup

### Automático
- Volumes são backupados automaticamente pelo Coolify
- Database backup via PostgreSQL

### Manual
```bash
# Backup do banco
pg_dump -h postgres -U postgres divino_lanches > backup.sql

# Backup dos volumes
coolify backup volumes
```

## 📞 Suporte

Para problemas específicos:
1. Verifique os logs
2. Verifique as variáveis de ambiente
3. Verifique os health checks
4. Consulte a documentação do Coolify

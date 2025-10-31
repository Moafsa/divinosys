# Guia de Deploy no Coolify

## ✅ Backup Automático Configurado!

O sistema agora **faz backup automaticamente** a cada deploy! 🎉

O script `docker/start-production.sh` foi configurado para:
1. ✅ Criar backup automaticamente antes de executar migrations
2. ✅ Salvar backup no diretório `/backups` (que persiste entre deploys)
3. ✅ Manter apenas os últimos 5 backups para economizar espaço
4. ✅ Executar migrations de forma segura

## Procedimento de Deploy Seguro

### No Coolify - Clicar em "Redeploy"

Agora você pode simplesmente clicar em "Redeploy" no Coolify! O sistema fará automaticamente:

1. **Criar backup automático** (se banco existir)
2. **Executar migrations** (criar tabelas/colunas que faltam)
3. **Iniciar aplicação**

### O que Acontece Automaticamente

Durante o startup do container, o script `docker/start-production.sh` executa:

```bash
# 1. Cria backup
pg_dump ... > backups/db_backup_TIMESTAMP.sql.gz

# 2. Executa migrations  
php database_migrate.php

# 3. Inicia aplicação
apache2-foreground
```

### Após o Deploy - Verificar

**Ver logs para confirmar que backup foi criado:**

```bash
docker logs <nome-container> | grep "Backup"
```

**Ver arquivos de backup:**

```bash
# No servidor
ls -lh ./backups/
```

### Testar a Aplicação

- Acesse a URL da aplicação
- Verifique se tudo está funcionando
- Teste funcionalidades críticas

### 5. Se Deu Errado - Restaurar Backup

```bash
# Restaurar backup
docker exec -i divino-lanches-db psql -U divino_user -d divino_db < backup_YYYYMMDD_HHMMSS.sql

# Ou se está comprimido:
gunzip -c backup_YYYYMMDD_HHMMSS.sql.gz | docker exec -i divino-lanches-db psql -U divino_user -d divino_db
```

## Automação com Post-Deploy Script

Para automatizar o backup antes do deploy, você pode adicionar um "Post-Deploy Script" no Coolify:

### Configuração no Coolify:

1. Vá em **Settings** → **Application** → **Deployment**
2. Em **Post-Deploy Command**, adicione:

```bash
# Criar backup antes de executar migrations
BACKUP_FILE="/backups/db_backup_$(date +%Y%m%d_%H%M%S).sql"
mkdir -p /backups
docker exec divino-lanches-db pg_dump -U divino_user divino_db > $BACKUP_FILE && gzip $BACKUP_FILE

# Executar migrations
php database_migrate.php

# Mostrar status
if [ $? -eq 0 ]; then
    echo "✅ Deployment successful!"
else
    echo "❌ Migration failed! Check logs."
    exit 1
fi
```

## Checklist Antes de Cada Deploy

- [ ] Backup do banco de dados foi criado
- [ ] Backup foi salvo em local seguro (fora do container)
- [ ] Testei as migrations localmente
- [ ] Documentei mudanças que afetam produção
- [ ] Tenho plano de rollback
- [ ] Horário adequado (baixo tráfego)
- [ ] Equipe está ciente do deploy

## Respostas às Suas Perguntas

### ❌ Coolify NÃO executa backup automaticamente
Você precisa configurar isso manualmente ou via Post-Deploy Script.

### ✅ As migrations são seguras
Elas NÃO apagam dados. Usam `IF NOT EXISTS` para criar tabelas/colunas.

### ✅ Pode voltar atrás
Sim, use o script de restore com o backup criado antes do deploy.

### ⚠️ Importante: O Código JÁ ESTÁ no Git
Quando você clica em "Redeploy", o Coolify baixa o código do Git. As migrations que você criou aqui serão executadas em produção.

## Recomendação

**Antes do primeiro deploy em produção:**

1. Teste TUDO localmente primeiro
2. Crie um backup manual de teste
3. Rode as migrations localmente
4. Verifique se nada quebrou
5. Só então faça o deploy em produção


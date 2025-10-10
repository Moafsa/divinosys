# 🚀 Guia de Instalação - Sistema SaaS Divino Lanches

## Pré-requisitos

- PHP 8.2 ou superior
- PostgreSQL 14 ou superior
- Composer (opcional)
- Git

---

## 📦 Passo 1: Executar Migrations

### Via Terminal

```bash
# Conectar ao PostgreSQL
psql -U postgres -d divino_lanches

# Executar o script de criação das tabelas SaaS
\i database/init/10_create_saas_tables.sql

# Verificar se as tabelas foram criadas
\dt
```

### Via pgAdmin

1. Abra o pgAdmin
2. Conecte ao banco `divino_lanches`
3. Clique com botão direito no banco → Query Tool
4. Abra o arquivo `database/init/10_create_saas_tables.sql`
5. Execute (F5)

---

## 🔐 Passo 2: Primeiro Acesso

### Credenciais Padrão do SuperAdmin

```
URL: http://localhost:8080/index.php?view=login_admin
Usuário: superadmin
Senha: password
```

⚠️ **IMPORTANTE**: Altere a senha em produção!

### Alterar Senha do SuperAdmin

```sql
UPDATE usuarios 
SET senha = '$2y$10$NOVA_SENHA_HASH_AQUI'
WHERE login = 'superadmin';
```

Para gerar hash da senha em PHP:
```php
echo password_hash('sua_nova_senha', PASSWORD_BCRYPT);
```

---

## ⚙️ Passo 3: Configuração Inicial

### 1. Acessar Dashboard do SuperAdmin

Após login, você verá:
- Total de estabelecimentos: 0
- Assinaturas ativas: 0
- Receita mensal: R$ 0
- Planos cadastrados: 4

### 2. Verificar Planos Cadastrados

Os seguintes planos já foram criados automaticamente:
- ✅ Starter - R$ 49,90/mês
- ✅ Professional - R$ 149,90/mês
- ✅ Business - R$ 299,90/mês
- ✅ Enterprise - R$ 999,90/mês

### 3. Customizar Planos (Opcional)

Acesse "Planos" no menu lateral e edite conforme necessário.

---

## 🏪 Passo 4: Criar Primeiro Estabelecimento

### Opção 1: Via Onboarding (Recomendado)

```
URL: http://localhost:8080/index.php?view=onboarding
```

Preencha o formulário em 4 passos:
1. Dados básicos do estabelecimento
2. Escolha do plano
3. Configurações iniciais
4. Finalização

### Opção 2: Via Dashboard do SuperAdmin

1. Acesse o Dashboard do SuperAdmin
2. Clique em "Estabelecimentos" no menu
3. Clique em "Novo Estabelecimento"
4. Preencha os dados e salve

---

## 🧪 Passo 5: Testar o Sistema

### 1. Criar Estabelecimento de Teste

```
Nome: Lanchonete Teste
Subdomain: teste
Email: teste@exemplo.com
Telefone: (11) 99999-9999
Plano: Starter
```

### 2. Criar Usuário Admin do Estabelecimento

```
Login: admin
Senha: admin123
```

### 3. Fazer Login

```
URL: http://localhost:8080/index.php?view=login_admin
Usuário: admin
Senha: admin123
```

### 4. Verificar Funcionalidades

- ✅ Dashboard principal carrega
- ✅ Mesas são exibidas
- ✅ Pode criar pedidos
- ✅ Produtos podem ser cadastrados
- ✅ Relatórios funcionam

---

## 🔧 Passo 6: Configurações Adicionais

### 1. Configurar Variáveis de Ambiente

Edite o arquivo `.env`:

```env
# Database
DB_HOST=localhost
DB_PORT=5432
DB_NAME=divino_lanches
DB_USER=postgres
DB_PASSWORD=sua_senha

# App
APP_NAME="Divino Lanches SaaS"
APP_URL=http://localhost:8080
APP_DEBUG=true

# Multi-tenant
MULTI_TENANT_ENABLED=true
DEFAULT_TENANT_ID=1

# Email (opcional)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu@email.com
MAIL_PASSWORD=sua_senha
MAIL_FROM_ADDRESS=noreply@divinolanches.com
MAIL_FROM_NAME="Divino Lanches"

# Gateway de Pagamento (opcional)
PAYMENT_GATEWAY=stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
```

### 2. Configurar Permissões

```bash
chmod -R 755 mvc/
chmod -R 775 logs/
chmod -R 775 uploads/
chmod -R 775 sessions/
```

### 3. Configurar Apache/Nginx

#### Apache (.htaccess)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?view=$1 [L,QSA]
```

#### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## 🌐 Passo 7: Deploy em Produção

### Usando Coolify

1. **Conectar Repositório**
   ```
   https://github.com/seu-usuario/divino-lanches
   ```

2. **Configurar Variáveis**
   - Todas as variáveis do `.env`
   - `APP_DEBUG=false`
   - `APP_URL=https://seu-dominio.com`

3. **Deploy**
   - Coolify irá buildar e deployar automaticamente
   - Executar migrations automaticamente

### Usando Docker

```bash
# Build
docker-compose build

# Start
docker-compose up -d

# Verificar logs
docker-compose logs -f app
```

### Pós-Deploy

1. **Executar Migrations**
   ```bash
   docker exec -it divino-lanches-app bash
   psql -U postgres -d divino_lanches -f database/init/10_create_saas_tables.sql
   ```

2. **Verificar Saúde**
   ```bash
   curl https://seu-dominio.com/health-check.php
   ```

3. **Configurar SSL**
   - Let's Encrypt (Coolify faz automaticamente)
   - Cloudflare
   - Certificado próprio

---

## 📊 Passo 8: Monitoramento

### 1. Verificar Logs

```bash
# Logs da aplicação
tail -f logs/app.log

# Logs do PostgreSQL
tail -f /var/log/postgresql/postgresql-14-main.log
```

### 2. Monitorar Banco de Dados

```sql
-- Ver conexões ativas
SELECT * FROM pg_stat_activity;

-- Ver tamanho do banco
SELECT pg_size_pretty(pg_database_size('divino_lanches'));

-- Ver tabelas maiores
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 10;
```

### 3. Métricas Importantes

- Tempo de resposta das páginas
- Taxa de erro
- Uso de CPU e memória
- Conexões com banco de dados
- Taxa de conversão (trial → pago)

---

## 🔄 Passo 9: Backup e Restauração

### Backup Automático

```bash
# Criar script de backup
nano /usr/local/bin/backup-divino.sh
```

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/divino-lanches"
mkdir -p $BACKUP_DIR

# Backup do banco
pg_dump -U postgres divino_lanches > $BACKUP_DIR/backup_$DATE.sql

# Backup dos uploads
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz uploads/

# Manter apenas últimos 30 dias
find $BACKUP_DIR -name "backup_*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "uploads_*.tar.gz" -mtime +30 -delete
```

```bash
# Tornar executável
chmod +x /usr/local/bin/backup-divino.sh

# Adicionar ao crontab (diariamente às 3h)
crontab -e
0 3 * * * /usr/local/bin/backup-divino.sh
```

### Restauração

```bash
# Restaurar banco
psql -U postgres divino_lanches < backup_20250101_030000.sql

# Restaurar uploads
tar -xzf uploads_20250101_030000.tar.gz
```

---

## 🐛 Passo 10: Troubleshooting

### Problema: Erro ao conectar ao banco

```bash
# Verificar se PostgreSQL está rodando
systemctl status postgresql

# Verificar conexão
psql -U postgres -d divino_lanches -c "SELECT 1"

# Ver logs
tail -f /var/log/postgresql/postgresql-14-main.log
```

### Problema: Permissões negadas

```bash
# Verificar proprietário
ls -la mvc/
ls -la logs/

# Corrigir permissões
chown -R www-data:www-data .
chmod -R 755 mvc/
chmod -R 775 logs/ uploads/ sessions/
```

### Problema: Assinatura não valida corretamente

```sql
-- Verificar assinatura
SELECT * FROM assinaturas WHERE tenant_id = 1;

-- Verificar status
UPDATE assinaturas SET status = 'ativa' WHERE tenant_id = 1;

-- Verificar data de trial
UPDATE assinaturas SET trial_ate = CURRENT_DATE + INTERVAL '14 days' WHERE tenant_id = 1;
```

### Problema: SuperAdmin não consegue acessar

```sql
-- Verificar usuário
SELECT * FROM usuarios WHERE login = 'superadmin';

-- Verificar nível
UPDATE usuarios SET nivel = 999 WHERE login = 'superadmin';

-- Redefinir senha
UPDATE usuarios SET senha = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE login = 'superadmin';
-- Nova senha: password
```

---

## ✅ Checklist Final

### Desenvolvimento

- [ ] PostgreSQL instalado e rodando
- [ ] Migrations executadas
- [ ] SuperAdmin criado e acessível
- [ ] Planos cadastrados
- [ ] Estabelecimento de teste criado
- [ ] Login funcionando
- [ ] Dashboard carregando

### Produção

- [ ] Variáveis de ambiente configuradas
- [ ] `APP_DEBUG=false`
- [ ] SSL configurado
- [ ] Backup automático configurado
- [ ] Senha do superadmin alterada
- [ ] Logs sendo gravados
- [ ] Monitoramento configurado
- [ ] Gateway de pagamento integrado
- [ ] Emails configurados
- [ ] Domínio próprio configurado

---

## 📚 Recursos Adicionais

### Documentação

- `SISTEMA_SAAS_DOCUMENTACAO.md` - Documentação completa do sistema
- `README.md` - Visão geral do projeto
- `ANALISE_COMPLETA_SISTEMA_DIVINO_LANCHES.md` - Análise técnica

### Suporte

- GitHub Issues: https://github.com/seu-usuario/divino-lanches/issues
- Email: suporte@divinolanches.com
- WhatsApp: (11) 99999-9999

---

## 🎉 Pronto!

Seu sistema SaaS está instalado e funcionando!

Próximos passos:
1. Customizar planos conforme seu negócio
2. Integrar gateway de pagamento
3. Configurar emails transacionais
4. Divulgar e começar a vender! 🚀

---

**Divino Lanches SaaS**
Sistema de Assinatura Multi-Tenant
© 2025 Todos os direitos reservados


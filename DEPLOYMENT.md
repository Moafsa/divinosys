# Guia de Deploy - Divino Lanches 2.0

## 🚀 Deploy no Coolify (Recomendado)

### 1. Preparação do Repositório

1. **Clone o repositório**:
```bash
git clone https://github.com/Moafsa/div1.0.git
cd div1.0
```

2. **Configure o repositório**:
```bash
git remote add origin https://github.com/Moafsa/div1.0.git
git add .
git commit -m "Initial commit"
git push -u origin main
```

### 2. Configuração no Coolify

1. **Acesse o Coolify** e crie um novo projeto
2. **Conecte o repositório** GitHub
3. **Configure as variáveis de ambiente**:

#### Variáveis Obrigatórias:
```
DB_HOST=postgres
DB_PORT=5432
DB_NAME=divinosys
DB_USER=divino_user
DB_PASSWORD=sua_senha_super_segura_aqui
APP_URL=https://seu-dominio.com
APP_KEY=base64:$(openssl rand -base64 32)
```

#### Variáveis Opcionais:
```
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=sua-senha-do-email
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seu-dominio.com
MAIL_FROM_NAME=Divino Lanches
ENABLE_MULTI_TENANT=true
DEFAULT_TENANT_ID=1
```

### 3. Deploy Automático

1. **Clique em "Deploy"** no Coolify
2. O sistema irá:
   - Fazer build da imagem Docker
   - Configurar o PostgreSQL
   - Configurar o Redis
   - Executar as migrações do banco
   - Iniciar a aplicação

### 4. Verificação

1. **Acesse a URL** fornecida pelo Coolify
2. **Teste o login** com as credenciais padrão:
   - Usuário: `admin`
   - Senha: `admin`
   - Estabelecimento: `divino`

## 🐳 Deploy com Docker Compose

### 1. Configuração Local

```bash
# Clone o repositório
git clone https://github.com/Moafsa/div1.0.git
cd div1.0

# Configure as variáveis
cp env.example .env
# Edite o .env com suas configurações

# Inicie os serviços
docker-compose up -d
```

### 2. Configuração de Produção

```bash
# Use o arquivo coolify.yml
docker-compose -f coolify.yml up -d
```

## 🔧 Configuração Manual

### 1. Servidor Web (Apache/Nginx)

#### Apache
```apache
<VirtualHost *:80>
    ServerName seu-dominio.com
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    root /var/www/html;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 2. PostgreSQL

```sql
-- Criar banco de dados
CREATE DATABASE divinosys;
CREATE USER divino_user WITH PASSWORD 'sua_senha';
GRANT ALL PRIVILEGES ON DATABASE divinosys TO divino_user;

-- Executar migrações
\c divinosys
\i database/init/01_create_schema.sql
\i database/init/02_insert_default_data.sql
```

### 3. Redis

```bash
# Instalar Redis
sudo apt-get install redis-server

# Configurar Redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 4. PHP

```bash
# Instalar PHP 8.2 e extensões
sudo apt-get install php8.2 php8.2-fpm php8.2-pgsql php8.2-redis php8.2-gd php8.2-zip php8.2-curl php8.2-mbstring

# Configurar PHP
sudo nano /etc/php/8.2/fpm/php.ini
```

## 🔒 Configurações de Segurança

### 1. SSL/HTTPS

```bash
# Instalar Certbot
sudo apt-get install certbot python3-certbot-apache

# Obter certificado
sudo certbot --apache -d seu-dominio.com
```

### 2. Firewall

```bash
# Configurar UFW
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

### 3. Backup

```bash
# Script de backup
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
pg_dump -h localhost -U divino_user divinosys > backup_$DATE.sql
tar -czf backup_$DATE.tar.gz backup_$DATE.sql uploads/ logs/
```

## 📊 Monitoramento

### 1. Logs

```bash
# Ver logs da aplicação
tail -f logs/application.log

# Ver logs de segurança
tail -f logs/security.log

# Ver logs do Apache
tail -f /var/log/apache2/error.log
```

### 2. Métricas

- **Uso de CPU/Memória**: `htop` ou `top`
- **Espaço em disco**: `df -h`
- **Conexões de banco**: `SELECT * FROM pg_stat_activity;`
- **Logs de acesso**: `/var/log/apache2/access.log`

## 🔄 Atualizações

### 1. Atualização via Git

```bash
# Fazer backup
./backup.sh

# Atualizar código
git pull origin main

# Rebuild containers
docker-compose down
docker-compose up -d --build
```

### 2. Atualização via Coolify

1. **Push das mudanças** para o repositório
2. **Coolify detecta** automaticamente
3. **Deploy automático** é executado

## 🆘 Troubleshooting

### Problemas Comuns

1. **Erro de conexão com banco**:
   - Verificar variáveis de ambiente
   - Verificar se PostgreSQL está rodando
   - Verificar permissões de usuário

2. **Erro 500**:
   - Verificar logs de erro
   - Verificar permissões de arquivo
   - Verificar configurações do PHP

3. **Upload de arquivos não funciona**:
   - Verificar permissões da pasta uploads/
   - Verificar configurações do PHP (upload_max_filesize)
   - Verificar espaço em disco

4. **Sessões não funcionam**:
   - Verificar configurações do Redis
   - Verificar permissões da pasta de sessão
   - Verificar configurações do PHP

### Comandos Úteis

```bash
# Ver status dos containers
docker-compose ps

# Ver logs dos containers
docker-compose logs -f

# Entrar no container
docker-compose exec app bash

# Reiniciar serviços
docker-compose restart

# Limpar volumes
docker-compose down -v
```

## 📞 Suporte

Para suporte técnico:

- **Email**: contato@divinolanches.com
- **GitHub Issues**: [https://github.com/Moafsa/div1.0/issues](https://github.com/Moafsa/div1.0/issues)
- **Documentação**: [README.md](README.md)

---

**Desenvolvido com ❤️ para o setor de alimentação**

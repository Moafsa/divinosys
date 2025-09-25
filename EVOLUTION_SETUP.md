# Evolution API - Setup Manual

## 📋 Comandos para Executar Após Deploy

### 1. Criar Banco de Dados Evolution

```bash
# Conectar ao container do PostgreSQL
docker exec -it <postgres_container_name> psql -U postgres

# Criar banco evolution_db
CREATE DATABASE evolution_db;

# Sair do psql
\q
```

### 2. Executar Migrações da Evolution API

```bash
# Conectar ao container da Evolution API
docker exec -it <evolution_container_name> sh

# Executar migrações
npx prisma db push --force-reset --schema ./prisma/postgresql-schema.prisma

# Sair do container
exit
```

### 3. Verificar se Funcionou

```bash
# Verificar tabelas criadas
docker exec -it <postgres_container_name> psql -U postgres -d evolution_db -c "\dt"

# Verificar logs da Evolution API
docker logs <evolution_container_name>
```

## 🔧 Nomes dos Containers (Coolify)

- **PostgreSQL**: `postgres-vgco4kosg0ko0k8c8w80sk00-xxxxx`
- **Evolution API**: `evolution-vgco4kosg0ko0k8c8w80sk00-xxxxx`

## 📝 Comandos Rápidos

```bash
# 1. Criar banco
docker exec -it postgres-vgco4kosg0ko0k8c8w80sk00-xxxxx psql -U postgres -c "CREATE DATABASE evolution_db;"

# 2. Executar migrações
docker exec -it evolution-vgco4kosg0ko0k8c8w80sk00-xxxxx npx prisma db push --force-reset --schema ./prisma/postgresql-schema.prisma

# 3. Verificar
docker exec -it postgres-vgco4kosg0ko0k8c8w80sk00-xxxxx psql -U postgres -d evolution_db -c "\dt"
```

## ✅ Resultado Esperado

- Banco `evolution_db` criado
- Tabela `Instance` e outras tabelas Evolution criadas
- Evolution API funcionando sem erros
- Sistema completo operacional

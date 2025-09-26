# 🔀 SUBSTITUIÇÃO DO BD ONLINE COM BACKUP LOCAL

## 📁 ARQUIVO GERADO
- **Backup:** `backup_local_2025_09_26_15_36_48.sql`
- **Tamanho:** 312 KB  
- **Formato:** PostgreSQL Dump Completo

## 🎯 OBJETIVO
Substituir o banco de dados online que está dando erro ao criar instâncias, usando dados que funcionam **localmente**.

---

## 📋 PROCEDIMENTO DE SUBSTITUIÇÃO

### 1️⃣ **ENVIAR ARQUIVO PARA O SERVIDOR**

```bash
# Via SCP ou interface do Coolify
# Subir arquivo: backup_local_2025_09_26_15_36_48.sql 
# Para: /app/backup_local_2025_09_26_15_36_48.sql
```

### 2️⃣ **PARAR SERVIÇOS ONLINE** 
No painel Coolify:
- **Aparelho:** Pause application
- **Exit services:** PostgreSQL também

### 3️⃣ **ESTAÇÃO DE RESTAURAÇÃO**

```bash
# 1. Conectar ao container PostgreSQL
docker exec -it [CONTAINER_POSTGRES_OLINE] bash

# 2. Fazer backup do banco atual (caso precise reverter)
pg_dump -U postgres divino_lanches > backup_antigo_$(date +%Y%m%d_%H%M%S).sql

# 3. DROPAR o banco problemático  
psql -U postgres -c "DROP DATABASE divino_lanches CASCADE;"

# 4. CRIAR banco novo
psql -U postgres -c "CREATE DATABASE divino_lanches OWNER postgres;"

# 5. RESTAURAR dados locais
psql -U postgres -d divino_lanches < /app/backup_local_2025_09_26_15_36_48.sql
```

### 4️⃣ **RESTAURAR VIA COOLIFY**
Alternativa mais simples:

1. **Coolify** → **Databases** 
2. **Drop Database:** `divino_lanches`
3. **Create New Database:** `divino_lanches`
4. **Import:** Upload arquivo `backup_local_2025_09_26_15_36_48.sql`

---

## 🔧 MÉTODO VIA SQL DIRETO

Se o Coolify não for suficiente:

```sql
-- 1. Conectar ao PostgreSQL master
\c postgres

-- 2. Dropar banco existente
DROP DATABASE divino_lanches CASCADE;

-- 3. Criar banco novo
CREATE DATABASE divino_lanches 
    WITH 
    OWNER = postgres
    ENCODING = 'UTF8'
    TABLESPACE = pg_default
    CONNECTION LIMIT = -1;

-- 4. Liberar conexões
SELECT pg_terminate_backend(pid) 
FROM pg_stat_activity 
WHERE datname = 'divino_lanches' AND pid <> pg_backend_pid();

-- 5. Importar dados locais
\i /app/backup_local_2025_09_26_15_36_48.sql
```

---

## ✅ VERIFICAÇÕES APÓS RESTORE

### Testar no painel online:
1. **Login:** admin / admin123
2. **Configurações** → **Usuários** 
3. **Criar nova instância** → **Deve funcionar!**

### Script de verificação:
```sql
-- Verificar tabelas importantes
SELECT table_name FROM information_schema.tables WHERE table_schema = 'public';

-- Checar instâncias WhatsApp  
SELECT * FROM whatsapp_instances LIMIT 5;

-- Checar perfis usuários
SELECT id, username, role, status FROM usuarios LIMIT 5;
```

---

## 🚨 EM CASO DE PROBLEMAS

### Rollback rápido:
```bash
# Restaurar backup antigo
psql -U postgres -d divino_lanches < backup_antigo_[TIMESTAMP].sql
```

### Debug conexão:
```bash
# Testar conectividade
docker exec [POSTGRES_CONTAINER] psql -U postgres -c "\l"
docker exec [POSTGRES_CONTAINER] psql -U postgres -d divino_lanches -c "\dt"
```

---

## ⚡ INSTRUÇÕES RÁPIDAS - RESUMIDAS

1. **Subir arquivo:** `backup_local_2025_09_26_15_36_48.sql` ao servidor
2. **Pause app** via Coolify  
3. **Drop database:** `divino_lanches`
4. **Create database:** `divino_lanches` novamente
5. **Import:** arquivo de backup
6. **Start app** - instâncias funcionarão! ✨

---

**📱 RESULTADO ESPERADO:** BD substituído com configurações que funcionam **localmente**, resolvendo erro de criação de instâncias online.

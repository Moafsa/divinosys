# Sistema de Migrações e Inicialização do Banco de Dados

## Visão Geral

Este documento descreve o sistema consolidado de migrações, inicialização e seeds do banco de dados do sistema Divino Lanches. O sistema garante que todas as migrações, seeds e correções sejam executadas na ordem correta durante o build ou deploy.

## Arquitetura

### Componentes Principais

1. **`database_migrate.php`** - Script principal consolidado que orquestra todas as operações
2. **`database/init/`** - Scripts de inicialização (executados em ordem numérica)
3. **`database/migrations/`** - Scripts de migração (executados em ordem alfabética)
4. **`database_migrations`** - Tabela de controle que rastreia quais migrations foram executadas

### Fluxo de Execução

```
1. Criar tabela de controle (database_migrations)
2. Executar scripts init (database/init/*.sql) em ordem numérica
3. Executar migrations (database/migrations/*.sql) em ordem alfabética
4. Corrigir sequences (database/init/99_fix_sequences.sql)
5. Verificar estado do banco de dados
```

## Estrutura de Diretórios

```
database/
├── init/              # Scripts de inicialização (ordem numérica)
│   ├── 00_init_database.sql
│   ├── 01_insert_essential_data.sql
│   ├── 02_create_missing_tables.sql
│   ├── 05_create_usuarios_globais.sql
│   ├── 06_create_whatsapp_tables.sql
│   ├── 10_create_saas_tables.sql
│   └── 99_fix_sequences.sql
│
└── migrations/        # Scripts de migração (ordem alfabética)
    ├── add_partial_payment_support.sql
    ├── create_cliente_system_tables.sql
    ├── create_financial_system.sql
    └── ...
```

## Scripts de Inicialização (Init)

### Ordem de Execução

Os scripts em `database/init/` são executados em ordem numérica baseada no prefixo do nome do arquivo:

- `00_*` - Executado primeiro (criação de estrutura base)
- `01_*` - Dados essenciais
- `02_*` - Tabelas adicionais
- `05_*` - Sistemas específicos
- `99_*` - Executado por último (correções e ajustes)

### Convenções

- Nomes devem começar com número para garantir ordem
- Usar `CREATE TABLE IF NOT EXISTS` para evitar erros em re-execução
- Usar `INSERT ... ON CONFLICT DO NOTHING` para dados iniciais
- Arquivos com extensão `.disabled` são ignorados

### Scripts Principais

#### `00_init_database.sql`
Cria estrutura base do banco de dados:
- Extensões PostgreSQL (uuid-ossp)
- Tabelas principais (tenants, planos, filiais, usuarios, etc.)
- Constraints e índices básicos

#### `01_insert_essential_data.sql`
Insere dados essenciais:
- Planos padrão
- Tenant padrão
- Filial padrão
- Usuário admin
- Categorias, ingredientes e produtos padrão
- Mesas padrão

#### `02_create_missing_tables.sql`
Cria tabelas adicionais necessárias:
- estoque
- whatsapp_instances
- log_pedidos
- entregadores
- movimentacoes_financeiras
- categorias_financeiras
- contas_financeiras

#### `99_fix_sequences.sql`
Corrige todas as sequences do banco de dados para evitar erros de chave duplicada.

## Migrations

### Sistema de Controle

O sistema mantém uma tabela `database_migrations` que rastreia:
- Nome do arquivo executado
- Tipo (init, migration, fix)
- Data/hora de execução
- Tempo de execução
- Status (sucesso/falha)
- Mensagens de erro (se houver)

### Execução Idempotente

- Migrations só são executadas uma vez
- Se uma migration já foi executada com sucesso, é pulada
- Migrations com falha podem ser re-executadas após correção

### Adicionando Nova Migration

1. Criar arquivo SQL em `database/migrations/`
2. Usar nome descritivo com prefixo numérico ou alfabético:
   ```sql
   -- database/migrations/20250115_add_new_feature.sql
   CREATE TABLE IF NOT EXISTS new_feature (
       id SERIAL PRIMARY KEY,
       ...
   );
   ```
3. O sistema executará automaticamente no próximo deploy

## Seeds

Atualmente, os seeds estão integrados nos scripts init (`01_insert_essential_data.sql`). Para seeds mais complexos, pode-se criar arquivos separados em `database/seeds/` e adicionar sua execução no script principal.

## Execução Automática

### Desenvolvimento (docker/start.sh)

O script é executado automaticamente quando o container é iniciado:
- Aguarda PostgreSQL e Redis estarem prontos
- Executa `database_migrate.php`
- Inicia Apache mesmo se houver warnings (não erros críticos)

### Produção (docker/start-production.sh)

O script é executado automaticamente quando o container é iniciado:
- Aguarda PostgreSQL estar pronto
- Executa `database_migrate.php`
- **Aborta** o startup se houver erros críticos
- Inicia Apache apenas se migration for bem-sucedida

## Execução Manual

```bash
# Via CLI
php database_migrate.php

# Via Docker
docker exec -it divino-lanches-app php database_migrate.php
```

## Verificação de Estado

O sistema verifica automaticamente:
- ✅ Tabelas essenciais existem (tenants, usuarios, produtos, categorias, mesas)
- ✅ Usuário admin existe
- ✅ Sequences estão sincronizadas

## Resolução de Problemas

### Migrations não executam

1. Verificar se o arquivo está em `database/migrations/`
2. Verificar se já foi executado:
   ```sql
   SELECT * FROM database_migrations WHERE migration_file = 'nome_arquivo.sql';
   ```
3. Se necessário, remover registro e re-executar:
   ```sql
   DELETE FROM database_migrations WHERE migration_file = 'nome_arquivo.sql';
   ```

### Sequences com erro

O script `99_fix_sequences.sql` é executado automaticamente. Se problemas persistirem:

```bash
php database_migrate.php
```

Ou manualmente:
```sql
SELECT setval('produtos_id_seq', (SELECT MAX(id) FROM produtos) + 1);
```

### Erros de duplicação

Scripts init usam `IF NOT EXISTS` e `ON CONFLICT DO NOTHING` para evitar erros. Se ainda ocorrerem:

1. Verificar logs do container
2. Verificar tabela `database_migrations` para ver qual script falhou
3. Corrigir o script SQL
4. Remover registro e re-executar

## Boas Práticas

1. **Sempre use `IF NOT EXISTS`** em CREATE TABLE
2. **Sempre use `ON CONFLICT DO NOTHING`** em INSERTs de dados iniciais
3. **Nomeie migrations com timestamps** para ordem clara
4. **Teste migrations localmente** antes de fazer deploy
5. **Use transações** para migrations complexas (quando possível)
6. **Documente mudanças** nos comentários SQL

## Troubleshooting

### Logs

Verificar logs do container:
```bash
docker logs divino-lanches-app
```

### Verificar migrations executadas

```sql
SELECT migration_file, migration_type, executed_at, success 
FROM database_migrations 
ORDER BY executed_at DESC;
```

### Re-executar migration específica

```sql
DELETE FROM database_migrations WHERE migration_file = 'nome_arquivo.sql';
```

Depois executar:
```bash
php database_migrate.php
```

## Compatibilidade

- PostgreSQL 12+
- PHP 8.1+
- Docker e Docker Compose
- Compatível com Coolify e outros orquestradores Docker

## Manutenção

### Adicionar novo script init

1. Criar arquivo com prefixo numérico: `03_nome_script.sql`
2. Colocar em `database/init/`
3. O sistema executará automaticamente na próxima inicialização

### Adicionar nova migration

1. Criar arquivo descritivo: `nome_migration.sql`
2. Colocar em `database/migrations/`
3. O sistema executará automaticamente na próxima inicialização

### Desabilitar script temporariamente

Renomear arquivo adicionando `.disabled`:
```
02_create_missing_tables.sql.disabled
```

O sistema ignorará arquivos com esta extensão.




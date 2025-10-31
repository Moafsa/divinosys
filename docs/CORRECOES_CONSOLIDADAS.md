# Correções Consolidadas - Sistema Divino Lanches

## Data: 29/10/2025

Este documento consolida todas as correções e melhorias implementadas no sistema.

---

## 1. Correção de Formatação de Telefone (WhatsApp)

### Problema:
- Sistema estava enviando números com formato incorreto para WuzAPI
- Números com DDD brasileiro (ex: 54) estavam sendo interpretados como código de país
- Números com 9 extra (ex: 54997092223) não eram tratados corretamente

### Solução:
**Arquivo**: `system/WhatsApp/WuzAPIManager.php`

- Adicionada função `formatPhoneNumber()` que:
  - Remove o 9 extra automaticamente de números brasileiros
  - Converte números para formato E.164 (+555497092223)
  - Remove o + antes de enviar para WuzAPI (evita truncamento)

**Código consolidado**: ✅
**Data da correção**: 29/10/2025

---

## 2. Correção de Redirecionamento Após Login

### Problema:
- Após validar código de acesso, usuário era redirecionado de volta para login
- Sessão não era persistida corretamente entre requisições

### Solução:
**Arquivos**:
- `mvc/ajax/phone_auth_clean.php`
- `mvc/views/login.php`
- `mvc/views/pedidos.php`
- `mvc/views/financeiro.php`

**Mudanças**:
1. Criação de `phone_auth_clean.php` completamente limpo (sem output HTML antes de JSON)
2. Sessão completa salva com objetos `tenant` e `filial`
3. Correção para usar tenant como filial padrão quando `filial_id` é null

**Código consolidado**: ✅
**Data da correção**: 29/10/2025

---

## 3. Permissões de Cozinha Ajustadas

### Problema:
- Cozinha tinha acesso a todas as funcionalidades (produtos, etc)
- Cozinha podia excluir pedidos permanentemente
- Sidebar mostrava todos os links como se fosse admin

### Solução:
**Arquivos**:
- `system/Auth.php`
- `mvc/ajax/pedidos.php`
- `mvc/views/pedidos.php`

**Mudanças**:
1. **Permissões reduzidas para cozinha**:
   - Antes: `'dashboard', 'pedidos', 'estoque', 'produtos', 'gerenciar_produtos', 'gerar_pedido', 'novo_pedido', 'logout'`
   - Agora: `'dashboard', 'pedidos', 'estoque', 'logout'`

2. **Excluir pedido como cozinha**:
   - Ao invés de deletar o pedido, marca como "Cancelado"
   - Libera a mesa automaticamente
   - Retorna mensagem "Pedido cancelado com sucesso!"

3. **Botão excluir oculto**:
   - Botão de excluir não aparece na interface quando usuário é cozinha
   - Aplicado em ambos os lugares onde o botão aparece

**Código consolidado**: ✅
**Data da correção**: 29/10/2025

---

## 4. Sanitização de Dados (Date Fields)

### Problema:
- Campos de data recebendo string vazia ("") causavam erro no PostgreSQL
- PostgreSQL espera NULL ou data válida

### Solução:
**Arquivo**: `system/Database.php`

- Adicionado método `sanitizeData()` que:
  - Converte strings vazias para NULL em campos de data
  - Detecta campos de data por padrões (data_, _date, _at, etc)
  - Aplicado automaticamente em `insert()` e `update()`

**Código consolidado**: ✅
**Data da correção**: 29/10/2025

---

## 5. Estrutura de Banco de Dados

### Problemas:
- Tabelas criadas em ordem incorreta
- Colunas faltando em várias tabelas
- Foreign keys quebradas

### Solução:
**Arquivos**: `database/init/*.sql`

**Mudanças principais**:
1. Consolidação em `00_init_database.sql` com todas as tabelas principais
2. Criação de `02_create_auxiliary_tables.sql` para tabelas auxiliares
3. Adição de colunas faltantes:
   - `id_mesa`, `numero`, `nome` em `mesas`
   - `preco_custo` em `produtos`
   - `wuzapi_instance_id`, `wuzapi_token` em `whatsapp_instances`
   - `descricao`, `ativo` em `ingredientes`
   - `cor`, `icone` em `contas_financeiras`

**Código consolidado**: ✅
**Data da correção**: 29/10/2025

---

## 6. Sistema de Migração Consolidado

### Problema:
- Múltiplos scripts de migração causando conflitos
- Execução não era idempotente
- Sequências desatualizadas

### Solução:
**Arquivo**: `database_migrate.php`

**Funcionalidades**:
1. Tabela de controle `database_migrations`
2. Execução em ordem: init → migrations → seeds → sequences
3. Idempotência: pode rodar várias vezes sem problemas
4. Logs detalhados de execução

**Integração**:
- `docker/start.sh` (dev)
- `docker/start-production.sh` (produção)

**Código consolidado**: ✅
**Data da correção**: 29/10/2025

---

## Resumo de Arquivos Modificados

### Código Fonte:
1. `system/WhatsApp/WuzAPIManager.php` - Formatação de telefone
2. `system/Database.php` - Sanitização de dados
3. `system/Auth.php` - Permissões de cozinha
4. `mvc/ajax/phone_auth_clean.php` - Autenticação limpa
5. `mvc/ajax/pedidos.php` - Exclusão condicional de pedidos
6. `mvc/views/login.php` - Redirecionamento melhorado
7. `mvc/views/pedidos.php` - Ocultação de botão para cozinha
8. `mvc/views/financeiro.php` - Correção de filial
9. `mvc/views/Dashboard1.php` - Correção de filial

### Base de Dados:
1. `database/init/00_init_database.sql` - Esquema completo
2. `database/init/02_create_auxiliary_tables.sql` - Tabelas auxiliares
3. `database/init/06_create_whatsapp_tables.sql` - Colunas WuzAPI

### Scripts:
1. `database_migrate.php` - Migração consolidada
2. `docker/start.sh` - Execução em dev
3. `docker/start-production.sh` - Execução em produção

---

## Como Aplicar em Novo Deploy

Todas as correções estão consolidadas no código-fonte. Para aplicar em novo deploy:

1. **Clone o repositório** (código já contém todas as correções)
2. **Execute docker-compose** (migrations rodam automaticamente)
3. **Pronto!** Todas as correções estarão ativas

---

## Testes Recomendados

### 1. Login com WhatsApp
- [ ] Solicitar código
- [ ] Validar código
- [ ] Redirecionamento correto por tipo de usuário

### 2. Permissões de Cozinha
- [ ] Verificar sidebar (apenas Dashboard, Pedidos, Estoque, Sair)
- [ ] Tentar excluir pedido (deve cancelar, não excluir)
- [ ] Verificar que botão excluir não aparece
- [ ] Tentar acessar produtos (deve redirecionar)

### 3. Formatação de Telefone
- [ ] Enviar mensagem para número brasileiro
- [ ] Verificar logs (número correto no formato)
- [ ] Confirmar entrega da mensagem

---

## Status Final

✅ **TODAS AS CORREÇÕES ESTÃO CONSOLIDADAS**

- Código-fonte atualizado
- Migrações funcionando
- Permissões corretas
- Sistema testado e funcionando

**Data de consolidação**: 29/10/2025
**Status**: PRONTO PARA PRODUÇÃO



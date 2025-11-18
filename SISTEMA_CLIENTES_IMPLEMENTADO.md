# 🎯 Sistema de Clientes - Divino Lanches

## 📋 Visão Geral

Sistema completo de gerenciamento de clientes implementado para o Divino Lanches, permitindo cadastro, histórico de pedidos, pagamentos e estabelecimentos visitados por cada cliente.

---

## ✅ Funcionalidades Implementadas

### 1. **Banco de Dados** ✅
- **Tabelas criadas:**
  - `usuarios_globais` - Dados dos clientes
  - `enderecos` - Endereços dos clientes
  - `preferencias_cliente` - Preferências por estabelecimento
  - `cliente_historico` - Histórico de interações
  - `cliente_estabelecimentos` - Estabelecimentos visitados
  - `pagamentos` - Histórico de pagamentos

- **Campos adicionados ao pedido:**
  - `usuario_global_id` - Referência ao cliente
  - `forma_pagamento` - Forma de pagamento
  - `status_pagamento` - Status do pagamento
  - `valor_pago` - Valor pago
  - `data_pagamento` - Data do pagamento

### 2. **Model Cliente.php** ✅
- **Métodos implementados:**
  - `create()` - Criar cliente
  - `findByTelefone()` - Buscar por telefone
  - `findByEmail()` - Buscar por email
  - `getById()` - Buscar por ID
  - `update()` - Atualizar dados
  - `getAll()` - Listar com filtros
  - `getHistoricoPedidos()` - Histórico de pedidos
  - `getHistoricoPagamentos()` - Histórico de pagamentos
  - `getEstabelecimentosVisitados()` - Estabelecimentos visitados
  - `registrarHistorico()` - Registrar interações
  - `atualizarVisitaEstabelecimento()` - Atualizar visita
  - `getEstatisticas()` - Estatísticas do cliente
  - `search()` - Buscar clientes
  - `getEnderecos()` - Endereços do cliente
  - `adicionarEndereco()` - Adicionar endereço
  - `getPreferencias()` - Preferências do cliente
  - `atualizarPreferencias()` - Atualizar preferências

### 3. **Controller ClienteController.php** ✅
- **APIs implementadas:**
  - `listar` - Listar clientes
  - `buscar` - Buscar clientes
  - `criar` - Criar cliente
  - `atualizar` - Atualizar cliente
  - `buscar_por_telefone` - Buscar por telefone
  - `historico_pedidos` - Histórico de pedidos
  - `historico_pagamentos` - Histórico de pagamentos
  - `estabelecimentos` - Estabelecimentos visitados
  - `estatisticas` - Estatísticas
  - `enderecos` - Endereços
  - `adicionar_endereco` - Adicionar endereço
  - `preferencias` - Preferências
  - `atualizar_preferencias` - Atualizar preferências
  - `desativar` - Desativar cliente

### 4. **Página de Clientes** ✅
- **Funcionalidades:**
  - Listagem de clientes com paginação
  - Busca por nome, telefone ou email
  - Filtros por status e ordenação
  - Estatísticas em tempo real
  - Modal de cadastro/edição
  - Modal de detalhes com abas:
    - Informações pessoais
    - Estatísticas
    - Estabelecimentos visitados
    - Histórico de pedidos
    - Histórico de pagamentos
    - Endereços
  - Exportação de dados
  - Desativação de clientes

### 5. **Integração com Pedidos** ✅
- **Formulário de pedido modificado:**
  - Campos opcionais de cliente (nome, telefone, email, CPF)
  - Busca automática por telefone
  - Carregamento automático de dados do cliente
  - Criação automática de cliente se não existir
  - Registro automático de interação

- **Processamento de pedidos:**
  - Vinculação automática do cliente ao pedido
  - Registro de histórico de interação
  - Atualização de estabelecimentos visitados
  - Cálculo de estatísticas

### 6. **Sistema de Histórico** ✅
- **Registro automático de:**
  - Cadastro de cliente
  - Atualização de dados
  - Realização de pedidos
  - Pagamentos
  - Visitas a estabelecimentos

- **Dados registrados:**
  - Tipo de interação
  - Descrição da ação
  - Dados anteriores e novos (JSON)
  - IP e User Agent
  - Timestamp

### 7. **Dashboard do Cliente** ✅
- **Informações exibidas:**
  - Dados pessoais completos
  - Estatísticas de consumo
  - Histórico de pedidos
  - Histórico de pagamentos
  - Estabelecimentos visitados
  - Endereços cadastrados
  - Preferências

---

## 🚀 Como Usar

### 1. **Executar Migração do Banco**
```sql
-- Execute o arquivo: execute_client_migration.sql
-- Ou execute via interface do banco de dados
```

### 2. **Acessar Sistema de Clientes**
- URL: `http://localhost/clientes`
- Menu: Clientes
- Funcionalidades disponíveis:
  - Listar clientes
  - Cadastrar novo cliente
  - Buscar por telefone/nome
  - Ver detalhes completos
  - Editar dados
  - Gerenciar endereços

### 3. **Usar no Formulário de Pedidos**
- Ao criar um pedido, preencher campos opcionais:
  - Nome do cliente
  - Telefone (com busca automática)
  - Email
  - CPF
- Sistema automaticamente:
  - Busca cliente existente por telefone
  - Cria novo cliente se não existir
  - Vincula cliente ao pedido
  - Registra histórico

---

## 📊 Estrutura de Dados

### Cliente (usuarios_globais)
```sql
- id (PK)
- nome (obrigatório)
- telefone (único)
- email (único)
- cpf
- data_nascimento
- telefone_secundario
- observacoes
- ativo (boolean)
- created_at, updated_at
```

### Endereço (enderecos)
```sql
- id (PK)
- usuario_global_id (FK)
- tenant_id (FK)
- tipo (entrega, cobranca, residencial, comercial)
- cep, logradouro, numero, complemento
- bairro, cidade, estado, pais
- referencia
- principal (boolean)
- ativo (boolean)
```

### Histórico (cliente_historico)
```sql
- id (PK)
- usuario_global_id (FK)
- tenant_id (FK)
- filial_id (FK)
- tipo_interacao (pedido, pagamento, cadastro, atualizacao)
- descricao
- dados_anteriores (JSONB)
- dados_novos (JSONB)
- ip_address, user_agent
- created_at
```

### Estabelecimentos (cliente_estabelecimentos)
```sql
- id (PK)
- usuario_global_id (FK)
- tenant_id (FK)
- filial_id (FK)
- primeira_visita
- ultima_visita
- total_pedidos
- total_gasto
- ativo (boolean)
```

---

## 🔧 Configuração

### 1. **Rotas Adicionadas**
- `clientes` → `mvc/ajax/clientes.php`
- Página: `mvc/views/clientes.php`

### 2. **Dependências**
- Sistema de rotas existente
- Sistema de sessão
- Sistema de banco de dados
- Bootstrap 5
- SweetAlert2
- Font Awesome

### 3. **Permissões**
- Usuários autenticados podem acessar
- Controle por tenant/filial
- Isolamento de dados por estabelecimento

---

## 📈 Benefícios

### Para o Estabelecimento:
- **Histórico completo** de cada cliente
- **Dados de contato** sempre atualizados
- **Análise de comportamento** de compra
- **Fidelização** através de dados
- **Marketing direcionado** por preferências

### Para o Cliente:
- **Experiência personalizada**
- **Dados salvos** automaticamente
- **Histórico de pedidos** acessível
- **Preferências** lembradas
- **Endereços** salvos para delivery

### Para o Sistema:
- **Escalabilidade** com multi-tenant
- **Performance** com índices otimizados
- **Auditoria** completa de interações
- **Integração** nativa com pedidos
- **Flexibilidade** para novos campos

---

## 🎯 Próximos Passos

1. **Executar migração** do banco de dados
2. **Testar funcionalidades** básicas
3. **Configurar notificações** por email/SMS
4. **Implementar relatórios** avançados
5. **Adicionar integração** com WhatsApp
6. **Criar sistema de** fidelidade
7. **Implementar cupons** personalizados

---

## 📝 Notas Técnicas

- **Compatibilidade:** PHP 8.2+, PostgreSQL 14+
- **Arquitetura:** MVC com multi-tenant
- **Segurança:** Isolamento por tenant
- **Performance:** Índices otimizados
- **Auditoria:** Log completo de interações
- **Escalabilidade:** Suporte a múltiplos estabelecimentos

---

## 🚨 Importante

1. **Execute a migração** antes de usar o sistema
2. **Configure as variáveis** de ambiente
3. **Teste em ambiente** de desenvolvimento primeiro
4. **Faça backup** antes de aplicar em produção
5. **Monitore performance** com muitos clientes

---

**Sistema implementado com sucesso! 🎉**

















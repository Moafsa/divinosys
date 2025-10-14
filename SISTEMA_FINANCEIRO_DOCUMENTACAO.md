# 📊 Sistema Financeiro Completo - Documentação

## 📋 Visão Geral

Sistema financeiro completo integrado ao Divino Lanches, com gestão de receitas, despesas, relatórios avançados e análise de pedidos quitados.

---

## ✅ Funcionalidades Implementadas

### 1. **Gestão de Lançamentos Financeiros**

#### Tipos de Lançamentos:
- ✅ **Receitas** - Entradas de dinheiro
- ✅ **Despesas** - Saídas de dinheiro
- ✅ **Transferências** - Movimentação entre contas

#### Recursos:
- 📝 Descrição detalhada
- 🏷️ Categorização por tipo
- 💳 Múltiplas formas de pagamento
- 📅 Data de vencimento e pagamento
- 🔄 Recorrência (diária, semanal, mensal, anual)
- 📎 Upload de anexos (imagens, PDFs, documentos)
- 💰 Controle de contas financeiras
- 👤 Rastreamento de usuário responsável

---

### 2. **Histórico de Pedidos Quitados**

#### Visualização em Sanfona:
- 📋 Lista completa de pedidos quitados
- 💵 Valor total e valor pago
- 🔢 Quantidade de pagamentos
- 💳 Formas de pagamento utilizadas
- 📅 Data e hora do pedido
- 🍽️ Mesa ou delivery

#### Ações Disponíveis:
- 👁️ Ver detalhes completos
- 🖨️ Imprimir pedido
- 📥 Exportar dados

---

### 3. **Categorias Financeiras**

#### Categorias Padrão de Receitas:
- 🍽️ Vendas Mesa
- 🏍️ Vendas Delivery
- 💳 Vendas Fiadas

#### Categorias Padrão de Despesas:
- 🔧 Despesas Operacionais
- 📢 Despesas de Marketing
- 👥 Salários
- 🏢 Aluguel
- ⚡ Energia Elétrica
- 💧 Água
- 📡 Internet

#### Recursos:
- 🎨 Cores personalizadas
- 🎯 Ícones customizados
- 📁 Categorias hierárquicas (pai/filho)
- ✅ Ativação/desativação

---

### 4. **Contas Financeiras**

#### Tipos de Contas:
- 💵 Carteira (Caixa)
- 🏦 Conta Corrente
- 💰 Poupança
- 📱 Outros (PIX, cartões, etc.)

#### Recursos:
- 💲 Saldo inicial e atual
- 🏦 Dados bancários (banco, agência, conta)
- 💳 Limite de crédito
- 🎨 Cores e ícones personalizados
- 🔄 Atualização automática de saldo via triggers

---

### 5. **Relatórios e Análises**

#### Tipos de Relatórios:
- 📊 Fluxo de Caixa
- 📈 Receitas por Categoria
- 📉 Despesas por Categoria
- 💹 Lucro/Prejuízo
- 🛒 Vendas por Período

#### Visualizações:
- 📊 Gráficos de linha (vendas diárias)
- 🥧 Gráficos de pizza (distribuição)
- 📊 Gráficos de barras (comparativo)
- 📋 Tabelas detalhadas
- 📈 Métricas em tempo real

#### Exportação:
- 📄 PDF
- 📊 Excel
- 📝 CSV

---

### 6. **Filtros Avançados**

#### Filtros Disponíveis:
- 📅 Período (data início e fim)
- 🏷️ Tipo (receita/despesa)
- 📁 Categoria
- 💳 Conta financeira
- ✅ Status (pendente/pago/cancelado)

#### Períodos Rápidos:
- 📅 Hoje
- 📅 Ontem
- 📅 Esta Semana
- 📅 Este Mês
- 📅 Este Trimestre
- 📅 Este Ano

---

## 🗄️ Estrutura do Banco de Dados

### Tabelas Criadas:

#### 1. **categorias_financeiras**
```sql
- id (SERIAL PRIMARY KEY)
- nome (VARCHAR 100)
- tipo (VARCHAR 20) - receita/despesa/investimento
- descricao (TEXT)
- cor (VARCHAR 7) - código hexadecimal
- icone (VARCHAR 50) - classe Font Awesome
- ativo (BOOLEAN)
- pai_id (INTEGER) - categoria pai
- tenant_id (INTEGER)
- filial_id (INTEGER)
- created_at, updated_at (TIMESTAMP)
```

#### 2. **contas_financeiras**
```sql
- id (SERIAL PRIMARY KEY)
- nome (VARCHAR 100)
- tipo (VARCHAR 20) - caixa/banco/cartao/pix/outros
- saldo_inicial (DECIMAL 10,2)
- saldo_atual (DECIMAL 10,2)
- banco, agencia, conta (VARCHAR)
- limite (DECIMAL 10,2)
- ativo (BOOLEAN)
- cor, icone (VARCHAR)
- tenant_id, filial_id (INTEGER)
- created_at, updated_at (TIMESTAMP)
```

#### 3. **lancamentos_financeiros**
```sql
- id (SERIAL PRIMARY KEY)
- tipo (VARCHAR 20) - receita/despesa/transferencia
- categoria_id (INTEGER FK)
- conta_id (INTEGER FK)
- conta_destino_id (INTEGER FK) - para transferências
- pedido_id (INTEGER FK) - vinculação com pedidos
- valor (DECIMAL 10,2)
- data_vencimento (DATE)
- data_pagamento (TIMESTAMP)
- descricao (TEXT)
- observacoes (TEXT)
- forma_pagamento (VARCHAR 50)
- status (VARCHAR 20) - pendente/pago/vencido/cancelado
- recorrência (VARCHAR 20) - nenhuma/diaria/semanal/mensal/anual
- data_fim_recorrência (DATE)
- usuario_id, tenant_id, filial_id (INTEGER)
- created_at, updated_at (TIMESTAMP)
```

#### 4. **anexos_financeiros**
```sql
- id (SERIAL PRIMARY KEY)
- lancamento_id (INTEGER FK)
- nome_arquivo (VARCHAR 255)
- caminho_arquivo (VARCHAR 500)
- tipo_arquivo (VARCHAR 50)
- tamanho_arquivo (INTEGER)
- tenant_id, filial_id (INTEGER)
- created_at (TIMESTAMP)
```

#### 5. **historico_pedidos_financeiros**
```sql
- id (SERIAL PRIMARY KEY)
- pedido_id (INTEGER FK)
- acao (VARCHAR 50) - criado/pago_parcial/pago_total/cancelado/reembolsado
- valor_anterior, valor_novo, diferenca (DECIMAL 10,2)
- forma_pagamento (VARCHAR 50)
- observacoes (TEXT)
- usuario_id, tenant_id, filial_id (INTEGER)
- created_at (TIMESTAMP)
```

#### 6. **relatorios_financeiros**
```sql
- id (SERIAL PRIMARY KEY)
- nome (VARCHAR 100)
- tipo (VARCHAR 50) - vendas/despesas/fluxo_caixa/lucro_prejuizo
- periodo_inicio, periodo_fim (DATE)
- filtros (JSONB)
- dados (JSONB)
- status (VARCHAR 20) - gerando/gerado/erro
- usuario_id, tenant_id, filial_id (INTEGER)
- created_at (TIMESTAMP)
```

#### 7. **metas_financeiras**
```sql
- id (SERIAL PRIMARY KEY)
- nome (VARCHAR 100)
- tipo (VARCHAR 20) - receita/despesa/lucro
- valor_meta, valor_atual (DECIMAL 10,2)
- periodo_inicio, periodo_fim (DATE)
- status (VARCHAR 20) - ativa/concluida/cancelada
- tenant_id, filial_id (INTEGER)
- created_at, updated_at (TIMESTAMP)
```

---

## 🔧 Triggers e Automações

### Trigger: atualizar_saldo_conta
**Função:** Atualiza automaticamente o saldo das contas quando um lançamento é criado, atualizado ou excluído.

**Comportamento:**
- **INSERT:** Adiciona valor para receitas, subtrai para despesas
- **UPDATE:** Reverte o valor anterior e aplica o novo
- **DELETE:** Reverte o lançamento excluído

---

## 📁 Arquivos Criados

### Backend:
- `database/migrations/create_financial_system.sql` - Migração completa do banco
- `mvc/views/financeiro.php` - Página principal do sistema financeiro
- `mvc/views/relatorios.php` - Página de relatórios e análises
- `mvc/ajax/financeiro.php` - API REST para operações financeiras
- `fix_financial_tables.php` - Script de correção de tabelas
- `test_financial_system.php` - Script de testes automatizados

### Frontend:
- `assets/js/financeiro.js` - JavaScript para interações e modais

---

## 🎨 Interface do Usuário

### Página Financeiro:

#### Cards de Resumo:
- 💰 Total Receitas (verde)
- 💸 Total Despesas (vermelho)
- 💵 Saldo Líquido (roxo)
- 📊 Total Lançamentos (azul)

#### Tabs:
1. **Lançamentos** - Lista de todos os lançamentos com filtros
2. **Pedidos Quitados** - Histórico em sanfona
3. **Relatórios** - Cards de relatórios disponíveis

#### Filtros:
- Data início/fim
- Tipo (receita/despesa)
- Categoria
- Conta
- Status

### Página Relatórios:

#### Métricas Principais:
- 🛒 Total Vendas
- 📋 Total Pedidos
- 💰 Ticket Médio
- 🏍️ % Delivery

#### Tabs:
1. **Gráficos** - Visualizações interativas
2. **Tabelas** - Dados tabulares
3. **Histórico** - Relatórios gerados

#### Gráficos:
- 📈 Vendas Diárias (linha)
- 🥧 Distribuição Mesa/Delivery (pizza)
- 📊 Fluxo Financeiro (barras)

---

## 🔐 Segurança

### Implementações:
- ✅ Multi-tenancy (tenant_id, filial_id)
- ✅ Validação de permissões
- ✅ Prepared statements (SQL Injection)
- ✅ Sanitização de inputs
- ✅ Validação de tipos de arquivo
- ✅ Limite de tamanho de upload (5MB)
- ✅ CSRF protection

---

## 📊 Métricas e KPIs

### Resumo Financeiro:
- Total de receitas no período
- Total de despesas no período
- Saldo líquido (receitas - despesas)
- Total de lançamentos

### Análise de Vendas:
- Total de vendas
- Quantidade de pedidos
- Ticket médio
- Percentual delivery vs mesa

### Análise de Categorias:
- Receitas por categoria
- Despesas por categoria
- Top categorias

---

## 🚀 Como Usar

### 1. Acessar o Sistema Financeiro:
```
http://localhost:8080/index.php?view=financeiro
```

### 2. Criar um Lançamento:
1. Clicar em "Novo Lançamento"
2. Preencher o formulário
3. Adicionar anexos (opcional)
4. Salvar

### 3. Gerar um Relatório:
1. Acessar a página de Relatórios
2. Escolher o tipo de relatório
3. Definir período
4. Gerar

### 4. Consultar Pedidos Quitados:
1. Ir para a tab "Pedidos Quitados"
2. Clicar em um pedido para expandir
3. Ver detalhes e ações disponíveis

---

## 🧪 Testes

### Script de Teste: `test_financial_system.php`

**Testes Executados:**
1. ✅ Conexão com banco de dados
2. ✅ Verificação de tabelas
3. ✅ Categorias financeiras
4. ✅ Contas financeiras
5. ✅ Criação de lançamento
6. ✅ Atualização de saldo
7. ✅ Consulta de lançamentos
8. ✅ Resumo financeiro
9. ✅ Pedidos quitados
10. ✅ Limpeza de dados de teste

**Resultado:** ✅ Todos os testes passaram com sucesso!

---

## 📈 Próximos Passos (Sugestões)

### Melhorias Futuras:
1. 📊 Dashboard com gráficos em tempo real
2. 📧 Alertas por email para vencimentos
3. 📱 Notificações push
4. 🔄 Sincronização com bancos (Open Banking)
5. 🤖 IA para previsão de fluxo de caixa
6. 📊 Análise preditiva de vendas
7. 💳 Integração com gateways de pagamento
8. 📄 Geração automática de notas fiscais
9. 📊 Comparativo de períodos
10. 🎯 Metas e objetivos financeiros

---

## 🛠️ Tecnologias Utilizadas

### Backend:
- PHP 8.1+
- PostgreSQL 15
- PDO (PHP Data Objects)

### Frontend:
- Bootstrap 5.3
- Font Awesome 6.4
- Chart.js
- SweetAlert2
- Select2
- jQuery

### Infraestrutura:
- Docker
- Docker Compose
- Nginx/Apache

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Verificar logs em `logs/`
2. Executar script de teste
3. Verificar permissões de banco
4. Consultar documentação

---

## 📝 Changelog

### Versão 1.0.0 (2025-10-14)
- ✅ Sistema financeiro completo implementado
- ✅ 7 tabelas criadas com relacionamentos
- ✅ Interface moderna e responsiva
- ✅ Relatórios com gráficos interativos
- ✅ Filtros avançados
- ✅ Exportação de dados
- ✅ Testes automatizados
- ✅ Documentação completa

---

**Sistema desenvolvido com ❤️ para Divino Lanches**

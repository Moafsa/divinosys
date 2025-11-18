# 📄 Sistema de Gerenciamento de Faturas

## ✅ Implementado

### 1. Página de Faturas (`gerenciar_faturas.php`)

**Localização:** `mvc/views/gerenciar_faturas.php`

**Funcionalidades:**

- ✅ **Visualização do Plano Atual**
  - Mostra nome do plano, valor, periodicidade
  - Status da assinatura (ativa, trial, suspensa, cancelada)
  - Próxima data de cobrança
  - Botão para mudar plano

- ✅ **Planos Disponíveis**
  - Lista todos os planos ativos
  - Destaca o plano atual
  - Botões de Upgrade/Downgrade
  - Mostra recursos de cada plano (mesas, usuários, produtos, pedidos)

- ✅ **Histórico de Faturas**
  - Tabela com todas as faturas
  - Colunas: ID, Data, Vencimento, Valor, Periodicidade, Status
  - Botão "Pagar" para faturas pendentes (abre URL do Asaas)
  - Sincronização automática a cada 30 segundos
  - Botão manual "Sincronizar Faturas"

### 2. Backend de Mudança de Plano

**Localização:** `mvc/ajax/tenant_subscription.php`

**Funcionalidades:**

- ✅ **Mudar Plano** (`mudarPlano`)
  - Permite upgrade/downgrade de plano
  - Permite mudar periodicidade (mensal, semestral, anual)
  - Calcula descontos automaticamente:
    - Semestral: -10% (6 meses)
    - Anual: -20% (12 meses)
  - Atualiza no banco local
  - Atualiza no Asaas (se assinatura recorrente)

- ✅ **Sincronizar Faturas** (`syncAsaasInvoices`)
  - Busca todas as faturas da assinatura no Asaas
  - Cria novas faturas no banco local
  - Atualiza faturas existentes
  - Retorna contagem de novas/atualizadas

### 3. Webhook do Asaas

**Localização:** `webhook/asaas.php`

**Funcionalidades:**

- ✅ **Processamento Automático de Eventos**
  - Recebe notificações do Asaas em tempo real
  - Eventos suportados:
    - `PAYMENT_CREATED` - Nova fatura criada
    - `PAYMENT_CONFIRMED` - Pagamento confirmado
    - `PAYMENT_RECEIVED` - Pagamento recebido
    - `PAYMENT_OVERDUE` - Fatura vencida
  - Cria/atualiza faturas automaticamente
  - Atualiza status da assinatura
  - Calcula próxima data de cobrança

- ✅ **Mapeamento de Status**
  - `PENDING` → pendente
  - `CONFIRMED` → pago
  - `RECEIVED` → pago
  - `OVERDUE` → pendente (assinatura suspensa)
  - `REFUNDED` → cancelado

### 4. Permissões e Navegação

**Localização:** `system/Auth.php` e `system/Middleware/AccessControl.php`

- ✅ Adicionado `faturas` e `gerenciar_faturas` às permissões do admin
- ✅ Adicionado item de menu "Faturas" na sidebar
- ✅ Ícone: `fas fa-file-invoice-dollar`

## 🚀 Como Usar

### Para o Estabelecimento (Tenant)

1. **Acessar:** Dashboard → Menu lateral → **Faturas**

2. **Ver Plano Atual:**
   - Informações completas sobre a assinatura
   - Próxima data de cobrança
   - Status da assinatura

3. **Mudar Plano:**
   - Clicar no botão "Mudar Plano" ou em um plano diferente
   - Escolher periodicidade (mensal/semestral/anual)
   - Confirmar mudança
   - ✅ Atualização imediata no banco
   - ✅ Atualização no Asaas (se assinatura recorrente)

4. **Ver Histórico de Faturas:**
   - Todas as faturas listadas
   - Status atualizado em tempo real
   - Botão "Pagar" para faturas pendentes

5. **Sincronizar Faturas:**
   - Clicar no botão "Sincronizar Faturas"
   - Busca novas faturas do Asaas
   - Atualiza status de faturas existentes

### Para o Asaas (Webhook)

1. **Configurar Webhook no Asaas:**
   - URL: `https://seu-dominio.com/webhook/asaas.php`
   - Eventos: `PAYMENT_*` (todos os eventos de pagamento)

2. **Processamento Automático:**
   - Asaas envia notificação → Webhook processa
   - Nova fatura criada → Aparece na lista
   - Pagamento confirmado → Status atualizado
   - Vencimento → Assinatura suspensa

## 📊 Estrutura de Dados

### Tabela `assinaturas`
```sql
- id
- tenant_id
- plano_id
- valor
- periodicidade (mensal, semestral, anual)
- status (ativa, trial, suspensa, cancelada)
- data_proxima_cobranca
- asaas_subscription_id
```

### Tabela `pagamentos`
```sql
- id
- tenant_id
- filial_id
- assinatura_id
- valor
- valor_pago
- status (pendente, pago, cancelado)
- data_vencimento
- data_pagamento
- gateway_payment_id (ID do Asaas)
- gateway_response (JSON completo da fatura)
```

## 🔧 Configuração

### 1. Variáveis de Ambiente (.env)
```env
ASAAS_API_KEY=aact_hmlg_...
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_URL=https://seu-dominio.com/webhook/asaas.php
```

### 2. Configurar Webhook no Asaas
1. Acessar: Asaas Dashboard → Configurações → Webhooks
2. Adicionar nova URL: `https://seu-dominio.com/webhook/asaas.php`
3. Selecionar eventos: `PAYMENT_*`
4. Salvar

### 3. Testar
```bash
# Fazer login como admin do estabelecimento
# Acessar: Faturas
# Clicar em "Sincronizar Faturas"
# Verificar se as faturas aparecem
```

## 🎯 Diferenciais

1. ✅ **Upgrade/Downgrade em tempo real**
   - Mudanças refletidas imediatamente no Asaas
   - Sem necessidade de intervenção manual

2. ✅ **Sincronização Bidirecional**
   - Asaas → Sistema (webhook automático)
   - Sistema → Asaas (mudanças de plano)

3. ✅ **Histórico Completo**
   - Todas as faturas armazenadas localmente
   - Links diretos para pagamento
   - Status em tempo real

4. ✅ **Descontos Automáticos**
   - Semestral: -10%
   - Anual: -20%
   - Calculados automaticamente

5. ✅ **Interface Moderna**
   - Design responsivo
   - Cards visuais para planos
   - Badges coloridos para status
   - SweetAlert2 para confirmações

## 🔐 Segurança

- ✅ Verificação de autenticação
- ✅ Validação de tenant_id
- ✅ Logs detalhados de todas as operações
- ✅ Tratamento de erros robusto
- ✅ Sanitização de dados do Asaas

## 📝 Logs

Todos os eventos são logados:

```
error_log("tenant_subscription.php - Plano alterado: Tenant=$tenantId, Plano=$planoId")
error_log("ASAAS WEBHOOK - Evento: PAYMENT_CONFIRMED, Payment ID: pay_xxx")
error_log("syncAsaasInvoices - Sincronizado: 3 nova(s) fatura(s), 2 atualizada(s)")
```

## 🚨 Tratamento de Erros

1. **Assinatura não encontrada:** Mensagem clara ao usuário
2. **Erro no Asaas:** Atualização apenas local
3. **Webhook falha:** Sincronização manual disponível
4. **Plano inválido:** Validação antes de aplicar

## ✅ Consolidado

Todos os arquivos foram criados/atualizados:
- ✅ `mvc/views/gerenciar_faturas.php`
- ✅ `mvc/ajax/tenant_subscription.php`
- ✅ `webhook/asaas.php`
- ✅ `system/Auth.php` (permissões)
- ✅ `system/Middleware/AccessControl.php` (menu)
- ✅ `index.php` (rotas)

**Pronto para uso em produção! 🎉**


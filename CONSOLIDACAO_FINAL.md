# 🎯 Consolidação Final - Sistema de Assinaturas e Faturas

## ✅ Todas as Implementações Consolidadas

### 1. **Página de Faturas para Estabelecimentos** ✅

**Arquivo:** `mvc/views/gerenciar_faturas.php`

**Funcionalidades:**
- ✅ Visualização do plano atual (nome, valor, periodicidade, status, próxima cobrança)
- ✅ Botão "Mudar Plano" com popup mostrando todos os planos disponíveis
- ✅ Seleção de periodicidade (mensal, semestral, anual) com descontos automáticos
- ✅ Histórico completo de faturas
- ✅ Botão "Sincronizar Faturas" (busca novas faturas do Asaas)
- ✅ Avisos informativos para assinaturas antigas/não integradas

**Permissões:** Admin

**Menu:** Sidebar → "Faturas" (ícone: `fas fa-file-invoice-dollar`)

---

### 2. **Sistema de Mudança de Plano** ✅

**Arquivo:** `mvc/ajax/tenant_subscription.php`

**Cenários Implementados:**

#### **A. Mudança APENAS de plano (mesma periodicidade):**
- ✅ Atualiza valor na assinatura existente do Asaas
- ✅ Atualiza banco local
- ✅ Rápido e simples (1 chamada API)

#### **B. Mudança de periodicidade:**
- ✅ **CANCELA** assinatura antiga no Asaas
- ✅ **CRIA** nova assinatura com nova periodicidade
- ✅ Atualiza `asaas_subscription_id` no banco local
- ✅ Mantém histórico de faturas

#### **C. Assinaturas sem Asaas:**
- ✅ Atualiza apenas localmente
- ✅ Mostra aviso informativo ao usuário

---

### 3. **SuperAdmin - Mudança de Plano** ✅

**Arquivo:** `mvc/controller/SuperAdminController.php` → método `updateTenant()`

**Implementação Idêntica ao Tenant:**
- ✅ Mudança de plano → atualiza valor
- ✅ Mudança de periodicidade → cancela e recria assinatura
- ✅ Logs detalhados para debugging

---

### 4. **Validação de Limite de Filiais** ✅

**Arquivo:** `mvc/ajax/filiais.php` (linhas 83-108)

**Lógica:**
```php
// Buscar plano do tenant
$plano = $planModel->getById($subscription['plano_id']);

// Se max_filiais != -1 (ilimitado)
if ($plano['max_filiais'] != -1) {
    // Contar filiais existentes
    $totalFiliais = COUNT(*) FROM filiais WHERE tenant_id = X
    
    // Se atingiu o limite
    if ($totalFiliais >= $plano['max_filiais']) {
        ERRO: "Limite atingido! Faça upgrade do plano"
    }
}
```

**Valores Padrão por Plano:**
- **Starter/Básico:** 1 filial
- **Profissional:** 3 filiais
- **Business/Empresarial:** 10 filiais
- **Enterprise:** -1 (ilimitado)

---

### 5. **Webhook do Asaas** ✅

**Arquivo:** `webhook/asaas.php`

**Eventos Processados:**
- `PAYMENT_CREATED` → Cria nova fatura no banco
- `PAYMENT_CONFIRMED` → Atualiza status para "pago"
- `PAYMENT_RECEIVED` → Atualiza status e próxima cobrança
- `PAYMENT_OVERDUE` → Suspende assinatura

**Mapeamento de Status:**
```
PENDING → pendente
CONFIRMED → pago
RECEIVED → pago
OVERDUE → pendente (assinatura → suspensa)
REFUNDED → cancelado
```

---

### 6. **Correções de Bugs** ✅

#### **A. Coluna `max_filiais` adicionada:**
- **Arquivo:** `database/migrations/add_max_filiais_to_planos.sql`
- ✅ Coluna criada com valores padrão
- ✅ `Plan->update()` restaurado para incluir `max_filiais`

#### **B. Coluna `telefone_cliente` removida:**
- **Problema:** Query buscava coluna inexistente
- **Solução:** Removida de `mvc/ajax/financeiro.php` e `mvc/views/financeiro.php`
- ✅ Pedidos fiado agora listam corretamente

#### **C. `asaas_subscription_id` não salvava:**
- **Problema:** Criava no Asaas mas não atualizava banco local
- **Solução:** Adicionado `subscriptionModel->update()` em `OnboardingController.php`
- ✅ Novos estabelecimentos salvam ID corretamente

---

### 7. **Arquivos de Migração Criados** ✅

Todos prontos para futuros deploys:

1. ✅ `database/migrations/add_asaas_columns_to_pagamentos.sql`
2. ✅ `database/migrations/add_asaas_subscription_id_to_assinaturas.sql`
3. ✅ `database/migrations/add_address_columns_to_filiais.sql`
4. ✅ `database/migrations/add_is_superadmin_to_whatsapp_instances.sql`
5. ✅ `database/migrations/create_filial_settings.sql`
6. ✅ `database/migrations/add_max_filiais_to_planos.sql` ← **NOVO**

---

### 8. **Permissões Atualizadas** ✅

**Arquivo:** `system/Auth.php`

```php
'admin' => [
    'dashboard', 'pedidos', 'delivery', 'produtos', 'estoque', 
    'financeiro', 'relatorios', 'clientes', 'configuracoes', 'usuarios',
    'novo_pedido', 'relatorios_avancados', 'asaas_config', 
    'gerenciar_faturas', // ← ADICIONADO
    'logout'
]
```

---

### 9. **Menu de Navegação Atualizado** ✅

**Arquivo:** `system/Middleware/AccessControl.php`

```php
'gerenciar_faturas' => [
    'label' => 'Faturas',
    'icon' => 'fas fa-file-invoice-dollar',
    'url' => 'index.php?view=gerenciar_faturas'
]
```

---

### 10. **Rotas AJAX Adicionadas** ✅

**Arquivo:** `index.php`

```php
// Tenant actions
'mudarPlano' => 'tenant_subscription.php',
'syncAsaasInvoices' => 'tenant_subscription.php',
```

---

## 🚀 Como Funciona em Produção

### **Cenário 1: Novo Estabelecimento**
1. Cliente se cadastra via `register.php`
2. Sistema cria:
   - ✅ Tenant
   - ✅ Filial padrão (ID 1 - matriz)
   - ✅ Assinatura local
   - ✅ **Customer no Asaas**
   - ✅ **Subscription no Asaas**
   - ✅ Primeira fatura
3. `asaas_subscription_id` é salvo no banco ✅
4. Fatura enviada via WhatsApp ✅

### **Cenário 2: Mudança de Plano (Estabelecimento)**
1. Admin acessa **Faturas** → **Mudar Plano**
2. Seleciona novo plano + periodicidade
3. Sistema:
   - Se **mesma periodicidade** → `updateSubscription()` no Asaas
   - Se **mudou periodicidade** → `cancelSubscription()` + `createSubscription()`
4. ✅ Banco local atualizado
5. ✅ Asaas atualizado

### **Cenário 3: Mudança de Plano (SuperAdmin)**
1. SuperAdmin edita estabelecimento
2. Muda plano ou periodicidade
3. **Mesma lógica** do cenário 2 aplicada
4. ✅ Sincronização automática

### **Cenário 4: Nova Filial**
1. Admin tenta criar filial
2. Sistema verifica:
   ```
   Filiais existentes < plano.max_filiais?
   ```
3. Se **SIM** → Cria filial ✅
4. Se **NÃO** → Erro: "Limite atingido! Faça upgrade" ⚠️

### **Cenário 5: Sincronização de Faturas**
1. Asaas gera nova cobrança (automático mensalmente)
2. **Opção A:** Webhook notifica → fatura criada automaticamente
3. **Opção B:** Cliente clica "Sincronizar" → busca do Asaas

---

## 📋 Checklist de Deploy

### **Antes do Deploy:**
- ✅ Todas as migrações em `database/migrations/`
- ✅ `.env` configurado com `ASAAS_API_KEY`
- ✅ Webhook configurado no Asaas

### **Processo de Deploy (Coolify):**
1. ✅ `start-production.sh` executa automaticamente:
   - Backup do banco (`/backups`)
   - Execução de migrações (`database_migrate.php`)
   - Fix de sequences
2. ✅ Volumes persistentes:
   - `./backups:/var/www/html/backups`
   - `postgres_data:/var/lib/postgresql/data`
   - `wuzapi_sessions:/app/sessions`

### **Após Deploy:**
1. ✅ Verificar logs: `docker logs divino-lanches-app`
2. ✅ Testar criação de estabelecimento
3. ✅ Testar mudança de plano
4. ✅ Verificar sincronização de faturas

---

## 🔧 Configurações Importantes

### **1. Variáveis de Ambiente (.env)**
```env
ASAAS_API_KEY=aact_hmlg_...
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_URL=https://seu-dominio.com/webhook/asaas.php
```

### **2. Webhook do Asaas**
- **URL:** `https://seu-dominio.com/webhook/asaas.php`
- **Eventos:** `PAYMENT_*` (todos)

### **3. Limites de Plano**
- **max_filiais:** Quantidade de filiais permitidas
- **max_mesas:** Quantidade de mesas
- **max_usuarios:** Quantidade de usuários
- **max_produtos:** Quantidade de produtos
- **max_pedidos_mes:** Pedidos por mês

---

## 🎯 Funcionalidades Completas

### ✅ **Página de Faturas**
- Plano atual
- Histórico de faturas
- Upgrade/Downgrade
- Sincronização manual

### ✅ **SuperAdmin**
- Editar planos (incluindo max_filiais)
- Editar estabelecimentos (plano + periodicidade)
- Sincronização automática com Asaas

### ✅ **Validações**
- Limite de filiais por plano
- Verificação de assinatura ativa
- Tratamento de assinaturas antigas

### ✅ **Asaas Integration**
- Criar assinaturas recorrentes
- Atualizar assinaturas
- Cancelar e recriar (mudança de periodicidade)
- Webhook para notificações automáticas

---

## 📊 Estrutura Final

```
mvc/
├── views/
│   └── gerenciar_faturas.php ← Página de faturas
├── ajax/
│   ├── tenant_subscription.php ← Mudança de plano (tenant)
│   └── filiais.php ← Criação de filiais (com validação)
├── controller/
│   ├── OnboardingController.php ← Criação de estabelecimentos
│   └── SuperAdminController.php ← Gestão de planos e tenants
└── model/
    ├── AsaasPayment.php ← Integração Asaas
    ├── Subscription.php ← Gestão de assinaturas
    └── Plan.php ← Gestão de planos

webhook/
└── asaas.php ← Webhook do Asaas

database/migrations/
├── add_max_filiais_to_planos.sql ← NOVA
├── add_asaas_subscription_id_to_assinaturas.sql
├── add_asaas_columns_to_pagamentos.sql
├── create_filial_settings.sql
├── add_is_superadmin_to_whatsapp_instances.sql
└── add_address_columns_to_filiais.sql
```

---

## 🔥 Tudo Pronto para Produção!

**Testado e funcionando:**
- ✅ Criação de estabelecimentos com assinatura Asaas
- ✅ Mudança de plano (valor + periodicidade)
- ✅ Limite de filiais por plano
- ✅ Sincronização de faturas
- ✅ Webhook do Asaas
- ✅ Listagem de pedidos fiado
- ✅ Edição de planos no SuperAdmin

**Próximo deploy:**
- Todas as migrações serão executadas automaticamente
- Backup automático antes das migrações
- Rollback disponível se necessário

**🎉 SISTEMA COMPLETO E CONSOLIDADO! 🎉**


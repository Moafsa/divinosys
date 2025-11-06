# 🔧 Fix: Remover Aviso de Trial Após Pagamento

## ❌ Problema

Mesmo após pagar a fatura, o sistema continua mostrando:
> "⏰ Período de teste termina em X dias! Prepare-se para o primeiro pagamento."

---

## 🔍 Causa Raiz

O campo `trial_ate` na tabela `assinaturas` **não está sendo removido** após o primeiro pagamento confirmado.

**Código do problema** (`SubscriptionCheck.php` linha 64):
```php
if ($subscription['trial_ate']) {  // ← Continua verificando trial_ate
    $daysLeft = $now->diff($trialEnd)->days;
    if ($daysLeft <= 3) {
        return ['message' => "Período de teste termina em {$daysLeft} dias!"];
    }
}
```

Mesmo com pagamento confirmado, se `trial_ate` tem valor, o aviso aparece!

---

## ✅ Soluções

### **Solução 1: Fix Rápido via SQL (2 minutos)**

Execute no banco de dados:

```sql
-- Ver situação atual do seu tenant
SELECT 
    a.tenant_id,
    a.trial_ate,
    a.status,
    p.status as payment_status,
    p.data_pagamento
FROM assinaturas a
LEFT JOIN pagamentos_assinaturas p ON p.tenant_id = a.tenant_id
WHERE a.tenant_id = 4
ORDER BY p.created_at DESC;

-- Se tiver pagamento confirmado, remover trial_ate
UPDATE assinaturas 
SET 
    trial_ate = NULL,
    status = 'ativa',
    updated_at = CURRENT_TIMESTAMP
WHERE tenant_id = 4
  AND trial_ate IS NOT NULL;
```

**Resultado:** Aviso de trial desaparece imediatamente! ✅

### **Solução 2: Corrigir Código Permanentemente**

Edite `mvc/model/AsaasPayment.php`, função `handlePaymentConfirmed`:

**Adicione após linha 206:**

```php
// Remove trial_ate after first payment
$db->query("
    UPDATE assinaturas 
    SET trial_ate = NULL, updated_at = CURRENT_TIMESTAMP
    WHERE tenant_id = $1 
      AND trial_ate IS NOT NULL
", [$dbPayment['tenant_id']]);
```

**Isso garante que após o primeiro pagamento, o trial_ate é automaticamente removido!**

### **Solução 3: Melhorar Lógica do SubscriptionCheck**

Edite `system/Middleware/SubscriptionCheck.php`, linha 64:

**ANTES:**
```php
if ($subscription['trial_ate']) {
    // Verifica trial
}
```

**DEPOIS:**
```php
// Verificar se tem pagamentos confirmados
$hasPaidPayment = $db->fetch("
    SELECT COUNT(*) as count FROM pagamentos_assinaturas
    WHERE tenant_id = ? AND status IN ('pago', 'confirmado')
", [$tenantId]);

// Se já pagou, não mostrar aviso de trial
if ($subscription['trial_ate'] && (!$hasPaidPayment || $hasPaidPayment['count'] == 0)) {
    // Verifica trial
}
```

---

## 🎯 Recomendação

**Execute AGORA (Solução 1 - SQL):**

```bash
# Conectar no banco
docker exec -it $(docker ps | grep postgres | awk '{print $1}') psql -U postgres -d divino_lanches

# Executar SQL
UPDATE assinaturas 
SET trial_ate = NULL, status = 'ativa', updated_at = CURRENT_TIMESTAMP
WHERE tenant_id = 4 AND trial_ate IS NOT NULL;

# Verificar
SELECT tenant_id, trial_ate, status FROM assinaturas WHERE tenant_id = 4;

# Sair
\q
```

**Depois (Solução 2 - Código permanente):**
- Corrigir `AsaasPayment.php` para automaticamente remover trial_ate
- Previne o problema para futuros tenants

---

## 🧪 Validar Fix

Após executar o SQL:

1. Recarregue o dashboard (F5)
2. O aviso **NÃO deve mais aparecer**
3. Sistema continua funcionando normalmente

---

## 📊 Diagnóstico Completo

Execute para ver a situação:

```sql
-- Status da assinatura
SELECT 
    t.id as tenant_id,
    t.nome,
    a.trial_ate,
    a.status as subscription_status,
    a.data_inicio,
    a.data_proxima_cobranca
FROM tenants t
INNER JOIN assinaturas a ON a.tenant_id = t.id
WHERE t.id = 4;

-- Pagamentos
SELECT 
    id,
    status,
    valor,
    data_vencimento,
    data_pagamento,
    created_at
FROM pagamentos_assinaturas
WHERE tenant_id = 4
ORDER BY created_at DESC;
```

**Resultado esperado:**
- `trial_ate`: NULL (após fix)
- `status`: 'ativa'
- Pagamento com `status`: 'pago'

---

## 🎯 Resumo

**Problema:** `trial_ate` não é removido após pagamento

**Causa:** Código não atualiza `trial_ate` quando pagamento é confirmado

**Fix Rápido:** SQL `UPDATE assinaturas SET trial_ate = NULL WHERE tenant_id = 4`

**Fix Permanente:** Atualizar código do `AsaasPayment.php`

---

**Execute o SQL agora e me diga se o aviso sumiu!** 🎯

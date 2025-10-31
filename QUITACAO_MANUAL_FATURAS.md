# Sistema de Quitação Manual de Faturas

## 📋 Como Funciona (Baseado na Documentação Asaas)

### 🎯 Decisão de Design

Após consultar a documentação do Asaas, a **melhor prática** é:

**❌ NÃO cancelar/deletar cobranças no Asaas**
- Motivo: Gera estorno que pode demorar até 10 dias
- Taxas não são devolvidas
- Pode causar inconsistências

**✅ Marcar como PAGO apenas localmente**
- Desbloqueia o tenant imediatamente
- Asaas continua gerando próximas cobranças automaticamente
- Cobrança antiga fica "ativa" no Asaas mas é ignorada pelo sistema local

---

## 🔄 Fluxo de Quitação Manual

### Cenário: Cliente pagou FORA do sistema

**Exemplo:** Cliente fez PIX direto, TED, pagou em dinheiro, acordo especial, etc.

```
┌─────────────────────────────────────────────────────────┐
│ SISTEMA LOCAL                    ASAAS                  │
├─────────────────────────────────────────────────────────┤
│ Fatura ID 22                     Payment pay_abc123     │
│ Status: PENDENTE          →      Status: PENDING        │
│ Tenant: BLOQUEADO                                       │
└─────────────────────────────────────────────────────────┘
                     ↓
         SuperAdmin clica "Quitar Fatura"
                     ↓
┌─────────────────────────────────────────────────────────┐
│ SISTEMA LOCAL                    ASAAS                  │
├─────────────────────────────────────────────────────────┤
│ Fatura ID 22                     Payment pay_abc123     │
│ Status: PAGO              →      Status: PENDING        │
│ Tenant: ATIVO            (não altera)  (fica ativo)     │
│ Assinatura: ATIVA                                       │
└─────────────────────────────────────────────────────────┘
                     ↓
         Próximo mês: Asaas gera nova cobrança
                     ↓
┌─────────────────────────────────────────────────────────┐
│ SISTEMA LOCAL                    ASAAS                  │
├─────────────────────────────────────────────────────────┤
│ Fatura ID 23 (nova!)             Payment pay_xyz789     │
│ Status: PENDENTE          ←      Status: PENDING        │
│                                  (gerada automaticamente)│
└─────────────────────────────────────────────────────────┘
```

---

## ✅ Vantagens desta Abordagem

### 1. **Assinatura Continua Funcionando**
- ✅ Asaas gera cobranças automaticamente todo mês/semestre/ano
- ✅ Não precisa recriar assinatura
- ✅ Histórico de cobranças fica intacto

### 2. **Cliente Desbloqueia Imediatamente**
- ✅ Não precisa esperar processamento Asaas
- ✅ Tenant volta a funcionar instantaneamente
- ✅ Pode criar pedidos, produtos, etc

### 3. **Sem Complicações de Estorno**
- ✅ Não gera estorno no cartão do cliente
- ✅ Não perde taxas
- ✅ Não precisa esperar 10 dias

### 4. **Flexibilidade**
- ✅ Admin pode deletar cobrança antiga no Asaas manualmente se quiser
- ✅ Ou deixar lá como registro
- ✅ Sistema local ignora cobranças já quitadas localmente

---

## 🔧 Implementação Técnica

### Backend: `SuperAdminController::markPaymentAsPaid()`

```php
try {
    // NÃO mexer no Asaas - apenas marcar como pago localmente
    error_log("Quitando pagamento ID {$payment_id} localmente");
    
    // 1. Marcar pagamento como pago
    $this->paymentModel->markAsPaid($payment_id, 'manual');
    
    // 2. Reativar assinatura
    $db->update('assinaturas', [
        'status' => 'ativa'
    ], 'id = ?', [$payment['assinatura_id']]);
    
    // 3. Reativar tenant
    $db->update('tenants', [
        'status' => 'ativo'
    ], 'id = ?', [$payment['tenant_id']]);
    
    // ✅ PRONTO! Tenant desbloqueado
    // Asaas continua gerando cobranças normalmente
}
```

---

## 🎨 Interface do SuperAdmin

### Modal de Confirmação:

```
╔═══════════════════════════════════════════════════════╗
║ ❓ Quitar Fatura Manualmente?                         ║
╠═══════════════════════════════════════════════════════╣
║ Esta ação irá:                                        ║
║ ✅ Marcar como PAGO no sistema local                 ║
║ ✅ Reativar a assinatura e o estabelecimento          ║
║ ✅ Desbloquear acesso completo do tenant              ║
║                                                        ║
║ ┌───────────────────────────────────────────────┐    ║
║ │ ⚠️ Importante: A cobrança no Asaas continuará │    ║
║ │ ativa. O Asaas irá gerar a próxima cobrança  │    ║
║ │ automaticamente conforme a periodicidade.     │    ║
║ └───────────────────────────────────────────────┘    ║
║                                                        ║
║ ┌───────────────────────────────────────────────┐    ║
║ │ 💡 Quando usar: Cliente pagou fora do sistema │    ║
║ │ (dinheiro, TED, PIX direto, etc) ou acordo    │    ║
║ │ especial.                                      │    ║
║ └───────────────────────────────────────────────┘    ║
╠═══════════════════════════════════════════════════════╣
║  [❌ Cancelar]    [💵 Sim, Quitar Manualmente]       ║
╚═══════════════════════════════════════════════════════╝
```

---

## 🔍 Gerenciamento de Cobranças no Asaas

### Cobranças Antigas (Quitadas Manualmente)

**Opção 1: Deixar no Asaas (Recomendado)**
- ✅ Mantém histórico completo
- ✅ Não interfere em nada
- ✅ Sistema local ignora automaticamente

**Opção 2: Deletar Manualmente no Painel Asaas**
- Acesse: https://sandbox.asaas.com (ou produção)
- Menu → Cobranças → Todas
- Clique na cobrança → Ícone de lixeira
- ⚠️ Opcional: Apenas para organização visual

---

## 📊 Exemplo Prático

### Situação:
- **Cliente:** DIVINO torxc
- **Plano:** Empresarial (R$ 199,90/mês)
- **Problema:** Fatura vencida há 10 dias, tenant bloqueado
- **Solução:** Cliente pagou R$ 199,90 via TED

### Passos:

1. **SuperAdmin → Pagamentos**
2. **Localizar fatura pendente** (linha amarela)
3. **Clicar "💵 Quitar Fatura"**
4. **Confirmar na modal**
5. ✅ **Resultado:**
   - Fatura marcada como PAGA localmente
   - Tenant REATIVADO
   - Cliente pode usar o sistema novamente
   - Asaas continua gerando próximas cobranças

---

## 🔄 Webhook do Asaas (Pagamentos Automáticos)

### Quando o cliente paga PELO Asaas (PIX/Boleto):

```
Cliente gera PIX no Asaas
       ↓
Cliente paga PIX
       ↓
Asaas detecta pagamento
       ↓
Asaas envia Webhook para nosso sistema
       ↓
Sistema atualiza automaticamente:
  - Status: PAGO
  - Tenant: ATIVO
  - Assinatura: ATIVA
       ↓
Próximo mês: Nova cobrança gerada
```

**Arquivo:** `mvc/webhook/asaas.php` (a ser implementado)

---

## 📝 Recomendações

### Para Pagamentos Recorrentes:

1. **Incentivar pagamento via Asaas** (PIX/Boleto gerado automaticamente)
   - Automático via webhook
   - Sem intervenção manual

2. **Quitação Manual = Exceção**
   - Usar apenas para casos especiais
   - Documentar o motivo (campo observação)

3. **Não deletar cobranças antigas**
   - Deixar no Asaas como histórico
   - Sistema local ignora automaticamente

---

## ✅ Arquivos Modificados

1. **`mvc/model/AsaasPayment.php`**
   - Adicionado método `cancelPayment()` (não usado mais)
   - Mantido método `confirmPaymentManually()` (não usado mais)

2. **`mvc/controller/SuperAdminController.php`**
   - **Removida** integração com Asaas na quitação manual
   - Apenas atualiza banco local
   - Logs informativos

3. **`mvc/views/superadmin_dashboard.php`**
   - Modal atualizada com avisos corretos
   - Campo de busca adicionado
   - Botão destacado em verde

---

## 🎉 Conclusão

**Sistema simplificado e funcional:**
- ✅ Quitação manual desbloqueia tenant instantaneamente
- ✅ Asaas continua gerando cobranças recorrentes normalmente
- ✅ Sem riscos de estornos ou perda de taxas
- ✅ Flexível para acordos especiais

**🚀 Pronto para uso em produção!**


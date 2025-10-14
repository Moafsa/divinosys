# Sistema de Pagamento Parcial - Guia de Implementação

## 📋 Visão Geral

Este sistema permite que os pedidos sejam pagos de forma parcial, com múltiplos pagamentos até a quitação total. A mesa permanece ocupada até que o valor total seja pago.

## 🎯 Funcionalidades

### 1. **Pagamentos Parciais**
- ✅ Permite múltiplos pagamentos para um mesmo pedido
- ✅ Cada pagamento pode usar uma forma de pagamento diferente
- ✅ Sistema calcula automaticamente o saldo devedor

### 2. **Controle de Saldo**
- ✅ Exibe valor total, valor pago e saldo devedor
- ✅ Barra de progresso visual do pagamento
- ✅ Validação para não permitir pagamento acima do saldo

### 3. **Informações do Cliente**
- ✅ Captura nome do cliente
- ✅ Captura telefone do cliente
- ✅ Campo para observações/descrição

### 4. **Gestão da Mesa**
- ✅ Mesa permanece ocupada enquanto houver saldo devedor
- ✅ Mesa é liberada automaticamente quando o pedido é quitado
- ✅ Verifica se há outros pedidos abertos na mesa antes de liberar

### 5. **Histórico de Pagamentos**
- ✅ Exibe todos os pagamentos realizados
- ✅ Mostra data, forma de pagamento, valor e cliente
- ✅ Permite visualizar o histórico completo

### 6. **Cálculo de Troco**
- ✅ Campo específico para pagamentos em dinheiro
- ✅ Calcula automaticamente o troco a devolver
- ✅ Valida se o valor informado é suficiente

## 🗄️ Estrutura do Banco de Dados

### Novas Colunas na Tabela `pedido`

```sql
- valor_pago DECIMAL(10,2)        -- Valor total já pago
- saldo_devedor DECIMAL(10,2)     -- Valor ainda a pagar
- status_pagamento VARCHAR(20)     -- pendente | parcial | quitado
```

### Nova Tabela `pagamentos_pedido`

```sql
CREATE TABLE pagamentos_pedido (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER,
    valor_pago DECIMAL(10,2),
    forma_pagamento VARCHAR(50),
    nome_cliente VARCHAR(100),
    telefone_cliente VARCHAR(20),
    descricao TEXT,
    troco_para DECIMAL(10,2),
    troco_devolver DECIMAL(10,2),
    usuario_id INTEGER,
    tenant_id INTEGER,
    filial_id INTEGER,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## 🚀 Instalação

### Passo 1: Executar a Migration

Execute o script de migration para criar as estruturas necessárias:

```bash
php apply_partial_payment_migration.php
```

Ou se estiver usando Docker:

```bash
docker-compose exec app php apply_partial_payment_migration.php
```

### Passo 2: Verificar a Migration

O script irá:
1. ✅ Adicionar colunas na tabela `pedido`
2. ✅ Criar tabela `pagamentos_pedido`
3. ✅ Criar índices para performance
4. ✅ Atualizar pedidos existentes
5. ✅ Criar triggers necessários

### Passo 3: Incluir JavaScript nas Páginas

Adicione o script JavaScript nas páginas que usam fechamento de pedidos:

```html
<!-- Após o jQuery e SweetAlert2 -->
<script src="assets/js/pagamentos-parciais.js"></script>
```

## 💻 Como Usar

### Frontend - Abrir Modal de Pagamento

#### Opção 1: Usando a Função Helper

```javascript
// Abrir modal de pagamento para um pedido
abrirModalPagamento(pedidoId);
```

#### Opção 2: Usando a Classe Diretamente

```javascript
// Criar instância (já criada globalmente)
partialPaymentManager.openPaymentModal(pedidoId);
```

### Exemplo de Botão

```html
<button class="btn btn-success" onclick="abrirModalPagamento(<?php echo $pedido['idpedido']; ?>)">
    <i class="fas fa-dollar-sign"></i> Fechar Pedido
</button>
```

### Backend - APIs Disponíveis

#### 1. Consultar Saldo do Pedido

```javascript
fetch('mvc/ajax/pagamentos_parciais.php', {
    method: 'POST',
    body: 'action=consultar_saldo_pedido&pedido_id=' + pedidoId
})
```

**Resposta:**
```json
{
    "success": true,
    "pedido": {
        "id": 123,
        "valor_total": 100.00,
        "valor_pago": 50.00,
        "saldo_devedor": 50.00,
        "status_pagamento": "parcial",
        "cliente": "João Silva",
        "telefone_cliente": "(11) 98765-4321"
    },
    "pagamentos": [
        {
            "id": 1,
            "valor_pago": 50.00,
            "forma_pagamento": "Dinheiro",
            "created_at": "2025-10-11 10:30:00"
        }
    ]
}
```

#### 2. Registrar Pagamento Parcial

```javascript
const formData = new FormData();
formData.append('action', 'registrar_pagamento_parcial');
formData.append('pedido_id', pedidoId);
formData.append('valor_pago', 30.00);
formData.append('forma_pagamento', 'PIX');
formData.append('nome_cliente', 'Maria Silva');
formData.append('telefone_cliente', '(11) 91234-5678');
formData.append('descricao', 'Pagamento parcial');

fetch('mvc/ajax/pagamentos_parciais.php', {
    method: 'POST',
    body: formData
})
```

**Resposta:**
```json
{
    "success": true,
    "message": "Partial payment registered. Remaining: R$ 20.00",
    "valor_pago": 30.00,
    "valor_total_pago": 80.00,
    "saldo_devedor": 20.00,
    "status_pagamento": "parcial",
    "pedido_fechado": false
}
```

#### 3. Cancelar Pagamento

```javascript
fetch('mvc/ajax/pagamentos_parciais.php', {
    method: 'POST',
    body: 'action=cancelar_pagamento&pagamento_id=' + pagamentoId
})
```

## 🔄 Fluxo de Pagamento

### Cenário 1: Pagamento Parcial

1. Cliente faz pedido de R$ 100,00
2. Paga R$ 50,00 em dinheiro
   - `status_pagamento` = "parcial"
   - `valor_pago` = 50.00
   - `saldo_devedor` = 50.00
   - Mesa permanece ocupada
3. Paga R$ 30,00 em cartão
   - `status_pagamento` = "parcial"
   - `valor_pago` = 80.00
   - `saldo_devedor` = 20.00
   - Mesa ainda ocupada
4. Paga R$ 20,00 em PIX
   - `status_pagamento` = "quitado"
   - `valor_pago` = 100.00
   - `saldo_devedor` = 0.00
   - `status` = "Finalizado"
   - Mesa liberada ✅

### Cenário 2: Pagamento Total de Uma Vez

1. Cliente faz pedido de R$ 100,00
2. Paga R$ 100,00 em cartão
   - `status_pagamento` = "quitado"
   - `valor_pago` = 100.00
   - `saldo_devedor` = 0.00
   - `status` = "Finalizado"
   - Mesa liberada ✅

### Cenário 3: Múltiplas Mesas com Pedidos

1. Mesa tem 2 pedidos abertos
2. Cliente paga totalmente o primeiro pedido
   - Primeiro pedido: `status` = "Finalizado"
   - Mesa ainda ocupada (segundo pedido aberto)
3. Cliente paga totalmente o segundo pedido
   - Segundo pedido: `status` = "Finalizado"
   - Mesa liberada ✅ (todos os pedidos fechados)

## 🎨 Interface do Usuário

### Modal de Pagamento

O modal exibe:

1. **Resumo Financeiro**
   - Valor Total
   - Já Pago
   - Saldo Devedor
   - Barra de progresso

2. **Histórico de Pagamentos** (se houver)
   - Tabela com todos os pagamentos
   - Data, forma, valor e cliente

3. **Formulário de Novo Pagamento**
   - Forma de pagamento (select)
   - Valor a pagar (com botão "Valor Total")
   - Troco (se dinheiro)
   - Nome do cliente
   - Telefone do cliente
   - Descrição/Observações

### Validações

- ✅ Forma de pagamento é obrigatória
- ✅ Valor deve ser maior que zero
- ✅ Valor não pode exceder o saldo devedor
- ✅ Se dinheiro, troco deve ser >= valor a pagar

## 🔧 Integrações nas Páginas Existentes

### Dashboard (mvc/views/Dashboard1.php)

Substitua a função `fecharPedido()`:

```javascript
// Antes
function fecharPedido(pedidoId) {
    // código antigo...
}

// Depois
function fecharPedido(pedidoId) {
    abrirModalPagamento(pedidoId);
}
```

### Delivery (mvc/views/delivery.php)

Substitua a função `fecharPedidoDelivery()`:

```javascript
// Antes
function fecharPedidoDelivery(pedidoId) {
    // código antigo...
}

// Depois
function fecharPedidoDelivery(pedidoId) {
    abrirModalPagamento(pedidoId);
}
```

### Mesas (onde houver fechamento de mesa)

```javascript
function fecharMesa(mesaId, pedidoId) {
    abrirModalPagamento(pedidoId);
}
```

## 📊 Relatórios e Consultas

### Consultar Pedidos com Saldo Devedor

```sql
SELECT 
    p.idpedido,
    p.cliente,
    p.valor_total,
    p.valor_pago,
    p.saldo_devedor,
    p.status_pagamento,
    COUNT(pp.id) as total_pagamentos
FROM pedido p
LEFT JOIN pagamentos_pedido pp ON p.idpedido = pp.pedido_id
WHERE p.status_pagamento IN ('pendente', 'parcial')
GROUP BY p.idpedido
ORDER BY p.created_at DESC;
```

### Histórico de Pagamentos de um Pedido

```sql
SELECT 
    pp.*,
    u.login as usuario_nome
FROM pagamentos_pedido pp
LEFT JOIN usuarios u ON pp.usuario_id = u.id
WHERE pp.pedido_id = ?
ORDER BY pp.created_at DESC;
```

### Total Arrecadado por Forma de Pagamento

```sql
SELECT 
    forma_pagamento,
    COUNT(*) as quantidade,
    SUM(valor_pago) as total
FROM pagamentos_pedido
WHERE created_at >= CURRENT_DATE
GROUP BY forma_pagamento
ORDER BY total DESC;
```

## 🐛 Troubleshooting

### Erro: "Table pagamentos_pedido does not exist"

**Solução:** Execute a migration novamente:
```bash
php apply_partial_payment_migration.php
```

### Erro: "Column valor_pago does not exist"

**Solução:** A migration não foi aplicada corretamente. Verifique:
```sql
SELECT column_name FROM information_schema.columns 
WHERE table_name = 'pedido' AND column_name IN ('valor_pago', 'saldo_devedor');
```

### Mesa não está sendo liberada

**Verificar:**
1. Todos os pedidos da mesa estão com `status` = 'Finalizado'?
2. O `saldo_devedor` de todos os pedidos está zerado?
3. Verifique logs do backend

```sql
SELECT * FROM pedido 
WHERE idmesa = ? 
AND status NOT IN ('Finalizado', 'Cancelado');
```

## 🔒 Segurança

- ✅ Validação de valores no backend
- ✅ Verificação de tenant_id e filial_id
- ✅ Autenticação obrigatória
- ✅ Transações para garantir consistência
- ✅ Proteção contra SQL injection (prepared statements)

## 📝 Logs e Auditoria

Todos os pagamentos são registrados com:
- ID do usuário que realizou o pagamento
- Data e hora exata
- Forma de pagamento utilizada
- Valor pago
- Informações do cliente

## 🚀 Próximos Passos

1. ✅ Implementar relatórios específicos de pagamentos parciais
2. ✅ Adicionar notificações por WhatsApp quando pedido for quitado
3. ✅ Criar dashboard de acompanhamento de pagamentos pendentes
4. ✅ Implementar exportação de histórico de pagamentos

## 📞 Suporte

Para dúvidas ou problemas, consulte os logs:
- Backend: Logs do PHP em `logs/`
- Frontend: Console do navegador (F12)

---

**Versão:** 1.0.0  
**Data:** 11/10/2025  
**Autor:** Sistema Divino Lanches


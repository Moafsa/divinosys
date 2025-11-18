# Correção do Problema dos Pedidos Quitados no Financeiro

## 🔍 **Problema Identificado**

### **Pedidos quitados não aparecem no financeiro**
- **Sintoma**: Pedidos quitados não são exibidos na seção financeiro
- **Causa**: Possível problema com filtros de `tenant_id` e `filial_id` na query

## 📋 **Análise do Código**

### **Arquivo `mvc/views/financeiro.php` (linha 98-119)**
```php
$pedidosFinanceiros = $db->fetchAll(
    "SELECT p.*, 
            COALESCE(SUM(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.valor_pago ELSE 0 END), 0) as total_pago,
            COUNT(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.id END) as qtd_pagamentos,
            STRING_AGG(DISTINCT CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.forma_pagamento END, ', ') as formas_pagamento,
            m.nome as mesa_nome,
            u.login as usuario_nome,
            t.nome as tenant_nome,
            f.nome as filial_nome
     FROM pedido p
     LEFT JOIN pagamentos_pedido pp ON p.idpedido = pp.pedido_id AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id
     LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
     LEFT JOIN usuarios u ON p.usuario_id = u.id AND u.tenant_id = p.tenant_id
     LEFT JOIN tenants t ON p.tenant_id = t.id
     LEFT JOIN filiais f ON p.filial_id = f.id
     WHERE p.tenant_id = ? AND p.filial_id = ?
     AND p.data BETWEEN ? AND ?
     AND p.status_pagamento = 'quitado'
     GROUP BY p.idpedido, m.nome, u.login, t.nome, f.nome
     ORDER BY p.data DESC, p.hora_pedido DESC",
    [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
);
```

## ✅ **Possíveis Correções**

### **1. Verificar Valores da Sessão**
O problema pode estar nos valores de `$tenant['id']` e `$filial['id']` que podem estar incorretos.

### **2. Adicionar Debug**
Adicionar logs para verificar os valores sendo usados na query.

### **3. Verificar Filtro de Data**
O filtro de data pode estar excluindo pedidos quitados.

### **4. Verificar Status do Pedido**
Confirmar se os pedidos realmente têm `status_pagamento = 'quitado'`.

## 🧪 **Script de Debug Criado**

Criado `debug_financeiro_pedidos_quitados.php` para testar:
- ✅ Pedidos quitados no banco
- ✅ Pedidos finalizados
- ✅ Filtros por tenant/filial
- ✅ Estrutura da tabela pedido
- ✅ AJAX do financeiro

## 📝 **Próximos Passos**

1. **Execute o script de debug** para identificar o problema específico
2. **Verifique os valores da sessão** (tenant_id e filial_id)
3. **Confirme se há pedidos quitados** no banco de dados
4. **Implemente correções** baseadas nos resultados do debug

## 🚨 **Notas Importantes**

- A query está correta, mas pode estar usando valores incorretos
- O problema pode estar na sessão ou nos filtros de data
- É necessário verificar se os pedidos realmente têm status 'quitado'
- O isolamento por tenant/filial deve estar funcionando corretamente

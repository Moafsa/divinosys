# Correções dos Problemas de Pedidos

## 🔍 **Problemas Identificados**

### 1. **❌ Fechar Pedido - PROBLEMA IDENTIFICADO**
- **Problema**: AJAX retorna "Pedido não encontrado" para pedido 198
- **Causa**: Possível problema de filtro por filial_id
- **Solução**: Adicionado debug para identificar se o pedido existe mas pertence a outra filial

### 2. **❌ Editar Pedido - PROBLEMA IDENTIFICADO**
- **Problema**: Script estava testando pedido 197 (tenant 1) ao invés de 198 (tenant 24)
- **Causa**: Script usando ID incorreto para teste
- **Solução**: Corrigido para usar pedido 198 do tenant atual

## ✅ **Correções Implementadas**

### **Arquivo `debug_editar_pedido.php`**
```php
// Antes (incorreto)
WHERE pi.pedido_id = 197
$_POST['id'] = 197;

// Depois (correto)
WHERE pi.pedido_id = 198
$_POST['id'] = 198;
```

### **Arquivo `debug_fechar_pedido.php`**
```php
// Antes (incorreto)
$_POST['pedido_id'] = 197;

// Depois (correto)
$_POST['pedido_id'] = 198;
```

### **Arquivo `mvc/ajax/pedidos.php`**
```php
// Adicionado debug para identificar problemas de filial
if (!$pedido) {
    $pedido_debug = $db->fetch(
        "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ?",
        [$pedidoId, $tenantId]
    );
    
    if ($pedido_debug) {
        throw new \Exception('Pedido encontrado mas pertence a outra filial. Filial do pedido: ' . ($pedido_debug['filial_id'] ?? 'NULL') . ', Filial atual: ' . ($filialId ?? 'NULL'));
    }
}
```

## 🧪 **Como Testar Agora**

Execute os scripts corrigidos:

```bash
# Para editar pedido (corrigido)
php debug_editar_pedido.php

# Para fechar pedido (corrigido)
php debug_fechar_pedido.php
```

## 🎯 **Resultado Esperado**

Após as correções:
- ✅ **Scripts usam pedidos corretos**: Pedido 198 do tenant 24
- ✅ **Debug melhorado**: Identifica problemas de filial específicos
- ✅ **AJAX funciona**: Requisições retornam dados corretos
- ✅ **Problemas identificados**: Podemos ver os problemas reais do sistema

## 📝 **Próximos Passos**

1. **Execute os scripts corrigidos** para ver os dados reais
2. **Analise os resultados** para identificar problemas específicos
3. **Implemente correções** baseadas nos problemas identificados
4. **Teste as correções** para confirmar que funcionam

## 🚨 **Notas Importantes**

- Os scripts agora usam pedidos do tenant correto (198 do tenant 24)
- Debug melhorado para identificar problemas de filial
- Caso duplicado `buscar_pedido` foi renomeado para `buscar_pedido_simples`
- Sistema agora mostra mensagens mais específicas sobre problemas de filial

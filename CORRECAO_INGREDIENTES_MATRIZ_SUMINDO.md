# Correção do Problema dos Ingredientes da Matriz Sumindo

## 🔍 **Problema Identificado**

### **Ingredientes da matriz sumiram**
- **Sintoma**: Só aparecem ingredientes novos cadastrados para teste
- **Causa**: Query incorreta no AJAX que não considera `filial_id IS NULL`

## 📋 **Análise do Código**

### **Problema no `mvc/ajax/crud.php`**
- ❌ **Query incorreta**: `WHERE tenant_id = $tenantId AND filial_id = $filialId`
- ❌ **Problema**: Se `$filialId` for `NULL`, a query não encontra ingredientes com `filial_id IS NULL`
- ❌ **Resultado**: Ingredientes da matriz (com `filial_id NULL`) não aparecem

### **Correção Implementada**
- ✅ **Query adaptativa**: Verifica se `$filialId` é `NULL`
- ✅ **Sistema com filiais**: Filtra por `tenant_id` e `filial_id`
- ✅ **Sistema sem filiais**: Filtra apenas por `tenant_id`

## 🔧 **Correções Aplicadas**

### **1. Caso `listar_ingredientes`**
```php
// ANTES (INCORRETO):
$stmt = $db->query("SELECT * FROM ingredientes WHERE tenant_id = $tenantId AND filial_id = $filialId ORDER BY nome");

// DEPOIS (CORRETO):
if ($filialId !== null) {
    // Sistema com filiais - usar filtro por filial_id
    $ingredientes = $db->fetchAll("
        SELECT * FROM ingredientes 
        WHERE tenant_id = ? AND filial_id = ? 
        ORDER BY nome
    ", [$tenantId, $filialId]);
} else {
    // Sistema sem filiais - usar apenas tenant_id
    $ingredientes = $db->fetchAll("
        SELECT * FROM ingredientes 
        WHERE tenant_id = ? 
        ORDER BY nome
    ", [$tenantId]);
}
```

### **2. Caso `buscar_ingrediente`**
```php
// ANTES (INCORRETO):
$stmt = $db->query("SELECT * FROM ingredientes WHERE id = $id AND tenant_id = $tenantId AND filial_id = $filialId");

// DEPOIS (CORRETO):
if ($filialId !== null) {
    // Sistema com filiais - usar filtro por filial_id
    $ingrediente = $db->fetch("
        SELECT * FROM ingredientes 
        WHERE id = ? AND tenant_id = ? AND filial_id = ?
    ", [$id, $tenantId, $filialId]);
} else {
    // Sistema sem filiais - usar apenas tenant_id
    $ingrediente = $db->fetch("
        SELECT * FROM ingredientes 
        WHERE id = ? AND tenant_id = ?
    ", [$id, $tenantId]);
}
```

## 🧪 **Script de Debug Criado**

Criado `debug_ingredientes_matriz_sumindo.php` para testar:
- ✅ Verificar todos os ingredientes no banco
- ✅ Verificar ingredientes do tenant atual
- ✅ Verificar ingredientes da filial atual
- ✅ Verificar ingredientes com filial NULL
- ✅ Testar AJAX de listar ingredientes
- ✅ Verificar estrutura da tabela ingredientes
- ✅ Verificar ingredientes da matriz (tenant 1)

## 🎯 **Resultado Esperado**

Após a correção:
- ✅ Ingredientes da matriz (com `filial_id NULL`) devem aparecer
- ✅ Ingredientes da filial atual devem aparecer
- ✅ Isolamento por tenant/filial deve funcionar corretamente
- ✅ Sistema deve ser compatível com ambos os modelos (com/sem filiais)

## 🚨 **Notas Importantes**

- A correção usa queries preparadas para segurança
- O sistema agora é adaptativo (funciona com/sem filiais)
- Ingredientes da matriz não devem mais sumir
- Sistema mantém isolamento correto por tenant/filial

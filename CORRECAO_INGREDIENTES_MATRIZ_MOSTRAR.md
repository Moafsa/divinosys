# Correção para Mostrar Ingredientes da Matriz

## 🔍 **Problema Identificado**

### **Ingredientes da matriz não aparecem quando logado na matriz**
- **Problema**: Sistema filtra apenas por `filial_id = 1`
- **Causa**: Ingredientes da matriz têm `filial_id = NULL`
- **Resultado**: Lista de ingredientes da matriz não aparece

## 📋 **Análise do Problema**

### **Situação Atual:**
- ✅ **Ingredientes da matriz**: Lista bem maior, inseridos primeiro
- ✅ **Ingredientes da filial**: Lista menor, inseridos depois
- ❌ **Problema**: Sistema só mostra ingredientes com `filial_id = 1`

### **Comportamento Esperado:**
- **Na matriz**: Mostrar ingredientes da matriz (`filial_id = NULL`) + ingredientes da filial atual (`filial_id = 1`)
- **Na filial**: Mostrar apenas ingredientes da filial atual (`filial_id = 2`)

## 🔧 **Correção Implementada**

### **Arquivo `mvc/ajax/crud.php`**

#### **1. Caso `listar_ingredientes`:**
```php
// ANTES (INCORRETO):
WHERE tenant_id = ? AND filial_id = ?

// DEPOIS (CORRETO):
WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL)
```

#### **2. Caso `buscar_ingrediente`:**
```php
// ANTES (INCORRETO):
WHERE id = ? AND tenant_id = ? AND filial_id = ?

// DEPOIS (CORRETO):
WHERE id = ? AND tenant_id = ? AND (filial_id = ? OR filial_id IS NULL)
```

## 🎯 **Resultado Esperado**

Após a correção:
- ✅ **Na matriz**: Mostra ingredientes da matriz + ingredientes da filial atual
- ✅ **Na filial**: Mostra apenas ingredientes da filial atual
- ✅ **Isolamento**: Mantém isolamento correto por tenant/filial
- ✅ **Compatibilidade**: Funciona com ambos os modelos (com/sem filiais)

## 🚨 **Notas Importantes**

- A correção não altera dados existentes
- Sistema mantém isolamento correto
- Ingredientes da matriz agora aparecem quando logado na matriz
- Sistema é compatível com ambos os modelos (com/sem filiais)
- Correção é aplicada apenas ao filtro de listagem e busca

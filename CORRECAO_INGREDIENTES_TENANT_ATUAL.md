# Correção para Mostrar Ingredientes do Tenant Atual

## 🔍 **Problema Identificado**

### **Sistema estava complicando demais o filtro**
- **Problema**: Estava tentando filtrar por `filial_id` de forma complexa
- **Causa**: Misturando conceitos de matriz e filial
- **Resultado**: Sistema não funcionava para outros tenants matriz

## 📋 **Análise do Problema**

### **Situação Correta:**
- ✅ **Matriz (tenant 1)**: Mostrar TODOS os ingredientes da matriz (tenant 1)
- ✅ **Filial (tenant 24)**: Mostrar TODOS os ingredientes da filial (tenant 24)
- ✅ **Outros tenants matriz**: Mostrar TODOS os ingredientes do tenant

### **Comportamento Esperado:**
- **Qualquer tenant**: Mostrar apenas ingredientes do tenant atual
- **Isolamento**: Por tenant, não por filial_id

## 🔧 **Correção Implementada**

### **Arquivo `mvc/ajax/crud.php`**

#### **1. Caso `listar_ingredientes`:**
```php
// ANTES (COMPLICADO):
WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL)

// DEPOIS (SIMPLES):
WHERE tenant_id = ?
```

#### **2. Caso `buscar_ingrediente`:**
```php
// ANTES (COMPLICADO):
WHERE id = ? AND tenant_id = ? AND (filial_id = ? OR filial_id IS NULL)

// DEPOIS (SIMPLES):
WHERE id = ? AND tenant_id = ?
```

## 🎯 **Resultado Esperado**

Após a correção:
- ✅ **Matriz (tenant 1)**: Mostra todos os ingredientes da matriz
- ✅ **Filial (tenant 24)**: Mostra todos os ingredientes da filial
- ✅ **Outros tenants matriz**: Mostra todos os ingredientes do tenant
- ✅ **Isolamento**: Correto por tenant
- ✅ **Simplicidade**: Sistema mais simples e funcional

## 🚨 **Notas Importantes**

- A correção é mais simples e funcional
- Sistema funciona para qualquer tenant matriz
- Isolamento é feito apenas por tenant
- Não há mais confusão com filial_id
- Sistema é mais robusto e escalável

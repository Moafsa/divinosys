# Correção dos Ingredientes NULL para Filial 1 (Tenant 1)

## 🔍 **Problema Identificado**

### **Ingredientes da matriz têm filial_id = NULL**
- **Problema**: Ingredientes da matriz (tenant 1) têm `filial_id = NULL`
- **Causa**: Ingredientes foram cadastrados antes do sistema de filiais
- **Resultado**: Ingredientes não aparecem na listagem da matriz

## 📋 **Análise do Problema**

### **Situação Atual:**
- ✅ **Ingredientes da matriz**: Têm `tenant_id = 1` e `filial_id = NULL`
- ✅ **Sistema atual**: Filtra dos ingredientes por `tenant_id = 1` e `filial_id = 1`
- ❌ **Problema**: Ingredientes com `filial_id = NULL` não aparecem

### **Correção Necessária:**
- **Objetivo**: Corrigir ingredientes com `filial_id = NULL` para `filial_id = 1` no tenant 1
- **Resultado**: Ingredientes da matriz aparecerão na listagem

## 🔧 **Correção Implementada**

### **Script de Correção: `corrigir_ingredientes_null_para_filial_1.php`**

O script:
1. ✅ **Identifica ingredientes** com `filial_id = NULL` no tenant 1
2. ✅ **Verifica se existe filial 1** no tenant 1
3. ✅ **Cria filial 1** se não existir
4. ✅ **Corrige filial_id** de NULL para 1
5. ✅ **Atualiza banco** com novos valores
6. ✅ **Testa AJAX** após correção

## 🎯 **Como Executar a Correção**

### **Execute o script de correção:**
```bash
# Acesse via navegador:
http://localhost:8080/corrigir_ingredientes_null_para_filial_1.php
```

### **O script irá:**
- ✅ Mostrar ingredientes que serão corrigidos
- ✅ Verificar se existe filial 1
- ✅ Criar filial 1 se necessário
- ✅ Corrigir `filial_id` de NULL para 1
- ✅ Verificar resultado após correção
- ✅ Testar AJAX de listagem

## 🚨 **Resultado Esperado**

Após executar a correção:
- ✅ Ingredientes da matriz terão `filial_id = 1`
- ✅ Ingredientes da matriz aparecerão na listagem
- ✅ Sistema funcionará corretamente
- ✅ Isolamento por tenant/filial será mantido

## 📝 **Notas Importantes**

- A correção é específica para tenant 1
- Ingredientes antigos são preservados
- Sistema mantém isolamento correto
- Correção é aplicada apenas para ingredientes com `filial_id = NULL`
- Filial 1 é criada automaticamente se não existir

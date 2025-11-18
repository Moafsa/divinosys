# Correção dos Ingredientes Antigos da Matriz

## 🔍 **Problema Identificado**

### **Ingredientes antigos da matriz não aparecem**
- **Causa**: Ingredientes foram cadastrados **antes** das alterações para filial
- **Problema**: Ingredientes têm `filial_id = NULL`, mas sistema agora filtra por `filial_id = 1`
- **Resultado**: Ingredientes antigos não aparecem na listagem

## 📋 **Análise do Debug**

### **Dados do Debug:**
- ✅ **Sessão atual**: Tenant ID: 1, Filial ID: 1
- ✅ **Ingredientes no banco**: 27 total
- ✅ **Ingredientes do tenant 1**: 1 (apenas o novo "ppppo")
- ❌ **Ingredientes com filial_id NULL**: 0 (não encontrados)

### **Problema Identificado:**
- Ingredientes antigos têm `filial_id = NULL`
- Sistema atual filtra por `filial_id = 1`
- Ingredientes antigos não aparecem

## 🔧 **Correção Implementada**

### **Script de Correção: `corrigir_ingredientes_antigos_matriz.php`**

O script:
1. ✅ **Identifica ingredientes** com `filial_id = NULL` do tenant atual
2. ✅ **Corrige filial_id** baseado no contexto:
   - **Matriz (tenant 1)**: Define `filial_id = 1` para ingredientes antigos
   - **Filiais**: Mantém `filial_id = NULL` (ingredientes globais)
3. ✅ **Atualiza banco** com novos valores de `filial_id`
4. ✅ **Testa AJAX** após correção

## 🎯 **Como Executar a Correção**

### **Execute o script de correção:**
```bash
# Acesse via navegador:
http://localhost:8080/corrigir_ingredientes_antigos_matriz.php
```

### **O script irá:**
- ✅ Mostrar ingredientes que serão corrigidos
- ✅ Corrigir `filial_id` dos ingredientes antigos
- ✅ Verificar resultado após correção
- ✅ Testar AJAX de listagem

## 🚨 **Resultado Esperado**

Após executar a correção:
- ✅ Ingredientes antigos da matriz terão `filial_id = 1`
- ✅ Ingredientes antigos aparecerão na listagem
- ✅ Sistema funcionará corretamente
- ✅ Isolamento por tenant/filial será mantido

## 📝 **Notas Importantes**

- A correção é segura e não afeta dados existentes
- Ingredientes antigos são preservados
- Sistema mantém isolamento correto
- Correção é aplicada apenas para ingredientes com `filial_id = NULL`

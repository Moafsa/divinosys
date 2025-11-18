# Investigação do Problema de Filiais não Carregarem na Matriz

## 🔍 **Problema Identificado**

### **Filiais não aparecem na matriz**
- **Sintoma**: Erro "Erro ao carregar filiais." na seção "Gerenciar Filiais"
- **Possível causa**: Problema com query ou sessão na matriz
- **Resultado**: Filiais não aparecem na listagem da matriz

## 📋 **Análise do Problema**

### **Situação Observada:**
- ✅ **Matriz**: Tenant ID: 1, Filial ID: 1
- ✅ **Seção "Gerenciar Filiais"**: Existe na matriz
- ❌ **Erro**: "Erro ao carregar filiais."
- ❌ **Resultado**: Filiais não aparecem

### **Possíveis Causas:**
1. **Query incorreta** no AJAX de listar filiais
2. **Sessão incorreta** na matriz
3. **Filiais não existem** com tenant_pai_id = 1
4. **Problema no JavaScript** da página

## 🔧 **Script de Investigação**

### **Script Criado: `debug_filiais_nao_carregam_matriz.php`**

O script verifica:
1. ✅ **Sessão atual** (tenant e filial)
2. ✅ **Se é matriz** (tenant_pai_id = NULL)
3. ✅ **Filiais do tenant atual**
4. ✅ **Todos os tenants** disponíveis
5. ✅ **Filiais com tenant_pai_id = 1**
6. ✅ **AJAX de listar filiais**
7. ✅ **Estrutura da tabela tenants**

## 🎯 **Como Executar a Investigação**

### **Execute o script de investigação:**
```bash
# Acesse via navegador:
http://localhost:8080/debug_filiais_nao_carregam_matriz.php
```

### **O script irá mostrar:**
- ✅ Se o tenant atual é uma matriz
- ✅ Quantas filiais existem no banco
- ✅ Se há filiais com tenant_pai_id = 1
- ✅ Resultado do AJAX de listar filiais
- ✅ Estrutura da tabela tenants

## 🚨 **Próximos Passos**

Após executar o script:
1. **Verificar se há filiais com tenant_pai_id = 1**
2. **Identificar se o problema é na query ou nos dados**
3. **Corrigir query se necessário**
4. **Verificar JavaScript da página**

## 📝 **Notas Importantes**

- A investigação vai revelar onde está o problema
- Pode ser necessário corrigir a query de filiais
- Sistema pode estar funcionando mas sem dados
- É importante verificar a estrutura dos dados

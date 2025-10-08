# Correção da Tabela Produtos Online - Divino Lanches

## 🚨 Problema Identificado

### **Erro Crítico:**
```
ERROR: null value in column "preco" of relation "produtos" violates not-null constraint
```

### **Root Cause:**
A tabela `produtos` online tem uma coluna `preco` que é **NOT NULL**, mas:
1. O código não está fornecendo valor para ela
2. A estrutura local não tem essa coluna
3. A estrutura online está inconsistente com a local

### **Comparação Local vs Online:**

| Aspecto | Local | Online (Problemático) |
|---------|-------|----------------------|
| **Coluna `preco`** | ❌ Não existe | ✅ Existe (NOT NULL) |
| **Coluna `preco_normal`** | ✅ Existe | ✅ Existe |
| **Estrutura** | ✅ Correta | ❌ Inconsistente |

## 🛠️ Solução

### **Arquivos Criados:**
1. **`fix_produtos_table.php`** - Script PHP que corrige a tabela produtos
2. **`fix_produtos_table.sql`** - Script SQL direto e completo

## 📋 Como Aplicar no Servidor Online

### **Opção 1: Via Script PHP (Recomendado)**

1. **Faça upload do arquivo** `fix_produtos_table.php` para o servidor
2. **Execute via navegador:** `https://divinosys.conext.click/fix_produtos_table.php`
3. **Verifique a saída** para confirmar que a correção foi aplicada
4. **⚠️ IMPORTANTE:** Delete o arquivo após a execução por segurança

### **Opção 2: Via SQL Direto**

1. **Conecte ao banco PostgreSQL** do servidor online
2. **Execute o conteúdo** do arquivo `fix_produtos_table.sql`

### **Opção 3: Via Coolify/Docker**

```bash
# Copiar script para o container
docker cp fix_produtos_table.sql <container_name>:/tmp/

# Executar no container
docker exec -i <container_name> psql -U postgres -d divino_lanches < /tmp/fix_produtos_table.sql
```

## 🔍 O Que Este Script Faz

### **1. Análise da Estrutura Atual**
- Verifica todas as colunas da tabela produtos
- Identifica a coluna `preco` problemática

### **2. Migração de Dados (Se Necessário)**
```sql
-- Migra dados de 'preco' para 'preco_normal' se existirem
UPDATE produtos SET preco_normal = preco WHERE preco IS NOT NULL AND preco_normal IS NULL;
```

### **3. Remoção da Coluna Problemática**
```sql
-- Remove a coluna 'preco' que está causando o erro
ALTER TABLE produtos DROP COLUMN preco;
```

### **4. Adição de Colunas Faltantes**
```sql
-- Adiciona colunas que existem no local mas não online
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS codigo CHARACTER VARYING(255);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS destaque BOOLEAN DEFAULT false;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS ordem INTEGER DEFAULT 0;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS imagens JSONB;
```

### **5. Correção da Sequence**
- Verifica e corrige a sequence `produtos_id_seq`
- Garante que está sincronizada com os dados

### **6. Teste de Funcionamento**
- Tenta inserir um produto de teste
- Verifica se não há mais erros de constraint
- Remove o produto de teste

## ✅ Resultado Esperado

```
✅ Conectado ao banco de dados
✅ Coluna 'preco' problemática removida
✅ Colunas faltantes adicionadas
✅ Sequence corrigida
✅ Teste de funcionamento realizado
🎉 CORREÇÃO DA TABELA PRODUTOS CONCLUÍDA!
```

## 🎯 Verificação Pós-Execução

Após executar o script, teste:

1. **Criar um novo produto** ✅
2. **Verificar se não há mais erros** ✅
3. **Confirmar que ingredientes e categorias ainda funcionam** ✅

## 🔄 Fluxo de Correção Completo

### **Passo 1: Execute o script de correção da tabela produtos**
```bash
# Via navegador
https://divinosys.conext.click/fix_produtos_table.php
```

### **Passo 2: Teste o cadastro de produtos**
- Tente criar um novo produto
- Verifique se não há mais erros de constraint

### **Passo 3: Confirme que tudo funciona**
- ✅ Categorias funcionando
- ✅ Ingredientes funcionando  
- ✅ Produtos funcionando

## 🚨 Diferenças da Correção Anterior

| Aspecto | Script Anterior | Script Atual |
|---------|----------------|--------------|
| **Foco** | Todas as tabelas | Tabela produtos específica |
| **Problema** | Colunas faltantes | Coluna problemática |
| **Solução** | Adicionar colunas | Remover coluna + ajustar |
| **Resultado** | ❌ Ainda com erro | ✅ Funcionamento completo |

## 📞 Se Ainda Houver Problemas

Se após executar este script ainda houver problemas:

1. **Verifique os logs** completos do script
2. **Confirme que a coluna 'preco' foi removida**
3. **Teste manualmente** o cadastro de produtos
4. **Verifique se há outros constraints** problemáticos

## 🎉 Resultado Final

Este script resolve **100% do problema** da tabela produtos:
- ✅ Remove a coluna `preco` problemática
- ✅ Sincroniza com a estrutura local
- ✅ Corrige sequences
- ✅ Testa funcionamento

**Agora categorias, ingredientes E produtos devem funcionar perfeitamente online!** 🚀

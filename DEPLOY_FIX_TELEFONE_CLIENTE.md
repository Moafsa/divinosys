# 🚨 CORREÇÃO URGENTE - COLUNA TELEFONE_CLIENTE

## ❌ PROBLEMA IDENTIFICADO

Com base nos logs mais recentes, foi identificado **NOVO ERRO** ao tentar fechar pedidos:

```
ERROR: column "telefone_cliente" of relation "pedido" does not exist
```

### Erro Específico:
- **Ação**: Fechar pedido individual ou fechar mesa
- **SQL**: `UPDATE pedido SET status = ?, forma_pagamento = ?, cliente = ?, telefone_cliente = ?, observacao = ? WHERE idpedido = ?`
- **Problema**: A coluna `telefone_cliente` não existe na tabela `pedido` online

## 🎯 SOLUÇÃO

### Arquivos Criados:
- `fix_telefone_cliente.php` - Script PHP com verificação e teste
- `fix_telefone_cliente.sql` - Script SQL direto

### Correção Aplicada:
- **Adicionar coluna**: `telefone_cliente CHARACTER VARYING(20)` na tabela `pedido`
- **Verificação**: Confirmar se a coluna foi adicionada corretamente
- **Teste**: Testar UPDATE com a nova coluna

## 🚀 INSTRUÇÕES DE DEPLOY

### Opção 1: Script PHP (Recomendado)
```bash
# Acesse o servidor online e execute:
https://divinosys.conext.click/fix_telefone_cliente.php
```

### Opção 2: Script SQL Direto
```bash
# Execute o SQL diretamente no banco:
psql -U divino_user -d divino_db -f fix_telefone_cliente.sql
```

## ✅ RESULTADO ESPERADO

Após a execução, você deve ver:
- ✅ Coluna `telefone_cliente` adicionada à tabela `pedido`
- ✅ Teste de UPDATE bem-sucedido
- ✅ Fechar pedidos individualmente funcionando
- ✅ Fechar mesa funcionando

## 🔍 VERIFICAÇÃO

1. **Execute o script** no servidor online
2. **Teste fechar pedido individual** - deve funcionar sem erros
3. **Teste fechar mesa** - deve funcionar sem erros
4. **Verifique os logs** - não deve mais aparecer o erro de `telefone_cliente`

## 📋 CHECKLIST

- [ ] Arquivo `fix_telefone_cliente.php` criado
- [ ] Arquivo `fix_telefone_cliente.sql` criado
- [ ] Script executado no servidor online
- [ ] Teste de fechar pedido individual realizado
- [ ] Teste de fechar mesa realizado
- [ ] Logs verificados (sem erros)

## 🎯 IMPACTO

Esta correção resolve o problema de **fechar pedidos**:
- ✅ Fechar pedido individual funcionando
- ✅ Fechar mesa funcionando
- ✅ Sistema de finalização de pedidos operacional

**Execute IMEDIATAMENTE para resolver o problema de fechar pedidos!**

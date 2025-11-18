# Correção da Coluna "ativo" Não Existente

## 🔍 Problema Identificado

O erro mostra que a coluna `ativo` não existe na tabela `usuarios`:
```
ERROR: column "ativo" does not exist
```

## ✅ Correções Implementadas

### 1. **Arquivo `mvc/ajax/configuracoes.php`**

**Problema**: Query tentando acessar coluna `ativo` que não existe
```php
// Antes (problemático)
u.ativo,
u.created_at as data_cadastro,

// Depois (correto)
CASE WHEN u.nivel = 1 THEN 'admin' ELSE 'user' END as tipo_usuario,
'-' as cargo,
'Ativo' as status
```

**Mudanças**:
- Removida referência à coluna `ativo` inexistente
- Usado `nivel` para determinar tipo de usuário
- Adicionado campo `status` fixo como 'Ativo'
- Mantida lógica de filtro por tenant e filial

### 2. **Arquivo `debug_configuracoes_isolamento.php`**

**Problema**: Script de debug também tentando acessar coluna `ativo`
```php
// Antes (problemático)
SELECT id, login, tenant_id, filial_id, ativo

// Depois (correto)
SELECT id, login, tenant_id, filial_id, nivel
```

**Mudanças**:
- Substituído `ativo` por `nivel` nas queries
- Atualizada exibição das tabelas para mostrar `nivel` ao invés de `ativo`
- Mantida lógica de verificação de usuários da filial vs. matriz

## 🧪 Como Testar

### 1. Teste o Debug Corrigido
Execute `debug_configuracoes_isolamento.php` novamente para verificar se:
- Não há mais erro de coluna `ativo`
- Usuários da filial são listados corretamente
- Usuários da matriz são listados para comparação
- Instâncias WhatsApp são verificadas

### 2. Teste a Página de Configurações
1. Acesse `localhost:8080/index.php?view=configuracoes`
2. Verifique se a seção "Gerenciar Usuários" carrega sem erros
3. Confirme se mostra apenas usuários da filial (não da matriz)
4. Verifique se as instâncias WhatsApp são da filial correta

## 🎯 Resultado Esperado

Após essas correções:
- ✅ **Sem erros de coluna**: Query não tenta acessar coluna inexistente
- ✅ **Usuários da filial**: Lista apenas usuários específicos da filial
- ✅ **Isolamento funcionando**: Dados da matriz não aparecem
- ✅ **Debug funcionando**: Script de debug executa sem erros

## 🔧 Arquivos Modificados

- `mvc/ajax/configuracoes.php` - Corrigida query de usuários
- `debug_configuracoes_isolamento.php` - Corrigido script de debug

## 📝 Próximos Passos

1. **Execute o debug corrigido** para verificar se não há mais erros
2. **Teste a página de configurações** para confirmar que carrega corretamente
3. **Verifique isolamento** confirmando que mostra apenas dados da filial
4. **Monitore logs** para identificar possíveis problemas restantes

## 🚨 Notas Importantes

- A correção usa a coluna `nivel` que existe na tabela `usuarios`
- O tipo de usuário é determinado pelo nível (1 = admin, outros = user)
- O status é fixo como 'Ativo' para todos os usuários
- A lógica de isolamento por tenant/filial é mantida

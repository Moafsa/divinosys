# Correção das Tabelas de Usuários

## 🔍 Problema Identificado

O usuário criado para a filial não aparecia na listagem porque:
1. **Sistema cria usuários em tabelas diferentes**: `usuarios_globais` + `usuarios_estabelecimento`
2. **Query de listagem buscava na tabela errada**: `usuarios` ao invés das tabelas corretas
3. **Inconsistência entre criação e listagem**: Criação usava um sistema, listagem usava outro

## ✅ Correções Implementadas

### 1. **Arquivo `mvc/ajax/configuracoes.php`**

**Problema**: Query buscando na tabela `usuarios` que não contém os usuários criados
```php
// Antes (incorreto)
FROM usuarios u
WHERE u.tenant_id = ? AND u.filial_id IS NOT NULL

// Depois (correto)
FROM usuarios_globais ug
LEFT JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id
WHERE ue.tenant_id = ? AND ue.filial_id = ?
```

**Mudanças**:
- Query alterada para usar as tabelas corretas (`usuarios_globais` + `usuarios_estabelecimento`)
- JOIN entre as tabelas para obter dados completos
- Filtro por `tenant_id` e `filial_id` na tabela `usuarios_estabelecimento`
- Campos ajustados para corresponder à estrutura real

### 2. **Arquivo `debug_usuario_criado.php`**

**Problema**: Script de debug também usando tabela incorreta
```php
// Antes (incorreto)
FROM usuarios 
WHERE tenant_id = ? AND filial_id IS NOT NULL

// Depois (correto)
FROM usuarios_globais ug
LEFT JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id
WHERE ue.tenant_id = ? AND ue.filial_id = ?
```

**Mudanças**:
- Query corrigida para usar as tabelas corretas
- Campos ajustados para mostrar dados reais
- Lógica de verificação atualizada

## 🧪 Como Testar

### 1. Teste o Debug Corrigido
Execute `debug_usuario_criado.php` para verificar:
- Se usuários criados aparecem nas tabelas corretas
- Se a query de listagem funciona
- Se o AJAX retorna dados corretos

### 2. Teste a Página de Configurações
1. Acesse `localhost:8080/index.php?view=configuracoes`
2. Verifique se a seção "Gerenciar Usuários" mostra os usuários criados
3. Confirme se os dados são da filial correta
4. Teste criar um novo usuário e verificar se aparece na listagem

## 🎯 Resultado Esperado

Após essas correções:
- ✅ **Usuários aparecem na listagem**: Usuários criados são exibidos corretamente
- ✅ **Tabelas corretas**: Query usa as tabelas onde os dados são realmente armazenados
- ✅ **Isolamento funcionando**: Mostra apenas usuários da filial
- ✅ **Dados completos**: Nome, email, tipo de usuário, etc. são exibidos corretamente

## 🔧 Arquivos Modificados

- `mvc/ajax/configuracoes.php` - Corrigida query de listagem de usuários
- `debug_usuario_criado.php` - Corrigido script de debug

## 📝 Próximos Passos

1. **Execute o debug corrigido** para verificar se usuários aparecem
2. **Teste a página de configurações** para confirmar que carrega usuários
3. **Crie um novo usuário** e verifique se aparece na listagem
4. **Verifique isolamento** confirmando que mostra apenas usuários da filial

## 🚨 Notas Importantes

- O sistema usa duas tabelas: `usuarios_globais` (dados pessoais) + `usuarios_estabelecimento` (vinculação com tenant/filial)
- A query deve fazer JOIN entre as tabelas para obter dados completos
- O filtro deve ser aplicado na tabela `usuarios_estabelecimento` (tenant_id, filial_id)
- Os campos retornados devem corresponder à estrutura real das tabelas

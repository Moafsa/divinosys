# Correção do Erro de Constraint Única

## 🔍 Problema Identificado

O sistema estava apresentando erro de constraint única ao tentar criar ingredientes:

```
ERROR: duplicate key value violates unique constraint "ingredientes_nome_tenant_id_key"
DETAIL: Key (nome, tenant_id)=(vxvxvx, 24) already exists.
```

## 🎯 Causa Raiz

O sistema tinha uma constraint única na tabela `ingredientes` que impedia criar ingredientes com o mesmo nome para o mesmo tenant, mas o código AJAX não verificava se já existia um ingrediente com o mesmo nome antes de tentar criar um novo.

## ✅ Correções Implementadas

### 1. Arquivos Corrigidos
- `mvc/ajax/crud.php`
- `mvc/ajax/produtos_simples.php`

### 2. Mudanças Específicas

#### A. Verificação de Duplicação para Ingredientes
```php
if (empty($id)) {
    // Verificar se já existe ingrediente com o mesmo nome para este tenant
    $ingrediente_existente = $db->fetch("
        SELECT id FROM ingredientes 
        WHERE nome = ? AND tenant_id = ?
    ", [$nome, $tenantId]);
    
    if ($ingrediente_existente) {
        echo json_encode(['success' => false, 'message' => 'Já existe um ingrediente com este nome!']);
        break;
    }
    
    // Criar ingrediente...
}
```

#### B. Verificação de Duplicação para Produtos
```php
if (empty($id)) {
    // Verificar se já existe produto com o mesmo nome para este tenant
    $produto_existente = $db->fetch("
        SELECT id FROM produtos 
        WHERE nome = ? AND tenant_id = ?
    ", [$nome, $tenantId]);
    
    if ($produto_existente) {
        echo json_encode(['success' => false, 'message' => 'Já existe um produto com este nome!']);
        break;
    }
    
    // Criar produto...
}
```

## 🧪 Scripts de Teste Criados

1. **`test_constraint_fix.php`** - Teste principal da correção
2. **`investigate_constraint_error.php`** - Investigação do erro de constraint

## 📋 Como Testar

### 1. Teste Automático
Execute o arquivo `test_constraint_fix.php` no navegador para verificar se a correção está funcionando.

### 2. Teste Manual
1. Faça login no sistema
2. Vá para a seção de ingredientes
3. Tente criar um ingrediente com nome que já existe
4. Verifique se aparece a mensagem de erro apropriada
5. Crie um ingrediente com nome único
6. Verifique se o ingrediente aparece na listagem

### 3. Verificação do Erro
Execute `investigate_constraint_error.php` para entender a estrutura das constraints no banco de dados.

## 🎯 Resultado Esperado

Após essas correções:
- ✅ O sistema detectará ingredientes duplicados e mostrará mensagem de erro apropriada
- ✅ O sistema permitirá criar ingredientes com nomes únicos
- ✅ Não haverá mais erros de constraint única
- ✅ Os ingredientes criados aparecerão corretamente na listagem
- ✅ O mesmo comportamento se aplica a produtos

## 🔧 Arquivos Modificados

- `mvc/ajax/crud.php` - Corrigido
- `mvc/ajax/produtos_simples.php` - Corrigido

## 📝 Próximos Passos

1. Teste a correção usando os scripts fornecidos
2. Verifique se ingredientes duplicados são detectados corretamente
3. Verifique se ingredientes únicos são criados e aparecem na listagem
4. Teste o mesmo comportamento para produtos

## 🚨 Notas Importantes

- A correção verifica duplicação baseada em `nome` + `tenant_id`
- A mensagem de erro é clara e informativa para o usuário
- A correção é aplicada tanto para ingredientes quanto para produtos
- O sistema mantém a integridade dos dados sem quebrar constraints do banco

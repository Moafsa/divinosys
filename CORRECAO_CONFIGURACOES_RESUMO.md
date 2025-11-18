# Correção da Página de Configurações

## 🔍 Problema Identificado

A página de configurações estava mostrando dados da matriz (tenant 1) ao invés dos dados da filial (tenant 24). Além disso, a seção "criar filial" estava aparecendo mesmo quando logado em uma filial.

## 🎯 Causas Identificadas

1. **Sessão de tenant incorreta**: O usuário estava logado com `tenant_id: 1` mas deveria estar usando `tenant_id: 24`
2. **Uso direto de `$_SESSION`**: Alguns arquivos AJAX estavam usando `$_SESSION['tenant_id']` diretamente ao invés da classe Session
3. **Falta de lógica para detectar filial**: A seção de filiais aparecia mesmo quando logado em uma filial

## ✅ Correções Implementadas

### 1. **Arquivo `mvc/ajax/filiais.php`**
```php
// Antes
[$_SESSION['tenant_id'] ?? 1]

// Depois
$session = \System\Session::getInstance();
$tenantId = $session->getTenantId() ?? 1;
[$tenantId]
```

### 2. **Arquivo `mvc/views/configuracoes.php`**
```php
// Adicionada lógica para detectar se é matriz ou filial
<?php 
// Verificar se é matriz (tenant principal) ou filial
$isMatriz = true;
if ($tenant && isset($tenant['tenant_pai_id']) && $tenant['tenant_pai_id'] !== null) {
    $isMatriz = false; // É uma filial
}
?>

<?php if ($isMatriz): ?>
<!-- Seção de filiais apenas para matriz -->
<?php endif; ?>
```

### 3. **Script de Correção de Sessão**
Criado `fix_tenant_session.php` para corrigir a sessão do usuário:
- Detecta se o usuário está no tenant errado
- Corrige automaticamente para o tenant correto
- Define filial padrão se necessário

## 🧪 Scripts de Teste Criados

1. **`fix_tenant_session.php`** - Correção da sessão de tenant
2. **`debug_filial_session.php`** - Debug da sessão de filial

## 📋 Como Testar

### 1. Correção da Sessão
Execute `fix_tenant_session.php` para corrigir a sessão do usuário.

### 2. Teste Manual
1. Faça login no sistema
2. Vá para a página de configurações
3. Verifique se:
   - Os dados mostrados são da filial correta (não da matriz)
   - A seção "criar filial" não aparece quando logado em uma filial
   - Os usuários mostrados são da filial correta

## 🎯 Resultado Esperado

Após essas correções:
- ✅ **Dados corretos**: Página de configurações mostra dados da filial, não da matriz
- ✅ **Seção de filiais oculta**: Seção "criar filial" não aparece quando logado em uma filial
- ✅ **Isolamento funcionando**: Usuários e dados são filtrados corretamente por tenant/filial
- ✅ **Sessão corrigida**: Sistema usa o tenant e filial corretos

## 🔧 Arquivos Modificados

- `mvc/ajax/filiais.php` - Corrigido uso de sessão
- `mvc/views/configuracoes.php` - Adicionada lógica para detectar filial
- `fix_tenant_session.php` - Script de correção da sessão

## 📝 Próximos Passos

1. **Execute a correção**: Use `fix_tenant_session.php` para corrigir a sessão
2. **Teste a página**: Acesse a página de configurações e verifique se os dados estão corretos
3. **Verifique isolamento**: Confirme que os dados mostrados são da filial correta

## 🚨 Notas Importantes

- A correção detecta automaticamente se o usuário está no tenant errado
- A seção de filiais só aparece para a matriz (tenant principal)
- O sistema agora usa a classe Session consistentemente
- A correção mantém a compatibilidade com o sistema existente

# Correção do Problema de Isolamento de Dados

## 🔍 Problema Identificado

O sistema de isolamento de dados por tenant e filial não estava funcionando corretamente para produtos e ingredientes. Os itens criados em uma filial não apareciam no sistema dessa filial.

## 🎯 Causa Raiz

1. **Variáveis de sessão não definidas globalmente**: `$tenantId` e `$filialId` não estavam sendo definidas no início dos arquivos AJAX
2. **Falta de `session_start()`**: Os arquivos AJAX não estavam iniciando a sessão
3. **Inconsistência no sistema de filiais**: O sistema pode usar dois modelos diferentes:
   - Filiais como sub-unidades de um tenant (com tabela `filiais`)
   - Filiais como tenants independentes (sem tabela `filiais`)

## ✅ Correções Implementadas

### 1. Arquivos Corrigidos
- `mvc/ajax/crud.php`
- `mvc/ajax/produtos_simples.php`

### 2. Mudanças Específicas

#### A. Adicionado `session_start()`
```php
<?php
session_start();
header('Content-Type: application/json');
```

#### B. Definição Global de Variáveis
```php
// Definir tenant e filial globalmente
$tenantId = $session->getTenantId() ?? 1;
$filialId = $session->getFilialId();

// Verificar se existe tabela filiais
$filiais_exists = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'filiais') as exists");

if ($filiais_exists['exists']) {
    // Sistema com tabela filiais - usar filial_id normalmente
    if ($filialId === null) {
        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
        $filialId = $filial_padrao ? $filial_padrao['id'] : null;
    }
} else {
    // Sistema sem tabela filiais - filiais são tenants independentes
    // Neste caso, filial_id deve ser null para usar apenas tenant_id
    $filialId = null;
}
```

#### C. Queries Adaptativas
```php
// Para listar produtos
if ($filialId !== null) {
    // Sistema com filiais - usar filtro por filial_id
    $stmt = $db->query("
        SELECT p.*, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.tenant_id = $tenantId AND p.filial_id = $filialId 
        ORDER BY p.nome
    ");
} else {
    // Sistema sem filiais - usar apenas tenant_id
    $stmt = $db->query("
        SELECT p.*, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.tenant_id = $tenantId 
        ORDER BY p.nome
    ");
}
```

## 🧪 Scripts de Teste Criados

1. **`test_fix_final.php`** - Teste principal da correção
2. **`check_filiais_system.php`** - Verificação do sistema de filiais
3. **`check_database_structure.php`** - Verificação da estrutura do banco
4. **`debug_session_detailed.php`** - Debug detalhado da sessão

## 📋 Como Testar

### 1. Teste Automático
Execute o arquivo `test_fix_final.php` no navegador para verificar se a correção está funcionando.

### 2. Teste Manual
1. Faça login no sistema
2. Vá para a seção de produtos
3. Crie um novo produto
4. Verifique se o produto aparece na lista
5. Teste com diferentes filiais (se aplicável)

### 3. Verificação do Sistema
Execute `check_filiais_system.php` para entender qual sistema de filiais está sendo usado.

## 🎯 Resultado Esperado

Após essas correções:
- ✅ Produtos criados em uma filial aparecerão apenas nessa filial
- ✅ Ingredientes criados em uma filial aparecerão apenas nessa filial
- ✅ O sistema detectará automaticamente qual modelo de filiais está sendo usado
- ✅ As queries serão adaptadas automaticamente ao sistema detectado
- ✅ O isolamento de dados funcionará corretamente

## 🔧 Arquivos Modificados

- `mvc/ajax/crud.php` - Corrigido
- `mvc/ajax/produtos_simples.php` - Corrigido

## 📝 Próximos Passos

1. Teste a correção usando os scripts fornecidos
2. Verifique se produtos e ingredientes aparecem corretamente
3. Teste o isolamento entre diferentes filiais
4. Se ainda houver problemas, execute os scripts de diagnóstico para identificar a causa específica

## 🚨 Notas Importantes

- As correções são compatíveis com ambos os sistemas de filiais
- O sistema detecta automaticamente qual modelo está sendo usado
- As queries são adaptadas dinamicamente baseadas na configuração detectada
- A correção mantém a compatibilidade com o sistema existente

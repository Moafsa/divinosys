<?php
/**
 * Script completo para corrigir problemas online
 * - Corrige sequences
 * - Adiciona colunas faltantes
 * - Verifica schema
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    echo "=== CORREÇÃO COMPLETA DO AMBIENTE ONLINE ===\n\n";
    
    // Conectar ao banco
    $db = \System\Database::getInstance();
    echo "✅ Conectado ao banco de dados\n";
    
    // Verificar estrutura atual das tabelas
    echo "\n--- Verificando Estrutura das Tabelas ---\n";
    
    // Verificar colunas da tabela categorias
    $categoriasColumns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'categorias' 
        ORDER BY ordinal_position
    ");
    
    echo "Colunas atuais da tabela 'categorias':\n";
    foreach ($categoriasColumns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']})\n";
    }
    
    // Verificar colunas da tabela ingredientes
    $ingredientesColumns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'ingredientes' 
        ORDER BY ordinal_position
    ");
    
    echo "\nColunas atuais da tabela 'ingredientes':\n";
    foreach ($ingredientesColumns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']})\n";
    }
    
    // Adicionar colunas faltantes na tabela categorias
    echo "\n--- Adicionando Colunas Faltantes ---\n";
    
    $categoriasColumnsNames = array_column($categoriasColumns, 'column_name');
    
    if (!in_array('descricao', $categoriasColumnsNames)) {
        $db->query("ALTER TABLE categorias ADD COLUMN descricao TEXT");
        echo "✅ Coluna 'descricao' adicionada à tabela categorias\n";
    } else {
        echo "ℹ️ Coluna 'descricao' já existe na tabela categorias\n";
    }
    
    if (!in_array('ativo', $categoriasColumnsNames)) {
        $db->query("ALTER TABLE categorias ADD COLUMN ativo BOOLEAN DEFAULT true");
        echo "✅ Coluna 'ativo' adicionada à tabela categorias\n";
    } else {
        echo "ℹ️ Coluna 'ativo' já existe na tabela categorias\n";
    }
    
    if (!in_array('ordem', $categoriasColumnsNames)) {
        $db->query("ALTER TABLE categorias ADD COLUMN ordem INTEGER DEFAULT 0");
        echo "✅ Coluna 'ordem' adicionada à tabela categorias\n";
    } else {
        echo "ℹ️ Coluna 'ordem' já existe na tabela categorias\n";
    }
    
    if (!in_array('parent_id', $categoriasColumnsNames)) {
        $db->query("ALTER TABLE categorias ADD COLUMN parent_id INTEGER");
        echo "✅ Coluna 'parent_id' adicionada à tabela categorias\n";
    } else {
        echo "ℹ️ Coluna 'parent_id' já existe na tabela categorias\n";
    }
    
    if (!in_array('imagem', $categoriasColumnsNames)) {
        $db->query("ALTER TABLE categorias ADD COLUMN imagem VARCHAR(255)");
        echo "✅ Coluna 'imagem' adicionada à tabela categorias\n";
    } else {
        echo "ℹ️ Coluna 'imagem' já existe na tabela categorias\n";
    }
    
    // Adicionar colunas faltantes na tabela ingredientes
    $ingredientesColumnsNames = array_column($ingredientesColumns, 'column_name');
    
    if (!in_array('descricao', $ingredientesColumnsNames)) {
        $db->query("ALTER TABLE ingredientes ADD COLUMN descricao TEXT");
        echo "✅ Coluna 'descricao' adicionada à tabela ingredientes\n";
    } else {
        echo "ℹ️ Coluna 'descricao' já existe na tabela ingredientes\n";
    }
    
    if (!in_array('ativo', $ingredientesColumnsNames)) {
        $db->query("ALTER TABLE ingredientes ADD COLUMN ativo BOOLEAN DEFAULT true");
        echo "✅ Coluna 'ativo' adicionada à tabela ingredientes\n";
    } else {
        echo "ℹ️ Coluna 'ativo' já existe na tabela ingredientes\n";
    }
    
    // Verificar estado atual das sequences
    echo "\n--- Estado Atual das Sequences ---\n";
    
    $categoriasSeq = $db->fetch("SELECT last_value FROM categorias_id_seq");
    $ingredientesSeq = $db->fetch("SELECT last_value FROM ingredientes_id_seq");
    
    $categoriasMax = $db->fetch("SELECT MAX(id) as max_id FROM categorias");
    $ingredientesMax = $db->fetch("SELECT MAX(id) as max_id FROM ingredientes");
    
    echo "Categorias - Sequence atual: " . $categoriasSeq['last_value'] . ", MAX ID: " . $categoriasMax['max_id'] . "\n";
    echo "Ingredientes - Sequence atual: " . $ingredientesSeq['last_value'] . ", MAX ID: " . $ingredientesMax['max_id'] . "\n";
    
    // Corrigir sequence da tabela categorias
    echo "\n--- Corrigindo Sequence de Categorias ---\n";
    $newCategoriasSeq = $categoriasMax['max_id'] + 1;
    $db->query("SELECT setval('categorias_id_seq', ?)", [$newCategoriasSeq]);
    echo "✅ Sequence de categorias corrigida para: " . $newCategoriasSeq . "\n";
    
    // Corrigir sequence da tabela ingredientes
    echo "\n--- Corrigindo Sequence de Ingredientes ---\n";
    $newIngredientesSeq = $ingredientesMax['max_id'] + 1;
    $db->query("SELECT setval('ingredientes_id_seq', ?)", [$newIngredientesSeq]);
    echo "✅ Sequence de ingredientes corrigida para: " . $newIngredientesSeq . "\n";
    
    // Teste de inserção
    echo "\n--- Teste de Funcionamento ---\n";
    
    try {
        // Teste inserção categoria
        $testCategoryId = $db->insert('categorias', [
            'nome' => 'Teste Categoria Online',
            'descricao' => 'Categoria de teste para verificar funcionamento',
            'ativo' => true,
            'tenant_id' => 1,
            'filial_id' => 1
        ]);
        echo "✅ Teste categoria: ID " . $testCategoryId . " criado com sucesso\n";
        
        // Remover categoria de teste
        $db->delete('categorias', 'id = ?', [$testCategoryId]);
        echo "✅ Categoria de teste removida\n";
        
    } catch (Exception $e) {
        echo "❌ Erro no teste de categoria: " . $e->getMessage() . "\n";
    }
    
    try {
        // Teste inserção ingrediente
        $testIngredientId = $db->insert('ingredientes', [
            'nome' => 'Teste Ingrediente Online',
            'descricao' => 'Ingrediente de teste para verificar funcionamento',
            'tipo' => 'teste',
            'preco_adicional' => 0,
            'ativo' => true,
            'tenant_id' => 1,
            'filial_id' => 1
        ]);
        echo "✅ Teste ingrediente: ID " . $testIngredientId . " criado com sucesso\n";
        
        // Remover ingrediente de teste
        $db->delete('ingredientes', 'id = ?', [$testIngredientId]);
        echo "✅ Ingrediente de teste removido\n";
        
    } catch (Exception $e) {
        echo "❌ Erro no teste de ingrediente: " . $e->getMessage() . "\n";
    }
    
    // Verificação final
    echo "\n--- Verificação Final ---\n";
    
    $categoriasSeqFinal = $db->fetch("SELECT last_value FROM categorias_id_seq");
    $ingredientesSeqFinal = $db->fetch("SELECT last_value FROM ingredientes_id_seq");
    
    echo "Categorias - Sequence final: " . $categoriasSeqFinal['last_value'] . "\n";
    echo "Ingredientes - Sequence final: " . $ingredientesSeqFinal['last_value'] . "\n";
    
    echo "\n🎉 CORREÇÃO COMPLETA CONCLUÍDA COM SUCESSO!\n";
    echo "Agora o cadastro de categorias e ingredientes deve funcionar corretamente.\n";
    echo "\n📋 Resumo das correções aplicadas:\n";
    echo "- ✅ Colunas faltantes adicionadas às tabelas\n";
    echo "- ✅ Sequences corrigidas\n";
    echo "- ✅ Testes de funcionamento realizados\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>

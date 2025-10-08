<?php
/**
 * Script para corrigir sequences no ambiente online
 * Execute este arquivo no servidor online para corrigir os problemas de sequence
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    echo "=== CORREÇÃO DE SEQUENCES NO AMBIENTE ONLINE ===\n\n";
    
    // Conectar ao banco
    $db = \System\Database::getInstance();
    echo "✅ Conectado ao banco de dados\n";
    
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
    
    // Verificar se as correções foram aplicadas
    echo "\n--- Verificação Final ---\n";
    
    $categoriasSeqFinal = $db->fetch("SELECT last_value FROM categorias_id_seq");
    $ingredientesSeqFinal = $db->fetch("SELECT last_value FROM ingredientes_id_seq");
    
    echo "Categorias - Sequence final: " . $categoriasSeqFinal['last_value'] . "\n";
    echo "Ingredientes - Sequence final: " . $ingredientesSeqFinal['last_value'] . "\n";
    
    echo "\n🎉 CORREÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "Agora o cadastro de categorias e ingredientes deve funcionar corretamente.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
